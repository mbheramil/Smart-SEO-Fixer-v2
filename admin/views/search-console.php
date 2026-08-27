<?php
/**
 * Search Console Fixer View
 * 
 * Displays and helps fix common Google Search Console indexing issues.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-shield"></span>
        <?php esc_html_e('Indexability Auditor', 'smart-seo-fixer'); ?>
    </h1>
    
    <p class="ssf-page-description">
        <?php esc_html_e('Scans every published page against all 9 Google Search Console indexing issue types — then lets you fix them with one click.', 'smart-seo-fixer'); ?>
    </p>
    
    <!-- Compact legend strip -->
    <div class="ssf-legend-strip">
        <span class="ssf-legend-item"><span class="ssf-legend-dot" style="background:#10b981;"></span><?php esc_html_e('Auto-Fix', 'smart-seo-fixer'); ?></span>
        <span class="ssf-legend-item"><span class="ssf-legend-dot" style="background:#8b5cf6;"></span><?php esc_html_e('AI Fix', 'smart-seo-fixer'); ?></span>
        <span class="ssf-legend-item"><span class="ssf-legend-dot" style="background:#f59e0b;"></span><?php esc_html_e('Review Needed', 'smart-seo-fixer'); ?></span>
        <span class="ssf-legend-item"><span class="ssf-legend-dot" style="background:#ef4444;"></span><?php esc_html_e('Manual', 'smart-seo-fixer'); ?></span>
    </div>
    
    <!-- Summary Stats -->
    <div class="ssf-stats-grid" id="gsc-stats-grid">
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon ssf-stat-good">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-trailing-slash">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Trailing Slash - Auto-Enforced', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
        
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon ssf-stat-ok">
                <span class="dashicons dashicons-migrate"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-redirects">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Active Redirects', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
        
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon ssf-stat-poor">
                <span class="dashicons dashicons-hidden"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-noindex">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Noindex Pages', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
        
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon ssf-stat-unanalyzed">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-404s">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Tracked 404s', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
        
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;">
                <span class="dashicons dashicons-editor-help"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-missing-seo">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Missing SEO Data', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
        
        <div class="ssf-stat-card">
            <div class="ssf-stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff;">
                <span class="dashicons dashicons-media-text"></span>
            </div>
            <div class="ssf-stat-content">
                <span class="ssf-stat-value" id="stat-thin-content">—</span>
                <span class="ssf-stat-label"><?php esc_html_e('Thin Content', 'smart-seo-fixer'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Scan & Fix Actions -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-shield"></span>
                <?php esc_html_e('Indexability Audit', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <p class="ssf-audit-description">
                <?php esc_html_e('Scans every published page against all 9 Google Search Console issue types. Detects problems and provides one-click fixes.', 'smart-seo-fixer'); ?>
            </p>
            <div class="ssf-actions-grid">
                <button type="button" class="ssf-action-btn ssf-action-primary" id="scan-url-issues-btn">
                    <span class="dashicons dashicons-shield"></span>
                    <span class="ssf-action-text">
                        <strong><?php esc_html_e('Run Indexability Audit', 'smart-seo-fixer'); ?></strong>
                        <small><?php esc_html_e('Check all 9 GSC issue types at once', 'smart-seo-fixer'); ?></small>
                    </span>
                </button>
                
                <button type="button" class="ssf-action-btn" id="fix-all-issues-btn" disabled>
                    <span class="dashicons dashicons-admin-generic"></span>
                    <span class="ssf-action-text">
                        <strong><?php esc_html_e('Fix All Auto-Fixable', 'smart-seo-fixer'); ?></strong>
                        <small><?php esc_html_e('Redirect chains, trailing slashes', 'smart-seo-fixer'); ?></small>
                    </span>
                </button>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-redirects')); ?>" class="ssf-action-btn">
                    <span class="dashicons dashicons-migrate"></span>
                    <span class="ssf-action-text">
                        <strong><?php esc_html_e('Manage Redirects', 'smart-seo-fixer'); ?></strong>
                        <small><?php esc_html_e('Add/edit 301 redirects and view 404 log', 'smart-seo-fixer'); ?></small>
                    </span>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts')); ?>" class="ssf-action-btn">
                    <span class="dashicons dashicons-admin-post"></span>
                    <span class="ssf-action-text">
                        <strong><?php esc_html_e('Review All Posts', 'smart-seo-fixer'); ?></strong>
                        <small><?php esc_html_e('SEO scores, noindex, canonicals', 'smart-seo-fixer'); ?></small>
                    </span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Scan Results -->
    <div class="ssf-card" id="scan-results-card" style="display: none;">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-analytics"></span>
                <?php esc_html_e('Scan Results', 'smart-seo-fixer'); ?>
            </h2>
            <span class="ssf-badge" id="total-issues-badge">0 issues</span>
        </div>
        <div class="ssf-card-body">
            <div id="scan-results-content">
                <!-- Results populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    var editUrl = '<?php echo esc_url(admin_url('post.php?action=edit&post=')); ?>';
    var scanData = null;
    
    // Load summary stats
    function loadGSCSummary() {
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_gsc_summary',
            nonce: ssfAdmin.nonce
        }, function(response) {
            if (response.success) {
                var d = response.data;
                $('#stat-trailing-slash').text(d.trailing_slash_mode);
                $('#stat-redirects').text(d.active_redirects);
                $('#stat-noindex').text(d.noindex_pages);
                $('#stat-404s').text(d.tracked_404s);
                $('#stat-missing-seo').text(d.missing_seo);
                $('#stat-thin-content').text(d.thin_content);
            }
        });
    }
    
    loadGSCSummary();
    
    // Scan for all indexability issues
    $('#scan-url-issues-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('strong').text('<?php echo esc_js(__('Running full audit...', 'smart-seo-fixer')); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_scan_url_issues',
            nonce: ssfAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).find('strong').text('<?php echo esc_js(__('Run Indexability Audit', 'smart-seo-fixer')); ?>');
            if (response.success) {
                scanData = response.data;
                displayFullResults(response.data);
            } else {
                alert(response.data.message || '<?php echo esc_js(__('Audit failed.', 'smart-seo-fixer')); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).find('strong').text('<?php echo esc_js(__('Run Indexability Audit', 'smart-seo-fixer')); ?>');
        });
    });
    
    // Display comprehensive results
    function displayFullResults(data) {
        var $card = $('#scan-results-card');
        var $content = $('#scan-results-content');
        var total = data.total || 0;
        
        $('#total-issues-badge').text(total + ' <?php echo esc_js(__('issues found', 'smart-seo-fixer')); ?>');
        
        if (total === 0) {
            $content.html('<div class="ssf-audit-clean"><span class="dashicons dashicons-yes-alt"></span><h3><?php echo esc_js(__('Your site is fully optimized for Google indexing!', 'smart-seo-fixer')); ?></h3><p><?php echo esc_js(__('No issues detected. All published pages should be indexable.', 'smart-seo-fixer')); ?></p></div>');
            $('#fix-all-issues-btn').prop('disabled', true);
            $card.show();
            return;
        }
        
        var html = '';
        var issues = data.issues;
        
        // === CRITICAL ISSUES (Red) ===
        
        // Blocked by robots.txt
        if (issues.blocked_by_robots && issues.blocked_by_robots.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-lock',
                color: '#ef4444',
                gscLabel: '<?php echo esc_js(__('Blocked by robots.txt', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Published pages blocked from crawling', 'smart-seo-fixer')); ?>',
                items: issues.blocked_by_robots,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-critical">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<code>' + escHtml(item.path) + '</code>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<span class="ssf-audit-tag ssf-tag-manual"><?php echo esc_js(__('Edit robots.txt', 'smart-seo-fixer')); ?></span>' +
                    '</div>';
                }
            });
        }
        
        // Noindex conflicts
        if (issues.noindex_conflict && issues.noindex_conflict.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-hidden',
                color: '#ef4444',
                gscLabel: '<?php echo esc_js(__("Excluded by 'noindex' tag", 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Pages excluded from Google index', 'smart-seo-fixer')); ?>',
                items: issues.noindex_conflict,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-critical">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<button class="button ssf-fix-btn" data-fix="remove_noindex" data-post-id="' + item.post_id + '">' +
                            '<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Remove Noindex', 'smart-seo-fixer')); ?>' +
                        '</button>' +
                    '</div>';
                }
            });
        }
        
        // Redirect chains
        if (issues.redirect_chains && issues.redirect_chains.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-migrate',
                color: '#ef4444',
                gscLabel: '<?php echo esc_js(__('Page with redirect (chains)', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Redirect chains slow down crawling', 'smart-seo-fixer')); ?>',
                items: issues.redirect_chains,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-critical">' +
                        '<div class="ssf-audit-item-info">' +
                            '<code>' + escHtml(item.from) + '</code> → <code>' + escHtml(item.to) + '</code> → <code>' + escHtml(item.chain_to) + '</code>' +
                        '</div>' +
                        '<button class="button ssf-fix-btn" data-fix="fix_redirect_chains">' +
                            '<span class="dashicons dashicons-admin-generic"></span> <?php echo esc_js(__('Flatten Chain', 'smart-seo-fixer')); ?>' +
                        '</button>' +
                    '</div>';
                }
            });
        }
        
        // === HIGH PRIORITY (Orange) ===
        
        // Missing SEO data
        if (issues.missing_seo && issues.missing_seo.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-editor-help',
                color: '#f59e0b',
                gscLabel: '<?php echo esc_js(__('Discovered - currently not indexed', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Pages missing SEO title or description', 'smart-seo-fixer')); ?>',
                items: issues.missing_seo,
                bulkFix: {type: 'generate_seo', label: '<?php echo esc_js(__('AI Generate All Missing', 'smart-seo-fixer')); ?>'},
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-warning">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<button class="button button-primary ssf-fix-btn" data-fix="generate_seo" data-post-id="' + item.post_id + '">' +
                            '<span class="dashicons dashicons-superhero-alt"></span> <?php echo esc_js(__('AI Generate', 'smart-seo-fixer')); ?>' +
                        '</button>' +
                    '</div>';
                }
            });
        }
        
        // Duplicate titles
        if (issues.duplicate_titles && issues.duplicate_titles.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-admin-page',
                color: '#f59e0b',
                gscLabel: '<?php echo esc_js(__('Duplicate without user-selected canonical', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Pages with identical SEO titles', 'smart-seo-fixer')); ?>',
                items: issues.duplicate_titles,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-warning">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<button class="button button-primary ssf-fix-btn" data-fix="generate_unique_title" data-post-id="' + item.post_id + '">' +
                            '<span class="dashicons dashicons-superhero-alt"></span> <?php echo esc_js(__('AI Unique Title', 'smart-seo-fixer')); ?>' +
                        '</button>' +
                    '</div>';
                }
            });
        }
        
        // Duplicate descriptions
        if (issues.duplicate_descs && issues.duplicate_descs.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-media-text',
                color: '#f59e0b',
                gscLabel: '<?php echo esc_js(__('Duplicate, Google chose different canonical', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Pages with identical meta descriptions', 'smart-seo-fixer')); ?>',
                items: issues.duplicate_descs,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-warning">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<button class="button button-primary ssf-fix-btn" data-fix="generate_unique_desc" data-post-id="' + item.post_id + '">' +
                            '<span class="dashicons dashicons-superhero-alt"></span> <?php echo esc_js(__('AI Unique Desc', 'smart-seo-fixer')); ?>' +
                        '</button>' +
                    '</div>';
                }
            });
        }
        
        // Not found 404
        if (issues.not_found_404 && issues.not_found_404.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-dismiss',
                color: '#f59e0b',
                gscLabel: '<?php echo esc_js(__('Not found (404)', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Broken URLs returning 404 errors', 'smart-seo-fixer')); ?>',
                items: issues.not_found_404,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-warning">' +
                        '<div class="ssf-audit-item-info">' +
                            '<code>' + escHtml(item.url) + '</code>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + (item.referrer ? ' | Referrer: ' + escHtml(item.referrer) : '') + '</span>' +
                        '</div>' +
                        '<a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-redirects')); ?>" class="button">' +
                            '<span class="dashicons dashicons-migrate"></span> <?php echo esc_js(__('Add Redirect', 'smart-seo-fixer')); ?>' +
                        '</a>' +
                    '</div>';
                }
            });
        }
        
        // === MEDIUM PRIORITY (Blue/Purple) ===
        
        // Trailing slash — always show as handled since canonical/sitemap enforce correct slashes
        html += '<div class="ssf-audit-group">' +
            '<div class="ssf-audit-group-header" style="background: #f0fdf4; border-color: #bbf7d0;">' +
                '<div class="ssf-audit-group-left">' +
                    '<span class="dashicons dashicons-yes-alt" style="color: #059669;"></span>' +
                    '<div>' +
                        '<span class="ssf-audit-gsc-label" style="color: #059669;"><?php echo esc_js(__('Trailing Slash Consistency', 'smart-seo-fixer')); ?></span>' +
                        '<strong style="color: #065f46;"><?php echo esc_js(__('Automatically handled', 'smart-seo-fixer')); ?></strong>' +
                    '</div>' +
                '</div>' +
                '<span class="ssf-audit-count" style="background: #059669;">✓</span>' +
            '</div>' +
            '<div style="padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-top: 0; border-radius: 0 0 8px 8px; font-size: 13px; color: #065f46;">' +
                '<?php echo esc_js(__('Canonical tags, sitemap URLs, and Open Graph URLs all enforce correct trailing slashes automatically. WordPress also auto-redirects non-slash URLs. No action needed.', 'smart-seo-fixer')); ?>' +
            '</div>' +
        '</div>';
        
        // Redirected published pages
        if (issues.redirected_pages && issues.redirected_pages.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-migrate',
                color: '#3b82f6',
                gscLabel: '<?php echo esc_js(__('Page with redirect', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Published pages with active redirects', 'smart-seo-fixer')); ?>',
                items: issues.redirected_pages,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-medium">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<span class="ssf-audit-tag ssf-tag-review"><?php echo esc_js(__('Review', 'smart-seo-fixer')); ?></span>' +
                    '</div>';
                }
            });
        }
        
        // Custom canonicals
        if (issues.custom_canonical && issues.custom_canonical.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-admin-links',
                color: '#3b82f6',
                gscLabel: '<?php echo esc_js(__('Alternate page with proper canonical tag', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Pages with custom canonical pointing elsewhere', 'smart-seo-fixer')); ?>',
                items: issues.custom_canonical,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-medium">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">→ ' + escHtml(item.canonical) + '</span>' +
                        '</div>' +
                        '<span class="ssf-audit-tag ssf-tag-info"><?php echo esc_js(__('Intentional?', 'smart-seo-fixer')); ?></span>' +
                    '</div>';
                }
            });
        }
        
        // === LOW PRIORITY (Gray/Purple) ===
        
        // Thin content
        if (issues.thin_content && issues.thin_content.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-editor-paragraph',
                color: '#8b5cf6',
                gscLabel: '<?php echo esc_js(__('Crawled - currently not indexed', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Thin content pages (< 300 words)', 'smart-seo-fixer')); ?>',
                items: issues.thin_content,
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-info">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                        '</div>' +
                        '<a href="' + editUrl + item.post_id + '" class="button" target="_blank">' +
                            '<span class="dashicons dashicons-edit"></span> <?php echo esc_js(__('Edit Post', 'smart-seo-fixer')); ?>' +
                        '</a>' +
                    '</div>';
                }
            });
        }
        
        // Orphaned pages
        if (issues.orphaned_pages && issues.orphaned_pages.length > 0) {
            html += renderIssueGroup({
                icon: 'dashicons-networking',
                color: '#8b5cf6',
                gscLabel: '<?php echo esc_js(__('Discovered - currently not indexed', 'smart-seo-fixer')); ?>',
                title: '<?php echo esc_js(__('Orphaned pages (no internal links)', 'smart-seo-fixer')); ?>',
                items: issues.orphaned_pages,
                bulkFix: {
                    type: 'orphaned_pages',
                    label: '<?php echo esc_js(__('Fix All with AI', 'smart-seo-fixer')); ?>'
                },
                renderItem: function(item) {
                    return '<div class="ssf-audit-item ssf-audit-info" id="orphan-item-' + item.post_id + '">' +
                        '<div class="ssf-audit-item-info">' +
                            '<a href="' + editUrl + item.post_id + '" target="_blank"><strong>' + escHtml(item.title) + '</strong></a>' +
                            '<span class="ssf-audit-detail">' + escHtml(item.issue) + '</span>' +
                            '<span class="ssf-orphan-status" id="orphan-status-' + item.post_id + '"></span>' +
                        '</div>' +
                        '<div class="ssf-audit-item-actions">' +
                            '<button class="button ssf-fix-orphan-btn" data-post-id="' + item.post_id + '" data-title="' + escHtml(item.title) + '">' +
                                '<span class="dashicons dashicons-admin-links"></span> <?php echo esc_js(__('Add Link with AI', 'smart-seo-fixer')); ?>' +
                            '</button>' +
                            '<a href="' + escHtml(item.url) + '" class="button" target="_blank">' +
                                '<span class="dashicons dashicons-external"></span>' +
                            '</a>' +
                        '</div>' +
                    '</div>';
                }
            });
        }
        
        $content.html(html);
        $('#fix-all-issues-btn').prop('disabled', total === 0);
        $card.show();
        
        // Scroll to results
        $('html, body').animate({scrollTop: $card.offset().top - 50}, 400);
    }
    
    // Render a grouped issue section
    function renderIssueGroup(opts) {
        var count = opts.items.length;
        var maxShow = 10;
        var html = '<div class="ssf-audit-group">';
        html += '<div class="ssf-audit-group-header">';
        html += '<div class="ssf-audit-group-title">';
        html += '<span class="dashicons ' + opts.icon + '" style="color:' + opts.color + ';"></span>';
        html += '<div>';
        html += '<span class="ssf-gsc-label" style="color:' + opts.color + ';">' + opts.gscLabel + '</span>';
        html += '<strong>' + opts.title + '</strong>';
        html += '</div>';
        html += '<span class="ssf-audit-count" style="background:' + opts.color + ';">' + count + '</span>';
        html += '</div>';
        if (opts.bulkFix) {
            html += '<button class="button button-primary ssf-bulk-fix-btn" data-fix="' + opts.bulkFix.type + '" data-items=\'' + JSON.stringify(opts.items.map(function(i){return i.post_id;})) + '\'>';
            html += '<span class="dashicons dashicons-superhero-alt"></span> ' + opts.bulkFix.label;
            html += '</button>';
        }
        html += '</div>';
        html += '<div class="ssf-audit-items">';
        var groupId = 'ssf-group-' + (opts.bulkFix ? opts.bulkFix.type : Math.random().toString(36).substr(2, 6));
        opts.items.slice(0, maxShow).forEach(function(item) {
            html += opts.renderItem(item);
        });
        if (count > maxShow) {
            html += '<div class="ssf-audit-hidden-items" id="' + groupId + '-hidden" style="display:none;">';
            opts.items.slice(maxShow).forEach(function(item) {
                html += opts.renderItem(item);
            });
            html += '</div>';
            html += '<div class="ssf-audit-more">';
            html += '<button class="button ssf-show-all-btn" data-target="' + groupId + '-hidden">';
            html += '<?php echo esc_js(__('Show all', 'smart-seo-fixer')); ?> ' + count + ' <?php echo esc_js(__('items', 'smart-seo-fixer')); ?>';
            html += '</button>';
            html += '</div>';
        }
        html += '</div></div>';
        return html;
    }
    
    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }
    
    // Individual fix button handler
    $(document).on('click', '.ssf-fix-btn', function() {
        var $btn = $(this);
        var fixType = $btn.data('fix');
        var postId = $btn.data('post-id') || 0;
        var originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update ssf-spin"></span> <?php echo esc_js(__('Fixing...', 'smart-seo-fixer')); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_fix_indexability_issue',
            nonce: ssfAdmin.nonce,
            fix_type: fixType,
            post_id: postId
        }, function(response) {
            if (response.success && response.data.fixed && response.data.fixed.length > 0) {
                var $item = $btn.closest('.ssf-audit-item');
                $item.addClass('ssf-audit-fixed');
                var msg = response.data.message || '<?php echo esc_js(__('Fixed!', 'smart-seo-fixer')); ?>';
                $item.find('.ssf-audit-detail').html('<span style="color:#16a34a;">✓ ' + escHtml(msg) + '</span>');
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__('Fixed', 'smart-seo-fixer')); ?>').addClass('ssf-btn-fixed');
            } else {
                $btn.prop('disabled', false).html(originalHtml);
                var errMsg = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('No changes made. Try again.', 'smart-seo-fixer')); ?>';
                alert(errMsg);
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            alert('<?php echo esc_js(__('Request failed. Check your connection.', 'smart-seo-fixer')); ?>');
        });
    });
    
    // Bulk fix button handler
    $(document).on('click', '.ssf-bulk-fix-btn', function() {
        var $btn = $(this);
        var $group = $btn.closest('.ssf-audit-group');
        var fixType = $btn.data('fix');

        // 'orphaned_pages' has its own dedicated handler (below) that runs the
        // orphan link fix. This generic handler is registered first, so without
        // this guard it also fires and sends an unknown fix_type to the server,
        // producing a bogus "Unknown fix type" error while the real fix works.
        if (fixType === 'orphaned_pages') { return; }

        var items = $btn.data('items') || [];
        var index = 0;
        var okCount = 0;
        var failCount = 0;
        var firstError = '';

        if (!items.length) { return; }
        if (!confirm('<?php echo esc_js(__('This will AI-generate missing SEO data for all listed pages. Continue?', 'smart-seo-fixer')); ?>')) {
            return;
        }

        $btn.prop('disabled', true);

        // Update a single row to reflect success/failure so the user sees
        // exactly what happened instead of the text staying "Missing...".
        function markRow(postId, ok, msg) {
            var $item = $group.find('.ssf-fix-btn[data-post-id="' + postId + '"]').closest('.ssf-audit-item');
            if (!$item.length) { return; }
            var $detail = $item.find('.ssf-audit-detail');
            var $rowBtn = $item.find('.ssf-fix-btn');
            if (ok) {
                $item.addClass('ssf-audit-fixed');
                $detail.html('<span style="color:#16a34a;">✓ ' + escHtml(msg || '<?php echo esc_js(__('Fixed', 'smart-seo-fixer')); ?>') + '</span>');
                $rowBtn.prop('disabled', true).addClass('ssf-btn-fixed')
                       .html('<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__('Fixed', 'smart-seo-fixer')); ?>');
            } else {
                $detail.html('<span style="color:#dc2626;">✗ ' + escHtml(msg || '<?php echo esc_js(__('Could not fix', 'smart-seo-fixer')); ?>') + '</span>');
                $rowBtn.prop('disabled', false);
            }
        }

        function fixNext() {
            if (index >= items.length) {
                var summary = okCount + ' <?php echo esc_js(__('fixed', 'smart-seo-fixer')); ?>, ' +
                              failCount + ' <?php echo esc_js(__('failed', 'smart-seo-fixer')); ?>';
                $btn.prop('disabled', false)
                    .html('<span class="dashicons dashicons-yes-alt"></span> ' + summary)
                    .toggleClass('ssf-btn-fixed', failCount === 0);
                // Surface WHY it failed — most often this is "AI not configured"
                // or the Bedrock model isn't enabled for the account.
                if (failCount > 0 && firstError) {
                    alert('<?php echo esc_js(__('Some pages could not be fixed:', 'smart-seo-fixer')); ?>\n\n' + firstError);
                }
                loadGSCSummary();
                return;
            }

            $btn.html('<span class="dashicons dashicons-update ssf-spin"></span> <?php echo esc_js(__('Fixing', 'smart-seo-fixer')); ?> ' + (index + 1) + '/' + items.length + '...');

            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_fix_indexability_issue',
                nonce: ssfAdmin.nonce,
                fix_type: fixType,
                post_id: items[index]
            }, function(response) {
                if (response.success && response.data && response.data.fixed && response.data.fixed.length > 0) {
                    okCount++;
                    markRow(items[index], true, response.data.message);
                } else {
                    failCount++;
                    var em = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('No changes made.', 'smart-seo-fixer')); ?>';
                    if (!firstError) { firstError = em; }
                    markRow(items[index], false, em);
                }
                index++;
                fixNext();
            }).fail(function() {
                failCount++;
                if (!firstError) { firstError = '<?php echo esc_js(__('Request failed — check your connection.', 'smart-seo-fixer')); ?>'; }
                markRow(items[index], false, '<?php echo esc_js(__('Request failed', 'smart-seo-fixer')); ?>');
                index++;
                fixNext();
            });
        }

        fixNext();
    });
    
    // Show all hidden items in an issue group
    $(document).on('click', '.ssf-show-all-btn', function() {
        var target = $(this).data('target');
        $('#' + target).slideDown(200);
        $(this).closest('.ssf-audit-more').remove();
    });
    
    // Fix single orphaned page with AI internal linking
    $(document).on('click', '.ssf-fix-orphan-btn', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var postTitle = $btn.data('title');
        var originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update ssf-spin"></span> <?php echo esc_js(__('Finding link...', 'smart-seo-fixer')); ?>');
        $('#orphan-status-' + postId).text('').removeClass('ssf-orphan-success ssf-orphan-error');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_fix_orphaned_page',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success && response.data.linked) {
                $btn.closest('.ssf-audit-item').addClass('ssf-audit-fixed');
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__('Linked!', 'smart-seo-fixer')); ?>').addClass('ssf-btn-fixed');
                $('#orphan-status-' + postId).text(response.data.message).addClass('ssf-orphan-success');
            } else {
                $btn.prop('disabled', false).html(originalHtml);
                var msg = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js(__('Could not find a natural link placement.', 'smart-seo-fixer')); ?>';
                $('#orphan-status-' + postId).text(msg).addClass('ssf-orphan-error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            $('#orphan-status-' + postId).text('<?php echo esc_js(__('Request failed. Check connection.', 'smart-seo-fixer')); ?>').addClass('ssf-orphan-error');
        });
    });
    
    // Bulk fix orphaned pages — override the default bulk handler for this type
    $(document).on('click', '.ssf-bulk-fix-btn[data-fix="orphaned_pages"]', function(e) {
        e.stopImmediatePropagation();
        var $btn = $(this);
        var items = $btn.data('items') || [];
        var index = 0;
        var successCount = 0;
        var failCount = 0;
        
        if (!confirm('<?php echo esc_js(__('AI will find natural internal link placements for all orphaned pages. This makes one AI call per page. Continue?', 'smart-seo-fixer')); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true);
        
        function fixNextOrphan() {
            if (index >= items.length) {
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__('Done!', 'smart-seo-fixer')); ?> ' + successCount + '/' + items.length + ' <?php echo esc_js(__('linked', 'smart-seo-fixer')); ?>').addClass('ssf-btn-fixed');
                loadGSCSummary();
                return;
            }
            
            var postId = items[index];
            $btn.html('<span class="dashicons dashicons-update ssf-spin"></span> <?php echo esc_js(__('Linking', 'smart-seo-fixer')); ?> ' + (index + 1) + '/' + items.length + '...');
            
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_fix_orphaned_page',
                nonce: ssfAdmin.nonce,
                post_id: postId
            }, function(response) {
                var $item = $('#orphan-item-' + postId);
                if (response.success && response.data.linked) {
                    successCount++;
                    $item.addClass('ssf-audit-fixed');
                    $item.find('.ssf-fix-orphan-btn').html('<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__('Linked!', 'smart-seo-fixer')); ?>').addClass('ssf-btn-fixed').prop('disabled', true);
                    $('#orphan-status-' + postId).text(response.data.message).addClass('ssf-orphan-success');
                } else {
                    failCount++;
                    var msg = (response.data && response.data.message) ? response.data.message : '';
                    $('#orphan-status-' + postId).text(msg).addClass('ssf-orphan-error');
                }
                index++;
                fixNextOrphan();
            }).fail(function() {
                failCount++;
                index++;
                fixNextOrphan();
            });
        }
        
        fixNextOrphan();
    });
    
    // Fix all issues (legacy + new)
    $('#fix-all-issues-btn').on('click', function() {
        var $btn = $(this);
        if (!confirm('<?php echo esc_js(__('This will fix all auto-fixable issues (redirect chains). For AI-generated content, use the individual buttons. Continue?', 'smart-seo-fixer')); ?>')) {
            return;
        }
        $btn.prop('disabled', true).find('strong').text('<?php echo esc_js(__('Fixing...', 'smart-seo-fixer')); ?>');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_fix_url_issues',
            nonce: ssfAdmin.nonce,
            issue_type: 'all'
        }, function(response) {
            $btn.prop('disabled', false).find('strong').text('<?php echo esc_js(__('Fix All Auto-Fixable', 'smart-seo-fixer')); ?>');
            if (response.success) {
                alert('<?php echo esc_js(__('Auto-fixable issues resolved! Re-scanning...', 'smart-seo-fixer')); ?>');
                $('#scan-url-issues-btn').click();
                loadGSCSummary();
            }
        });
    });
});
</script>

<style>
/* Legend strip */
.ssf-legend-strip {
    display: flex;
    gap: 20px;
    padding: 10px 16px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.ssf-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: #4b5563;
    font-weight: 500;
}
.ssf-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Audit description */
.ssf-audit-description {
    margin: 0 0 16px;
    color: #6b7280;
    font-size: 14px;
}

