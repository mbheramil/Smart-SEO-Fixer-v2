<?php
/**
 * Uninstall Smart SEO Fixer
 *
 * Removes all plugin data when the plugin is deleted (not just deactivated).
 * This only runs when a user explicitly deletes the plugin from the admin.
 *
 * Cleanup is prefix-based so nothing is left behind — every option, transient,
 * post meta, term meta, table, and cron event this plugin creates uses the
 * ssf_ / _ssf_ prefix.
 */

// Abort if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Remove ALL plugin options by prefix (includes every API key:
//    Bedrock, OpenAI, Claude, Gemini, GitHub token, GSC/GA credentials).
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'ssf\_%'
        OR option_name LIKE '\_transient\_ssf\_%'
        OR option_name LIKE '\_transient\_timeout\_ssf\_%'
        OR option_name LIKE '\_site\_transient\_ssf\_%'
        OR option_name LIKE '\_site\_transient\_timeout\_ssf\_%'"
);

// Legacy combined-options array used by early versions / migration v7.
delete_option('smart_seo_fixer_options');

// 2. Remove all plugin post meta (every key starts with _ssf_ — this covers
//    _ssf_alt_unavailable, _ssf_alt_generated and _ssf_alt_regen_pass).
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_ssf\_%'");

// 3. Remove all plugin term meta (WooCommerce category SEO etc.).
$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_ssf\_%'");

// 4. Drop custom database tables.
$tables = [
    $wpdb->prefix . 'ssf_seo_scores',
    $wpdb->prefix . 'ssf_history',
    $wpdb->prefix . 'ssf_logs',
    $wpdb->prefix . 'ssf_jobs',
    $wpdb->prefix . 'ssf_broken_links',
    $wpdb->prefix . 'ssf_404_log',
    $wpdb->prefix . 'ssf_keyword_tracking',
];
foreach ($tables as $table_name) {
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// 5. Clear ALL scheduled cron events (wp_clear_scheduled_hook removes
//    every occurrence of the hook, not just the next one).
$cron_hooks = [
    'ssf_cron_generate_missing_seo',
    'ssf_process_job_queue',
    'ssf_check_broken_links',
    'ssf_track_keywords',
    'ssf_send_email_digest',
    'ssf_auto_update_check',
    'ssf_auto_internal_links_run',
    'ssf_generate_alt_for_upload',
];
foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}

// 6. Flush rewrite rules (sitemap rules).
flush_rewrite_rules();
