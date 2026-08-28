<?php
/**
 * Plugin Name: Smart SEO Fixer
 * Version: 2.0.83
 * Author: mbh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-seo-fixer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SSF_VERSION', '2.0.83');
define('SSF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class Smart_SEO_Fixer {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $ai;
    /** @deprecated Use $ai — kept for back-compat, holds the same provider instance. */
    public $openai;
    public $analyzer;
    public $meta_manager;
    public $schema;
    public $sitemap;
    public $local_seo;
    public $redirects;
    public $breadcrumbs;
    public $woocommerce;
    public $updater;
    public $search_console;
    public $gsc_client;
    public $ga_client;
    public $admin;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
                $includes = [
            'includes/class-bedrock.php',
            'includes/class-openai.php',
            'includes/class-claude.php',
            'includes/class-gemini.php',
            'includes/class-ai.php',
            'includes/class-analyzer.php',
            'includes/class-meta-manager.php',
            'includes/class-schema.php',
            'includes/class-sitemap.php',
            'includes/class-local-seo.php',
            'includes/class-migration.php',
            'includes/class-redirects.php',
            'includes/class-breadcrumbs.php',
            'includes/class-woocommerce.php',
            'includes/class-updater.php',
            'includes/class-history.php',
            'includes/class-logger.php',
            'includes/class-job-queue.php',
            'includes/class-rate-limiter.php',
            'includes/class-search-console.php',
            'includes/class-gsc-client.php',
            'includes/class-ga-client.php',
            'includes/class-db-migrator.php',
            'includes/class-validator.php',
            'includes/class-setup-wizard.php',
            'includes/class-broken-links.php',
            'includes/class-404-monitor.php',
            'includes/class-robots-editor.php',
            'includes/class-indexnow.php',
            'includes/class-readability.php',
            'includes/class-social-preview.php',
            'includes/class-keyword-tracker.php',
            'includes/class-content-suggestions.php',
            'includes/class-wp-standards.php',
            'includes/class-performance.php',
            'includes/class-image-seo.php',
            'includes/class-media-library.php',
            'includes/class-email-digest.php',
            'includes/class-client-report.php',
            'includes/class-auto-internal-links.php',
            'includes/class-ajax.php',
        ];
        
        foreach ($includes as $file) {
            $path = SSF_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        if (is_admin()) {
            $admin_path = SSF_PLUGIN_DIR . 'includes/class-admin.php';
            if (file_exists($admin_path)) {
                require_once $admin_path;
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        
        // Background SEO generation cron
        add_action('ssf_cron_generate_missing_seo', [$this, 'cron_generate_missing_seo']);
        
        // Job queue processor cron
        if (class_exists('SSF_Job_Queue')) {
            add_filter('cron_schedules', ['SSF_Job_Queue', 'add_cron_interval']);
            add_action(SSF_Job_Queue::CRON_HOOK, ['SSF_Job_Queue', 'process_queue']);
            // Loopback tick endpoint — lets the queue process the next batch
            // in seconds rather than waiting for the next WP-Cron minute.
            add_action('wp_ajax_ssf_queue_tick',        ['SSF_Job_Queue', 'ajax_queue_tick']);
            add_action('wp_ajax_nopriv_ssf_queue_tick', ['SSF_Job_Queue', 'ajax_queue_tick']);
        }
        
        // Broken link checker cron
        if (class_exists('SSF_Broken_Links')) {
            add_action(SSF_Broken_Links::CRON_HOOK, ['SSF_Broken_Links', 'cron_scan']);
        }
        
        // Keyword tracker cron
        if (class_exists('SSF_Keyword_Tracker')) {
            add_action(SSF_Keyword_Tracker::CRON_HOOK, ['SSF_Keyword_Tracker', 'cron_track']);
        }
        
        // Email digest cron
        if (class_exists('SSF_Email_Digest')) {
            add_action(SSF_Email_Digest::CRON_HOOK, ['SSF_Email_Digest', 'send_digest']);
        }
        
        // 404 Monitor (frontend hook)
        if (class_exists('SSF_404_Monitor')) {
            SSF_404_Monitor::init();
        }

        // Auto-add internal links when a post is first published.
        if (class_exists('SSF_Auto_Internal_Links')) {
            SSF_Auto_Internal_Links::init();
        }
        
        // robots.txt editor (frontend hook)
        if (class_exists('SSF_Robots_Editor')) {
            SSF_Robots_Editor::init();
        }

        // IndexNow — instant indexing on publish/update (frontend + admin)
        if (class_exists('SSF_IndexNow')) {
            SSF_IndexNow::init();
        }
        
        // Image SEO (frontend content filter for lazy load, dimensions, etc.)
        if (class_exists('SSF_Image_SEO') && Smart_SEO_Fixer::get_option('enable_image_seo', true)) {
            SSF_Image_SEO::init();
        }

        // Media Library alt text column, per-image buttons, and bulk action.
        // Not gated on enable_image_seo — that option covers the frontend
        // content filter, while this is an editorial tool in the admin.
        if (class_exists('SSF_Media_Library')) {
            SSF_Media_Library::init();
        }
        
        // Performance profiler (record plugin load metrics)
        if (class_exists('SSF_Performance') && is_admin()) {
            SSF_Performance::start();
            add_action('wp_loaded', ['SSF_Performance', 'end'], 9999);
        }
        
        // Core Web Vitals tracking (frontend)
        if (class_exists('SSF_Performance')) {
            add_action('wp', ['SSF_Performance', 'enqueue_cwv_script']);
            add_action('wp_ajax_ssf_cwv_beacon', ['SSF_Performance', 'handle_cwv_beacon']);
            add_action('wp_ajax_nopriv_ssf_cwv_beacon', ['SSF_Performance', 'handle_cwv_beacon']);
        }
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize components
     */
    public function init() {
        // Safely instantiate each class (defensive if a file failed to load)
        if (class_exists('SSF_AI')) {
            $this->ai     = SSF_AI::get();
            $this->openai = $this->ai; // back-compat alias
        }
        if (class_exists('SSF_Analyzer'))     $this->analyzer      = new SSF_Analyzer();
        if (class_exists('SSF_Meta_Manager')) $this->meta_manager  = new SSF_Meta_Manager();
        if (class_exists('SSF_Schema'))       $this->schema        = new SSF_Schema();
        if (class_exists('SSF_Sitemap'))      $this->sitemap       = new SSF_Sitemap();
        if (class_exists('SSF_Local_SEO'))    $this->local_seo     = new SSF_Local_SEO();
        if (class_exists('SSF_Redirects'))    $this->redirects     = new SSF_Redirects();
        if (class_exists('SSF_Breadcrumbs'))  $this->breadcrumbs   = new SSF_Breadcrumbs();
        if (class_exists('SSF_WooCommerce'))  $this->woocommerce   = new SSF_WooCommerce();
        if (class_exists('SSF_Search_Console')) $this->search_console = new SSF_Search_Console();
        if (class_exists('SSF_GSC_Client'))   $this->gsc_client    = new SSF_GSC_Client();
        if (class_exists('SSF_GA_Client'))    $this->ga_client     = new SSF_GA_Client();
        if (class_exists('SSF_Social_Preview')) new SSF_Social_Preview();
        if (class_exists('SSF_Ajax'))         new SSF_Ajax();
        
        // Enable automatic history tracking for all _ssf_ meta changes
        if (class_exists('SSF_History')) {
            SSF_History::enable_tracking();
        }
        
        // Run pending DB migrations
        if (is_admin() && class_exists('SSF_DB_Migrator')) {
            SSF_DB_Migrator::maybe_migrate();
        }
        
        if (is_admin() && class_exists('SSF_Admin')) {
            $this->admin = new SSF_Admin();
            // Updater only needed in admin (update checks, plugin info, download auth)
            if (class_exists('SSF_Updater')) $this->updater = new SSF_Updater();
            // Setup wizard (hidden page, redirects on first activation)
            if (class_exists('SSF_Setup_Wizard')) new SSF_Setup_Wizard();
            // Ensure DB table exists (self-healing if plugin was updated without reactivation)
            add_action('admin_init', [$this, 'maybe_create_tables']);
            // Ensure cron is scheduled (self-healing if cron was lost)
            add_action('admin_init', [$this, 'maybe_schedule_cron']);
            // Handle force update check from plugins page
            if (class_exists('SSF_Updater')) add_action('admin_init', ['SSF_Updater', 'force_check']);
            // Auto-detect custom post types (no need on every frontend request)
            add_action('init', [$this, 'auto_detect_post_types'], 999);
        }
    }
    
    /**
     * Auto-detect all public post types and ensure they're in the post_types setting
     */
    public function auto_detect_post_types() {
        $saved = self::get_option('post_types', ['post', 'page']);
        
        // Get all public post types (excluding built-in non-content types)
        $all_public = get_post_types(['public' => true], 'names');
        unset($all_public['attachment']); // Don't include media attachments
        
        // Merge: add any new public post types that weren't in settings
        $merged = array_unique(array_merge($saved, array_values($all_public)));
        
        // Only update if we found new ones
        if (count($merged) > count($saved)) {
            update_option('ssf_post_types', $merged);
        }
    }
    
    /**
     * Load translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('smart-seo-fixer', false, dirname(SSF_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Activation
     */
    public function activate() {
        // Set default options
        $defaults = [
            'ai_provider' => 'bedrock',
            'bedrock_model' => 'us.anthropic.claude-haiku-4-5-20251001-v1:0',
            'bedrock_region' => 'us-east-1',
            'auto_meta' => false,
            'auto_alt_text' => false,
            'auto_noindex_thin' => true,
            'thin_content_threshold' => 50,
            'enrich_image_posts' => true,
            'enable_schema' => true,
            'enable_sitemap' => true,
            'disable_other_seo_output' => false,
            'title_separator' => '|',
            'homepage_title' => '',
            'homepage_description' => '',
            'post_types' => ['post', 'page'], // Updated on init to include custom post types
            'background_seo_cron' => true, // Auto-fill missing SEO in background
            'enable_image_seo' => true, // Lazy load, width/height, decoding=async
            'enable_cwv_tracking' => false, // Core Web Vitals RUM (opt-in)
            'enable_email_digest' => false, // Weekly SEO score digest (opt-in)
            'enable_indexnow' => true, // Instant indexing (Bing/Yandex/etc.) on publish
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option('ssf_' . $key) === false) {
                update_option('ssf_' . $key, $value);
            }
        }
        
        // Create database tables if needed
        $this->create_tables();
        
        // Flush rewrite rules so sitemap URLs are properly routed (force=true to also update .htaccess)
        flush_rewrite_rules(true);
        
        // Trigger setup wizard redirect on first activation
        if (!get_option('ssf_setup_completed', false)) {
            set_transient('ssf_activation_redirect', true, 60);
        }
        
        // Schedule background SEO generation cron (twice daily)
        if (!wp_next_scheduled('ssf_cron_generate_missing_seo')) {
            wp_schedule_event(time(), 'twicedaily', 'ssf_cron_generate_missing_seo');
        }
        
        // Schedule job queue processor (every minute)
        if (class_exists('SSF_Job_Queue')) {
            SSF_Job_Queue::schedule_cron();
        }
        
        // Schedule broken link checker (daily)
        if (class_exists('SSF_Broken_Links')) {
            SSF_Broken_Links::schedule_cron();
        }
        
        // Schedule keyword tracker (daily)
        if (class_exists('SSF_Keyword_Tracker')) {
            SSF_Keyword_Tracker::schedule_cron();
        }
        
        // Schedule email digest (weekly)
        if (class_exists('SSF_Email_Digest')) {
            SSF_Email_Digest::schedule_cron();
        }

        // Schedule instant auto-update check (every 5 minutes)
        if (class_exists('SSF_Updater')) {
            SSF_Updater::schedule_cron();
        }
    }
    
    /**
     * Deactivation
     */
    public function deactivate() {
        // Clear scheduled crons
        $timestamp = wp_next_scheduled('ssf_cron_generate_missing_seo');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ssf_cron_generate_missing_seo');
        }
        
        if (class_exists('SSF_Job_Queue')) {
            SSF_Job_Queue::unschedule_cron();
        }
        
        if (class_exists('SSF_Broken_Links')) {
            SSF_Broken_Links::unschedule_cron();
        }
        
        if (class_exists('SSF_Keyword_Tracker')) {
            SSF_Keyword_Tracker::unschedule_cron();
        }
        
        if (class_exists('SSF_Email_Digest')) {
            SSF_Email_Digest::unschedule_cron();
        }

        if (class_exists('SSF_Updater')) {
            SSF_Updater::unschedule_cron();
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Background cron: Auto-generate missing SEO titles & descriptions
     * Runs twice daily — processes up to 10 posts per run to respect API limits
     */
    public function cron_generate_missing_seo() {
        // Check if background cron is enabled
        if (!self::get_option('background_seo_cron', true)) {
            return;
        }
        
        // Set history source to cron
        if (class_exists('SSF_History')) {
            SSF_History::set_source('cron');
        }
        
                // Must have AI configured
        if (!class_exists('SSF_AI')) {
            return;
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            return;
        }
        
        global $wpdb;
        $post_types = self::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Find published posts missing SEO title (limit 10 per cron run)
        $posts_missing_title = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_seo_title'
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
                ORDER BY p.post_date DESC
                LIMIT 10",
                ...$post_types
            )
        );
        
        $generated_count = 0;
        
        foreach ($posts_missing_title as $post_id) {
            $post = get_post($post_id);
            if (!$post) { continue; }
            // Use enriched context (body + excerpt + public meta + image alts)
            // so page-builder / location CPTs with empty post_content still
            // get processed. See SSF_Job_Queue::enrich_post_context().
            $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
            if (str_word_count(strip_tags($enriched)) < 10) {
                continue;
            }

            $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);

            // Generate title
            $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
            if (empty($seo_title)) {
                $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                if (!is_wp_error($title) && !empty(trim($title))) {
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field(trim($title)));
                    $generated_count++;
                }
            }
            
            // Generate description
            $meta_desc = get_post_meta($post_id, '_ssf_meta_description', true);
            if (empty($meta_desc)) {
                $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                if (!is_wp_error($desc) && !empty(trim($desc))) {
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field(trim($desc)));
                }
            }
            
            // Generate focus keyword if empty
            if (empty($focus_keyword)) {
                $keywords = $openai->suggest_keywords($enriched, $post->post_title);
                if (!is_wp_error($keywords) && is_array($keywords) && !empty($keywords['primary'])) {
                    update_post_meta($post_id, '_ssf_focus_keyword', sanitize_text_field($keywords['primary']));
                }
            }
            
            // Run analysis to update score
            if (class_exists('SSF_Analyzer')) {
                $analyzer = new SSF_Analyzer();
                $analyzer->analyze_post($post_id);
            }
        }
        
        // Log for debugging
        if ($generated_count > 0) {
            update_option('ssf_cron_last_run', [
                'time' => current_time('mysql'),
                'generated' => $generated_count,
                'remaining' => max(0, count($posts_missing_title) - $generated_count),
            ]);
        }
    }
    
    /**
     * Ensure cron is scheduled (self-healing if lost after server migration, etc.)
     */
    public function maybe_schedule_cron() {
        if (self::get_option('background_seo_cron', true)) {
            if (!wp_next_scheduled('ssf_cron_generate_missing_seo')) {
                wp_schedule_event(time() + 3600, 'twicedaily', 'ssf_cron_generate_missing_seo');
            }
        }
    }
    
    /**
     * Ensure database tables exist (runs on admin_init).
     * Gated by a per-version option so the SHOW TABLES probes run once per
     * plugin version instead of on every admin request.
     */
    public function maybe_create_tables() {
        if (get_option('ssf_tables_verified') === SSF_VERSION) {
            return;
        }

        global $wpdb;
        $tables_to_check = [
            $wpdb->prefix . 'ssf_seo_scores',
            $wpdb->prefix . 'ssf_history',
            $wpdb->prefix . 'ssf_logs',
            $wpdb->prefix . 'ssf_jobs',
            $wpdb->prefix . 'ssf_broken_links',
            $wpdb->prefix . 'ssf_404_log',
            $wpdb->prefix . 'ssf_keyword_tracking',
        ];

        foreach ($tables_to_check as $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $this->create_tables();
                break;
            }
        }

        update_option('ssf_tables_verified', SSF_VERSION, false);
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ssf_seo_scores';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            score int(3) NOT NULL DEFAULT 0,
            issues longtext,
            suggestions longtext,
            last_analyzed datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY score (score)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create history table
        if (class_exists('SSF_History')) {
            SSF_History::create_table();
        }
        
        // Create log table
        if (class_exists('SSF_Logger')) {
            SSF_Logger::create_table();
        }
        
        // Create jobs table
        if (class_exists('SSF_Job_Queue')) {
            SSF_Job_Queue::create_table();
        }
        
        // Create broken links table
        if (class_exists('SSF_Broken_Links')) {
            SSF_Broken_Links::create_table();
        }
        
        // Create 404 log table
        if (class_exists('SSF_404_Monitor')) {
            SSF_404_Monitor::create_table();
        }
        
        // Create keyword tracking table
        if (class_exists('SSF_Keyword_Tracker')) {
            SSF_Keyword_Tracker::create_table();
        }
    }
    
    /**
     * Get option helper
     */
    public static function get_option($key, $default = '') {
        return get_option('ssf_' . $key, $default);
    }
    
    /**
     * Update option helper
     */
    public static function update_option($key, $value) {
        return update_option('ssf_' . $key, $value);
    }
}

/**
 * Returns the main instance
 */
function ssf() {
    return Smart_SEO_Fixer::instance();
}

// Initialize
ssf();

