/**
 * Smart SEO Fixer - Admin JavaScript
 */

(function($) {
    'use strict';
    
    var SSF = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // General UI events are handled inline in the views
            // This file provides utility functions
        },
        
        /**
         * Make AJAX request with error handling
         */
        ajax: function(action, data, callback, errorCallback) {
            data = data || {};
            data.action = action;
            data.nonce = ssfAdmin.nonce;
            
            $.post(ssfAdmin.ajax_url, data, function(response) {
                if (callback) {
                    callback(response);
                }
            }).fail(function(xhr, status, error) {
                console.error('SSF AJAX Error (' + action + '):', error, xhr.responseText);
                if (errorCallback) {
                    errorCallback(error, xhr);
                } else if (callback) {
                    callback({ success: false, data: { message: error || 'Request failed' } });
                }
            });
        },
        
        /**
         * Analyze a post
         */
        analyzePost: function(postId, callback) {
            this.ajax('ssf_analyze_post', {post_id: postId}, callback);
        },
        
        /**
         * Generate title with AI
         */
        generateTitle: function(postId, focusKeyword, callback) {
            this.ajax('ssf_generate_title', {
                post_id: postId,
                focus_keyword: focusKeyword
            }, callback);
        },
        
        /**
         * Generate meta description with AI
         */
        generateDescription: function(postId, focusKeyword, callback) {
            this.ajax('ssf_generate_description', {
                post_id: postId,
                focus_keyword: focusKeyword
            }, callback);
        },
        
        /**
         * Suggest keywords
         */
        suggestKeywords: function(postId, callback) {
            this.ajax('ssf_suggest_keywords', {post_id: postId}, callback);
        },
        
        /**
         * Save SEO data
         */
        saveSeoData: function(postId, data, callback) {
            data.post_id = postId;
            this.ajax('ssf_save_seo_data', data, callback);
        },
        
        /**
         * Get score class based on score value
         */
        getScoreClass: function(score) {
            if (score >= 80) return 'good';
            if (score >= 60) return 'ok';
            return 'poor';
        },
        
        /**
         * Get grade based on score
         */
        getGrade: function(score) {
            if (score >= 90) return 'A';
            if (score >= 80) return 'B';
            if (score >= 70) return 'C';
            if (score >= 60) return 'D';
            return 'F';
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Show notification
         */
        notify: function(message, type) {
            type = type || 'success';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').first().after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Show loading state on button
         */
        buttonLoading: function($btn, loading) {
            if (loading) {
                $btn.data('original-html', $btn.html());
                $btn.prop('disabled', true);
                $btn.find('.dashicons').addClass('spin');
            } else {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('spin');
                
                var originalHtml = $btn.data('original-html');
                if (originalHtml) {
                    $btn.html(originalHtml);
                }
            }
        },
        
        /**
         * Icon-free loading state for a button — plain CSS spinner
         * (.ssf-spinner), no dashicon. Separate from buttonLoading() above
         * (which assumes a dashicon already inside the button); used on
         * pages migrated off dashicons (dashboard, settings). Two modes:
         *
         *   setLoading($btn, true)              — keep the button's label
         *     (hidden) and overlay a centered spinner on top. Use when the
         *     button shouldn't change size/text, just show "something's
         *     happening" (e.g. Auto-Create, Refresh).
         *   setLoading($btn, true, 'Checking…') — replace the label outright
         *     with "[spinner] Checking…". Use when a status message is
         *     useful (e.g. Connect to Google, Test Connection).
         */
        setLoading: function($btn, isLoading, loadingText) {
            if (isLoading) {
                if ($btn.data('ssf-original-html') === undefined) {
                    $btn.data('ssf-original-html', $btn.html());
                }
                var spinnerClass = $btn.hasClass('button-primary') ? 'ssf-spinner ssf-spinner-light' : 'ssf-spinner';
                if (loadingText) {
                    $btn.prop('disabled', true)
                        .html('<span class="' + spinnerClass + '"></span> ' + this.escapeHtml(loadingText));
                } else {
                    $btn.addClass('ssf-is-loading').prop('disabled', true)
                        .append('<span class="' + spinnerClass + '"></span>');
                }
            } else {
                $btn.removeClass('ssf-is-loading').prop('disabled', false);
                var original = $btn.data('ssf-original-html');
                if (original !== undefined) {
                    $btn.html(original);
                }
            }
        },

        /**
         * Format date
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        
        /**
         * Truncate text
         */
        truncate: function(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        },
        
        /**
         * Bulk process posts
         */
        bulkProcess: function(postIds, action, options, progressCallback, completeCallback) {
            var self = this;
            var processed = 0;
            var total = postIds.length;
            var results = [];
            
            function processNext() {
                if (processed >= total) {
                    if (completeCallback) {
                        completeCallback(results);
                    }
                    return;
                }
                
                var postId = postIds[processed];
                
                self.ajax(action, $.extend({post_id: postId}, options), function(response) {
                    processed++;
                    results.push({
                        post_id: postId,
                        success: response.success,
                        data: response.data
                    });
                    
                    if (progressCallback) {
                        progressCallback(processed, total, response);
                    }
                    
                    // Process next with slight delay to avoid overwhelming server
                    setTimeout(processNext, 500);
                });
            }
            
            processNext();
        },
        
        /**
         * Render analysis results
         */
        renderAnalysis: function($container, data) {
            var html = '';
            
            // Issues
            if (data.issues && data.issues.length) {
                html += '<div class="ssf-issues-section">';
                html += '<h4>Issues</h4>';
                data.issues.forEach(function(issue) {
                    html += '<div class="ssf-issue-item">';
                    html += '<span class="dashicons dashicons-warning"></span>';
                    html += '<span>' + this.escapeHtml(issue.message) + '</span>';
                    if (issue.fix) {
                        html += '<small>' + this.escapeHtml(issue.fix) + '</small>';
                    }
                    html += '</div>';
                }.bind(this));
                html += '</div>';
            }
            
            // Warnings
            if (data.warnings && data.warnings.length) {
                html += '<div class="ssf-warnings-section">';
                html += '<h4>Warnings</h4>';
                data.warnings.forEach(function(warning) {
                    html += '<div class="ssf-warning-item">';
                    html += '<span class="dashicons dashicons-info"></span>';
                    html += '<span>' + this.escapeHtml(warning.message) + '</span>';
                    html += '</div>';
                }.bind(this));
                html += '</div>';
            }
            
            // Passed
            if (data.passed && data.passed.length) {
                html += '<div class="ssf-passed-section">';
                html += '<h4>Passed</h4>';
                data.passed.forEach(function(item) {
                    html += '<div class="ssf-passed-item">';
                    html += '<span class="dashicons dashicons-yes-alt"></span>';
                    html += '<span>' + this.escapeHtml(item.message) + '</span>';
                    html += '</div>';
                }.bind(this));
                html += '</div>';
            }
            
            $container.html(html);
        },

        /**
         * Modal replacement for window.confirm() — same "ask, then act only
         * if yes" shape, but async: pass the code that should run on
         * confirmation as onConfirm instead of relying on a return value.
         *
         *   SSF.confirm('Delete this redirect?', function() { ...do it... });
         *
         * options: { title, confirmText, cancelText, danger, onCancel }
         * onCancel fires on any dismissal that isn't a confirm — the Cancel
         * button, a backdrop click, or Escape — useful for reverting UI that
         * already changed optimistically before the modal appeared (e.g. a
         * checkbox the browser toggles before 'change' fires).
         */
        confirm: function(message, onConfirm, options) {
            options = options || {};
            var $existing = $('.ssf-confirm-modal, .ssf-alert-modal');
            if ($existing.length) $existing.remove();

            var confirmClass = 'button button-primary ssf-modal-confirm' + (options.danger ? ' ssf-modal-confirm-danger' : '');
            var $modal = $(
                '<div class="ssf-modal ssf-confirm-modal">' +
                    '<div class="ssf-modal-content">' +
                        (options.title ? '<div class="ssf-modal-header"><h3>' + this.escapeHtml(options.title) + '</h3></div>' : '') +
                        '<div class="ssf-modal-body"><p style="margin:0;">' + this.escapeHtml(message) + '</p></div>' +
                        '<div class="ssf-modal-footer">' +
                            '<button type="button" class="button ssf-modal-cancel">' + this.escapeHtml(options.cancelText || 'Cancel') + '</button>' +
                            '<button type="button" class="' + confirmClass + '">' + this.escapeHtml(options.confirmText || 'Confirm') + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            var confirmed = false;
            function close() {
                $modal.remove();
                $(document).off('keydown.ssfModal');
                if (!confirmed && options.onCancel) options.onCancel();
            }

            $modal.on('click', function(e) { if (e.target === this) close(); });
            $modal.find('.ssf-modal-cancel').on('click', close);
            $modal.find('.ssf-modal-confirm').on('click', function() {
                confirmed = true;
                close();
                if (onConfirm) onConfirm();
            });
            $(document).on('keydown.ssfModal', function(e) {
                if (e.key === 'Escape') close();
            });

            $('body').append($modal);
            $modal.find('.ssf-modal-confirm').trigger('focus');
        },

        /**
         * Modal replacement for window.alert() — fire-and-forget, no return
         * value to wait on, so every existing alert(msg) call is a direct
         * swap for SSF.alert(msg).
         */
        alert: function(message, options) {
            options = options || {};
            var $existing = $('.ssf-confirm-modal, .ssf-alert-modal');
            if ($existing.length) $existing.remove();

            var $modal = $(
                '<div class="ssf-modal ssf-alert-modal">' +
                    '<div class="ssf-modal-content">' +
                        (options.title ? '<div class="ssf-modal-header"><h3>' + this.escapeHtml(options.title) + '</h3></div>' : '') +
                        '<div class="ssf-modal-body"><p style="margin:0;">' + this.escapeHtml(message) + '</p></div>' +
                        '<div class="ssf-modal-footer">' +
                            '<button type="button" class="button button-primary ssf-modal-ok">' + this.escapeHtml(options.okText || 'OK') + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );

            function close() {
                $modal.remove();
                $(document).off('keydown.ssfModal');
                if (options.onClose) options.onClose();
            }

            $modal.on('click', function(e) { if (e.target === this) close(); });
            $modal.find('.ssf-modal-ok').on('click', close);
            $(document).on('keydown.ssfModal', function(e) {
                if (e.key === 'Escape' || e.key === 'Enter') close();
            });

            $('body').append($modal);
            $modal.find('.ssf-modal-ok').trigger('focus');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SSF.init();
        SSF_ClientReport.init();
    });
    
    // Expose to global scope
    window.SSF = SSF;

    /* ======================================================================
       Client Report Module
       ====================================================================== */
    var SSF_ClientReport = {
        init: function() {
            if (!$('#ssf-report-config').length) return;

            // Toggle custom dates
            $('#ssf-report-range').on('change', function() {
                $('#ssf-custom-dates').toggle($(this).val() === 'custom');
            });

            // Toggle full-mode-only checkboxes and description
            $('#ssf-report-mode').on('change', function() {
                var isFull = $(this).val() === 'full';
                $('.ssf-full-mode-only').toggle(isFull);
                $('#ssf-mode-description').text(isFull
                    ? 'Show everything — positive results, issues, pages needing work, and recommendations.'
                    : 'Show only positive metrics — great for client-facing reports.');
            });

            $('#ssf-generate-report').on('click', this.generate.bind(this));
            $('#ssf-reanalyze-all').on('click', this.reanalyzeAll.bind(this));
            $('#ssf-print-report').on('click', function() { window.print(); });
            $('#ssf-download-pdf').on('click', this.downloadPDF.bind(this));
            $('#ssf-new-report').on('click', function() {
                $('#ssf-report-output').hide();
                $('#ssf-report-config').show();
            });

            // Template fetch
            $('#ssf-fetch-template').on('click', this.fetchTemplate.bind(this));
            $('#ssf-clear-template').on('click', this.clearTemplate.bind(this));
        },

        fetchTemplate: function() {
            var url = $('#ssf-template-url').val().trim();
            if (!url) { SSF.alert('Please enter a template URL.'); return; }
            var $status = $('#ssf-template-status');
            $status.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Fetching template…');
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_fetch_report_template',
                nonce: ssfAdmin.nonce,
                template_url: url
            }).done(function(res) {
                if (res.success) {
                    $status.html('<span class="ssf-template-loaded">&#10003; Template loaded</span>');
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Failed to fetch template.';
                    $status.html('<span style="color:#d63638;">' + msg + '</span>');
                }
            }).fail(function() {
                $status.html('<span style="color:#d63638;">Request failed.</span>');
            });
        },

        clearTemplate: function() {
            var $status = $('#ssf-template-status');
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_clear_report_template',
                nonce: ssfAdmin.nonce
            }).done(function(res) {
                if (res.success) {
                    $('#ssf-template-url').val('');
                    $status.html('<span style="color:#666;">Template cleared.</span>');
                } else {
                    $status.html('<span style="color:#d63638;">Failed to clear template.</span>');
                }
            }).fail(function() {
                $status.html('<span style="color:#d63638;">Request failed.</span>');
            });
        },

        generate: function() {
            var $btn = $('#ssf-generate-report');
            var sections = [];
            $('input[name="ssf_section"]:checked').each(function() {
                sections.push($(this).val());
            });

            if (!sections.length) {
                SSF.alert('Please select at least one section.');
                return;
            }

            $btn.prop('disabled', true).text('Generating...');

            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_generate_client_report',
                nonce: ssfAdmin.nonce,
                date_range: $('#ssf-report-range').val(),
                start_date: $('#ssf-report-start').val() || '',
                end_date: $('#ssf-report-end').val() || '',
                mode: $('#ssf-report-mode').val() || 'positive',
                sections: sections
            }, function(res) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-analytics"></span> Generate Report');
                if (res.success) {
                    SSF_ClientReport.render(res.data);
                    $('#ssf-report-config').hide();
                    $('#ssf-report-output').show();
                } else {
                    SSF.alert(res.data && res.data.message ? res.data.message : 'Failed to generate report.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-analytics"></span> Generate Report');
                SSF.alert('Request failed. Please try again.');
            });
        },

        render: function(d) {
            // Apply template if available — replaces the default cover
            $('#ssf-template-styles').remove();
            $('#ssf-template-banner').remove();
            if (d.template) {
                // Extract <style> tags — scope them inside .ssf-template-banner
                var styleMatch = d.template.match(/<style[^>]*>([\s\S]*?)<\/style>/gi);
                if (styleMatch) {
                    // Rewrite selectors to scope inside .ssf-template-banner
                    var scoped = styleMatch.map(function(s) {
                        return s.replace(/<style[^>]*>([\s\S]*?)<\/style>/i, function(m, css) {
                            // Prefix each rule with .ssf-template-banner
                            var scopedCss = css.replace(/([^\r\n,{}]+)(,(?=[^}]*{)|\s*{)/g, function(match, selector, sep) {
                                selector = selector.trim();
                                if (!selector || selector.indexOf('@') === 0 || selector === 'body' || selector === 'html') return match;
                                return '.ssf-template-banner ' + selector + sep;
                            });
                            return '<style>' + scopedCss + '</style>';
                        });
                    }).join('\n');
                    $('head').append('<div id="ssf-template-styles">' + scoped + '</div>');
                }
                // Extract body content — use as the cover replacement
                var bodyContent = d.template.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '').trim();
                if (bodyContent) {
                    // Hide default cover, show template content as cover
                    $('.ssf-report-cover').hide();
                    $('.ssf-report-cover').before('<div id="ssf-template-banner" class="ssf-template-banner ssf-report-cover">' + bodyContent + '</div>');
                } else {
                    $('.ssf-report-cover').show();
                }
            } else {
                $('.ssf-report-cover').show();
            }

            // Cover
            if (d.site_icon) {
                $('#ssf-report-icon').html('<img src="' + this.esc(d.site_icon) + '" alt="" />');
            } else {
                $('#ssf-report-icon').html('<span class="dashicons dashicons-admin-site-alt3" style="font-size:60px;width:60px;height:60px;"></span>');
            }
            $('#ssf-report-site-name').text(d.site_name || '');
            $('#ssf-report-tagline').text(d.site_tagline || '');
            $('#ssf-report-url').text(d.site_url || '');
            $('#ssf-report-period').text(d.date_range ? d.date_range.label : '');
            $('#ssf-report-generated').text('Generated: ' + (d.generated_at || ''));

            var sec = d.sections || {};

            // Toggle section visibility — only show sections with data
            var allSections = ['overview', 'meta_coverage', 'score_distribution', 'score_factors', 'top_pages', 'content_health', 'image_seo', 'schema_coverage', 'redirects', 'keywords', 'broken_links_fixed', 'optimizations', 'sitemap_status', 'data_freshness', 'analytics', 'worst_pages', 'issues'];
            allSections.forEach(function(s) {
                var $el = $('#ssf-section-' + s);
                if (sec[s] !== undefined) { $el.show(); } else { $el.hide(); }
            });

            var isFullMode = d.mode === 'full';

            // ── Overview & Score ──
            if (sec.overview) {
                var ov = sec.overview;
                var score = ov.avg_score || 0;
                this.animateRing(score);

                // Grade label under score ring
                if (ov.grade_label) {
                    $('#ssf-report-grade').html('<span class="ssf-grade-badge grade-' + (ov.grade || '').toLowerCase().replace('+','plus') + '">' + this.esc(ov.grade) + ' — ' + this.esc(ov.grade_label) + '</span>');
                }

                var grid = '';
                grid += this.statBox(ov.total_analyzed, 'Pages Analyzed', ov.analyzed_pct ? ov.analyzed_pct + '% of published content' : '');
                grid += this.statBox(ov.good_count, 'Good Score (80+)');
                grid += this.statBox(ov.ok_count, 'OK Score (60-79)');
                grid += this.statBox(ov.healthy_pct + '%', 'Healthy Pages', 'Scoring 60 or above');
                if (isFullMode && ov.needs_work_count !== undefined) {
                    grid += this.statBox(ov.needs_work_count, 'Needs Work (<60)', '', 'ssf-stat-warning');
                }
                if (isFullMode && ov.not_analyzed !== undefined && ov.not_analyzed > 0) {
                    grid += this.statBox(ov.not_analyzed, 'Not Yet Analyzed', '', 'ssf-stat-info');
                }
                $('#ssf-overview-grid').html(grid);
            }

            // ── Meta Tag Coverage ──
            if (sec.meta_coverage) {
                var mc = sec.meta_coverage;
                var mh = '<div class="ssf-report-progress-list">';
                mh += this.progressBar('SEO Titles', mc.with_title, mc.total, mc.title_pct);
                mh += this.progressBar('Meta Descriptions', mc.with_description, mc.total, mc.description_pct);
                mh += this.progressBar('Focus Keywords', mc.with_keyword, mc.total, mc.keyword_pct);
                mh += '</div>';
                if (isFullMode && (mc.missing_title > 0 || mc.missing_description > 0)) {
                    mh += '<div class="ssf-report-alert ssf-alert-warning" style="margin-top:16px;">';
                    mh += '<strong>Action Needed:</strong> ';
                    var parts = [];
                    if (mc.missing_title > 0) parts.push(mc.missing_title + ' pages missing SEO titles');
                    if (mc.missing_description > 0) parts.push(mc.missing_description + ' pages missing meta descriptions');
                    if (mc.missing_keyword > 0) parts.push(mc.missing_keyword + ' pages missing focus keywords');
                    mh += parts.join(', ') + '.';
                    mh += '</div>';
                }
                $('#ssf-meta-coverage-content').html(mh);
            }

            // ── Score distribution ──
            if (sec.score_distribution && sec.score_distribution.length) {
                var maxCount = Math.max.apply(null, sec.score_distribution.map(function(b) { return b.count; }));
                var html = '';
                sec.score_distribution.forEach(function(b) {
                    var pct = maxCount > 0 ? (b.count / maxCount * 100) : 0;
                    html += '<div class="ssf-bar-row">';
                    html += '<span class="ssf-bar-label">' + SSF_ClientReport.esc(b.label) + '</span>';
                    html += '<div class="ssf-bar-track"><div class="ssf-bar-fill ' + b.label.toLowerCase() + '" style="width:' + pct + '%"></div></div>';
                    html += '<span class="ssf-bar-count">' + b.count + ' <small>(' + (b.pct || 0) + '%)</small></span>';
                    html += '</div>';
                });
                $('#ssf-dist-chart').html(html);
            }

            // ── Score Factors (Why Your Score Is What It Is) ──
            if (sec.score_factors && sec.score_factors.factors && sec.score_factors.factors.length) {
                var sf = sec.score_factors;
                $('#ssf-score-factors-subtitle').text(
                    'Based on analysis of ' + sf.total_pages + ' pages, these are the most common issues affecting your SEO score:'
                );
                var postsUrl = ssfAdmin.admin_url + 'admin.php?page=smart-seo-fixer-posts&issue=';
                var fhtml = '';
                sf.factors.forEach(function(f) {
                    var barColor = f.pct >= 50 ? '#d63638' : (f.pct >= 25 ? '#dba617' : '#72aee6');
                    var href = postsUrl + encodeURIComponent(f.issue);
                    fhtml += '<div class="ssf-factor-row-wrap">';
                    fhtml += '<a class="ssf-factor-row" href="' + SSF_ClientReport.esc(href) + '" target="_blank" title="View affected pages">';
                    fhtml += '<div class="ssf-factor-header">';
                    fhtml += '<span class="ssf-factor-category">' + SSF_ClientReport.esc(f.category) + '</span>';
                    fhtml += '<span class="ssf-factor-issue">' + SSF_ClientReport.esc(f.issue) + '</span>';
                    fhtml += '<span class="ssf-factor-count">' + f.count + ' pages (' + f.pct + '%)</span>';
                    fhtml += '</div>';
                    fhtml += '<div class="ssf-factor-bar-track"><div class="ssf-factor-bar-fill" style="width:' + f.pct + '%;background:' + barColor + ';"></div></div>';
                    fhtml += '</a>';
                    fhtml += '<button type="button" class="button button-small ssf-factor-fix-btn" data-issue="' + SSF_ClientReport.esc(f.issue) + '" data-category="' + SSF_ClientReport.esc(f.category) + '" data-count="' + f.count + '">';
                    fhtml += '<span class="dashicons dashicons-admin-generic"></span> AI Fix';
                    fhtml += '</button>';
                    fhtml += '</div>';
                });
                $('#ssf-score-factors-list').html(fhtml);
            }

            // ── Top pages ──
            if (sec.top_pages && sec.top_pages.length) {
                var tbody = '';
                sec.top_pages.forEach(function(p, i) {
                    var cls = p.score >= 90 ? 'excellent' : (p.score >= 80 ? 'good' : 'fair');
                    tbody += '<tr>';
                    tbody += '<td>' + (i + 1) + '</td>';
                    tbody += '<td>' + SSF_ClientReport.esc(p.title) + '</td>';
                    tbody += '<td>' + SSF_ClientReport.esc(p.post_type) + '</td>';
                    tbody += '<td><span class="score-badge ' + cls + '">' + p.score + '</span></td>';
                    tbody += '<td>' + SSF_ClientReport.esc(p.grade) + '</td>';
                    tbody += '</tr>';
                });
                $('#ssf-top-pages-table tbody').html(tbody);
            }

            // ── Content Health ──
            if (sec.content_health) {
                var ch = sec.content_health;
                var chh = '<div class="ssf-report-info-grid">';
                chh += this.infoCard(ch.avg_word_count.toLocaleString(), 'Avg. Word Count');
                chh += this.infoCard(ch.total_words.toLocaleString(), 'Total Words');
                chh += this.infoCard(ch.avg_readability, 'Readability Score', ch.readability_label || '');
                chh += this.infoCard(ch.avg_images, 'Avg. Images/Page');
                chh += this.infoCard(ch.avg_links, 'Avg. Links/Page');
                chh += this.infoCard(ch.total_analyzed.toLocaleString(), 'Pages Analyzed');
                chh += '</div>';
                $('#ssf-content-health-content').html(chh);
            }

            // ── Image SEO ──
            if (sec.image_seo) {
                var im = sec.image_seo;
                var imh = '<div class="ssf-report-info-grid" style="margin-bottom:16px;">';
                imh += this.infoCard(im.total_images.toLocaleString(), 'Images in Content');
                imh += this.infoCard(im.with_alt.toLocaleString(), 'With Alt Text');
                imh += this.infoCard(im.alt_pct + '%', 'Alt Text Coverage');
                imh += this.infoCard(im.posts_with_images.toLocaleString(), 'Pages With Images');
                imh += '</div>';
                if (im.alt_pct >= 80) {
                    imh += '<p class="ssf-report-note ssf-note-positive">Excellent image accessibility — ' + im.alt_pct + '% of images have descriptive alt text.</p>';
                } else if (im.alt_pct >= 50) {
                    imh += '<p class="ssf-report-note ssf-note-positive">Good progress — over half of your images have alt text for accessibility and SEO.</p>';
                }
                if (isFullMode && im.without_alt > 0) {
                    imh += '<div class="ssf-report-alert ssf-alert-warning">' + im.without_alt + ' images are missing alt text. Alt text improves accessibility and helps search engines understand your images.</div>';
                }
                imh += '<p class="ssf-report-note" style="font-size:12px;opacity:.75;margin-top:8px;">Scope: images actually used in published content. Orphan uploads in the media library are excluded so the coverage % reflects what visitors and search engines see.</p>';
                $('#ssf-image-seo-content').html(imh);
            }

            // ── Schema ──
            if (sec.schema_coverage) {
                var sc = sec.schema_coverage;
                var sh = '';
                if (sc.auto_coverage_note) {
                    sh += '<p class="ssf-report-note ssf-note-positive">' + this.esc(sc.auto_coverage_note) + '</p>';
                }
                sh += '<div class="ssf-report-info-grid">';
                if (sc.custom_schema > 0) {
                    sh += this.infoCard(sc.custom_schema, 'Custom Schema Entries');
                }
                sh += this.infoCard(sc.auto_schema_types ? sc.auto_schema_types.length : 0, 'Auto Schema Types');
                sh += '</div>';
                if (sc.auto_schema_types && sc.auto_schema_types.length) {
                    sh += '<div class="ssf-report-tag-list">';
                    sc.auto_schema_types.forEach(function(t) {
                        sh += '<span class="ssf-report-tag">' + SSF_ClientReport.esc(t) + '</span>';
                    });
                    sh += '</div>';
                }
                $('#ssf-schema-coverage-content').html(sh);
            }

            // ── Redirects ──
            if (sec.redirects) {
                var rd = sec.redirects;
                var rh = '<div class="ssf-report-info-grid">';
                rh += this.infoCard(rd.total_active, 'Active Redirects');
                if (rd.auto_created > 0) rh += this.infoCard(rd.auto_created, 'Auto-Created');
                if (rd.manual > 0) rh += this.infoCard(rd.manual, 'Manual');
                rh += '</div>';
                rh += '<p class="ssf-report-note">Active redirects protect your link equity by ensuring old URLs reach the right destination.</p>';
                $('#ssf-redirects-content').html(rh);
            }

            // ── Keywords ──
            if (sec.keywords) {
                var kw = sec.keywords;
                var kh = '<div class="ssf-report-info-grid" style="margin-bottom:16px;">';
                kh += this.infoCard(kw.total_tracked, 'Keywords Tracked');
                if (kw.total_clicks > 0) kh += this.infoCard(kw.total_clicks.toLocaleString(), 'Total Clicks');
                if (kw.total_impressions > 0) kh += this.infoCard(kw.total_impressions.toLocaleString(), 'Total Impressions');
                kh += '</div>';
                if (kw.top_keywords && kw.top_keywords.length) {
                    kh += '<table class="ssf-report-kw-table"><thead><tr>';
                    kh += '<th>Keyword</th><th>Avg. Position</th><th>Clicks</th><th>Impressions</th>';
                    kh += '</tr></thead><tbody>';
                    kw.top_keywords.forEach(function(k) {
                        kh += '<tr>';
                        kh += '<td><strong>' + SSF_ClientReport.esc(k.keyword) + '</strong></td>';
                        kh += '<td>' + k.position + '</td>';
                        kh += '<td>' + k.clicks + '</td>';
                        kh += '<td>' + k.impressions.toLocaleString() + '</td>';
                        kh += '</tr>';
                    });
                    kh += '</tbody></table>';
                }
                $('#ssf-keywords-content').html(kh);
            }

            // ── Broken links ──
            if (sec.broken_links_fixed) {
                var bl = sec.broken_links_fixed;
                var period = bl.period_label ? ' (' + SSF_ClientReport.esc(bl.period_label) + ')' : '';
                var bh = '<div class="ssf-report-info-grid">';
                bh += this.infoCard(bl.fixed || 0, 'Links Fixed' + period);
                if ((bl.fixed_total || 0) > (bl.fixed || 0)) {
                    bh += this.infoCard(bl.fixed_total, 'Fixed All Time');
                }
                if ((bl.dismissed || 0) > 0) {
                    bh += this.infoCard(bl.dismissed, 'Dismissed' + period);
                }
                if (isFullMode && (bl.outstanding || 0) > 0) {
                    bh += this.infoCard(bl.outstanding, 'Still Broken', '', 'ssf-card-error');
                }
                if (isFullMode && (bl.total_tracked || 0) > 0) {
                    bh += this.infoCard(bl.total_tracked, 'Total Tracked');
                }
                bh += '</div>';
                if ((bl.fixed || 0) > 0) {
                    bh += '<p class="ssf-report-note ssf-note-positive">' + bl.fixed + ' broken link(s) were resolved during this period — keeping visitors on the right path.</p>';
                } else if ((bl.fixed_total || 0) > 0) {
                    bh += '<p class="ssf-report-note">No new link fixes in this period. ' + bl.fixed_total + ' total have been resolved historically.</p>';
                }
                if (isFullMode && (bl.outstanding || 0) > 0) {
                    bh += '<div class="ssf-report-alert ssf-alert-error">' + bl.outstanding + ' broken link(s) still need attention. Review them in the Broken Links page.</div>';
                }
                bh += '<p class="ssf-report-note" style="font-size:12px;opacity:.75;margin-top:8px;">“Fixed” means a previously broken link was re-checked and found working. “Dismissed” means an admin manually hid the entry.</p>';
                $('#ssf-broken-links-content').html(bh);
            }

            // ── Optimizations ──
            if (sec.optimizations) {
                var op = sec.optimizations;
                var oh = '<div class="ssf-report-info-grid">';
                oh += this.infoCard(op.total, 'Total Changes');
                if (op.posts_optimized > 0) oh += this.infoCard(op.posts_optimized, 'Pages Optimized');
                if (op.ai_generated > 0) oh += this.infoCard(op.ai_generated, 'AI-Generated');
                if (op.manual > 0) oh += this.infoCard(op.manual, 'Manual Edits');
                oh += '</div>';
                if (op.by_type && op.by_type.length) {
                    oh += '<div class="ssf-report-tag-list" style="margin-top:16px;">';
                    op.by_type.forEach(function(t) {
                        oh += '<span class="ssf-report-tag">' + SSF_ClientReport.esc(t.category) + ': ' + t.count + '</span>';
                    });
                    oh += '</div>';
                }
                $('#ssf-optimizations-content').html(oh);
            }

            // ── Sitemap Status ──
            if (sec.sitemap_status) {
                var sm = sec.sitemap_status;
                var smh = '<div class="ssf-report-info-grid">';
                smh += this.infoCard('<span class="dashicons dashicons-yes-alt" style="color:#22c55e;font-size:28px;"></span>', 'Sitemap Active');
                smh += this.infoCard(sm.indexable_pages.toLocaleString(), 'Indexable Pages');
                smh += this.infoCard(sm.post_types.length, 'Content Types');
                smh += '</div>';
                smh += '<p class="ssf-report-note">Sitemap URL: <a href="' + this.esc(sm.url) + '" target="_blank">' + this.esc(sm.url) + '</a></p>';
                if (sm.post_types && sm.post_types.length) {
                    smh += '<div class="ssf-report-tag-list">';
                    sm.post_types.forEach(function(pt) {
                        smh += '<span class="ssf-report-tag">' + SSF_ClientReport.esc(pt) + '</span>';
                    });
                    smh += '</div>';
                }
                $('#ssf-sitemap-status-content').html(smh);
            }

            // ── Data Freshness ──
            if (sec.data_freshness) {
                var df = sec.data_freshness;
                var qualityMap = {
                    good:    { label: 'Fresh',   cls: 'ssf-note-positive' },
                    partial: { label: 'Partial', cls: '' },
                    stale:   { label: 'Stale',   cls: 'ssf-alert-error' },
                    none:    { label: 'Not Analyzed', cls: 'ssf-alert-error' },
                    unknown: { label: 'Unknown', cls: '' }
                };
                var q = qualityMap[df.quality] || qualityMap.unknown;
                var dfh = '<div class="ssf-report-info-grid">';
                dfh += this.infoCard(df.analyzed_count || 0, 'Pages Analyzed');
                dfh += this.infoCard((df.fresh_pct || 0) + '%', 'Analyzed in last 30 days');
                if ((df.never_analyzed || 0) > 0) {
                    dfh += this.infoCard(df.never_analyzed, 'Never Analyzed');
                }
                if ((df.stale_over_90d || 0) > 0) {
                    dfh += this.infoCard(df.stale_over_90d, 'Older than 90 days');
                }
                dfh += '</div>';
                if (df.last_analyzed) {
                    dfh += '<p class="ssf-report-note">Most recent analysis: <strong>' + SSF_ClientReport.esc(df.last_analyzed) + '</strong></p>';
                }
                if (df.quality === 'stale' || df.quality === 'none') {
                    dfh += '<div class="ssf-report-alert ssf-alert-error">Some scores in this report may be out of date. Run a bulk re-analysis for the most accurate figures.</div>';
                } else if (df.quality === 'partial') {
                    dfh += '<p class="ssf-report-note">Most scores are recent, but a bulk re-analysis will bring every page fully up to date.</p>';
                } else if (df.quality === 'good') {
                    dfh += '<p class="ssf-report-note ssf-note-positive">Scores in this report reflect a recent analysis of your content.</p>';
                }
                $('#ssf-data-freshness-content').html(dfh);
            }

            // ── Google Analytics (GA4) ──
            if (sec.analytics) {
                var ga = sec.analytics;
                var gah = '';
                if (!ga.connected) {
                    gah = '<div class="ssf-report-alert">Google Analytics is not connected. Connect it in Settings to include real visitor metrics.</div>';
                } else if (ga.error) {
                    gah = '<div class="ssf-report-alert ssf-alert-error">Analytics data could not be loaded: ' + SSF_ClientReport.esc(ga.error) + '</div>';
                } else {
                    var mins = ga.avg_session_seconds ? (Math.round(ga.avg_session_seconds / 6) / 10) : 0;
                    gah  = '<div class="ssf-report-info-grid">';
                    gah += this.infoCard((ga.sessions || 0).toLocaleString(), 'Sessions');
                    gah += this.infoCard((ga.users || 0).toLocaleString(), 'Users');
                    gah += this.infoCard((ga.pageviews || 0).toLocaleString(), 'Pageviews');
                    gah += this.infoCard((ga.bounce_rate || 0) + '%', 'Bounce Rate');
                    gah += this.infoCard((ga.engagement_rate || 0) + '%', 'Engagement Rate');
                    gah += this.infoCard(mins + ' min', 'Avg. Session Duration');
                    gah += '</div>';
                    gah += '<p class="ssf-report-note">Data covers the last ' + (ga.days || 30) + ' days from Google Analytics 4.</p>';

                    if (ga.top_pages && ga.top_pages.length) {
                        gah += '<h4 style="margin:16px 0 8px;">Top Landing Pages</h4>';
                        gah += '<table class="ssf-report-table"><thead><tr><th>#</th><th>Page</th><th>Pageviews</th><th>Sessions</th></tr></thead><tbody>';
                        ga.top_pages.forEach(function(p, i) {
                            gah += '<tr><td>' + (i + 1) + '</td><td>' + SSF_ClientReport.esc(p.path) + '</td><td>' + (p.pageviews || 0).toLocaleString() + '</td><td>' + (p.sessions || 0).toLocaleString() + '</td></tr>';
                        });
                        gah += '</tbody></table>';
                    }
                    if (ga.top_sources && ga.top_sources.length) {
                        gah += '<h4 style="margin:16px 0 8px;">Traffic Sources</h4>';
                        gah += '<table class="ssf-report-table"><thead><tr><th>#</th><th>Source</th><th>Sessions</th></tr></thead><tbody>';
                        ga.top_sources.forEach(function(s, i) {
                            gah += '<tr><td>' + (i + 1) + '</td><td>' + SSF_ClientReport.esc(s.source) + '</td><td>' + (s.sessions || 0).toLocaleString() + '</td></tr>';
                        });
                        gah += '</tbody></table>';
                    }
                }
                $('#ssf-analytics-content').html(gah);
            }

            // ── Worst Pages (full mode) ──
            if (sec.worst_pages && sec.worst_pages.length) {
                var wbody = '';
                sec.worst_pages.forEach(function(p, i) {
                    wbody += '<tr>';
                    wbody += '<td>' + (i + 1) + '</td>';
                    wbody += '<td>' + SSF_ClientReport.esc(p.title) + '</td>';
                    wbody += '<td><span class="score-badge needs-work">' + p.score + '</span></td>';
                    wbody += '<td>';
                    if (p.issues && p.issues.length) {
                        p.issues.forEach(function(issue) {
                            wbody += '<span class="ssf-issue-tag">' + SSF_ClientReport.esc(issue) + '</span> ';
                        });
                    }
                    wbody += '</td>';
                    wbody += '</tr>';
                });
                $('#ssf-worst-pages-table tbody').html(wbody);
            }

            // ── Issues & Recommendations (full mode) ──
            if (sec.issues && sec.issues.length) {
                var ih = '';
                sec.issues.forEach(function(issue) {
                    var icon = issue.severity === 'error' ? 'dismiss' : (issue.severity === 'warning' ? 'warning' : 'info-outline');
                    ih += '<div class="ssf-report-issue ssf-issue-' + issue.severity + '">';
                    ih += '<div class="ssf-issue-icon"><span class="dashicons dashicons-' + icon + '"></span></div>';
                    ih += '<div class="ssf-issue-body">';
                    ih += '<strong>' + SSF_ClientReport.esc(issue.title) + '</strong>';
                    if (issue.detail) ih += '<p>' + SSF_ClientReport.esc(issue.detail) + '</p>';
                    ih += '</div></div>';
                });
                $('#ssf-issues-content').html(ih);
            }
        },

        animateRing: function(score) {
            var circumference = 2 * Math.PI * 54; // r=54
            var offset = circumference - (score / 100) * circumference;
            var $fg = $('#ssf-ring-fg');
            var color = score >= 80 ? '#22c55e' : (score >= 60 ? '#f59e0b' : '#ef4444');

            $fg.css({ 'stroke-dasharray': circumference, 'stroke-dashoffset': circumference, 'stroke': color });
            setTimeout(function() { $fg.css('stroke-dashoffset', offset); }, 50);

            // Animate number
            var $val = $('#ssf-ring-value');
            $({ n: 0 }).animate({ n: score }, {
                duration: 1200,
                step: function() { $val.text(Math.round(this.n)); },
                complete: function() { $val.text(score); }
            });
        },

        downloadPDF: function() {
            var $report = $('#ssf-report');
            if (!$report.length) { return; }

            // Prefer html2pdf.js for a real PDF (no browser URL/date headers).
            // Falls back to window.print() if the library failed to load.
            if (typeof window.html2pdf !== 'function') {
                window.print();
                return;
            }

            var siteName = ($('#ssf-report-site-name').text() || 'seo-report').trim();
            var safeName = siteName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'seo-report';
            var today = new Date().toISOString().slice(0, 10);
            var filename = 'seo-report-' + safeName + '-' + today + '.pdf';

            var $btn = $('#ssf-download-pdf');
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update ssf-spin"></span> Generating PDF…');

            // Temporarily mark the body so print/pdf CSS can apply cleanly
            var $body = $('body');
            $body.addClass('ssf-pdf-exporting');

            var opts = {
                margin:       [10, 10, 12, 10],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.95 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            window.html2pdf().set(opts).from($report[0]).save().then(function() {
                $body.removeClass('ssf-pdf-exporting');
                $btn.prop('disabled', false).html(originalHtml);
            }).catch(function() {
                $body.removeClass('ssf-pdf-exporting');
                $btn.prop('disabled', false).html(originalHtml);
                window.print();
            });
        },

        reanalyzeAll: function() {
            var self = this;
            SSF.confirm('Re-analyze every published page? This recalculates all SEO scores using the current content and meta. On large sites this may take a few minutes.', function() {
                var $btn = $('#ssf-reanalyze-all');
                var $gen = $('#ssf-generate-report');
                var $progress = $('#ssf-reanalyze-progress');
                var $bar = $('#ssf-reanalyze-bar');
                var $label = $('#ssf-reanalyze-label');
                var $count = $('#ssf-reanalyze-count');

                var originalBtn = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update ssf-spin"></span> Re-analyzing…');
                $gen.prop('disabled', true);
                $progress.show();
                $bar.css('width', '0%');
                $label.text('Starting…');
                $count.text('0 / 0');

                var offset = 0;
                var batchSize = 10;
                var total = 0;
                var processed = 0;

                function step() {
                    $.post(ssfAdmin.ajax_url, {
                        action: 'ssf_bulk_analyze',
                        nonce: ssfAdmin.nonce,
                        offset: offset,
                        batch_size: batchSize,
                        analyze_mode: 'all'
                    }).done(function(resp) {
                        if (!resp || !resp.success) {
                            var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Analyze failed.';
                            $label.text('Error: ' + msg);
                            $btn.prop('disabled', false).html(originalBtn);
                            $gen.prop('disabled', false);
                            return;
                        }
                        var data = resp.data || {};
                        total = parseInt(data.total, 10) || total;
                        processed += parseInt(data.processed, 10) || 0;
                        offset += batchSize;

                        var pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
                        $bar.css('width', pct + '%');
                        $count.text(processed + ' / ' + total);
                        $label.text('Re-analyzing pages… (' + pct + '%)');

                        if (data.done) {
                            $bar.css('width', '100%');
                            $label.html('<span class="dashicons dashicons-yes-alt" style="color:#16a34a;"></span> Done — ' + processed + ' pages re-analyzed. Click <strong>Generate Report</strong> to see the updated score.');
                            $btn.prop('disabled', false).html(originalBtn);
                            $gen.prop('disabled', false);
                        } else {
                            step();
                        }
                    }).fail(function(xhr) {
                        $label.text('Network error: ' + (xhr.statusText || 'failed'));
                        $btn.prop('disabled', false).html(originalBtn);
                        $gen.prop('disabled', false);
                    });
                }

                step();
            });
        },

        statBox: function(number, label, subtitle, extraClass) {
            var cls = 'ssf-report-stat-box' + (extraClass ? ' ' + extraClass : '');
            var h = '<div class="' + cls + '"><span class="stat-number">' + (number !== undefined ? number : 0) + '</span><span class="stat-label">' + this.esc(label) + '</span>';
            if (subtitle) h += '<span class="stat-subtitle">' + this.esc(subtitle) + '</span>';
            h += '</div>';
            return h;
        },

        infoCard: function(value, label, subtitle, extraClass) {
            var cls = 'ssf-report-info-card' + (extraClass ? ' ' + extraClass : '');
            var h = '<div class="' + cls + '"><span class="info-value">' + (value !== undefined ? value : 0) + '</span><span class="info-label">' + this.esc(label) + '</span>';
            if (subtitle) h += '<span class="info-subtitle">' + this.esc(subtitle) + '</span>';
            h += '</div>';
            return h;
        },

        progressBar: function(label, count, total, pct) {
            var color = pct >= 80 ? '#22c55e' : (pct >= 50 ? '#f59e0b' : '#ef4444');
            return '<div class="ssf-report-progress-item">' +
                '<div class="ssf-progress-header"><span class="ssf-progress-label">' + this.esc(label) + '</span><span class="ssf-progress-value">' + count + ' / ' + total + ' (' + pct + '%)</span></div>' +
                '<div class="ssf-progress-track"><div class="ssf-progress-fill" style="width:' + pct + '%;background:' + color + '"></div></div>' +
                '</div>';
        },

        esc: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    window.SSF_ClientReport = SSF_ClientReport;

    /* ======================================================================
       Score Factor AI Fix Handler
       ====================================================================== */
    $(document).on('click', '.ssf-factor-fix-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var issueText = $btn.data('issue');
        var category = $btn.data('category');
        var count = parseInt($btn.data('count'), 10);

        SSF.confirm('AI Fix "' + issueText + '" across ' + count + ' pages?\n\nThis will generate missing SEO titles, descriptions, and keywords for affected pages.', function() {
            // Auto-detect fix options based on category
            var options = { generate_title: true, generate_desc: true, generate_keywords: true, overwrite: false };
            var catLower = category.toLowerCase();
            if (catLower === 'title') {
                options = { generate_title: true, generate_desc: false, generate_keywords: true, overwrite: true };
            } else if (catLower === 'description') {
                options = { generate_title: false, generate_desc: true, generate_keywords: true, overwrite: true };
            } else if (catLower === 'keywords') {
                options = { generate_title: false, generate_desc: false, generate_keywords: true, overwrite: true };
            }

            var $modal = $('#ssf-factor-fix-modal');
            var $status = $('#ssf-factor-fix-status');
            var $bar = $('#ssf-factor-fix-bar');
            var $log = $('#ssf-factor-fix-log');
            var $title = $('#ssf-factor-fix-title');

            $title.text('AI Fix: ' + issueText);
            $status.text('Fetching affected pages...');
            $bar.css('width', '0%');
            $log.html('');
            $modal.show();
            $btn.prop('disabled', true).text('Fixing...');

            // Step 1: Get post IDs with this issue
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_get_posts_by_issue',
                nonce: ssfAdmin.nonce,
                issue: issueText
            }, function(res) {
                if (!res.success || !res.data.post_ids.length) {
                    $status.text('No pages found with this issue.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> AI Fix');
                    return;
                }

                var postIds = res.data.post_ids;
                var total = postIds.length;
                var index = 0;
                var successCount = 0;
                var failCount = 0;

                $status.text('Fixing 0 of ' + total + ' pages...');

                function fixNext() {
                    if (index >= total) {
                        var pct = 100;
                        $bar.css('width', pct + '%');
                        $status.html('<strong>Done!</strong> ' + successCount + ' fixed, ' + failCount + ' failed out of ' + total + ' pages.');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Done');
                        return;
                    }

                    $.post(ssfAdmin.ajax_url, {
                        action: 'ssf_ai_fix_single',
                        nonce: ssfAdmin.nonce,
                        post_id: postIds[index],
                        options: options
                    }, function(resp) {
                        index++;
                        var pct = Math.round((index / total) * 100);
                        $bar.css('width', pct + '%');
                        $status.text('Fixing ' + index + ' of ' + total + ' pages...');

                        if (resp.success) {
                            successCount++;
                            $log.prepend('<div class="ssf-fix-log-item ssf-fix-success">\u2705 ' + SSF_ClientReport.esc(resp.data.title) + ' &mdash; ' + SSF_ClientReport.esc(resp.data.message) + '</div>');
                        } else {
                            failCount++;
                            $log.prepend('<div class="ssf-fix-log-item ssf-fix-fail">\u274c ' + SSF_ClientReport.esc(resp.data ? resp.data.message : 'Unknown error') + '</div>');
                        }

                        setTimeout(fixNext, 300);
                    }).fail(function() {
                        index++;
                        failCount++;
                        $log.prepend('<div class="ssf-fix-log-item ssf-fix-fail">\u274c Request failed for post #' + postIds[index - 1] + '</div>');
                        setTimeout(fixNext, 300);
                    });
                }

                fixNext();
            }).fail(function() {
                $status.text('Failed to fetch pages. Please try again.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> AI Fix');
            });
        });
    });

    // Close factor fix modal
    $(document).on('click', '#ssf-factor-fix-close', function() {
        $('#ssf-factor-fix-modal').hide();
    });

    /* ======================================================================
       Bulk Alt Text Generator
       ====================================================================== */
    $(document).ready(function() {
        var $btn = $('#ssf-bulk-alt-btn');
        var $status = $('#ssf-bulk-alt-status');
        if (!$btn.length) return;

        var $regenBtn = $('#ssf-regen-alt-btn');
        var $stop     = $('#ssf-bulk-alt-stop');
        var $progress = $('#ssf-bulk-alt-progress');
        var $bar      = $('#ssf-bulk-alt-bar');
        var $samples  = $('#ssf-bulk-alt-samples');
        var $useAi    = $('#ssf-bulk-alt-use-ai');

        var totalUpdated   = 0;
        var totalSkipped   = 0;
        var totalByAi      = 0;
        var totalByFile    = 0;
        var totalUnchanged = 0;
        var totalPreserved = 0;
        var whyNoAi        = '';
        var startTotal     = 0;
        var running        = false;
        var cancelled      = false;
        var mode           = 'missing';   // 'missing' | 'regenerate'
        var firstCall      = true;
        // Hard safety cap: even if the server keeps reporting work, never loop
        // unbounded. The old version could recurse forever on undescribable files.
        var MAX_BATCHES  = 400;
        var batches      = 0;

        function esc(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        }

        function useAi() {
            return $useAi.length ? $useAi.is(':checked') : true;
        }

        function num(n) {
            return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // Repaint the counter tiles from a stats payload.
        function paintStats(s) {
            if (!s) { return; }
            $('#ssf-alt-stat-total').text(num(s.total || 0));
            $('#ssf-alt-stat-with').text(num(s.with_alt || 0));
            $('#ssf-alt-stat-missing').text(num(s.missing || 0))
                .css('color', (s.missing > 0) ? '#d63638' : '#646970');
            $('#ssf-alt-stat-percent').text((s.percent || 0) + '%');
            $('#ssf-alt-stat-skipped').text(num(s.skipped || 0));
            $('#ssf-alt-stat-skipped-box').toggle((s.skipped || 0) > 0);
        }

        function loadStats($trigger) {
            if ($trigger) { $trigger.prop('disabled', true); }
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_alt_stats',
                nonce: ssfAdmin.nonce
            }).done(function(res) {
                if (res && res.success) { paintStats(res.data); }
            }).always(function() {
                if ($trigger) { $trigger.prop('disabled', false); }
            });
        }

        function finish(html) {
            running = false;
            $btn.prop('disabled', false);
            $regenBtn.prop('disabled', false);
            $stop.hide();
            $status.html(html);
            loadStats();
        }

        function summary() {
            var parts = [totalUpdated + (mode === 'regenerate' ? ' rewritten' : ' updated')];
            if (totalByAi > 0)      { parts.push(totalByAi + ' by AI vision'); }
            if (totalByFile > 0)    { parts.push(totalByFile + ' from filenames'); }
            if (totalUnchanged > 0) { parts.push(totalUnchanged + ' unchanged'); }
            if (totalPreserved > 0) { parts.push(totalPreserved + ' kept (looks hand-written)'); }
            if (totalSkipped > 0)   { parts.push(totalSkipped + ' skipped (no usable description)'); }
            return parts.join(', ');
        }

        function startRun(runMode) {
            running        = true;
            cancelled      = false;
            mode           = runMode;
            firstCall      = true;
            totalUpdated   = 0;
            totalSkipped   = 0;
            totalByAi      = 0;
            totalByFile    = 0;
            totalUnchanged = 0;
            totalPreserved = 0;
            whyNoAi        = '';
            batches        = 0;
            startTotal     = 0;
            $btn.prop('disabled', true);
            $regenBtn.prop('disabled', true);
            $stop.show();
            $samples.empty();
            $progress.show();
            $bar.css('width', '0%');
            $status.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Starting…');
            processBatch();
        }

        $btn.on('click', function() {
            if (running) return;
            startRun('missing');
        });

        $regenBtn.on('click', function() {
            if (running) return;

            // Rewriting replaces text that is already live on the site, so it
            // needs explicit consent — especially in overwrite-everything mode.
            var all = $('#ssf-regen-alt-overwrite-all').is(':checked');
            var msg = all
                ? 'This will REPLACE alt text on every image — including text written by hand. This cannot be undone. Continue?'
                : 'This will replace alt text previously generated by this plugin (filename-based and old buggy text). Alt text that looks hand-written is kept. Continue?';
            SSF.confirm(msg, function() {
                startRun('regenerate');
            }, { danger: all });
        });

        $stop.on('click', function() {
            cancelled = true;
            $stop.prop('disabled', true).text('Stopping…');
        });

        function processBatch() {
            if (cancelled) {
                $stop.prop('disabled', false).text('Stop');
                finish('<span style="color:#996800;font-weight:600;">Stopped. ' + summary() + '.</span>');
                return;
            }

            if (++batches > MAX_BATCHES) {
                finish('<span style="color:#996800;font-weight:600;">Stopped after ' + MAX_BATCHES +
                       ' batches (safety limit). ' + summary() + '. Click again to continue.</span>');
                return;
            }

            var payload = {
                action: (mode === 'regenerate') ? 'ssf_regenerate_alt' : 'ssf_bulk_generate_alt',
                nonce: ssfAdmin.nonce,
                use_ai: useAi() ? '1' : '0'
            };
            if (mode === 'regenerate') {
                payload.overwrite_all = $('#ssf-regen-alt-overwrite-all').is(':checked') ? '1' : '0';
                // Only the first request opens a new pass; the rest continue it.
                payload.new_pass = firstCall ? '1' : '0';
            }
            firstCall = false;

            $.post(ssfAdmin.ajax_url, payload).done(function(res) {
                if (!res || !res.success) {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed.';
                    finish('<span style="color:#d63638;">' + esc(msg) + '</span>');
                    return;
                }

                var d = res.data || {};
                totalUpdated   += (d.updated     || 0);
                totalSkipped   += (d.skipped     || 0);
                totalByAi      += (d.by_ai       || 0);
                totalByFile    += (d.by_filename || 0);
                totalUnchanged += (d.unchanged   || 0);
                totalPreserved += (d.preserved   || 0);

                // Keep the first explanation of why AI did not describe the
                // images — otherwise a run that "succeeded" gives no clue that
                // every description was guessed from a filename.
                if (!whyNoAi && d.why_no_ai) { whyNoAi = d.why_no_ai; }

                paintStats(d.stats);

                // Establish the denominator on the first response so the bar
                // reflects real progress rather than jumping around.
                if (startTotal === 0) {
                    startTotal = (d.updated || 0) + (d.skipped || 0) + (d.unchanged || 0) +
                                 (d.preserved || 0) + (d.remaining || 0);
                }

                // Show a few real examples so the quality is visible immediately.
                if (d.samples && d.samples.length && $samples.children().length < 5) {
                    d.samples.forEach(function(s) {
                        if ($samples.children().length >= 5) { return; }
                        var badge = s.source === 'ai'
                            ? '<span style="color:#00a32a;font-weight:600;">AI</span>'
                            : '<span style="color:#996800;font-weight:600;" title="' +
                              esc(s.reason ? 'AI not used: ' + s.reason : 'Derived from the filename') +
                              '">filename</span>';
                        var before = s.old
                            ? '<span style="color:#8c8f94;text-decoration:line-through;">' + esc(s.old) + '</span> &rarr; '
                            : '';
                        $samples.append(
                            '<div style="font-size:12px;margin-top:4px;">' + badge +
                            ' &mdash; ' + before + '<em>' + esc(s.alt) + '</em></div>'
                        );
                    });
                }

                var doneCount = startTotal - (d.remaining || 0);
                var pct = startTotal > 0 ? Math.min(100, Math.round((doneCount / startTotal) * 100)) : 100;
                $bar.css('width', pct + '%');

                if (d.done) {
                    $bar.css('width', '100%');
                    var extra = '';

                    // A run can finish "successfully" while every description
                    // was guessed from a filename. Say so, and say why.
                    if (whyNoAi) {
                        extra += '<div style="margin-top:6px;padding:8px 10px;background:#fcf9e8;' +
                                 'border-left:4px solid #dba617;font-weight:400;">' +
                                 '<strong>AI vision was not used.</strong> ' + esc(whyNoAi) +
                                 '</div>';
                    }
                    if (d.last_error && (!whyNoAi || whyNoAi.indexOf(d.last_error) === -1)) {
                        extra += ' <span style="color:#d63638;">Last error: ' + esc(d.last_error) + '</span>';
                    }
                    finish('<span style="color:#00a32a;font-weight:600;">&#10003; Done! ' + summary() + '.</span>' + extra);
                    return;
                }

                $status.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> ' +
                             summary() + ' &mdash; ' + (d.remaining || 0) + ' remaining (' + pct + '%)…');
                processBatch();
            }).fail(function(xhr) {
                var detail = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
                finish('<span style="color:#d63638;">Request failed' + detail +
                       '. ' + summary() + '. Click again to resume.</span>');
            });
        }

        $('#ssf-alt-stats-refresh').on('click', function() {
            loadStats($(this));
        });

        /* ------------------------------------------------------------------
           Test AI Vision — answers "can the AI actually SEE my images?"
           Sends one real image through the same path a normal run uses, so a
           silent degrade to filename text becomes a visible, explained failure.
           ------------------------------------------------------------------ */
        $('#ssf-test-vision-btn').on('click', function() {
            var $b       = $(this);
            var $vstatus = $('#ssf-test-vision-status');
            var $vresult = $('#ssf-test-vision-result');

            $b.prop('disabled', true);
            $vresult.hide().empty();
            $vstatus.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Looking at an image…');

            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_test_vision',
                nonce: ssfAdmin.nonce
            }).done(function(res) {
                var d = (res && res.data) ? res.data : {};

                if (res && res.success) {
                    $vstatus.html('<span style="color:#00a32a;font-weight:600;">&#10003; ' + esc(d.message || 'Vision is working.') + '</span>');

                    var html = '<div style="padding:10px 12px;background:#f0f6fc;border-left:4px solid #2271b1;">';
                    if (d.image) {
                        html += '<img src="' + esc(d.image) + '" alt="" ' +
                                'style="max-width:90px;height:auto;float:left;margin:0 12px 0 0;border-radius:3px;">';
                    }
                    html += '<div><strong>It described this image as:</strong><br>' +
                            '<em>' + esc(d.sample || '') + '</em>';
                    if (d.filename) {
                        html += '<br><span style="color:#646970;font-size:12px;">Test image: ' + esc(d.filename) + '</span>';
                    }
                    html += '<br><span style="color:#646970;font-size:12px;">' +
                            'Open the image and check the description matches. If it does, vision is working.' +
                            '</span></div><div style="clear:both;"></div></div>';

                    $vresult.html(html).show();
                    return;
                }

                // Failure is the useful case: report the specific cause.
                $vstatus.html('<span style="color:#d63638;font-weight:600;">&#10007; AI vision is not working</span>');

                var fix = '';
                switch (d.reason) {
                    case 'not_configured':
                        fix = 'Add your API credentials in the AI Provider section above, then save.';
                        break;
                    case 'model_not_vision':
                        fix = 'Pick a vision-capable model above. On AWS Bedrock that means a Claude model; ' +
                              'also confirm that model is enabled for your account and region in the Bedrock console.';
                        break;
                    case 'image_unreadable':
                        fix = 'The plugin could not read the image file. Check file permissions on wp-content/uploads, ' +
                              'or whether an offload/CDN plugin has moved the original off this server.';
                        break;
                    case 'ai_error':
                        fix = 'The provider rejected the request. Verify the credentials, the region, and that the model ' +
                              'is enabled for your account — the message above is the provider’s own reply.';
                        break;
                    case 'no_images':
                        fix = 'Upload an image to the Media Library, then run the test again.';
                        break;
                    default:
                        fix = 'Until this is fixed, alt text will be derived from filenames rather than the images.';
                }

                var ehtml = '<div style="padding:10px 12px;background:#fcf0f1;border-left:4px solid #d63638;">' +
                            '<strong>' + esc(d.message || 'The AI could not describe the image.') + '</strong>';
                if (d.provider || d.model) {
                    ehtml += '<br><span style="color:#646970;font-size:12px;">Provider: ' +
                             esc(d.provider || 'unknown') +
                             (d.model ? ' &middot; Model: ' + esc(d.model) : '') + '</span>';
                }
                ehtml += '<p style="margin:8px 0 0;">' + esc(fix) + '</p></div>';

                $vresult.html(ehtml).show();
            }).fail(function(xhr) {
                $vstatus.html('<span style="color:#d63638;">Request failed' +
                              (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '.</span>');
            }).always(function() {
                $b.prop('disabled', false);
            });
        });

        // Retry images that were previously flagged as undescribable.
        $('#ssf-bulk-alt-reset').on('click', function() {
            var $b = $(this);
            $b.prop('disabled', true);
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_reset_skipped_alt',
                nonce: ssfAdmin.nonce
            }).done(function(res) {
                if (res && res.success) {
                    paintStats(res.data.stats);
                    $status.html('<span style="color:#00a32a;">Cleared ' + (res.data.cleared || 0) +
                                 ' skip flags. ' + (res.data.remaining || 0) + ' images now eligible.</span>');
                } else {
                    $status.html('<span style="color:#d63638;">Could not reset.</span>');
                }
            }).fail(function() {
                $status.html('<span style="color:#d63638;">Request failed.</span>');
            }).always(function() {
                $b.prop('disabled', false);
            });
        });
    });

    /* ======================================================================
       Media Library — per-image alt text generation
       Handles the Alt Text column button on upload.php, and the "Generate
       with AI" button under the Alternative Text field on the attachment
       edit screen and inside the media modal.
       ====================================================================== */
    $(document).ready(function() {
        function escHtml(s) {
            return $('<div>').text(s == null ? '' : String(s)).html();
        }

        function request(id, force, onDone) {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_generate_single_alt',
                nonce: ssfAdmin.nonce,
                attachment_id: id,
                force: force ? '1' : '0'
            }).done(function(res) {
                if (res && res.success) {
                    onDone(null, res.data || {});
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed.';
                    onDone(msg, null);
                }
            }).fail(function(xhr) {
                onDone('Request failed' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '.', null);
            });
        }

        // --- Media Library list view column ---------------------------------
        // Delegated so it survives AJAX-refreshed list tables.
        $(document).on('click', '.ssf-alt-generate', function() {
            var $btn    = $(this);
            var $cell   = $btn.closest('.ssf-alt-cell');
            var $status = $cell.find('.ssf-alt-cell-status');
            var $text   = $cell.find('.ssf-alt-cell-text');
            var id      = $btn.data('id');
            var force   = String($btn.data('force')) === '1';

            function proceed() {
                var label = $btn.text();
                $btn.prop('disabled', true).text('Working…');
                $status.html('').css('color', '');

                request(id, force, function(err, d) {
                    $btn.prop('disabled', false);

                    if (err) {
                        $btn.text(label);
                        $status.css('color', '#d63638').text(err);
                        return;
                    }

                    if (d.changed) {
                        var mark = '<span class="ssf-alt-value">' + escHtml(d.alt) + '</span>';
                        if (d.source !== 'ai') {
                            mark += ' <span class="ssf-alt-source" style="color:#996800;font-size:11px;' +
                                    'white-space:nowrap;" title="' + escHtml(d.note || '') +
                                    '">(from filename)</span>';
                        }
                        $text.html(mark);
                        $btn.text('Rewrite').data('force', '1');

                        if (d.source === 'ai') {
                            $status.css('color', '#00a32a').text('✓ AI');
                        } else {
                            // Not a clean success: the text came from the filename,
                            // so show why rather than a green tick.
                            $status.css('color', '#996800')
                                   .attr('title', d.note || '')
                                   .text('⚠ filename' + (d.note ? ' — ' + d.note : ''));
                        }
                    } else {
                        $btn.text(label);
                        $status.css('color', '#646970').text(d.message || 'No change.');
                    }
                });
            }

            if (force) {
                SSF.confirm('Replace the existing alt text for this image?', proceed);
            } else {
                proceed();
            }
        });

        // --- Attachment edit screen (post.php) ------------------------------
        // Core renders the Alternative Text input directly here rather than
        // through attachment_fields_to_edit, so the button is added against the
        // real input instead of guessing at a PHP-side field definition.
        (function addButtonToEditScreen() {
            var $alt = $('#attachment_alt');
            if (!$alt.length || $('.ssf-alt-generate-field').length) {
                return;
            }
            var id = (typeof ssfAdmin !== 'undefined' && ssfAdmin.post_id)
                ? ssfAdmin.post_id
                : ($('#post_ID').val() || 0);
            if (!id) { return; }

            $alt.closest('td, .wp_attachment_details, p').first().append(
                $('<p style="margin:6px 0 0;"></p>').append(
                    $('<button type="button" class="button button-small ssf-alt-generate-field"></button>')
                        .attr('data-id', id)
                        .text('Generate alt text with AI'),
                    $('<span class="ssf-alt-field-status" style="margin-left:6px;font-size:12px;"></span>')
                )
            );
        })();

        // --- Attachment edit screen / media modal ---------------------------
        $(document).on('click', '.ssf-alt-generate-field', function() {
            var $btn    = $(this);
            var $status = $btn.siblings('.ssf-alt-field-status');
            var id      = $btn.data('id');

            // The alt input differs between the edit screen and the modal.
            var $input = $('#attachment_alt, #attachment-details-alt-text, ' +
                           '#attachment-details-two-column-alt-text, ' +
                           'input[name="attachments[' + id + '][image_alt]"]').filter(':visible').first();
            if (!$input.length) {
                $input = $('#attachment_alt, #attachment-details-alt-text, ' +
                           '#attachment-details-two-column-alt-text, ' +
                           'input[name="attachments[' + id + '][image_alt]"]').first();
            }

            var existing = $input.length ? String($input.val() || '').trim() : '';

            function proceed() {
                var label = $btn.text();
                $btn.prop('disabled', true).text('Generating…');
                $status.html('').css('color', '');

                // Force, because the field may hold text the server has not seen yet.
                request(id, true, function(err, d) {
                    $btn.prop('disabled', false).text(label);

                    if (err) {
                        $status.css('color', '#d63638').text(err);
                        return;
                    }

                    if ($input.length) {
                        // Fire change so the media modal persists it in its model.
                        $input.val(d.alt).trigger('change');
                    }

                    if (d.source === 'ai') {
                        $status.css('color', '#00a32a').attr('title', '').text('✓ Described by AI');
                    } else {
                        // Filename-derived text is not a real description — say why.
                        $status.css('color', '#996800')
                               .attr('title', d.note || '')
                               .text('⚠ From filename' + (d.note ? ' — ' + d.note : ''));
                    }
                });
            }

            if (existing !== '') {
                SSF.confirm('Replace the current alt text?', proceed);
            } else {
                proceed();
            }
        });
    });

})(jQuery);

