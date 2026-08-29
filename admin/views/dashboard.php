<?php
/**
 * Dashboard View
 * Clean overview with SEO health, quick actions, and organized tool navigation.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ssf-wrap">
    <?php SSF_Admin::page_header(__('Smart SEO Fixer', 'smart-seo-fixer')); ?>
    
    <?php if (class_exists('SSF_AI') && !SSF_AI::is_configured()): ?>
    <div class="ssf-notice ssf-notice-warning">
        <p>
            <strong><?php esc_html_e('AWS Bedrock Required:', 'smart-seo-fixer'); ?></strong>
            <?php esc_html_e('Add your AWS Bedrock credentials in', 'smart-seo-fixer'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-settings')); ?>">
                <?php esc_html_e('Settings', 'smart-seo-fixer'); ?>
            </a>
            <?php esc_html_e('to enable AI-powered features.', 'smart-seo-fixer'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="ssf-dashboard" id="ssf-dashboard">

        <!-- SEO Health Card -->
        <div class="ssf-health-card">
            <div class="ssf-health-score">
                <div class="ssf-score-ring" id="score-ring">
                    <svg viewBox="0 0 120 120">
                        <circle class="ssf-ring-bg" cx="60" cy="60" r="52" />
                        <circle class="ssf-ring-fill" id="score-ring-fill" cx="60" cy="60" r="52" stroke-dasharray="327" stroke-dashoffset="327" />
                    </svg>
                    <div class="ssf-ring-text">
                        <span class="ssf-ring-value" id="stat-avg-score">&mdash;</span>
                        <span class="ssf-ring-label"><?php esc_html_e('SEO Score', 'smart-seo-fixer'); ?></span>
                    </div>
                </div>
            </div>
            <div class="ssf-health-details">
                <div class="ssf-health-bar-wrap">
                    <div class="ssf-health-bar">
                        <div class="ssf-bar-good" id="bar-good" style="width:0%"></div>
                        <div class="ssf-bar-ok" id="bar-ok" style="width:0%"></div>
                        <div class="ssf-bar-poor" id="bar-poor" style="width:0%"></div>
                    </div>
                </div>
                <div class="ssf-health-metrics">
                    <div class="ssf-metric ssf-metric-good">
                        <span class="ssf-metric-dot"></span>
                        <span class="ssf-metric-value" id="stat-good">&mdash;</span>
                        <span class="ssf-metric-label"><?php esc_html_e('Good', 'smart-seo-fixer'); ?></span>
                    </div>
                    <div class="ssf-metric ssf-metric-ok">
                        <span class="ssf-metric-dot"></span>
                        <span class="ssf-metric-value" id="stat-ok">&mdash;</span>
                        <span class="ssf-metric-label"><?php esc_html_e('OK', 'smart-seo-fixer'); ?></span>
                    </div>
                    <div class="ssf-metric ssf-metric-poor">
                        <span class="ssf-metric-dot"></span>
                        <span class="ssf-metric-value" id="stat-poor">&mdash;</span>
                        <span class="ssf-metric-label"><?php esc_html_e('Needs Work', 'smart-seo-fixer'); ?></span>
                    </div>
                    <div class="ssf-metric ssf-metric-unanalyzed">
                        <span class="ssf-metric-dot"></span>
                        <span class="ssf-metric-value" id="stat-unanalyzed">&mdash;</span>
                        <span class="ssf-metric-label"><?php esc_html_e('Unanalyzed', 'smart-seo-fixer'); ?></span>
                    </div>
                </div>
                <!-- Hidden elements to keep JS compatibility -->
                <span id="stat-missing-titles" style="display:none;">&mdash;</span>
                <span id="stat-card-missing" style="display:none;"></span>
            </div>
        </div>

        <!-- Missing SEO Alert Banner (conditional, populated by JS) -->
        <div class="ssf-missing-seo-banner" id="missing-seo-banner" style="display:none;">
            <div class="ssf-banner-content">
                <strong id="missing-banner-title"><?php esc_html_e('Posts missing AI-generated SEO', 'smart-seo-fixer'); ?></strong>
                <p id="missing-banner-desc"></p>
            </div>
            <div class="ssf-banner-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-bulk-fix&auto=missing')); ?>" class="button button-primary">
                    <?php esc_html_e('Fix Now', 'smart-seo-fixer'); ?>
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="ssf-quick-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-analyzer')); ?>" class="ssf-quick-btn ssf-quick-analyze">
                <?php esc_html_e('Analyze Posts', 'smart-seo-fixer'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-bulk-fix')); ?>" class="ssf-quick-btn ssf-quick-fix">
                <?php esc_html_e('Bulk AI Fix', 'smart-seo-fixer'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-posts')); ?>" class="ssf-quick-btn ssf-quick-posts">
                <?php esc_html_e('All Posts', 'smart-seo-fixer'); ?>
            </a>
        </div>

        <!-- Posts: Needs Attention + Recently Analyzed -->
        <div class="ssf-content-grid">
            <div class="ssf-card ssf-card-attention">
                <div class="ssf-card-header">
                    <h2><?php esc_html_e('Needs Attention', 'smart-seo-fixer'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-analyzer&score_filter=poor')); ?>" class="ssf-link">
                        <?php esc_html_e('View All', 'smart-seo-fixer'); ?>
                    </a>
                </div>
                <div class="ssf-card-body">
                    <div class="ssf-post-list" id="needs-attention-list">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="ssf-skeleton ssf-skeleton-row"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="ssf-card ssf-card-recent">
                <div class="ssf-card-header">
                    <h2><?php esc_html_e('Recently Analyzed', 'smart-seo-fixer'); ?></h2>
                </div>
                <div class="ssf-card-body">
                    <div class="ssf-post-list" id="recent-list">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="ssf-skeleton ssf-skeleton-row"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools — Organized by Category -->
        <div class="ssf-tools-section">
            <h3 class="ssf-tools-heading"><?php esc_html_e('Content & Analysis', 'smart-seo-fixer'); ?></h3>
            <div class="ssf-tools-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-schema')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Schema', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-social-preview')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Social Preview', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-content-suggestions')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Content Tips', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-keywords')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Keywords', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-local')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Local SEO', 'smart-seo-fixer'); ?>
                </a>
            </div>

            <h3 class="ssf-tools-heading"><?php esc_html_e('Technical SEO', 'smart-seo-fixer'); ?></h3>
            <div class="ssf-tools-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-redirects')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Redirects', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-404-monitor')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('404 Monitor', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-broken-links')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Broken Links', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-robots')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('robots.txt', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-gsc')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Indexability', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-search-performance')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Search Perf.', 'smart-seo-fixer'); ?>
                </a>
            </div>

            <h3 class="ssf-tools-heading"><?php esc_html_e('Reports & Admin', 'smart-seo-fixer'); ?></h3>
            <div class="ssf-tools-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-history')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('History', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-settings')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Settings', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-migration')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Migration', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-jobs')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Jobs', 'smart-seo-fixer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-debug-log')); ?>" class="ssf-tool-link">
                    <?php esc_html_e('Debug Log', 'smart-seo-fixer'); ?>
                </a>
            </div>
        </div>

    </div>
</div>

<style>
/* ── SEO Health Card ─────────────────────────────── */
.ssf-health-card {
    display: flex;
    align-items: center;
    gap: 40px;
    background: #fff;
    border: 1px solid var(--ssf-gray-200);
    border-radius: var(--ssf-radius);
    padding: 28px 32px;
    margin-bottom: 20px;
}

