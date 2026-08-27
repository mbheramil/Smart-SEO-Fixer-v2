<?php
/**
 * Background Job Queue
 * 
 * Processes long-running operations (bulk AI fix, bulk schema, etc.)
 * in small batches via WordPress cron to prevent timeouts.
 * 
 * Architecture:
 * - Jobs are stored in a DB table with status tracking
 * - Each job has a type, payload (serialized args), and item list
 * - A cron event processes pending jobs in small batches (5 items per tick)
 * - The admin UI polls for progress via AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Job_Queue {
    
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';
    const STATUS_CANCELLED  = 'cancelled';
    
    const BATCH_SIZE = 5;
    const CRON_HOOK  = 'ssf_process_job_queue';
    
    /**
     * Get the jobs table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_jobs';
    }
    
    /**
     * Create the jobs table
     */
    public static function create_table() {
        global $wpdb;
        
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            failed_items int(11) NOT NULL DEFAULT 0,
            payload longtext,
            items longtext,
            results longtext,
            error_message text,
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule the cron processor
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'ssf_every_minute', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule the cron processor
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Register custom cron interval (every minute)
     */
    public static function add_cron_interval($schedules) {
        $schedules['ssf_every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute (Smart SEO Fixer)', 'smart-seo-fixer'),
        ];
        return $schedules;
    }
    
    /**
     * Create a new background job
     * 
     * @param string $job_type  'bulk_ai_fix', 'bulk_schema', 'bulk_alt_text', 'orphan_fix_batch'
     * @param array  $items     Array of post IDs or item identifiers to process
     * @param array  $payload   Job configuration (options, settings, etc.)
     * @return int|WP_Error     Job ID or error
     */
    public static function create($job_type, $items, $payload = []) {
        global $wpdb;
        
        if (empty($items)) {
            return new WP_Error('empty_items', __('No items to process.', 'smart-seo-fixer'));
        }
        
        $result = $wpdb->insert(
            self::table(),
            [
                'job_type'        => sanitize_key($job_type),
                'status'          => self::STATUS_PENDING,
                'total_items'     => count($items),
                'processed_items' => 0,
                'failed_items'    => 0,
                'payload'         => wp_json_encode($payload),
                'items'           => wp_json_encode($items),
                'results'         => wp_json_encode([]),
                'user_id'         => get_current_user_id(),
                'created_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to create job.', 'smart-seo-fixer'));
        }
        
        $job_id = $wpdb->insert_id;
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('Job #%d created: %s (%d items)', $job_id, $job_type, count($items)), 'queue');
        }
        
        // Ensure cron is scheduled
        self::schedule_cron();

        // Kick the first batch immediately via loopback so the user sees
        // progress in seconds, not after WP-Cron's next minute tick.
        self::spawn_next_tick();

        return $job_id;
    }
    
    /**
     * Get a job by ID
     */
    public static function get($job_id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $job_id)
        );
        
        if ($row) {
            $row->payload = json_decode($row->payload, true) ?: [];
            $row->items   = json_decode($row->items, true) ?: [];
            $row->results = json_decode($row->results, true) ?: [];
        }
        
        return $row;
    }
    
    /**
     * Process the next batch of pending/processing jobs
     * Called by WordPress cron every minute
     */
    public static function process_queue() {
        // Serialize workers: WP-Cron, the loopback tick chain, and the AJAX
        // poll nudge can all land here concurrently. Without a lock, two
        // workers read the same processed_items offset and process the same
        // slice twice — duplicate AI spend and corrupted progress counts.
        if (!self::acquire_lock()) {
            return; // Another worker is already processing the queue.
        }

        try {
            self::process_queue_locked();
        } finally {
            self::release_lock();
        }
    }

    /**
     * Acquire the cross-request queue lock (MySQL advisory lock, auto-released
     * if the PHP process dies). Waits up to 3 seconds so a chained tick that
     * lands just as the previous worker is finishing isn't dropped; if the
     * holder is mid-batch (AI calls run 30s+), we give up — the holder spawns
     * its own follow-up tick when its batch completes.
     */
    private static function acquire_lock() {
        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', self::lock_name()));
        return (string) $got === '1';
    }

    /**
     * Release the queue lock.
     */
    private static function release_lock() {
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name()));
    }

    /**
     * Per-site lock name (prefix makes it unique per install on shared DBs).
     */
    private static function lock_name() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_queue_lock';
    }

    /**
     * The actual queue worker. Must only run while holding the queue lock.
     */
    private static function process_queue_locked() {
        global $wpdb;
        $table = self::table();

        // Check for stuck jobs first
        self::handle_dead_letters();
        
        // Find the next job to process (oldest pending or in-progress)
        $job = $wpdb->get_row(
            "SELECT * FROM $table 
             WHERE status IN ('pending', 'processing') 
             ORDER BY created_at ASC 
             LIMIT 1"
        );
        
        if (!$job) {
            return;
        }
        
        $job->payload = json_decode($job->payload, true) ?: [];
        $job->items   = json_decode($job->items, true) ?: [];
        $job->results = json_decode($job->results, true) ?: [];
        
        // Mark as processing if pending
        if ($job->status === self::STATUS_PENDING) {
            $wpdb->update(
                $table,
                ['status' => self::STATUS_PROCESSING, 'started_at' => current_time('mysql')],
                ['id' => $job->id],
                ['%s', '%s'],
                ['%d']
            );
        }
        
        // Set history source for tracking
        if (class_exists('SSF_History')) {
            SSF_History::set_source('bulk');
        }
        
        // Determine which items still need processing
        $processed_count = intval($job->processed_items);

        // Dynamic batch size: AI-fix jobs on Bedrock run in parallel and can
        // safely handle a bigger slice per cron tick. Covers both the manual
        // Bulk AI Fix page and the Search Console "Fix All Not-Indexed" flow,
        // since they share the same keyword/title/description AI shape.
        $batch_size = self::BATCH_SIZE;
        $parallel_job_types = ['bulk_ai_fix', 'not_indexed_ai_fix'];
        $use_parallel_bedrock = (
            in_array($job->job_type, $parallel_job_types, true)
            && class_exists('SSF_AI')
            && SSF_AI::active_provider() === 'bedrock'
            && function_exists('curl_multi_init')
        );
        if ($use_parallel_bedrock) {
            $batch_size = 20;
        }

        // Normalize not_indexed payload so the shared parallel batch handler
        // knows what to generate. not_indexed_ai_fix always tries to fill any
        // missing keyword/title/description on posts flagged by GSC.
        if ($use_parallel_bedrock && $job->job_type === 'not_indexed_ai_fix') {
            if (!isset($job->payload['generate_title']))    { $job->payload['generate_title'] = true; }
            if (!isset($job->payload['generate_desc']))     { $job->payload['generate_desc'] = true; }
            if (!isset($job->payload['generate_keywords'])) { $job->payload['generate_keywords'] = true; }
            if (!isset($job->payload['apply_to']))          { $job->payload['apply_to'] = 'missing'; }
        }

        $remaining_items = array_slice($job->items, $processed_count, $batch_size);

        if (empty($remaining_items)) {
            self::mark_completed($job->id);
            return;
        }

        $batch_results = [];
        $batch_failed = 0;

        if ($use_parallel_bedrock) {
            $batch_results = self::process_ai_fix_batch_parallel($remaining_items, $job->payload);
            foreach ($batch_results as $r) {
                if ($r['status'] === 'failed') { $batch_failed++; }
            }
        } else {
            foreach ($remaining_items as $item_id) {
                $result = self::process_single_item($job->job_type, $item_id, $job->payload);

                if (is_wp_error($result)) {
                    $batch_results[] = [
                        'item_id' => $item_id,
                        'status'  => 'failed',
                        'message' => $result->get_error_message(),
                    ];
                    $batch_failed++;
                } else {
                    $batch_results[] = [
                        'item_id' => $item_id,
                        'status'  => 'success',
                        'message' => $result,
                    ];
                }
            }
        }
        
        // Merge results and update progress
        $all_results = array_merge($job->results, $batch_results);
        $new_processed = $processed_count + count($remaining_items);
        $new_failed = intval($job->failed_items) + $batch_failed;
        
        $update_data = [
            'processed_items' => $new_processed,
            'failed_items'    => $new_failed,
            'results'         => wp_json_encode($all_results),
        ];
        
        // Check if we're done
        $job_completed = ($new_processed >= intval($job->total_items));
        if ($job_completed) {
            $update_data['status'] = self::STATUS_COMPLETED;
            $update_data['completed_at'] = current_time('mysql');

            if (class_exists('SSF_Logger')) {
                SSF_Logger::info(sprintf(
                    'Job #%d completed: %d processed, %d failed',
                    $job->id, $new_processed, $new_failed
                ), 'queue');
            }
        }

        $wpdb->update($table, $update_data, ['id' => $job->id]);

        if ($job_completed) {
            self::clear_progress_snapshot($job->id);
            self::cleanup();
        }

        // If the job isn't done, fire a non-blocking loopback request so the
        // next batch runs in seconds rather than waiting for WP-Cron's next
        // minute tick. This turns a 1017-post job from ~1 hour (wall clock,
        // cron-gated) into a handful of minutes while still yielding between
        // batches so we don't monopolise a single PHP worker. Applies to every
        // job type — previously only Bedrock-parallel jobs re-spawned, so
        // OpenAI/Claude/Gemini bulk runs would stall at 0/N waiting for cron
        // on low-traffic sites.
        if (!$job_completed) {
            self::spawn_next_tick();
        }
    }

    /**
     * Fire a non-blocking HTTP loopback request to admin-ajax.php to trigger
     * the next queue tick immediately. Safe no-op if wp_remote_post fails.
     */
    private static function spawn_next_tick() {
        $url = admin_url('admin-ajax.php?action=ssf_queue_tick&token=' . self::get_tick_token());
        wp_remote_post($url, [
            'timeout'   => 0.1,      // fire-and-forget
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'headers'   => ['Cache-Control' => 'no-cache'],
            'cookies'   => [],
        ]);
    }

    /**
     * Public wrapper so the AJAX polling endpoint can nudge the queue
     * forward while the user is watching the progress bar. Important on
     * hosts where the server-side non-blocking loopback is swallowed
     * (caching layers, reverse proxies) and the job would otherwise sit
     * idle waiting for WP-Cron.
     */
    public static function spawn_next_tick_public() {
        self::spawn_next_tick();
    }

    /**
     * Rotating token for the loopback tick endpoint (not a full nonce because
     * cron-style callers don't have a session). Tied to auth key + hour.
     *
     * @param int $offset_hours 0 for the current hour, -1 for the previous one.
     */
    public static function get_tick_token($offset_hours = 0) {
        $hour = gmdate('YmdH', time() + ($offset_hours * HOUR_IN_SECONDS));
        return substr(hash_hmac('sha256', 'ssf_queue_tick|' . $hour, wp_salt('auth')), 0, 32);
    }

    /**
     * AJAX handler — triggered by the loopback to advance the queue.
     */
    public static function ajax_queue_tick() {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        // Accept the previous hour's token too, so a tick spawned right before
        // the hour rolls over isn't rejected when it arrives just after.
        $valid = hash_equals(self::get_tick_token(), $token)
              || hash_equals(self::get_tick_token(-1), $token);
        if (!$valid) {
            wp_die('Invalid token', '', ['response' => 403]);
        }
        // Close the HTTP connection back to the caller immediately so this
        // tick and any chained ticks run without keeping the browser waiting.
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            echo 'ok';
            fastcgi_finish_request();
        } else {
            header('Content-Length: 2');
            header('Connection: close');
            echo 'ok';
            if (ob_get_level()) { ob_end_flush(); }
            flush();
        }
        self::process_queue();
        exit;
    }

    /**
     * Gather as much usable text as possible for SEO generation when
     * $post->post_content alone is too thin. Page-builder based post types
     * (Elementor, Divi, Oxygen) and location/service templates frequently
     * leave post_content empty while the real copy lives in post_excerpt,
     * ACF-style custom fields, or attached image alt/caption text. Without
     * this helper, the bulk AI fix job silently marked those posts as
     * "Skipped (content too short)" and reported 100% success while writing
     * nothing — which is the "jobs say 999/999 but posts still show ⚠ Title /
     * Desc / Keyword" symptom on location_service post types.
     *
     * @param WP_Post $post
     * @return string Enriched plain-text content suitable for AI prompts.
     */
    public static function enrich_post_context($post) {
        if (!$post instanceof WP_Post) {
            return '';
        }

        $parts = [];

        // Primary: stripped shortcodes + HTML of the post body.
        $body = wp_strip_all_tags(strip_shortcodes((string) $post->post_content));
        if ($body !== '') { $parts[] = $body; }

        // Excerpt.
        if (!empty($post->post_excerpt)) {
            $parts[] = wp_strip_all_tags((string) $post->post_excerpt);
        }

        // Page-builder / ACF-style public custom fields. We deliberately skip
        // keys beginning with "_" (WP-private meta such as our own SEO keys,
        // Elementor's _elementor_data blob which is not human text, etc.) and
        // cap total meta text so one enormous field doesn't dominate.
        $meta = get_post_meta($post->ID);
        $meta_text_budget = 2000;
        if (is_array($meta)) {
            foreach ($meta as $key => $values) {
                if ($key === '' || $key[0] === '_') { continue; }
                foreach ((array) $values as $v) {
                    if (!is_scalar($v)) { continue; }
                    $v = wp_strip_all_tags((string) $v);
                    $v = trim($v);
                    if ($v === '' || strlen($v) < 3) { continue; }
                    $parts[] = $v;
                    $meta_text_budget -= strlen($v);
                    if ($meta_text_budget <= 0) { break 2; }
                }
            }
        }

        // Image alt/caption from featured image + attached media (helps
        // image-heavy location pages where body is a short template).
        $image_ids = [];
        $thumb_id = get_post_thumbnail_id($post->ID);
        if ($thumb_id) { $image_ids[] = $thumb_id; }
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_parent'    => $post->ID,
            'post_mime_type' => 'image',
            'posts_per_page' => 10,
            'fields'         => 'ids',
        ]);
        if (is_array($attachments)) {
            $image_ids = array_unique(array_merge($image_ids, $attachments));
        }
        foreach ($image_ids as $img_id) {
            $alt = get_post_meta($img_id, '_wp_attachment_image_alt', true);
            if (!empty($alt)) { $parts[] = $alt; }
            $att = get_post($img_id);
            if ($att) {
                if (!empty($att->post_excerpt)) { $parts[] = $att->post_excerpt; } // caption
                if (!empty($att->post_content)) { $parts[] = wp_strip_all_tags($att->post_content); } // description
            }
        }

        $combined = trim(implode("\n", array_filter(array_map('trim', $parts))));
        // Collapse whitespace so str_word_count works reliably.
        $combined = preg_replace('/\s+/', ' ', $combined);
        return (string) $combined;
    }

    /**
     * Parallel batch processor for `bulk_ai_fix` jobs when the active AI
     * provider is Bedrock. Combines the 3 per-post calls (keyword/title/desc)
     * into a single JSON "SEO bundle" prompt and fires all posts in the batch
     * concurrently via curl_multi.
     *
     * Expected speedup vs. sequential 3-call flow: ~10-15x.
     *
     * @param int[]  $post_ids
     * @param array  $payload Job payload (generate_title/desc/keywords, apply_to)
     * @return array          Batch results in the same shape as the sequential path.
     */
    private static function process_ai_fix_batch_parallel($post_ids, $payload) {
        $bedrock = SSF_AI::get();
        if (!$bedrock instanceof SSF_Bedrock || !$bedrock->is_configured()) {
            // Fall back to sequential processing if something misaligned.
            $results = [];
            foreach ($post_ids as $pid) {
                $r = self::process_ai_fix($pid, $payload);
                if (is_wp_error($r)) {
                    $results[] = ['item_id' => $pid, 'status' => 'failed', 'message' => $r->get_error_message()];
                } else {
                    $results[] = ['item_id' => $pid, 'status' => 'success', 'message' => $r];
                }
            }
            return $results;
        }

        $generate_title    = !empty($payload['generate_title']);
        $generate_desc     = !empty($payload['generate_desc']);
        $generate_keywords = !empty($payload['generate_keywords']);
        $apply_to          = $payload['apply_to'] ?? 'missing';
        $overwrite         = ($apply_to === 'all');

        // Build the parallel job list. Each post that needs AI work gets a
        // single SEO-bundle request; posts that can be skipped are recorded
        // immediately.
        $jobs_to_run = [];
        $immediate_results = [];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $immediate_results[$post_id] = ['item_id' => $post_id, 'status' => 'failed', 'message' => 'Post not found'];
                continue;
            }

            // Use the enriched context so page-builder / location-template
            // post types (empty post_content but rich meta+images) still get
            // processed instead of being silently skipped.
            $enriched_content = self::enrich_post_context($post);
            if (str_word_count($enriched_content) < 10) {
                $immediate_results[$post_id] = ['item_id' => $post_id, 'status' => 'success', 'message' => 'Skipped (content too short)'];
                continue;
            }

            $current_kw    = trim(get_post_meta($post_id, '_ssf_focus_keyword', true));
            $current_title = trim(get_post_meta($post_id, '_ssf_seo_title', true));
            $current_desc  = trim(get_post_meta($post_id, '_ssf_meta_description', true));

            $needs_kw    = $generate_keywords && ($overwrite || empty($current_kw));
            $needs_title = $generate_title    && ($overwrite || empty($current_title));
            $needs_desc  = $generate_desc     && ($overwrite || empty($current_desc));

            if (!$needs_kw && !$needs_title && !$needs_desc) {
                $immediate_results[$post_id] = ['item_id' => $post_id, 'status' => 'success', 'message' => 'Skipped (already has SEO data)'];
                continue;
            }

            $messages = $bedrock->build_seo_bundle_messages($enriched_content, $post->post_title);
            $jobs_to_run[$post_id] = [
                'messages'         => $messages,
                'max_tokens'       => 400,
                'temperature'      => 0.3,
                'post'             => $post,
                'enriched_content' => $enriched_content,
                'needs_kw'         => $needs_kw,
                'needs_title'      => $needs_title,
                'needs_desc'       => $needs_desc,
                'overwrite'        => $overwrite,
            ];
        }

        // Fire all bundle requests concurrently.
        $ai_results = [];
        if (!empty($jobs_to_run)) {
            $ai_payload = [];
            foreach ($jobs_to_run as $pid => $job) {
                $ai_payload[$pid] = [
                    'messages'    => $job['messages'],
                    'max_tokens'  => $job['max_tokens'],
                    'temperature' => $job['temperature'],
                ];
            }
            $ai_results = $bedrock->request_multi($ai_payload);
        }

        // Apply results back to post meta.
        $applied_results = [];
        foreach ($jobs_to_run as $post_id => $job) {
            $raw = $ai_results[$post_id] ?? new WP_Error('missing', 'No response');
            $bundle = $bedrock->parse_seo_bundle($raw);

            if (is_wp_error($bundle)) {
                $applied_results[$post_id] = [
                    'item_id' => $post_id,
                    'status'  => 'failed',
                    'message' => $bundle->get_error_message(),
                ];
                continue;
            }

            $post          = $job['post'];
            $generated     = [];
            $enriched      = $job['enriched_content'] ?? '';
            $haystack      = strtolower(wp_strip_all_tags(strip_shortcodes($post->post_title . "\n" . $enriched)));

            // Keyword
            if ($job['needs_kw']) {
                $kw = trim((string) $bundle['keyword']);
                // Ground the keyword — if AI's suggestion isn't in the content,
                // fall back to SSF_AI's grounded extractor so we never save an
                // orphan keyword (which is what was tanking scores).
                if ($kw === '' || strpos($haystack, strtolower($kw)) === false) {
                    $kw = SSF_AI::pick_grounded_keyword($enriched, $post->post_title);
                }
                if (!empty($kw)) {
                    update_post_meta($post_id, '_ssf_focus_keyword', sanitize_text_field($kw));
                    $generated[] = 'keyword';
                }
            }

            // Title
            if ($job['needs_title']) {
                $title = trim((string) $bundle['title']);
                if ($title !== '') {
                    $title = SSF_Validator::enforce_seo_title($title, 60);
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                    $generated[] = 'title';
                }
            }

            // Description
            if ($job['needs_desc']) {
                $desc = trim((string) $bundle['description']);
                if ($desc !== '') {
                    $desc = SSF_Validator::enforce_meta_description($desc, 160);
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                    $generated[] = 'description';
                }
            }

            // Re-analyze so scores refresh immediately.
            if (class_exists('SSF_Analyzer')) {
                (new SSF_Analyzer())->analyze_post($post_id);
            }

            $applied_results[$post_id] = [
                'item_id' => $post_id,
                'status'  => 'success',
                'message' => !empty($generated) ? ('Generated: ' . implode(', ', $generated)) : 'Skipped (already has SEO data)',
            ];
        }

        // Preserve original post_id order in the returned results.
        $final = [];
        foreach ($post_ids as $pid) {
            if (isset($immediate_results[$pid])) { $final[] = $immediate_results[$pid]; }
            elseif (isset($applied_results[$pid])) { $final[] = $applied_results[$pid]; }
        }
        return $final;
    }

    /**
     * Process a single item based on job type
     * 
     * @param string $job_type The type of job
     * @param int    $item_id  The post/item ID
     * @param array  $payload  Job configuration
     * @return string|WP_Error Success message or error
     */
    private static function process_single_item($job_type, $item_id, $payload) {
        switch ($job_type) {
            case 'bulk_ai_fix':
                return self::process_ai_fix($item_id, $payload);
                
            case 'bulk_schema':
                return self::process_schema_regen($item_id, $payload);
                
            case 'orphan_fix_batch':
                return self::process_orphan_fix($item_id, $payload);
                
            case 'not_indexed_ai_fix':
                return self::process_not_indexed_fix($item_id, $payload);
                
            case 'bulk_404_redirect':
                return self::process_404_redirect($item_id, $payload);
                
            default:
                return new WP_Error('unknown_type', sprintf('Unknown job type: %s', $job_type));
        }
    }
    
    /**
     * Process a single AI fix item
     */
    private static function process_ai_fix($post_id, $payload) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found');
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
              return new WP_Error('no_api_key', SSF_AI::not_configured_message());
        }

        // Enriched context covers page-builder / location templates where
        // post_content is empty but real content lives in meta / images.
        $enriched_content = self::enrich_post_context($post);
        if (str_word_count($enriched_content) < 10) {
            return 'Skipped (content too short)';
        }

        $generate_title    = !empty($payload['generate_title']);
        $generate_desc     = !empty($payload['generate_desc']);
        $generate_keywords = !empty($payload['generate_keywords']);
        $apply_to          = $payload['apply_to'] ?? 'missing';
        $overwrite         = ($apply_to === 'all');
        
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        $generated = [];
        
        // Keywords first
        if ($generate_keywords) {
            $current_kw = trim(get_post_meta($post_id, '_ssf_focus_keyword', true));
            if ($overwrite || empty($current_kw)) {
                $keywords = $openai->suggest_keywords($enriched_content, $post->post_title);
                if (!is_wp_error($keywords) && !empty($keywords['primary'])) {
                    update_post_meta($post_id, '_ssf_focus_keyword', sanitize_text_field($keywords['primary']));
                    $focus_keyword = $keywords['primary'];
                    $generated[] = 'keyword';
                } elseif (is_wp_error($keywords)) {
                    return $keywords;
                }
            }
        }
        
        // Title
        if ($generate_title) {
            $current_title = trim(get_post_meta($post_id, '_ssf_seo_title', true));
            if ($overwrite || empty($current_title)) {
                $title = $openai->generate_title($enriched_content, $post->post_title, $focus_keyword);
                if (!is_wp_error($title) && !empty(trim($title))) {
                    $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                    $generated[] = 'title';
                } elseif (is_wp_error($title)) {
                    return $title;
                }
            }
        }
        
        // Description
        if ($generate_desc) {
            $current_desc = trim(get_post_meta($post_id, '_ssf_meta_description', true));
            if ($overwrite || empty($current_desc)) {
                $desc = $openai->generate_meta_description($enriched_content, '', $focus_keyword);
                if (!is_wp_error($desc) && !empty(trim($desc))) {
                    $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                    $generated[] = 'description';
                } elseif (is_wp_error($desc)) {
                    return $desc;
                }
            }
        }
        
        // Re-analyze
        if (class_exists('SSF_Analyzer')) {
            $analyzer = new SSF_Analyzer();
            $analyzer->analyze_post($post_id);
        }
        
        if (!empty($generated)) {
            return sprintf('Generated: %s', implode(', ', $generated));
        }
        
        return 'Skipped (already has SEO data)';
    }
    
    /**
     * Process a single schema regeneration
     */
    private static function process_schema_regen($post_id, $payload) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found');
        }
        
        if (!class_exists('SSF_Schema')) {
            return new WP_Error('no_schema', 'Schema module not available');
        }
        
        $schema = new SSF_Schema();
        if (method_exists($schema, 'generate_schema')) {
            $result = $schema->generate_schema($post_id);
            if (is_wp_error($result)) {
                return $result;
            }
            return 'Schema regenerated';
        }
        
        return new WP_Error('no_method', 'Schema generation method not found');
    }
    
    /**
     * Process a single orphan page fix
     */
    private static function process_orphan_fix($post_id, $payload) {
        // Delegate to the search console class if available
        if (!class_exists('SSF_Search_Console')) {
            return new WP_Error('no_module', 'Search Console module not available');
        }
        
        // The orphan fix is complex — we simulate what the AJAX handler does
        // For now, return a placeholder; the actual fix logic is in class-search-console.php
        return new WP_Error('not_implemented', 'Orphan fix via queue not yet implemented');
    }
    
    /**
     * Process a single "not indexed" AI fix item
     * Generates missing SEO title, meta description, and fixes link issues
     */
    private static function process_not_indexed_fix($post_id, $payload) {
        $post = get_post(intval($post_id));
        if (!$post) {
            return new WP_Error('not_found', 'Post not found');
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            return new WP_Error('no_api_key', SSF_AI::not_configured_message());
        }
        
        $issues_map = [];
        if (!empty($payload['issues'])) {
            $decoded = is_string($payload['issues']) ? json_decode($payload['issues'], true) : $payload['issues'];
            if (is_array($decoded)) {
                $issues_map = $decoded;
            }
        }
        $issues = isset($issues_map[$post_id]) ? (array) $issues_map[$post_id] : [];
        $generated = [];
        
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        // Enriched context for page-builder / location CPTs where post_content
        // alone is too sparse for the AI to produce useful SEO data.
        $enriched_content = self::enrich_post_context($post);
        $clean_content = $enriched_content;

        // Generate keyword if missing
        if (empty($focus_keyword) && str_word_count($clean_content) >= 10) {
            $keywords = $openai->suggest_keywords($enriched_content, $post->post_title);
            if (!is_wp_error($keywords) && !empty($keywords['primary'])) {
                $focus_keyword = sanitize_text_field($keywords['primary']);
                update_post_meta($post_id, '_ssf_focus_keyword', $focus_keyword);
                $generated[] = 'keyword';
            }
        }
        
        // Fix missing title
        if (in_array('missing_title', $issues) || empty(get_post_meta($post_id, '_ssf_seo_title', true))) {
            if (str_word_count($clean_content) >= 10) {
                $title = $openai->generate_title($enriched_content, $post->post_title, $focus_keyword);
                if (!is_wp_error($title) && !empty(trim($title))) {
                    $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                    $generated[] = 'title';
                }
            }
        }
        
        // Fix missing description
        if (in_array('missing_description', $issues) || in_array('missing_meta', $issues) || empty(get_post_meta($post_id, '_ssf_meta_description', true))) {
            if (str_word_count($clean_content) >= 10) {
                $desc = $openai->generate_meta_description($enriched_content, '', $focus_keyword);
                if (!is_wp_error($desc) && !empty(trim($desc))) {
                    $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                    $generated[] = 'description';
                }
            }
        }
        
        // Re-analyze
        if (class_exists('SSF_Analyzer')) {
            $analyzer = new SSF_Analyzer();
            $analyzer->analyze_post($post_id);
        }
        
        if (!empty($generated)) {
            return sprintf('Fixed: %s', implode(', ', $generated));
        }
        
        return 'No fixable issues found';
    }
    
    /**
     * Process a single 404 redirect item
     * Item is the 404 URL string, payload contains redirect_to
     */
    private static function process_404_redirect($url, $payload) {
        $redirect_to = $payload['redirect_to'] ?? '';
        if (empty($redirect_to)) {
            return new WP_Error('no_target', 'No redirect target URL');
        }
        
        if (!class_exists('SSF_Redirects')) {
            return new WP_Error('no_module', 'Redirects module not available');
        }
        
        $redirects = new SSF_Redirects();
        
        // Check for duplicates
        $existing = $redirects->get_redirects();
        foreach ($existing as $r) {
            if (trim($r['from'], '/') === trim($url, '/')) {
                return 'Skipped (redirect already exists)';
            }
        }
        
        $redirects->add_redirect([
            'from'    => $url,
            'to'      => esc_url_raw($redirect_to),
            'type'    => 301,
            'note'    => 'Bulk from 404 log (background)',
            'auto'    => false,
        ]);
        
        return 'Redirect created';
    }
    
    /**
     * Cancel a job
     */
    public static function cancel($job_id) {
        global $wpdb;
        
        $job = self::get($job_id);
        if (!$job) {
            return new WP_Error('not_found', __('Job not found.', 'smart-seo-fixer'));
        }
        
        if (in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return new WP_Error('invalid_status', __('Job is already finished.', 'smart-seo-fixer'));
        }
        
        $wpdb->update(
            self::table(),
            [
                'status'       => self::STATUS_CANCELLED,
                'completed_at' => current_time('mysql'),
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        );

        self::clear_progress_snapshot($job_id);

        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('Job #%d cancelled', $job_id), 'queue');
        }

        return true;
    }
    
    /**
     * Retry failed items in a job
     */
    public static function retry_failed($job_id) {
        global $wpdb;
        
        $job = self::get($job_id);
        if (!$job) {
            return new WP_Error('not_found', __('Job not found.', 'smart-seo-fixer'));
        }
        
        // Collect failed item IDs
        $failed_ids = [];
        foreach ($job->results as $r) {
            if ($r['status'] === 'failed') {
                $failed_ids[] = $r['item_id'];
            }
        }
        
        if (empty($failed_ids)) {
            return new WP_Error('no_failures', __('No failed items to retry.', 'smart-seo-fixer'));
        }
        
        // Create a new job with just the failed items
        return self::create($job->job_type, $failed_ids, $job->payload);
    }
    
    /**
     * Mark a job as completed
     */
    private static function mark_completed($job_id) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            [
                'status'       => self::STATUS_COMPLETED,
                'completed_at' => current_time('mysql'),
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        );
        self::clear_progress_snapshot($job_id);
        self::cleanup();
    }
    
    /**
     * Get recent jobs (for admin UI)
     */
    public static function get_recent($limit = 20) {
        global $wpdb;
        $table = self::table();
        
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, job_type, status, total_items, processed_items, failed_items,
                        error_message, user_id, created_at, started_at, completed_at
                 FROM $table
                 ORDER BY created_at DESC
                 LIMIT %d",
                $limit
            )
        );
        
        return $jobs ?: [];
    }
    
    /**
     * Get active (pending/processing) jobs count
     */
    public static function active_count() {
        global $wpdb;
        return intval($wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE status IN ('pending', 'processing')"
        ));
    }
    
    /**
     * Cleanup old completed/cancelled jobs (keep last 30 days)
     */
    public static function cleanup($days = 30) {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::table() . " 
                 WHERE status IN ('completed', 'cancelled', 'failed') 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
    
    /**
     * Progress-snapshot option key for a job. Used by handle_dead_letters()
     * to detect jobs that have genuinely stalled (no items processed for 30+
     * minutes) as opposed to jobs that are simply large and long-running.
     */
    private static function progress_key($job_id) {
        return 'ssf_job_progress_' . intval($job_id);
    }

    /**
     * Remove the progress snapshot once a job reaches a terminal state.
     */
    private static function clear_progress_snapshot($job_id) {
        delete_option(self::progress_key($job_id));
    }

    /**
     * Detect and handle stuck/dead-letter jobs.
     *
     * A job is "stuck" if it has been in 'processing' status for more than 30 minutes
     * without any progress. These are marked as failed and an admin email is sent.
     *
     * Progress is tracked via a per-job snapshot (processed count + timestamp):
     * comparing against started_at alone would kill big jobs that are still
     * advancing, since started_at is set once and never refreshed.
     *
     * Call this from process_queue() or on a separate cron schedule.
     */
    public static function handle_dead_letters() {
        global $wpdb;
        $table = self::table();

        // Candidates: anything that's been in 'processing' longer than the window.
        $candidates = $wpdb->get_results(
            "SELECT * FROM $table
             WHERE status = 'processing'
             AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             ORDER BY started_at ASC"
        );

        if (empty($candidates)) {
            return;
        }

        $stuck_jobs = [];
        foreach ($candidates as $job) {
            $key      = self::progress_key($job->id);
            $snapshot = get_option($key);
            $processed = intval($job->processed_items);

            if (!is_array($snapshot) || intval($snapshot['processed'] ?? -1) !== $processed) {
                // First sighting at this progress level — record it and give the
                // job another full window to advance before declaring it dead.
                update_option($key, ['processed' => $processed, 'time' => time()], false);
                continue;
            }

            if ((time() - intval($snapshot['time'] ?? 0)) < 30 * MINUTE_IN_SECONDS) {
                continue; // Same progress, but not stalled long enough yet.
            }

            $stuck_jobs[] = $job;
        }

        if (empty($stuck_jobs)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name   = get_bloginfo('name');
        $dead_letters = [];
        
        foreach ($stuck_jobs as $job) {
            // Mark as failed
            $wpdb->update(
                $table,
                [
                    'status'        => self::STATUS_FAILED,
                    'error_message' => __('Job timed out — no progress for 30+ minutes.', 'smart-seo-fixer'),
                    'completed_at'  => current_time('mysql'),
                ],
                ['id' => $job->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            self::clear_progress_snapshot($job->id);
            $dead_letters[] = $job;

            if (class_exists('SSF_Logger')) {
                SSF_Logger::error(sprintf(
                    'Job #%d marked as dead letter: stuck in processing since %s (%d/%d items)',
                    $job->id, $job->started_at, $job->processed_items, $job->total_items
                ), 'queue');
            }
        }
        
        // Send single admin notification for all stuck jobs
        if (!empty($dead_letters) && !empty($admin_email)) {
            $subject = sprintf('[%s] Smart SEO Fixer: %d background job(s) failed', $site_name, count($dead_letters));
            
            $body = __("The following background jobs were stuck and have been marked as failed:\n\n", 'smart-seo-fixer');
            
            foreach ($dead_letters as $job) {
                $body .= sprintf(
                    "- Job #%d (%s): %d/%d items processed, started %s\n",
                    $job->id,
                    $job->job_type,
                    $job->processed_items,
                    $job->total_items,
                    $job->started_at
                );
            }
            
            $body .= "\n" . sprintf(
                __("You can retry these jobs from: %s\n", 'smart-seo-fixer'),
                admin_url('admin.php?page=smart-seo-fixer-jobs')
            );
            
            wp_mail($admin_email, $subject, $body);
        }
    }
}
