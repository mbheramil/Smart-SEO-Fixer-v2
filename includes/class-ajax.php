<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Analyze actions
        add_action('wp_ajax_ssf_analyze_post', [$this, 'analyze_post']);
        add_action('wp_ajax_ssf_bulk_analyze', [$this, 'bulk_analyze']);
        
        // AI generation actions
        add_action('wp_ajax_ssf_generate_title', [$this, 'generate_title']);
        add_action('wp_ajax_ssf_generate_description', [$this, 'generate_description']);
        add_action('wp_ajax_ssf_generate_alt_text', [$this, 'generate_alt_text']);
        add_action('wp_ajax_ssf_ai_analyze', [$this, 'ai_analyze']);
        add_action('wp_ajax_ssf_suggest_keywords', [$this, 'suggest_keywords']);
        
        // Save actions
        add_action('wp_ajax_ssf_save_seo_data', [$this, 'save_seo_data']);
        add_action('wp_ajax_ssf_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_ssf_test_bedrock',  [$this, 'test_bedrock']);
        add_action('wp_ajax_ssf_test_ai_provider', [$this, 'test_ai_provider']);
        
        // Fix actions
        add_action('wp_ajax_ssf_fix_issue', [$this, 'fix_issue']);
        add_action('wp_ajax_ssf_bulk_fix', [$this, 'bulk_fix']);
        
        // Utility actions
        add_action('wp_ajax_ssf_get_post_seo_data', [$this, 'get_post_seo_data']);
        add_action('wp_ajax_ssf_get_dashboard_stats', [$this, 'get_dashboard_stats']);
        
        // AI content tools
        add_action('wp_ajax_ssf_suggest_internal_links', [$this, 'suggest_internal_links']);
        add_action('wp_ajax_ssf_suggest_external_links', [$this, 'suggest_external_links']);
        add_action('wp_ajax_ssf_fix_image_alt_texts', [$this, 'fix_image_alt_texts']);
        add_action('wp_ajax_ssf_get_map_embed', [$this, 'get_map_embed']);
        
        // Bulk operations
        add_action('wp_ajax_ssf_bulk_ai_fix', [$this, 'bulk_ai_fix']);
        add_action('wp_ajax_ssf_preview_bulk_fix', [$this, 'preview_bulk_fix']);
        add_action('wp_ajax_ssf_suggest_images', [$this, 'suggest_images']);
        
        // Google Search Console
        add_action('wp_ajax_ssf_gsc_refresh_sites', [$this, 'gsc_refresh_sites']);
        add_action('wp_ajax_ssf_gsc_disconnect', [$this, 'gsc_disconnect']);
        add_action('wp_ajax_ssf_gsc_performance', [$this, 'gsc_performance']);
        add_action('wp_ajax_ssf_gsc_inspect_url', [$this, 'gsc_inspect_url']);
        add_action('wp_ajax_ssf_gsc_submit_sitemap', [$this, 'gsc_submit_sitemap']);
        add_action('wp_ajax_ssf_gsc_not_indexed', [$this, 'gsc_not_indexed']);
        add_action('wp_ajax_ssf_gsc_auto_setup', [$this, 'gsc_auto_setup']);
        add_action('wp_ajax_ssf_ga_disconnect', [$this, 'ga_disconnect']);
        add_action('wp_ajax_ssf_ga_auto_setup', [$this, 'ga_auto_setup']);
        add_action('wp_ajax_ssf_ga_save_measurement_id', [$this, 'ga_save_measurement_id']);
        add_action('wp_ajax_ssf_ga_test_report', [$this, 'ga_test_report']);
        add_action('wp_ajax_ssf_ga_list_properties', [$this, 'ga_list_properties']);
        add_action('wp_ajax_ssf_ga_select_property', [$this, 'ga_select_property']);
        add_action('wp_ajax_ssf_ai_fix_single', [$this, 'ai_fix_single']);
        add_action('wp_ajax_ssf_get_posts_by_issue', [$this, 'get_posts_by_issue']);
        
        // Schema tools
        add_action('wp_ajax_ssf_toggle_local_schema', [$this, 'toggle_local_schema']);
        
        // AI content tools
        add_action('wp_ajax_ssf_generate_outline', [$this, 'generate_outline']);
        add_action('wp_ajax_ssf_improve_readability', [$this, 'improve_readability']);
        add_action('wp_ajax_ssf_suggest_schema', [$this, 'suggest_schema']);
        
        // Bulk schema regeneration
        add_action('wp_ajax_ssf_bulk_regenerate_schemas', [$this, 'bulk_regenerate_schemas']);
        
        // Schema management page
        add_action('wp_ajax_ssf_toggle_setting', [$this, 'toggle_setting']);
        add_action('wp_ajax_ssf_get_schema_list', [$this, 'get_schema_list']);
        add_action('wp_ajax_ssf_delete_single_schema', [$this, 'delete_single_schema']);
        add_action('wp_ajax_ssf_regenerate_single_schema', [$this, 'regenerate_single_schema']);
        add_action('wp_ajax_ssf_generate_schema_for_post', [$this, 'generate_schema_for_post']);
        add_action('wp_ajax_ssf_search_posts_for_schema', [$this, 'search_posts_for_schema']);
        
        // Change History & Undo
        add_action('wp_ajax_ssf_get_history', [$this, 'get_history']);
        add_action('wp_ajax_ssf_undo_change', [$this, 'undo_change']);
        add_action('wp_ajax_ssf_get_history_stats', [$this, 'get_history_stats']);
        
        // Debug Log
        add_action('wp_ajax_ssf_get_logs', [$this, 'get_logs']);
        add_action('wp_ajax_ssf_clear_logs', [$this, 'clear_logs']);
        
        // Job Queue
        add_action('wp_ajax_ssf_get_job_status', [$this, 'get_job_status']);
        add_action('wp_ajax_ssf_get_jobs', [$this, 'get_jobs']);
        add_action('wp_ajax_ssf_get_job', [$this, 'get_job']);
        add_action('wp_ajax_ssf_cancel_job', [$this, 'cancel_job']);
        add_action('wp_ajax_ssf_retry_job', [$this, 'retry_job']);
        
        // Broken Links
        add_action('wp_ajax_ssf_get_broken_links', [$this, 'get_broken_links']);
        add_action('wp_ajax_ssf_scan_broken_links', [$this, 'scan_broken_links']);
        add_action('wp_ajax_ssf_recheck_broken_link', [$this, 'recheck_broken_link']);
        add_action('wp_ajax_ssf_dismiss_broken_link', [$this, 'dismiss_broken_link']);
        add_action('wp_ajax_ssf_undismiss_broken_link', [$this, 'undismiss_broken_link']);
        add_action('wp_ajax_ssf_bulk_redirect_broken_links', [$this, 'bulk_redirect_broken_links']);
        add_action('wp_ajax_ssf_bulk_dismiss_broken_links', [$this, 'bulk_dismiss_broken_links']);

        // Canonical fixer
        add_action('wp_ajax_ssf_auto_fix_canonicals', [$this, 'auto_fix_canonicals']);
        add_action('wp_ajax_ssf_scan_canonical_issues', [$this, 'scan_canonical_issues']);
        
        // 404 Monitor
        add_action('wp_ajax_ssf_get_404_logs', [$this, 'get_404_logs']);
        add_action('wp_ajax_ssf_dismiss_404', [$this, 'dismiss_404']);
        add_action('wp_ajax_ssf_create_404_redirect', [$this, 'create_404_redirect']);
        add_action('wp_ajax_ssf_clear_404_logs', [$this, 'clear_404_logs']);
        
        // robots.txt Editor
        add_action('wp_ajax_ssf_save_robots', [$this, 'save_robots']);
        
        // Readability
        add_action('wp_ajax_ssf_analyze_readability', [$this, 'analyze_readability']);
        
        // Social Preview
        add_action('wp_ajax_ssf_save_social_data', [$this, 'save_social_data']);
        add_action('wp_ajax_ssf_get_social_data', [$this, 'get_social_data']);
        
        // Keyword Tracker
        add_action('wp_ajax_ssf_get_tracked_keywords', [$this, 'get_tracked_keywords']);
        add_action('wp_ajax_ssf_get_keyword_history', [$this, 'get_keyword_history']);
        add_action('wp_ajax_ssf_fetch_keywords_now', [$this, 'fetch_keywords_now']);
        
        // Content Suggestions
        add_action('wp_ajax_ssf_content_suggestions', [$this, 'content_suggestions']);
        add_action('wp_ajax_ssf_apply_content_suggestion', [$this, 'apply_content_suggestion']);
        
        // WP Coding Standards
        add_action('wp_ajax_ssf_wp_standards_audit', [$this, 'wp_standards_audit']);
        
        // Performance Profiler
        add_action('wp_ajax_ssf_performance_data', [$this, 'performance_data']);
        add_action('wp_ajax_ssf_performance_clear', [$this, 'performance_clear']);
        
        // Content Duplication Detection
        add_action('wp_ajax_ssf_detect_duplicates', [$this, 'detect_duplicates']);
        
        // Core Web Vitals data
        add_action('wp_ajax_ssf_get_cwv_data', [$this, 'get_cwv_data']);
        
        // Internal Link Auto-Insertion
        add_action('wp_ajax_ssf_insert_internal_links', [$this, 'insert_internal_links']);
        
        // Bulk Fix Preview — Approve/Reject
        add_action('wp_ajax_ssf_apply_bulk_preview', [$this, 'apply_bulk_preview']);
        
        // Image SEO audit
        add_action('wp_ajax_ssf_audit_images', [$this, 'audit_images']);
        
        // Onboarding checklist
        add_action('wp_ajax_ssf_get_onboarding_status', [$this, 'get_onboarding_status']);
        add_action('wp_ajax_ssf_dismiss_onboarding', [$this, 'dismiss_onboarding']);
        
        // Generic background job dispatch & polling
        add_action('wp_ajax_ssf_dispatch_job', [$this, 'dispatch_job']);
        add_action('wp_ajax_ssf_poll_job', [$this, 'poll_job']);
        
        // Client Report
        add_action('wp_ajax_ssf_generate_client_report', [$this, 'generate_client_report']);
        add_action('wp_ajax_ssf_fetch_report_template', [$this, 'fetch_report_template']);
        add_action('wp_ajax_ssf_clear_report_template', [$this, 'clear_report_template']);

        // Image Alt Text
        add_action('wp_ajax_ssf_bulk_generate_alt', [$this, 'bulk_generate_alt']);
        add_action('wp_ajax_ssf_count_missing_alt', [$this, 'count_missing_alt']);
        add_action('wp_ajax_ssf_reset_skipped_alt', [$this, 'reset_skipped_alt']);
        add_action('wp_ajax_ssf_alt_stats', [$this, 'alt_stats']);
        add_action('wp_ajax_ssf_regenerate_alt', [$this, 'regenerate_alt']);
        add_action('wp_ajax_ssf_generate_single_alt', [$this, 'generate_single_alt']);
        add_action('wp_ajax_ssf_test_vision', [$this, 'test_vision']);
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!check_ajax_referer('ssf_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
    }
    
    /**
     * Analyze single post
     */
    public function analyze_post() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $analyzer = new SSF_Analyzer();
        $result = $analyzer->analyze_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // Track for onboarding checklist
        if (!get_option('ssf_first_analysis_done', false)) {
            update_option('ssf_first_analysis_done', true);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Bulk analyze posts (supports both post_ids array and offset/batch_size)
     */
    public function bulk_analyze() {
        $this->verify_nonce();
        
        // Check if using batch mode (offset/batch_size) or direct mode (post_ids)
        if (isset($_POST['offset'])) {
            // Batch mode for dashboard
            $offset = intval($_POST['offset'] ?? 0);
            $batch_size = intval($_POST['batch_size'] ?? 5);
            $mode = sanitize_text_field($_POST['analyze_mode'] ?? 'unanalyzed'); // 'unanalyzed' or 'all'
            
            global $wpdb;
            $table = $wpdb->prefix . 'ssf_seo_scores';
            $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
            $post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
            
            if ($mode === 'all') {
                // Get ALL published posts
                $total = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type IN ($post_types_str)
                ");
                
                $posts = $wpdb->get_col($wpdb->prepare("
                    SELECT ID 
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type IN ($post_types_str)
                    ORDER BY ID ASC
                    LIMIT %d OFFSET %d
                ", $batch_size, $offset));
            } else {
                // Get only unanalyzed posts
                // Check if table exists first
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                
                if ($table_exists) {
                    $total = $wpdb->get_var("
                        SELECT COUNT(*) 
                        FROM {$wpdb->posts} p
                        LEFT JOIN $table s ON p.ID = s.post_id
                        WHERE p.post_status = 'publish'
                        AND p.post_type IN ($post_types_str)
                        AND s.post_id IS NULL
                    ");
                    
                    $posts = $wpdb->get_col($wpdb->prepare("
                        SELECT p.ID 
                        FROM {$wpdb->posts} p
                        LEFT JOIN $table s ON p.ID = s.post_id
                        WHERE p.post_status = 'publish'
                        AND p.post_type IN ($post_types_str)
                        AND s.post_id IS NULL
                        ORDER BY p.ID ASC
                        LIMIT %d OFFSET %d
                    ", $batch_size, $offset));
                } else {
                    // Table doesn't exist — treat all as unanalyzed
                    $total = $wpdb->get_var("
                        SELECT COUNT(*) 
                        FROM {$wpdb->posts}
                        WHERE post_status = 'publish'
                        AND post_type IN ($post_types_str)
                    ");
                    
                    $posts = $wpdb->get_col($wpdb->prepare("
                        SELECT ID 
                        FROM {$wpdb->posts}
                        WHERE post_status = 'publish'
                        AND post_type IN ($post_types_str)
                        ORDER BY ID ASC
                        LIMIT %d OFFSET %d
                    ", $batch_size, $offset));
                }
            }
            
            $analyzer = new SSF_Analyzer();
            $log = [];
            
            foreach ($posts as $post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $analysis = $analyzer->analyze_post($post_id);
                    $log[] = sprintf('✅ %s (Score: %d)', $post->post_title, $analysis['score'] ?? 0);
                }
            }
            
            $done = ($offset + $batch_size) >= $total || empty($posts);
            
            // Track for onboarding checklist
            if ($done && !get_option('ssf_bulk_analyze_done', false)) {
                update_option('ssf_bulk_analyze_done', true);
            }
            
            wp_send_json_success([
                'processed' => count($posts),
                'total' => intval($total),
                'done' => $done,
                'log' => $log,
            ]);
        } else {
            // Direct mode with post_ids (accept CSV to avoid PHP max_input_vars truncation on large selections)
            if (!empty($_POST['post_ids_csv'])) {
                $csv = (string) wp_unslash($_POST['post_ids_csv']);
                $post_ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv))));
            } else {
                $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
            }
            
            if (empty($post_ids)) {
                wp_send_json_error(['message' => __('No posts selected.', 'smart-seo-fixer')]);
            }
            
            $analyzer = new SSF_Analyzer();
            $results = $analyzer->bulk_analyze($post_ids);
            
            wp_send_json_success($results);
        }
    }
    
    /**
     * Generate AI title
     */
    public function generate_title() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('ai');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $focus_keyword = sanitize_text_field(wp_unslash($_POST['focus_keyword'] ?? ''));
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
        $result = $openai->generate_title(
            $enriched,
            $post->post_title,
            $focus_keyword
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $title = SSF_Validator::enforce_seo_title(trim($result), 60);
        $title = sanitize_text_field($title);
        
        if (empty($title)) {
            wp_send_json_error(['message' => __('AI returned an empty title. Please try again.', 'smart-seo-fixer')]);
        }
        
        // Auto-save to post meta so generated content persists immediately
        update_post_meta($post_id, '_ssf_seo_title', $title);
        
        wp_send_json_success(['title' => $title]);
    }
    
    /**
     * Generate AI meta description
     */
    public function generate_description() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('ai');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $focus_keyword = sanitize_text_field(wp_unslash($_POST['focus_keyword'] ?? ''));
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $current_desc = get_post_meta($post_id, '_ssf_meta_description', true);
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

        $result = $openai->generate_meta_description(
            $enriched,
            $current_desc,
            $focus_keyword
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $description = SSF_Validator::enforce_meta_description(trim($result), 160);
        $description = sanitize_textarea_field($description);
        
        if (empty($description)) {
            wp_send_json_error(['message' => __('AI returned an empty description. Please try again.', 'smart-seo-fixer')]);
        }
        
        // Auto-save to post meta so generated content persists immediately
        update_post_meta($post_id, '_ssf_meta_description', $description);
        
        wp_send_json_success(['description' => $description]);
    }
    
    /**
     * Generate AI alt text for image
     */
    public function generate_alt_text() {
        $this->verify_nonce();
        
        $image_url = esc_url_raw(wp_unslash($_POST['image_url'] ?? ''));
        $page_context = sanitize_textarea_field(wp_unslash($_POST['page_context'] ?? ''));
        $focus_keyword = sanitize_text_field(wp_unslash($_POST['focus_keyword'] ?? ''));
        
        if (empty($image_url)) {
            wp_send_json_error(['message' => __('Image URL required.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $result = $openai->generate_alt_text($image_url, $page_context, $focus_keyword);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['alt_text' => trim($result)]);
    }
    
    /**
     * AI content analysis
     */
    public function ai_analyze() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $focus_keyword = sanitize_text_field(wp_unslash($_POST['focus_keyword'] ?? ''));
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
        $result = $openai->analyze_content(
            $enriched,
            $post->post_title,
            $focus_keyword
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Suggest focus keywords
     */
    public function suggest_keywords() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('ai');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
        $result = $openai->suggest_keywords($enriched, $post->post_title);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        if (!is_array($result) || empty($result['primary'])) {
            wp_send_json_error(['message' => __('Could not parse keyword suggestions. Please try again.', 'smart-seo-fixer')]);
        }
        
        // Auto-save primary keyword to post meta
        $primary = sanitize_text_field($result['primary']);
        update_post_meta($post_id, '_ssf_focus_keyword', $primary);
        
        wp_send_json_success($result);
    }
    
    /**
     * Save SEO data for a post
     */
    public function save_seo_data() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('manual');
        
        $post_id = class_exists('SSF_Validator') ? SSF_Validator::post_id($_POST['post_id'] ?? 0) : intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $data = [
            'seo_title'        => class_exists('SSF_Validator') ? SSF_Validator::enforce_seo_title(SSF_Validator::seo_title(wp_unslash($_POST['seo_title'] ?? '')), 60) : sanitize_text_field(wp_unslash($_POST['seo_title'] ?? '')),
            'meta_description' => class_exists('SSF_Validator') ? SSF_Validator::enforce_meta_description(SSF_Validator::meta_description(wp_unslash($_POST['meta_description'] ?? '')), 160) : sanitize_textarea_field(wp_unslash($_POST['meta_description'] ?? '')),
            'focus_keyword'    => class_exists('SSF_Validator') ? SSF_Validator::focus_keyword(wp_unslash($_POST['focus_keyword'] ?? '')) : sanitize_text_field(wp_unslash($_POST['focus_keyword'] ?? '')),
            'canonical_url'    => $this->normalize_canonical_for_storage(
                                       class_exists('SSF_Validator') ? SSF_Validator::url(wp_unslash($_POST['canonical_url'] ?? '')) : esc_url_raw(wp_unslash($_POST['canonical_url'] ?? '')),
                                       intval($_POST['post_id'] ?? 0)
                                   ),
            'noindex'          => !empty($_POST['noindex']) ? 1 : 0,
            'nofollow'         => !empty($_POST['nofollow']) ? 1 : 0,
        ];
        
        $meta_manager = new SSF_Meta_Manager();
        $meta_manager->save_post_seo_data($post_id, $data);
        
        // Re-analyze after save
        $analyzer = new SSF_Analyzer();
        $analysis = $analyzer->analyze_post($post_id);
        
        wp_send_json_success([
            'message' => __('SEO data saved successfully.', 'smart-seo-fixer'),
            'analysis' => $analysis,
        ]);
    }
    
    /**
     * Test AWS Bedrock connection with the provided credentials
     */
    public function test_bedrock() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_Bedrock')) {
            wp_send_json_error(['message' => __('Bedrock class not available.', 'smart-seo-fixer')]);
        }

        $using_const = defined('SSF_BEDROCK_ACCESS_KEY') && SSF_BEDROCK_ACCESS_KEY !== ''
                    && defined('SSF_BEDROCK_SECRET_KEY') && SSF_BEDROCK_SECRET_KEY !== '';

        $access_key = sanitize_text_field(wp_unslash($_POST['access_key'] ?? ''));
        $secret_key = sanitize_text_field(wp_unslash($_POST['secret_key'] ?? ''));

        // Only treat this as "testing newly typed-in keys" if the user
        // actually entered something. Blank fields here don't mean "no
        // credentials" — SSF_Bedrock::request() always tries the broker
        // first regardless, so testing with nothing typed in still
        // exercises the real connection this site would actually use
        // (broker, previously-saved options, or nothing).
        $testing_new_keys = !$using_const && (!empty($access_key) || !empty($secret_key));

        if (!$testing_new_keys) {
            // wp-config constants, OR nothing typed in — test the
            // connection exactly as this site's real AI calls would use it.
            $bedrock = new SSF_Bedrock();
            $result  = $bedrock->request(
                [['role' => 'user', 'content' => 'Reply with exactly the word: CONNECTED']],
                10,
                0.0
            );
        } else {
            $region = sanitize_text_field(wp_unslash($_POST['region'] ?? 'us-east-1'));

            if (empty($access_key) || empty($secret_key)) {
                wp_send_json_error(['message' => __('Enter both an Access Key and Secret Key to test new credentials, or leave both blank to test the current connection.', 'smart-seo-fixer')]);
            }

            // Temporarily override options so SSF_Bedrock uses these values
            $original = [
                'bedrock_access_key' => Smart_SEO_Fixer::get_option('bedrock_access_key'),
                'bedrock_secret_key' => Smart_SEO_Fixer::get_option('bedrock_secret_key'),
                'bedrock_region'     => Smart_SEO_Fixer::get_option('bedrock_region', 'us-east-1'),
            ];

            $opts = get_option('smart_seo_fixer_options', []);
            $opts['bedrock_access_key'] = $access_key;
            $opts['bedrock_secret_key'] = $secret_key;
            $opts['bedrock_region']     = $region;
            update_option('smart_seo_fixer_options', $opts);

            $bedrock = new SSF_Bedrock();
            $result  = $bedrock->request(
                [['role' => 'user', 'content' => 'Reply with exactly the word: CONNECTED']],
                10,
                0.0
            );

            // Restore original credentials
            $opts['bedrock_access_key'] = $original['bedrock_access_key'];
            $opts['bedrock_secret_key'] = $original['bedrock_secret_key'];
            $opts['bedrock_region']     = $original['bedrock_region'];
            update_option('smart_seo_fixer_options', $opts);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['reply' => trim($result)]);
    }

    /**
     * Test any AI provider connection
     */
    public function test_ai_provider() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        $test_msg = [['role' => 'user', 'content' => 'Reply with exactly the word: CONNECTED']];

        // Temporarily swap credentials so the provider class picks up the unsaved values
        $originals = [];

        switch ($provider) {
            case 'bedrock':
                // Bedrock has its own test handler — delegate
                $this->test_bedrock();
                return;

            case 'openai':
                $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
                $model   = sanitize_text_field(wp_unslash($_POST['model']   ?? 'gpt-4o-mini'));
                if (empty($api_key)) {
                    wp_send_json_error(['message' => __('API key is required.', 'smart-seo-fixer')]);
                }
                $originals = [
                    'openai_api_key' => Smart_SEO_Fixer::get_option('openai_api_key'),
                    'openai_model'   => Smart_SEO_Fixer::get_option('openai_model'),
                ];
                Smart_SEO_Fixer::update_option('openai_api_key', $api_key);
                Smart_SEO_Fixer::update_option('openai_model', $model);
                $instance = new SSF_OpenAI();
                break;

            case 'claude':
                $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
                $model   = sanitize_text_field(wp_unslash($_POST['model']   ?? 'claude-sonnet-4-20250514'));
                if (empty($api_key)) {
                    wp_send_json_error(['message' => __('API key is required.', 'smart-seo-fixer')]);
                }
                $originals = [
                    'claude_api_key' => Smart_SEO_Fixer::get_option('claude_api_key'),
                    'claude_model'   => Smart_SEO_Fixer::get_option('claude_model'),
                ];
                Smart_SEO_Fixer::update_option('claude_api_key', $api_key);
                Smart_SEO_Fixer::update_option('claude_model', $model);
                $instance = new SSF_Claude();
                break;

            case 'gemini':
                $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
                $model   = sanitize_text_field(wp_unslash($_POST['model']   ?? 'gemini-2.0-flash'));
                if (empty($api_key)) {
                    wp_send_json_error(['message' => __('API key is required.', 'smart-seo-fixer')]);
                }
                $originals = [
                    'gemini_api_key' => Smart_SEO_Fixer::get_option('gemini_api_key'),
                    'gemini_model'   => Smart_SEO_Fixer::get_option('gemini_model'),
                ];
                Smart_SEO_Fixer::update_option('gemini_api_key', $api_key);
                Smart_SEO_Fixer::update_option('gemini_model', $model);
                $instance = new SSF_Gemini();
                break;

            default:
                wp_send_json_error(['message' => __('Unknown provider.', 'smart-seo-fixer')]);
                return;
        }

        $result = $instance->request($test_msg, 10, 0.0);

        // Restore original credentials
        foreach ($originals as $k => $v) {
            Smart_SEO_Fixer::update_option($k, $v);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['reply' => trim($result)]);
    }

    /**
     * Save plugin settings
     */
    public function save_settings() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $v = class_exists('SSF_Validator');
        
                $settings = [
            'ai_provider'             => in_array(($_POST['ai_provider'] ?? ''), ['bedrock', 'openai', 'claude', 'gemini'], true) ? sanitize_text_field($_POST['ai_provider']) : 'bedrock',
            'bedrock_region'          => sanitize_text_field(wp_unslash($_POST['bedrock_region'] ?? 'us-east-1')),
            'bedrock_access_key'      => $v ? SSF_Validator::api_key(wp_unslash($_POST['bedrock_access_key'] ?? '')) : sanitize_text_field(wp_unslash($_POST['bedrock_access_key'] ?? '')),
            'bedrock_secret_key'      => $v ? SSF_Validator::api_key(wp_unslash($_POST['bedrock_secret_key'] ?? '')) : sanitize_text_field(wp_unslash($_POST['bedrock_secret_key'] ?? '')),
            'bedrock_model'           => 'us.anthropic.claude-haiku-4-5-20251001-v1:0',
            'openai_api_key'          => $v ? SSF_Validator::api_key(wp_unslash($_POST['openai_api_key'] ?? '')) : sanitize_text_field(wp_unslash($_POST['openai_api_key'] ?? '')),
            'openai_model'            => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? 'gpt-4o-mini')),
            'claude_api_key'          => $v ? SSF_Validator::api_key(wp_unslash($_POST['claude_api_key'] ?? '')) : sanitize_text_field(wp_unslash($_POST['claude_api_key'] ?? '')),
            'claude_model'            => sanitize_text_field(wp_unslash($_POST['claude_model'] ?? 'claude-sonnet-4-20250514')),
            'gemini_api_key'          => $v ? SSF_Validator::api_key(wp_unslash($_POST['gemini_api_key'] ?? '')) : sanitize_text_field(wp_unslash($_POST['gemini_api_key'] ?? '')),
            'gemini_model'            => sanitize_text_field(wp_unslash($_POST['gemini_model'] ?? 'gemini-2.0-flash')),
            'auto_meta'               => !empty($_POST['auto_meta']) ? 1 : 0,
            'auto_alt_text'           => !empty($_POST['auto_alt_text']) ? 1 : 0,
            'auto_internal_links'     => !empty($_POST['auto_internal_links']) ? 1 : 0,
            'auto_noindex_thin'       => !empty($_POST['auto_noindex_thin']) ? 1 : 0,
            'enrich_image_posts'      => !empty($_POST['enrich_image_posts']) ? 1 : 0,
            'thin_content_threshold'  => max(20, min(300, intval($_POST['thin_content_threshold'] ?? 50))),
            'enable_schema'           => !empty($_POST['enable_schema']) ? 1 : 0,
            'enable_sitemap'          => !empty($_POST['enable_sitemap']) ? 1 : 0,
            'disable_other_seo_output'=> !empty($_POST['disable_other_seo_output']) ? 1 : 0,
            'redirect_attachments'    => in_array(($_POST['redirect_attachments'] ?? ''), ['parent', 'file'], true) ? sanitize_text_field($_POST['redirect_attachments']) : '',
            'background_seo_cron'     => !empty($_POST['background_seo_cron']) ? 1 : 0,
            'gsc_client_id'           => sanitize_text_field(wp_unslash($_POST['gsc_client_id'] ?? '')),
            'gsc_client_secret'       => sanitize_text_field(wp_unslash($_POST['gsc_client_secret'] ?? '')),
            'title_separator'         => $v ? SSF_Validator::title_separator(wp_unslash($_POST['title_separator'] ?? '|')) : sanitize_text_field(wp_unslash($_POST['title_separator'] ?? '|')),
            'homepage_title'          => $v ? SSF_Validator::seo_title(wp_unslash($_POST['homepage_title'] ?? '')) : sanitize_text_field(wp_unslash($_POST['homepage_title'] ?? '')),
            'homepage_description'    => $v ? SSF_Validator::meta_description(wp_unslash($_POST['homepage_description'] ?? '')) : sanitize_textarea_field(wp_unslash($_POST['homepage_description'] ?? '')),
        ];
        
        // Schedule or unschedule background cron based on setting
        $cron_enabled = !empty($_POST['background_seo_cron']);
        $cron_scheduled = wp_next_scheduled('ssf_cron_generate_missing_seo');
        
        if ($cron_enabled && !$cron_scheduled) {
            wp_schedule_event(time(), 'twicedaily', 'ssf_cron_generate_missing_seo');
        } elseif (!$cron_enabled && $cron_scheduled) {
            wp_unschedule_event($cron_scheduled, 'ssf_cron_generate_missing_seo');
        }
        
        // Handle post types array
        if (isset($_POST['post_types']) && is_array($_POST['post_types'])) {
            $settings['post_types'] = $v ? SSF_Validator::post_types($_POST['post_types']) : array_map('sanitize_text_field', $_POST['post_types']);
        }
        
        // Handle GSC site URL selection (can be URL or sc-domain: format)
        if (isset($_POST['gsc_site_url'])) {
            $val = sanitize_text_field($_POST['gsc_site_url']);
            if (strpos($val, 'sc-domain:') === 0 || filter_var($val, FILTER_VALIDATE_URL)) {
                $settings['gsc_site_url'] = $val;
            }
        }
        
        // Preserve existing credentials if not re-submitted
        if (empty($settings['bedrock_access_key'])) {
            unset($settings['bedrock_access_key']);
        }
        if (empty($settings['bedrock_secret_key'])) {
            unset($settings['bedrock_secret_key']);
        }
        if (empty($settings['openai_api_key'])) {
            unset($settings['openai_api_key']);
        }
        if (empty($settings['claude_api_key'])) {
            unset($settings['claude_api_key']);
        }
        if (empty($settings['gemini_api_key'])) {
            unset($settings['gemini_api_key']);
        }
        
        // Preserve existing GSC credentials if not submitted (connected state hides the fields)
        if (empty($settings['gsc_client_id'])) {
            unset($settings['gsc_client_id']);
        }
        if (empty($settings['gsc_client_secret'])) {
            unset($settings['gsc_client_secret']);
        }
        
        foreach ($settings as $key => $value) {
            Smart_SEO_Fixer::update_option($key, $value);
        }
        
        // Flush rewrite rules if sitemap setting changed
        SSF_Sitemap::flush_rules();
        
        wp_send_json_success(['message' => __('Settings saved successfully.', 'smart-seo-fixer')]);
    }
    
    /**
     * Fix a specific issue
     */
    public function fix_issue() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('ai');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $issue_code = sanitize_text_field($_POST['issue_code'] ?? '');
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        $result = [];
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        switch ($issue_code) {
            case 'no_title':
            case 'title_too_short':
            case 'keyword_not_in_title':
                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                
                if (is_wp_error($title)) {
                    wp_send_json_error(['message' => $title->get_error_message()]);
                }
                if (!empty(trim($title))) {
                    $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                    $result['seo_title'] = $title;
                } else {
                    wp_send_json_error(['message' => __('AI returned empty title. Try again.', 'smart-seo-fixer')]);
                }
                break;
                
            case 'no_meta_description':
            case 'meta_too_short':
            case 'keyword_not_in_meta':
                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                
                if (is_wp_error($desc)) {
                    wp_send_json_error(['message' => $desc->get_error_message()]);
                }
                if (!empty(trim($desc))) {
                    $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                    $result['meta_description'] = $desc;
                } else {
                    wp_send_json_error(['message' => __('AI returned empty description. Try again.', 'smart-seo-fixer')]);
                }
                break;
                
            default:
                wp_send_json_error(['message' => __('Unknown issue type.', 'smart-seo-fixer')]);
        }
        
        // Re-analyze
        $analyzer = new SSF_Analyzer();
        $analysis = $analyzer->analyze_post($post_id);
        
        wp_send_json_success([
            'message' => __('Issue fixed successfully.', 'smart-seo-fixer'),
            'fixed' => $result,
            'analysis' => $analysis,
        ]);
    }
    
    /**
     * Bulk fix issues
     */
    public function bulk_fix() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('bulk');
        
        // Accept CSV form to avoid PHP max_input_vars truncation on large selections.
        if (!empty($_POST['post_ids_csv'])) {
            $csv = (string) wp_unslash($_POST['post_ids_csv']);
            $post_ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv))));
        } else {
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
        }
        $issue_types = isset($_POST['issue_types']) ? array_map('sanitize_text_field', $_POST['issue_types']) : [];
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $analyzer = class_exists('SSF_Analyzer') ? new SSF_Analyzer() : null;
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'posts' => [],
        ];

        // Parallel fast path: fire all title+desc Bedrock calls concurrently.
        $use_parallel = (
            $openai instanceof SSF_Bedrock
            && function_exists('curl_multi_init')
            && class_exists('SSF_AI')
            && SSF_AI::active_provider() === 'bedrock'
        );

        if ($use_parallel) {
            // Phase 1 — build per-post work and the concurrent request payload.
            $work    = [];
            $jobs    = [];
            foreach ($post_ids as $post_id) {
                if (!current_user_can('edit_post', $post_id)) { $results['failed']++; continue; }
                $post = get_post($post_id);
                if (!$post) { $results['failed']++; continue; }

                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                $needs_title = false;
                $needs_desc  = false;

                if (in_array('title', $issue_types)) {
                    $current_title = get_post_meta($post_id, '_ssf_seo_title', true);
                    if (empty($current_title) || strlen($current_title) < 30) { $needs_title = true; }
                }
                if (in_array('meta', $issue_types)) {
                    $current_desc = get_post_meta($post_id, '_ssf_meta_description', true);
                    if (empty($current_desc) || strlen($current_desc) < 120) { $needs_desc = true; }
                }

                $work[$post_id] = [
                    'post' => $post, 'kw' => $focus_keyword,
                    'needs_title' => $needs_title, 'needs_desc' => $needs_desc,
                ];
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                if ($needs_title) {
                    $jobs["t_{$post_id}"] = [
                        'messages'    => $openai->build_title_messages($enriched, $post->post_title, $focus_keyword),
                        'max_tokens'  => 100,
                        'temperature' => 0.3,
                    ];
                }
                if ($needs_desc) {
                    $jobs["d_{$post_id}"] = [
                        'messages'    => $openai->build_desc_messages($enriched, '', $focus_keyword),
                        'max_tokens'  => 200,
                        'temperature' => 0.3,
                    ];
                }
            }

            $responses = !empty($jobs) ? $openai->request_multi($jobs) : [];

            // Phase 2 — apply results.
            foreach ($work as $post_id => $w) {
                $fixed = [];
                if ($w['needs_title']) {
                    $r = $responses["t_{$post_id}"] ?? null;
                    if (!is_wp_error($r) && !empty(trim((string) $r))) {
                        $title = SSF_Validator::enforce_seo_title(trim((string) $r, " \t\n\r\0\x0B\"'"), 60);
                        update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                        $fixed[] = 'title';
                    }
                }
                if ($w['needs_desc']) {
                    $r = $responses["d_{$post_id}"] ?? null;
                    if (!is_wp_error($r) && !empty(trim((string) $r))) {
                        $desc = SSF_Validator::enforce_meta_description(trim((string) $r, " \t\n\r\0\x0B\"'"), 160);
                        update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                        $fixed[] = 'meta';
                    }
                }

                $score = 0;
                if ($analyzer) {
                    $analysis = $analyzer->analyze_post($post_id);
                    $score = $analysis['score'] ?? 0;
                }
                $results['success']++;
                $results['posts'][$post_id] = ['fixed' => $fixed, 'new_score' => $score];
            }

            wp_send_json_success($results);
        }

        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                continue;
            }
            
            $post = get_post($post_id);
            if (!$post) {
                $results['failed']++;
                continue;
            }
            
            $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
            $fixed = [];
            $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

            // Generate title if needed
            if (in_array('title', $issue_types)) {
                $current_title = get_post_meta($post_id, '_ssf_seo_title', true);
                if (empty($current_title) || strlen($current_title) < 30) {
                    $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                    if (!is_wp_error($title) && !empty(trim($title))) {
                        $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                        update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                        $fixed[] = 'title';
                    }
                }
            }
            
            // Generate meta description if needed
            if (in_array('meta', $issue_types)) {
                $current_desc = get_post_meta($post_id, '_ssf_meta_description', true);
                if (empty($current_desc) || strlen($current_desc) < 120) {
                    $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                    if (!is_wp_error($desc) && !empty(trim($desc))) {
                        $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                        update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                        $fixed[] = 'meta';
                    }
                }
            }
            
            // Re-analyze
            $score = 0;
            if ($analyzer) {
                $analysis = $analyzer->analyze_post($post_id);
                $score = $analysis['score'] ?? 0;
            }
            
            $results['success']++;
            $results['posts'][$post_id] = [
                'fixed' => $fixed,
                'new_score' => $score,
            ];
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Get SEO data for a post
     */
    public function get_post_seo_data() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $meta_manager = new SSF_Meta_Manager();
        $data = $meta_manager->get_post_seo_data($post_id);
        
        // Get analysis
        $analyzer = new SSF_Analyzer();
        $analysis = $analyzer->get_analysis($post_id);
        
        if ($analysis) {
            $data['analysis'] = [
                'score' => $analysis->score,
                'issues' => json_decode($analysis->issues, true),
                'suggestions' => json_decode($analysis->suggestions, true),
                'last_analyzed' => $analysis->last_analyzed,
            ];
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        $this->verify_nonce();
        
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        
        $stats = null;
        $needs_attention = [];
        $recent = [];
        
        if ($table_exists) {
            // Get overall stats
            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_posts,
                    AVG(score) as avg_score,
                    SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as good_count,
                    SUM(CASE WHEN score >= 60 AND score < 80 THEN 1 ELSE 0 END) as ok_count,
                    SUM(CASE WHEN score < 60 THEN 1 ELSE 0 END) as poor_count
                FROM $table
            ");
            
            // Get posts needing attention
            $needs_attention = $wpdb->get_results("
                SELECT s.*, p.post_title 
                FROM $table s
                JOIN {$wpdb->posts} p ON s.post_id = p.ID
                WHERE s.score < 60
                ORDER BY s.score ASC
                LIMIT 10
            ") ?: [];
            
            // Get recently analyzed
            $recent = $wpdb->get_results("
                SELECT s.*, p.post_title 
                FROM $table s
                JOIN {$wpdb->posts} p ON s.post_id = p.ID
                ORDER BY s.last_analyzed DESC
                LIMIT 10
            ") ?: [];
        }
        
        // Count unanalyzed posts
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        
        // Sanitize post types for SQL
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        if ($table_exists) {
            $unanalyzed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM {$wpdb->posts} p
                    LEFT JOIN $table s ON p.ID = s.post_id
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND s.post_id IS NULL",
                    ...$post_types
                )
            );
        } else {
            // If table doesn't exist, all published posts are unanalyzed
            $unanalyzed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type IN ($placeholders)",
                    ...$post_types
                )
            );
        }
        
        // Count posts missing AI-generated SEO title
        $missing_titles = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_seo_title'
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                ...$post_types
            )
        );
        
        // Count posts missing meta description
        $missing_descs = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_meta_description'
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                ...$post_types
            )
        );
        
        // Cron status
        $cron_last = get_option('ssf_cron_last_run', null);
        $cron_next = wp_next_scheduled('ssf_cron_generate_missing_seo');
        
        wp_send_json_success([
            'total_posts' => intval($stats->total_posts ?? 0),
            'avg_score' => round($stats->avg_score ?? 0),
            'good_count' => intval($stats->good_count ?? 0),
            'ok_count' => intval($stats->ok_count ?? 0),
            'poor_count' => intval($stats->poor_count ?? 0),
            'unanalyzed' => intval($unanalyzed),
            'missing_titles' => intval($missing_titles),
            'missing_descs' => intval($missing_descs),
            'cron_last_run' => $cron_last,
            'cron_next_run' => $cron_next ? date('Y-m-d H:i:s', $cron_next) : null,
            'needs_attention' => $needs_attention,
            'recent' => $recent,
        ]);
    }
    
    /**
     * Suggest internal links based on content
     */
    public function suggest_internal_links() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }

        // IMPORTANT: prefer the live editor content (sent from the meta-box JS)
        // over $post->post_content, which is the last-saved version. On a new
        // or just-edited post, the DB content is stale and the AI has nothing
        // to anchor into, which is why this button used to find nothing.
        $live_content = isset($_POST['content']) ? (string) wp_unslash($_POST['content']) : '';
        $source_content = trim($live_content) !== '' ? $live_content : $post->post_content;
        if (trim(wp_strip_all_tags(strip_shortcodes($source_content))) === '') {
            wp_send_json_error(['message' => __('Add some content to the post first, then click again.', 'smart-seo-fixer')]);
        }

        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);

        // Find candidate posts with broader matching (same approach the orphan
        // fixer uses on the Indexability page) instead of WP's narrow ?s= search.
        global $wpdb;
        $post_types   = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $candidates   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)
                 AND ID != %d AND LENGTH(post_content) > 200
                 ORDER BY post_date DESC LIMIT 60",
                ...array_merge($post_types, [$post_id])
            )
        );

        // Score candidates by title-word + keyword overlap with the source post.
        $source_text = strtolower($post->post_title . ' ' . ($focus_keyword ? $focus_keyword . ' ' : '') . wp_strip_all_tags($source_content));
        $source_words = array_unique(array_filter(str_word_count($source_text, 1), function($w){ return strlen($w) > 3; }));
        $scored = [];
        foreach ($candidates as $c) {
            $url = get_permalink($c->ID);
            if ($url && stripos($source_content, $url) !== false) { continue; }
            $path = wp_parse_url($url, PHP_URL_PATH);
            if ($path && stripos($source_content, $path) !== false) { continue; }
            $ctext = strtolower($c->post_title);
            $score = 0;
            foreach ($source_words as $w) { if (stripos($ctext, $w) !== false) { $score++; } }
            if ($score <= 0) { continue; }
            $scored[] = ['post' => $c, 'url' => $url, 'score' => $score];
        }
        usort($scored, function($a,$b){ return $b['score'] - $a['score']; });
        $scored = array_slice($scored, 0, 6); // try up to 6 to find 3 good anchors

        $ai    = SSF_AI::get();
        $links = [];

        // Parallel fast path on Bedrock: fire all AI anchor searches at once.
        $use_parallel = (
            $ai instanceof SSF_Bedrock
            && function_exists('curl_multi_init')
            && class_exists('SSF_AI')
            && SSF_AI::active_provider() === 'bedrock'
            && $ai->is_configured()
            && !empty($scored)
        );

        if ($use_parallel && method_exists($ai, 'build_internal_link_messages')) {
            $jobs = [];
            foreach ($scored as $i => $s) {
                $jobs["il_{$i}"] = [
                    'messages'    => $ai->build_internal_link_messages($source_content, $s['post']->post_title, $s['url']),
                    'max_tokens'  => 150,
                    'temperature' => 0.3,
                ];
            }
            $responses = $ai->request_multi($jobs);
            foreach ($scored as $i => $s) {
                if (count($links) >= 3) { break; }
                $r = $responses["il_{$i}"] ?? null;
                if (is_wp_error($r) || $r === null) { continue; }
                $placement = $ai->parse_internal_link_placement($r);
                if (is_wp_error($placement)) { continue; }
                if (empty($placement['found']) || empty($placement['anchor_text'])) { continue; }
                if (stripos($source_content, $placement['anchor_text']) === false) { continue; }
                $links[] = [
                    'title'       => $s['post']->post_title,
                    'url'         => $s['url'],
                    'anchor_text' => $placement['anchor_text'],
                ];
            }
        } else {
            foreach ($scored as $s) {
                if (count($links) >= 3) { break; }
                if ($ai->is_configured()) {
                    $placement = $ai->find_internal_link_placement($source_content, $s['post']->post_title, $s['url']);
                    if (!is_wp_error($placement)
                        && !empty($placement['found'])
                        && !empty($placement['anchor_text'])
                        && stripos($source_content, $placement['anchor_text']) !== false) {
                        $links[] = [
                            'title'       => $s['post']->post_title,
                            'url'         => $s['url'],
                            'anchor_text' => $placement['anchor_text'],
                        ];
                    }
                } else {
                    $links[] = [
                        'title'       => $s['post']->post_title,
                        'url'         => $s['url'],
                        'anchor_text' => '',
                    ];
                }
            }
        }

        if (empty($links)) {
            wp_send_json_error(['message' => __('No suitable anchor phrases found in this content for internal linking.', 'smart-seo-fixer')]);
        }

        wp_send_json_success(['links' => $links]);
    }
    
    /**
     * Suggest external authoritative links using AI
     */
    public function suggest_external_links() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        // Prepare content — strip tags and limit length for the AI prompt
        $plain_content = wp_strip_all_tags($post->post_content);
        if (strlen($plain_content) > 2500) {
            $plain_content = substr($plain_content, 0, 2500);
        }
        
        // Use AI to suggest authoritative sources and find exact anchor phrases in the content
        $prompt = "Analyze the following content and suggest 3 authoritative external sources to link to for credibility and SEO.\n\n";
        $prompt .= "For each suggestion provide:\n";
        $prompt .= "- 'url': a real, working URL to an authoritative source (Wikipedia, government site, major publication, or official documentation)\n";
        $prompt .= "- 'anchor_text': an EXACT phrase (2-6 words) copied VERBATIM from the content below — this phrase will become the hyperlink\n";
        $prompt .= "- 'reason': one sentence explaining why this source adds value\n\n";
        $prompt .= "CRITICAL: 'anchor_text' must appear EXACTLY as written in the content. Do not invent or paraphrase phrases.\n\n";
        $prompt .= "Return ONLY a JSON array: [{\"url\":\"https://...\",\"anchor_text\":\"...\",\"reason\":\"...\"}]\n\n";
        $prompt .= "Content:\n" . $plain_content;
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert. Suggest authoritative sources with real URLs. Respond only with valid JSON array.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $response = $openai->request($messages, 500, 0.7);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        
        // Parse response
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $suggestions = json_decode(trim($response), true);
        
        if (!is_array($suggestions)) {
            wp_send_json_error(['message' => __('Could not parse AI response.', 'smart-seo-fixer')]);
        }
        
        // Filter: only keep suggestions with a valid URL and anchor_text that exists verbatim in the content
        $valid = [];
        foreach ($suggestions as $s) {
            if (empty($s['url']) || strpos($s['url'], 'http') !== 0) {
                continue;
            }
            if (empty($s['anchor_text'])) {
                continue;
            }
            if (stripos($post->post_content, $s['anchor_text']) === false) {
                continue;
            }
            $valid[] = $s;
        }
        
        if (empty($valid)) {
            wp_send_json_error(['message' => __('Could not find suitable anchor phrases in this content for external linking.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success(['suggestions' => $valid]);
    }
    
    /**
     * Fix missing image alt texts in post
     */
    public function fix_image_alt_texts() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        // Find images in content
        preg_match_all('/<img[^>]+>/i', $post->post_content, $images);
        
        if (empty($images[0])) {
            wp_send_json_success(['message' => __('No images found in content.', 'smart-seo-fixer'), 'fixed' => []]);
        }
        
        $openai = SSF_AI::get();
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        $fixed = [];
        $new_content = $post->post_content;

        /**
         * Persist alt text to the attachment as well as the inline <img> tag.
         * Without this the media library and the "missing alt text" counters
         * still report the image as missing, because they read attachment meta.
         */
        $sync_attachment_alt = function ($image_url, $alt_text) {
            if ($alt_text === '') {
                return;
            }
            $attachment_id = attachment_url_to_postid($image_url);
            if (!$attachment_id) {
                return;
            }
            $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (empty(trim((string) $existing))) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                // Tag it as ours so "Rewrite Existing Alt Text" may replace it.
                update_post_meta($attachment_id, SSF_Image_SEO::GENERATED_META, 'ai');
            }
        };

        // Collect images that need alt text first (cheap work).
        $to_fix = [];
        foreach ($images[0] as $img_tag) {
            if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                if (!empty(trim($alt_match[1]))) { continue; }
            }
            if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                $to_fix[] = ['tag' => $img_tag, 'src' => $src_match[1]];
            }
        }

        // Parallel Bedrock fast path: one curl_multi call for all images.
        $use_parallel = (
            $openai instanceof SSF_Bedrock
            && function_exists('curl_multi_init')
            && class_exists('SSF_AI')
            && SSF_AI::active_provider() === 'bedrock'
            && $openai->is_configured()
            && !empty($to_fix)
        );

        if ($use_parallel) {
            $page_context = wp_trim_words($post->post_content, 50);
            $jobs = [];
            $vision_error = '';
            foreach ($to_fix as $i => $img) {
                // Returns WP_Error when the model cannot see images or the bytes
                // are unreadable. Skip those rather than sending a prompt the
                // model would answer by guessing from the file slug.
                $messages = $openai->build_alt_messages($img['src'], $page_context, $focus_keyword);
                if (is_wp_error($messages)) {
                    if ($vision_error === '') {
                        $vision_error = $messages->get_error_message();
                    }
                    continue;
                }
                $jobs["img_{$i}"] = [
                    'messages'    => $messages,
                    'max_tokens'  => 100,
                    'temperature' => 0.4,
                ];
            }
            $responses = !empty($jobs) ? $openai->request_multi($jobs) : [];
            foreach ($to_fix as $i => $img) {
                $r = $responses["img_{$i}"] ?? null;
                if (is_wp_error($r) || $r === null) { continue; }
                $alt_text = SSF_Image_SEO::sanitize_generated_alt((string) $r);
                if ($alt_text === '') { continue; }
                $filename = basename(parse_url($img['src'], PHP_URL_PATH));
                if (strpos($img['tag'], 'alt=') !== false) {
                    $new_img_tag = preg_replace('/alt\s*=\s*["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img['tag']);
                } else {
                    $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $img['tag']);
                }
                $new_content = str_replace($img['tag'], $new_img_tag, $new_content);
                $sync_attachment_alt($img['src'], $alt_text);
                $fixed[] = ['filename' => $filename, 'alt' => $alt_text];
            }

            // Update post content
            if (!empty($fixed)) {
                wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
            }

            $message = sprintf(__('Fixed %d images with missing alt text.', 'smart-seo-fixer'), count($fixed));
            if ($vision_error !== '') {
                $message .= ' ' . sprintf(
                    /* translators: %s: reason the AI could not see the image */
                    __('Some images were skipped: %s', 'smart-seo-fixer'),
                    $vision_error
                );
            }

            wp_send_json_success([
                'message'      => $message,
                'fixed'        => $fixed,
                'vision_error' => $vision_error,
            ]);
        }

        foreach ($images[0] as $img_tag) {
            // Check if has alt or alt is empty
            if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                if (!empty(trim($alt_match[1]))) {
                    continue; // Already has alt text
                }
            }
            
            // Get image src
            if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                $image_url = $src_match[1];
                $filename = basename(parse_url($image_url, PHP_URL_PATH));
                
                // Generate alt text
                if ($openai->is_configured()) {
                    $alt_text = $openai->generate_alt_text($image_url, wp_trim_words($post->post_content, 50), $focus_keyword);

                    if (!is_wp_error($alt_text)) {
                        $alt_text = SSF_Image_SEO::sanitize_generated_alt($alt_text);

                        if ($alt_text !== '') {
                            // Add or replace alt attribute
                            if (strpos($img_tag, 'alt=') !== false) {
                                $new_img_tag = preg_replace('/alt\s*=\s*["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img_tag);
                            } else {
                                $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $img_tag);
                            }

                            $new_content = str_replace($img_tag, $new_img_tag, $new_content);
                            $sync_attachment_alt($image_url, $alt_text);
                            $fixed[] = ['filename' => $filename, 'alt' => $alt_text];
                        }
                    }
                } else {
                    // No AI configured — derive from the filename as a last resort.
                    $alt_text = SSF_Image_SEO::generate_alt_from_filename($filename);

                    if ($alt_text !== '') {
                        if (strpos($img_tag, 'alt=') !== false) {
                            $new_img_tag = preg_replace('/alt\s*=\s*["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img_tag);
                        } else {
                            $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $img_tag);
                        }

                        $new_content = str_replace($img_tag, $new_img_tag, $new_content);
                        $sync_attachment_alt($image_url, $alt_text);
                        $fixed[] = ['filename' => $filename, 'alt' => $alt_text];
                    }
                }
            }
        }
        
        // Update post content
        if (!empty($fixed)) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content,
            ]);
        }
        
        wp_send_json_success([
            'message' => sprintf(__('Fixed %d images with missing alt text.', 'smart-seo-fixer'), count($fixed)),
            'fixed' => $fixed,
        ]);
    }
    
    /**
     * Get Google Map embed code
     */
    public function get_map_embed() {
        $this->verify_nonce();
        
        $local_seo = new SSF_Local_SEO();
        $settings = $local_seo->get_settings();
        
        if (empty($settings['address']['street']) && empty($settings['address']['city'])) {
            wp_send_json_error(['message' => __('No business address configured.', 'smart-seo-fixer')]);
        }
        
        // Build address string
        $address_parts = array_filter([
            $settings['address']['street'],
            $settings['address']['city'],
            $settings['address']['state'],
            $settings['address']['zip'],
            $settings['address']['country'],
        ]);
        
        $address = implode(', ', $address_parts);
        $encoded_address = urlencode($address);
        
        // Embed using the free Google Maps search mode (no API key required)
        $embed = '<iframe 
            src="https://maps.google.com/maps?q=' . esc_attr($encoded_address) . '&output=embed" 
            width="100%" 
            height="400" 
            style="border:0;" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>';
        
        wp_send_json_success([
            'embed'   => $embed,
            'address' => $address,
        ]);
    }
    
    /**
     * Suggest images for content using AI
     */
    public function suggest_images() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $prompt = "Based on this content, suggest 3-5 types of images that would enhance SEO and user engagement. Format as JSON array with 'type' (e.g., 'Hero Image', 'Infographic'), 'description' (what it should show), and 'search_term' (keyword to find similar stock photos).\n\nContent:\n" . wp_trim_words($post->post_content, 300);
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO and content expert. Suggest images that would improve engagement and SEO. Respond only with valid JSON array.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $response = $openai->request($messages, 500, 0.7);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $suggestions = json_decode(trim($response), true);
        
        if (!is_array($suggestions)) {
            wp_send_json_error(['message' => __('Could not parse AI response.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success(['suggestions' => $suggestions]);
    }
    
    /**
     * Preview posts that need AI fixes (returns list without making changes)
     */
    public function preview_bulk_fix() {
        $this->verify_nonce();
        
        $apply_to = sanitize_text_field($_POST['apply_to'] ?? 'missing');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Helper: subquery to check if a meta key has a real (non-empty, non-whitespace) value
        $has_meta = function($key) use ($wpdb) {
            return "EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '{$key}' AND TRIM(meta_value) != '')";
        };
        
        switch ($apply_to) {
            case 'missing':
                // Posts where ANY of title/description/keyword is missing or empty
                $query = $wpdb->prepare(
                    "SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_date
                    FROM {$wpdb->posts} p
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND (
                        NOT {$has_meta('_ssf_seo_title')}
                        OR NOT {$has_meta('_ssf_meta_description')}
                        OR NOT {$has_meta('_ssf_focus_keyword')}
                    )
                    ORDER BY p.post_date DESC",
                    ...$post_types
                );
                break;
                
            case 'poor':
                $query = $wpdb->prepare(
                    "SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_date
                    FROM {$wpdb->posts} p
                    LEFT JOIN $table s ON p.ID = s.post_id
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND (s.score < 60 OR s.post_id IS NULL)
                    ORDER BY p.post_date DESC",
                    ...$post_types
                );
                break;
                
            case 'all':
            default:
                $query = $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type, p.post_date
                    FROM {$wpdb->posts} p
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    ORDER BY p.post_date DESC",
                    ...$post_types
                );
                break;
        }
        
        $posts = $wpdb->get_results($query);
        
        // Enrich with current SEO status (use trim to catch whitespace-only values)
        $items = [];
        foreach ($posts as $post) {
            $seo_title = trim(get_post_meta($post->ID, '_ssf_seo_title', true));
            $meta_desc = trim(get_post_meta($post->ID, '_ssf_meta_description', true));
            $focus_kw  = trim(get_post_meta($post->ID, '_ssf_focus_keyword', true));
            $score_row = $wpdb->get_row($wpdb->prepare("SELECT score FROM $table WHERE post_id = %d", $post->ID));
            
            $missing = [];
            if (empty($seo_title))  $missing[] = 'title';
            if (empty($meta_desc))  $missing[] = 'description';
            if (empty($focus_kw))   $missing[] = 'keyword';
            
            $items[] = [
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'type'        => $post->post_type,
                'edit_url'    => admin_url('post.php?action=edit&post=' . $post->ID),
                'has_title'   => !empty($seo_title),
                'has_desc'    => !empty($meta_desc),
                'has_keyword' => !empty($focus_kw),
                'seo_title'   => $seo_title,
                'score'       => $score_row ? intval($score_row->score) : null,
                'missing'     => $missing,
            ];
        }
        
        wp_send_json_success([
            'total' => count($items),
            'posts' => $items,
        ]);
    }
    
    /**
     * Bulk AI fix posts
     */
    public function bulk_ai_fix() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('bulk');
        
        $options = $_POST['options'] ?? [];
        
        $generate_title = !empty($options['generate_title']);
        $generate_desc = !empty($options['generate_desc']);
        $generate_keywords = !empty($options['generate_keywords']);
        $apply_to = sanitize_text_field($options['apply_to'] ?? 'missing');
        
        // Validate OpenAI is configured BEFORE doing any work
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        // Accept explicit post IDs from the frontend (preview selection).
        // Prefer the CSV field (`post_ids_csv`) because PHP's max_input_vars
        // (default 1000) silently truncates large array POSTs — a selection
        // of 1453 posts would lose ~454 IDs on the way in. Fallback to the
        // legacy array field for backward compat.
        if (!empty($_POST['post_ids_csv'])) {
            $csv = (string) wp_unslash($_POST['post_ids_csv']);
            $post_ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv))));
        } else {
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) $_POST['post_ids']) : [];
        }
        $post_ids = array_values(array_unique(array_filter($post_ids, function($id) { return $id > 0; })));
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected.', 'smart-seo-fixer')]);
        }
        
        // For batches of 5+ posts, use background job queue so the UI can show
        // live progress on the Job Queue page and the work is processed in
        // parallel batches of 20 (when Bedrock is active) without browser
        // timeouts. Small batches (<5) run in-request for instant feedback.
        $use_background = !empty($_POST['background']) || count($post_ids) >= 5;
        if ($use_background && class_exists('SSF_Job_Queue')) {
            $payload = [
                'generate_title'    => $generate_title,
                'generate_desc'     => $generate_desc,
                'generate_keywords' => $generate_keywords,
                'apply_to'          => $apply_to,
            ];
            
            $job_id = SSF_Job_Queue::create('bulk_ai_fix', $post_ids, $payload);
            
            if (is_wp_error($job_id)) {
                wp_send_json_error(['message' => $job_id->get_error_message()]);
            }
            
            wp_send_json_success([
                'queued'    => true,
                'job_id'    => $job_id,
                'total'     => count($post_ids),
                'message'   => sprintf(
                    __('Queued %d posts for background processing.', 'smart-seo-fixer'),
                    count($post_ids)
                ),
            ]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';
        $posts = $post_ids;
        
        $analyzer = class_exists('SSF_Analyzer') ? new SSF_Analyzer() : null;
        $log = [];

        // Parallel fast path: one curl_multi call builds title+desc for all
        // posts in this (small) batch. Keeps the in-request path fast for
        // batches <5 so the user sees instant results without waiting for cron.
        $use_parallel = (
            $openai instanceof SSF_Bedrock
            && function_exists('curl_multi_init')
            && method_exists($openai, 'request_multi')
            && method_exists($openai, 'build_title_messages')
            && method_exists($openai, 'build_desc_messages')
        );

        if ($use_parallel) {
            $work = [];
            $jobs = [];
            foreach ($posts as $post_id) {
                $post = get_post($post_id);
                if (!$post) { $log[] = sprintf('⚠️ Post #%d — Not found, skipped', $post_id); continue; }
                // Enrich with excerpt / public meta / image alt so page-builder
                // and location-template CPTs aren't silently skipped.
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                if (str_word_count($enriched) < 10) {
                    $log[] = sprintf('⏭️ %s — Skipped (content too short for AI)', esc_html($post->post_title));
                    continue;
                }
                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);

                if ($generate_keywords) {
                    $current_kw = trim((string) $focus_keyword);
                    if ($apply_to === 'all' || empty($current_kw)) {
                        $kw = SSF_AI::pick_grounded_keyword($enriched, $post->post_title);
                        if (!empty($kw)) {
                            update_post_meta($post_id, '_ssf_focus_keyword', $kw);
                            $focus_keyword = $kw;
                        }
                    }
                }

                $needs_title = false;
                $needs_desc  = false;
                if ($generate_title) {
                    $current_title = trim((string) get_post_meta($post_id, '_ssf_seo_title', true));
                    if ($apply_to === 'all' || empty($current_title)) { $needs_title = true; }
                }
                if ($generate_desc) {
                    $current_desc = trim((string) get_post_meta($post_id, '_ssf_meta_description', true));
                    if ($apply_to === 'all' || empty($current_desc)) { $needs_desc = true; }
                }

                $work[$post_id] = ['post' => $post, 'kw' => $focus_keyword, 'needs_title' => $needs_title, 'needs_desc' => $needs_desc];
                if ($needs_title) {
                    $jobs["t_{$post_id}"] = [
                        'messages'    => $openai->build_title_messages($enriched, $post->post_title, $focus_keyword),
                        'max_tokens'  => 100,
                        'temperature' => 0.3,
                    ];
                }
                if ($needs_desc) {
                    $jobs["d_{$post_id}"] = [
                        'messages'    => $openai->build_desc_messages($enriched, '', $focus_keyword),
                        'max_tokens'  => 200,
                        'temperature' => 0.3,
                    ];
                }
            }

            $responses = !empty($jobs) ? $openai->request_multi($jobs) : [];

            foreach ($work as $post_id => $w) {
                $generated = [];
                if ($w['needs_title']) {
                    $r = $responses["t_{$post_id}"] ?? null;
                    if (!is_wp_error($r) && !empty(trim((string) $r))) {
                        $title = SSF_Validator::enforce_seo_title(trim((string) $r, " \t\n\r\0\x0B\"'"), 60);
                        update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                        $generated[] = 'title';
                    }
                }
                if ($w['needs_desc']) {
                    $r = $responses["d_{$post_id}"] ?? null;
                    if (!is_wp_error($r) && !empty(trim((string) $r))) {
                        $desc = SSF_Validator::enforce_meta_description(trim((string) $r, " \t\n\r\0\x0B\"'"), 160);
                        update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                        $generated[] = 'description';
                    }
                }
                $score = 0;
                if ($analyzer) {
                    $analysis = $analyzer->analyze_post($post_id);
                    $score = $analysis['score'] ?? 0;
                }
                if (!empty($generated)) {
                    $log[] = sprintf('✅ %s — Generated: %s (Score: %d)', esc_html($w['post']->post_title), implode(', ', $generated), $score);
                } else {
                    $log[] = sprintf('⏭️ %s — Skipped (already has SEO data)', esc_html($w['post']->post_title));
                }
            }

            wp_send_json_success([
                'processed' => count($posts),
                'log' => $log,
            ]);
        }
        
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $log[] = sprintf('⚠️ Post #%d - Not found, skipped', $post_id);
                continue;
            }
            
            // Clean content for AI: strip shortcodes + HTML tags
            $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
            if (str_word_count($clean_content) < 10) {
                $log[] = sprintf('⏭️ %s - Skipped (content too short for AI)', esc_html($post->post_title));
                continue;
            }
            
            $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
            $generated = [];
            $errors = [];
            $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
            
            // Generate keywords first (used for better title/desc generation)
            if ($generate_keywords) {
                $current_kw = trim(get_post_meta($post_id, '_ssf_focus_keyword', true));
                if ($apply_to === 'all' || empty($current_kw)) {
                    // Use grounded helper — guarantees keyword exists in the content
                    $kw = SSF_AI::pick_grounded_keyword($enriched, $post->post_title);
                    if (!empty($kw)) {
                        update_post_meta($post_id, '_ssf_focus_keyword', $kw);
                        $focus_keyword = $kw;
                        $generated[] = 'keyword';
                    } else {
                        $errors[] = 'keyword: could not extract a grounded keyword';
                    }
                }
            }
            
            // Generate title
            if ($generate_title) {
                $current_title = trim(get_post_meta($post_id, '_ssf_seo_title', true));
                if ($apply_to === 'all' || empty($current_title)) {
                    $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                    if (!is_wp_error($title) && !empty(trim($title))) {
                        $clean_title = sanitize_text_field(trim($title));
                        if (!empty($clean_title)) {
                            update_post_meta($post_id, '_ssf_seo_title', $clean_title);
                            $generated[] = 'title';
                        }
                    } elseif (is_wp_error($title)) {
                        $errors[] = 'title: ' . $title->get_error_message();
                    }
                }
            }
            
            // Generate description
            if ($generate_desc) {
                $current_desc = trim(get_post_meta($post_id, '_ssf_meta_description', true));
                if ($apply_to === 'all' || empty($current_desc)) {
                    $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                    if (!is_wp_error($desc) && !empty(trim($desc))) {
                        $clean_desc = sanitize_textarea_field(trim($desc));
                        if (!empty($clean_desc)) {
                            update_post_meta($post_id, '_ssf_meta_description', $clean_desc);
                            $generated[] = 'description';
                        }
                    } elseif (is_wp_error($desc)) {
                        $errors[] = 'desc: ' . $desc->get_error_message();
                    }
                }
            }
            
            // Re-analyze
            $score = 0;
            if ($analyzer) {
                $analysis = $analyzer->analyze_post($post_id);
                $score = $analysis['score'] ?? 0;
            }
            
            if (!empty($generated)) {
                $log[] = sprintf('✅ %s — Generated: %s (Score: %d)', 
                    esc_html($post->post_title), 
                    implode(', ', $generated),
                    $score
                );
            } elseif (!empty($errors)) {
                $log[] = sprintf('❌ %s — API error: %s', 
                    esc_html($post->post_title),
                    implode('; ', $errors)
                );
            } else {
                $log[] = sprintf('⏭️ %s — Skipped (already has SEO data)', esc_html($post->post_title));
            }
        }
        
        wp_send_json_success([
            'processed' => count($posts),
            'log' => $log,
        ]);
    }
    
    /**
     * AI fix single post with options
     */
    public function ai_fix_single() {
        $this->verify_nonce();
        if (class_exists('SSF_History')) SSF_History::set_source('ai');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $options = $_POST['options'] ?? [];
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $generate_title = !empty($options['generate_title']);
        $generate_desc = !empty($options['generate_desc']);
        $generate_keywords = !empty($options['generate_keywords']);
        $overwrite = !empty($options['overwrite']);
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        $generated = [];
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

        // Generate keywords first if requested (so it can be used for title/desc)
        if ($generate_keywords) {
            $current_kw = get_post_meta($post_id, '_ssf_focus_keyword', true);
            if ($overwrite || empty($current_kw)) {
                $kw = SSF_AI::pick_grounded_keyword($enriched, $post->post_title);
                if (!empty($kw)) {
                    update_post_meta($post_id, '_ssf_focus_keyword', $kw);
                    $focus_keyword = $kw;
                    $generated[] = 'keyword';
                }
            }
        }
        
        // Generate title
        if ($generate_title) {
            $current_title = get_post_meta($post_id, '_ssf_seo_title', true);
            if ($overwrite || empty($current_title)) {
                $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                if (!is_wp_error($title) && !empty(trim($title))) {
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field(trim($title)));
                    $generated[] = 'title';
                }
            }
        }
        
        // Generate description
        if ($generate_desc) {
            $current_desc = get_post_meta($post_id, '_ssf_meta_description', true);
            if ($overwrite || empty($current_desc)) {
                $desc = $openai->generate_meta_description($enriched, $current_desc, $focus_keyword);
                if (!is_wp_error($desc) && !empty(trim($desc))) {
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field(trim($desc)));
                    $generated[] = 'description';
                }
            }
        }
        
        // Re-analyze
        $analyzer = new SSF_Analyzer();
        $analysis = $analyzer->analyze_post($post_id);
        
        if (empty($generated)) {
            wp_send_json_success([
                'title' => $post->post_title,
                'message' => __('Skipped (already has content)', 'smart-seo-fixer'),
                'score' => $analysis['score'] ?? 0,
            ]);
        }
        
        wp_send_json_success([
            'title' => $post->post_title,
            'message' => sprintf(__('Generated: %s (Score: %d)', 'smart-seo-fixer'), implode(', ', $generated), $analysis['score'] ?? 0),
            'generated' => $generated,
            'score' => $analysis['score'] ?? 0,
        ]);
    }
    
    /**
     * Get post IDs that have a specific SEO issue
     */
    public function get_posts_by_issue() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $issue_text = sanitize_text_field(wp_unslash($_POST['issue'] ?? ''));
        if (empty($issue_text)) {
            wp_send_json_error(['message' => __('No issue specified.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        $issue_like = '%' . $wpdb->esc_like($issue_text) . '%';
        $params = array_merge($post_types, [$issue_like]);
        
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT s.post_id FROM $table s
                 INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 AND s.issues LIKE %s
                 ORDER BY s.score ASC
                 LIMIT 200",
                ...$params
            )
        );
        
        wp_send_json_success([
            'post_ids' => array_map('intval', $post_ids),
            'count' => count($post_ids),
        ]);
    }
    
    /**
     * Toggle local business schema on a specific post/page
     */
    public function toggle_local_schema() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        // Check if local SEO is configured
        $local_seo = new SSF_Local_SEO();
        $settings = $local_seo->get_settings();
        
        if (!$settings['enabled']) {
            wp_send_json_error(['message' => __('Local SEO is not enabled. Please configure it in the Local SEO settings first.', 'smart-seo-fixer')]);
        }
        
        if (empty($settings['business_name']) && empty($settings['address']['street'])) {
            wp_send_json_error(['message' => __('No business information configured. Please fill in your business details in Local SEO settings.', 'smart-seo-fixer')]);
        }
        
        // Toggle the flag
        $current = get_post_meta($post_id, '_ssf_include_local_schema', true);
        $new_value = empty($current) ? 1 : 0;
        update_post_meta($post_id, '_ssf_include_local_schema', $new_value);
        
        if ($new_value) {
            wp_send_json_success([
                'enabled' => true,
                'message' => __('Local Business schema enabled for this page. It will appear in the page source.', 'smart-seo-fixer'),
                'business_name' => $settings['business_name'],
                'business_type' => $settings['business_type'],
            ]);
        } else {
            wp_send_json_success([
                'enabled' => false,
                'message' => __('Local Business schema removed from this page.', 'smart-seo-fixer'),
            ]);
        }
    }
    
    /**
     * Generate content outline using AI
     */
    public function generate_outline() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        $topic = !empty($post->post_title) ? $post->post_title : $focus_keyword;
        
        if (empty($topic)) {
            wp_send_json_error(['message' => __('Please add a title or focus keyword first.', 'smart-seo-fixer')]);
        }
        
        $result = $openai->generate_outline($topic, $focus_keyword);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        if (!is_array($result)) {
            wp_send_json_error(['message' => __('Could not parse outline response. Please try again.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Improve content readability using AI
     */
    public function improve_readability() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        if (empty(trim($post->post_content))) {
            wp_send_json_error(['message' => __('Post has no content to improve.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        // Send trimmed content to avoid token limits
        $content = wp_trim_words(wp_strip_all_tags($post->post_content), 1000);
        $result = $openai->improve_readability($content);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        if (empty(trim($result))) {
            wp_send_json_error(['message' => __('AI returned empty content. Please try again.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success(['improved_content' => trim($result)]);
    }
    
    /**
     * Suggest schema markup using AI
     */
    public function suggest_schema() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $action_type = sanitize_text_field($_POST['schema_action'] ?? 'generate');
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        // Handle save/remove actions
        if ($action_type === 'save') {
            $schema_json = wp_unslash($_POST['schema_json'] ?? '');
            // Validate it's real JSON
            $decoded = json_decode($schema_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => __('Invalid JSON schema.', 'smart-seo-fixer')]);
            }
            update_post_meta($post_id, '_ssf_custom_schema', $schema_json);
            wp_send_json_success(['message' => __('Schema saved and will appear on the frontend automatically.', 'smart-seo-fixer')]);
        }
        
        if ($action_type === 'remove') {
            delete_post_meta($post_id, '_ssf_custom_schema');
            wp_send_json_success(['message' => __('Custom schema removed from this post.', 'smart-seo-fixer')]);
        }
        
        // Generate new schema
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        // Gather real site data so AI doesn't make up URLs
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
        }
        if (empty($logo_url)) {
            $logo_url = get_site_icon_url();
        }
        
        $result = $openai->suggest_schema(
            $post->post_content,
            $post->post_type,
            get_permalink($post_id),
            home_url('/'),
            $post->post_title,
            get_bloginfo('name'),
            $logo_url
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        if (empty(trim($result))) {
            wp_send_json_error(['message' => __('Could not generate schema suggestion.', 'smart-seo-fixer')]);
        }
        
        // Clean JSON response
        $clean = preg_replace('/```json\s*/', '', $result);
        $clean = preg_replace('/```\s*/', '', $clean);
        $clean = trim($clean);
        
        // Validate it's real JSON
        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('AI returned invalid JSON. Please try again.', 'smart-seo-fixer')]);
        }
        
        // Check if AI says no additional schema needed
        if (!empty($decoded['_no_schema'])) {
            wp_send_json_success([
                'no_schema' => true,
                'message' => __('This post already has the right schema types (Article, Breadcrumb, etc.) generated automatically. No additional schema markup is needed.', 'smart-seo-fixer'),
            ]);
        }
        
        // Sanitize: replace any fake/guessed logo URLs with the real one
        if (!empty($logo_url)) {
            $clean_json = json_encode($decoded);
            // Fix common AI hallucinations: /logo.png, example.com, site.com/logo etc.
            $clean_json = preg_replace(
                '#https?://[^"]*?/logo\.(png|jpg|jpeg|svg|webp)#i',
                $logo_url,
                $clean_json
            );
            $clean_json = preg_replace(
                '#https?://example\.com[^"]*#i',
                home_url('/'),
                $clean_json
            );
            $decoded = json_decode($clean_json, true);
            $clean = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        
        // Reject if AI returned a duplicate type we already generate
        $auto_types = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'BreadcrumbList', 'Organization', 'WebSite'];
        if (!empty($decoded['@type']) && in_array($decoded['@type'], $auto_types)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('The AI suggested "%s" schema, but this is already generated automatically by the plugin. No additional schema needed for this content.', 'smart-seo-fixer'),
                    $decoded['@type']
                ),
            ]);
        }
        
        // Check if post already has custom schema
        $existing = get_post_meta($post_id, '_ssf_custom_schema', true);
        
        wp_send_json_success([
            'schema' => $clean,
            'has_existing' => !empty($existing),
        ]);
    }
    
    /**
     * Bulk regenerate custom schemas
     * Processes posts in batches, re-running AI schema generation for each
     */
    public function bulk_regenerate_schemas() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 3);
        $mode = sanitize_text_field($_POST['mode'] ?? 'regenerate'); // 'regenerate' or 'remove'
        
        // Get all posts that have custom schema
        $posts_with_schema = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'meta_key' => '_ssf_custom_schema',
            'meta_compare' => '!=',
            'meta_value' => '',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        $total = count($posts_with_schema);
        
        if ($total === 0) {
            wp_send_json_success([
                'done' => true,
                'processed' => 0,
                'total' => 0,
                'log' => [__('No posts with custom schemas found.', 'smart-seo-fixer')],
            ]);
        }
        
        // Handle remove mode
        if ($mode === 'remove') {
            foreach ($posts_with_schema as $pid) {
                delete_post_meta($pid, '_ssf_custom_schema');
            }
            wp_send_json_success([
                'done' => true,
                'processed' => $total,
                'total' => $total,
                'log' => [sprintf(__('Removed custom schemas from %d posts.', 'smart-seo-fixer'), $total)],
            ]);
        }
        
        // Get batch slice
        $batch = array_slice($posts_with_schema, $offset, $batch_size);
        
        if (empty($batch)) {
            wp_send_json_success([
                'done' => true,
                'processed' => $offset,
                'total' => $total,
                'log' => [__('All custom schemas regenerated!', 'smart-seo-fixer')],
            ]);
        }
        
        $openai = SSF_AI::get();
        
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        // Real site data for the AI
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
        }
        if (empty($logo_url)) {
            $logo_url = get_site_icon_url();
        }
        $site_name = get_bloginfo('name');
        $site_url = home_url('/');
        
        $log = [];
        $processed = 0;
        
        foreach ($batch as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            
            $result = $openai->suggest_schema(
                $post->post_content,
                $post->post_type,
                get_permalink($post_id),
                $site_url,
                $post->post_title,
                $site_name,
                $logo_url
            );
            
            if (is_wp_error($result)) {
                $log[] = '❌ ' . $post->post_title . ': ' . $result->get_error_message();
                $processed++;
                continue;
            }
            
            // Clean response
            $clean = preg_replace('/```json\s*/', '', $result);
            $clean = preg_replace('/```\s*/', '', $clean);
            $clean = trim($clean);
            
            $decoded = json_decode($clean, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $log[] = '❌ ' . $post->post_title . ': ' . __('Invalid JSON response', 'smart-seo-fixer');
                $processed++;
                continue;
            }
            
            // If AI says no schema needed, remove it
            if (!empty($decoded['_no_schema'])) {
                delete_post_meta($post_id, '_ssf_custom_schema');
                $log[] = '🗑️ ' . $post->post_title . ': ' . __('No additional schema needed — removed', 'smart-seo-fixer');
                $processed++;
                continue;
            }
            
            // Reject duplicate types
            $auto_types = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'BreadcrumbList', 'Organization', 'WebSite'];
            if (!empty($decoded['@type']) && in_array($decoded['@type'], $auto_types)) {
                delete_post_meta($post_id, '_ssf_custom_schema');
                $log[] = '🗑️ ' . $post->post_title . ': ' . sprintf(__('Duplicate %s removed', 'smart-seo-fixer'), $decoded['@type']);
                $processed++;
                continue;
            }
            
            // Sanitize fake URLs
            if (!empty($logo_url)) {
                $clean_json = json_encode($decoded);
                $clean_json = preg_replace('#https?://[^"]*?/logo\.(png|jpg|jpeg|svg|webp)#i', $logo_url, $clean_json);
                $clean_json = preg_replace('#https?://example\.com[^"]*#i', $site_url, $clean_json);
                $decoded = json_decode($clean_json, true);
                $clean = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
            
            // Save
            update_post_meta($post_id, '_ssf_custom_schema', $clean);
            $log[] = '✅ ' . $post->post_title . ': ' . ($decoded['@type'] ?? 'Schema') . ' ' . __('regenerated', 'smart-seo-fixer');
            $processed++;
        }
        
        wp_send_json_success([
            'done' => ($offset + $processed) >= $total,
            'processed' => $processed,
            'total' => $total,
            'log' => $log,
        ]);
    }
    
    /**
     * Toggle a single boolean setting safely
     */
    public function toggle_setting() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $key = sanitize_text_field($_POST['setting_key'] ?? '');
        $value = !empty($_POST['setting_value']) ? 1 : 0;
        
        // Only allow toggling known boolean settings
        $allowed = ['enable_schema', 'enable_sitemap', 'auto_meta', 'auto_alt_text', 'disable_other_seo_output'];
        
        if (!in_array($key, $allowed)) {
            wp_send_json_error(['message' => __('Invalid setting.', 'smart-seo-fixer')]);
        }
        
        Smart_SEO_Fixer::update_option($key, $value);
        
        wp_send_json_success(['message' => __('Setting saved.', 'smart-seo-fixer')]);
    }
    
    /**
     * Get list of all posts with custom schemas
     */
    public function get_schema_list() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $posts_with_schema = get_posts([
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'meta_key'       => '_ssf_custom_schema',
            'meta_compare'   => '!=',
            'meta_value'     => '',
            'posts_per_page' => -1,
        ]);
        
        $items = [];
        foreach ($posts_with_schema as $post) {
            $schema_raw = get_post_meta($post->ID, '_ssf_custom_schema', true);
            $decoded = json_decode($schema_raw, true);
            $schema_type = $decoded['@type'] ?? __('Unknown', 'smart-seo-fixer');
            
            $items[] = [
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'post_type'   => get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type,
                'edit_url'    => get_edit_post_link($post->ID, 'raw'),
                'view_url'    => get_permalink($post->ID),
                'schema_type' => $schema_type,
                'schema_json' => $schema_raw,
            ];
        }
        
        wp_send_json_success(['items' => $items, 'total' => count($items)]);
    }
    
    /**
     * Delete a single post's custom schema
     */
    public function delete_single_schema() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        delete_post_meta($post_id, '_ssf_custom_schema');
        
        wp_send_json_success(['message' => __('Schema removed.', 'smart-seo-fixer')]);
    }
    
    /**
     * Regenerate schema for a single post
     */
    public function regenerate_single_schema() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
        }
        if (empty($logo_url)) {
            $logo_url = get_site_icon_url();
        }
        
        $result = $openai->suggest_schema(
            $post->post_content,
            $post->post_type,
            get_permalink($post_id),
            home_url('/'),
            $post->post_title,
            get_bloginfo('name'),
            $logo_url
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // Clean JSON response
        $clean = preg_replace('/```json\s*/', '', $result);
        $clean = preg_replace('/```\s*/', '', $clean);
        $clean = trim($clean);
        
        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('AI returned invalid JSON. Please try again.', 'smart-seo-fixer')]);
        }
        
        // If AI says no schema needed
        if (!empty($decoded['_no_schema'])) {
            delete_post_meta($post_id, '_ssf_custom_schema');
            wp_send_json_success([
                'removed' => true,
                'message' => __('No additional schema needed — custom schema removed.', 'smart-seo-fixer'),
            ]);
            return;
        }
        
        // Reject duplicates
        $auto_types = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'BreadcrumbList', 'Organization', 'WebSite'];
        if (!empty($decoded['@type']) && in_array($decoded['@type'], $auto_types)) {
            delete_post_meta($post_id, '_ssf_custom_schema');
            wp_send_json_success([
                'removed' => true,
                'message' => sprintf(__('"%s" is already auto-generated — custom schema removed.', 'smart-seo-fixer'), $decoded['@type']),
            ]);
            return;
        }
        
        // Sanitize URLs
        if (!empty($logo_url)) {
            $clean_json = json_encode($decoded);
            $clean_json = preg_replace('#https?://[^"]*?/logo\.(png|jpg|jpeg|svg|webp)#i', $logo_url, $clean_json);
            $clean_json = preg_replace('#https?://example\.com[^"]*#i', home_url('/'), $clean_json);
            $decoded = json_decode($clean_json, true);
            $clean = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        
        update_post_meta($post_id, '_ssf_custom_schema', $clean);
        
        $schema_type = $decoded['@type'] ?? __('Schema', 'smart-seo-fixer');
        
        wp_send_json_success([
            'message'     => sprintf(__('%s schema regenerated successfully.', 'smart-seo-fixer'), $schema_type),
            'schema_type' => $schema_type,
            'schema_json' => $clean,
        ]);
    }
    
    /**
     * Generate schema for a post that doesn't have one yet
     */
    public function generate_schema_for_post() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        // Check if already has custom schema
        $existing = get_post_meta($post_id, '_ssf_custom_schema', true);
        if (!empty($existing)) {
            wp_send_json_error(['message' => __('This post already has a custom schema. Use Regenerate instead.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_url($custom_logo_id);
        }
        if (empty($logo_url)) {
            $logo_url = get_site_icon_url();
        }
        
        $result = $openai->suggest_schema(
            $post->post_content,
            $post->post_type,
            get_permalink($post_id),
            home_url('/'),
            $post->post_title,
            get_bloginfo('name'),
            $logo_url
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $clean = preg_replace('/```json\s*/', '', $result);
        $clean = preg_replace('/```\s*/', '', $clean);
        $clean = trim($clean);
        
        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('AI returned invalid JSON. Please try again.', 'smart-seo-fixer')]);
        }
        
        if (!empty($decoded['_no_schema'])) {
            wp_send_json_success([
                'no_schema' => true,
                'message'   => __('AI determined no additional schema is needed for this content.', 'smart-seo-fixer'),
            ]);
            return;
        }
        
        $auto_types = ['Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'BreadcrumbList', 'Organization', 'WebSite'];
        if (!empty($decoded['@type']) && in_array($decoded['@type'], $auto_types)) {
            wp_send_json_success([
                'no_schema' => true,
                'message'   => sprintf(__('"%s" is already auto-generated. No custom schema needed.', 'smart-seo-fixer'), $decoded['@type']),
            ]);
            return;
        }
        
        // Sanitize URLs
        if (!empty($logo_url)) {
            $clean_json = json_encode($decoded);
            $clean_json = preg_replace('#https?://[^"]*?/logo\.(png|jpg|jpeg|svg|webp)#i', $logo_url, $clean_json);
            $clean_json = preg_replace('#https?://example\.com[^"]*#i', home_url('/'), $clean_json);
            $decoded = json_decode($clean_json, true);
            $clean = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        
        update_post_meta($post_id, '_ssf_custom_schema', $clean);
        
        wp_send_json_success([
            'message'     => sprintf(__('%s schema generated and saved.', 'smart-seo-fixer'), $decoded['@type'] ?? 'Custom'),
            'schema_type' => $decoded['@type'] ?? 'Custom',
            'schema_json' => $clean,
            'post_id'     => $post_id,
        ]);
    }
    
    /**
     * Search posts for adding schema (AJAX autocomplete)
     */
    public function search_posts_for_schema() {
        $this->verify_nonce();
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (strlen($search) < 2) {
            wp_send_json_success([]);
        }
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        
        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            's'              => $search,
            'posts_per_page' => 10,
        ]);
        
        $results = [];
        foreach ($posts as $post) {
            $has_schema = !empty(get_post_meta($post->ID, '_ssf_custom_schema', true));
            $results[] = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'post_type'  => get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type,
                'has_schema' => $has_schema,
            ];
        }
        
        wp_send_json_success($results);
    }
    
    // ========================================================
    // Google Search Console AJAX Handlers
    // ========================================================
    
    /**
     * Disconnect from Google Search Console
     */
    public function gsc_refresh_sites() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }

        try {
            $gsc = new SSF_GSC_Client();
            if (!$gsc->is_connected()) {
                wp_send_json_error(['message' => __('Not connected to GSC. Please connect first.', 'smart-seo-fixer')]);
            }

            $sites = $gsc->get_sites();
            if (is_wp_error($sites)) {
                wp_send_json_error(['message' => $sites->get_error_message()]);
            }

            if (empty($sites)) {
                wp_send_json_error(['message' => __('No site properties found in your GSC account. Make sure your site is verified.', 'smart-seo-fixer')]);
            }

            set_transient('ssf_gsc_sites_cache', $sites, DAY_IN_SECONDS);
            wp_send_json_success(['sites' => $sites]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function gsc_disconnect() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        $gsc->disconnect();
        
        wp_send_json_success(['message' => __('Disconnected from Google Search Console.', 'smart-seo-fixer')]);
    }

    /**
     * One-click: create + verify + submit-sitemap a Search Console property
     * for this site.
     */
    public function gsc_auto_setup() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }

        try {
            $gsc = new SSF_GSC_Client();
            if (!$gsc->is_connected()) {
                wp_send_json_error(['message' => __('Please connect to Google first.', 'smart-seo-fixer')]);
            }

            // Can take ~15-30s: token request + homepage fetch + verify call.
            @set_time_limit(60);

            $result = $gsc->auto_setup_property();

            if (!empty($result['success'])) {
                wp_send_json_success($result);
            }
            wp_send_json_error($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'steps'   => [],
            ]);
        }
    }

    /**
     * Disconnect Google Analytics.
     */
    public function ga_disconnect() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_GA_Client')) {
            wp_send_json_error(['message' => __('GA module not available.', 'smart-seo-fixer')]);
        }
        $ga = new SSF_GA_Client();
        $ga->disconnect(true);
        wp_send_json_success(['message' => __('Google Analytics disconnected.', 'smart-seo-fixer')]);
    }

    /**
     * One-click: create GA4 property + web stream for this site, save
     * the measurement ID and install the tracking code.
     */
    public function ga_auto_setup() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_GA_Client')) {
            wp_send_json_error(['message' => __('GA module not available.', 'smart-seo-fixer')]);
        }

        try {
            $ga = new SSF_GA_Client();
            if (!$ga->is_connected()) {
                wp_send_json_error(['message' => __('Please connect Google Analytics first.', 'smart-seo-fixer')]);
            }
            @set_time_limit(60);

            $preferred = isset($_POST['account_id']) ? sanitize_text_field(wp_unslash($_POST['account_id'])) : null;
            $result    = $ga->auto_setup_property($preferred ?: null);

            if (!empty($result['success'])) {
                wp_send_json_success($result);
            }
            wp_send_json_error($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'steps'   => [],
            ]);
        }
    }

    /**
     * Manually save an existing GA4 Measurement ID (for users who already
     * have a property and just want the tracking code installed).
     */
    public function ga_save_measurement_id() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        $mid = isset($_POST['measurement_id']) ? sanitize_text_field(wp_unslash($_POST['measurement_id'])) : '';
        $mid = strtoupper(trim($mid));
        if ($mid !== '' && !preg_match('/^G-[A-Z0-9]{4,}$/', $mid)) {
            wp_send_json_error(['message' => __('Invalid Measurement ID. Expected format: G-XXXXXXXXXX', 'smart-seo-fixer')]);
        }
        update_option(SSF_GA_Client::MEASUREMENT_ID_OPT, $mid, false);
        update_option(SSF_GA_Client::AUTO_TAG_OPTION, !empty($mid), false);
        wp_send_json_success([
            'measurement_id' => $mid,
            'message'        => $mid === ''
                ? __('Measurement ID cleared. Tracking code removed.', 'smart-seo-fixer')
                : __('Measurement ID saved. Tracking code is now live on your site.', 'smart-seo-fixer'),
        ]);
    }

    /**
     * Quick connectivity test — pulls 7-day sessions/users from the Data API.
     */
    public function ga_test_report() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_GA_Client')) {
            wp_send_json_error(['message' => __('GA module not available.', 'smart-seo-fixer')]);
        }
        $ga = new SSF_GA_Client();
        $summary = $ga->get_report_summary(7);
        if (is_wp_error($summary)) {
            wp_send_json_error(['message' => $summary->get_error_message()]);
        }
        wp_send_json_success($summary);
    }

    /**
     * List every GA4 property the connected Google account can access,
     * across all GA accounts it's a member of (not just accounts they own).
     */
    public function ga_list_properties() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_GA_Client')) {
            wp_send_json_error(['message' => __('GA module not available.', 'smart-seo-fixer')]);
        }
        $ga = new SSF_GA_Client();
        if (!$ga->is_connected()) {
            wp_send_json_error(['message' => __('Please connect Google Analytics first.', 'smart-seo-fixer')]);
        }
        @set_time_limit(60);
        $props = $ga->list_all_properties();
        if (is_wp_error($props)) {
            wp_send_json_error(['message' => $props->get_error_message()]);
        }
        wp_send_json_success([
            'properties' => $props,
            'current'    => $ga->get_property_id(),
        ]);
    }

    /**
     * Save a picked existing property (with optional measurement ID for
     * auto-injecting gtag.js). Used when the site's GA4 property belongs to
     * a different owner and we're joining it rather than creating a new one.
     */
    public function ga_select_property() {
        $this->verify_nonce();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_GA_Client')) {
            wp_send_json_error(['message' => __('GA module not available.', 'smart-seo-fixer')]);
        }
        $property = isset($_POST['property'])       ? sanitize_text_field(wp_unslash($_POST['property']))       : '';
        $account  = isset($_POST['account'])        ? sanitize_text_field(wp_unslash($_POST['account']))        : '';
        $mid      = isset($_POST['measurement_id']) ? sanitize_text_field(wp_unslash($_POST['measurement_id'])) : '';
        $stream   = isset($_POST['stream'])         ? sanitize_text_field(wp_unslash($_POST['stream']))         : '';

        $ga = new SSF_GA_Client();
        $result = $ga->select_existing_property($property, $account, $mid, $stream);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success([
            'message'        => __('Property selected. Reports will now use this property.', 'smart-seo-fixer'),
            'property'       => $property,
            'measurement_id' => $mid,
        ]);
    }

    /**
     * Get search performance data
     */
    public function gsc_performance() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        
        if (!$gsc->is_connected()) {
            wp_send_json_error(['message' => __('Not connected to Google Search Console.', 'smart-seo-fixer')]);
        }
        
        $days = intval($_POST['days'] ?? 28);
        $type = sanitize_text_field($_POST['type'] ?? 'overview');
        
        // Use transient caching (1 hour)
        $cache_key = "ssf_gsc_{$type}_{$days}";
        $cached = get_transient($cache_key);
        if ($cached !== false && empty($_POST['refresh'])) {
            wp_send_json_success($cached);
        }
        
        switch ($type) {
            case 'overview':
                $data = $gsc->get_performance_overview($days);
                break;
            case 'queries':
                $data = $gsc->get_top_queries($days, 100);
                break;
            case 'pages':
                $data = $gsc->get_top_pages($days, 100);
                break;
            default:
                $data = new WP_Error('invalid_type', __('Invalid data type.', 'smart-seo-fixer'));
        }
        
        if (is_wp_error($data)) {
            wp_send_json_error(['message' => $data->get_error_message()]);
        }
        
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        
        wp_send_json_success($data);
    }
    
    /**
     * Inspect a URL for index status
     */
    public function gsc_inspect_url() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(['message' => __('URL is required.', 'smart-seo-fixer')]);
        }
        
        $result = $gsc->inspect_url($url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Submit sitemap to Google
     */
    public function gsc_submit_sitemap() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('GSC module not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        $sitemap_url = home_url('/sitemap.xml');
        
        $result = $gsc->submit_sitemap($sitemap_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Sitemap submitted successfully!', 'smart-seo-fixer')]);
    }

    // =========================================================================
    // Broken Links
    // =========================================================================

    public function get_broken_links() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_Broken_Links')) {
            wp_send_json_error(['message' => __('Broken Links module not available.', 'smart-seo-fixer')]);
        }

        $result = SSF_Broken_Links::query([
            'page'      => intval($_POST['page'] ?? 1),
            'per_page'  => 20,
            'link_type' => sanitize_text_field($_POST['link_type'] ?? ''),
            'status'    => sanitize_text_field($_POST['status'] ?? 'active'),
            'search'    => sanitize_text_field($_POST['search'] ?? ''),
        ]);

        // Include fresh stats so the UI cards update without a page reload.
        $result['stats'] = SSF_Broken_Links::get_stats();

        wp_send_json_success($result);
    }

    /**
     * Scan for broken links in small batches. The frontend calls this
     * repeatedly with an increasing offset and shows a progress bar, so a
     * large site never blows the PHP time limit in a single request (which
     * is what made "Scan Now" appear to do nothing).
     */
    public function scan_broken_links() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_Broken_Links')) {
            wp_send_json_error(['message' => __('Broken Links module not available.', 'smart-seo-fixer')]);
        }

        @set_time_limit(120);

        // Ensure the table exists (self-heal if the plugin was updated without reactivation).
        SSF_Broken_Links::create_table();

        global $wpdb;
        $offset     = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = 3; // posts per request — small so each batch stays well under the time limit

        $post_types   = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (empty($post_types)) { $post_types = ['post', 'page']; }
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
            ...$post_types
        ));

        if ($total === 0) {
            wp_send_json_success([
                'done' => true, 'total' => 0, 'processed' => 0,
                'checked' => 0, 'broken' => 0, 'percent' => 100, 'next_offset' => 0,
            ]);
        }

        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ($placeholders)
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            ...array_merge($post_types, [$batch_size, $offset])
        ));

        $checked = 0;
        $broken  = 0;
        foreach ($post_ids as $pid) {
            $r = SSF_Broken_Links::scan_post((int) $pid);
            $checked += $r['checked'];
            $broken  += $r['broken'];
        }

        $processed = $offset + count($post_ids);
        $done = ($processed >= $total) || empty($post_ids);

        wp_send_json_success([
            'done'        => $done,
            'total'       => $total,
            'processed'   => $processed,
            'checked'     => $checked,
            'broken'      => $broken,
            'percent'     => $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 100,
            'next_offset' => $processed,
        ]);
    }

    public function recheck_broken_link() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_Broken_Links')) {
            wp_send_json_error(['message' => __('Broken Links module not available.', 'smart-seo-fixer')]);
        }

        $id     = absint($_POST['id'] ?? 0);
        $result = SSF_Broken_Links::recheck($id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public function dismiss_broken_link() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        SSF_Broken_Links::dismiss(absint($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public function undismiss_broken_link() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        SSF_Broken_Links::undismiss(absint($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    /**
     * Bulk-redirect broken links: replace the broken URL in each post's content
     * with the target URL specified by the user, then dismiss the records.
     */
    public function bulk_redirect_broken_links() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $ids        = array_map('absint', (array) ($_POST['ids'] ?? []));
        $target_url = esc_url_raw(trim($_POST['target_url'] ?? ''));

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No links selected.', 'smart-seo-fixer')]);
        }

        if (empty($target_url) || strpos($target_url, 'http') !== 0) {
            wp_send_json_error(['message' => __('Please provide a valid destination URL.', 'smart-seo-fixer')]);
        }

        global $wpdb;
        $table      = SSF_Broken_Links::table();
        $redirected = 0;
        $failed     = 0;

        foreach ($ids as $id) {
            $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$record) {
                $failed++;
                continue;
            }

            $post = get_post(intval($record->post_id));
            if (!$post) {
                $failed++;
                continue;
            }

            // Replace broken URL in post content (simple string replace — handles plain text and href attributes)
            $new_content = str_replace($record->url, $target_url, $post->post_content);

            if ($new_content !== $post->post_content) {
                wp_update_post([
                    'ID'           => $post->ID,
                    'post_content' => $new_content,
                ]);
            }

            // Dismiss the broken link record (no longer needed)
            SSF_Broken_Links::dismiss($id);
            $redirected++;
        }

        /* translators: 1: number updated, 2: number skipped */
        $msg = sprintf(
            _n('%d link updated.', '%d links updated.', $redirected, 'smart-seo-fixer'),
            $redirected
        );
        if ($failed > 0) {
            $msg .= ' ' . sprintf(
                /* translators: %d: number skipped */
                _n('%d skipped (record not found).', '%d skipped (records not found).', $failed, 'smart-seo-fixer'),
                $failed
            );
        }

        wp_send_json_success(['message' => $msg, 'updated' => $redirected, 'failed' => $failed]);
    }

    /**
     * Bulk-dismiss broken links.
     */
    public function bulk_dismiss_broken_links() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $ids = array_map('absint', (array) ($_POST['ids'] ?? []));

        foreach ($ids as $id) {
            SSF_Broken_Links::dismiss($id);
        }

        wp_send_json_success(['dismissed' => count($ids)]);
    }

    // =========================================================================
    // Canonical URL Fixer
    // =========================================================================

    /**
     * Normalize a canonical URL for storage:
     * - Fixes scheme to match site (http → https or vice-versa)
     * - Fixes www prefix to match site preference
     * - Normalises trailing slash to match WordPress permalink settings
     * - Returns empty string if the normalised value equals the post's own
     *   permalink (makes the stored meta redundant — plugin outputs it by default)
     */
    private function normalize_canonical_for_storage( $url, $post_id = 0 ) {
        if ( empty( $url ) ) return '';

        $site      = home_url('/');
        $site_p    = wp_parse_url( $site );
        $site_scheme = $site_p['scheme'] ?? 'https';
        $site_host   = $site_p['host']  ?? '';

        $p = wp_parse_url( $url );
        if ( empty( $p['host'] ) ) return $url; // relative — leave as-is

        $url_host = $p['host'];

        // 1. Correct scheme
        $p['scheme'] = $site_scheme;

        // 2. Correct www: if site host and url host differ only by www, align them
        $site_www = strpos( $site_host, 'www.' ) === 0;
        $url_www  = strpos( $url_host,  'www.' ) === 0;

        $site_bare = $site_www ? substr( $site_host, 4 ) : $site_host;
        $url_bare  = $url_www  ? substr( $url_host,  4 ) : $url_host;

        if ( $site_bare === $url_bare ) {
            // Same domain, just different www prefix — align to site preference
            $p['host'] = $site_host;
        }

        // 3. Rebuild URL
        $normalized  = $p['scheme'] . '://' . $p['host'];
        $normalized .= $p['path'] ?? '/';
        if ( !empty( $p['query'] )    ) $normalized .= '?' . $p['query'];
        if ( !empty( $p['fragment'] ) ) $normalized .= '#' . $p['fragment'];

        // 4. Normalise trailing slash to match WordPress permalink settings
        $permalink_structure = get_option( 'permalink_structure', '' );
        $wants_slash = !empty( $permalink_structure ) && substr( $permalink_structure, -1 ) === '/';
        // Only touch paths without a file extension
        $path_only = $p['path'] ?? '/';
        if ( !preg_match('/\.\w{2,5}$/', $path_only) ) {
            $normalized = $wants_slash ? trailingslashit( $normalized ) : untrailingslashit( $normalized );
        }

        // 5. If this now equals the post's own permalink, it's a redundant self-canonical — clear it
        if ( $post_id > 0 ) {
            $permalink = untrailingslashit( get_permalink( $post_id ) );
            if ( untrailingslashit( $normalized ) === $permalink ) {
                return '';
            }
        }

        return $normalized;
    }

    /**
     * Scan for canonical mismatches without fixing — returns a count + sample list.
     */
    public function scan_canonical_issues() {
        $this->verify_nonce();

        if ( !current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        global $wpdb;
        $post_types  = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)
                 ORDER BY ID ASC",
                ...$post_types
            )
        );

        $issues  = [];
        $healthy = 0;

        foreach ( $posts as $post ) {
            $stored = get_post_meta( $post->ID, '_ssf_canonical_url', true );

            if ( empty($stored) ) {
                $healthy++;
                continue; // No custom canonical — plugin outputs self-canonical by default. Good.
            }

            $normalized = $this->normalize_canonical_for_storage( $stored, $post->ID );

            if ( $normalized !== $stored ) {
                // Something needs fixing
                $issues[] = [
                    'post_id'    => $post->ID,
                    'title'      => $post->post_title,
                    'url'        => get_permalink( $post->ID ),
                    'stored'     => $stored,
                    'normalized' => $normalized,
                    'action'     => empty($normalized) ? 'clear' : 'update',
                ];
            } else {
                $healthy++;
            }
        }

        wp_send_json_success([
            'issues'  => array_slice($issues, 0, 20), // preview up to 20
            'total'   => count($issues),
            'healthy' => $healthy,
        ]);
    }

    /**
     * Auto-fix all canonical mismatches:
     * - Wrong scheme (http when site is https)
     * - Wrong www prefix vs site preference
     * - Trailing-slash inconsistency
     * - Redundant self-canonicals (custom canonical = own permalink → clear it)
     */
    public function auto_fix_canonicals() {
        $this->verify_nonce();

        if ( !current_user_can('manage_options') ) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        global $wpdb;
        $post_types  = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)
                 ORDER BY ID ASC",
                ...$post_types
            )
        );

        $cleared = 0;
        $updated = 0;
        $skipped = 0;

        foreach ( $posts as $post ) {
            $stored = get_post_meta( $post->ID, '_ssf_canonical_url', true );

            if ( empty($stored) ) {
                $skipped++;
                continue;
            }

            $normalized = $this->normalize_canonical_for_storage( $stored, $post->ID );

            if ( $normalized === $stored ) {
                $skipped++;
                continue; // Already correct or intentional cross-URL canonical
            }

            if ( empty($normalized) ) {
                // Redundant self-canonical — delete it
                delete_post_meta( $post->ID, '_ssf_canonical_url' );
                $cleared++;
            } else {
                // Update to normalised version
                update_post_meta( $post->ID, '_ssf_canonical_url', $normalized );
                $updated++;
            }
        }

        $total_fixed = $cleared + $updated;

        wp_send_json_success([
            'fixed'   => $total_fixed,
            'cleared' => $cleared,
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => $total_fixed > 0
                ? sprintf(
                    /* translators: 1: cleared count, 2: updated count */
                    __('Fixed %1$d canonical issues: %2$d redundant self-canonicals removed, %3$d corrected for scheme/www.', 'smart-seo-fixer'),
                    $total_fixed, $cleared, $updated
                  )
                : __('No canonical issues found — all canonicals are already correct!', 'smart-seo-fixer'),
        ]);
    }
    
    // =========================================================================
    // New Feature Handlers
    // =========================================================================
    
    /**
     * Detect duplicate titles/descriptions across the whole site
     */
    public function detect_duplicates() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $analyzer = new SSF_Analyzer();
        $results = $analyzer->detect_duplicates();
        
        wp_send_json_success($results);
    }
    
    /**
     * Get Core Web Vitals data
     */
    public function get_cwv_data() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_Performance')) {
            wp_send_json_error(['message' => __('Performance module not available.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success([
            'summary'  => SSF_Performance::get_cwv_data()['summary'],
            'by_page'  => SSF_Performance::get_cwv_by_page(10),
        ]);
    }
    
    /**
     * Insert AI-suggested internal links into post content.
     * 
     * Takes link suggestions and applies them by inserting <a> tags into the content.
     */
    public function insert_internal_links() {
        $this->verify_nonce();
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $links = isset($_POST['links']) ? $_POST['links'] : [];
        
        if (!$post_id || empty($links)) {
            wp_send_json_error(['message' => __('Missing post ID or links.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        $content = $post->post_content;
        $inserted = 0;
        
        // Set history source
        if (class_exists('SSF_History')) {
            SSF_History::set_source('ai');
        }
        
        foreach ($links as $link) {
            $anchor = sanitize_text_field($link['anchor'] ?? '');
            $url    = esc_url($link['url'] ?? '');
            
            if (empty($anchor) || empty($url)) {
                continue;
            }
            
            // Only insert if the anchor text exists as plain text (not already linked)
            // Use word boundary matching to avoid partial word matches
            $pattern = '/(?<!["\'>])(' . preg_quote($anchor, '/') . ')(?![^<]*<\/a>)/i';
            
            if (preg_match($pattern, $content)) {
                // Replace only the first occurrence
                $replacement = '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>';
                $content = preg_replace($pattern, $replacement, $content, 1);
                $inserted++;
            }
        }
        
        if ($inserted > 0) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
            
            // Re-analyze to update score
            if (class_exists('SSF_Analyzer')) {
                (new SSF_Analyzer())->analyze_post($post_id);
            }
        }
        
        wp_send_json_success([
            'inserted' => $inserted,
            'total'    => count($links),
            'message'  => sprintf(
                __('Inserted %d of %d internal links.', 'smart-seo-fixer'),
                $inserted, count($links)
            ),
        ]);
    }
    
    /**
     * Apply selected items from a bulk fix preview.
     * 
     * The frontend sends an array of approved items (post_id + fields to apply).
     * Rejected items are simply not included. This allows per-item approve/reject.
     */
    public function apply_bulk_preview() {
        $this->verify_nonce();
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        
        if (empty($items) || !is_array($items)) {
            wp_send_json_error(['message' => __('No items to apply.', 'smart-seo-fixer')]);
        }
        
        if (class_exists('SSF_History')) {
            SSF_History::set_source('bulk');
        }
        
        $applied = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($items as $item) {
            $post_id = intval($item['post_id'] ?? 0);
            if (!$post_id || !get_post($post_id)) {
                $skipped++;
                continue;
            }
            
            // Per-post capability check
            if (!current_user_can('edit_post', $post_id)) {
                $skipped++;
                continue;
            }
            
            $changed = false;
            
            if (!empty($item['title'])) {
                update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($item['title']));
                $changed = true;
            }
            
            if (!empty($item['description'])) {
                update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($item['description']));
                $changed = true;
            }
            
            if (!empty($item['keyword'])) {
                update_post_meta($post_id, '_ssf_focus_keyword', sanitize_text_field($item['keyword']));
                $changed = true;
            }
            
            if ($changed) {
                $applied++;
                
                // Re-analyze
                if (class_exists('SSF_Analyzer')) {
                    (new SSF_Analyzer())->analyze_post($post_id);
                }
            } else {
                $skipped++;
            }
        }
        
        wp_send_json_success([
            'applied' => $applied,
            'skipped' => $skipped,
            'total'   => count($items),
            'message' => sprintf(
                __('Applied changes to %d posts, skipped %d.', 'smart-seo-fixer'),
                $applied, $skipped
            ),
        ]);
    }
    
    /**
     * Audit images in a post for SEO issues
     */
    public function audit_images() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Image_SEO')) {
            wp_send_json_error(['message' => __('Image SEO module not available.', 'smart-seo-fixer')]);
        }
        
        $issues = SSF_Image_SEO::audit_post_images($post_id);
        
        wp_send_json_success([
            'post_id' => $post_id,
            'issues'  => $issues,
            'total'   => count($issues),
        ]);
    }
    
    /**
     * Get onboarding checklist status
     */
    public function get_onboarding_status() {
        $this->verify_nonce();
        
        if (get_option('ssf_onboarding_dismissed', false)) {
            wp_send_json_success(['dismissed' => true, 'items' => []]);
            return;
        }
        
        $openai = class_exists('SSF_AI') ? SSF_AI::get() : null;
        
        $items = [
            [
                'id'       => 'api_configured',
                'label'    => __('Configure AI provider (AWS Bedrock)', 'smart-seo-fixer'),
                'complete' => $openai && $openai->is_configured(),
                'link'     => admin_url('admin.php?page=smart-seo-fixer-settings'),
            ],
            [
                'id'       => 'first_analysis',
                'label'    => __('Analyze your first post', 'smart-seo-fixer'),
                'complete' => (bool) get_option('ssf_first_analysis_done', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer'),
            ],
            [
                'id'       => 'bulk_analyze',
                'label'    => __('Run bulk analysis on all posts', 'smart-seo-fixer'),
                'complete' => (bool) get_option('ssf_bulk_analyze_done', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer'),
            ],
            [
                'id'       => 'schema_enabled',
                'label'    => __('Enable schema markup', 'smart-seo-fixer'),
                'complete' => (bool) Smart_SEO_Fixer::get_option('enable_schema', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer-settings'),
            ],
            [
                'id'       => 'sitemap_enabled',
                'label'    => __('Enable XML sitemap', 'smart-seo-fixer'),
                'complete' => (bool) Smart_SEO_Fixer::get_option('enable_sitemap', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer-settings'),
            ],
            [
                'id'       => 'auto_meta',
                'label'    => __('Enable auto meta generation on publish', 'smart-seo-fixer'),
                'complete' => (bool) Smart_SEO_Fixer::get_option('auto_meta', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer-settings'),
            ],
            [
                'id'       => 'redirects_reviewed',
                'label'    => __('Check your redirects & 404 monitor', 'smart-seo-fixer'),
                'complete' => (bool) get_option('ssf_redirects_reviewed', false),
                'link'     => admin_url('admin.php?page=smart-seo-fixer-redirects'),
            ],
        ];
        
        $completed = count(array_filter($items, function($item) { return $item['complete']; }));
        
        wp_send_json_success([
            'dismissed'  => false,
            'items'      => $items,
            'completed'  => $completed,
            'total'      => count($items),
            'percentage' => round(($completed / count($items)) * 100),
        ]);
    }
    
    /**
     * Dismiss onboarding checklist
     */
    public function dismiss_onboarding() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        update_option('ssf_onboarding_dismissed', true);
        
        wp_send_json_success(['message' => __('Onboarding dismissed.', 'smart-seo-fixer')]);
    }
    
    // =========================================================================
    // 404 Monitor AJAX Handlers
    // =========================================================================
    
    /**
     * Get 404 logs with pagination and filters
     */
    public function get_404_logs() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_404_Monitor')) {
            wp_send_json_error(['message' => __('404 Monitor is not available.', 'smart-seo-fixer')]);
        }
        
        $result = SSF_404_Monitor::query([
            'page'     => intval($_POST['page'] ?? 1),
            'per_page' => 20,
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'status'   => sanitize_text_field($_POST['status'] ?? 'active'),
        ]);
        
        $items = [];
        foreach ($result['items'] as $row) {
            $items[] = [
                'id'            => intval($row->id),
                'url'           => $row->url,
                'hit_count'     => intval($row->hit_count),
                'referrer'      => $row->referrer,
                'last_hit'      => $row->last_hit,
                'redirected_to' => $row->redirected_to,
                'dismissed'     => intval($row->dismissed),
            ];
        }
        
        wp_send_json_success([
            'items' => $items,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page'  => $result['page'],
        ]);
    }
    
    /**
     * Dismiss a 404 entry
     */
    public function dismiss_404() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_404_Monitor')) {
            wp_send_json_error(['message' => __('404 Monitor is not available.', 'smart-seo-fixer')]);
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid ID.', 'smart-seo-fixer')]);
        }
        
        SSF_404_Monitor::dismiss($id);
        
        wp_send_json_success(['message' => __('Entry dismissed.', 'smart-seo-fixer')]);
    }
    
    /**
     * Create redirect from a 404 entry
     */
    public function create_404_redirect() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_404_Monitor')) {
            wp_send_json_error(['message' => __('404 Monitor is not available.', 'smart-seo-fixer')]);
        }
        
        $id = intval($_POST['id'] ?? 0);
        $redirect_to = esc_url_raw($_POST['redirect_to'] ?? '');
        
        if (!$id || empty($redirect_to)) {
            wp_send_json_error(['message' => __('Both ID and redirect URL are required.', 'smart-seo-fixer')]);
        }
        
        $result = SSF_404_Monitor::create_redirect($id, $redirect_to);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Redirect created.', 'smart-seo-fixer')]);
    }
    
    /**
     * Clear all 404 logs
     */
    public function clear_404_logs() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_404_Monitor')) {
            wp_send_json_error(['message' => __('404 Monitor is not available.', 'smart-seo-fixer')]);
        }
        
        SSF_404_Monitor::clear_all();
        
        wp_send_json_success(['message' => __('All 404 logs cleared.', 'smart-seo-fixer')]);
    }
    
    // =========================================================================
    // GSC: Pages Not Indexed
    // =========================================================================
    
    /**
     * Scan for pages not appearing in Google Search
     */
    public function gsc_not_indexed() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('Google Search Console is not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        if (!$gsc->is_connected()) {
            wp_send_json_error(['message' => __('Google Search Console is not connected. Go to Settings to connect.', 'smart-seo-fixer')]);
        }
        
        // Get all published posts/pages
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $published = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        ]);
        
        if (empty($published)) {
            wp_send_json_success([
                'total_published' => 0,
                'total_in_gsc'    => 0,
                'count'           => 0,
                'not_indexed'     => [],
            ]);
        }
        
        // Build URL list for all published posts
        $published_urls = [];
        foreach ($published as $post_id) {
            $url = get_permalink($post_id);
            if ($url) {
                $published_urls[$post_id] = $url;
            }
        }
        
        // Get all pages that have appeared in GSC (last 90 days)
        $gsc_result = $gsc->get_search_analytics([
            'startDate'  => date('Y-m-d', strtotime('-90 days')),
            'endDate'    => date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['page'],
            'rowLimit'   => 5000,
        ]);
        
        $gsc_urls = [];
        if (!is_wp_error($gsc_result) && !empty($gsc_result['rows'])) {
            foreach ($gsc_result['rows'] as $row) {
                $gsc_urls[] = rtrim($row['keys'][0] ?? '', '/');
            }
        }
        
        // Compare: find published pages NOT in GSC
        $not_indexed = [];
        foreach ($published_urls as $post_id => $url) {
            $url_normalized = rtrim($url, '/');
            $found = false;
            foreach ($gsc_urls as $gsc_url) {
                if (strcasecmp($url_normalized, rtrim($gsc_url, '/')) === 0) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $post = get_post($post_id);
                $issues = [];
                $is_noindex = false;

                // If the post is marked noindex, Google ignoring it is EXPECTED
                // behaviour, not an issue. Skip it entirely so it doesn't
                // clutter the "needs fixing" list in reports.
                $noindex = get_post_meta($post_id, '_ssf_noindex', true);
                if ($noindex) {
                    $is_noindex = true;
                    continue; // don't add to $not_indexed — user intentionally excluded.
                }

                // Check for common SEO issues
                $title = get_post_meta($post_id, '_ssf_seo_title', true);
                $desc  = get_post_meta($post_id, '_ssf_meta_description', true);
                
                if (empty($title) && empty($post->post_title)) {
                    $issues[] = 'missing_title';
                }
                if (empty($desc)) {
                    $issues[] = 'missing_description';
                }
                // Check internal links
                $content = $post->post_content ?? '';
                if (substr_count($content, '<a ') < 1) {
                    $issues[] = 'no_internal_links';
                }
                
                $not_indexed[] = [
                    'id'          => $post_id,
                    'title'       => get_the_title($post_id),
                    'url'         => $url,
                    'post_type'   => $post->post_type,
                    'issues'      => $issues,
                    'issue_count' => count($issues),
                    'status'      => count($issues) > 0 ? 'issues' : 'not_found',
                ];
            }
        }
        
        // Sort by issue count descending
        usort($not_indexed, function($a, $b) {
            return $b['issue_count'] - $a['issue_count'];
        });
        
        wp_send_json_success([
            'total_published' => count($published_urls),
            'total_in_gsc'    => count($gsc_urls),
            'count'           => count($not_indexed),
            'not_indexed'     => $not_indexed,
        ]);
    }
    
    // =========================================================================
    // Keyword Tracker AJAX Handlers
    // =========================================================================
    
    /**
     * Get tracked keywords with pagination
     */
    public function get_tracked_keywords() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_Keyword_Tracker')) {
            wp_send_json_error(['message' => __('Keyword Tracker is not available.', 'smart-seo-fixer')]);
        }
        
        $result = SSF_Keyword_Tracker::get_keywords([
            'page'     => intval($_POST['page'] ?? 1),
            'per_page' => 20,
            'days'     => intval($_POST['days'] ?? 30),
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
        ]);
        
        wp_send_json_success([
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page'  => $result['page'],
        ]);
    }
    
    /**
     * Get keyword position history for a specific keyword
     */
    public function get_keyword_history() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_Keyword_Tracker')) {
            wp_send_json_error(['message' => __('Keyword Tracker is not available.', 'smart-seo-fixer')]);
        }
        
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $days    = intval($_POST['days'] ?? 30);
        
        if (empty($keyword)) {
            wp_send_json_error(['message' => __('No keyword specified.', 'smart-seo-fixer')]);
        }
        
        $history = SSF_Keyword_Tracker::get_keyword_history($keyword, $days);
        
        wp_send_json_success($history);
    }
    
    /**
     * Manually fetch keywords from GSC now
     */
    public function fetch_keywords_now() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Keyword_Tracker')) {
            wp_send_json_error(['message' => __('Keyword Tracker is not available.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_GSC_Client')) {
            wp_send_json_error(['message' => __('Google Search Console client is not available.', 'smart-seo-fixer')]);
        }
        
        $gsc = new SSF_GSC_Client();
        if (!$gsc->is_connected()) {
            wp_send_json_error(['message' => __('Google Search Console is not connected. Go to Settings to connect first.', 'smart-seo-fixer')]);
        }
        
        // Run the tracking cron manually
        SSF_Keyword_Tracker::cron_track();
        
        $stats = SSF_Keyword_Tracker::get_stats();
        
        wp_send_json_success([
            'message'        => sprintf(__('Done! %d keywords tracked.', 'smart-seo-fixer'), $stats['total_keywords']),
            'total_keywords' => $stats['total_keywords'],
        ]);
    }
    
    // =========================================================================
    // Debug Log AJAX Handlers
    // =========================================================================
    
    /**
     * Get plugin logs with pagination and filtering
     */
    public function get_logs() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Logger')) {
            wp_send_json_error(['message' => __('Logger is not available.', 'smart-seo-fixer')]);
        }
        
        $level    = sanitize_text_field($_POST['level'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        $result = SSF_Logger::query([
            'page'     => intval($_POST['page'] ?? 1),
            'per_page' => intval($_POST['per_page'] ?? 50),
            'level'    => !empty($level) ? $level : null,
            'category' => !empty($category) ? $category : null,
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
        ]);
        
        $counts = SSF_Logger::get_counts();
        
        wp_send_json_success([
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'total_pages' => $result['total_pages'],
            'counts'      => $counts,
        ]);
    }
    
    /**
     * Clear all debug logs
     */
    public function clear_logs() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Logger')) {
            wp_send_json_error(['message' => __('Logger is not available.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . SSF_Logger::table());
        
        wp_send_json_success(['message' => __('All logs cleared.', 'smart-seo-fixer')]);
    }
    
    // =========================================================================
    // Change History AJAX Handlers
    // =========================================================================
    
    /**
     * Get change history
     */
    public function get_history() {
        $this->verify_nonce();
        
        if (!class_exists('SSF_History')) {
            wp_send_json_error(['message' => __('History tracker is not available.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_history';
        
        $page     = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 50);
        $offset   = ($page - 1) * $per_page;
        $search   = sanitize_text_field($_POST['search'] ?? '');
        
        $where = '1=1';
        $params = [];
        
        if (!empty($search)) {
            $where .= ' AND (meta_key LIKE %s OR old_value LIKE %s OR new_value LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        $total = !empty($params) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$params)) : $wpdb->get_var($total_sql);
        
        $sql = "SELECT h.*, p.post_title FROM $table h LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID WHERE $where ORDER BY h.changed_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));
        
        wp_send_json_success([
            'items'       => $items ?: [],
            'total'       => intval($total),
            'page'        => $page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }
    
    /**
     * Undo a change from history
     */
    public function undo_change() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $history_id = intval($_POST['history_id'] ?? 0);
        if (!$history_id) {
            wp_send_json_error(['message' => __('Invalid history ID.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_history';
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id));
        
        if (!$record) {
            wp_send_json_error(['message' => __('History record not found.', 'smart-seo-fixer')]);
        }
        
        // Restore old value
        update_post_meta($record->post_id, $record->meta_key, $record->old_value);
        
        wp_send_json_success(['message' => __('Change undone successfully.', 'smart-seo-fixer')]);
    }
    
    /**
     * Get history stats
     */
    public function get_history_stats() {
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_history';
        
        $total   = intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
        $today   = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(changed_at) = %s", current_time('Y-m-d'))));
        $week    = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE changed_at >= %s", date('Y-m-d', strtotime('-7 days')))));
        
        wp_send_json_success([
            'total' => $total,
            'today' => $today,
            'week'  => $week,
        ]);
    }
    
    // =========================================================================
    // Job Queue AJAX Handlers
    // =========================================================================
    
    /**
     * Get job queue status summary
     */
    public function get_job_status() {
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_jobs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            wp_send_json_success(['pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0]);
        }
        
        $counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM $table GROUP BY status", OBJECT_K);
        
        wp_send_json_success([
            'pending'   => isset($counts['pending'])   ? intval($counts['pending']->cnt)   : 0,
            'running'   => isset($counts['running'])   ? intval($counts['running']->cnt)   : 0,
            'completed' => isset($counts['completed']) ? intval($counts['completed']->cnt) : 0,
            'failed'    => isset($counts['failed'])    ? intval($counts['failed']->cnt)    : 0,
        ]);
    }

    /**
     * Get a single job by ID (for live progress polling).
     */
    public function get_job() {
        $this->verify_nonce();
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            wp_send_json_error(['message' => __('Missing job_id.', 'smart-seo-fixer')]);
        }
        if (!class_exists('SSF_Job_Queue')) {
            wp_send_json_error(['message' => 'Job queue not available.']);
        }
        $job = SSF_Job_Queue::get($job_id);
        if (!$job) {
            wp_send_json_error(['message' => __('Job not found.', 'smart-seo-fixer')]);
        }
        // Column names are processed_items/failed_items/error_message — the
        // older *_count names this endpoint used first never existed on the
        // row, so progress polling always reported 0/N. Aligning field names
        // with the schema (see class-job-queue.php dbDelta).
        $total     = intval($job->total_items ?? (is_array($job->items) ? count($job->items) : 0));
        $processed = intval($job->processed_items ?? 0);
        $failed    = intval($job->failed_items ?? 0);
        $percent   = $total > 0 ? min(100, round(($processed / $total) * 100)) : 0;

        // Opportunistically nudge the pipeline forward while the user is
        // watching. If the job is still pending/processing, kick a loopback
        // tick from the polling request itself — on hosts where the initial
        // non-blocking loopback is dropped (some shared hosting, caching
        // layers, or when WP is behind a reverse proxy), this keeps the
        // batches moving off the back of normal admin traffic instead of
        // stalling until WP-Cron fires.
        if (in_array($job->status, ['pending', 'processing'], true)
            && method_exists('SSF_Job_Queue', 'spawn_next_tick_public')) {
            SSF_Job_Queue::spawn_next_tick_public();
        }

        // Summarize results so the UI can show real outcomes instead of just a
        // raw "999/999 processed". Previously bulk jobs on page-builder posts
        // reported 100% success while silently skipping everything as
        // "content too short" — this surfaces that so the user sees a
        // "generated X / skipped Y" breakdown.
        $summary = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'reasons' => []];
        if (!empty($job->results) && is_array($job->results)) {
            foreach ($job->results as $r) {
                $status  = $r['status']  ?? '';
                $message = (string) ($r['message'] ?? '');
                if ($status === 'failed') {
                    $summary['failed']++;
                } elseif (stripos($message, 'skipped') === 0 || stripos($message, 'skipped') !== false) {
                    $summary['skipped']++;
                    $reason = trim(preg_replace('/^Skipped\s*\(?/i', '', rtrim($message, ')')));
                    if ($reason !== '') {
                        $summary['reasons'][$reason] = ($summary['reasons'][$reason] ?? 0) + 1;
                    }
                } else {
                    $summary['generated']++;
                }
            }
        }

        wp_send_json_success([
            'id'          => intval($job->id),
            'job_type'    => $job->job_type,
            'status'      => $job->status,
            'total'       => $total,
            'processed'   => $processed,
            'failed'      => $failed,
            'percent'     => $percent,
            'summary'     => $summary,
            'created_at'  => $job->created_at,
            'started_at'  => $job->started_at ?? null,
            'completed_at'=> $job->completed_at ?? null,
            'error'       => $job->error_message ?? '',
        ]);
    }

    /**
     * Get jobs list
     */
    public function get_jobs() {
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_jobs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            wp_send_json_success(['items' => [], 'jobs' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
        }
        
        $page     = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;
        $status   = sanitize_text_field($_POST['status'] ?? '');
        
        $where = '1=1';
        $params = [];
        
        if (!empty($status)) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }
        
        $total_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        $total = !empty($params) ? $wpdb->get_var($wpdb->prepare($total_sql, ...$params)) : $wpdb->get_var($total_sql);
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$query_params)) ?: [];

        // Compute progress % for the JS renderer, which expects a `progress`
        // field on each job. Without this, the Recent Jobs table renders but
        // all bars read 0% regardless of actual processed_items.
        foreach ($items as $row) {
            $total_items     = max(1, intval($row->total_items));
            $processed_items = intval($row->processed_items);
            $row->progress   = min(100, round(($processed_items / $total_items) * 100));
        }

        wp_send_json_success([
            // Legacy key kept for any callers still using it.
            'items'       => $items,
            // The job-queue.php view reads `jobs`; without this key the table
            // always appeared empty even when jobs existed in the DB.
            'jobs'        => $items,
            'total'       => intval($total),
            'page'        => $page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }
    
    /**
     * Cancel a queued job
     */
    public function cancel_job() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_jobs';
        $wpdb->update($table, ['status' => 'cancelled'], ['id' => $job_id]);
        
        wp_send_json_success(['message' => __('Job cancelled.', 'smart-seo-fixer')]);
    }
    
    /**
     * Retry a failed job
     */
    public function retry_job() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'smart-seo-fixer')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_jobs';
        $wpdb->update($table, ['status' => 'pending', 'attempts' => 0, 'error' => ''], ['id' => $job_id]);
        
        wp_send_json_success(['message' => __('Job requeued.', 'smart-seo-fixer')]);
    }
    
    // =========================================================================
    // Generic Background Job Dispatch & Polling
    // =========================================================================
    
    /**
     * Dispatch a background job via SSF_Job_Queue
     * 
     * Accepts: job_type, items (array of IDs), payload (optional config)
     * Returns: job_id on success for polling
     */
    public function dispatch_job() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Job_Queue')) {
            wp_send_json_error(['message' => __('Job queue not available.', 'smart-seo-fixer')]);
        }
        
        $allowed_types = [
            'bulk_ai_fix', 'bulk_schema', 'orphan_fix_batch',
            'not_indexed_ai_fix', 'bulk_404_redirect',
        ];
        
        $job_type = sanitize_key($_POST['job_type'] ?? '');
        if (!in_array($job_type, $allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid job type.', 'smart-seo-fixer')]);
        }
        
        $items = isset($_POST['items']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $_POST['items']))) : [];
        if (empty($items)) {
            wp_send_json_error(['message' => __('No items to process.', 'smart-seo-fixer')]);
        }
        
        $payload = [];
        if (isset($_POST['payload']) && is_array($_POST['payload'])) {
            foreach ($_POST['payload'] as $k => $v) {
                $payload[sanitize_key($k)] = sanitize_text_field($v);
            }
        }
        
        $job_id = SSF_Job_Queue::create($job_type, $items, $payload);
        
        if (is_wp_error($job_id)) {
            wp_send_json_error(['message' => $job_id->get_error_message()]);
        }
        
        wp_send_json_success([
            'job_id'  => $job_id,
            'total'   => count($items),
            'message' => sprintf(
                __('Job #%d created with %d items. Processing in background.', 'smart-seo-fixer'),
                $job_id, count($items)
            ),
        ]);
    }
    
    /**
     * Poll a specific job's progress
     * 
     * Accepts: job_id
     * Returns: status, processed, total, failed, percent, results (if completed)
     */
    public function poll_job() {
        $this->verify_nonce();
        
        $job_id = intval($_POST['job_id'] ?? 0);
        if (!$job_id) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Job_Queue')) {
            wp_send_json_error(['message' => __('Job queue not available.', 'smart-seo-fixer')]);
        }
        
        $job = SSF_Job_Queue::get($job_id);
        if (!$job) {
            wp_send_json_error(['message' => __('Job not found.', 'smart-seo-fixer')]);
        }
        
        // Actively process the queue if job is still pending/processing
        // This ensures progress even if WP Cron is delayed or disabled
        if (in_array($job->status, ['pending', 'processing'])) {
            SSF_Job_Queue::process_queue();
            // Re-fetch after processing
            $job = SSF_Job_Queue::get($job_id);
        }
        
        $total     = intval($job->total_items);
        $processed = intval($job->processed_items);
        $failed    = intval($job->failed_items);
        $percent   = $total > 0 ? round(($processed / $total) * 100) : 0;
        
        $data = [
            'job_id'    => intval($job->id),
            'status'    => $job->status,
            'total'     => $total,
            'processed' => $processed,
            'failed'    => $failed,
            'percent'   => $percent,
        ];
        
        if (in_array($job->status, ['completed', 'failed', 'cancelled'])) {
            $data['results'] = $job->results;
            $data['error_message'] = $job->error_message;
        }
        
        wp_send_json_success($data);
    }
    
    // =========================================================================
    // Miscellaneous Missing Handlers
    // =========================================================================
    
    /**
     * Save robots.txt content
     */
    public function save_robots() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        // JS sends 'content' (not 'robots_content') and 'enabled'
        $content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));
        $enabled = !empty($_POST['enabled']);
        
        if (class_exists('SSF_Robots_Editor')) {
            SSF_Robots_Editor::save_content($content);
            SSF_Robots_Editor::set_enabled($enabled);
        } else {
            Smart_SEO_Fixer::update_option('robots_txt', $content);
        }
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info('robots.txt content updated', 'general');
        }
        
        wp_send_json_success(['message' => __('robots.txt saved.', 'smart-seo-fixer')]);
    }
    
    /**
     * Analyze readability of a post
     */
    public function analyze_readability() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
        }
        
        if (class_exists('SSF_Readability')) {
            $readability = new SSF_Readability();
            $result = $readability->analyze($post->post_content);
            wp_send_json_success($result);
        }
        
        wp_send_json_error(['message' => __('Readability analyzer is not available.', 'smart-seo-fixer')]);
    }
    
    /**
     * Save social preview data
     */
    public function save_social_data() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        // URL fields need esc_url_raw; text fields use sanitize_text_field
        $url_fields  = ['og_image', 'twitter_image'];
        $text_fields = ['og_title', 'og_description', 'twitter_title', 'twitter_description'];
        
        foreach ($url_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_ssf_' . $field, esc_url_raw(wp_unslash($_POST[$field])));
            }
        }
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_ssf_' . $field, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }
        
        wp_send_json_success(['message' => __('Social data saved.', 'smart-seo-fixer')]);
    }
    
    /**
     * Get social preview data for a post
     */
    public function get_social_data() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        $data = [];
        $fields = ['og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description', 'twitter_image'];
        foreach ($fields as $field) {
            $data[$field] = get_post_meta($post_id, '_ssf_' . $field, true);
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get content suggestions for a post
     */
    public function content_suggestions() {
        $this->verify_nonce();
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Content_Suggestions')) {
            wp_send_json_error(['message' => __('Content Suggestions module is not available.', 'smart-seo-fixer')]);
        }

        $mode = ($_POST['mode'] ?? 'rules') === 'ai' ? 'ai' : 'rules';
        $result = SSF_Content_Suggestions::generate($post_id, $mode);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }

    /**
     * Apply a single content suggestion: generate the fix with AI and save it
     * directly to the post (SEO title meta, meta description meta, or an
     * appended body-content section — SSF_Content_Suggestions::apply()
     * decides which based on what the suggestion is actually about).
     *
     * Runs inline since the user is waiting on the result. Requires
     * edit_post on this specific post, not just the blanket edit_posts check
     * verify_nonce() already does — this action writes to the post.
     */
    public function apply_content_suggestion() {
        $this->verify_nonce();

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (!class_exists('SSF_Content_Suggestions')) {
            wp_send_json_error(['message' => __('Content Suggestions module is not available.', 'smart-seo-fixer')]);
        }

        $category    = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $s_title     = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        if ($s_title === '') {
            wp_send_json_error(['message' => __('Missing suggestion.', 'smart-seo-fixer')]);
        }

        $result = SSF_Content_Suggestions::apply($post_id, $category, $s_title, $description);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * Run WP coding standards audit
     */
    public function wp_standards_audit() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_WP_Standards')) {
            wp_send_json_error(['message' => __('WP Standards module is not available.', 'smart-seo-fixer')]);
        }
        
        $auditor = new SSF_WP_Standards();
        $result = $auditor->audit();
        
        wp_send_json_success($result);
    }
    
    /**
     * Get performance profiling data
     */
    public function performance_data() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Performance')) {
            wp_send_json_error(['message' => __('Performance module is not available.', 'smart-seo-fixer')]);
        }
        
        $perf = new SSF_Performance();
        $data = $perf->get_data();
        
        wp_send_json_success($data);
    }
    
    /**
     * Clear performance data
     */
    public function performance_clear() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Performance')) {
            wp_send_json_error(['message' => __('Performance module is not available.', 'smart-seo-fixer')]);
        }
        
        $perf = new SSF_Performance();
        $perf->clear();
        
        wp_send_json_success(['message' => __('Performance data cleared.', 'smart-seo-fixer')]);
    }
    
    /**
     * Generate client SEO report (admin only)
     */
    public function generate_client_report() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied. Admin access required.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_Client_Report')) {
            wp_send_json_error(['message' => __('Client Report module is not available.', 'smart-seo-fixer')]);
        }
        
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date   = sanitize_text_field($_POST['end_date'] ?? '');
        $mode       = sanitize_key($_POST['mode'] ?? 'positive');
        if (!in_array($mode, ['positive', 'full'], true)) {
            $mode = 'positive';
        }
        $sections   = isset($_POST['sections']) && is_array($_POST['sections'])
            ? array_map('sanitize_key', $_POST['sections'])
            : [];
        
        $data = SSF_Client_Report::generate($date_range, $start_date, $end_date, $sections, $mode);
        
        wp_send_json_success($data);
    }

    /**
     * Fetch and cache a report template from a URL (admin only)
     */
    public function fetch_report_template() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $url = esc_url_raw($_POST['template_url'] ?? '');
        if (empty($url)) {
            wp_send_json_error(['message' => __('Please provide a template URL.', 'smart-seo-fixer')]);
        }
        
        $result = SSF_Client_Report::fetch_template($url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Clear cached report template (admin only)
     */
    public function clear_report_template() {
        $this->verify_nonce();
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        SSF_Client_Report::clear_template();
        wp_send_json_success(['message' => __('Template cleared.', 'smart-seo-fixer')]);
    }

    /**
     * Bulk generate alt text for images missing it (admin only).
     * Processes in batches — call repeatedly until done.
     */
    public function bulk_generate_alt() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        // AI vision costs one request per image, so batches stay small. The
        // filename-only mode can afford much larger batches. Size on whether
        // vision will actually run — a text-only model makes no per-image call,
        // so tiny batches would just make the run needlessly slow.
        $use_ai = !isset($_POST['use_ai']) || $_POST['use_ai'] === '1' || $_POST['use_ai'] === 'true';
        $ai_available = $use_ai && class_exists('SSF_AI') && !empty(SSF_AI::vision_status()['ok']);

        $default    = $ai_available ? 5 : 100;
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : $default;
        $batch_size = max(1, min($batch_size, $ai_available ? 10 : 250));

        $result = SSF_Image_SEO::bulk_generate_alt_text($batch_size, $use_ai);
        wp_send_json_success($result);
    }

    /**
     * Clear the "could not describe" flags so previously skipped images are
     * retried (used after configuring an AI provider).
     */
    public function reset_skipped_alt() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $cleared = SSF_Image_SEO::reset_skipped();
        wp_send_json_success([
            'cleared'   => $cleared,
            'remaining' => SSF_Image_SEO::count_missing_alt(),
            'stats'     => SSF_Image_SEO::alt_stats(),
        ]);
    }

    /**
     * Count images missing alt text (admin only).
     */
    public function count_missing_alt() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $count = SSF_Image_SEO::count_missing_alt();
        wp_send_json_success(['count' => $count]);
    }

    /**
     * Generate alt text for ONE attachment, from the Media Library row action,
     * the attachment edit screen, or the media modal.
     *
     * Runs inline (not via cron) because the user is waiting on the result.
     * Uses edit_post capability per attachment rather than manage_options, so
     * editors can describe their own uploads.
     */
    public function generate_single_alt() {
        if (!check_ajax_referer('ssf_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'smart-seo-fixer')]);
        }

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        if (!$attachment_id || !current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        if (get_post_type($attachment_id) !== 'attachment') {
            wp_send_json_error(['message' => __('Not an attachment.', 'smart-seo-fixer')]);
        }

        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            wp_send_json_error(['message' => __('Not an image.', 'smart-seo-fixer')]);
        }

        $existing = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));

        // Without force, never clobber text that is already there.
        $force = isset($_POST['force']) && ($_POST['force'] === '1' || $_POST['force'] === 'true');
        if ($existing !== '' && !$force) {
            wp_send_json_success([
                'alt'      => $existing,
                'source'   => 'existing',
                'changed'  => false,
                'message'  => __('Already has alt text.', 'smart-seo-fixer'),
            ]);
        }

        $use_ai = !isset($_POST['use_ai']) || $_POST['use_ai'] === '1' || $_POST['use_ai'] === 'true';
        $result = SSF_Image_SEO::generate_alt_for_attachment($attachment_id, $use_ai);

        if ($result['alt'] === '') {
            wp_send_json_error([
                'message' => !empty($result['error'])
                    ? $result['error']
                    : __('Could not generate a description for this image.', 'smart-seo-fixer'),
            ]);
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($result['alt']));
        update_post_meta($attachment_id, SSF_Image_SEO::GENERATED_META, $result['source']);
        delete_post_meta($attachment_id, SSF_Image_SEO::SKIP_META);

        // A filename-derived description is a degraded result, not a success —
        // pass the cause back so the button can say so instead of implying the
        // AI looked at the picture.
        $note = '';
        if ($result['source'] !== 'ai') {
            $note = !empty($result['error'])
                ? $result['error']
                : SSF_Image_SEO::reason_label((string) ($result['reason'] ?? ''));
        }

        wp_send_json_success([
            'alt'     => $result['alt'],
            'old'     => $existing,
            'source'  => $result['source'],
            'reason'  => (string) ($result['reason'] ?? ''),
            'note'    => $note,
            'changed' => true,
        ]);
    }

    /**
     * Check whether the configured AI provider can actually SEE an image.
     *
     * Answers the question the plugin could not answer before: is a description
     * coming from real vision, or from the filename? Uses a real attachment when
     * one exists, so credentials, model capability, permissions and image
     * readability are all exercised on the same path a normal run takes.
     */
    public function test_vision() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $status = SSF_AI::vision_status();

        if (empty($status['ok'])) {
            wp_send_json_error([
                'message'  => $status['message'],
                'reason'   => $status['reason'],
                'provider' => $status['provider'],
                'model'    => $status['model'],
            ]);
        }

        // Find a real image to test with.
        $ids = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ]);

        if (empty($ids)) {
            wp_send_json_error([
                'message' => __('No images in the Media Library to test with. Upload one and try again.', 'smart-seo-fixer'),
                'reason'  => 'no_images',
            ]);
        }

        $attachment_id = (int) $ids[0];

        // Confirm the bytes are readable before spending an API call.
        $image = SSF_AI::fetch_image_as_base64($attachment_id);
        if (is_wp_error($image)) {
            wp_send_json_error([
                'message'  => sprintf(
                    /* translators: %s: underlying error */
                    __('The image file could not be read: %s', 'smart-seo-fixer'),
                    $image->get_error_message()
                ),
                'reason'   => 'image_unreadable',
                'provider' => $status['provider'],
                'model'    => $status['model'],
            ]);
        }

        $result = SSF_Image_SEO::generate_alt_for_attachment($attachment_id, true);

        if ($result['source'] !== 'ai') {
            wp_send_json_error([
                'message'  => !empty($result['error'])
                    ? $result['error']
                    : SSF_Image_SEO::reason_label((string) ($result['reason'] ?? '')),
                'reason'   => (string) ($result['reason'] ?? ''),
                'provider' => $status['provider'],
                'model'    => $status['model'],
            ]);
        }

        wp_send_json_success([
            'message'  => sprintf(
                /* translators: 1: provider name, 2: model ID */
                __('%1$s can see your images (%2$s).', 'smart-seo-fixer'),
                $status['provider'],
                $status['model'] !== '' ? $status['model'] : __('default model', 'smart-seo-fixer')
            ),
            'provider' => $status['provider'],
            'model'    => $status['model'],
            'sample'   => $result['alt'],
            'image'    => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
            'filename' => basename((string) get_attached_file($attachment_id)),
        ]);
    }

    /**
     * Media-library alt text statistics (admin only) — total images, how many
     * have alt text, how many are missing it.
     */
    public function alt_stats() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        wp_send_json_success(SSF_Image_SEO::alt_stats());
    }

    /**
     * Regenerate alt text for images that already have it — replacing values
     * written by the old buggy filename heuristic, or upgrading filename text
     * to real AI descriptions.
     */
    public function regenerate_alt() {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $use_ai       = !isset($_POST['use_ai']) || $_POST['use_ai'] === '1' || $_POST['use_ai'] === 'true';
        $ai_available = $use_ai && class_exists('SSF_AI') && !empty(SSF_AI::vision_status()['ok']);

        // Default to protecting hand-written alt text; the UI must opt in
        // explicitly to overwrite everything.
        $only_generated = !isset($_POST['overwrite_all'])
            || !($_POST['overwrite_all'] === '1' || $_POST['overwrite_all'] === 'true');

        // Only the first call of a run starts a new pass; later calls continue it.
        $start_new_pass = isset($_POST['new_pass'])
            && ($_POST['new_pass'] === '1' || $_POST['new_pass'] === 'true');

        $default    = $ai_available ? 5 : 100;
        $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : $default;
        $batch_size = max(1, min($batch_size, $ai_available ? 10 : 250));

        $result = SSF_Image_SEO::regenerate_alt_text($batch_size, $use_ai, $only_generated, $start_new_pass);
        wp_send_json_success($result);
    }
}

