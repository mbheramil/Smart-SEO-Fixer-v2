<?php
/**
 * Email Digest Class
 * 
 * Sends a weekly email summary of SEO score changes, top issues, and improvements.
 * Opt-in via Settings (ssf_enable_email_digest).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Email_Digest {
    
    const CRON_HOOK = 'ssf_send_email_digest';
    
    /**
     * Schedule the weekly digest cron
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for next Monday at 9 AM site time
            $next_monday = strtotime('next Monday 09:00:00', current_time('timestamp'));
            wp_schedule_event($next_monday, 'weekly', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule the cron
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Main cron handler: generate and send the weekly digest
     */
    public static function send_digest() {
        if (!Smart_SEO_Fixer::get_option('enable_email_digest', false)) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }
        
        $data = self::gather_digest_data();
        
        // Don't send if there's nothing to report
        if (empty($data['score_changes']) && empty($data['top_issues']) && $data['total_analyzed'] === 0) {
            return;
        }
        
        $subject = sprintf(
            '[%s] Weekly SEO Report — Site Score: %d/100',
            get_bloginfo('name'),
            $data['avg_score']
        );
        
        $body = self::build_email_body($data);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($admin_email, $subject, $body, $headers);
        
        // Save snapshot for next week's comparison
        update_option('ssf_digest_last_snapshot', [
            'avg_score'      => $data['avg_score'],
            'total_analyzed' => $data['total_analyzed'],
            'timestamp'      => current_time('mysql'),
        ], false);
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info('Weekly email digest sent', 'general');
        }
    }
    
    /**
     * Gather all data needed for the digest
     */
    private static function gather_digest_data() {
        global $wpdb;
        
        $scores_table = $wpdb->prefix . 'ssf_seo_scores';
        $history_table = $wpdb->prefix . 'ssf_history';
        
        // Current average score
        $avg_score = intval($wpdb->get_var(
            "SELECT ROUND(AVG(score)) FROM $scores_table"
        ));
        
        $total_analyzed = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM $scores_table"
        ));
        
        // Score distribution
        $score_dist = [
            'excellent' => intval($wpdb->get_var("SELECT COUNT(*) FROM $scores_table WHERE score >= 80")),
            'good'      => intval($wpdb->get_var("SELECT COUNT(*) FROM $scores_table WHERE score >= 60 AND score < 80")),
            'fair'      => intval($wpdb->get_var("SELECT COUNT(*) FROM $scores_table WHERE score >= 40 AND score < 60")),
            'poor'      => intval($wpdb->get_var("SELECT COUNT(*) FROM $scores_table WHERE score < 40")),
        ];
        
        // Top 5 worst-scoring posts
        $worst_posts = $wpdb->get_results(
            "SELECT s.post_id, s.score, p.post_title 
             FROM $scores_table s 
             INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID 
             WHERE p.post_status = 'publish'
             ORDER BY s.score ASC 
             LIMIT 5"
        );
        
        // Changes in the past 7 days (from history table)
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $recent_changes = 0;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$history_table'") === $history_table) {
            $recent_changes = intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $history_table WHERE created_at >= %s",
                    $week_ago
                )
            ));
        }
        
        // Compare with last snapshot
        $last_snapshot = get_option('ssf_digest_last_snapshot', []);
        $score_change = 0;
        if (!empty($last_snapshot['avg_score'])) {
            $score_change = $avg_score - intval($last_snapshot['avg_score']);
        }
        
        // Missing SEO count
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        $missing_title = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_seo_title'
                 WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                ...$post_types
            )
        ));
        
        $missing_desc = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_meta_description'
                 WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                ...$post_types
            )
        ));
        
        // Broken links count
        $broken_links = 0;
        $broken_table = $wpdb->prefix . 'ssf_broken_links';
        if ($wpdb->get_var("SHOW TABLES LIKE '$broken_table'") === $broken_table) {
            $broken_links = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM $broken_table WHERE dismissed = 0"
            ));
        }
        
        return [
            'avg_score'       => $avg_score,
            'score_change'    => $score_change,
            'total_analyzed'  => $total_analyzed,
            'score_dist'      => $score_dist,
            'worst_posts'     => $worst_posts,
            'recent_changes'  => $recent_changes,
            'missing_title'   => $missing_title,
            'missing_desc'    => $missing_desc,
            'broken_links'    => $broken_links,
            'top_issues'      => [],
            'score_changes'   => $score_change !== 0 ? [$score_change] : [],
        ];
    }
    
    /**
     * Build the HTML email body
     */
    private static function build_email_body($data) {
        $site_name = get_bloginfo('name');
        $dashboard_url = admin_url('admin.php?page=smart-seo-fixer');
        
        $score_arrow = '';
        if ($data['score_change'] > 0) {
            $score_arrow = '<span style="color:#22c55e;">&#9650; +' . $data['score_change'] . '</span>';
        } elseif ($data['score_change'] < 0) {
            $score_arrow = '<span style="color:#ef4444;">&#9660; ' . $data['score_change'] . '</span>';
        }
        
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:20px;">';
        
        // Header
        $html .= '<div style="background:#1e40af;color:white;padding:24px;border-radius:8px 8px 0 0;text-align:center;">';
        $html .= '<h1 style="margin:0;font-size:22px;">&#128202; Weekly SEO Report</h1>';
        $html .= '<p style="margin:8px 0 0;opacity:0.9;">' . esc_html($site_name) . ' &mdash; ' . date_i18n('M j, Y') . '</p>';
        $html .= '</div>';
        
        // Score card
        $html .= '<div style="background:#f8fafc;padding:24px;border:1px solid #e2e8f0;">';
        $html .= '<div style="text-align:center;margin-bottom:16px;">';
        $html .= '<div style="font-size:48px;font-weight:bold;color:#1e40af;">' . intval($data['avg_score']) . '<span style="font-size:24px;color:#64748b;">/100</span></div>';
        $html .= '<div style="font-size:14px;color:#64748b;">Average SEO Score ' . $score_arrow . '</div>';
        $html .= '</div>';
        
        // Score distribution
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $html .= '<tr><td style="padding:6px;">&#128994; Excellent (80-100)</td><td style="text-align:right;font-weight:bold;">' . $data['score_dist']['excellent'] . '</td></tr>';
        $html .= '<tr><td style="padding:6px;">&#128993; Good (60-79)</td><td style="text-align:right;font-weight:bold;">' . $data['score_dist']['good'] . '</td></tr>';
        $html .= '<tr><td style="padding:6px;">&#128992; Fair (40-59)</td><td style="text-align:right;font-weight:bold;">' . $data['score_dist']['fair'] . '</td></tr>';
        $html .= '<tr><td style="padding:6px;">&#128308; Poor (&lt;40)</td><td style="text-align:right;font-weight:bold;">' . $data['score_dist']['poor'] . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Issues summary
        $html .= '<div style="padding:20px;border:1px solid #e2e8f0;border-top:0;">';
        $html .= '<h2 style="font-size:16px;margin:0 0 12px;">Action Items</h2>';
        $html .= '<ul style="margin:0;padding:0 0 0 20px;font-size:14px;line-height:1.8;">';
        
        if ($data['missing_title'] > 0) {
            $html .= '<li>' . $data['missing_title'] . ' posts missing SEO title</li>';
        }
        if ($data['missing_desc'] > 0) {
            $html .= '<li>' . $data['missing_desc'] . ' posts missing meta description</li>';
        }
        if ($data['broken_links'] > 0) {
            $html .= '<li>' . $data['broken_links'] . ' broken links detected</li>';
        }
        if ($data['missing_title'] === 0 && $data['missing_desc'] === 0 && $data['broken_links'] === 0) {
            $html .= '<li style="color:#22c55e;">No critical issues found!</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        // Worst posts
        if (!empty($data['worst_posts'])) {
            $html .= '<div style="padding:20px;border:1px solid #e2e8f0;border-top:0;">';
            $html .= '<h2 style="font-size:16px;margin:0 0 12px;">Lowest Scoring Posts</h2>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
            
            foreach ($data['worst_posts'] as $post) {
                $edit_url = admin_url('post.php?post=' . $post->post_id . '&action=edit');
                $score_color = $post->score >= 60 ? '#22c55e' : ($post->score >= 40 ? '#f59e0b' : '#ef4444');
                
                $html .= '<tr style="border-bottom:1px solid #f1f5f9;">';
                $html .= '<td style="padding:8px 4px;">' . esc_html(mb_substr($post->post_title, 0, 50)) . '</td>';
                $html .= '<td style="padding:8px 4px;text-align:right;font-weight:bold;color:' . $score_color . ';">' . $post->score . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
            $html .= '</div>';
        }
        
        // Activity
        $html .= '<div style="padding:20px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;">';
        $html .= '<p style="font-size:13px;color:#64748b;margin:0;">';
        $html .= $data['total_analyzed'] . ' posts analyzed &bull; ';
        $html .= $data['recent_changes'] . ' SEO changes this week';
        $html .= '</p>';
        $html .= '<p style="margin:12px 0 0;"><a href="' . esc_url($dashboard_url) . '" style="display:inline-block;background:#1e40af;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-size:14px;">Open Dashboard</a></p>';
        $html .= '</div>';
        
        // Footer
        $html .= '<p style="font-size:12px;color:#94a3b8;text-align:center;margin-top:16px;">';
        $html .= 'Sent by Smart SEO Fixer. <a href="' . esc_url(admin_url('admin.php?page=smart-seo-fixer-settings')) . '" style="color:#94a3b8;">Disable email digest</a>';
        $html .= '</p>';
        
        $html .= '</body></html>';
        
        return $html;
    }
}
