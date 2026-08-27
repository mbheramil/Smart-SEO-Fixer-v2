<?php
/**
 * Posts List View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';
$issue_filter = isset($_GET['issue']) ? sanitize_text_field(wp_unslash($_GET['issue'])) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$per_page = in_array($per_page, [10, 20, 50, 100, 200]) ? $per_page : 20;
$offset = ($paged - 1) * $per_page;

$table = $wpdb->prefix . 'ssf_seo_scores';
$post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
$post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

// Build query
$where = "WHERE p.post_status = 'publish' AND p.post_type IN ($post_types_str)";

if ($filter === 'good') {
    $where .= " AND s.score >= 80";
} elseif ($filter === 'ok') {
    $where .= " AND s.score >= 60 AND s.score < 80";
} elseif ($filter === 'poor') {
    $where .= " AND s.score < 60";
} elseif ($filter === 'unanalyzed') {
    $where .= " AND s.post_id IS NULL";
}

if ($issue_filter !== '') {
    $issue_like = '%' . $wpdb->esc_like($issue_filter) . '%';
    $where .= $wpdb->prepare(" AND s.issues LIKE %s", $issue_like);
}

// Get total count
$total = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    LEFT JOIN $table s ON p.ID = s.post_id
    $where
");

// Get posts
$posts = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_type, p.post_date, s.score, s.issues, s.last_analyzed
    FROM {$wpdb->posts} p
    LEFT JOIN $table s ON p.ID = s.post_id
    $where
    ORDER BY COALESCE(s.score, 999) ASC, p.post_date DESC
    LIMIT $offset, $per_page
");

$total_pages = ceil($total / $per_page);
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-admin-page"></span>
        <?php esc_html_e('All Posts - SEO Analysis', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <?php if ($issue_filter !== ''): ?>
    <div class="ssf-issue-filter-notice">
        <span class="dashicons dashicons-filter"></span>
        <?php printf(
            esc_html__('Showing pages with issue: %s', 'smart-seo-fixer'),
            '<strong>' . esc_html($issue_filter) . '</strong>'
        ); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts')); ?>" class="ssf-clear-issue-filter">
            &times; <?php esc_html_e('Clear filter', 'smart-seo-fixer'); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="ssf-filters">
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts')); ?>" 
           class="ssf-filter-btn <?php echo empty($filter) && empty($issue_filter) ? 'active' : ''; ?>">
            <?php esc_html_e('All', 'smart-seo-fixer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts&filter=good')); ?>" 
           class="ssf-filter-btn ssf-filter-good <?php echo $filter === 'good' ? 'active' : ''; ?>">
            <?php esc_html_e('Good (80+)', 'smart-seo-fixer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts&filter=ok')); ?>" 
           class="ssf-filter-btn ssf-filter-ok <?php echo $filter === 'ok' ? 'active' : ''; ?>">
            <?php esc_html_e('OK (60-79)', 'smart-seo-fixer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts&filter=poor')); ?>" 
           class="ssf-filter-btn ssf-filter-poor <?php echo $filter === 'poor' ? 'active' : ''; ?>">
            <?php esc_html_e('Needs Work (<60)', 'smart-seo-fixer'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts&filter=unanalyzed')); ?>" 
           class="ssf-filter-btn ssf-filter-unanalyzed <?php echo $filter === 'unanalyzed' ? 'active' : ''; ?>">
            <?php esc_html_e('Not Analyzed', 'smart-seo-fixer'); ?>
        </a>
    </div>
    
    <!-- Bulk Actions -->
    <div class="ssf-bulk-actions">
        <label>
            <input type="checkbox" id="select-all-posts">
            <?php esc_html_e('Select All', 'smart-seo-fixer'); ?>
        </label>
        <button type="button" class="button" id="bulk-analyze-btn">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('Analyze Selected', 'smart-seo-fixer'); ?>
        </button>
        <button type="button" class="button" id="bulk-fix-selected-btn">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('AI Fix Selected', 'smart-seo-fixer'); ?>
        </button>
        
        <span class="ssf-per-page">
            <?php esc_html_e('Show', 'smart-seo-fixer'); ?>
            <select id="per-page-select">
                <option value="10" <?php selected($per_page, 10); ?>>10</option>
                <option value="20" <?php selected($per_page, 20); ?>>20</option>
                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                <option value="200" <?php selected($per_page, 200); ?>>200</option>
            </select>
            <?php esc_html_e('per page', 'smart-seo-fixer'); ?>
        </span>
    </div>
    
    <!-- Posts Table -->
    <table class="wp-list-table widefat fixed striped ssf-posts-table">
        <thead>
            <tr>
                <th class="check-column">
                    <input type="checkbox" id="select-all-header">
                </th>
                <th class="ssf-col-title"><?php esc_html_e('Title', 'smart-seo-fixer'); ?></th>
                <th class="ssf-col-type"><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                <th class="ssf-col-score"><?php esc_html_e('Score', 'smart-seo-fixer'); ?></th>
                <th class="ssf-col-issues"><?php esc_html_e('Issues', 'smart-seo-fixer'); ?></th>
                <th class="ssf-col-analyzed"><?php esc_html_e('Last Analyzed', 'smart-seo-fixer'); ?></th>
                <th class="ssf-col-actions"><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($posts)): ?>
            <tr>
                <td colspan="7" class="ssf-empty-row">
                    <?php esc_html_e('No posts found.', 'smart-seo-fixer'); ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($posts as $post): 
                $issues = $post->issues ? json_decode($post->issues, true) : [];
                $issue_count = is_array($issues) ? count($issues) : 0;
                $score_class = get_score_class($post->score ?? 0);
            ?>
            <tr data-post-id="<?php echo esc_attr($post->ID); ?>">
                <td class="check-column">
                    <input type="checkbox" class="post-checkbox" value="<?php echo esc_attr($post->ID); ?>">
                </td>
                <td class="ssf-col-title">
                    <strong>
                        <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                    </strong>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                <?php esc_html_e('Edit', 'smart-seo-fixer'); ?>
                            </a> |
                        </span>
                        <span class="view">
                            <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank">
                                <?php esc_html_e('View', 'smart-seo-fixer'); ?>
                            </a>
                        </span>
                    </div>
                </td>
                <td class="ssf-col-type">
                    <?php 
                    $post_type_obj = get_post_type_object($post->post_type);
                    echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type); 
                    ?>
                </td>
                <td class="ssf-col-score">
                    <?php if ($post->score !== null): ?>
                    <span class="ssf-score ssf-score-<?php echo esc_attr($score_class); ?>">
                        <?php echo esc_html($post->score); ?>
                    </span>
                    <?php else: ?>
                    <span class="ssf-score ssf-score-none">—</span>
                    <?php endif; ?>
                </td>
                <td class="ssf-col-issues">
                    <?php if ($issue_count > 0): ?>
                    <span class="ssf-issue-count"><?php echo esc_html($issue_count); ?> <?php esc_html_e('issues', 'smart-seo-fixer'); ?></span>
                    <?php elseif ($post->score !== null): ?>
                    <span class="ssf-no-issues"><?php esc_html_e('No issues', 'smart-seo-fixer'); ?></span>
                    <?php else: ?>
                    <span class="ssf-not-analyzed">—</span>
                    <?php endif; ?>
                </td>
                <td class="ssf-col-analyzed">
                    <?php if ($post->last_analyzed): ?>
                    <?php echo esc_html(human_time_diff(strtotime($post->last_analyzed), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'smart-seo-fixer'); ?>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
                <td class="ssf-col-actions">
                    <button type="button" class="button button-small analyze-post-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Analyze', 'smart-seo-fixer'); ?>
                    </button>
                    <?php if ($issue_count > 0): ?>
                    <button type="button" class="button button-small button-primary fix-post-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('AI Fix', 'smart-seo-fixer'); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="ssf-pagination">
        <?php
        $base_url = admin_url('admin.php?page=smart-seo-fixer-posts');
        if ($filter) {
            $base_url .= '&filter=' . $filter;
        }
        if ($issue_filter !== '') {
            $base_url .= '&issue=' . urlencode($issue_filter);
        }
        $base_url .= '&per_page=' . $per_page;
        
        echo paginate_links([
            'base' => $base_url . '%_%',
            'format' => '&paged=%#%',
            'current' => $paged,
            'total' => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]);
        ?>
    </div>
    <?php endif; ?>
    
    <div class="ssf-total-count">
        <?php printf(
            esc_html__('Showing %1$d-%2$d of %3$d posts', 'smart-seo-fixer'),
            $offset + 1,
            min($offset + $per_page, $total),
            $total
        ); ?>
    </div>
    
    <!-- AI Fix Options Modal -->
    <div class="ssf-modal" id="ai-fix-modal" style="display: none;">
        <div class="ssf-modal-content">
            <div class="ssf-modal-header">
                <h3><?php esc_html_e('AI Fix Options', 'smart-seo-fixer'); ?></h3>
                <button type="button" class="ssf-modal-close" id="close-ai-fix-modal">&times;</button>
            </div>
            <div class="ssf-modal-body">
                <p class="ssf-selected-count">
                    <strong id="selected-count">0</strong> <?php esc_html_e('posts selected', 'smart-seo-fixer'); ?>
                </p>
                
                <div class="ssf-option-group">
                    <h4><?php esc_html_e('What should AI generate?', 'smart-seo-fixer'); ?></h4>
                    <label class="ssf-checkbox-option">
                        <input type="checkbox" name="fix_opt_title" checked>
                        <span class="ssf-checkbox-label">
                            <strong><?php esc_html_e('SEO Titles', 'smart-seo-fixer'); ?></strong>
                            <small><?php esc_html_e('Generate optimized titles (50-60 chars)', 'smart-seo-fixer'); ?></small>
                        </span>
                    </label>
                    <label class="ssf-checkbox-option">
                        <input type="checkbox" name="fix_opt_desc" checked>
                        <span class="ssf-checkbox-label">
                            <strong><?php esc_html_e('Meta Descriptions', 'smart-seo-fixer'); ?></strong>
                            <small><?php esc_html_e('Generate compelling descriptions (150-160 chars)', 'smart-seo-fixer'); ?></small>
                        </span>
                    </label>
                    <label class="ssf-checkbox-option">
                        <input type="checkbox" name="fix_opt_keywords">
                        <span class="ssf-checkbox-label">
                            <strong><?php esc_html_e('Focus Keywords', 'smart-seo-fixer'); ?></strong>
                            <small><?php esc_html_e('Suggest optimal focus keywords', 'smart-seo-fixer'); ?></small>
                        </span>
                    </label>
                </div>
                
                <div class="ssf-option-group">
                    <h4><?php esc_html_e('Overwrite mode', 'smart-seo-fixer'); ?></h4>
                    <label class="ssf-radio-option">
                        <input type="radio" name="fix_overwrite" value="missing" checked>
                        <span class="ssf-radio-label">
                            <strong><?php esc_html_e('Only fill empty fields', 'smart-seo-fixer'); ?></strong>
                            <small><?php esc_html_e('Safe - keeps existing content', 'smart-seo-fixer'); ?></small>
                        </span>
                    </label>
                    <label class="ssf-radio-option ssf-option-warning">
                        <input type="radio" name="fix_overwrite" value="overwrite">
                        <span class="ssf-radio-label">
                            <strong><?php esc_html_e('Overwrite all', 'smart-seo-fixer'); ?></strong>
                            <small><?php esc_html_e('Replace existing content with AI-generated', 'smart-seo-fixer'); ?></small>
                        </span>
                    </label>
                </div>
            </div>
            <div class="ssf-modal-footer">
                <button type="button" class="button" id="cancel-ai-fix">
                    <?php esc_html_e('Cancel', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button button-primary" id="start-ai-fix">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Start AI Fix', 'smart-seo-fixer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Progress Modal -->
    <div class="ssf-modal" id="progress-modal" style="display: none;">
        <div class="ssf-modal-content">
            <div class="ssf-modal-header">
                <h3 id="progress-title"><?php esc_html_e('Processing...', 'smart-seo-fixer'); ?></h3>
            </div>
            <div class="ssf-modal-body">
                <div class="ssf-progress-bar">
                    <div class="ssf-progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <p class="ssf-progress-text" id="progress-text">0%</p>
                <div class="ssf-progress-log" id="progress-log"></div>
            </div>
        </div>
    </div>
</div>

<style>
.ssf-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ssf-modal-content {
    background: white;
    border-radius: 8px;
    width: 500px;
    max-width: 90%;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.ssf-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.ssf-modal-header h3 {
    margin: 0;
    font-size: 16px;
}

.ssf-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.ssf-modal-body {
    padding: 20px;
    max-height: 50vh;
    overflow-y: auto;
}

.ssf-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

.ssf-selected-count {
    background: #eff6ff;
    padding: 10px 15px;
    border-radius: 6px;
    color: #1e40af;
    margin-bottom: 20px;
}

.ssf-option-group {
    margin-bottom: 20px;
}

.ssf-option-group h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: #374151;
}

.ssf-checkbox-option,
.ssf-radio-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.ssf-checkbox-option:hover,
.ssf-radio-option:hover {
    background: #f3f4f6;
    border-color: #e5e7eb;
}

.ssf-checkbox-option input:checked + .ssf-checkbox-label strong,
.ssf-radio-option input:checked + .ssf-radio-label strong {
    color: #059669;
}

.ssf-checkbox-label,
.ssf-radio-label {
    display: flex;
    flex-direction: column;
}

.ssf-checkbox-label strong,
.ssf-radio-label strong {
    color: #1f2937;
}

.ssf-checkbox-label small,
.ssf-radio-label small {
    color: #6b7280;
    font-size: 12px;
}

.ssf-option-warning {
    border-color: #fecaca;
    background: #fef2f2;
}

.ssf-option-warning:hover {
    background: #fee2e2;
}

.ssf-progress-bar {
    height: 20px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.ssf-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #059669, #10b981);
    transition: width 0.3s ease;
}

.ssf-progress-text {
    text-align: center;
    font-weight: 600;
    color: #374151;
    margin: 10px 0;
}

.ssf-progress-log {
    max-height: 200px;
    overflow-y: auto;
    background: #1f2937;
    color: #10b981;
    font-family: monospace;
    font-size: 12px;
    padding: 10px;
    border-radius: 6px;
    margin-top: 15px;
}

.ssf-progress-log div {
    padding: 3px 0;
}

.ssf-modal-footer .dashicons {
    vertical-align: middle;
    margin-right: 3px;
}

.ssf-per-page {
    float: right;
    color: #666;
}

.ssf-per-page select {
    margin: 0 5px;
    padding: 4px 8px;
}

.ssf-total-count {
    margin-top: 15px;
    color: #666;
    font-size: 13px;
}

.ssf-bulk-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ssf-bulk-actions .ssf-per-page {
    margin-left: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Per page selector
    $('#per-page-select').on('change', function() {
        var perPage = $(this).val();
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('paged'); // Reset to page 1
        window.location.href = url.toString();
    });
    
    // Select all checkboxes
    $('#select-all-posts, #select-all-header').on('change', function() {
        $('.post-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Get selected post IDs
    function getSelectedPosts() {
        return $('.post-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }
    
    // Analyze single post
    $('.analyze-post-btn').on('click', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text(ssfAdmin.strings.analyzing);
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_analyze_post',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            if (response.success && response.data) {
                var data = response.data;
                var $row = $btn.closest('tr');
                var scoreClass = getScoreClass(data.score);
                
                $row.find('.ssf-col-score').html(
                    '<span class="ssf-score ssf-score-' + scoreClass + '">' + data.score + '</span>'
                );
                
                var issueCount = data.issues ? data.issues.length : 0;
                if (issueCount > 0) {
                    $row.find('.ssf-col-issues').html(
                        '<span class="ssf-issue-count">' + issueCount + ' <?php esc_html_e('issues', 'smart-seo-fixer'); ?></span>'
                    );
                    // Add AI Fix button if not already present
                    if (!$row.find('.fix-post-btn').length) {
                        $row.find('.ssf-col-actions').append(
                            ' <button type="button" class="button button-small button-primary fix-post-btn" data-post-id="' + postId + '"><?php esc_html_e('AI Fix', 'smart-seo-fixer'); ?></button>'
                        );
                    }
                } else {
                    $row.find('.ssf-col-issues').html(
                        '<span class="ssf-no-issues"><?php esc_html_e('No issues', 'smart-seo-fixer'); ?></span>'
                    );
                }
                
                $row.find('.ssf-col-analyzed').text('<?php esc_html_e('Just now', 'smart-seo-fixer'); ?>');
            } else {
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Analysis failed.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).text(originalText);
            alert('<?php esc_html_e('Request failed:', 'smart-seo-fixer'); ?> ' + error);
            console.error('Analyze AJAX Error:', xhr.responseText);
        });
    });
    
    // Fix single post with AI - show modal
    $('.fix-post-btn').on('click', function() {
        var postId = $(this).data('post-id');
        selectedPostIds = [postId];
        $('#selected-count').text('1');
        $('#ai-fix-modal').show();
    });
    
    // Bulk analyze
    $('#bulk-analyze-btn').on('click', function() {
        var postIds = getSelectedPosts();
        
        if (postIds.length === 0) {
            alert('<?php esc_html_e('Please select at least one post.', 'smart-seo-fixer'); ?>');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_bulk_analyze',
            nonce: ssfAdmin.nonce,
            post_ids: postIds
        }, function(response) {
            $btn.prop('disabled', false);
            
            if (response.success) {
                location.reload();
            } else {
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Bulk analysis failed.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false);
            alert('<?php esc_html_e('Request failed:', 'smart-seo-fixer'); ?> ' + error);
        });
    });
    
    // Bulk fix - Open modal with options
    var selectedPostIds = [];
    
    $('#bulk-fix-selected-btn').on('click', function() {
        selectedPostIds = getSelectedPosts();
        
        if (selectedPostIds.length === 0) {
            alert('<?php esc_html_e('Please select at least one post.', 'smart-seo-fixer'); ?>');
            return;
        }
        
        $('#selected-count').text(selectedPostIds.length);
        $('#ai-fix-modal').show();
    });
    
    // Close modal
    $('#close-ai-fix-modal, #cancel-ai-fix').on('click', function() {
        $('#ai-fix-modal').hide();
    });
    
    // Close on background click
    $('#ai-fix-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Start AI Fix
    $('#start-ai-fix').on('click', function() {
        var options = {
            generate_title: $('input[name="fix_opt_title"]').is(':checked'),
            generate_desc: $('input[name="fix_opt_desc"]').is(':checked'),
            generate_keywords: $('input[name="fix_opt_keywords"]').is(':checked'),
            overwrite: $('input[name="fix_overwrite"]:checked').val() === 'overwrite'
        };
        
        if (!options.generate_title && !options.generate_desc && !options.generate_keywords) {
            alert('<?php esc_html_e('Please select at least one option to generate.', 'smart-seo-fixer'); ?>');
            return;
        }
        
        $('#ai-fix-modal').hide();
        startBatchFix(selectedPostIds, options);
    });
    
    function startBatchFix(postIds, options) {
        $('#progress-modal').show();
        $('#progress-title').text('<?php esc_html_e('Fixing SEO with AI...', 'smart-seo-fixer'); ?>');
        $('#progress-fill').css('width', '0%');
        $('#progress-text').text('0%');
        $('#progress-log').html('');
        
        var total = postIds.length;
        var processed = 0;
        var index = 0;
        
        function processNext() {
            if (index >= postIds.length) {
                $('#progress-title').text('<?php esc_html_e('Complete!', 'smart-seo-fixer'); ?>');
                setTimeout(function() {
                    $('#progress-modal').hide();
                    location.reload();
                }, 1500);
                return;
            }
            
            var postId = postIds[index];
            
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_ai_fix_single',
                nonce: ssfAdmin.nonce,
                post_id: postId,
                options: options
            }, function(response) {
                processed++;
                index++;
                
                var percent = Math.round((processed / total) * 100);
                $('#progress-fill').css('width', percent + '%');
                $('#progress-text').text(percent + '% (' + processed + '/' + total + ')');
                
                if (response.success) {
                    $('#progress-log').append('<div>✅ ' + response.data.title + ' - ' + response.data.message + '</div>');
                } else {
                    $('#progress-log').append('<div style="color: #ef4444;">❌ Error: ' + (response.data.message || 'Unknown error') + '</div>');
                }
                
                // Scroll log to bottom
                var logEl = document.getElementById('progress-log');
                logEl.scrollTop = logEl.scrollHeight;
                
                // Process next with small delay
                setTimeout(processNext, 300);
            }).fail(function() {
                processed++;
                index++;
                $('#progress-log').append('<div style="color: #ef4444;">❌ Request failed for post ' + postId + '</div>');
                setTimeout(processNext, 300);
            });
        }
        
        processNext();
    }
    
    function getScoreClass(score) {
        if (score >= 80) return 'good';
        if (score >= 60) return 'ok';
        return 'poor';
    }
});
</script>

<?php
// Helper method for the view
function get_score_class($score) {
    if ($score >= 80) return 'good';
    if ($score >= 60) return 'ok';
    return 'poor';
}
?>

