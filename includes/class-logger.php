<?php
/**
 * Error & Event Logger
 * 
 * Logs plugin events, errors, warnings, and debug info
 * to a database table with an admin-viewable interface.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Logger {
    
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';
    
    /**
     * Get the log table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_logs';
    }
    
    /**
     * Create the log table
     */
    public static function create_table() {
        global $wpdb;
        
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level varchar(10) NOT NULL DEFAULT 'info',
            category varchar(50) NOT NULL DEFAULT 'general',
            message text NOT NULL,
            context longtext,
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY category (category),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log a message
     * 
     * @param string $level    debug|info|warning|error
     * @param string $message  Human-readable message
     * @param string $category Category: 'ai', 'updater', 'gsc', 'meta', 'sitemap', 'general'
     * @param array  $context  Additional data (will be JSON-encoded)
     */
    public static function log($level, $message, $category = 'general', $context = []) {
        global $wpdb;
        
        $wpdb->insert(
            self::table(),
            [
                'level'      => sanitize_key($level),
                'category'   => sanitize_key($category),
                'message'    => sanitize_text_field(substr($message, 0, 1000)),
                'context'    => !empty($context) ? wp_json_encode($context) : null,
                'user_id'    => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s']
        );
    }
    
    /**
     * Convenience methods
     */
    public static function debug($message, $category = 'general', $context = []) {
        self::log(self::LEVEL_DEBUG, $message, $category, $context);
    }
    
    public static function info($message, $category = 'general', $context = []) {
        self::log(self::LEVEL_INFO, $message, $category, $context);
    }
    
    public static function warning($message, $category = 'general', $context = []) {
        self::log(self::LEVEL_WARNING, $message, $category, $context);
    }
    
    public static function error($message, $category = 'general', $context = []) {
        self::log(self::LEVEL_ERROR, $message, $category, $context);
    }
    
    /**
     * Log an AI operation
     */
    public static function ai($message, $context = []) {
        self::log(self::LEVEL_INFO, $message, 'ai', $context);
    }
    
    /**
     * Log an AI error
     */
    public static function ai_error($message, $context = []) {
        self::log(self::LEVEL_ERROR, $message, 'ai', $context);
    }
    
    /**
     * Query logs with filtering
     */
    public static function query($args = []) {
        global $wpdb;
        
        $defaults = [
            'level'    => null,
            'category' => null,
            'search'   => '',
            'per_page' => 50,
            'page'     => 1,
            'order'    => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $table = self::table();
        
        $where = ['1=1'];
        $params = [];
        
        if ($args['level'] !== null) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }
        
        if ($args['category'] !== null) {
            $where[] = 'category = %s';
            $params[] = $args['category'];
        }
        
        if (!empty($args['search'])) {
            $where[] = 'message LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_sql = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at $order LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
        
        // Total count
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $count_params = array_slice($params, 0, -2);
        $total = $count_params
            ? $wpdb->get_var($wpdb->prepare($count_query, ...$count_params))
            : $wpdb->get_var($count_query);
        
        return [
            'items'       => $results ?: [],
            'total'       => intval($total),
            'page'        => $args['page'],
            'per_page'    => $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        ];
    }
    
    /**
     * Get level counts for quick stats
     */
    public static function get_counts() {
        global $wpdb;
        $table = self::table();
        
        $counts = $wpdb->get_results(
            "SELECT level, COUNT(*) as cnt FROM $table GROUP BY level",
            OBJECT_K
        );
        
        return [
            'error'   => isset($counts['error'])   ? intval($counts['error']->cnt)   : 0,
            'warning' => isset($counts['warning']) ? intval($counts['warning']->cnt) : 0,
            'info'    => isset($counts['info'])     ? intval($counts['info']->cnt)     : 0,
            'debug'   => isset($counts['debug'])   ? intval($counts['debug']->cnt)   : 0,
        ];
    }
    
    /**
     * Clear all logs
     */
    public static function clear() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::table());
    }
    
    /**
     * Cleanup old logs (keep last 30 days)
     */
    public static function cleanup($days = 30) {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::table() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
