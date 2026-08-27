<?php
/**
 * Schema Management View
 */

if (!defined('ABSPATH')) {
    exit;
}

$enable_schema = Smart_SEO_Fixer::get_option('enable_schema', true);
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-shortcode"></span>
        <?php esc_html_e('Schema Management', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <!-- Schema Settings Card -->
    <div class="ssf-card" style="margin-bottom: 20px;">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e('Schema Settings', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <div style="display: flex; align-items: flex-start; gap: 30px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <input type="checkbox" id="ssf-enable-schema" value="1" <?php checked($enable_schema, true); ?>>
                        <strong><?php esc_html_e('Enable Auto Schema Markup', 'smart-seo-fixer'); ?></strong>
                    </label>
                    <p class="description" style="margin: 0;">
                        <?php esc_html_e('When enabled, the plugin automatically generates Article, BreadcrumbList, Organization, and WebSite schemas for your posts.', 'smart-seo-fixer'); ?>
                    </p>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <div style="background: #f0f6fc; border: 1px solid #c8d8e7; border-radius: 6px; padding: 12px 15px;">
                        <strong style="display: block; margin-bottom: 5px;">
                            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; color: #2271b1;"></span>
                            <?php esc_html_e('Auto-generated schemas', 'smart-seo-fixer'); ?>
                        </strong>
                        <small style="color: #50575e; line-height: 1.6;">
                            <?php esc_html_e('Article / BlogPosting, BreadcrumbList, Organization, WebSite — these are always output automatically. Custom schemas below are additional schemas suggested by AI for specific content (e.g. Review, FAQPage, HowTo, Event, Product).', 'smart-seo-fixer'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions Card -->
    <div class="ssf-card" style="margin-bottom: 20px;">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-controls-repeat"></span>
                <?php esc_html_e('Bulk Actions', 'smart-seo-fixer'); ?>
            </h2>
            <div>
                <button type="button" class="button button-primary" id="ssf-regen-all-schemas">
                    <span class="dashicons dashicons-update" style="line-height: 1.4;"></span>
                    <?php esc_html_e('Regenerate All Schemas', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button" id="ssf-remove-all-schemas" style="color: #dc2626; border-color: #dc2626;">
                    <span class="dashicons dashicons-trash" style="line-height: 1.4;"></span>
                    <?php esc_html_e('Remove All Custom Schemas', 'smart-seo-fixer'); ?>
                </button>
            </div>
        </div>
        <div class="ssf-card-body" id="ssf-bulk-progress" style="display: none;">
            <div style="margin-bottom: 10px;">
                <div style="background: #e5e7eb; border-radius: 9999px; height: 10px; overflow: hidden;">
                    <div id="ssf-schema-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.5s ease; border-radius: 9999px;"></div>
                </div>
            </div>
            <div id="ssf-schema-progress-text" style="font-size: 13px; color: #6b7280;"></div>
            <div id="ssf-schema-log" style="max-height: 200px; overflow-y: auto; font-size: 12px; font-family: monospace; margin-top: 10px; padding: 10px; background: #f9fafb; border-radius: 6px; display: none;"></div>
        </div>
    </div>
    
    <!-- Add Schema to a Post -->
    <div class="ssf-card" style="margin-bottom: 20px;">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('Generate Schema for a Post', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e('Search for a post to generate a custom AI schema for it. The AI will analyze the content and suggest the best additional schema type.', 'smart-seo-fixer'); ?>
            </p>
            <div style="display: flex; gap: 10px; align-items: flex-start; max-width: 600px;">
                <div style="flex: 1; position: relative;">
                    <input type="text" 
                           id="ssf-schema-search" 
                           class="regular-text" 
                           style="width: 100%;" 
                           placeholder="<?php esc_attr_e('Type post title to search...', 'smart-seo-fixer'); ?>"
                           autocomplete="off">
                    <div id="ssf-schema-search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 100; background: #fff; border: 1px solid #ddd; border-top: 0; border-radius: 0 0 6px 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 250px; overflow-y: auto;"></div>
                </div>
            </div>
            <div id="ssf-generate-status" style="margin-top: 10px;"></div>
        </div>
    </div>
    
    <!-- Schema List Card -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('Custom Schemas', 'smart-seo-fixer'); ?>
                <span id="ssf-schema-count" class="ssf-badge" style="display: none;">0</span>
            </h2>
            <button type="button" class="button" id="ssf-refresh-list">
                <span class="dashicons dashicons-update" style="line-height: 1.4;"></span>
                <?php esc_html_e('Refresh', 'smart-seo-fixer'); ?>
            </button>
        </div>
        <div class="ssf-card-body" style="padding: 0;">
            <div id="ssf-schema-loading" style="padding: 40px; text-align: center;">
                <span class="spinner is-active" style="float: none;"></span>
                <p><?php esc_html_e('Loading schemas...', 'smart-seo-fixer'); ?></p>
            </div>
            <div id="ssf-schema-empty" style="display: none; padding: 40px; text-align: center; color: #6b7280;">
                <span class="dashicons dashicons-shortcode" style="font-size: 48px; width: 48px; height: 48px; color: #d1d5db;"></span>
                <p style="font-size: 15px; margin-top: 10px;"><?php esc_html_e('No custom schemas found.', 'smart-seo-fixer'); ?></p>
                <p class="description"><?php esc_html_e('Use the search above to generate schemas for individual posts, or use the "Suggest Schema" tool in the post editor.', 'smart-seo-fixer'); ?></p>
            </div>
            <table id="ssf-schema-table" class="widefat striped" style="display: none; border: 0;">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="ssf-select-all-schemas"></th>
                        <th><?php esc_html_e('Post Title', 'smart-seo-fixer'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Post Type', 'smart-seo-fixer'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Schema Type', 'smart-seo-fixer'); ?></th>
                        <th style="width: 250px;"><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                    </tr>
                </thead>
                <tbody id="ssf-schema-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Schema page specific styles */
.ssf-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 11px;
    background: #2271b1;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    margin-left: 6px;
}

#ssf-schema-table td {
    vertical-align: middle;
}

.ssf-schema-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background: #e0f2fe;
    color: #0369a1;
}

.ssf-schema-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.ssf-schema-actions .button {
    font-size: 12px;
    line-height: 1.8;
    min-height: 28px;
    padding: 0 10px;
}

.ssf-schema-preview {
    display: none;
    padding: 15px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.ssf-schema-preview pre {
    margin: 0;
    padding: 10px;
    background: #1e293b;
    color: #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.5;
    max-height: 300px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
}

.ssf-search-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.15s;
}

.ssf-search-item:hover {
    background: #f0f6fc;
}

.ssf-search-item:last-child {
    border-bottom: 0;
}

.ssf-search-item .ssf-search-title {
    font-weight: 500;
    color: #1e293b;
}

.ssf-search-item .ssf-search-type {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
}

.ssf-search-item .ssf-search-has-schema {
    font-size: 11px;
    color: #059669;
    font-weight: 500;
}

.ssf-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 12px 20px;
    border-radius: 8px;
    color: #fff;
    font-weight: 500;
    font-size: 13px;
    z-index: 99999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: ssfSlideIn 0.3s ease;
}

.ssf-toast.success { background: #059669; }
.ssf-toast.error { background: #dc2626; }

@keyframes ssfSlideIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    var searchTimeout = null;
    
    // ========================================
    // Helper: Toast notification
    // ========================================
    function showToast(message, type) {
        var $toast = $('<div class="ssf-toast ' + (type || 'success') + '">' + message + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.fadeOut(300, function() { $(this).remove(); }); }, 3500);
    }
    
    // ========================================
    // Load schema list
    // ========================================
    function loadSchemaList() {
        $('#ssf-schema-loading').show();
        $('#ssf-schema-table, #ssf-schema-empty').hide();
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_schema_list',
            nonce: ssfAdmin.nonce
        }, function(response) {
            $('#ssf-schema-loading').hide();
            
            if (!response.success || !response.data.items.length) {
                $('#ssf-schema-empty').show();
                $('#ssf-schema-count').hide();
                return;
            }
            
            var items = response.data.items;
            var $tbody = $('#ssf-schema-tbody').empty();
            
            items.forEach(function(item) {
                var row = '<tr data-post-id="' + item.id + '">' +
                    '<td><input type="checkbox" class="ssf-schema-check" value="' + item.id + '"></td>' +
                    '<td>' +
                        '<strong><a href="' + item.edit_url + '" target="_blank">' + escHtml(item.title) + '</a></strong>' +
                        '<div class="row-actions">' +
                            '<a href="' + item.view_url + '" target="_blank"><?php esc_html_e('View', 'smart-seo-fixer'); ?></a> | ' +
                            '<a href="' + item.edit_url + '" target="_blank"><?php esc_html_e('Edit', 'smart-seo-fixer'); ?></a>' +
                        '</div>' +
                    '</td>' +
                    '<td>' + escHtml(item.post_type) + '</td>' +
                    '<td><span class="ssf-schema-type-badge">' + escHtml(item.schema_type) + '</span></td>' +
                    '<td class="ssf-schema-actions">' +
                        '<button type="button" class="button ssf-view-schema" data-schema=\'' + escAttr(item.schema_json) + '\'>' +
                            '<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;line-height:1.8;"></span> <?php esc_html_e('View', 'smart-seo-fixer'); ?>' +
                        '</button>' +
                        '<button type="button" class="button ssf-regen-one" data-id="' + item.id + '">' +
                            '<span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;line-height:1.8;"></span> <?php esc_html_e('Regenerate', 'smart-seo-fixer'); ?>' +
                        '</button>' +
                        '<button type="button" class="button ssf-delete-one" data-id="' + item.id + '" style="color:#dc2626;border-color:#dc2626;">' +
                            '<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;line-height:1.8;"></span> <?php esc_html_e('Remove', 'smart-seo-fixer'); ?>' +
                        '</button>' +
                    '</td>' +
                '</tr>' +
                '<tr class="ssf-schema-preview-row" data-post-id="' + item.id + '" style="display:none;">' +
                    '<td colspan="5" class="ssf-schema-preview" style="display:table-cell;">' +
                        '<pre>' + escHtml(item.schema_json) + '</pre>' +
                    '</td>' +
                '</tr>';
                $tbody.append(row);
            });
            
            $('#ssf-schema-table').show();
            $('#ssf-schema-count').text(items.length).show();
            
        }).fail(function() {
            $('#ssf-schema-loading').hide();
            $('#ssf-schema-empty').show().find('p:first').text('<?php echo esc_js(__('Failed to load schemas. Please refresh the page.', 'smart-seo-fixer')); ?>');
        });
    }
    
    // Initial load
    loadSchemaList();
    
    // Refresh button
    $('#ssf-refresh-list').on('click', function() {
        loadSchemaList();
    });
    
    // ========================================
    // View schema JSON
    // ========================================
    $(document).on('click', '.ssf-view-schema', function() {
        var $row = $(this).closest('tr');
        var postId = $row.data('post-id');
        var $previewRow = $('.ssf-schema-preview-row[data-post-id="' + postId + '"]');
        $previewRow.toggle();
    });
    
    // ========================================
    // Delete single schema
    // ========================================
    $(document).on('click', '.ssf-delete-one', function() {
        var $btn = $(this);
        var postId = $btn.data('id');
        
        if (!confirm('<?php echo esc_js(__('Remove custom schema from this post?', 'smart-seo-fixer')); ?>')) return;
        
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_delete_single_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $('tr[data-post-id="' + postId + '"]').fadeOut(300, function() { $(this).remove(); });
                showToast(response.data.message, 'success');
                // Update count
                var count = $('#ssf-schema-tbody tr:visible').length / 2 - 1; // minus the one being removed
                if (count <= 0) {
                    $('#ssf-schema-table').hide();
                    $('#ssf-schema-empty').show();
                    $('#ssf-schema-count').hide();
                } else {
                    $('#ssf-schema-count').text(count);
                }
            } else {
                showToast(response.data.message, 'error');
                $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
            }
        }).fail(function() {
            showToast('<?php echo esc_js(__('Request failed. Please try again.', 'smart-seo-fixer')); ?>', 'error');
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-trash');
        });
    });
    
    // ========================================
    // Regenerate single schema
    // ========================================
    $(document).on('click', '.ssf-regen-one', function() {
        var $btn = $(this);
        var postId = $btn.data('id');
        
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_regenerate_single_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success) {
                if (response.data.removed) {
                    // Schema was removed (no longer needed)
                    $('tr[data-post-id="' + postId + '"]').fadeOut(300, function() { $(this).remove(); });
                    showToast(response.data.message, 'success');
                } else {
                    // Update the row
                    var $row = $('tr[data-post-id="' + postId + '"]:first');
                    $row.find('.ssf-schema-type-badge').text(response.data.schema_type);
                    $row.find('.ssf-view-schema').attr('data-schema', response.data.schema_json);
                    // Update preview if visible
                    var $previewRow = $('.ssf-schema-preview-row[data-post-id="' + postId + '"]');
                    if ($previewRow.is(':visible')) {
                        $previewRow.find('pre').text(response.data.schema_json);
                    }
                    showToast(response.data.message, 'success');
                    // Flash row green
                    $row.css('background', '#d1fae5');
                    setTimeout(function() { $row.css('background', ''); }, 1500);
                }
            } else {
                showToast(response.data.message, 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showToast('<?php echo esc_js(__('Request failed. Please try again.', 'smart-seo-fixer')); ?>', 'error');
        });
    });
    
    // ========================================
    // Select All checkbox
    // ========================================
    $('#ssf-select-all-schemas').on('change', function() {
        $('.ssf-schema-check').prop('checked', $(this).is(':checked'));
    });
    
    // ========================================
    // Bulk: Regenerate All (inline batch with progress)
    // ========================================
    $('#ssf-regen-all-schemas').on('click', function() {
        var $btn = $(this);
        
        if (!confirm('<?php echo esc_js(__('This will re-run AI schema generation for ALL posts with custom schemas. This uses your AI API credits. Continue?', 'smart-seo-fixer')); ?>')) return;
        
        var $progress = $('#ssf-bulk-progress');
        var $bar = $('#ssf-schema-progress-bar');
        var $text = $('#ssf-schema-progress-text');
        var $log = $('#ssf-schema-log');
        
        $btn.prop('disabled', true);
        $progress.show();
        $bar.css({'width':'0%','background':'#2271b1'});
        $text.text('<?php echo esc_js(__('Starting...', 'smart-seo-fixer')); ?>');
        $log.empty().show();
        
        function processBatch(offset) {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_bulk_regenerate_schemas',
                nonce: ssfAdmin.nonce,
                mode: 'regenerate',
                offset: offset,
                batch_size: 2
            }, function(response) {
                if (!response.success) {
                    $text.text(response.data.message || '<?php echo esc_js(__('Error occurred.', 'smart-seo-fixer')); ?>');
                    $btn.prop('disabled', false);
                    return;
                }
                
                var data = response.data;
                var newOffset = offset + data.processed;
                var pct = data.total > 0 ? Math.round((newOffset / data.total) * 100) : 100;
                
                $bar.css('width', pct + '%');
                $text.text(newOffset + ' / ' + data.total + ' <?php echo esc_js(__('posts processed', 'smart-seo-fixer')); ?> (' + pct + '%)');
                
                if (data.log && data.log.length) {
                    data.log.forEach(function(entry) {
                        $log.append('<div>' + entry + '</div>');
                    });
                    $log.scrollTop($log[0].scrollHeight);
                }
                
                if (data.done) {
                    $bar.css({'width':'100%','background':'#059669'});
                    $text.text('<?php echo esc_js(__('Complete!', 'smart-seo-fixer')); ?> ' + newOffset + ' <?php echo esc_js(__('posts processed.', 'smart-seo-fixer')); ?>');
                    $btn.prop('disabled', false);
                    loadSchemaList();
                } else {
                    processBatch(newOffset);
                }
            }).fail(function() {
                $text.text('<?php echo esc_js(__('Request failed. Please try again.', 'smart-seo-fixer')); ?>');
                $btn.prop('disabled', false);
            });
        }
        
        processBatch(0);
    });
    
    // ========================================
    // Bulk: Remove All
    // ========================================
    $('#ssf-remove-all-schemas').on('click', function() {
        if (!confirm('<?php echo esc_js(__('This will permanently remove ALL custom schemas from every post. This cannot be undone. Continue?', 'smart-seo-fixer')); ?>')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_bulk_regenerate_schemas',
            nonce: ssfAdmin.nonce,
            mode: 'remove',
            offset: 0,
            batch_size: 999
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                showToast(response.data.log[0] || '<?php echo esc_js(__('All custom schemas removed.', 'smart-seo-fixer')); ?>', 'success');
                loadSchemaList();
            } else {
                showToast(response.data.message || '<?php echo esc_js(__('Something went wrong.', 'smart-seo-fixer')); ?>', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            showToast('<?php echo esc_js(__('Request failed.', 'smart-seo-fixer')); ?>', 'error');
        });
    });
    
    // ========================================
    // Search posts to generate schema
    // ========================================
    $('#ssf-schema-search').on('input', function() {
        var query = $(this).val().trim();
        var $results = $('#ssf-schema-search-results');
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            $results.hide();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_search_posts_for_schema',
                nonce: ssfAdmin.nonce,
                search: query
            }, function(response) {
                var posts = response.success ? (response.data || []) : [];
                if (!posts.length) {
                    $results.html('<div style="padding:15px;color:#6b7280;text-align:center;"><?php echo esc_js(__('No posts found.', 'smart-seo-fixer')); ?></div>').show();
                    return;
                }
                
                var html = '';
                posts.forEach(function(post) {
                    html += '<div class="ssf-search-item" data-id="' + post.id + '" data-has-schema="' + (post.has_schema ? '1' : '0') + '">';
                    html += '<div><span class="ssf-search-title">' + escHtml(post.title) + '</span><br><span class="ssf-search-type">' + escHtml(post.post_type) + '</span></div>';
                    if (post.has_schema) {
                        html += '<span class="ssf-search-has-schema">✓ <?php echo esc_js(__('Has Schema', 'smart-seo-fixer')); ?></span>';
                    }
                    html += '</div>';
                });
                $results.html(html).show();
            });
        }, 300);
    });
    
    // Click outside to close search results
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#ssf-schema-search, #ssf-schema-search-results').length) {
            $('#ssf-schema-search-results').hide();
        }
    });
    
    // Click on search result to generate schema
    $(document).on('click', '.ssf-search-item', function() {
        var postId = $(this).data('id');
        var hasSchema = $(this).data('has-schema');
        var postTitle = $(this).find('.ssf-search-title').text();
        var $status = $('#ssf-generate-status');
        var $results = $('#ssf-schema-search-results');
        
        $results.hide();
        $('#ssf-schema-search').val(postTitle);
        
        var actionName = hasSchema ? 'ssf_regenerate_single_schema' : 'ssf_generate_schema_for_post';
        
        $status.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> <?php echo esc_js(__('Generating schema... This may take a moment.', 'smart-seo-fixer')); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: actionName,
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                if (response.data.no_schema) {
                    $status.html('<span class="dashicons dashicons-info" style="color:#2271b1;"></span> ' + response.data.message);
                } else {
                    $status.html('<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span> ' + response.data.message);
                    loadSchemaList();
                }
            } else {
                $status.html('<span class="dashicons dashicons-warning" style="color:#dc2626;"></span> ' + response.data.message);
            }
            
            setTimeout(function() { $status.fadeOut(300, function() { $(this).html('').show(); }); }, 5000);
        }).fail(function() {
            $status.html('<span class="dashicons dashicons-warning" style="color:#dc2626;"></span> <?php echo esc_js(__('Request failed. Please try again.', 'smart-seo-fixer')); ?>');
        });
    });
    
    // ========================================
    // Toggle auto-schema setting
    // ========================================
    $('#ssf-enable-schema').on('change', function() {
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_toggle_setting',
            nonce: ssfAdmin.nonce,
            setting_key: 'enable_schema',
            setting_value: enabled
        }, function(response) {
            if (response.success) {
                showToast('<?php echo esc_js(__('Schema setting saved.', 'smart-seo-fixer')); ?>', 'success');
            }
        });
    });
    
    // ========================================
    // Helpers
    // ========================================
    function escHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    function escAttr(text) {
        return (text || '').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
</script>
