<?php
/**
 * Job Queue View
 * Shows background job status, progress, and controls.
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_count = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::active_count() : 0;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-clock"></span>
        <?php esc_html_e('Background Jobs', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
        <?php if ($active_count > 0): ?>
            <span style="background: #2563eb; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 13px; margin-left: 8px;">
                <?php printf(esc_html__('%d active', 'smart-seo-fixer'), $active_count); ?>
            </span>
        <?php endif; ?>
    </h1>
    
    <p class="description" style="margin-bottom: 20px;">
        <?php esc_html_e("Large operations (5+ posts) are automatically queued here and processed in the background. On AWS Bedrock, batches of 20 items run in parallel; other providers process sequentially within the provider's rate limit.", 'smart-seo-fixer'); ?>
    </p>
    
    <!-- Rate Limit Status -->
    <?php if (class_exists('SSF_Rate_Limiter')):
        // Show the ACTIVE AI provider's rate limiter bucket, not a hard-coded
        // "OpenAI" one. Before v2.0.40 this card always said "OpenAI Rate
        // Limit" and read the openai bucket even when the site was wired to
        // Bedrock/Claude/Gemini — which (a) was confusing ("why does it say
        // OpenAI when I'm on Bedrock?") and (b) hid the real bucket actually
        // being consumed by Bulk AI Fix.
        $ai_provider_slug = class_exists('SSF_AI') ? SSF_AI::active_provider() : 'openai';
        $ai_provider_label = class_exists('SSF_AI') ? SSF_AI::provider_label() : 'OpenAI';
        $ai_usage = SSF_Rate_Limiter::get_usage($ai_provider_slug);
        $gsc_usage = SSF_Rate_Limiter::get_usage('gsc');
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="ssf-card" style="padding: 20px;">
            <h3 style="margin: 0 0 12px; font-size: 14px; color: #64748b;">
                <span class="dashicons dashicons-admin-generic" style="font-size: 16px; vertical-align: text-bottom;"></span>
                <?php printf(esc_html__('%s Rate Limit', 'smart-seo-fixer'), esc_html($ai_provider_label)); ?>
            </h3>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 24px; font-weight: 700; color: <?php echo $ai_usage['remaining'] < 5 ? '#dc2626' : '#059669'; ?>;">
                        <?php echo intval($ai_usage['remaining']); ?>
                    </span>
                    <span style="color: #64748b; font-size: 13px;">/ <?php echo intval($ai_usage['limit']); ?> <?php esc_html_e('remaining', 'smart-seo-fixer'); ?></span>
                </div>
                <?php if ($ai_usage['resets_in'] > 0): ?>
                    <span style="font-size: 12px; color: #94a3b8;"><?php printf(esc_html__('Resets in %ds', 'smart-seo-fixer'), $ai_usage['resets_in']); ?></span>
                <?php endif; ?>
            </div>
            <div style="margin-top: 8px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                <div style="height: 100%; width: <?php echo $ai_usage['limit'] > 0 ? round(($ai_usage['remaining'] / $ai_usage['limit']) * 100) : 100; ?>%; background: <?php echo $ai_usage['remaining'] < 5 ? '#dc2626' : '#059669'; ?>; border-radius: 2px; transition: width 0.3s;"></div>
            </div>
        </div>
        
        <div class="ssf-card" style="padding: 20px;">
            <h3 style="margin: 0 0 12px; font-size: 14px; color: #64748b;">
                <span class="dashicons dashicons-google" style="font-size: 16px; vertical-align: text-bottom;"></span>
                <?php esc_html_e('GSC Rate Limit', 'smart-seo-fixer'); ?>
            </h3>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 24px; font-weight: 700; color: <?php echo $gsc_usage['remaining'] < 10 ? '#dc2626' : '#059669'; ?>;">
                        <?php echo intval($gsc_usage['remaining']); ?>
                    </span>
                    <span style="color: #64748b; font-size: 13px;">/ <?php echo intval($gsc_usage['limit']); ?> <?php esc_html_e('remaining', 'smart-seo-fixer'); ?></span>
                </div>
                <?php if ($gsc_usage['resets_in'] > 0): ?>
                    <span style="font-size: 12px; color: #94a3b8;"><?php printf(esc_html__('Resets in %ds', 'smart-seo-fixer'), $gsc_usage['resets_in']); ?></span>
                <?php endif; ?>
            </div>
            <div style="margin-top: 8px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                <div style="height: 100%; width: <?php echo $gsc_usage['limit'] > 0 ? round(($gsc_usage['remaining'] / $gsc_usage['limit']) * 100) : 100; ?>%; background: <?php echo $gsc_usage['remaining'] < 10 ? '#dc2626' : '#059669'; ?>; border-radius: 2px; transition: width 0.3s;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Jobs Table -->
    <div class="ssf-card">
        <div class="ssf-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php esc_html_e('Recent Jobs', 'smart-seo-fixer'); ?></h2>
            <button type="button" class="button" id="ssf-refresh-jobs">
                <span class="dashicons dashicons-update" style="vertical-align: text-bottom;"></span>
                <?php esc_html_e('Refresh', 'smart-seo-fixer'); ?>
            </button>
        </div>
        <div class="ssf-card-body" style="padding: 0;">
            <div id="ssf-jobs-loading" style="text-align: center; padding: 40px; display: none;">
                <span class="spinner is-active" style="float: none;"></span>
                <p style="color: #64748b;"><?php esc_html_e('Loading jobs...', 'smart-seo-fixer'); ?></p>
            </div>
            
            <div id="ssf-jobs-empty" style="text-align: center; padding: 40px 20px; display: none;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #10b981;"></span>
                <p style="color: #1e293b; margin-top: 12px; font-size: 14px; font-weight: 600;">
                    <?php esc_html_e('All clear — no background jobs running.', 'smart-seo-fixer'); ?>
                </p>
                <p style="color: #64748b; margin-top: 4px; font-size: 13px; max-width: 480px; margin-left: auto; margin-right: auto;">
                    <?php esc_html_e('Background jobs are created when you run "Bulk AI Fix" on the Search Performance page for pages not appearing in search. Jobs will appear here automatically once started.', 'smart-seo-fixer'); ?>
                </p>
            </div>
            
            <table class="wp-list-table widefat striped" id="ssf-jobs-table" style="display: none;">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('ID', 'smart-seo-fixer'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Status', 'smart-seo-fixer'); ?></th>
                        <th style="width: 200px;"><?php esc_html_e('Progress', 'smart-seo-fixer'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Created', 'smart-seo-fixer'); ?></th>
                        <th style="width: 140px;"><?php esc_html_e('Completed', 'smart-seo-fixer'); ?></th>
                        <th style="width: 120px; text-align: right;"><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody id="ssf-jobs-body"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
.ssf-job-progress {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ssf-job-bar {
    flex: 1;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}
.ssf-job-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}
.ssf-job-bar-fill.processing { background: #3b82f6; }
.ssf-job-bar-fill.completed { background: #10b981; }
.ssf-job-bar-fill.failed { background: #ef4444; }
.ssf-job-bar-fill.cancelled { background: #94a3b8; }
.ssf-job-bar-fill.pending { background: #f59e0b; }

.ssf-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.ssf-status-pending { background: #fef3c7; color: #b45309; }
.ssf-status-processing { background: #dbeafe; color: #1d4ed8; }
.ssf-status-completed { background: #dcfce7; color: #15803d; }
.ssf-status-failed { background: #fee2e2; color: #dc2626; }
.ssf-status-cancelled { background: #f1f5f9; color: #64748b; }
</style>

<script>
jQuery(document).ready(function($) {
    var pollInterval = null;
    
    function typeLabel(type) {
        var labels = {
            'bulk_ai_fix': '<?php esc_html_e('Bulk AI Fix', 'smart-seo-fixer'); ?>',
            'bulk_schema': '<?php esc_html_e('Bulk Schema', 'smart-seo-fixer'); ?>',
            'orphan_fix_batch': '<?php esc_html_e('Orphan Fix', 'smart-seo-fixer'); ?>',
            'not_indexed_ai_fix': '<?php esc_html_e('Not-Indexed AI Fix', 'smart-seo-fixer'); ?>',
            'bulk_404_redirect': '<?php esc_html_e('Bulk 404 Redirect', 'smart-seo-fixer'); ?>'
        };
        return labels[type] || type;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        var now = new Date();
        var diffMs = now - d;
        var diffMins = Math.floor(diffMs / 60000);
        var diffHours = Math.floor(diffMs / 3600000);
        
        if (diffMins < 1) return '<?php esc_html_e('Just now', 'smart-seo-fixer'); ?>';
        if (diffMins < 60) return diffMins + ' <?php esc_html_e('min ago', 'smart-seo-fixer'); ?>';
        if (diffHours < 24) return diffHours + ' <?php esc_html_e('hr ago', 'smart-seo-fixer'); ?>';
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    function loadJobs() {
        $('#ssf-jobs-loading').show();
        $('#ssf-jobs-table, #ssf-jobs-empty').hide();
        
        $.post(ajaxurl, {
            action: 'ssf_get_jobs',
            nonce: ssfAdmin.nonce
        }, function(response) {
            $('#ssf-jobs-loading').hide();
            
            if (!response.success || !response.data.jobs.length) {
                $('#ssf-jobs-empty').show();
                stopPolling();
                return;
            }
            
            var jobs = response.data.jobs;
            var hasActive = false;
            var html = '';
            
            $.each(jobs, function(i, job) {
                if (job.status === 'pending' || job.status === 'processing') {
                    hasActive = true;
                }
                
                var progressHtml = '<div class="ssf-job-progress">' +
                    '<div class="ssf-job-bar"><div class="ssf-job-bar-fill ' + job.status + '" style="width: ' + job.progress + '%;"></div></div>' +
                    '<span style="font-size: 12px; color: #64748b; min-width: 80px; text-align: right;">' + 
                    job.processed_items + '/' + job.total_items;
                
                if (job.failed_items > 0) {
                    progressHtml += ' <span style="color: #dc2626;">(' + job.failed_items + ' <?php esc_html_e('failed', 'smart-seo-fixer'); ?>)</span>';
                }
                progressHtml += '</span></div>';
                
                var actions = '';
                if (job.status === 'pending' || job.status === 'processing') {
                    actions = '<button type="button" class="button button-small ssf-cancel-job" data-id="' + job.id + '">' +
                              '<span class="dashicons dashicons-no" style="font-size:14px; vertical-align:text-bottom;"></span> ' +
                              '<?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>';
                } else if (job.status === 'completed' && job.failed_items > 0) {
                    actions = '<button type="button" class="button button-small ssf-retry-job" data-id="' + job.id + '">' +
                              '<span class="dashicons dashicons-controls-repeat" style="font-size:14px; vertical-align:text-bottom;"></span> ' +
                              '<?php esc_html_e('Retry Failed', 'smart-seo-fixer'); ?></button>';
                } else if (job.status === 'failed') {
                    actions = '<button type="button" class="button button-small ssf-retry-job" data-id="' + job.id + '">' +
                              '<span class="dashicons dashicons-controls-repeat" style="font-size:14px; vertical-align:text-bottom;"></span> ' +
                              '<?php esc_html_e('Retry', 'smart-seo-fixer'); ?></button>';
                }
                
                html += '<tr>';
                html += '<td style="color: #64748b;">#' + job.id + '</td>';
                html += '<td>' + typeLabel(job.job_type) + '</td>';
                html += '<td><span class="ssf-status-badge ssf-status-' + job.status + '">' + job.status + '</span></td>';
                html += '<td>' + progressHtml + '</td>';
                html += '<td style="font-size: 12px; color: #64748b;">' + formatDate(job.created_at) + '</td>';
                html += '<td style="font-size: 12px; color: #64748b;">' + formatDate(job.completed_at) + '</td>';
                html += '<td style="text-align: right;">' + actions + '</td>';
                html += '</tr>';
            });
            
            $('#ssf-jobs-body').html(html);
            $('#ssf-jobs-table').show();
            
            // Auto-poll if there are active jobs
            if (hasActive) {
                startPolling();
            } else {
                stopPolling();
            }
        });
    }
    
    function startPolling() {
        if (pollInterval) return;
        pollInterval = setInterval(loadJobs, 10000);
    }
    
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // Refresh button
    $('#ssf-refresh-jobs').on('click', loadJobs);
    
    // Cancel job
    $(document).on('click', '.ssf-cancel-job', function() {
        var $btn = $(this);
        if (!confirm('<?php esc_html_e('Cancel this job? Items already processed will keep their changes.', 'smart-seo-fixer'); ?>')) return;
        
        $btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'ssf_cancel_job',
            nonce: ssfAdmin.nonce,
            job_id: $btn.data('id')
        }, function(response) {
            loadJobs();
        });
    });
    
    // Retry failed
    $(document).on('click', '.ssf-retry-job', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'ssf_retry_job',
            nonce: ssfAdmin.nonce,
            job_id: $btn.data('id')
        }, function(response) {
            if (response.success) {
                loadJobs();
            } else {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Initial load
    loadJobs();
});
</script>
