<?php
/**
 * Bulk AI Fix — Dedicated Page
 * Preview posts with missing/poor SEO data, select and fix them with AI.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-superhero-alt"></span>
        <?php esc_html_e('Bulk AI Fix', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>

    <?php if (class_exists('SSF_AI') && !SSF_AI::is_configured()): ?>
    <div class="ssf-notice ssf-notice-warning">
        <p>
            <strong><?php esc_html_e('API Key Required:', 'smart-seo-fixer'); ?></strong>
            <?php esc_html_e('Add your AWS Bedrock credentials in', 'smart-seo-fixer'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-settings')); ?>">
                <?php esc_html_e('Settings', 'smart-seo-fixer'); ?>
            </a>
            <?php esc_html_e('to enable AI-powered features.', 'smart-seo-fixer'); ?>
        </p>
    </div>
    <?php endif; ?>

    <p class="ssf-page-desc"><?php esc_html_e('Select which posts need AI-generated SEO data, preview them, then fix in bulk.', 'smart-seo-fixer'); ?></p>

    <!-- Step 1: Configure & Preview -->
    <div id="bulk-step-config">
        <div class="ssf-bulk-layout">
            <!-- Left: Options -->
            <div class="ssf-bulk-sidebar">
                <div class="ssf-card">
                    <div class="ssf-card-header"><h2><?php esc_html_e('What to Generate', 'smart-seo-fixer'); ?></h2></div>
                    <div class="ssf-card-body">
                        <label class="ssf-checkbox-option">
                            <input type="checkbox" name="bulk_opt_title" checked>
                            <span class="ssf-checkbox-label">
                                <strong><?php esc_html_e('SEO Titles', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Optimized titles (50-60 chars)', 'smart-seo-fixer'); ?></small>
                            </span>
                        </label>
                        <label class="ssf-checkbox-option">
                            <input type="checkbox" name="bulk_opt_desc" checked>
                            <span class="ssf-checkbox-label">
                                <strong><?php esc_html_e('Meta Descriptions', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Compelling descriptions (150-160 chars)', 'smart-seo-fixer'); ?></small>
                            </span>
                        </label>
                        <label class="ssf-checkbox-option">
                            <input type="checkbox" name="bulk_opt_keywords" checked>
                            <span class="ssf-checkbox-label">
                                <strong><?php esc_html_e('Focus Keywords', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('AI-suggested focus keywords', 'smart-seo-fixer'); ?></small>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="ssf-card">
                    <div class="ssf-card-header"><h2><?php esc_html_e('Apply To', 'smart-seo-fixer'); ?></h2></div>
                    <div class="ssf-card-body">
                        <label class="ssf-radio-option">
                            <input type="radio" name="bulk_apply_to" value="missing" checked>
                            <span><?php esc_html_e('Only posts with MISSING SEO data (safe)', 'smart-seo-fixer'); ?></span>
                        </label>
                        <label class="ssf-radio-option">
                            <input type="radio" name="bulk_apply_to" value="poor">
                            <span><?php esc_html_e('Posts with score below 60', 'smart-seo-fixer'); ?></span>
                        </label>
                        <label class="ssf-radio-option ssf-option-danger">
                            <input type="radio" name="bulk_apply_to" value="all">
                            <span><?php esc_html_e('ALL posts (overwrite everything)', 'smart-seo-fixer'); ?></span>
                        </label>
                    </div>
                </div>

                <button type="button" class="button button-primary button-hero ssf-full-btn" id="load-preview-btn">
                    <span class="dashicons dashicons-visibility" style="margin-top:4px;"></span>
                    <?php esc_html_e('Load Preview', 'smart-seo-fixer'); ?>
                </button>
            </div>

            <!-- Right: Preview List -->
            <div class="ssf-bulk-main">
                <div class="ssf-card" style="flex:1; display:flex; flex-direction:column;">
                    <div class="ssf-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h2>
                            <span class="dashicons dashicons-list-view"></span>
                            <?php esc_html_e('Posts to Fix', 'smart-seo-fixer'); ?>
                            <span class="ssf-badge" id="preview-count" style="display:none;">0</span>
                        </h2>
                        <div style="display:flex; align-items:center; gap:16px;">
                            <label id="select-all-wrap" style="display:none; font-size:13px; cursor:pointer;">
                                <input type="checkbox" id="preview-select-all" checked> <?php esc_html_e('Select All', 'smart-seo-fixer'); ?>
                            </label>
                            <button type="button" class="button button-primary" id="start-bulk-fix" disabled>
                                <span class="dashicons dashicons-superhero-alt" style="margin-top:4px;"></span>
                                <?php esc_html_e('Fix All Selected Posts', 'smart-seo-fixer'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="ssf-card-body" style="flex:1; padding:0;">
                        <div class="ssf-preview-list" id="preview-list">
                            <div class="ssf-preview-empty">
                                <span class="dashicons dashicons-arrow-left-alt"></span>
                                <p><?php esc_html_e('Choose your options on the left, then click "Load Preview" to see which posts will be affected.', 'smart-seo-fixer'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Progress (replaces config when running) -->
    <div id="bulk-step-progress" style="display:none;">
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2 id="bulk-progress-title">
                    <span class="dashicons dashicons-update ssf-spin"></span>
                    <?php esc_html_e('Generating SEO Data...', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <div class="ssf-progress-bar">
                    <div class="ssf-progress-fill" id="bulk-progress-fill" style="width:0%"></div>
                </div>
                <p class="ssf-progress-text" id="bulk-progress-text">0%</p>
                <div class="ssf-progress-log" id="bulk-progress-log"></div>
                <div style="margin-top:16px; text-align:center;">
                    <button type="button" class="button button-primary" id="bulk-done-btn" style="display:none;">
                        <span class="dashicons dashicons-yes-alt" style="margin-top:4px;"></span>
                        <?php esc_html_e('Done — Back to Preview', 'smart-seo-fixer'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ssf-page-desc { color: #6b7280; font-size: 14px; margin: -10px 0 20px; }
.ssf-full-btn { width: 100%; display: flex !important; align-items: center; justify-content: center; gap: 6px; }
.ssf-badge { background: #2563eb; color: #fff; font-size: 11px; padding: 2px 10px; border-radius: 12px; font-weight: 700; vertical-align: middle; margin-left: 6px; }
.ssf-spin { animation: ssf-spin 1s linear infinite; }
@keyframes ssf-spin { 100% { transform: rotate(360deg); } }

/* Two-column layout */
.ssf-bulk-layout { display: flex; gap: 24px; align-items: flex-start; }
.ssf-bulk-sidebar { flex: 0 0 320px; display: flex; flex-direction: column; gap: 16px; }
.ssf-bulk-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

