<?php
/**
 * Client Report View
 *
 * Admin-only page for generating positive-only SEO reports.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-media-document"></span>
        <?php esc_html_e('Client SEO Report', 'smart-seo-fixer'); ?>
    </h1>

    <!-- ── Report Configuration ── -->
    <div class="ssf-card ssf-report-config" id="ssf-report-config">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('Report Settings', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <div class="ssf-report-options-grid">
                <!-- Report Mode -->
                <div class="ssf-option-group">
                    <label for="ssf-report-mode"><?php esc_html_e('Report Mode', 'smart-seo-fixer'); ?></label>
                    <select id="ssf-report-mode">
                        <option value="positive"><?php esc_html_e('Positive Only — highlights & wins', 'smart-seo-fixer'); ?></option>
                        <option value="full"><?php esc_html_e('Full Report — everything including issues', 'smart-seo-fixer'); ?></option>
                    </select>
                    <p class="description" id="ssf-mode-description"><?php esc_html_e('Show only positive metrics — great for client-facing reports.', 'smart-seo-fixer'); ?></p>
                </div>

                <!-- Date Range -->
                <div class="ssf-option-group">
                    <label for="ssf-report-range"><?php esc_html_e('Date Range', 'smart-seo-fixer'); ?></label>
                    <select id="ssf-report-range">
                        <option value="30"><?php esc_html_e('Last 30 Days', 'smart-seo-fixer'); ?></option>
                        <option value="60"><?php esc_html_e('Last 60 Days', 'smart-seo-fixer'); ?></option>
                        <option value="90"><?php esc_html_e('Last 90 Days', 'smart-seo-fixer'); ?></option>
                        <option value="all"><?php esc_html_e('All Time', 'smart-seo-fixer'); ?></option>
                        <option value="custom"><?php esc_html_e('Custom Range', 'smart-seo-fixer'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Applies to activity sections: Keyword Rankings, Broken Links Fixed, and Optimizations Made. Coverage & score sections always reflect the current state of the site.', 'smart-seo-fixer'); ?></p>
                </div>

                <!-- Custom Dates (hidden by default) -->
                <div class="ssf-option-group ssf-custom-dates" id="ssf-custom-dates" style="display:none;">
                    <label><?php esc_html_e('Custom Dates', 'smart-seo-fixer'); ?></label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="date" id="ssf-report-start" />
                        <span>&ndash;</span>
                        <input type="date" id="ssf-report-end" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" />
                    </div>
                </div>

                <!-- Sections -->
                <div class="ssf-option-group ssf-sections-checkboxes">
                    <label><?php esc_html_e('Sections to Include', 'smart-seo-fixer'); ?></label>
                    <div class="ssf-checkbox-grid">
                        <label><input type="checkbox" name="ssf_section" value="overview" checked /> <?php esc_html_e('Overview & Score', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="meta_coverage" checked /> <?php esc_html_e('Meta Tag Coverage', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="score_distribution" checked /> <?php esc_html_e('Score Distribution', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="score_factors" checked /> <?php esc_html_e('Score Factors (Why)', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="top_pages" checked /> <?php esc_html_e('Top Pages', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="content_health" checked /> <?php esc_html_e('Content Health', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="image_seo" checked /> <?php esc_html_e('Image SEO', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="schema_coverage" checked /> <?php esc_html_e('Schema Coverage', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="redirects" checked /> <?php esc_html_e('Redirects', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="keywords" checked /> <?php esc_html_e('Keyword Rankings', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="broken_links_fixed" checked /> <?php esc_html_e('Broken Links Fixed', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="optimizations" checked /> <?php esc_html_e('Optimizations Made', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="sitemap_status" checked /> <?php esc_html_e('Sitemap Status', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="data_freshness" checked /> <?php esc_html_e('Data Freshness', 'smart-seo-fixer'); ?></label>
                        <label><input type="checkbox" name="ssf_section" value="analytics" checked /> <?php esc_html_e('Website Traffic (GA4)', 'smart-seo-fixer'); ?></label>
                        <label class="ssf-full-mode-only" style="display:none;"><input type="checkbox" name="ssf_section" value="worst_pages" checked /> <?php esc_html_e('Pages Needing Work', 'smart-seo-fixer'); ?></label>
                        <label class="ssf-full-mode-only" style="display:none;"><input type="checkbox" name="ssf_section" value="issues" checked /> <?php esc_html_e('Issues & Recommendations', 'smart-seo-fixer'); ?></label>
                    </div>
                </div>

                <!-- Template URL -->
                <div class="ssf-option-group">
                    <label for="ssf-template-url"><?php esc_html_e('Template URL (optional)', 'smart-seo-fixer'); ?></label>
                    <div style="display:flex; gap:8px; align-items:flex-start;">
                        <input type="url" id="ssf-template-url" placeholder="https://docs.google.com/document/d/..." style="flex:1;" value="<?php echo esc_attr(get_option('ssf_report_template_url', '')); ?>" />
                        <button type="button" class="button" id="ssf-fetch-template">
                            <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px;"></span>
                            <?php esc_html_e('Fetch', 'smart-seo-fixer'); ?>
                        </button>
                        <?php if (get_option('ssf_report_template', '')): ?>
                        <button type="button" class="button" id="ssf-clear-template" style="color:#dc2626;">
                            <span class="dashicons dashicons-dismiss" style="vertical-align:middle;margin-top:-2px;"></span>
                            <?php esc_html_e('Clear', 'smart-seo-fixer'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php esc_html_e('Paste a Google Doc URL or any public HTML page. The document\'s styling will be used as a wrapper for the report. The document must be publicly viewable.', 'smart-seo-fixer'); ?></p>
                    <div id="ssf-template-status">
                        <?php if ($tpl_url = get_option('ssf_report_template_url', '')): ?>
                            <span class="ssf-template-loaded"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Template loaded', 'smart-seo-fixer'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ssf-report-actions">
                <button type="button" class="button button-primary button-hero" id="ssf-generate-report">
                    <span class="dashicons dashicons-analytics"></span>
                    <?php esc_html_e('Generate Report', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button button-secondary button-hero" id="ssf-reanalyze-all" title="<?php esc_attr_e('Re-run the SEO analyzer on every published page so scores reflect the latest content and meta.', 'smart-seo-fixer'); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Re-analyze All Pages', 'smart-seo-fixer'); ?>
                </button>
            </div>
            <div id="ssf-reanalyze-progress" style="display:none; margin-top:12px; padding:14px; background:#f0f9ff; border-left:4px solid #0284c7; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong id="ssf-reanalyze-label"><?php esc_html_e('Re-analyzing pages…', 'smart-seo-fixer'); ?></strong>
                    <span id="ssf-reanalyze-count" style="color:#64748b; font-size:13px;">0 / 0</span>
                </div>
                <div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                    <div id="ssf-reanalyze-bar" style="height:100%; width:0%; background:#0284c7; transition:width .3s;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Report Output (hidden until generated) ── -->
    <div id="ssf-report-output" style="display:none;">

        <!-- Report Action Bar -->
        <div class="ssf-report-action-bar">
            <button type="button" class="button" id="ssf-print-report">
                <span class="dashicons dashicons-printer"></span>
                <?php esc_html_e('Print Report', 'smart-seo-fixer'); ?>
            </button>
            <button type="button" class="button button-primary" id="ssf-download-pdf">
                <span class="dashicons dashicons-pdf"></span>
                <?php esc_html_e('Download PDF', 'smart-seo-fixer'); ?>
            </button>
            <button type="button" class="button" id="ssf-new-report">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('New Report', 'smart-seo-fixer'); ?>
            </button>
        </div>

        <!-- Printable Report Container -->
        <div class="ssf-report" id="ssf-report">

            <!-- Cover / Header -->
            <div class="ssf-report-cover">
                <div class="ssf-report-cover-icon" id="ssf-report-icon"></div>
                <h1 class="ssf-report-site-name" id="ssf-report-site-name"></h1>
                <p class="ssf-report-tagline" id="ssf-report-tagline"></p>
                <p class="ssf-report-url" id="ssf-report-url"></p>
                <div class="ssf-report-period" id="ssf-report-period"></div>
                <p class="ssf-report-generated" id="ssf-report-generated"></p>
            </div>

            <!-- Overall SEO Score -->
            <div class="ssf-report-section" id="ssf-section-overview">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-awards"></span>
                    <?php esc_html_e('SEO Optimization Score', 'smart-seo-fixer'); ?>
                </h2>
                <div class="ssf-report-score-ring-wrap">
                    <div class="ssf-report-score-ring" id="ssf-report-score-ring">
                        <svg viewBox="0 0 120 120">
                            <circle class="ssf-ring-bg" cx="60" cy="60" r="54" />
                            <circle class="ssf-ring-fg" cx="60" cy="60" r="54" id="ssf-ring-fg" />
                        </svg>
                        <span class="ssf-ring-value" id="ssf-ring-value">0</span>
                    </div>
                    <div class="ssf-report-grade" id="ssf-report-grade"></div>
                </div>
                <div class="ssf-report-overview-grid" id="ssf-overview-grid"></div>
            </div>

            <!-- Meta Tag Coverage -->
            <div class="ssf-report-section" id="ssf-section-meta_coverage">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-editor-code"></span>
                    <?php esc_html_e('Meta Tag Coverage', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-meta-coverage-content"></div>
            </div>

            <!-- Score Distribution -->
            <div class="ssf-report-section" id="ssf-section-score_distribution">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e('Score Distribution', 'smart-seo-fixer'); ?>
                </h2>
                <div class="ssf-report-bar-chart" id="ssf-dist-chart"></div>
            </div>

            <!-- Score Factors -->
            <div class="ssf-report-section" id="ssf-section-score_factors" style="display:none;">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e('Why Your Score Is What It Is', 'smart-seo-fixer'); ?>
                </h2>
                <p class="ssf-report-note" id="ssf-score-factors-subtitle"></p>
                <div id="ssf-score-factors-list"></div>
            </div>

            <!-- Top Pages -->
            <div class="ssf-report-section" id="ssf-section-top_pages">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Top Performing Pages', 'smart-seo-fixer'); ?>
                </h2>
                <table class="ssf-report-table" id="ssf-top-pages-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('#', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Page', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Score', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Grade', 'smart-seo-fixer'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Content Health -->
            <div class="ssf-report-section" id="ssf-section-content_health">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-text-page"></span>
                    <?php esc_html_e('Content Health', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-content-health-content"></div>
            </div>

            <!-- Image SEO -->
            <div class="ssf-report-section" id="ssf-section-image_seo">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e('Image SEO', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-image-seo-content"></div>
            </div>

            <!-- Schema Coverage -->
            <div class="ssf-report-section" id="ssf-section-schema_coverage">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-shortcode"></span>
                    <?php esc_html_e('Schema Markup Coverage', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-schema-coverage-content"></div>
            </div>

            <!-- Redirects -->
            <div class="ssf-report-section" id="ssf-section-redirects">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-randomize"></span>
                    <?php esc_html_e('Redirect Management', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-redirects-content"></div>
            </div>

            <!-- Keyword Rankings -->
            <div class="ssf-report-section" id="ssf-section-keywords">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-tag"></span>
                    <?php esc_html_e('Keyword Rankings', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-keywords-content"></div>
            </div>

            <!-- Broken Links Fixed -->
            <div class="ssf-report-section" id="ssf-section-broken_links_fixed">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Link Health', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-broken-links-content"></div>
            </div>

            <!-- Optimizations Made -->
            <div class="ssf-report-section" id="ssf-section-optimizations">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-hammer"></span>
                    <?php esc_html_e('SEO Optimizations Performed', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-optimizations-content"></div>
            </div>

            <!-- Sitemap Status -->
            <div class="ssf-report-section" id="ssf-section-sitemap_status">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-networking"></span>
                    <?php esc_html_e('Sitemap Status', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-sitemap-status-content"></div>
            </div>

            <!-- Data Freshness -->
            <div class="ssf-report-section" id="ssf-section-data_freshness">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Data Freshness', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-data-freshness-content"></div>
            </div>

            <!-- Google Analytics (GA4) -->
            <div class="ssf-report-section" id="ssf-section-analytics">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php esc_html_e('Website Traffic (Google Analytics)', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-analytics-content"></div>
            </div>

            <!-- Pages Needing Work (full mode) -->
            <div class="ssf-report-section" id="ssf-section-worst_pages">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Pages Needing Improvement', 'smart-seo-fixer'); ?>
                </h2>
                <table class="ssf-report-table" id="ssf-worst-pages-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('#', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Page', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Score', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Issues', 'smart-seo-fixer'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Issues & Recommendations (full mode) -->
            <div class="ssf-report-section" id="ssf-section-issues">
                <h2 class="ssf-report-section-title">
                    <span class="dashicons dashicons-flag"></span>
                    <?php esc_html_e('Issues & Recommendations', 'smart-seo-fixer'); ?>
                </h2>
                <div id="ssf-issues-content"></div>
            </div>

        </div><!-- .ssf-report -->
    </div><!-- #ssf-report-output -->

    <!-- AI Fix Progress Modal -->
    <div class="ssf-modal" id="ssf-factor-fix-modal" style="display:none;">
        <div class="ssf-modal-content">
            <div class="ssf-modal-header">
                <h3 id="ssf-factor-fix-title"><?php esc_html_e('AI Fix in Progress', 'smart-seo-fixer'); ?></h3>
                <button type="button" class="ssf-modal-close" id="ssf-factor-fix-close">&times;</button>
            </div>
            <div class="ssf-modal-body">
                <p id="ssf-factor-fix-status"></p>
                <div class="ssf-progress-bar">
                    <div class="ssf-progress-fill" id="ssf-factor-fix-bar"></div>
                </div>
                <div class="ssf-factor-fix-log" id="ssf-factor-fix-log"></div>
            </div>
        </div>
    </div>

</div>
