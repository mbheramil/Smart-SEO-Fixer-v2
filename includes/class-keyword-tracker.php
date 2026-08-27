<?php
/**
 * Keyword Tracker
 * 
 * Tracks keyword rankings over time using Google Search Console data.
 * Stores daily snapshots for trend analysis.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Keyword_Tracker {
    
    const CRON_HOOK = 'ssf_track_keywords';
    
    /**
     * Get database table name
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ssf_keyword_tracking';
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
            keyword varchar(500) NOT NULL,
            url varchar(2083) DEFAULT '',
            position float DEFAULT 0,
            clicks int(10) DEFAULT 0,
            impressions int(10) DEFAULT 0,
            ctr float DEFAULT 0,
            tracked_date date NOT NULL,
            source varchar(20) DEFAULT 'gsc',
            PRIMARY KEY (id),
            KEY keyword_date (keyword(191), tracked_date),
            KEY tracked_date (tracked_date),
            KEY position (position)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule daily cron
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
     * Cron handler: fetch keyword data from GSC and store
     */
    public static function cron_track() {
        if (!class_exists('SSF_GSC_Client')) return;
        
        $gsc = new SSF_GSC_Client();
        if (!$gsc->is_connected()) return;
        
        $site_url = Smart_SEO_Fixer::get_option('gsc_site_url', '');
        if (empty($site_url)) return;
        
        // Get yesterday's data (GSC data has ~2 day delay)
        $date = date('Y-m-d', strtotime('-2 days'));
        
        $result = $gsc->get_search_analytics([
            'startDate'  => $date,
            'endDate'    => $date,
            'dimensions' => ['query', 'page'],
            'rowLimit'   => 100,
        ]);
        
        if (is_wp_error($result) || empty($result['rows'])) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::info('Keyword tracking: no data for ' . $date, 'gsc');
            }
            return;
        }
        
        global $wpdb;
        $table = self::table();
        $count = 0;
        
        foreach ($result['rows'] as $row) {
            $keyword = $row['keys'][0] ?? '';
            $url     = $row['keys'][1] ?? '';
            
            if (empty($keyword)) continue;
            
            // Avoid duplicates for same keyword+date
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE keyword = %s AND url = %s AND tracked_date = %s",
                $keyword, $url, $date
            ));
            
            if ($exists) continue;
            
            $wpdb->insert($table, [
                'keyword'      => mb_substr($keyword, 0, 500),
                'url'          => $url,
                'position'     => floatval($row['position'] ?? 0),
                'clicks'       => intval($row['clicks'] ?? 0),
                'impressions'  => intval($row['impressions'] ?? 0),
                'ctr'          => floatval($row['ctr'] ?? 0) * 100,
                'tracked_date' => $date,
                'source'       => 'gsc',
            ]);
            $count++;
        }
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('Keyword tracking: stored %d records for %s', $count, $date), 'gsc');
        }
    }
    
    /**
     * Get tracked keywords with latest metrics
     */
    public static function get_keywords($args = []) {
        global $wpdb;
        $table = self::table();
        
        $defaults = [
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
            'order_by' => 'impressions',
            'order'    => 'DESC',
            'days'     => 30,
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $since = date('Y-m-d', strtotime('-' . intval($args['days']) . ' days'));
        
        $where = "tracked_date >= %s";
        $params = [$since];
        
        if (!empty($args['search'])) {
            $where .= " AND keyword LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $allowed_order = ['impressions', 'clicks', 'position', 'ctr', 'keyword'];
        $order_by = in_array($args['order_by'], $allowed_order) ? $args['order_by'] : 'impressions';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Aggregate by keyword
        $count_sql = "SELECT COUNT(DISTINCT keyword) FROM $table WHERE $where";
        $total = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));
        
        $sql = "SELECT 
                    keyword,
                    ROUND(AVG(position), 1) as avg_position,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions,
                    ROUND(AVG(ctr), 2) as avg_ctr,
                    MIN(tracked_date) as first_tracked,
                    MAX(tracked_date) as last_tracked,
                    COUNT(DISTINCT tracked_date) as data_points,
                    GROUP_CONCAT(DISTINCT url ORDER BY impressions DESC SEPARATOR '||') as urls
                FROM $table 
                WHERE $where
                GROUP BY keyword 
                ORDER BY $order_by $order 
                LIMIT %d OFFSET %d";
        
        $query_params = array_merge($params, [$args['per_page'], $offset]);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));
        
        // Add trend data for each keyword
        foreach ($results as &$row) {
            $row->urls = !empty($row->urls) ? explode('||', $row->urls) : [];
            $row->primary_url = !empty($row->urls) ? $row->urls[0] : '';
        }
        
        return [
            'items'    => $results,
            'total'    => intval($total),
            'pages'    => ceil($total / $args['per_page']),
            'page'     => $args['page'],
            'per_page' => $args['per_page'],
        ];
    }
    
    /**
     * Get position history for a specific keyword (for charting)
     */
    public static function get_keyword_history($keyword, $days = 30) {
        global $wpdb;
        $table = self::table();
        $since = date('Y-m-d', strtotime('-' . intval($days) . ' days'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT tracked_date, ROUND(AVG(position), 1) as position, SUM(clicks) as clicks, SUM(impressions) as impressions
             FROM $table 
             WHERE keyword = %s AND tracked_date >= %s
             GROUP BY tracked_date
             ORDER BY tracked_date ASC",
            $keyword, $since
        ));
    }
    
    /**
     * Get summary stats
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total_keywords' => 0, 'avg_position' => 0, 'total_clicks' => 0, 'total_impressions' => 0, 'top_keyword' => '', 'days_tracked' => 0];
        }
        
        $since_30 = date('Y-m-d', strtotime('-30 days'));
        
        $top = $wpdb->get_row($wpdb->prepare(
            "SELECT keyword, SUM(clicks) as tc FROM $table WHERE tracked_date >= %s GROUP BY keyword ORDER BY tc DESC LIMIT 1",
            $since_30
        ));
        
        return [
            'total_keywords'   => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT keyword) FROM $table WHERE tracked_date >= %s", $since_30))),
            'avg_position'     => round(floatval($wpdb->get_var($wpdb->prepare("SELECT AVG(position) FROM $table WHERE tracked_date >= %s", $since_30))), 1),
            'total_clicks'     => intval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(clicks), 0) FROM $table WHERE tracked_date >= %s", $since_30))),
            'total_impressions'=> intval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(impressions), 0) FROM $table WHERE tracked_date >= %s", $since_30))),
            'top_keyword'      => $top ? $top->keyword : '',
            'days_tracked'     => intval($wpdb->get_var("SELECT COUNT(DISTINCT tracked_date) FROM $table")),
        ];
    }
    
    /**
     * Clean up old data (older than 365 days)
     */
    public static function cleanup() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::table() . " WHERE tracked_date < %s",
            date('Y-m-d', strtotime('-365 days'))
        ));
    }
}