.ssf-health-score {
    flex-shrink: 0;
}

.ssf-score-ring {
    position: relative;
    width: 120px;
    height: 120px;
}

.ssf-score-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.ssf-ring-bg {
    fill: none;
    stroke: var(--ssf-gray-100);
    stroke-width: 10;
}

.ssf-ring-fill {
    fill: none;
    stroke: var(--ssf-success);
    stroke-width: 10;
    stroke-linecap: round;
    transition: stroke-dashoffset 1s ease, stroke 0.4s;
}

.ssf-ring-text {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.ssf-ring-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--ssf-gray-900);
    line-height: 1;
}

.ssf-ring-label {
    font-size: 11px;
    color: var(--ssf-gray-500);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ssf-health-details {
    flex: 1;
    min-width: 0;
}

/* Distribution bar */
.ssf-health-bar-wrap {
    margin-bottom: 16px;
}

.ssf-health-bar {
    display: flex;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    background: var(--ssf-gray-100);
}

.ssf-bar-good { background: var(--ssf-success); transition: width 0.8s ease; }
.ssf-bar-ok { background: var(--ssf-warning); transition: width 0.8s ease; }
.ssf-bar-poor { background: var(--ssf-danger); transition: width 0.8s ease; }

/* Metric chips */
.ssf-health-metrics {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}

.ssf-metric {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ssf-metric-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ssf-metric-good .ssf-metric-dot { background: var(--ssf-success); }
.ssf-metric-ok .ssf-metric-dot { background: var(--ssf-warning); }
.ssf-metric-poor .ssf-metric-dot { background: var(--ssf-danger); }
.ssf-metric-unanalyzed .ssf-metric-dot { background: var(--ssf-gray-300); }

.ssf-metric-value {
    font-weight: 700;
    font-size: 18px;
    color: var(--ssf-gray-900);
    line-height: 1;
}

.ssf-metric-label {
    font-size: 13px;
    color: var(--ssf-gray-500);
}

/* ── Missing SEO Banner ──────────────────────────── */
.ssf-missing-seo-banner { display: flex; align-items: center; gap: 16px; padding: 14px 20px; margin-bottom: 20px; background: #fefce8; border: 1px solid #facc15; border-left: 4px solid #d97706; border-radius: var(--ssf-radius); }
.ssf-banner-content { flex: 1; }
.ssf-banner-content strong { display: block; color: #92400e; font-size: 13px; margin-bottom: 2px; }
.ssf-banner-content p { margin: 0; color: #78350f; font-size: 12px; line-height: 1.4; }
.ssf-banner-actions { flex-shrink: 0; }
.ssf-banner-actions .button-primary { background: #d97706; border-color: #b45309; padding: 5px 14px; height: auto; display: flex; align-items: center; gap: 5px; font-weight: 600; font-size: 12px; white-space: nowrap; text-decoration: none; }
.ssf-banner-actions .button-primary:hover { background: #b45309; border-color: #92400e; }

/* ── Quick Actions ───────────────────────────────── */
.ssf-quick-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.ssf-quick-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: var(--ssf-radius-sm);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.15s var(--ssf-ease), border-color 0.15s var(--ssf-ease);
    border: 1px solid transparent;
}

/* One accent color, used once — the primary action. The other two are
   neutral/secondary, matching the rest of the button system. */
.ssf-quick-analyze { background: var(--ssf-primary); color: #fff; }
.ssf-quick-analyze:hover { background: var(--ssf-primary-dark); color: #fff; }
.ssf-quick-fix,
.ssf-quick-posts {
    background: #fff;
    color: var(--ssf-gray-700);
    border-color: var(--ssf-gray-300);
}
.ssf-quick-fix:hover,
.ssf-quick-posts:hover {
    background: var(--ssf-gray-50);
    border-color: var(--ssf-gray-400);
    color: var(--ssf-gray-900);
}

/* ── Tools Section ───────────────────────────────── */
.ssf-tools-section {
    background: #fff;
    border: 1px solid var(--ssf-gray-200);
    border-radius: var(--ssf-radius);
    padding: 24px 28px;
    margin-top: 24px;
}

.ssf-tools-heading {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--ssf-gray-500);
    margin: 0 0 10px 0;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--ssf-gray-100);
}

.ssf-tools-heading:not(:first-child) {
    margin-top: 20px;
}

.ssf-tools-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 4px;
}

.ssf-tool-link {
    display: inline-flex;
    align-items: center;
    padding: 7px 14px;
    background: var(--ssf-gray-50);
    border: 1px solid var(--ssf-gray-200);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: var(--ssf-gray-700);
    text-decoration: none;
    transition: all 0.15s;
}

.ssf-tool-link:hover {
    background: #fff;
    border-color: var(--ssf-primary);
    color: var(--ssf-primary);
    box-shadow: 0 1px 4px rgba(37,99,235,0.12);
}

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 782px) {
    .ssf-health-card {
        flex-direction: column;
        align-items: stretch;
        gap: 20px;
        padding: 20px;
        text-align: center;
    }
    .ssf-health-score {
        display: flex;
        justify-content: center;
    }
    .ssf-health-metrics {
        justify-content: center;
    }
    .ssf-quick-actions {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    function setRingScore(score) {
        var circumference = 2 * Math.PI * 52; // ~327
        var offset = circumference - (score / 100) * circumference;
        var $fill = $('#score-ring-fill');
        $fill.css('stroke-dashoffset', offset);
        // Color based on score
        if (score >= 80) $fill.css('stroke', 'var(--ssf-success)');
        else if (score >= 60) $fill.css('stroke', 'var(--ssf-warning)');
        else $fill.css('stroke', 'var(--ssf-danger)');
    }
    
    function loadDashboardStats() {
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_dashboard_stats',
            nonce: ssfAdmin.nonce
        }, function(response) {
            if (response.success) {
                var d = response.data;
                var avg = d.avg_score || 0;
                var good = d.good_count || 0;
                var ok = d.ok_count || 0;
                var poor = d.poor_count || 0;
                var unanalyzed = d.unanalyzed || 0;
                var total = good + ok + poor;
                
                // Update score ring
                $('#stat-avg-score').text(avg);
                setRingScore(avg);
                
                // Update metrics
                $('#stat-good').text(good);
                $('#stat-ok').text(ok);
                $('#stat-poor').text(poor);
                $('#stat-unanalyzed').text(unanalyzed);
                
                // Update distribution bar
                if (total > 0) {
                    $('#bar-good').css('width', (good / total * 100) + '%');
                    $('#bar-ok').css('width', (ok / total * 100) + '%');
                    $('#bar-poor').css('width', (poor / total * 100) + '%');
                }
                
                // Missing SEO data
                var mt = d.missing_titles || 0, md = d.missing_descs || 0;
                var total_missing = Math.max(mt, md);
                $('#stat-missing-titles').text(total_missing);
                total_missing > 0 ? $('#stat-card-missing').show() : $('#stat-card-missing').hide();
                
                if (mt > 0 || md > 0) {
                    var parts = [];
                    if (mt > 0) parts.push(mt + ' <?php echo esc_js(__('missing SEO titles', 'smart-seo-fixer')); ?>');
                    if (md > 0) parts.push(md + ' <?php echo esc_js(__('missing meta descriptions', 'smart-seo-fixer')); ?>');
                    $('#missing-banner-desc').text(parts.join(', ') + '. <?php echo esc_js(__('Go to Bulk AI Fix to review and fix them.', 'smart-seo-fixer')); ?>');
                    $('#missing-seo-banner').slideDown(300);
                } else {
                    $('#missing-seo-banner').slideUp(200);
                }
                
                renderPostList('#needs-attention-list', d.needs_attention);
                renderPostList('#recent-list', d.recent);
            } else {
                $('#needs-attention-list, #recent-list').html('<p class="ssf-empty"><?php echo esc_js(__('Could not load data.', 'smart-seo-fixer')); ?></p>');
            }
        });
    }
    
    function renderPostList(sel, posts) {
        var $c = $(sel);
        if (!posts || !posts.length) { $c.html('<p class="ssf-empty"><?php echo esc_js(__('No posts found.', 'smart-seo-fixer')); ?></p>'); return; }
        var h = '';
        posts.forEach(function(p) {
            var sc = p.score >= 80 ? 'good' : (p.score >= 60 ? 'ok' : 'poor');
            h += '<div class="ssf-post-item"><a href="<?php echo esc_url(admin_url('post.php?action=edit&post=')); ?>' + p.post_id + '" class="ssf-post-title">' + esc(p.post_title) + '</a><span class="ssf-score ssf-score-' + sc + '">' + p.score + '</span></div>';
        });
        $c.html(h);
    }
    
    loadDashboardStats();
});
</script>
