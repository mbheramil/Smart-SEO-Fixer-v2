<?php
/**
 * Change History Tracker
 * 
 * Records every change the plugin makes (AI-generated titles, descriptions,
 * internal links, etc.) and provides undo/rollback capabilities.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_History {
    
    /**
     * Current operation source — set before making changes
     * to tag the history records with the right context.
     */
    private static $current_source = 'manual';
    private static $tracking_enabled = false;
    
    /**
     * Set the current source context for history tracking
     * Call this at the start of an operation: SSF_History::set_source('ai')
     * 
     * @param string $source 'ai', 'manual', 'bulk', 'cron', 'orphan_fix', 'migration'
     */
    public static function set_source($source) {
        self::$current_source = sanitize_key($source);
    }
    
    /**
     * Get the current source
     */
    public static function get_source() {
        return self::$current_source;
    }
    
    /**
     * Enable automatic meta tracking via WordPress hooks.
     * Call once during plugin init.
     */
    public static function enable_tracking() {
        if (self::$tracking_enabled) {
            return;
        }
        self::$tracking_enabled = true;
        
        // Track updates to existing meta
        add_action('update_post_meta', [__CLASS__, 'on_meta_update'], 10, 4);
        
        // Track new meta additions
        add_action('add_post_meta', [__CLASS__, 'on_meta_add'], 10, 3);
    }
    
    /**
     * Hook: fires before post meta is updated
     */
    public static function on_meta_update($meta_id, $post_id, $meta_key, $new_value) {
        if (strpos($meta_key, '_ssf_') !== 0) {
            return;
        }
        $old_value = get_post_meta($post_id, $meta_key, true);
        if ($old_value !== $new_value) {
            self::record_meta($post_id, $meta_key, $old_value, $new_value, self::$current_source);
        }
    }
    
    /**
     * Hook: fires when new post meta is added
     */
    public static function on_meta_add($post_id, $meta_key, $new_value) {
        if (strpos($meta_key, '_ssf_') !== 0) {
            return;
        }
        self::record_meta($post_id, $meta_key, '', $new_value, self::$current_source);
    }
    
    /**
     * Get the history table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_history';
    }
    
    /**
     * Create the history table
     */
    public static function create_table() {
        global $wpdb;
        
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            action_type varchar(50) NOT NULL,
            field_key varchar(100) NOT NULL DEFAULT '',
            old_value longtext,
            new_value longtext,
            source varchar(50) NOT NULL DEFAULT 'manual',
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            reverted tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY reverted (reverted)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Record a change
     * 
     * @param int    $post_id     The post being modified (0 for non-post changes)
     * @param string $action_type Type: 'seo_title', 'meta_description', 'focus_keyword', 
     *                            'internal_link_added', 'internal_link_outgoing', 'schema', 
     *                            'redirect', 'bulk_ai_fix', 'content_modified'
     * @param string $field_key   Meta key or identifier (e.g., '_ssf_seo_title')
     * @param mixed  $old_value   Previous value
     * @param mixed  $new_value   New value
     * @param string $source      'ai', 'manual', 'bulk', 'cron', 'orphan_fix'
     * @return int|false          Insert ID or false on failure
     */
    public static function record($post_id, $action_type, $field_key, $old_value, $new_value, $source = 'ai') {
        global $wpdb;
        
        // Don't record if old and new are identical
        if ($old_value === $new_value) {
            return false;
        }
        
        $result = $wpdb->insert(
            self::table(),
            [
                'post_id'     => absint($post_id),
                'action_type' => sanitize_key($action_type),
                'field_key'   => sanitize_text_field($field_key),
                'old_value'   => is_array($old_value) || is_object($old_value) ? wp_json_encode($old_value) : $old_value,
                'new_value'   => is_array($new_value) || is_object($new_value) ? wp_json_encode($new_value) : $new_value,
                'source'      => sanitize_key($source),
                'user_id'     => get_current_user_id(),
                'reverted'    => 0,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Convenience: record a meta change
     */
    public static function record_meta($post_id, $meta_key, $old_value, $new_value, $source = 'ai') {
        $type_map = [
            '_ssf_seo_title'        => 'seo_title',
            '_ssf_meta_description' => 'meta_description',
            '_ssf_focus_keyword'    => 'focus_keyword',
            '_ssf_canonical_url'    => 'canonical_url',
            '_ssf_robots_meta'      => 'robots_meta',
        ];
        
        $action_type = $type_map[$meta_key] ?? 'meta_change';
        
        return self::record($post_id, $action_type, $meta_key, $old_value, $new_value, $source);
    }
    
    /**
     * Record a content modification (e.g., internal link insertion)
     */
    public static function record_content($post_id, $old_content, $new_content, $source = 'ai', $description = '') {
        return self::record($post_id, 'content_modified', 'post_content', $old_content, $new_content, $source);
    }
    
    /**
     * Undo a specific change by ID
     * 
     * @param int $history_id The history record to revert
     * @return true|WP_Error
     */
    public static function undo($history_id) {
        global $wpdb;
        
        $record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $history_id)
        );
        
        if (!$record) {
            return new WP_Error('not_found', __('History record not found.', 'smart-seo-fixer'));
        }
        
        if ($record->reverted) {
            return new WP_Error('already_reverted', __('This change has already been reverted.', 'smart-seo-fixer'));
        }
        
        $post_id = intval($record->post_id);
        
        // Temporarily disable tracking to avoid recording the revert as a new change
        $was_tracking = self::$tracking_enabled;
        if ($was_tracking) {
            remove_action('update_post_meta', [__CLASS__, 'on_meta_update'], 10);
            remove_action('add_post_meta', [__CLASS__, 'on_meta_add'], 10);
        }
        
        if ($record->field_key === 'post_content') {
            // Revert content change
            $result = wp_update_post([
                'ID'           => $post_id,
                'post_content' => $record->old_value,
            ], true);
            
            if (is_wp_error($result)) {
                // Re-enable tracking before returning
                if ($was_tracking) {
                    add_action('update_post_meta', [__CLASS__, 'on_meta_update'], 10, 4);
                    add_action('add_post_meta', [__CLASS__, 'on_meta_add'], 10, 3);
                }
                return $result;
            }
        } elseif (strpos($record->field_key, '_ssf_') === 0) {
            // Revert meta change
            if ($record->old_value === '' || $record->old_value === null) {
                delete_post_meta($post_id, $record->field_key);
            } else {
                update_post_meta($post_id, $record->field_key, $record->old_value);
            }
        }
        
        // Re-enable tracking
        if ($was_tracking) {
            add_action('update_post_meta', [__CLASS__, 'on_meta_update'], 10, 4);
            add_action('add_post_meta', [__CLASS__, 'on_meta_add'], 10, 3);
        }
        
        // Mark as reverted
        $wpdb->update(
            self::table(),
            ['reverted' => 1],
            ['id' => $history_id],
            ['%d'],
            ['%d']
        );
        
        // Log the undo action
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf(
                'Reverted change #%d on post %d (%s): restored %s',
                $history_id,
                $post_id,
                $record->action_type,
                $record->field_key
            ));
        }
        
        return true;
    }
    
    /**
     * Get history records with filtering
     * 
     * @param array $args Query arguments
     * @return array
     */
    public static function query($args = []) {
        global $wpdb;
        
        $defaults = [
            'post_id'     => null,
            'action_type' => null,
            'source'      => null,
            'reverted'    => null,
            'per_page'    => 50,
            'page'        => 1,
            'orderby'     => 'created_at',
            'order'       => 'DESC',
            'search'      => '',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $table = self::table();
        
        $where = ['1=1'];
        $params = [];
        
        if ($args['post_id'] !== null) {
            $where[] = 'h.post_id = %d';
            $params[] = absint($args['post_id']);
        }
        
        if ($args['action_type'] !== null) {
            $where[] = 'h.action_type = %s';
            $params[] = $args['action_type'];
        }
        
        if ($args['source'] !== null) {
            $where[] = 'h.source = %s';
            $params[] = $args['source'];
        }
        
        if ($args['reverted'] !== null) {
            $where[] = 'h.reverted = %d';
            $params[] = intval($args['reverted']);
        }
        
        if (!empty($args['search'])) {
            $where[] = '(h.new_value LIKE %s OR h.old_value LIKE %s OR p.post_title LIKE %s)';
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }
        
        $where_sql = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $allowed_orderby = ['created_at', 'action_type', 'post_id'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT h.*, p.post_title 
                  FROM $table h
                  LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
                  WHERE $where_sql
                  ORDER BY h.$orderby $order
                  LIMIT %d OFFSET %d";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, ...$params)
        );
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table h 
                        LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID 
                        WHERE $where_sql";
        
        $count_params = array_slice($params, 0, -2);
        $total = $count_params 
            ? $wpdb->get_var($wpdb->prepare($count_query, ...$count_params))
            : $wpdb->get_var($count_query);
        
        return [
            'items'      => $results ?: [],
            'total'      => intval($total),
            'page'       => $args['page'],
            'per_page'   => $args['per_page'],
            'total_pages' => ceil($total / $args['per_page']),
        ];
    }
    
    /**
     * Get history for a specific post
     */
    public static function get_post_history($post_id, $limit = 20) {
        return self::query([
            'post_id'  => $post_id,
            'per_page' => $limit,
        ]);
    }
    
    /**
     * Get summary stats
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_changes,
                COUNT(CASE WHEN reverted = 1 THEN 1 END) as total_reverted,
                COUNT(CASE WHEN source = 'ai' THEN 1 END) as ai_changes,
                COUNT(CASE WHEN source = 'bulk' THEN 1 END) as bulk_changes,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7d
            FROM $table"
        );
        
        return $stats;
    }
    
    /**
     * Cleanup old history (keep last 90 days by default)
     */
    public static function cleanup($days = 90) {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::table() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
