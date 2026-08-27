<?php
/**
 * Performance Profiler
 * 
 * Tracks plugin load time, database query count, and memory usage.
 * Provides an admin dashboard with trend data.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Performance {
    
    /**
     * Profile start time
     */
    private static $start_time = 0;
    private static $start_memory = 0;
    private static $start_queries = 0;
    
    /**
     * Begin profiling (called at plugin load)
     */
    public static function start() {
        self::$start_time = microtime(true);
        self::$start_memory = memory_get_usage();
        self::$start_queries = get_num_queries();
    }
    
    /**
     * End profiling and record (called at wp_loaded or shutdown)
     */
    public static function end() {
        if (self::$start_time === 0) return;
        
        $duration = round((microtime(true) - self::$start_time) * 1000, 2); // ms
        $memory = round((memory_get_usage() - self::$start_memory) / 1024, 1); // KB
        $queries = get_num_queries() - self::$start_queries;
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2); // MB
        
        // Store as transient (refreshed each request, no DB bloat)
        set_transient('ssf_perf_latest', [
            'duration'    => $duration,
            'memory_kb'   => $memory,
            'queries'     => $queries,
            'peak_mb'     => $peak_memory,
            'timestamp'   => current_time('mysql'),
            'is_admin'    => is_admin(),
            'page'        => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
        ], HOUR_IN_SECONDS);
        
        // Append to rolling history (last 100 samples)
        self::record_sample($duration, $memory, $queries, $peak_memory);
    }
    
    /**
     * Record a performance sample to history
     */
    private static function record_sample($duration, $memory, $queries, $peak) {
        $history = get_option('ssf_perf_history', []);
        
        $history[] = [
            'time'     => current_time('U'),
            'duration' => $duration,
            'memory'   => $memory,
            'queries'  => $queries,
            'peak'     => $peak,
            'admin'    => is_admin() ? 1 : 0,
        ];
        
        // Keep last 200 samples
        if (count($history) > 200) {
            $history = array_slice($history, -200);
        }
        
        update_option('ssf_perf_history', $history, false); // autoload=false
    }
    
    /**
     * Get the latest performance snapshot
     */
    public static function get_latest() {
        return get_transient('ssf_perf_latest') ?: [
            'duration' => 0, 'memory_kb' => 0, 'queries' => 0, 'peak_mb' => 0,
            'timestamp' => '', 'is_admin' => false, 'page' => '',
        ];
    }
    
    /**
     * Get performance history for charting
     */
    public static function get_history($limit = 50) {
        $history = get_option('ssf_perf_history', []);
        return array_slice($history, -$limit);
    }
    
    /**
     * Get average stats
     */
    public static function get_averages() {
        $history = get_option('ssf_perf_history', []);
        
        if (empty($history)) {
            return ['avg_duration' => 0, 'avg_memory' => 0, 'avg_queries' => 0, 'samples' => 0, 'avg_peak' => 0];
        }
        
        $count = count($history);
        $sum_duration = 0;
        $sum_memory = 0;
        $sum_queries = 0;
        $sum_peak = 0;
        
        foreach ($history as $sample) {
            $sum_duration += $sample['duration'];
            $sum_memory += $sample['memory'];
            $sum_queries += $sample['queries'];
            $sum_peak += $sample['peak'];
        }
        
        return [
            'avg_duration' => round($sum_duration / $count, 1),
            'avg_memory'   => round($sum_memory / $count, 1),
            'avg_queries'  => round($sum_queries / $count, 1),
            'avg_peak'     => round($sum_peak / $count, 2),
            'samples'      => $count,
        ];
    }
    
    /**
     * Get environment info
     */
    public static function get_environment() {
        global $wpdb;
        
        return [
            'php_version'    => PHP_VERSION,
            'wp_version'     => get_bloginfo('version'),
            'plugin_version' => defined('SSF_VERSION') ? SSF_VERSION : 'unknown',
            'mysql_version'  => $wpdb->get_var('SELECT VERSION()'),
            'server'         => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field($_SERVER['SERVER_SOFTWARE']) : 'Unknown',
            'memory_limit'   => ini_get('memory_limit'),
            'max_exec_time'  => ini_get('max_execution_time'),
            'php_sapi'       => php_sapi_name(),
            'active_plugins' => count(get_option('active_plugins', [])),
            'db_tables'      => self::count_plugin_tables(),
            'total_posts'    => wp_count_posts()->publish,
        ];
    }
    
    /**
     * Count plugin-specific database tables and their sizes
     */
    private static function count_plugin_tables() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'ssf_';
        
        $tables = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT table_name, 
                        ROUND(data_length / 1024, 1) as data_kb,
                        ROUND(index_length / 1024, 1) as index_kb,
                        table_rows
                 FROM information_schema.tables 
                 WHERE table_schema = %s AND table_name LIKE %s",
                DB_NAME, $prefix . '%'
            )
        );
        
        return $tables ?: [];
    }
    
    /**
     * Clear performance history
     */
    public static function clear_history() {
        delete_option('ssf_perf_history');
        delete_transient('ssf_perf_latest');
        delete_option('ssf_cwv_data');
    }
    
    // =========================================================================
    // Core Web Vitals (CWV) — Real User Monitoring
    // =========================================================================
    
    /**
     * Enqueue the CWV measurement script on the frontend.
     * Uses the web-vitals library via PerformanceObserver to measure LCP, CLS, INP.
     */
    public static function enqueue_cwv_script() {
        if (is_admin() || !Smart_SEO_Fixer::get_option('enable_cwv_tracking', false)) {
            return;
        }
        
        // Sample rate: only collect from 10% of pageviews to minimize overhead
        if (wp_rand(1, 10) > 1) {
            return;
        }
        
        add_action('wp_footer', [__CLASS__, 'output_cwv_inline_script'], 999);
    }
    
    /**
     * Output inline CWV measurement script (no external dependency).
     * Uses PerformanceObserver API available in modern browsers.
     */
    public static function output_cwv_inline_script() {
        $beacon_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('ssf_cwv_beacon');
        ?>
        <script>
        (function(){
            if (!('PerformanceObserver' in window)) return;
            var cwv = {lcp:0, cls:0, inp:0, url: location.pathname};
            
            // LCP
            try {
                new PerformanceObserver(function(l){
                    var e = l.getEntries();
                    if(e.length) cwv.lcp = Math.round(e[e.length-1].startTime);
                }).observe({type:'largest-contentful-paint', buffered:true});
            } catch(e){}
            
            // CLS
            try {
                var clsVal = 0;
                new PerformanceObserver(function(l){
                    l.getEntries().forEach(function(e){
                        if(!e.hadRecentInput) clsVal += e.value;
                    });
                    cwv.cls = Math.round(clsVal * 1000) / 1000;
                }).observe({type:'layout-shift', buffered:true});
            } catch(e){}
            
            // INP (Interaction to Next Paint)
            try {
                var inpVal = 0;
                new PerformanceObserver(function(l){
                    l.getEntries().forEach(function(e){
                        if(e.duration > inpVal) inpVal = e.duration;
                    });
                    cwv.inp = Math.round(inpVal);
                }).observe({type:'event', buffered:true, durationThreshold:16});
            } catch(e){}
            
            // Send on page unload
            function send(){
                if(cwv.lcp === 0 && cwv.cls === 0 && cwv.inp === 0) return;
                var data = 'action=ssf_cwv_beacon&nonce=<?php echo esc_js($nonce); ?>'
                    + '&lcp=' + cwv.lcp + '&cls=' + cwv.cls + '&inp=' + cwv.inp
                    + '&url=' + encodeURIComponent(cwv.url);
                if(navigator.sendBeacon){
                    navigator.sendBeacon('<?php echo esc_js($beacon_url); ?>', data);
                }
            }
            
            if('onvisibilitychange' in document){
                document.addEventListener('visibilitychange', function(){
                    if(document.visibilityState==='hidden') send();
                });
            } else {
                window.addEventListener('pagehide', send);
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX handler for CWV beacon (called from frontend).
     * Stores aggregated CWV data.
     */
    public static function handle_cwv_beacon() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ssf_cwv_beacon')) {
            wp_die('', '', ['response' => 204]);
        }
        
        $lcp = floatval($_POST['lcp'] ?? 0);
        $cls = floatval($_POST['cls'] ?? 0);
        $inp = floatval($_POST['inp'] ?? 0);
        $url = sanitize_text_field($_POST['url'] ?? '');
        
        if ($lcp <= 0 && $cls <= 0 && $inp <= 0) {
            wp_die('', '', ['response' => 204]);
        }
        
        $data = get_option('ssf_cwv_data', [
            'samples' => [],
            'summary' => ['lcp_avg' => 0, 'cls_avg' => 0, 'inp_avg' => 0, 'count' => 0],
        ]);
        
        // Add sample
        $data['samples'][] = [
            'lcp'  => $lcp,
            'cls'  => $cls,
            'inp'  => $inp,
            'url'  => $url,
            'time' => current_time('U'),
        ];
        
        // Keep last 500 samples
        if (count($data['samples']) > 500) {
            $data['samples'] = array_slice($data['samples'], -500);
        }
        
        // Recalculate summary (p75 values — what Google uses)
        $lcp_vals = array_column($data['samples'], 'lcp');
        $cls_vals = array_column($data['samples'], 'cls');
        $inp_vals = array_column($data['samples'], 'inp');
        
        sort($lcp_vals);
        sort($cls_vals);
        sort($inp_vals);
        
        $count = count($data['samples']);
        $p75_idx = max(0, (int) ceil($count * 0.75) - 1);
        
        $data['summary'] = [
            'lcp_p75' => $lcp_vals[$p75_idx] ?? 0,
            'cls_p75' => $cls_vals[$p75_idx] ?? 0,
            'inp_p75' => $inp_vals[$p75_idx] ?? 0,
            'lcp_avg' => round(array_sum($lcp_vals) / max(1, $count)),
            'cls_avg' => round(array_sum($cls_vals) / max(1, $count), 3),
            'inp_avg' => round(array_sum($inp_vals) / max(1, $count)),
            'count'   => $count,
            'last_updated' => current_time('mysql'),
        ];
        
        // Grade CWV (Google thresholds)
        $data['summary']['lcp_grade'] = $data['summary']['lcp_p75'] <= 2500 ? 'good' : ($data['summary']['lcp_p75'] <= 4000 ? 'needs-improvement' : 'poor');
        $data['summary']['cls_grade'] = $data['summary']['cls_p75'] <= 0.1 ? 'good' : ($data['summary']['cls_p75'] <= 0.25 ? 'needs-improvement' : 'poor');
        $data['summary']['inp_grade'] = $data['summary']['inp_p75'] <= 200 ? 'good' : ($data['summary']['inp_p75'] <= 500 ? 'needs-improvement' : 'poor');
        
        update_option('ssf_cwv_data', $data, false);
        
        wp_die('', '', ['response' => 204]);
    }
    
    /**
     * Get Core Web Vitals summary
     */
    public static function get_cwv_data() {
        return get_option('ssf_cwv_data', [
            'samples' => [],
            'summary' => [
                'lcp_p75' => 0, 'cls_p75' => 0, 'inp_p75' => 0,
                'lcp_avg' => 0, 'cls_avg' => 0, 'inp_avg' => 0,
                'count' => 0, 'last_updated' => '',
                'lcp_grade' => 'unknown', 'cls_grade' => 'unknown', 'inp_grade' => 'unknown',
            ],
        ]);
    }
    
    /**
     * Get CWV data for per-page breakdown (top slowest pages)
     */
    public static function get_cwv_by_page($limit = 10) {
        $data = self::get_cwv_data();
        $pages = [];
        
        foreach ($data['samples'] as $sample) {
            $url = $sample['url'];
            if (!isset($pages[$url])) {
                $pages[$url] = ['lcp' => [], 'cls' => [], 'inp' => [], 'count' => 0];
            }
            $pages[$url]['lcp'][] = $sample['lcp'];
            $pages[$url]['cls'][] = $sample['cls'];
            $pages[$url]['inp'][] = $sample['inp'];
            $pages[$url]['count']++;
        }
        
        $result = [];
        foreach ($pages as $url => $metrics) {
            $result[] = [
                'url'   => $url,
                'count' => $metrics['count'],
                'lcp_avg' => round(array_sum($metrics['lcp']) / max(1, $metrics['count'])),
                'cls_avg' => round(array_sum($metrics['cls']) / max(1, $metrics['count']), 3),
                'inp_avg' => round(array_sum($metrics['inp']) / max(1, $metrics['count'])),
            ];
        }
        
        // Sort by worst LCP
        usort($result, function($a, $b) { return $b['lcp_avg'] - $a['lcp_avg']; });
        
        return array_slice($result, 0, $limit);
    }
}
