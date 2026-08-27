<?php
/**
 * 404 Monitor
 * 
 * Logs every 404 hit on the site so you can identify missing pages
 * and create redirects to recover lost traffic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_404_Monitor {
    
    /**
     * Get database table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_404_log';
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
            url varchar(2083) NOT NULL,
            referrer varchar(2083) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            hit_count int(10) DEFAULT 1,
            first_hit datetime NOT NULL,
            last_hit datetime NOT NULL,
            redirected_to varchar(2083) DEFAULT '',
            dismissed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY url_hash (url(191)),
            KEY last_hit (last_hit),
            KEY hit_count (hit_count),
            KEY dismissed (dismissed)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Hook into WordPress template_redirect to catch 404s
     */
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'log_404']);
    }
    
    /**
     * Log a 404 hit
     */
    public static function log_404() {
        if (!is_404()) {
            return;
        }
        
        global $wpdb;
        $table = self::table();
        
        // Check table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        $url = self::get_current_url();
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)) : '';
        $ip = self::get_client_ip();
        $now = current_time('mysql');
        
        // Skip common bot/scanner paths
        if (self::should_skip($url)) {
            return;
        }
        
        // Check if we already logged this URL
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, hit_count FROM $table WHERE url = %s AND dismissed = 0",
            $url
        ));
        
        if ($existing) {
            $wpdb->update($table, [
                'hit_count'  => $existing->hit_count + 1,
                'last_hit'   => $now,
                'referrer'   => $referrer ?: $wpdb->get_var($wpdb->prepare("SELECT referrer FROM $table WHERE id = %d", $existing->id)),
                'user_agent' => $user_agent,
                'ip_address' => $ip,
            ], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table, [
                'url'        => $url,
                'referrer'   => $referrer,
                'user_agent' => $user_agent,
                'ip_address' => $ip,
                'hit_count'  => 1,
                'first_hit'  => $now,
                'last_hit'   => $now,
                'dismissed'  => 0,
            ]);

            // Occasionally prune so scanner noise can't grow the table forever.
            if (mt_rand(1, 25) === 1) {
                self::enforce_cap();
            }
        }
    }

    /**
     * Keep the 404 log bounded. Drops the least useful rows (dismissed first,
     * then single-hit entries with the oldest last_hit) once the table exceeds
     * the cap. Redirected entries are kept — they document recovered URLs.
     */
    public static function enforce_cap($max_rows = 1000) {
        global $wpdb;
        $table = self::table();

        // Purge old dismissed entries first (180+ days).
        self::cleanup();

        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE redirected_to = ''"));
        if ($count <= $max_rows) {
            return;
        }

        $excess = $count - $max_rows;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table
             WHERE redirected_to = ''
             ORDER BY dismissed DESC, hit_count ASC, last_hit ASC
             LIMIT %d",
            $excess
        ));
    }
    
    /**
     * Get the current request URL (path only)
     */
    private static function get_current_url() {
        $path = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '/';
        return $path;
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return sanitize_text_field($ip);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Skip common bot/scanner paths that generate noise
     */
    private static function should_skip($url) {
        $skip_patterns = [
            '/wp-login.php',
            '/xmlrpc.php',
            '/.env',
            '/wp-config',
            '/.git',
            '/phpmyadmin',
            '/wp-admin/admin-ajax.php',
            '.php.bak',
            '.sql',
            '/wp-content/debug.log',
        ];
        
        $url_lower = strtolower($url);
        foreach ($skip_patterns as $pattern) {
            if (strpos($url_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Query 404 logs with pagination and filters
     */
    public static function query($args = []) {
        global $wpdb;
        $table = self::table();
        
        $defaults = [
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
            'status'   => 'active', // active, dismissed, redirected, all
            'order_by' => 'hit_count',
            'order'    => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $where = ['1=1'];
        $params = [];
        
        if ($args['status'] === 'active') {
            $where[] = "dismissed = 0 AND redirected_to = ''";
        } elseif ($args['status'] === 'dismissed') {
            $where[] = 'dismissed = 1';
        } elseif ($args['status'] === 'redirected') {
            $where[] = "redirected_to != ''";
        }
        
        if (!empty($args['search'])) {
            $where[] = '(url LIKE %s OR referrer LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $allowed_order = ['hit_count', 'last_hit', 'first_hit', 'url'];
        $order_by = in_array($args['order_by'], $allowed_order) ? $args['order_by'] : 'hit_count';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Count
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        $total = !empty($params) ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql);
        
        // Results
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_by $order LIMIT %d OFFSET %d";
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
     * Create a redirect from a 404 URL
     */
    public static function create_redirect($id, $redirect_to) {
        global $wpdb;
        $table = self::table();
        
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$record) {
            return new WP_Error('not_found', '404 record not found');
        }
        
        // Update the 404 record
        $wpdb->update($table, [
            'redirected_to' => esc_url_raw($redirect_to),
        ], ['id' => $id]);
        
        // Also add to the plugin's redirect list if SSF_Redirects exists.
        // Must go through add_redirect() so the rule gets an id and the
        // 'enabled' flag that maybe_redirect() checks — raw option appends
        // produced rules that never fired and couldn't be managed in the UI.
        if (class_exists('SSF_Redirects')) {
            $redirects_manager = new SSF_Redirects();
            $from_path = wp_parse_url($record->url, PHP_URL_PATH);
            $from_path = $from_path !== null ? $from_path : $record->url;

            $duplicate = false;
            foreach ($redirects_manager->get_redirects() as $r) {
                if (trim($r['from'] ?? '', '/') === trim($from_path, '/')) {
                    $duplicate = true;
                    break;
                }
            }

            if (!$duplicate) {
                $redirects_manager->add_redirect([
                    'from' => $from_path,
                    'to'   => esc_url_raw($redirect_to),
                    'type' => 301,
                    'note' => __('Created from 404 Monitor', 'smart-seo-fixer'),
                    'auto' => false,
                ]);
            }
        }
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('Created redirect from 404: %s → %s', $record->url, $redirect_to), 'general');
        }
        
        return true;
    }
    
    /**
     * Dismiss a 404 entry
     */
    public static function dismiss($id) {
        global $wpdb;
        return $wpdb->update(self::table(), ['dismissed' => 1], ['id' => absint($id)]);
    }
    
    /**
     * Delete a 404 record
     */
    public static function delete_record($id) {
        global $wpdb;
        return $wpdb->delete(self::table(), ['id' => absint($id)]);
    }
    
    /**
     * Get summary stats
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total_active' => 0, 'total_hits' => 0, 'redirected' => 0, 'dismissed' => 0, 'top_url' => ''];
        }
        
        $top = $wpdb->get_row("SELECT url, hit_count FROM $table WHERE dismissed = 0 AND redirected_to = '' ORDER BY hit_count DESC LIMIT 1");
        
        return [
            'total_active' => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 0 AND redirected_to = ''")),
            'total_hits'   => intval($wpdb->get_var("SELECT COALESCE(SUM(hit_count), 0) FROM $table WHERE dismissed = 0")),
            'redirected'   => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE redirected_to != ''")),
            'dismissed'    => intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE dismissed = 1")),
            'top_url'      => $top ? $top->url : '',
            'top_hits'     => $top ? intval($top->hit_count) : 0,
        ];
    }
    
    /**
     * Clear all logs
     */
    public static function clear_all() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::table());
    }
    
    /**
     * Clean up old entries (older than 180 days)
     */
    public static function cleanup() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::table() . " WHERE dismissed = 1 AND last_hit < %s",
            date('Y-m-d H:i:s', strtotime('-180 days'))
        ));
    }
}
