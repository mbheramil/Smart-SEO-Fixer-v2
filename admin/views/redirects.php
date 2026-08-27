<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e('Redirect Manager', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <!-- Add New Redirect -->
    <div class="ssf-card" style="margin-bottom:20px;">
        <div class="ssf-card-header">
            <h2><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add Redirect', 'smart-seo-fixer'); ?></h2>
        </div>
        <div class="ssf-card-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:200px;">
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:13px;"><?php esc_html_e('From URL (old path)', 'smart-seo-fixer'); ?></label>
                    <input type="text" id="ssf-redir-from" class="regular-text" style="width:100%;" placeholder="/old-page/">
                </div>
                <div style="flex:1;min-width:200px;">
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:13px;"><?php esc_html_e('To URL (new destination)', 'smart-seo-fixer'); ?></label>
                    <input type="text" id="ssf-redir-to" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr(home_url('/new-page/')); ?>">
                </div>
                <div style="min-width:120px;">
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:13px;"><?php esc_html_e('Type', 'smart-seo-fixer'); ?></label>
                    <select id="ssf-redir-type" style="width:100%;">
                        <option value="301"><?php esc_html_e('301 Permanent', 'smart-seo-fixer'); ?></option>
                        <option value="302"><?php esc_html_e('302 Temporary', 'smart-seo-fixer'); ?></option>
                        <option value="307"><?php esc_html_e('307 Temporary', 'smart-seo-fixer'); ?></option>
                    </select>
                </div>
                <div style="min-width:200px;">
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:13px;"><?php esc_html_e('Note (optional)', 'smart-seo-fixer'); ?></label>
                    <input type="text" id="ssf-redir-note" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('Why this redirect exists', 'smart-seo-fixer'); ?>">
                </div>
                <button type="button" class="button button-primary" id="ssf-add-redirect" style="height:30px;">
                    <span class="dashicons dashicons-plus" style="line-height:1.5;"></span> <?php esc_html_e('Add', 'smart-seo-fixer'); ?>
                </button>
            </div>
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Use relative paths for "From" (e.g., /old-page/). Use full URLs for "To" (e.g., https://site.com/new-page/). Add * at the end of "From" for wildcard matching — and if "To" ends with a slash, the rest of the path is carried across. Example: /wp-content/uploads/* → https://cdn.example.net/wp-content/uploads/ forwards every file to the same path on your CDN.', 'smart-seo-fixer'); ?>
            </p>
        </div>
    </div>
    
    <!-- Redirects Table -->
    <div class="ssf-card" style="margin-bottom:20px;">
        <div class="ssf-card-header">
            <h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Active Redirects', 'smart-seo-fixer'); ?> <span id="ssf-redir-count" class="ssf-badge" style="display:none;">0</span></h2>
            <button type="button" class="button" id="ssf-refresh-redirects"><span class="dashicons dashicons-update" style="line-height:1.4;"></span> <?php esc_html_e('Refresh', 'smart-seo-fixer'); ?></button>
        </div>
        <div class="ssf-card-body" style="padding:0;">
            <div id="ssf-redir-loading" style="padding:30px;text-align:center;"><span class="spinner is-active" style="float:none;"></span></div>
            <div id="ssf-redir-empty" style="display:none;padding:30px;text-align:center;color:#6b7280;">
                <span class="dashicons dashicons-randomize" style="font-size:40px;width:40px;height:40px;color:#d1d5db;"></span>
                <p><?php esc_html_e('No redirects yet. Add one above or change a post slug — the plugin will auto-create one.', 'smart-seo-fixer'); ?></p>
            </div>
            <table id="ssf-redir-table" class="widefat striped" style="display:none;border:0;">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e('On', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('From', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('To', 'smart-seo-fixer'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Type', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Note', 'smart-seo-fixer'); ?></th>
                        <th style="width:80px;"><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody id="ssf-redir-tbody"></tbody>
            </table>
        </div>
    </div>
    
    <!-- 404 Log -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2><span class="dashicons dashicons-warning"></span> <?php esc_html_e('404 Error Log', 'smart-seo-fixer'); ?></h2>
            <div>
                <button type="button" class="button button-primary" id="ssf-bulk-redirect-404" style="display:none;margin-right:6px;">
                    <span class="dashicons dashicons-randomize" style="line-height:1.5;"></span> <?php esc_html_e('Bulk Redirect Selected', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button" id="ssf-refresh-404" style="margin-right:6px;">
                    <span class="dashicons dashicons-update" style="line-height:1.5;"></span> <?php esc_html_e('Refresh', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button" id="ssf-clear-404" style="color:#dc2626;border-color:#dc2626;"><?php esc_html_e('Clear Log', 'smart-seo-fixer'); ?></button>
            </div>
        </div>
        <div class="ssf-card-body" style="padding:0;">
            <div id="ssf-404-loading" style="padding:30px;text-align:center;"><span class="spinner is-active" style="float:none;"></span></div>
            <div id="ssf-404-empty" style="display:none;padding:30px;text-align:center;color:#6b7280;">
                <p><?php esc_html_e('No 404 errors logged yet.', 'smart-seo-fixer'); ?></p>
            </div>
            <table id="ssf-404-table" class="widefat striped" style="display:none;border:0;">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="ssf-404-select-all"></th>
                        <th><?php esc_html_e('URL', 'smart-seo-fixer'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Hits', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Last Hit', 'smart-seo-fixer'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Action', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody id="ssf-404-tbody"></tbody>
            </table>
        </div>
        <!-- Bulk Redirect Modal -->
        <div id="ssf-bulk-redirect-modal" style="display:none;">
            <div style="position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:999999;">
                <div style="background:#fff;border-radius:12px;padding:24px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <h3 style="margin:0 0 12px;"><?php esc_html_e('Bulk Redirect 404s', 'smart-seo-fixer'); ?></h3>
                    <p style="color:#6b7280;font-size:13px;margin-bottom:16px;">
                        <span id="ssf-bulk-count">0</span> <?php esc_html_e('URLs selected. All will be 301 redirected to:', 'smart-seo-fixer'); ?>
                    </p>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;"><?php esc_html_e('Redirect to:', 'smart-seo-fixer'); ?></label>
                        <input type="url" id="ssf-bulk-redirect-to" class="regular-text" style="width:100%;" value="<?php echo esc_attr(home_url('/')); ?>">
                    </div>
                    <div id="ssf-bulk-progress-wrap" style="display:none;margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                            <span id="ssf-bulk-progress-text" style="color:#374151;font-weight:500;">0 / 0</span>
                            <span id="ssf-bulk-progress-pct" style="color:#6b7280;">0%</span>
                        </div>
                        <div style="background:#e5e7eb;border-radius:9999px;height:10px;overflow:hidden;">
                            <div id="ssf-bulk-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s ease;border-radius:9999px;"></div>
                        </div>
                        <div id="ssf-bulk-progress-status" style="margin-top:8px;font-size:12px;color:#059669;display:none;"></div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="button" id="ssf-bulk-redirect-cancel"><?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>
                        <button type="button" class="button button-primary" id="ssf-bulk-redirect-confirm"><?php esc_html_e('Create Redirects', 'smart-seo-fixer'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadRedirects() {
        $('#ssf-redir-loading').show();
        $('#ssf-redir-table,#ssf-redir-empty').hide();
        $.post(ssfAdmin.ajax_url, {action:'ssf_get_redirects',nonce:ssfAdmin.nonce}, function(r) {
            $('#ssf-redir-loading').hide();
            if (!r.success || !r.data.redirects.length) { $('#ssf-redir-empty').show(); $('#ssf-redir-count').hide(); return; }
            var html = '';
            r.data.redirects.forEach(function(rd) {
                var badge = rd.type == 301 ? 'background:#dbeafe;color:#1d4ed8;' : 'background:#fef3c7;color:#92400e;';
                html += '<tr data-id="'+rd.id+'">';
                html += '<td><input type="checkbox" class="ssf-toggle-redir" data-id="'+rd.id+'" '+(rd.enabled?'checked':'')+'></td>';
                html += '<td style="'+(rd.enabled?'':'opacity:0.5;')+'"><code>'+escH(rd.from)+'</code>'+(rd.auto?' <small style="color:#059669;">⚡ auto</small>':'')+'</td>';
                html += '<td style="'+(rd.enabled?'':'opacity:0.5;')+'"><a href="'+escH(rd.to)+'" target="_blank">'+escH(rd.to)+'</a></td>';
                html += '<td><span style="'+badge+'padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">'+rd.type+'</span></td>';
                html += '<td style="font-size:12px;color:#6b7280;">'+escH(rd.note||'')+'</td>';
                html += '<td><button type="button" class="button ssf-del-redir" data-id="'+rd.id+'" style="color:#dc2626;border-color:#dc2626;font-size:12px;padding:0 8px;min-height:26px;">Delete</button></td>';
                html += '</tr>';
            });
            $('#ssf-redir-tbody').html(html);
            $('#ssf-redir-table').show();
            $('#ssf-redir-count').text(r.data.redirects.length).show();
        });
    }
    
    var all404Urls = []; // Store all 404 URLs for true select-all
    var selectAll404Mode = false;
    
    function load404Log() {
        $('#ssf-404-loading').show();
        $('#ssf-404-table,#ssf-404-empty').hide();
        $.post(ssfAdmin.ajax_url, {action:'ssf_get_404_log',nonce:ssfAdmin.nonce}, function(r) {
            $('#ssf-404-loading').hide();
            if (!r.success || !r.data.log.length) { $('#ssf-404-empty').show(); all404Urls = []; return; }
            all404Urls = r.data.log.map(function(entry) { return entry.url; });
            var html = '';
            r.data.log.forEach(function(entry) {
                html += '<tr>';
                html += '<td><input type="checkbox" class="ssf-404-check" value="'+escA(entry.url)+'"></td>';
                html += '<td><code>'+escH(entry.url)+'</code></td>';
                html += '<td><strong>'+entry.hits+'</strong></td>';
                html += '<td style="font-size:12px;">'+escH(entry.last_hit||'')+'</td>';
                if (entry.redirected) {
                    html += '<td><span style="display:inline-flex;align-items:center;gap:4px;color:#059669;font-weight:600;font-size:12px;"><span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;"></span><?php echo esc_js(__('Redirected', 'smart-seo-fixer')); ?></span></td>';
                } else {
                    html += '<td><button type="button" class="button ssf-404-to-redir" data-url="'+escA(entry.url)+'" style="font-size:12px;padding:0 8px;min-height:26px;">→ Redirect</button></td>';
                }
                html += '</tr>';
            });
            $('#ssf-404-tbody').html(html);
            $('#ssf-404-table').show();
        });
    }
    
    loadRedirects();
    load404Log();
    
    $('#ssf-refresh-redirects').on('click', loadRedirects);

    // Refresh the 404 log (re-checks which entries are now covered by a redirect).
    $('#ssf-refresh-404').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $btn.find('.dashicons').addClass('ssf-spin');
        load404Log();
        // Re-enable shortly after (load404Log has no completion callback exposed here).
        setTimeout(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('ssf-spin');
        }, 800);
    });

    // Add redirect
    $('#ssf-add-redirect').on('click', function() {
        var $btn = $(this);
        var from = $('#ssf-redir-from').val().trim();
        var to = $('#ssf-redir-to').val().trim();
        if (!from || !to) { alert('<?php echo esc_js(__('Both From and To URLs are required.', 'smart-seo-fixer')); ?>'); return; }
        $btn.prop('disabled',true);
        $.post(ssfAdmin.ajax_url, {action:'ssf_add_redirect',nonce:ssfAdmin.nonce,from:from,to:to,redirect_type:$('#ssf-redir-type').val(),note:$('#ssf-redir-note').val()}, function(r) {
            $btn.prop('disabled',false);
            if (r.success) { $('#ssf-redir-from,#ssf-redir-to,#ssf-redir-note').val(''); loadRedirects(); }
            else { alert(r.data.message); }
        }).fail(function() { $btn.prop('disabled',false); });
    });
    
    // Toggle redirect
    $(document).on('change', '.ssf-toggle-redir', function() {
        $.post(ssfAdmin.ajax_url, {action:'ssf_toggle_redirect',nonce:ssfAdmin.nonce,redirect_id:$(this).data('id')});
        var $row = $(this).closest('tr');
        $row.find('td:not(:first-child):not(:last-child)').css('opacity', this.checked?1:0.5);
    });
    
    // Delete redirect
    $(document).on('click', '.ssf-del-redir', function() {
        if (!confirm('<?php echo esc_js(__('Delete this redirect?', 'smart-seo-fixer')); ?>')) return;
        var $btn = $(this);
        $.post(ssfAdmin.ajax_url, {action:'ssf_delete_redirect',nonce:ssfAdmin.nonce,redirect_id:$btn.data('id')}, function(r) {
            if (r.success) { $btn.closest('tr').fadeOut(200, function() { $(this).remove(); }); }
        });
    });
    
    // 404 → Redirect
    $(document).on('click', '.ssf-404-to-redir', function() {
        var url = $(this).data('url');
        $('#ssf-redir-from').val(url);
        $('#ssf-redir-to').focus();
        $('html,body').animate({scrollTop:0}, 300);
    });
    
    // Clear 404 log
    $('#ssf-clear-404').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Clear all 404 log entries?', 'smart-seo-fixer')); ?>')) return;
        $.post(ssfAdmin.ajax_url, {action:'ssf_clear_404_log',nonce:ssfAdmin.nonce}, function() { load404Log(); });
    });
    
    // Select all 404 checkboxes — selects ALL items, not just visible page
    $('#ssf-404-select-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.ssf-404-check').prop('checked', isChecked);
        selectAll404Mode = isChecked;
        toggleBulkBtn();
    });
    $(document).on('change', '.ssf-404-check', function() {
        if (!$(this).is(':checked')) selectAll404Mode = false;
        toggleBulkBtn();
    });
    function toggleBulkBtn() {
        var count = selectAll404Mode ? all404Urls.length : $('.ssf-404-check:checked').length;
        if (count > 0) { $('#ssf-bulk-redirect-404').show(); } else { $('#ssf-bulk-redirect-404').hide(); }
    }
    
    // Bulk redirect modal
    $('#ssf-bulk-redirect-404').on('click', function() {
        var count = selectAll404Mode ? all404Urls.length : $('.ssf-404-check:checked').length;
        if (!count) return;
        $('#ssf-bulk-count').text(count);
        $('#ssf-bulk-redirect-modal').show();
    });
    $('#ssf-bulk-redirect-cancel').on('click', function() { $('#ssf-bulk-redirect-modal').hide(); });
    
    // Bulk redirect confirm — inline sequential processing with progress
    $('#ssf-bulk-redirect-confirm').on('click', function() {
        var redirectTo = $('#ssf-bulk-redirect-to').val().trim();
        if (!redirectTo) { alert('<?php echo esc_js(__('Enter a redirect URL.', 'smart-seo-fixer')); ?>'); return; }
        
        var urls = [];
        if (selectAll404Mode) {
            urls = all404Urls.slice();
        } else {
            $('.ssf-404-check:checked').each(function() { urls.push($(this).val()); });
        }
        if (!urls.length) return;
        
        var total = urls.length;
        var done = 0;
        var failed = 0;
        var $btn = $(this).prop('disabled', true);
        $('#ssf-bulk-redirect-cancel').prop('disabled', true);
        $('#ssf-bulk-progress-wrap').show();
        $('#ssf-bulk-progress-status').hide();
        $('#ssf-bulk-progress-bar').css({'width':'0%','background':'#2271b1'});
        $('#ssf-bulk-progress-text').text('0 / ' + total);
        $('#ssf-bulk-progress-pct').text('0%');
        
        function updateProgress() {
            var pct = Math.round((done / total) * 100);
            $('#ssf-bulk-progress-bar').css('width', pct + '%');
            $('#ssf-bulk-progress-text').text(done + ' / ' + total);
            $('#ssf-bulk-progress-pct').text(pct + '%');
        }
        
        function addNext() {
            if (done >= total) {
                $('#ssf-bulk-progress-bar').css({'width':'100%','background':'#059669'});
                var msg = done + ' <?php echo esc_js(__('redirects created', 'smart-seo-fixer')); ?>';
                if (failed > 0) msg += ' (' + failed + ' <?php echo esc_js(__('failed', 'smart-seo-fixer')); ?>)';
                $('#ssf-bulk-progress-status').text('✓ ' + msg).show();
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Done!', 'smart-seo-fixer')); ?>');
                $('#ssf-bulk-redirect-cancel').prop('disabled', false).text('<?php echo esc_js(__('Close', 'smart-seo-fixer')); ?>');
                setTimeout(function() {
                    $('#ssf-bulk-redirect-modal').hide();
                    $('#ssf-bulk-progress-wrap').hide();
                    $('#ssf-bulk-progress-bar').css('background', '#2271b1');
                    $btn.text('<?php echo esc_js(__('Create Redirects', 'smart-seo-fixer')); ?>');
                    $('#ssf-bulk-redirect-cancel').text('<?php echo esc_js(__('Cancel', 'smart-seo-fixer')); ?>');
                    loadRedirects();
                    load404Log();
                }, 1500);
                return;
            }
            $.post(ssfAdmin.ajax_url, {action:'ssf_add_redirect',nonce:ssfAdmin.nonce,from:urls[done],to:redirectTo,redirect_type:'301',note:'Bulk from 404 log'}, function() {
                done++;
                updateProgress();
                addNext();
            }).fail(function() { done++; failed++; updateProgress(); addNext(); });
        }
        addNext();
    });
    
    function escH(t) { var d=document.createElement('div'); d.textContent=t||''; return d.innerHTML; }
    function escA(t) { return (t||'').replace(/'/g,"&#39;").replace(/"/g,"&quot;"); }
});
</script>
