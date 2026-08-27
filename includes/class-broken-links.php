<?php
/**
 * Broken Link Checker
 * 
 * Scans published posts for dead links (404s, timeouts, connection errors)
 * and provides an admin interface to review and fix them.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Broken_Links {
    
    const CRON_HOOK = 'ssf_check_broken_links';
    
    /**
     * Get database table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_broken_links';
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            url varchar(2083) NOT NULL,
            anchor_text varchar(500) DEFAULT '',
            status_code int(3) DEFAULT 0,
            error_message varchar(500) DEFAULT '',
            link_type varchar(20) DEFAULT 'internal',
            first_detected datetime NOT NULL,
            last_checked datetime NOT NULL,
            check_count int(5) DEFAULT 1,
            dismissed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status_code (status_code),
            KEY dismissed (dismissed),
            KEY url_hash (url(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule cron for background scanning
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule cron
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Cron handler: scan a batch of posts for broken links
     */
    public static function cron_scan() {
        global $wpdb;
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Get last scanned post ID to resume from
        $last_scanned = intval(get_option('ssf_broken_links_last_post', 0));
        
        // Get 5 posts to scan per cron run
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content 
                 FROM {$wpdb->posts} 
                 WHERE post_status = 'publish' 
                 AND post_type IN ($placeholders) 
                 AND ID > %d
                 ORDER BY ID ASC 
                 LIMIT 5",
                ...array_merge($post_types, [$last_scanned])
            )
        );
        
        // If no more posts, reset to start
        if (empty($posts)) {
            update_option('ssf_broken_links_last_post', 0);
            
            if (class_exists('SSF_Logger')) {
                SSF_Logger::info('Broken link scan cycle completed, resetting', 'general');
            }
            return;
        }
        
        $total_checked = 0;
        $total_broken = 0;
        
        foreach ($posts as $post) {
            $result = self::scan_post($post->ID, $post->post_content);
            $total_checked += $result['checked'];
            $total_broken += $result['broken'];
            update_option('ssf_broken_links_last_post', $post->ID);
        }
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf(
                'Broken link scan: checked %d links across %d posts, found %d broken',
                $total_checked, count($posts), $total_broken
            ), 'general');
        }
    }
    
    /**
     * Max concurrent link checks per scan_post call.
     * Prevents overwhelming the server on posts with many links.
     */
    const MAX_CONCURRENT_CHECKS = 5;

    /**
     * Scan a single post for broken links
     */
    public static function scan_post($post_id, $content = null) {
        if ($content === null) {
            $post = get_post($post_id);
            if (!$post) return ['checked' => 0, 'broken' => 0];
            $content = $post->post_content;
        }
        
        // Extract all links from content
        $links = self::extract_links($content);
        
        $checked = 0;
        $broken = 0;
        
        // Process links in batches to limit concurrent outbound requests
        $batches = array_chunk($links, self::MAX_CONCURRENT_CHECKS);
        
        foreach ($batches as $batch) {
            foreach ($batch as $link) {
                $checked++;
                $result = self::check_url($link['url']);
                
                if ($result['is_broken']) {
                    $broken++;
                    self::record_broken_link($post_id, $link, $result);
                } else {
                    // Remove from broken links if it was previously broken but now works
                    self::remove_if_fixed($post_id, $link['url']);
                }
            }
        }
        
        return ['checked' => $checked, 'broken' => $broken];
    }
    
    /**
     * Extract links from post content
     */
    public static function extract_links($content) {
        $links = [];
        
        // Match all <a> tags
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = trim($match[1]);
                $anchor = wp_strip_all_tags(trim($match[2]));
                
                // Skip anchors, mailto, tel, javascript
                if (empty($url) || $url[0] === '#' || 
                    strpos($url, 'mailto:') === 0 || 
                    strpos($url, 'tel:') === 0 || 
                    strpos($url, 'javascript:') === 0) {
                    continue;
                }
                
                // Determine link type
                $site_url = home_url();
                $is_internal = strpos($url, $site_url) === 0 || (strpos($url, '/') === 0 && strpos($url, '//') !== 0);
                
                $links[] = [
                    'url'    => $url,
                    'anchor' => mb_substr($anchor, 0, 500),
                    'type'   => $is_internal ? 'internal' : 'external',
                ];
            }
        }
        
        return $links;
    }
    
    /**
     * Check if a URL is broken
     */
    public static function check_url($url) {
        // Make relative URLs absolute
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = home_url($url);
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'is_broken'    => true,
                'status_code'  => 0,
                'error'        => 'Invalid URL format',
            ];
        }
        
        // Use a browser-like user agent — many hosts serve 403 to obvious bots.
        $args = [
            'timeout'     => 8,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (compatible; Smart-SEO-Fixer-LinkChecker/1.0; +' . home_url('/') . ')',
            'headers'     => ['Accept' => '*/*'],
        ];

        $response = wp_remote_head($url, $args);

        $code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

        // Many servers reject or mishandle HEAD (405/403/400/501) — retry with a
        // ranged GET, which is widely accepted and cheap (we only need the status).
        if (is_wp_error($response) || in_array($code, [0, 400, 403, 405, 501], true)) {
            $get_args = $args;
            $get_args['headers']['Range'] = 'bytes=0-0';
            $response = wp_remote_get($url, $get_args);

            if (is_wp_error($response)) {
                return [
                    'is_broken'   => true,
                    'status_code' => 0,
                    'error'       => mb_substr($response->get_error_message(), 0, 500),
                ];
            }
            $code = wp_remote_retrieve_response_code($response);
        }

        // Codes that do NOT mean the link is dead — they mean the server is
        // blocking automated checks, requires auth, or is rate-limiting. Flagging
        // these produces false positives (LinkedIn returns 999, many CDNs 403).
        $soft_ok = in_array($code, self::SOFT_OK_CODES, true);

        // 2xx and 3xx are OK; treat soft-block codes as OK too.
        $is_broken = ($code === 0) || ($code >= 400 && !$soft_ok);

        return [
            'is_broken'   => $is_broken,
            'status_code' => $code,
            'error'       => $is_broken ? "HTTP $code" : '',
        ];
    }
    
    /**
     * Record a broken link in the database
     */
    private static function record_broken_link($post_id, $link, $result) {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql');
        
        // Check if we already have this exact broken link
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, check_count FROM $table WHERE post_id = %d AND url = %s",
            $post_id, $link['url']
        ));
        
        if ($existing) {
            $wpdb->update($table, [
                'status_code'   => $result['status_code'],
                'error_message' => $result['error'],
                'last_checked'  => $now,
                'check_count'   => $existing->check_count + 1,
            ], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table, [
                'post_id'        => $post_id,
                'url'            => $link['url'],
                'anchor_text'    => $link['anchor'],
                'status_code'    => $result['status_code'],
                'error_message'  => $result['error'],
                'link_type'      => $link['type'],
                'first_detected' => $now,
                'last_checked'   => $now,
                'check_count'    => 1,
                'dismissed'      => 0,
            ]);
        }
    }
    
    /**
     * Remove a broken link record if it's now fixed.
     * Also increments a persistent resolved counter so reports can show an
     * accurate "fixed over time" metric (the row itself is deleted here).
     */
    private static function remove_if_fixed($post_id, $url) {
        global $wpdb;
        $deleted = $wpdb->delete(self::table(), [
            'post_id' => $post_id,
            'url'     => $url,
        ]);

        if ($deleted) {
            // Lifetime counter of links that were broken and later resolved.
            $total = (int) get_option('ssf_broken_links_resolved_total', 0);
            update_option('ssf_broken_links_resolved_total', $total + (int) $deleted, false);

            // Rolling log: keep last 500 resolutions with timestamp for date-range reporting.
            $log = get_option('ssf_broken_links_resolved_log', []);
            if (!is_array($log)) {
                $log = [];
            }
            $log[] = [
                't' => current_time('mysql'),
                'p' => (int) $post_id,
            ];
            if (count($log) > 500) {
                $log = array_slice($log, -500);
            }
            update_option('ssf_broken_links_resolved_log', $log, false);
        }
    }
    
    /**
     * Get broken links with pagination and filters
     */
    public static function query($args = []) {
        global $wpdb;
        $table = self::table();
        
        $defaults = [
            'page'       => 1,
            'per_page'   => 20,
            'link_type'  => '',       // internal, external, or empty for all
            'status'     => '',       // dismissed, active, or empty for all
            'search'     => '',
            'order_by'   => 'last_checked',
            'order'      => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $where = ['1=1'];
        $params = [];
        
        if ($args['link_type'] === 'internal') {
            $where[] = "bl.link_type = 'internal'";
        } elseif ($args['link_type'] === 'external') {
            $where[] = "bl.link_type = 'external'";
        }
        
        if ($args['status'] === 'dismissed') {
            $where[] = 'bl.dismissed = 1';
        } elseif ($args['status'] === 'active') {
            $where[] = 'bl.dismissed = 0';
        }
        
        if (!empty($args['search'])) {
            $where[] = '(bl.url LIKE %s OR bl.anchor_text LIKE %s OR p.post_title LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $allowed_order = ['last_checked', 'first_detected', 'status_code', 'check_count', 'url'];
        $order_by = in_array($args['order_by'], $allowed_order) ? $args['order_by'] : 'last_checked';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Count
        $count_sql = "SELECT COUNT(*) FROM $table bl LEFT JOIN {$wpdb->posts} p ON bl.post_id = p.ID WHERE $where_clause";
        $total = !empty($params) ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql);
        
        // Results
        $sql = "SELECT bl.*, p.post_title 
                FROM $table bl 
                LEFT JOIN {$wpdb->posts} p ON bl.post_id = p.ID 
                WHERE $where_clause 
                ORDER BY bl.$order_by $order 
                LIMIT %d OFFSET %d";
        
        $query_params = array_merge($params, [$args['per_page'], $offset]);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));
        
        return [
            'items'    => $results,
            'total'    => intval($total),
            'pages'    => ceil($total / $args['per_page']),
            'page'     => $args['page'],
            'per_page' => $args['per_page'],
        ];
    }
    
    /**
     * Dismiss a broken link (stop showing in active list)
     */
    public static function dismiss($id) {
        global $wpdb;
        return $wpdb->update(self::table(), ['dismissed' => 1], ['id' => absint($id)]);
    }
    
    /**
     * Undismiss a broken link
     */
    public static function undismiss($id) {
        global $wpdb;
        return $wpdb->update(self::table(), ['dismissed' => 0], ['id' => absint($id)]);
    }
    
    /**
     * Delete a broken link record
     */
    public static function delete_record($id) {
        global $wpdb;
        return $wpdb->delete(self::table(), ['id' => absint($id)]);
    }
    
    /**
     * Recheck a specific broken link
     */
    public static function recheck($id) {
        global $wpdb;
        $table = self::table();
        
        $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$link) {
            return new WP_Error('not_found', 'Link record not found');
        }
        
        $result = self::check_url($link->url);
        
        if ($result['is_broken']) {
            $wpdb->update($table, [
                'status_code'   => $result['status_code'],
                'error_message' => $result['error'],
                'last_checked'  => current_time('mysql'),
                'check_count'   => $link->check_count + 1,
            ], ['id' => $id]);
            
            return ['still_broken' => true, 'status_code' => $result['status_code'], 'error' => $result['error']];
        }
        
        // Link is fixed, remove record
        $wpdb->delete($table, ['id' => $id]);
        return ['still_broken' => false, 'message' => 'Link is now working'];
    }
    
    /**
     * Get summary stats
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total' => 0, 'internal' => 0, 'external' => 0, 'dismissed' => 0, 'posts_affected' => 0];
        }
        
        return [
            'total'          => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 0")),
            'internal'       => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 0 AND link_type = 'internal'")),
            'external'       => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 0 AND link_type = 'external'")),
            'dismissed'      => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 1")),
            'posts_affected' => intval($wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM $table WHERE dismissed = 0")),
        ];
    }
    
    /**
     * HTTP status codes that are NOT real broken links — the server is blocking
     * automated checks, requires auth, or is rate-limiting. Kept in sync with
     * the classification in check_url().
     */
    const SOFT_OK_CODES = [401, 403, 429, 999];

    /**
     * Remove existing records whose status code is now treated as "not broken"
     * (auth / bot-block / rate-limit). Clears out historical false positives
     * like YellowPages (403) or LinkedIn (999) recorded by older versions.
     *
     * @return int Rows removed.
     */
    public static function purge_soft_ok() {
        global $wpdb;
        $table = self::table();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }
        $codes = implode(',', array_map('intval', self::SOFT_OK_CODES));
        return (int) $wpdb->query("DELETE FROM $table WHERE status_code IN ($codes)");
    }

    /**
     * Clean up old dismissed records (older than 90 days)
     */
    public static function cleanup() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::table() . " WHERE dismissed = 1 AND last_checked < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }
}
