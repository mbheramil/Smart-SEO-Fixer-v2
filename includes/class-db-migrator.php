<?php
/**
 * Database Migrator
 * 
 * Versioned schema updates that apply cleanly on plugin update.
 * Each migration runs once and is tracked by version number.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_DB_Migrator {
    
    const DB_VERSION_KEY = 'ssf_db_version';
    
    /**
     * Current target DB version (increment when adding new migrations)
     */
    const CURRENT_VERSION = 11;
    
    /**
     * Run any pending migrations
     * Called on admin_init to catch updates seamlessly.
     */
    public static function maybe_migrate() {
        $current = intval(get_option(self::DB_VERSION_KEY, 0));
        
        if ($current >= self::CURRENT_VERSION) {
            return;
        }
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('Running DB migrations from v%d to v%d', $current, self::CURRENT_VERSION), 'migration');
        }
        
        // Run each migration that hasn't been applied yet
        $migrations = self::get_migrations();
        
        foreach ($migrations as $version => $callback) {
            if ($current < $version) {
                try {
                    call_user_func($callback);
                    update_option(self::DB_VERSION_KEY, $version);
                    
                    if (class_exists('SSF_Logger')) {
                        SSF_Logger::info(sprintf('Migration v%d completed', $version), 'migration');
                    }
                } catch (Exception $e) {
                    if (class_exists('SSF_Logger')) {
                        SSF_Logger::error(sprintf('Migration v%d failed: %s', $version, $e->getMessage()), 'migration');
                    }
                    return; // Stop on failure
                }
            }
        }
    }
    
    /**
     * Define all migrations in order.
     * Each key is a version number, value is a callable.
     */
    private static function get_migrations() {
        return [
            // v1: SEO scores table (original)
            1 => [__CLASS__, 'migrate_v1_seo_scores'],
            // v2: History table (v1.10.0)
            2 => [__CLASS__, 'migrate_v2_history_logs'],
            // v3: Jobs table (v1.11.0)
            3 => [__CLASS__, 'migrate_v3_jobs'],
            // v4: Setup wizard completed flag + input validation defaults (v1.12.0)
            4 => [__CLASS__, 'migrate_v4_setup_validation'],
            // v5: Broken links + 404 log tables (v1.13.0)
            5 => [__CLASS__, 'migrate_v5_broken_links_404'],
            // v6: Keyword tracking table (v1.14.0)
            6 => [__CLASS__, 'migrate_v6_keyword_tracking'],
            // v7: Fix stale Bedrock model IDs saved with wrong date suffix or us. prefix (v1.16.10)
            7 => [__CLASS__, 'migrate_v7_fix_bedrock_model_id'],
            // v8: Repair redirect rules created by the 404 Monitor with the wrong
            // 'active' key (instead of 'enabled') and no id — they never fired
            // and couldn't be deleted/toggled from the Redirects UI (v2.0.54)
            8 => [__CLASS__, 'migrate_v8_repair_404_redirects'],
            // v9: Upgrade Bedrock model to Claude Haiku 4.5 and re-flush sitemap
            // rewrite rules so /sitemap.xml resolves after the auto-update (v2.0.57)
            9 => [__CLASS__, 'migrate_v9_haiku_45_and_sitemap'],
            // v10: Purge broken-link false positives (403/401/429/999 bot-block,
            // auth, and rate-limit codes) recorded by older versions (v2.0.65)
            10 => [__CLASS__, 'migrate_v10_purge_softok_broken_links'],
            // v11: Remove alt text corrupted by the old filename regex, which
            // stripped a leading letter from ordinary words ("party-rentals"
            // became "Arty Rentals") (v2.0.66)
            11 => [__CLASS__, 'migrate_v11_repair_corrupted_alt_text'],
        ];
    }
    
    /**
     * Migration v1: SEO scores table
     */
    public static function migrate_v1_seo_scores() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
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
    }
    
    /**
     * Migration v2: History and logs tables
     */
    public static function migrate_v2_history_logs() {
        if (class_exists('SSF_History')) {
            SSF_History::create_table();
        }
        if (class_exists('SSF_Logger')) {
            SSF_Logger::create_table();
        }
    }
    
    /**
     * Migration v3: Jobs table
     */
    public static function migrate_v3_jobs() {
        if (class_exists('SSF_Job_Queue')) {
            SSF_Job_Queue::create_table();
        }
    }
    
    /**
     * Migration v4: Setup wizard flag and validation defaults
     */
    public static function migrate_v4_setup_validation() {
        // If the plugin already has Bedrock credentials configured, mark setup as complete
        $access_key = get_option('ssf_bedrock_access_key', '');
        if (!empty($access_key)) {
            update_option('ssf_setup_completed', true);
        }
    }
    
    /**
     * Migration v5: Broken links and 404 log tables
     */
    public static function migrate_v5_broken_links_404() {
        if (class_exists('SSF_Broken_Links')) {
            SSF_Broken_Links::create_table();
        }
        if (class_exists('SSF_404_Monitor')) {
            SSF_404_Monitor::create_table();
        }
    }
    
    /**
     * Migration v6: Keyword tracking table
     */
    public static function migrate_v6_keyword_tracking() {
        if (class_exists('SSF_Keyword_Tracker')) {
            SSF_Keyword_Tracker::create_table();
        }
    }

    /**
     * Migration v7: Force bedrock_model to the correct hardcoded value.
     * Model selection has been removed from the UI — always use anthropic.claude-3-5-haiku-20241022-v1:0.
     */
    public static function migrate_v7_fix_bedrock_model_id() {
        $opts = get_option('smart_seo_fixer_options', []);
        $opts['bedrock_model'] = 'us.anthropic.claude-3-5-haiku-20241022-v1:0';
        update_option('smart_seo_fixer_options', $opts);
    }

    /**
     * Migration v8: Repair redirect rules appended directly to the ssf_redirects
     * option by the old 404 Monitor code path. Those rows used 'active' => true
     * (never read by maybe_redirect, which checks 'enabled') and had no 'id',
     * so they silently never redirected and couldn't be managed from the UI.
     */
    public static function migrate_v8_repair_404_redirects() {
        $redirects = get_option('ssf_redirects', []);
        if (!is_array($redirects) || empty($redirects)) {
            return;
        }

        $changed = false;
        foreach ($redirects as &$r) {
            if (!is_array($r)) {
                continue;
            }
            if (!isset($r['enabled'])) {
                $r['enabled'] = isset($r['active']) ? (bool) $r['active'] : true;
                unset($r['active']);
                $changed = true;
            }
            if (empty($r['id'])) {
                $r['id'] = uniqid('r_');
                $changed = true;
            }
        }
        unset($r);

        if ($changed) {
            update_option('ssf_redirects', $redirects);
        }
    }

    /**
     * Migration v9: Move existing installs to Claude Haiku 4.5 (the prior default
     * was Claude 3.5 Haiku) and force a rewrite-rule flush so the sitemap routes
     * resolve immediately after the auto-update — without waiting for a manual
     * Settings → Permalinks save.
     */
    public static function migrate_v9_haiku_45_and_sitemap() {
        $new_model = 'us.anthropic.claude-haiku-4-5-20251001-v1:0';

        // Standalone option used by SSF_Bedrock / settings.
        $current = get_option('ssf_bedrock_model', '');
        if ($current !== $new_model) {
            update_option('ssf_bedrock_model', $new_model);
        }

        // Legacy combined-options array (kept in sync by v7).
        $opts = get_option('smart_seo_fixer_options', []);
        if (is_array($opts)) {
            $opts['bedrock_model'] = $new_model;
            update_option('smart_seo_fixer_options', $opts);
        }

        // The sitemap singleton already registered its rewrite rules on `init`
        // (which runs before this admin_init migration), so flushing here
        // persists them — /sitemap.xml resolves without a manual Permalinks save.
        flush_rewrite_rules(false);
    }

    /**
     * Migration v10: Remove historical broken-link false positives — records
     * whose status code is now treated as "not broken" (403 bot-block, 401 auth,
     * 429 rate-limit, 999 LinkedIn). These were flagged by older versions before
     * the checker learned to ignore bot-blocking responses.
     */
    public static function migrate_v10_purge_softok_broken_links() {
        if (class_exists('SSF_Broken_Links')) {
            SSF_Broken_Links::purge_soft_ok();
        }
    }

    /**
     * Migration v11: Repair image alt text corrupted by the old filename
     * heuristic. That regex stripped a leading P/DC/WP/VID/MOV even when it
     * was part of an ordinary word, so "party-rentals.jpg" produced
     * "Arty Rentals" and "photo-booth.jpg" produced "Hoto Booth".
     *
     * Strategy: recompute what the OLD buggy code would have produced for each
     * attachment's filename. If the stored alt text matches that exactly, it
     * was machine-generated by us and is safe to replace with the corrected
     * value. Anything a human wrote is left untouched.
     */
    public static function migrate_v11_repair_corrupted_alt_text() {
        global $wpdb;

        if (!class_exists('SSF_Image_SEO')) {
            return;
        }

        // Replicates the pre-2.0.66 buggy algorithm exactly (single copy lives
        // on SSF_Image_SEO so the migration and the regenerate pass agree).
        $old_algorithm = function ($filename) {
            return SSF_Image_SEO::legacy_alt_from_filename($filename);
        };

        $repaired = 0;
        $offset   = 0;
        $limit    = 500;
        $scanned  = 0;
        $max_scan = 20000; // Safety bound for very large media libraries.

        do {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT p.ID, pm.meta_value AS alt
                   FROM {$wpdb->posts} p
                   INNER JOIN {$wpdb->postmeta} pm
                           ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                  WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE %s
                    AND pm.meta_value <> ''
                  ORDER BY p.ID ASC
                  LIMIT %d OFFSET %d",
                'image/%',
                $limit,
                $offset
            ));

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $scanned++;

                $path = get_attached_file($row->ID);
                if (!$path) {
                    continue;
                }
                $filename = basename($path);
                $stored   = trim((string) $row->alt);

                $buggy = $old_algorithm($filename);
                if ($buggy === '' || strcasecmp($stored, $buggy) !== 0) {
                    // Not our old output (likely human-written) — leave it be.
                    continue;
                }

                $corrected = SSF_Image_SEO::generate_alt_from_filename($filename);

                if ($corrected === '' ) {
                    // Old code wrote something meaningless (e.g. from IMG_1234).
                    // Clear it so the bulk generator can describe it properly.
                    delete_post_meta($row->ID, '_wp_attachment_image_alt');
                    $repaired++;
                } elseif ($corrected !== $stored) {
                    update_post_meta($row->ID, '_wp_attachment_image_alt', sanitize_text_field($corrected));
                    $repaired++;
                }
            }

            $offset += $limit;
        } while (count($rows) === $limit && $scanned < $max_scan);

        if ($repaired > 0 && class_exists('SSF_Logger')) {
            SSF_Logger::info(
                sprintf('Repaired %d image alt texts corrupted by the old filename heuristic', $repaired),
                'migration'
            );
        }
    }

    /**
     * Get current DB version
     */
    public static function get_version() {
        return intval(get_option(self::DB_VERSION_KEY, 0));
    }
    
    /**
     * Force re-run all migrations (useful for repair)
     */
    public static function reset() {
        delete_option(self::DB_VERSION_KEY);
    }
}
