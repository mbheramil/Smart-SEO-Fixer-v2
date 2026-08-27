<?php
/**
 * 404 Monitor View
 */
if (!defined('ABSPATH')) exit;

$stats = class_exists('SSF_404_Monitor') ? SSF_404_Monitor::get_stats() : ['total_active' => 0, 'total_hits' => 0, 'redirected' => 0, 'dismissed' => 0, 'top_url' => '', 'top_hits' => 0];
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-dismiss"></span>
        <?php esc_html_e('404 Monitor', 'smart-seo-fixer'); ?>
    </h1>
    
    <!-- Stats Cards -->
    <div class="ssf-stats-row">
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #ef4444;"><?php echo esc_html($stats['total_active']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Active 404s', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #f59e0b;"><?php echo esc_html(number_format($stats['total_hits'])); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Total Hits', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #10b981;"><?php echo esc_html($stats['redirected']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Redirected', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #94a3b8;"><?php echo esc_html($stats['dismissed']); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Dismissed', 'smart-seo-fixer'); ?></span>
        </div>
    </div>
    
    <?php if (!empty($stats['top_url'])): ?>
    <div class="ssf-notice" style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <strong><?php esc_html_e('Top 404:', 'smart-seo-fixer'); ?></strong>
        <code><?php echo esc_html($stats['top_url']); ?></code>
        — <?php echo esc_html(number_format($stats['top_hits'])); ?> <?php esc_html_e('hits', 'smart-seo-fixer'); ?>
    </div>
    <?php endif; ?>
    
    <!-- Controls -->
    <div class="ssf-toolbar">
        <div class="ssf-toolbar-left">
            <select id="ssf-404-status" class="ssf-select">
                <option value="active"><?php esc_html_e('Active', 'smart-seo-fixer'); ?></option>
                <option value="redirected"><?php esc_html_e('Redirected', 'smart-seo-fixer'); ?></option>
                <option value="dismissed"><?php esc_html_e('Dismissed', 'smart-seo-fixer'); ?></option>
                <option value="all"><?php esc_html_e('All', 'smart-seo-fixer'); ?></option>
            </select>
            <input type="text" id="ssf-404-search" class="ssf-input" placeholder="<?php esc_attr_e('Search URL or referrer...', 'smart-seo-fixer'); ?>">
        </div>
        <div class="ssf-toolbar-right">
            <button type="button" class="button ssf-btn-danger-outline" id="ssf-404-clear-all">
                <?php esc_html_e('Clear All', 'smart-seo-fixer'); ?>
            </button>
        </div>
    </div>
    
    <!-- Results Table -->
    <div class="ssf-table-wrap">
        <table class="ssf-table" id="ssf-404-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('URL', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Hits', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Referrer', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Last Hit', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                </tr>
            </thead>
            <tbody id="ssf-404-body">
                <tr><td colspan="5" class="ssf-loading"><?php esc_html_e('Loading...', 'smart-seo-fixer'); ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="ssf-pagination" id="ssf-404-pagination"></div>
    
    <!-- Redirect Modal -->
    <div id="ssf-redirect-modal" style="display:none;">
        <div class="ssf-modal-overlay">
            <div class="ssf-modal-box">
                <h3><?php esc_html_e('Create Redirect', 'smart-seo-fixer'); ?></h3>
                <p class="ssf-modal-from"></p>
                <div class="ssf-modal-field">
                    <label><?php esc_html_e('Redirect to:', 'smart-seo-fixer'); ?></label>
                    <input type="url" id="ssf-redirect-to" class="ssf-input" style="width: 100%;" placeholder="https://">
                </div>
                <div class="ssf-modal-actions">
                    <button type="button" class="button" id="ssf-redirect-cancel"><?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>
                    <button type="button" class="button button-primary" id="ssf-redirect-save"><?php esc_html_e('Create 301 Redirect', 'smart-seo-fixer'); ?></button>
                </div>
            </div>
        </div>
    </div>
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
.ssf-url-cell { max-width: 350px; word-break: break-all; }
.ssf-hits-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 13px; }
.ssf-btn-sm { padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer; border: 1px solid #d1d5db; background: #fff; color: #374151; margin-right: 4px; }
.ssf-btn-sm:hover { background: #f3f4f6; }
.ssf-btn-sm.ssf-btn-redirect { border-color: #86efac; color: #059669; }
.ssf-btn-sm.ssf-btn-redirect:hover { background: #f0fdf4; }
.ssf-btn-danger-outline { border: 1px solid #fca5a5 !important; color: #dc2626 !important; background: #fff !important; }
.ssf-btn-danger-outline:hover { background: #fef2f2 !important; }
.ssf-pagination { display: flex; justify-content: center; gap: 4px; margin-top: 16px; }
.ssf-pagination button { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; cursor: pointer; font-size: 13px; }
.ssf-pagination button.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.ssf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 999999; }
.ssf-modal-box { background: #fff; border-radius: 12px; padding: 24px; max-width: 480px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.ssf-modal-box h3 { margin: 0 0 12px; }
.ssf-modal-from { color: #6b7280; font-size: 13px; margin-bottom: 16px; }
.ssf-modal-from code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
.ssf-modal-field { margin-bottom: 16px; }
.ssf-modal-field label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
.ssf-modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
</style>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var redirectRecordId = null;
    
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    function load404s() {
        var data = {
            action: 'ssf_get_404_logs',
            nonce: ssfAdmin.nonce,
            page: currentPage,
            status: $('#ssf-404-status').val(),
            search: $('#ssf-404-search').val()
        };
        
        $('#ssf-404-body').html('<tr><td colspan="5" class="ssf-loading">Loading...</td></tr>');
        
        $.post(ssfAdmin.ajax_url, data, function(response) {
            if (!response.success) {
                $('#ssf-404-body').html('<tr><td colspan="5" class="ssf-loading">' + esc(response.data?.message || 'Error') + '</td></tr>');
                return;
            }
            
            var items = response.data.items;
            var html = '';
            
            if (!items.length) {
                html = '<tr><td colspan="5" class="ssf-loading">No 404 errors logged yet. They\'ll appear here as visitors hit missing pages.</td></tr>';
            }
            
            $.each(items, function(i, entry) {
                html += '<tr>';
                html += '<td class="ssf-url-cell"><code>' + esc(entry.url) + '</code></td>';
                html += '<td><span class="ssf-hits-badge">' + entry.hit_count + '</span></td>';
                html += '<td>' + (entry.referrer ? '<a href="' + esc(entry.referrer) + '" target="_blank">' + esc(entry.referrer.substring(0, 40)) + '</a>' : '<span style="color:#94a3b8;">Direct</span>') + '</td>';
                html += '<td>' + esc(entry.last_hit) + '</td>';
                html += '<td>';
                if (!entry.redirected_to) {
                    html += '<button class="ssf-btn-sm ssf-btn-redirect ssf-404-redirect" data-id="' + entry.id + '" data-url="' + esc(entry.url) + '">Redirect</button>';
                    html += '<button class="ssf-btn-sm ssf-404-dismiss" data-id="' + entry.id + '">Dismiss</button>';
                } else {
                    html += '<span style="color: #059669; font-size: 12px;">→ ' + esc(entry.redirected_to.substring(0, 30)) + '</span>';
                }
                html += '</td>';
                html += '</tr>';
            });
            
            $('#ssf-404-body').html(html);
            
            var pages = response.data.pages;
            var paginationHtml = '';
            for (var p = 1; p <= Math.min(pages, 10); p++) {
                paginationHtml += '<button ' + (p === currentPage ? 'class="active"' : '') + ' data-page="' + p + '">' + p + '</button>';
            }
            $('#ssf-404-pagination').html(paginationHtml);
        });
    }
    
    // Filters
    $('#ssf-404-status').on('change', function() { currentPage = 1; load404s(); });
    var searchTimer;
    $('#ssf-404-search').on('input', function() { clearTimeout(searchTimer); searchTimer = setTimeout(function() { currentPage = 1; load404s(); }, 400); });
    
    // Pagination
    $(document).on('click', '#ssf-404-pagination button', function() { currentPage = $(this).data('page'); load404s(); });
    
    // Dismiss
    $(document).on('click', '.ssf-404-dismiss', function() {
        var $btn = $(this);
        $.post(ssfAdmin.ajax_url, { action: 'ssf_dismiss_404', nonce: ssfAdmin.nonce, id: $btn.data('id') }, function() {
            $btn.closest('tr').fadeOut(function() { $(this).remove(); });
        });
    });
    
    // Redirect - open modal
    $(document).on('click', '.ssf-404-redirect', function() {
        redirectRecordId = $(this).data('id');
        var url = $(this).data('url');
        $('#ssf-redirect-modal .ssf-modal-from').html('From: <code>' + esc(url) + '</code>');
        $('#ssf-redirect-to').val('<?php echo esc_js(home_url('/')); ?>');
        $('#ssf-redirect-modal').show();
    });
    
    // Redirect - cancel
    $('#ssf-redirect-cancel').on('click', function() {
        $('#ssf-redirect-modal').hide();
        redirectRecordId = null;
    });
    
    // Redirect - save
    $('#ssf-redirect-save').on('click', function() {
        var redirectTo = $('#ssf-redirect-to').val();
        if (!redirectTo) { alert('Please enter a URL'); return; }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Creating...');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_create_404_redirect',
            nonce: ssfAdmin.nonce,
            id: redirectRecordId,
            redirect_to: redirectTo
        }, function(response) {
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Create 301 Redirect', 'smart-seo-fixer')); ?>');
            $('#ssf-redirect-modal').hide();
            redirectRecordId = null;
            load404s();
        });
    });
    
    // Clear all
    $('#ssf-404-clear-all').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Clear all 404 logs? This cannot be undone.', 'smart-seo-fixer')); ?>')) return;
        $.post(ssfAdmin.ajax_url, { action: 'ssf_clear_404_logs', nonce: ssfAdmin.nonce }, function() { load404s(); });
    });
    
    load404s();
});
</script>
