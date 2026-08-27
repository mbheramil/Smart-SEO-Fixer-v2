<?php
/**
 * SEO Analyzer — Dedicated Page
 * Analyze and re-analyze all posts, view scores and status.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ssf_seo_scores';
$post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
$post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($post_types_str)");

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
$analyzed_count = 0;
if ($table_exists) {
    $analyzed_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str)");
}
$unanalyzed_count = $total_posts - $analyzed_count;

$avg_score = 0;
$good_count = 0;
$ok_count = 0;
$poor_count = 0;
if ($table_exists && $analyzed_count > 0) {
    $avg_score = (int) $wpdb->get_var("SELECT ROUND(AVG(score)) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str)");
    $good_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str) AND s.score >= 80");
    $ok_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str) AND s.score >= 60 AND s.score < 80");
    $poor_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str) AND s.score < 60");
}

// Get scored posts for the table (paginated)
$per_page = 30;
$paged = max(1, intval($_GET['paged'] ?? 1));
$offset = ($paged - 1) * $per_page;
$filter = sanitize_text_field($_GET['score_filter'] ?? 'all');

$where_score = '';
if ($filter === 'good') $where_score = 'AND s.score >= 80';
elseif ($filter === 'ok') $where_score = 'AND s.score >= 60 AND s.score < 80';
elseif ($filter === 'poor') $where_score = 'AND s.score < 60';
elseif ($filter === 'unanalyzed') $where_score = 'AND s.post_id IS NULL';

if ($filter === 'unanalyzed') {
    $filtered_total = $unanalyzed_count;
    $scored_posts = $table_exists ? $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_date
        FROM {$wpdb->posts} p
        LEFT JOIN $table s ON p.ID = s.post_id
        WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str)
        AND s.post_id IS NULL
        ORDER BY p.post_date DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset)) : $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title, post_type, post_date
        FROM {$wpdb->posts}
        WHERE post_status = 'publish' AND post_type IN ($post_types_str)
        ORDER BY post_date DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
} else {
    if ($table_exists) {
        $count_sql = "SELECT COUNT(*) FROM $table s INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str) $where_score";
        $filtered_total = (int) $wpdb->get_var($count_sql);
        $scored_posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_type, p.post_date, s.score
            FROM {$wpdb->posts} p
            INNER JOIN $table s ON p.ID = s.post_id
            WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str) $where_score
            ORDER BY s.score ASC, p.post_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
    } else {
        $filtered_total = 0;
        $scored_posts = [];
    }
}
$total_pages = ceil($filtered_total / $per_page);
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-search"></span>
        <?php esc_html_e('SEO Analyzer', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>

    <p class="ssf-page-desc"><?php esc_html_e('Analyze your posts to generate SEO scores and identify issues.', 'smart-seo-fixer'); ?></p>

    <!-- Stats -->
    <div class="ssf-analyzer-stats">
        <div class="ssf-astat">
            <span class="ssf-astat-num"><?php echo esc_html($total_posts); ?></span>
            <span class="ssf-astat-label"><?php esc_html_e('Total Posts', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-astat">
            <span class="ssf-astat-num"><?php echo esc_html($analyzed_count); ?></span>
            <span class="ssf-astat-label"><?php esc_html_e('Analyzed', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-astat ssf-astat-highlight">
            <span class="ssf-astat-num"><?php echo esc_html($unanalyzed_count); ?></span>
            <span class="ssf-astat-label"><?php esc_html_e('Not Analyzed', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-astat">
            <span class="ssf-astat-num"><?php echo esc_html($avg_score); ?></span>
            <span class="ssf-astat-label"><?php esc_html_e('Avg Score', 'smart-seo-fixer'); ?></span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="ssf-analyzer-actions">
        <button type="button" class="button button-primary button-hero" id="analyze-unanalyzed-btn" <?php echo $unanalyzed_count === 0 ? 'disabled' : ''; ?>>
            <span class="dashicons dashicons-search" style="margin-top:4px;"></span>
            <?php printf(esc_html__('Analyze %d Unanalyzed Posts', 'smart-seo-fixer'), $unanalyzed_count); ?>
        </button>
        <button type="button" class="button button-hero" id="reanalyze-all-btn">
            <span class="dashicons dashicons-update" style="margin-top:4px;"></span>
            <?php printf(esc_html__('Re-Analyze All %d Posts', 'smart-seo-fixer'), $total_posts); ?>
        </button>
    </div>

    <!-- Progress (hidden until running) -->
    <div id="analyzer-progress" style="display:none;">
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2 id="analyzer-progress-title">
                    <span class="dashicons dashicons-update ssf-spin"></span>
                    <?php esc_html_e('Analyzing Posts...', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <div class="ssf-progress-bar">
                    <div class="ssf-progress-fill" id="analyzer-progress-fill" style="width:0%"></div>
                </div>
                <p class="ssf-progress-text" id="analyzer-progress-text">0%</p>
                <div class="ssf-progress-log" id="analyzer-progress-log"></div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="ssf-filter-tabs" id="analyzer-table-section">
        <?php
        $base_url = admin_url('admin.php?page=smart-seo-fixer-analyzer');
        $tabs = [
            'all' => __('All Analyzed', 'smart-seo-fixer') . " ($analyzed_count)",
            'poor' => __('Poor <60', 'smart-seo-fixer') . " ($poor_count)",
            'ok' => __('OK 60-79', 'smart-seo-fixer') . " ($ok_count)",
            'good' => __('Good 80+', 'smart-seo-fixer') . " ($good_count)",
            'unanalyzed' => __('Unanalyzed', 'smart-seo-fixer') . " ($unanalyzed_count)",
        ];
        foreach ($tabs as $key => $label): ?>
            <a href="<?php echo esc_url(add_query_arg('score_filter', $key, $base_url)); ?>" class="ssf-tab <?php echo $filter === $key ? 'ssf-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Posts Table -->
    <div class="ssf-card">
        <div class="ssf-card-body" style="padding:0;">
            <table class="ssf-table">
                <thead>
                    <tr>
                        <th style="width:40%;"><?php esc_html_e('Post Title', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Score', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Meta Desc', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Keyword', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Published', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scored_posts)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#9ca3af;"><?php esc_html_e('No posts found for this filter.', 'smart-seo-fixer'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($scored_posts as $sp):
                        $score = isset($sp->score) ? (int)$sp->score : null;
                        $sc = $score === null ? 'na' : ($score >= 80 ? 'good' : ($score >= 60 ? 'ok' : 'poor'));
                        $has_title = !empty(trim(get_post_meta($sp->ID, '_ssf_seo_title', true)));
                        $has_desc = !empty(trim(get_post_meta($sp->ID, '_ssf_meta_description', true)));
                        $has_kw = !empty(trim(get_post_meta($sp->ID, '_ssf_focus_keyword', true)));
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url(admin_url('post.php?action=edit&post=' . $sp->ID)); ?>"><?php echo esc_html($sp->post_title); ?></a></td>
                        <td><span class="ssf-type-badge"><?php echo esc_html($sp->post_type); ?></span></td>
                        <td>
                            <?php if ($score !== null): ?>
                            <span class="ssf-score-pill ssf-score-<?php echo esc_attr($sc); ?>"><?php echo esc_html($score); ?></span>
                            <?php else: ?>
                            <span class="ssf-score-pill ssf-score-na">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $has_title ? '<span class="ssf-check">✓</span>' : '<span class="ssf-miss">✗</span>'; ?></td>
                        <td><?php echo $has_desc ? '<span class="ssf-check">✓</span>' : '<span class="ssf-miss">✗</span>'; ?></td>
                        <td><?php echo $has_kw ? '<span class="ssf-check">✓</span>' : '<span class="ssf-miss">✗</span>'; ?></td>
                        <td style="color:#9ca3af; font-size:12px;"><?php echo esc_html(date('M j, Y', strtotime($sp->post_date))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="ssf-pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i === $paged): ?>
                <span class="ssf-page-current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo esc_url(add_query_arg(['paged' => $i, 'score_filter' => $filter], $base_url)); ?>" class="ssf-page-link"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.ssf-page-desc { color: #6b7280; font-size: 14px; margin: -10px 0 20px; }
.ssf-spin { animation: ssf-spin 1s linear infinite; }
@keyframes ssf-spin { 100% { transform: rotate(360deg); } }

/* Stats row */
.ssf-analyzer-stats { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.ssf-astat { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 24px; text-align: center; min-width: 120px; }
.ssf-astat-num { display: block; font-size: 28px; font-weight: 700; color: #1f2937; }
.ssf-astat-label { display: block; font-size: 12px; color: #6b7280; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.ssf-astat-highlight { border-color: #f59e0b; background: #fffbeb; }
.ssf-astat-highlight .ssf-astat-num { color: #d97706; }

/* Actions */
.ssf-analyzer-actions { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.ssf-analyzer-actions .button-hero { display: flex !important; align-items: center; gap: 6px; }

/* Filter tabs */
.ssf-filter-tabs { display: flex; gap: 4px; margin-bottom: 0; background: #f9fafb; border: 1px solid #e5e7eb; border-bottom: none; border-radius: 8px 8px 0 0; padding: 8px 8px 0; }
.ssf-tab { padding: 8px 16px; font-size: 13px; color: #6b7280; text-decoration: none; border-radius: 6px 6px 0 0; transition: all 0.2s; }
.ssf-tab:hover { color: #1f2937; background: #fff; }
.ssf-tab-active { background: #fff; color: #2563eb; font-weight: 600; border: 1px solid #e5e7eb; border-bottom: 1px solid #fff; margin-bottom: -1px; }

/* Table */
.ssf-table { width: 100%; border-collapse: collapse; }
.ssf-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 12px 14px; border-bottom: 2px solid #e5e7eb; background: #f9fafb; }
.ssf-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
.ssf-table tr:hover td { background: #f9fafb; }
.ssf-table td a { color: #1f2937; font-weight: 600; text-decoration: none; }
.ssf-table td a:hover { color: #2563eb; }
.ssf-type-badge { font-size: 11px; background: #f3f4f6; color: #6b7280; padding: 2px 8px; border-radius: 4px; }
.ssf-score-pill { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; }
.ssf-score-good { background: #d1fae5; color: #065f46; }
.ssf-score-ok { background: #fef3c7; color: #92400e; }
.ssf-score-poor { background: #fee2e2; color: #991b1b; }
.ssf-score-na { background: #f3f4f6; color: #9ca3af; }
.ssf-check { color: #059669; font-weight: 700; }
.ssf-miss { color: #dc2626; font-weight: 700; }

/* Pagination */
.ssf-pagination { display: flex; gap: 4px; margin-top: 16px; justify-content: center; }
.ssf-page-link, .ssf-page-current { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; font-size: 13px; text-decoration: none; }
.ssf-page-link { background: #fff; border: 1px solid #e5e7eb; color: #374151; }
.ssf-page-link:hover { background: #f3f4f6; }
.ssf-page-current { background: #2563eb; color: #fff; font-weight: 700; }
</style>

<script>
jQuery(document).ready(function($) {
    function esc(t) { return $('<span>').text(t || '').html(); }

    function runAnalyzer(mode) {
        $('#analyzer-progress').show();
        $('html, body').animate({ scrollTop: $('#analyzer-progress').offset().top - 40 }, 300);

        var offset = 0, batchSize = 5, processed = 0, total = 0;

        function next() {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_bulk_analyze',
                nonce: ssfAdmin.nonce,
                offset: offset,
                batch_size: batchSize,
                analyze_mode: mode
            }, function(response) {
                if (response.success) {
                    processed += response.data.processed || 0;
                    total = response.data.total || total;
                    var pct = total > 0 ? Math.round((processed / total) * 100) : 100;
                    $('#analyzer-progress-fill').css('width', pct + '%');
                    $('#analyzer-progress-text').text(pct + '% (' + processed + '/' + total + ')');

                    if (response.data.log) {
                        response.data.log.forEach(function(e) {
                            $('#analyzer-progress-log').append('<div>' + e + '</div>');
                        });
                        var el = document.getElementById('analyzer-progress-log');
                        el.scrollTop = el.scrollHeight;
                    }

                    if (response.data.done) {
                        $('#analyzer-progress-title').html('<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span> <?php echo esc_js(__('Complete! Refreshing...', 'smart-seo-fixer')); ?>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        offset += batchSize;
                        setTimeout(next, 300);
                    }
                } else {
                    $('#analyzer-progress-log').append('<div style="color:#dc2626;">Error: ' + esc((response.data && response.data.message) || 'Unknown') + '</div>');
                }
            }).fail(function() {
                $('#analyzer-progress-log').append('<div style="color:#dc2626;"><?php echo esc_js(__('Request failed. Retrying...', 'smart-seo-fixer')); ?></div>');
                setTimeout(next, 2000);
            });
        }

        next();
    }

    $('#analyze-unanalyzed-btn').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Analyze all unanalyzed posts?', 'smart-seo-fixer')); ?>')) return;
        $(this).prop('disabled', true);
        runAnalyzer('unanalyzed');
    });

    $('#reanalyze-all-btn').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Re-analyze ALL published posts? This may take a while.', 'smart-seo-fixer')); ?>')) return;
        $(this).prop('disabled', true);
        runAnalyzer('all');
    });
});
</script>
