<?php
/**
 * Broken Links View
 */
if (!defined('ABSPATH')) exit;

$stats = class_exists('SSF_Broken_Links') ? SSF_Broken_Links::get_stats() : ['total' => 0, 'internal' => 0, 'external' => 0, 'dismissed' => 0, 'posts_affected' => 0];
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-editor-unlink"></span>
        <?php esc_html_e('Broken Link Checker', 'smart-seo-fixer'); ?>
    </h1>
    
    <!-- Stats Cards -->
    <div class="ssf-stats-row">
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" id="ssf-bl-stat-total" style="color: #ef4444;"><?php echo esc_html($stats['total']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Broken Links', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" id="ssf-bl-stat-internal" style="color: #f59e0b;"><?php echo esc_html($stats['internal']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Internal', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" id="ssf-bl-stat-external" style="color: #3b82f6;"><?php echo esc_html($stats['external']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('External', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" id="ssf-bl-stat-posts" style="color: #6b7280;"><?php echo esc_html($stats['posts_affected']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Posts Affected', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" id="ssf-bl-stat-dismissed" style="color: #94a3b8;"><?php echo esc_html($stats['dismissed']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Dismissed', 'smart-seo-fixer'); ?></span>
        </div>
    </div>
    
    <!-- Controls -->
    <div class="ssf-toolbar">
        <div class="ssf-toolbar-left">
            <select id="ssf-bl-type" class="ssf-select">
                <option value=""><?php esc_html_e('All Types', 'smart-seo-fixer'); ?></option>
                <option value="internal"><?php esc_html_e('Internal', 'smart-seo-fixer'); ?></option>
                <option value="external"><?php esc_html_e('External', 'smart-seo-fixer'); ?></option>
            </select>
            <select id="ssf-bl-status" class="ssf-select">
                <option value="active"><?php esc_html_e('Active', 'smart-seo-fixer'); ?></option>
                <option value="dismissed"><?php esc_html_e('Dismissed', 'smart-seo-fixer'); ?></option>
                <option value=""><?php esc_html_e('All', 'smart-seo-fixer'); ?></option>
            </select>
            <input type="text" id="ssf-bl-search" class="ssf-input" placeholder="<?php esc_attr_e('Search URL or anchor...', 'smart-seo-fixer'); ?>">
        </div>
        <div class="ssf-toolbar-right">
            <button type="button" class="button" id="ssf-bl-scan-now">
                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                <?php esc_html_e('Scan Now', 'smart-seo-fixer'); ?>
            </button>
        </div>
    </div>

    <!-- Scan progress -->
    <div id="ssf-bl-scan-status" class="ssf-scan-status">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="flex:1;">
                <div id="ssf-bl-scan-text" style="margin-bottom:6px;font-weight:500;"></div>
                <div style="background:#dbeafe;border-radius:9999px;height:10px;overflow:hidden;">
                    <div id="ssf-bl-scan-bar" style="background:#2563eb;height:100%;width:0%;transition:width 0.3s ease;border-radius:9999px;"></div>
                </div>
            </div>
            <span id="ssf-bl-scan-pct" style="font-weight:700;min-width:48px;text-align:right;">0%</span>
        </div>
    </div>

    <!-- Bulk Action Bar (shown when items are selected) -->
    <div id="ssf-bl-bulk-bar" style="display:none;" class="ssf-bulk-bar">
        <span id="ssf-bl-selected-count" class="ssf-bulk-bar-count">0 <?php esc_html_e('selected', 'smart-seo-fixer'); ?></span>
        <span class="ssf-bulk-bar-sep">|</span>
        <label class="ssf-bulk-bar-label"><?php esc_html_e('Redirect all to:', 'smart-seo-fixer'); ?></label>
        <input type="url" id="ssf-bl-redirect-url" class="ssf-input ssf-bulk-url-input" placeholder="https://example.com/new-page/">
        <button type="button" class="button button-primary" id="ssf-bl-bulk-redirect">
            <span class="dashicons dashicons-randomize" style="margin-top:3px;"></span>
            <?php esc_html_e('Apply Redirect', 'smart-seo-fixer'); ?>
        </button>
        <button type="button" class="button" id="ssf-bl-bulk-dismiss">
            <?php esc_html_e('Dismiss Selected', 'smart-seo-fixer'); ?>
        </button>
    </div>
    
    <!-- Results Table -->
    <div class="ssf-table-wrap">
        <table class="ssf-table" id="ssf-bl-table">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="ssf-bl-select-all" title="<?php esc_attr_e('Select all', 'smart-seo-fixer'); ?>"></th>
                    <th><?php esc_html_e('URL', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Found In', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Anchor Text', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Status', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Detected', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                </tr>
            </thead>
            <tbody id="ssf-bl-body">
                <tr><td colspan="8" class="ssf-loading"><?php esc_html_e('Loading...', 'smart-seo-fixer'); ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="ssf-pagination" id="ssf-bl-pagination"></div>
</div>

<style>
.ssf-stats-row { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.ssf-mini-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; text-align: center; min-width: 120px; flex: 1; }
.ssf-mini-val { display: block; font-size: 28px; font-weight: 700; line-height: 1.2; }
.ssf-mini-label { color: #6b7280; font-size: 12px; margin-top: 4px; display: block; }
.ssf-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.ssf-toolbar-left { display: flex; gap: 8px; flex-wrap: wrap; }
.ssf-select, .ssf-input { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
.ssf-input { min-width: 220px; }
.ssf-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow-x: auto; }
.ssf-table { width: 100%; border-collapse: collapse; }
.ssf-table th { background: #f9fafb; padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.ssf-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #1f2937; }
.ssf-table tr:last-child td { border-bottom: none; }
.ssf-table tr:hover { background: #f9fafb; }
.ssf-loading { text-align: center; color: #94a3b8; padding: 40px !important; }
.ssf-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.ssf-badge-error { background: #fef2f2; color: #dc2626; }
.ssf-badge-warning { background: #fffbeb; color: #d97706; }
.ssf-badge-internal { background: #eff6ff; color: #2563eb; }
.ssf-badge-external { background: #f5f3ff; color: #7c3aed; }
.ssf-url-cell { max-width: 300px; word-break: break-all; }
.ssf-btn-sm { padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer; border: 1px solid #d1d5db; background: #fff; color: #374151; }
.ssf-btn-sm:hover { background: #f3f4f6; }
.ssf-btn-sm.ssf-btn-danger { border-color: #fca5a5; color: #dc2626; }
.ssf-btn-sm.ssf-btn-danger:hover { background: #fef2f2; }
.ssf-btn-sm.ssf-btn-primary { border-color: #2563eb; color: #2563eb; }
.ssf-btn-sm.ssf-btn-primary:hover { background: #eff6ff; }
.ssf-pagination { display: flex; justify-content: center; gap: 4px; margin-top: 16px; }
.ssf-pagination button { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; cursor: pointer; font-size: 13px; }
.ssf-pagination button.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.ssf-pagination button:hover:not(.active) { background: #f3f4f6; }
.ssf-scan-status { padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 16px; color: #1e40af; font-size: 13px; display: none; }
.ssf-bulk-bar { display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.ssf-bulk-bar-count { font-weight: 600; color: #1e40af; font-size: 13px; white-space: nowrap; }
.ssf-bulk-bar-sep { color: #93c5fd; }
.ssf-bulk-bar-label { font-size: 13px; color: #374151; white-space: nowrap; }
.ssf-bulk-url-input { min-width: 280px; }
</style>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    
    function loadLinks() {
        var data = {
            action: 'ssf_get_broken_links',
            nonce: ssfAdmin.nonce,
            page: currentPage,
            link_type: $('#ssf-bl-type').val(),
            status: $('#ssf-bl-status').val(),
            search: $('#ssf-bl-search').val()
        };
        
        $('#ssf-bl-body').html('<tr><td colspan="8" class="ssf-loading">Loading...</td></tr>');

        $.post(ssfAdmin.ajax_url, data, function(response) {
            if (!response.success) {
                $('#ssf-bl-body').html('<tr><td colspan="8" class="ssf-loading">' + (response.data?.message || 'Error') + '</td></tr>');
                return;
            }

            // Refresh the stat cards from the fresh counts.
            if (response.data.stats) {
                var s = response.data.stats;
                $('#ssf-bl-stat-total').text(s.total);
                $('#ssf-bl-stat-internal').text(s.internal);
                $('#ssf-bl-stat-external').text(s.external);
                $('#ssf-bl-stat-posts').text(s.posts_affected);
                $('#ssf-bl-stat-dismissed').text(s.dismissed);
            }

            var items = response.data.items;
            var html = '';
            
            if (!items.length) {
                html = '<tr><td colspan="8" class="ssf-loading">No broken links found. Click "Scan Now" to check your content.</td></tr>';
            }
            
            $.each(items, function(i, link) {
                var statusBadge = link.status_code >= 400 ? '<span class="ssf-badge ssf-badge-error">' + link.status_code + '</span>' : '<span class="ssf-badge ssf-badge-warning">Error</span>';
                var typeBadge = link.link_type === 'internal' ? '<span class="ssf-badge ssf-badge-internal">Internal</span>' : '<span class="ssf-badge ssf-badge-external">External</span>';
                var editUrl = '<?php echo admin_url('post.php?action=edit&post='); ?>' + link.post_id;
                
                html += '<tr data-id="' + link.id + '">';
                html += '<td><input type="checkbox" class="ssf-bl-cb" data-id="' + link.id + '"></td>';
                html += '<td class="ssf-url-cell"><a href="' + link.url + '" target="_blank" rel="noopener">' + $('<span>').text(link.url.substring(0, 60) + (link.url.length > 60 ? '...' : '')).html() + '</a></td>';
                html += '<td><a href="' + editUrl + '" target="_blank">' + $('<span>').text(link.post_title || 'Post #' + link.post_id).html() + '</a></td>';
                html += '<td>' + $('<span>').text(link.anchor_text || '—').html() + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td>' + typeBadge + '</td>';
                html += '<td>' + link.last_checked + '</td>';
                html += '<td>';
                html += '<button class="ssf-btn-sm ssf-bl-recheck" data-id="' + link.id + '">Recheck</button> ';
                if (link.dismissed == 0) {
                    html += '<button class="ssf-btn-sm ssf-bl-dismiss" data-id="' + link.id + '">Dismiss</button>';
                } else {
                    html += '<button class="ssf-btn-sm ssf-bl-undismiss" data-id="' + link.id + '">Undismiss</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            
            $('#ssf-bl-body').html(html);
            updateBulkBar();
            
            // Pagination
            var pages = response.data.pages;
            var paginationHtml = '';
            for (var p = 1; p <= Math.min(pages, 10); p++) {
                paginationHtml += '<button ' + (p === currentPage ? 'class="active"' : '') + ' data-page="' + p + '">' + p + '</button>';
            }
            $('#ssf-bl-pagination').html(paginationHtml);
        });
    }
    
    // ---- Bulk selection helpers ----
    function getSelectedIds() {
        var ids = [];
        $('.ssf-bl-cb:checked').each(function() { ids.push($(this).data('id')); });
        return ids;
    }

    function updateBulkBar() {
        var count = getSelectedIds().length;
        if (count > 0) {
            $('#ssf-bl-selected-count').text(count + ' <?php esc_html_e('selected', 'smart-seo-fixer'); ?>');
            $('#ssf-bl-bulk-bar').slideDown(150);
        } else {
            $('#ssf-bl-bulk-bar').slideUp(150);
        }
    }

    // Select-all checkbox
    $(document).on('change', '#ssf-bl-select-all', function() {
        var checked = $(this).prop('checked');
        $('.ssf-bl-cb').prop('checked', checked);
        updateBulkBar();
    });

    // Individual checkboxes
    $(document).on('change', '.ssf-bl-cb', function() {
        var total = $('.ssf-bl-cb').length;
        var checked = $('.ssf-bl-cb:checked').length;
        $('#ssf-bl-select-all').prop('checked', total > 0 && total === checked);
        updateBulkBar();
    });

    // Bulk Redirect
    $('#ssf-bl-bulk-redirect').on('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) { alert('<?php esc_html_e('Please select at least one broken link.', 'smart-seo-fixer'); ?>'); return; }
        var target = $('#ssf-bl-redirect-url').val().trim();
        if (!target || target.indexOf('http') !== 0) { alert('<?php esc_html_e('Please enter a valid destination URL (must start with http).', 'smart-seo-fixer'); ?>'); return; }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php esc_html_e('Applying...', 'smart-seo-fixer'); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action:     'ssf_bulk_redirect_broken_links',
            nonce:      ssfAdmin.nonce,
            ids:        ids,
            target_url: target
        }, function(response) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-randomize" style="margin-top:3px;"></span> <?php esc_html_e('Apply Redirect', 'smart-seo-fixer'); ?>');
            if (response.success) {
                alert(response.data.message);
                $('#ssf-bl-redirect-url').val('');
                $('#ssf-bl-select-all').prop('checked', false);
                loadLinks();
            } else {
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Something went wrong.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-randomize" style="margin-top:3px;"></span> <?php esc_html_e('Apply Redirect', 'smart-seo-fixer'); ?>');
            alert('<?php esc_html_e('Request failed. Please try again.', 'smart-seo-fixer'); ?>');
        });
    });

    // Bulk Dismiss
    $('#ssf-bl-bulk-dismiss').on('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var $btn = $(this).prop('disabled', true).text('<?php esc_html_e('Working...', 'smart-seo-fixer'); ?>');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_bulk_dismiss_broken_links',
            nonce:  ssfAdmin.nonce,
            ids:    ids
        }, function() {
            $btn.prop('disabled', false).text('<?php esc_html_e('Dismiss Selected', 'smart-seo-fixer'); ?>');
            $('#ssf-bl-select-all').prop('checked', false);
            loadLinks();
        });
    });

    // Filters
    $('#ssf-bl-type, #ssf-bl-status').on('change', function() { currentPage = 1; loadLinks(); });
    var searchTimer;
    $('#ssf-bl-search').on('input', function() { clearTimeout(searchTimer); searchTimer = setTimeout(function() { currentPage = 1; loadLinks(); }, 400); });
    
    // Pagination
    $(document).on('click', '#ssf-bl-pagination button', function() { currentPage = $(this).data('page'); loadLinks(); });
    
    // Recheck
    $(document).on('click', '.ssf-bl-recheck', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Checking...');
        $.post(ssfAdmin.ajax_url, { action: 'ssf_recheck_broken_link', nonce: ssfAdmin.nonce, id: $btn.data('id') }, function(response) {
            if (response.success && !response.data.still_broken) {
                $btn.closest('tr').fadeOut(function() { $(this).remove(); });
            } else {
                $btn.prop('disabled', false).text('Recheck');
                alert(response.data?.error || 'Still broken');
            }
        });
    });
    
    // Dismiss
    $(document).on('click', '.ssf-bl-dismiss', function() {
        var $btn = $(this);
        $.post(ssfAdmin.ajax_url, { action: 'ssf_dismiss_broken_link', nonce: ssfAdmin.nonce, id: $btn.data('id') }, function() {
            $btn.closest('tr').fadeOut(function() { $(this).remove(); });
        });
    });
    
    // Undismiss
    $(document).on('click', '.ssf-bl-undismiss', function() {
        var $btn = $(this);
        $.post(ssfAdmin.ajax_url, { action: 'ssf_undismiss_broken_link', nonce: ssfAdmin.nonce, id: $btn.data('id') }, function() {
            loadLinks();
        });
    });
    
    // Scan Now — runs in small batches with a progress bar so large sites
    // never time out. Each batch scans a few posts and reports progress.
    var scanning = false;
    var resumeOffset = 0; // where to resume if a batch times out
    $('#ssf-bl-scan-now').on('click', function() {
        if (scanning) return;
        scanning = true;

        var $btn = $(this).prop('disabled', true);
        $btn.find('.dashicons').addClass('spin');

        var totalChecked = 0, totalBroken = 0;
        var $status = $('#ssf-bl-scan-status').show();
        $('#ssf-bl-scan-bar').css({'width':'0%','background':'#2563eb'});
        $('#ssf-bl-scan-pct').text('0%');
        $('#ssf-bl-scan-text').text('<?php echo esc_js(__('Starting scan…', 'smart-seo-fixer')); ?>');

        function finish(msg, ok) {
            scanning = false;
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            $('#ssf-bl-scan-bar').css('background', ok ? '#059669' : '#dc2626');
            $('#ssf-bl-scan-text').text(msg);
            loadLinks();
        }

        function scanBatch(offset) {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_scan_broken_links',
                nonce:  ssfAdmin.nonce,
                offset: offset
            }, function(response) {
                if (!response.success) {
                    finish('<?php echo esc_js(__('Scan failed:', 'smart-seo-fixer')); ?> ' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('unknown error', 'smart-seo-fixer')); ?>'), false);
                    return;
                }
                var d = response.data;
                totalChecked += d.checked;
                totalBroken  += d.broken;

                $('#ssf-bl-scan-bar').css('width', d.percent + '%');
                $('#ssf-bl-scan-pct').text(d.percent + '%');
                $('#ssf-bl-scan-text').text(
                    '<?php echo esc_js(__('Scanning…', 'smart-seo-fixer')); ?> ' +
                    d.processed + ' / ' + d.total + ' <?php echo esc_js(__('posts', 'smart-seo-fixer')); ?> · ' +
                    totalChecked + ' <?php echo esc_js(__('links checked', 'smart-seo-fixer')); ?> · ' +
                    totalBroken + ' <?php echo esc_js(__('broken', 'smart-seo-fixer')); ?>'
                );

                if (d.done) {
                    resumeOffset = 0;
                    finish(
                        '✓ <?php echo esc_js(__('Scan complete:', 'smart-seo-fixer')); ?> ' +
                        totalChecked + ' <?php echo esc_js(__('links checked across', 'smart-seo-fixer')); ?> ' +
                        d.total + ' <?php echo esc_js(__('posts,', 'smart-seo-fixer')); ?> ' +
                        totalBroken + ' <?php echo esc_js(__('broken found.', 'smart-seo-fixer')); ?>',
                        true
                    );
                } else {
                    resumeOffset = d.next_offset;
                    scanBatch(d.next_offset);
                }
            }).fail(function() {
                // A batch timed out — results so far are saved. Resume from here.
                resumeOffset = offset;
                finish('<?php echo esc_js(__('A batch took too long and stopped. Partial results were saved — click Scan Now again to continue.', 'smart-seo-fixer')); ?>', false);
            });
        }

        scanBatch(resumeOffset);
    });
    
    loadLinks();
});
</script>