/* Options */
.ssf-checkbox-option, .ssf-radio-option { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; background: #f9fafb; border-radius: 6px; margin-bottom: 6px; cursor: pointer; transition: background 0.2s; }
.ssf-checkbox-option:hover, .ssf-radio-option:hover { background: #f3f4f6; }
.ssf-checkbox-option input, .ssf-radio-option input { margin-top: 3px; }
.ssf-checkbox-label { display: flex; flex-direction: column; }
.ssf-checkbox-label strong { color: #1f2937; font-size: 13px; }
.ssf-checkbox-label small { color: #6b7280; font-size: 11px; }
.ssf-option-danger { border: 1px solid #fecaca; background: #fef2f2; }
.ssf-option-danger:hover { background: #fee2e2; }

/* Preview list */
.ssf-preview-list { border-top: 1px solid #e5e7eb; overflow-y: auto; max-height: 520px; min-height: 300px; }
.ssf-preview-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #9ca3af; text-align: center; padding: 30px; }
.ssf-preview-empty .dashicons { font-size: 32px; width: 32px; height: 32px; margin-bottom: 8px; }
.ssf-preview-empty p { margin: 0; font-size: 13px; line-height: 1.5; }
.ssf-preview-loading { display: flex; align-items: center; justify-content: center; height: 200px; color: #6b7280; font-size: 14px; gap: 8px; }

/* Preview items */
.ssf-preview-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f3f4f6; transition: background 0.15s; }
.ssf-preview-item:last-child { border-bottom: none; }
.ssf-preview-item:hover { background: #f9fafb; }
.ssf-preview-item input[type="checkbox"] { flex-shrink: 0; }
.ssf-preview-item-info { flex: 1; min-width: 0; }
.ssf-preview-item-title { font-weight: 600; font-size: 13px; color: #1f2937; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ssf-preview-item-title a { color: #1f2937; text-decoration: none; }
.ssf-preview-item-title a:hover { color: #2563eb; }
.ssf-preview-item-meta { font-size: 11px; color: #9ca3af; margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
.ssf-tag-missing { color: #dc2626; font-weight: 600; }
.ssf-tag-has { color: #059669; }
.ssf-preview-item-score { flex-shrink: 0; font-size: 12px; font-weight: 700; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.ssf-preview-item-score.score-good { background: #d1fae5; color: #065f46; }
.ssf-preview-item-score.score-ok { background: #fef3c7; color: #92400e; }
.ssf-preview-item-score.score-poor { background: #fee2e2; color: #991b1b; }
.ssf-preview-item-score.score-na { background: #f3f4f6; color: #9ca3af; font-size: 10px; }

@media (max-width: 860px) {
    .ssf-bulk-layout { flex-direction: column; }
    .ssf-bulk-sidebar { flex: none; width: 100%; }
}
</style>

<script>
jQuery(document).ready(function($) {
    var previewData = [];

    function esc(t) { return $('<span>').text(t || '').html(); }

    // Load preview
    $('#load-preview-btn').on('click', loadPreview);

    <?php
    // Auto-load preview if opened via banner link
    $auto_load = isset($_GET['auto']) && $_GET['auto'] === 'missing';
    ?>
    <?php if ($auto_load): ?>
    $('input[name="bulk_apply_to"][value="missing"]').prop('checked', true);
    loadPreview();
    <?php endif; ?>

    function loadPreview() {
        var applyTo = $('input[name="bulk_apply_to"]:checked').val();

        $('#preview-list').html('<div class="ssf-preview-loading"><span class="spinner is-active"></span> <?php echo esc_js(__('Loading affected posts...', 'smart-seo-fixer')); ?></div>');
        $('#start-bulk-fix').prop('disabled', true);

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_preview_bulk_fix',
            nonce: ssfAdmin.nonce,
            apply_to: applyTo
        }, function(response) {
            if (response.success && response.data.posts) {
                previewData = response.data.posts;
                renderPreview(previewData);
            } else {
                var msg = (response.data && response.data.message) || '<?php echo esc_js(__('Failed to load preview.', 'smart-seo-fixer')); ?>';
                $('#preview-list').html('<div class="ssf-preview-empty" style="color:#dc2626;"><span class="dashicons dashicons-warning"></span><p>' + esc(msg) + '</p></div>');
            }
        }).fail(function() {
            $('#preview-list').html('<div class="ssf-preview-empty" style="color:#dc2626;"><span class="dashicons dashicons-warning"></span><p><?php echo esc_js(__('Request failed. Check connection.', 'smart-seo-fixer')); ?></p></div>');
        });
    }

    function renderPreview(posts) {
        if (!posts.length) {
            $('#preview-list').html('<div class="ssf-preview-empty"><span class="dashicons dashicons-smiley"></span><p><?php echo esc_js(__('All posts already have SEO data. Nothing to fix!', 'smart-seo-fixer')); ?></p></div>');
            $('#preview-count').text('0').show();
            $('#select-all-wrap').hide();
            $('#start-bulk-fix').prop('disabled', true);
            return;
        }

        var html = '';
        posts.forEach(function(p) {
            var scoreClass = p.score === null ? 'na' : (p.score >= 80 ? 'good' : (p.score >= 60 ? 'ok' : 'poor'));
            var scoreText = p.score === null ? '—' : p.score;

            var tags = '';
            tags += p.has_title   ? '<span class="ssf-tag-has">✓ Title</span>' : '<span class="ssf-tag-missing">⚠ Title</span>';
            tags += p.has_desc    ? '<span class="ssf-tag-has">✓ Desc</span>'  : '<span class="ssf-tag-missing">⚠ Desc</span>';
            tags += p.has_keyword ? '<span class="ssf-tag-has">✓ Keyword</span>' : '<span class="ssf-tag-missing">⚠ Keyword</span>';

            html += '<div class="ssf-preview-item">';
            html += '<input type="checkbox" class="preview-item-cb" value="' + p.id + '" checked>';
            html += '<div class="ssf-preview-item-info">';
            html += '<span class="ssf-preview-item-title"><a href="' + p.edit_url + '" target="_blank">' + esc(p.title) + '</a></span>';
            html += '<div class="ssf-preview-item-meta">' + tags + ' <span style="color:#9ca3af;">(' + esc(p.type) + ')</span></div>';
            html += '</div>';
            html += '<div class="ssf-preview-item-score score-' + scoreClass + '">' + scoreText + '</div>';
            html += '</div>';
        });

        $('#preview-list').html(html);
        $('#preview-count').text(posts.length).show();
        $('#select-all-wrap').show();
        $('#preview-select-all').prop('checked', true);
        updateFixButton();
    }

    // Select all
    $(document).on('change', '#preview-select-all', function() {
        $('.preview-item-cb').prop('checked', $(this).is(':checked'));
        updateFixButton();
    });
    $(document).on('change', '.preview-item-cb', function() {
        updateFixButton();
        var total = $('.preview-item-cb').length;
        var selected = $('.preview-item-cb:checked').length;
        $('#preview-select-all').prop('checked', selected === total);
    });

    function updateFixButton() {
        var count = $('.preview-item-cb:checked').length;
        if (count > 0) {
            $('#start-bulk-fix').prop('disabled', false).html('<span class="dashicons dashicons-superhero-alt" style="margin-top:4px;"></span> <?php echo esc_js(__('Fix', 'smart-seo-fixer')); ?> ' + count + ' <?php echo esc_js(__('Selected Posts', 'smart-seo-fixer')); ?>');
        } else {
            $('#start-bulk-fix').prop('disabled', true).html('<span class="dashicons dashicons-superhero-alt" style="margin-top:4px;"></span> <?php echo esc_js(__('Select posts to fix', 'smart-seo-fixer')); ?>');
        }
    }

    // Start fix
    $('#start-bulk-fix').on('click', function() {
        var genTitle = $('input[name="bulk_opt_title"]').is(':checked');
        var genDesc  = $('input[name="bulk_opt_desc"]').is(':checked');
        var genKw    = $('input[name="bulk_opt_keywords"]').is(':checked');

        if (!genTitle && !genDesc && !genKw) {
            alert('<?php echo esc_js(__('Select at least one option to generate.', 'smart-seo-fixer')); ?>');
            return;
        }

        var selectedIds = [];
        $('.preview-item-cb:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });

        if (!selectedIds.length) {
            alert('<?php echo esc_js(__('No posts selected.', 'smart-seo-fixer')); ?>');
            return;
        }

        if (!confirm('<?php echo esc_js(__('AI will generate SEO data for', 'smart-seo-fixer')); ?> ' + selectedIds.length + ' <?php echo esc_js(__('posts using AWS Bedrock. Continue?', 'smart-seo-fixer')); ?>')) {
            return;
        }

        // Switch to progress view
        $('#bulk-step-config').hide();
        $('#bulk-step-progress').show();
        $('#bulk-progress-fill').css('width', '0%');
        $('#bulk-progress-text').text('0%');
        $('#bulk-progress-log').html('');
        $('#bulk-done-btn').hide();

        var options = {
            generate_title: genTitle,
            generate_desc: genDesc,
            generate_keywords: genKw,
            apply_to: $('input[name="bulk_apply_to"]:checked').val()
        };

        runBulk(options, selectedIds);
    });

    function runBulk(options, postIds) {
        var total = postIds.length;

        // Always send the whole selection in one request. The server decides:
        //   - 5+ posts  → queued as a background job (visible on Job Queue page)
        //                → we poll progress every 2s and show a live progress bar
        //   - <5 posts  → run in-request with parallel Bedrock curl_multi and
        //                return immediately
        $('#bulk-progress-fill').css('width', '0%');
        $('#bulk-progress-text').text('0% (0/' + total + ')');
        $('#bulk-progress-log').html('');

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_bulk_ai_fix',
            nonce: ssfAdmin.nonce,
            // Send IDs as a single CSV string (one POST field) instead of an
            // array. WordPress / PHP's default max_input_vars = 1000 silently
            // truncates array POSTs larger than that, which was capping bulk
            // jobs at ~999 items no matter how many the user selected.
            post_ids_csv: postIds.join(','),
            options: options
        }, function(response) {
            if (!response.success) {
                var msg = (response.data && response.data.message) || '<?php echo esc_js(__('Unknown error', 'smart-seo-fixer')); ?>';
                $('#bulk-progress-log').append('<div style="color:#dc2626;">❌ ' + esc(msg) + '</div>');
                $('#bulk-progress-title').html('<span class="dashicons dashicons-warning" style="color:#dc2626;"></span> <?php echo esc_js(__('Error', 'smart-seo-fixer')); ?>');
                $('#bulk-done-btn').show();
                return;
            }

            // Small batch — synchronous response, already done.
            if (!response.data.queued) {
                $('#bulk-progress-fill').css('width', '100%');
                $('#bulk-progress-text').text('100% (' + total + '/' + total + ')');
                if (response.data.log) {
                    response.data.log.forEach(function(e) {
                        $('#bulk-progress-log').append('<div>' + e + '</div>');
                    });
                    var el = document.getElementById('bulk-progress-log');
                    el.scrollTop = el.scrollHeight;
                }
                $('#bulk-progress-title').html('<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span> <?php echo esc_js(__('Complete!', 'smart-seo-fixer')); ?>');
                $('#bulk-done-btn').show();
                return;
            }

            // Background job queued — poll for progress.
            var jobId = response.data.job_id;
            $('#bulk-progress-log').append(
                '<div style="padding:10px 12px; background:#eff6ff; border-left:4px solid #2563eb; border-radius:4px; margin-bottom:10px;">' +
                '<strong><?php echo esc_js(__('Queued in background (Job #', 'smart-seo-fixer')); ?>' + jobId + ')</strong> — ' +
                esc(response.data.message) + ' ' +
                '<a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-jobs')); ?>" target="_blank"><?php echo esc_js(__('Open Job Queue page', 'smart-seo-fixer')); ?></a>' +
                '</div>'
            );

            // Kick the queue immediately so we don't wait for WP cron's 60s tick.
            $.post(ssfAdmin.ajax_url, { action: 'ssf_queue_tick', token: '<?php echo esc_js(SSF_Job_Queue::get_tick_token()); ?>' });

            pollJob(jobId, total);
        }).fail(function() {
            $('#bulk-progress-log').append('<div style="color:#dc2626;"><?php echo esc_js(__('Request failed.', 'smart-seo-fixer')); ?></div>');
            $('#bulk-done-btn').show();
        });
    }

    function pollJob(jobId, total) {
        var stallCount = 0;
        var lastProcessed = 0;

        function tick() {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_get_job',
                nonce: ssfAdmin.nonce,
                job_id: jobId
            }, function(r) {
                if (!r.success) {
                    $('#bulk-progress-log').append('<div style="color:#dc2626;"><?php echo esc_js(__('Failed to read job status.', 'smart-seo-fixer')); ?></div>');
                    $('#bulk-done-btn').show();
                    return;
                }
                var j = r.data;
                var pct = j.percent || 0;
                $('#bulk-progress-fill').css('width', pct + '%');
                $('#bulk-progress-text').text(pct + '% (' + j.processed + '/' + j.total + ') — ' + j.status);

                if (j.status === 'completed') {
                    $('#bulk-progress-title').html('<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span> <?php echo esc_js(__('Complete!', 'smart-seo-fixer')); ?>');
                    $('#bulk-progress-log').append('<div style="color:#059669;">✅ <?php echo esc_js(__('Background job completed.', 'smart-seo-fixer')); ?> (' + j.processed + '/' + j.total + ')</div>');
                    if (j.summary) {
                        var s = j.summary;
                        var parts = [];
                        parts.push('<strong>' + s.generated + '</strong> <?php echo esc_js(__('generated', 'smart-seo-fixer')); ?>');
                        if (s.skipped > 0) parts.push('<strong>' + s.skipped + '</strong> <?php echo esc_js(__('skipped', 'smart-seo-fixer')); ?>');
                        if (s.failed > 0)  parts.push('<strong style="color:#dc2626;">' + s.failed + '</strong> <?php echo esc_js(__('failed', 'smart-seo-fixer')); ?>');
                        $('#bulk-progress-log').append('<div style="color:#1e293b; margin-top:4px;">📊 ' + parts.join(' · ') + '</div>');
                        if (s.reasons) {
                            var reasonKeys = Object.keys(s.reasons);
                            reasonKeys.forEach(function(k) {
                                $('#bulk-progress-log').append('<div style="color:#64748b; font-size:12px; margin-left:24px;">&bull; ' + s.reasons[k] + ' &mdash; ' + esc(k) + '</div>');
                            });
                        }
                        if (s.skipped > 0 && s.generated === 0) {
                            $('#bulk-progress-log').append(
                                '<div style="color:#b45309; margin-top:8px;">⚠️ <?php echo esc_js(__('All posts were skipped. This usually means post_content is empty (common with page-builder or location templates).', 'smart-seo-fixer')); ?></div>'
                            );
                        }
                    }
                    $('#bulk-done-btn').show();
                    return;
                }
                if (j.status === 'failed') {
                    $('#bulk-progress-title').html('<span class="dashicons dashicons-warning" style="color:#dc2626;"></span> <?php echo esc_js(__('Failed', 'smart-seo-fixer')); ?>');
                    $('#bulk-progress-log').append('<div style="color:#dc2626;">❌ ' + esc(j.error || '<?php echo esc_js(__('Unknown error', 'smart-seo-fixer')); ?>') + '</div>');
                    $('#bulk-done-btn').show();
                    return;
                }
                if (j.status === 'cancelled') {
                    $('#bulk-progress-title').html('<span class="dashicons dashicons-no" style="color:#64748b;"></span> <?php echo esc_js(__('Cancelled', 'smart-seo-fixer')); ?>');
                    $('#bulk-done-btn').show();
                    return;
                }

                // Self-tick the queue if progress stalls (WP cron can be slow).
                if (j.processed === lastProcessed) {
                    stallCount++;
                    if (stallCount >= 2) {
                        $.post(ssfAdmin.ajax_url, { action: 'ssf_queue_tick', token: '<?php echo esc_js(SSF_Job_Queue::get_tick_token()); ?>' });
                        stallCount = 0;
                    }
                } else {
                    stallCount = 0;
                    lastProcessed = j.processed;
                }

                setTimeout(tick, 2000);
            }).fail(function() {
                setTimeout(tick, 3000);
            });
        }

        tick();
    }

    // Done button — go back to config
    $('#bulk-done-btn').on('click', function() {
        $('#bulk-step-progress').hide();
        $('#bulk-step-config').show();
        loadPreview();
    });
});
</script>
