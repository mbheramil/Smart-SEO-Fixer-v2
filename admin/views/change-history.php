<?php
/**
 * Change History View
 * Shows all AI/manual changes with undo capability.
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = class_exists('SSF_History') ? SSF_History::get_stats() : null;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-backup"></span>
        <?php esc_html_e('Change History', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <!-- Stats Cards -->
    <?php if ($stats): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #1e40af;"><?php echo intval($stats->total_changes); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Total Changes', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #059669;"><?php echo intval($stats->ai_changes); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('AI Changes', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #7c3aed;"><?php echo intval($stats->bulk_changes); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Bulk Changes', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #dc2626;"><?php echo intval($stats->total_reverted); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Reverted', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #ea580c;"><?php echo intval($stats->last_24h); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Last 24h', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #0891b2;"><?php echo intval($stats->last_7d); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Last 7 Days', 'smart-seo-fixer'); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="ssf-card" style="margin-bottom: 24px;">
        <div class="ssf-card-body" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div>
                <label for="ssf-history-type" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Change Type', 'smart-seo-fixer'); ?>
                </label>
                <select id="ssf-history-type" class="ssf-select">
                    <option value=""><?php esc_html_e('All Types', 'smart-seo-fixer'); ?></option>
                    <option value="seo_title"><?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?></option>
                    <option value="meta_description"><?php esc_html_e('Meta Description', 'smart-seo-fixer'); ?></option>
                    <option value="focus_keyword"><?php esc_html_e('Focus Keyword', 'smart-seo-fixer'); ?></option>
                    <option value="content_modified"><?php esc_html_e('Content Modified', 'smart-seo-fixer'); ?></option>
                    <option value="meta_change"><?php esc_html_e('Other Meta', 'smart-seo-fixer'); ?></option>
                </select>
            </div>
            <div>
                <label for="ssf-history-source" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Source', 'smart-seo-fixer'); ?>
                </label>
                <select id="ssf-history-source" class="ssf-select">
                    <option value=""><?php esc_html_e('All Sources', 'smart-seo-fixer'); ?></option>
                    <option value="ai"><?php esc_html_e('AI Generated', 'smart-seo-fixer'); ?></option>
                    <option value="manual"><?php esc_html_e('Manual', 'smart-seo-fixer'); ?></option>
                    <option value="bulk"><?php esc_html_e('Bulk Operation', 'smart-seo-fixer'); ?></option>
                    <option value="cron"><?php esc_html_e('Background Cron', 'smart-seo-fixer'); ?></option>
                    <option value="orphan_fix"><?php esc_html_e('Orphan Fix', 'smart-seo-fixer'); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="ssf-history-search" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Search', 'smart-seo-fixer'); ?>
                </label>
                <input type="text" id="ssf-history-search" class="regular-text" placeholder="<?php esc_attr_e('Search by post title or value...', 'smart-seo-fixer'); ?>" style="width: 100%;">
            </div>
            <div>
                <button type="button" class="button button-primary" id="ssf-history-filter">
                    <span class="dashicons dashicons-filter" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Filter', 'smart-seo-fixer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- History Table -->
    <div class="ssf-card">
        <div class="ssf-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php esc_html_e('Recent Changes', 'smart-seo-fixer'); ?></h2>
            <span id="ssf-history-total" style="color: #64748b; font-size: 13px;"></span>
        </div>
        <div class="ssf-card-body" style="padding: 0;">
            <div id="ssf-history-loading" style="text-align: center; padding: 40px; display: none;">
                <span class="spinner is-active" style="float: none;"></span>
                <p style="color: #64748b;"><?php esc_html_e('Loading history...', 'smart-seo-fixer'); ?></p>
            </div>
            
            <div id="ssf-history-empty" style="text-align: center; padding: 40px; display: none;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #10b981;"></span>
                <p style="color: #64748b; margin-top: 12px;"><?php esc_html_e('No changes recorded yet. Changes will appear here as you use the plugin.', 'smart-seo-fixer'); ?></p>
            </div>
            
            <table class="wp-list-table widefat striped" id="ssf-history-table" style="display: none;">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php esc_html_e('Date', 'smart-seo-fixer'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Page', 'smart-seo-fixer'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Change', 'smart-seo-fixer'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Source', 'smart-seo-fixer'); ?></th>
                        <th style="width: 100px; text-align: right;"><?php esc_html_e('Action', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody id="ssf-history-body"></tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div id="ssf-history-pagination" style="display: none; padding: 16px; border-top: 1px solid #e2e8f0; text-align: center;">
            <button type="button" class="button" id="ssf-history-prev" disabled>
                &laquo; <?php esc_html_e('Previous', 'smart-seo-fixer'); ?>
            </button>
            <span id="ssf-history-page-info" style="margin: 0 16px; color: #64748b;"></span>
            <button type="button" class="button" id="ssf-history-next" disabled>
                <?php esc_html_e('Next', 'smart-seo-fixer'); ?> &raquo;
            </button>
        </div>
    </div>
</div>

<style>
.ssf-select {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    min-width: 140px;
}
.ssf-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.ssf-badge-ai { background: #dbeafe; color: #1d4ed8; }
.ssf-badge-manual { background: #f3e8ff; color: #7c3aed; }
.ssf-badge-bulk { background: #fef3c7; color: #b45309; }
.ssf-badge-cron { background: #e0e7ff; color: #4338ca; }
.ssf-badge-orphan_fix { background: #dcfce7; color: #15803d; }
.ssf-badge-migration { background: #f1f5f9; color: #475569; }
.ssf-badge-type { background: #f1f5f9; color: #334155; }
.ssf-badge-reverted { background: #fee2e2; color: #dc2626; }

.ssf-diff-old {
    background: #fef2f2;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: block;
    margin-bottom: 4px;
    word-break: break-word;
    max-height: 60px;
    overflow: hidden;
}
.ssf-diff-new {
    background: #f0fdf4;
    color: #166534;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: block;
    word-break: break-word;
    max-height: 60px;
    overflow: hidden;
}
.ssf-diff-content {
    font-size: 11px;
    color: #64748b;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var totalPages = 1;
    
    function typeLabel(type) {
        var labels = {
            'seo_title': '<?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?>',
            'meta_description': '<?php esc_html_e('Description', 'smart-seo-fixer'); ?>',
            'focus_keyword': '<?php esc_html_e('Keyword', 'smart-seo-fixer'); ?>',
            'content_modified': '<?php esc_html_e('Content', 'smart-seo-fixer'); ?>',
            'meta_change': '<?php esc_html_e('Meta', 'smart-seo-fixer'); ?>',
            'canonical_url': '<?php esc_html_e('Canonical', 'smart-seo-fixer'); ?>',
            'robots_meta': '<?php esc_html_e('Robots', 'smart-seo-fixer'); ?>'
        };
        return labels[type] || type;
    }
    
    function truncate(str, len) {
        if (!str) return '<em style="color:#94a3b8;"><?php esc_html_e('(empty)', 'smart-seo-fixer'); ?></em>';
        str = $('<div/>').text(str).html();
        if (str.length > len) return str.substring(0, len) + '...';
        return str;
    }
    
    function formatDate(dateStr) {
        var d = new Date(dateStr);
        var now = new Date();
        var diffMs = now - d;
        var diffMins = Math.floor(diffMs / 60000);
        var diffHours = Math.floor(diffMs / 3600000);
        var diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return '<?php esc_html_e('Just now', 'smart-seo-fixer'); ?>';
        if (diffMins < 60) return diffMins + ' <?php esc_html_e('min ago', 'smart-seo-fixer'); ?>';
        if (diffHours < 24) return diffHours + ' <?php esc_html_e('hr ago', 'smart-seo-fixer'); ?>';
        if (diffDays < 7) return diffDays + ' <?php esc_html_e('days ago', 'smart-seo-fixer'); ?>';
        
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    function loadHistory(page) {
        page = page || 1;
        currentPage = page;
        
        $('#ssf-history-loading').show();
        $('#ssf-history-table, #ssf-history-empty, #ssf-history-pagination').hide();
        
        $.post(ajaxurl, {
            action: 'ssf_get_history',
            nonce: ssfAdmin.nonce,
            page: page,
            per_page: 50,
            action_type: $('#ssf-history-type').val(),
            source: $('#ssf-history-source').val(),
            search: $('#ssf-history-search').val()
        }, function(response) {
            $('#ssf-history-loading').hide();
            
            if (!response.success || !response.data.items.length) {
                $('#ssf-history-empty').show();
                $('#ssf-history-total').text('');
                return;
            }
            
            var data = response.data;
            totalPages = data.total_pages;
            
            $('#ssf-history-total').text(
                '<?php esc_html_e('Showing', 'smart-seo-fixer'); ?> ' + data.items.length + ' <?php esc_html_e('of', 'smart-seo-fixer'); ?> ' + data.total
            );
            
            var html = '';
            $.each(data.items, function(i, item) {
                var changeHtml = '';
                if (item.action_type === 'content_modified') {
                    changeHtml = '<span class="ssf-diff-content"><?php esc_html_e('Content was modified (internal links)', 'smart-seo-fixer'); ?></span>';
                } else {
                    changeHtml = '<span class="ssf-diff-old">' + truncate(item.old_value, 80) + '</span>' +
                                 '<span class="ssf-diff-new">' + truncate(item.new_value, 80) + '</span>';
                }
                
                var undoBtn = '';
                if (item.reverted) {
                    undoBtn = '<span class="ssf-badge ssf-badge-reverted"><?php esc_html_e('Reverted', 'smart-seo-fixer'); ?></span>';
                } else {
                    undoBtn = '<button type="button" class="button button-small ssf-undo-btn" data-id="' + item.id + '">' +
                              '<span class="dashicons dashicons-undo" style="font-size: 14px; vertical-align: text-bottom;"></span> ' +
                              '<?php esc_html_e('Undo', 'smart-seo-fixer'); ?></button>';
                }
                
                var editLink = item.post_id ? '<a href="<?php echo esc_url(admin_url('post.php?action=edit&post=')); ?>' + item.post_id + '" target="_blank" style="text-decoration: none;">' + $('<span/>').text(item.post_title).html() + '</a>' : '-';
                
                html += '<tr>';
                html += '<td style="font-size: 12px; color: #64748b; white-space: nowrap;">' + formatDate(item.created_at) + '</td>';
                html += '<td>' + editLink + '</td>';
                html += '<td><span class="ssf-badge ssf-badge-type">' + typeLabel(item.action_type) + '</span></td>';
                html += '<td>' + changeHtml + '</td>';
                html += '<td><span class="ssf-badge ssf-badge-' + item.source + '">' + item.source + '</span></td>';
                html += '<td style="text-align: right;">' + undoBtn + '</td>';
                html += '</tr>';
            });
            
            $('#ssf-history-body').html(html);
            $('#ssf-history-table').show();
            
            if (totalPages > 1) {
                $('#ssf-history-pagination').show();
                $('#ssf-history-page-info').text(
                    '<?php esc_html_e('Page', 'smart-seo-fixer'); ?> ' + currentPage + ' / ' + totalPages
                );
                $('#ssf-history-prev').prop('disabled', currentPage <= 1);
                $('#ssf-history-next').prop('disabled', currentPage >= totalPages);
            }
        });
    }
    
    // Filter button
    $('#ssf-history-filter').on('click', function() {
        loadHistory(1);
    });
    
    // Enter key in search
    $('#ssf-history-search').on('keypress', function(e) {
        if (e.which === 13) loadHistory(1);
    });
    
    // Pagination
    $('#ssf-history-prev').on('click', function() {
        if (currentPage > 1) loadHistory(currentPage - 1);
    });
    
    $('#ssf-history-next').on('click', function() {
        if (currentPage < totalPages) loadHistory(currentPage + 1);
    });
    
    // Undo button
    $(document).on('click', '.ssf-undo-btn', function() {
        var $btn = $(this);
        var historyId = $btn.data('id');
        
        if (!confirm('<?php esc_html_e('Are you sure you want to revert this change? The original value will be restored.', 'smart-seo-fixer'); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
        
        $.post(ajaxurl, {
            action: 'ssf_undo_change',
            nonce: ssfAdmin.nonce,
            history_id: historyId
        }, function(response) {
            if (response.success) {
                $btn.replaceWith('<span class="ssf-badge ssf-badge-reverted"><?php esc_html_e('Reverted', 'smart-seo-fixer'); ?></span>');
            } else {
                alert(response.data.message || '<?php esc_html_e('Failed to undo.', 'smart-seo-fixer'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="font-size: 14px; vertical-align: text-bottom;"></span> <?php esc_html_e('Undo', 'smart-seo-fixer'); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });
    
    // Initial load
    loadHistory(1);
});
</script>