/* Action button primary variant */
.ssf-action-btn.ssf-action-primary {
    background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
}
.ssf-action-btn.ssf-action-primary .dashicons,
.ssf-action-btn.ssf-action-primary strong,
.ssf-action-btn.ssf-action-primary small {
    color: #fff !important;
}
.ssf-action-btn.ssf-action-primary:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
}

/* Badge */
.ssf-badge {
    background: #ef4444;
    color: #fff;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

/* Audit clean state */
.ssf-audit-clean {
    text-align: center;
    padding: 40px 20px;
    color: #10b981;
}
.ssf-audit-clean .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin-bottom: 10px;
}
.ssf-audit-clean h3 {
    margin: 0 0 8px;
    font-size: 18px;
}
.ssf-audit-clean p {
    margin: 0;
    color: #6b7280;
}

/* Audit groups */
.ssf-audit-group {
    margin-bottom: 20px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}
.ssf-audit-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    gap: 12px;
}
.ssf-audit-group-title {
    display: flex;
    align-items: center;
    gap: 12px;
}
.ssf-audit-group-title .dashicons {
    font-size: 22px;
    width: 22px;
    height: 22px;
}
.ssf-audit-group-title > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.ssf-gsc-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ssf-audit-group-title strong {
    font-size: 14px;
    color: #1f2937;
}
.ssf-audit-count {
    color: #fff;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    min-width: 28px;
    text-align: center;
}
.ssf-audit-items {
    padding: 0;
}
.ssf-audit-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid #f3f4f6;
    gap: 12px;
    transition: all 0.3s ease;
}
.ssf-audit-item:last-child {
    border-bottom: none;
}
.ssf-audit-item-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
    flex: 1;
}
.ssf-audit-item-info a {
    text-decoration: none;
}
.ssf-audit-item-info strong {
    font-size: 13px;
    color: #1f2937;
}
.ssf-audit-item-info code {
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 1px 6px;
    border-radius: 4px;
    word-break: break-all;
}
.ssf-audit-detail {
    font-size: 12px;
    color: #9ca3af;
}

