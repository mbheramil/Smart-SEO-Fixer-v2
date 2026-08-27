<?php
/**
 * WordPress Coding Standards View
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-editor-code"></span>
        <?php esc_html_e('WordPress Coding Standards', 'smart-seo-fixer'); ?>
    </h1>
    
    <p class="ssf-subtitle"><?php esc_html_e('Self-audit of plugin code against common WordPress coding standards.', 'smart-seo-fixer'); ?></p>
    
    <button type="button" id="ssf-run-audit" class="button button-primary" style="margin-bottom: 16px;">
        <span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
        <?php esc_html_e('Run Audit', 'smart-seo-fixer'); ?>
    </button>
    
    <div id="ssf-audit-loading" style="display: none; padding: 40px; text-align: center; color: #94a3b8;">
        <?php esc_html_e('Scanning plugin files...', 'smart-seo-fixer'); ?>
    </div>
    
    <div id="ssf-audit-output" style="display: none;">
        <div class="ssf-std-stats">
            <div class="ssf-stat-card">
                <div class="ssf-stat-num" id="ssf-std-score">—</div>
                <div class="ssf-stat-label"><?php esc_html_e('Score', 'smart-seo-fixer'); ?></div>
            </div>
            <div class="ssf-stat-card">
                <div class="ssf-stat-num" id="ssf-std-files">0</div>
                <div class="ssf-stat-label"><?php esc_html_e('Files Checked', 'smart-seo-fixer'); ?></div>
            </div>
            <div class="ssf-stat-card error">
                <div class="ssf-stat-num" id="ssf-std-errors">0</div>
                <div class="ssf-stat-label"><?php esc_html_e('Errors', 'smart-seo-fixer'); ?></div>
            </div>
            <div class="ssf-stat-card warning">
                <div class="ssf-stat-num" id="ssf-std-warnings">0</div>
                <div class="ssf-stat-label"><?php esc_html_e('Warnings', 'smart-seo-fixer'); ?></div>
            </div>
            <div class="ssf-stat-card info">
                <div class="ssf-stat-num" id="ssf-std-info">0</div>
                <div class="ssf-stat-label"><?php esc_html_e('Info', 'smart-seo-fixer'); ?></div>
            </div>
            <div class="ssf-stat-card clean">
                <div class="ssf-stat-num" id="ssf-std-clean">0</div>
                <div class="ssf-stat-label"><?php esc_html_e('Clean Files', 'smart-seo-fixer'); ?></div>
            </div>
        </div>
        
        <div id="ssf-audit-issues"></div>
    </div>
</div>

<style>
.ssf-subtitle { color: #64748b; font-size: 13px; margin-top: -4px; margin-bottom: 16px; }
.ssf-std-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
.ssf-stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; text-align: center; }
.ssf-stat-num { font-size: 28px; font-weight: 800; color: #1e293b; }
.ssf-stat-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-top: 2px; }
.ssf-stat-card.error .ssf-stat-num { color: #ef4444; }
.ssf-stat-card.warning .ssf-stat-num { color: #f59e0b; }
.ssf-stat-card.info .ssf-stat-num { color: #3b82f6; }
.ssf-stat-card.clean .ssf-stat-num { color: #10b981; }

.ssf-file-group { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
.ssf-file-header { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 13px; background: #f9fafb; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.ssf-file-header:hover { background: #f3f4f6; }
.ssf-file-header .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.ssf-file-header .badge.e { background: #fef2f2; color: #dc2626; }
.ssf-file-header .badge.w { background: #fffbeb; color: #d97706; }
.ssf-file-header .badge.i { background: #eff6ff; color: #2563eb; }
.ssf-file-issues { display: none; }
.ssf-file-issues.open { display: block; }
.ssf-issue-row { display: flex; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; font-size: 12px; align-items: flex-start; }
.ssf-issue-row:last-child { border-bottom: none; }
.ssf-issue-sev { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; flex-shrink: 0; }
.ssf-issue-sev.error { background: #fef2f2; color: #dc2626; }
.ssf-issue-sev.warning { background: #fffbeb; color: #d97706; }
.ssf-issue-sev.info { background: #eff6ff; color: #2563eb; }
.ssf-issue-line { color: #94a3b8; flex-shrink: 0; width: 50px; }
.ssf-issue-rule { color: #8b5cf6; flex-shrink: 0; width: 160px; font-family: monospace; font-size: 11px; }
.ssf-issue-msg { color: #334155; flex: 1; line-height: 1.4; }
.ssf-all-clean { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 24px; text-align: center; color: #166534; font-weight: 600; }
</style>

<script>
jQuery(document).ready(function($) {
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    function scoreColor(s) {
        if (s >= 90) return '#10b981';
        if (s >= 70) return '#f59e0b';
        if (s >= 50) return '#f97316';
        return '#ef4444';
    }
    
    $('#ssf-run-audit').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $('#ssf-audit-loading').show();
        $('#ssf-audit-output').hide();
        
        $.post(ssfAdmin.ajax_url, { action: 'ssf_wp_standards_audit', nonce: ssfAdmin.nonce }, function(r) {
            $btn.prop('disabled', false);
            $('#ssf-audit-loading').hide();
            
            if (!r.success) return;
            
            var d = r.data;
            $('#ssf-std-score').text(d.score).css('color', scoreColor(d.score));
            $('#ssf-std-files').text(d.files_checked);
            $('#ssf-std-errors').text(d.summary.errors);
            $('#ssf-std-warnings').text(d.summary.warnings);
            $('#ssf-std-info').text(d.summary.info);
            $('#ssf-std-clean').text(d.summary.clean_files);
            
            var html = '';
            if (!d.summary.total_issues) {
                html = '<div class="ssf-all-clean">All files pass the standards check — well done!</div>';
            }
            
            $.each(d.issues, function(file, issues) {
                var eCount = issues.filter(function(i) { return i.severity === 'error'; }).length;
                var wCount = issues.filter(function(i) { return i.severity === 'warning'; }).length;
                var iCount = issues.filter(function(i) { return i.severity === 'info'; }).length;
                var badges = '';
                if (eCount) badges += '<span class="badge e">' + eCount + ' error' + (eCount > 1 ? 's' : '') + '</span> ';
                if (wCount) badges += '<span class="badge w">' + wCount + ' warning' + (wCount > 1 ? 's' : '') + '</span> ';
                if (iCount) badges += '<span class="badge i">' + iCount + ' info</span>';
                
                html += '<div class="ssf-file-group">';
                html += '<div class="ssf-file-header"><span>' + esc(file) + '</span><span>' + badges + '</span></div>';
                html += '<div class="ssf-file-issues">';
                $.each(issues, function(j, iss) {
                    html += '<div class="ssf-issue-row">';
                    html += '<span class="ssf-issue-sev ' + iss.severity + '">' + iss.severity + '</span>';
                    html += '<span class="ssf-issue-line">L' + iss.line + '</span>';
                    html += '<span class="ssf-issue-rule">' + esc(iss.rule) + '</span>';
                    html += '<span class="ssf-issue-msg">' + esc(iss.message) + '</span>';
                    html += '</div>';
                });
                html += '</div></div>';
            });
            
            $('#ssf-audit-issues').html(html);
            $('#ssf-audit-output').show();
        });
    });
    
    $(document).on('click', '.ssf-file-header', function() {
        $(this).next('.ssf-file-issues').toggleClass('open');
    });
});
</script>