/* Severity colors */
.ssf-audit-critical { border-left: 3px solid #ef4444; }
.ssf-audit-warning { border-left: 3px solid #f59e0b; }
.ssf-audit-medium { border-left: 3px solid #3b82f6; }
.ssf-audit-info { border-left: 3px solid #8b5cf6; }

/* Fixed state */
.ssf-audit-fixed {
    background: #f0fdf4 !important;
    opacity: 0.7;
}
.ssf-btn-fixed {
    background: #10b981 !important;
    border-color: #10b981 !important;
    color: #fff !important;
    cursor: default !important;
}

/* Fix buttons */
.ssf-fix-btn {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px !important;
    padding: 4px 12px !important;
}
.ssf-fix-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
.ssf-bulk-fix-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.ssf-bulk-fix-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Audit tags */
.ssf-audit-tag {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.ssf-tag-auto-handled { background: #d1fae5; color: #065f46; }
.ssf-tag-info { background: #dbeafe; color: #1e40af; }

/* More items */
.ssf-audit-more {
    padding: 10px 18px;
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    font-style: italic;
    background: #f9fafb;
}

/* Orphan fix status */
.ssf-orphan-status {
    display: block;
    font-size: 12px;
    margin-top: 4px;
    line-height: 1.4;
}
.ssf-orphan-success {
    color: #059669;
    font-weight: 500;
}
.ssf-orphan-error {
    color: #dc2626;
}
.ssf-audit-item-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-shrink: 0;
}
.ssf-fix-orphan-btn {
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px !important;
    padding: 4px 12px !important;
}
.ssf-fix-orphan-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Spinner */
@keyframes ssf-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.ssf-spin {
    animation: ssf-spin 1s linear infinite;
}
.ssf-feature-item p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 782px) {
    .ssf-audit-item {
        flex-direction: column;
        align-items: flex-start;
    }
    .ssf-audit-group-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
