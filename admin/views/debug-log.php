<?php
/**
 * Debug Log View
 * Shows plugin event logs with level/category filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

$counts = class_exists('SSF_Logger') ? SSF_Logger::get_counts() : ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-code-standards"></span>
        <?php esc_html_e('Debug Log', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <!-- Level Summary -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div class="ssf-card ssf-log-filter-card" data-level="error" style="text-align: center; padding: 20px; cursor: pointer; border-left: 4px solid #ef4444;">
            <div style="font-size: 28px; font-weight: 700; color: #dc2626;" id="ssf-count-error"><?php echo intval($counts['error']); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Errors', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card ssf-log-filter-card" data-level="warning" style="text-align: center; padding: 20px; cursor: pointer; border-left: 4px solid #f59e0b;">
            <div style="font-size: 28px; font-weight: 700; color: #d97706;" id="ssf-count-warning"><?php echo intval($counts['warning']); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Warnings', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card ssf-log-filter-card" data-level="info" style="text-align: center; padding: 20px; cursor: pointer; border-left: 4px solid #3b82f6;">
            <div style="font-size: 28px; font-weight: 700; color: #2563eb;" id="ssf-count-info"><?php echo intval($counts['info']); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Info', 'smart-seo-fixer'); ?></div>
        </div>
        <div class="ssf-card ssf-log-filter-card" data-level="debug" style="text-align: center; padding: 20px; cursor: pointer; border-left: 4px solid #94a3b8;">
            <div style="font-size: 28px; font-weight: 700; color: #64748b;" id="ssf-count-debug"><?php echo intval($counts['debug']); ?></div>
            <div style="font-size: 13px; color: #64748b; margin-top: 4px;"><?php esc_html_e('Debug', 'smart-seo-fixer'); ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="ssf-card" style="margin-bottom: 24px;">
        <div class="ssf-card-body" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div>
                <label for="ssf-log-level" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Level', 'smart-seo-fixer'); ?>
                </label>
                <select id="ssf-log-level" class="ssf-select">
                    <option value=""><?php esc_html_e('All Levels', 'smart-seo-fixer'); ?></option>
                    <option value="error"><?php esc_html_e('Error', 'smart-seo-fixer'); ?></option>
                    <option value="warning"><?php esc_html_e('Warning', 'smart-seo-fixer'); ?></option>
                    <option value="info"><?php esc_html_e('Info', 'smart-seo-fixer'); ?></option>
                    <option value="debug"><?php esc_html_e('Debug', 'smart-seo-fixer'); ?></option>
                </select>
            </div>
            <div>
                <label for="ssf-log-category" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Category', 'smart-seo-fixer'); ?>
                </label>
                <select id="ssf-log-category" class="ssf-select">
                    <option value=""><?php esc_html_e('All Categories', 'smart-seo-fixer'); ?></option>
                    <option value="ai"><?php esc_html_e('AI', 'smart-seo-fixer'); ?></option>
                    <option value="updater"><?php esc_html_e('Updater', 'smart-seo-fixer'); ?></option>
                    <option value="gsc"><?php esc_html_e('Search Console', 'smart-seo-fixer'); ?></option>
                    <option value="meta"><?php esc_html_e('Meta', 'smart-seo-fixer'); ?></option>
                    <option value="sitemap"><?php esc_html_e('Sitemap', 'smart-seo-fixer'); ?></option>
                    <option value="general"><?php esc_html_e('General', 'smart-seo-fixer'); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="ssf-log-search" style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">
                    <?php esc_html_e('Search', 'smart-seo-fixer'); ?>
                </label>
                <input type="text" id="ssf-log-search" class="regular-text" placeholder="<?php esc_attr_e('Search log messages...', 'smart-seo-fixer'); ?>" style="width: 100%;">
            </div>
            <div>
                <button type="button" class="button button-primary" id="ssf-log-filter">
                    <span class="dashicons dashicons-filter" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Filter', 'smart-seo-fixer'); ?>
                </button>
            </div>
            <div>
                <button type="button" class="button" id="ssf-log-clear" style="color: #dc2626; border-color: #fca5a5;">
                    <span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Clear All', 'smart-seo-fixer'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Log Table -->
    <div class="ssf-card">
        <div class="ssf-card-body" style="padding: 0;">
            <div id="ssf-log-loading" style="text-align: center; padding: 40px; display: none;">
                <span class="spinner is-active" style="float: none;"></span>
                <p style="color: #64748b;"><?php esc_html_e('Loading logs...', 'smart-seo-fixer'); ?></p>
            </div>
            
            <div id="ssf-log-empty" style="text-align: center; padding: 40px; display: none;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #10b981;"></span>
                <p style="color: #64748b; margin-top: 12px;"><?php esc_html_e('No log entries found. The plugin is running smoothly.', 'smart-seo-fixer'); ?></p>
            </div>
            
            <table class="wp-list-table widefat striped" id="ssf-log-table" style="display: none;">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php esc_html_e('Time', 'smart-seo-fixer'); ?></th>
                        <th style="width: 70px;"><?php esc_html_e('Level', 'smart-seo-fixer'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Category', 'smart-seo-fixer'); ?></th>
                        <th><?php esc_html_e('Message', 'smart-seo-fixer'); ?></th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="ssf-log-body"></tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div id="ssf-log-pagination" style="display: none; padding: 16px; border-top: 1px solid #e2e8f0; text-align: center;">
            <button type="button" class="button" id="ssf-log-prev" disabled>
                &laquo; <?php esc_html_e('Previous', 'smart-seo-fixer'); ?>
            </button>
            <span id="ssf-log-page-info" style="margin: 0 16px; color: #64748b;"></span>
            <button type="button" class="button" id="ssf-log-next" disabled>
                <?php esc_html_e('Next', 'smart-seo-fixer'); ?> &raquo;
            </button>
        </div>
    </div>
</div>

<style>
.ssf-select {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    min-width: 140px;
}
.ssf-log-filter-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    transform: translateY(-1px);
    transition: all 0.2s;
}
.ssf-log-filter-card.active {
    box-shadow: 0 0 0 2px #3b82f6;
}
.ssf-level-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ssf-level-error { background: #fef2f2; color: #dc2626; }
.ssf-level-warning { background: #fffbeb; color: #d97706; }
.ssf-level-info { background: #eff6ff; color: #2563eb; }
.ssf-level-debug { background: #f8fafc; color: #94a3b8; }
.ssf-cat-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}
.ssf-log-context {
    display: none;
    background: #1e293b;
    color: #e2e8f0;
    padding: 12px 16px;
    border-radius: 6px;
    margin-top: 8px;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 200px;
    overflow: auto;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var totalPages = 1;
    
    function loadLogs(page) {
        page = page || 1;
        currentPage = page;
        
        $('#ssf-log-loading').show();
        $('#ssf-log-table, #ssf-log-empty, #ssf-log-pagination').hide();
        
        $.post(ajaxurl, {
            action: 'ssf_get_logs',
            nonce: ssfAdmin.nonce,
            page: page,
            per_page: 50,
            level: $('#ssf-log-level').val(),
            category: $('#ssf-log-category').val(),
            search: $('#ssf-log-search').val()
        }, function(response) {
            $('#ssf-log-loading').hide();
            
            if (!response.success || !response.data.items.length) {
                $('#ssf-log-empty').show();
                return;
            }
            
            var data = response.data;
            totalPages = data.total_pages;
            
            // Update counts
            if (data.counts) {
                $('#ssf-count-error').text(data.counts.error);
                $('#ssf-count-warning').text(data.counts.warning);
                $('#ssf-count-info').text(data.counts.info);
                $('#ssf-count-debug').text(data.counts.debug);
            }
            
            var html = '';
            $.each(data.items, function(i, item) {
                var contextBtn = '';
                if (item.context) {
                    contextBtn = '<button type="button" class="button button-small ssf-toggle-context" data-idx="' + i + '" title="<?php esc_attr_e('Show details', 'smart-seo-fixer'); ?>">' +
                                 '<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span></button>';
                }
                
                html += '<tr>';
                html += '<td style="font-size: 12px; color: #64748b; white-space: nowrap;">' + formatDate(item.created_at) + '</td>';
                html += '<td><span class="ssf-level-badge ssf-level-' + item.level + '">' + item.level + '</span></td>';
                html += '<td><span class="ssf-cat-badge">' + item.category + '</span></td>';
                html += '<td>' + 
                         '<span style="font-size: 13px;">' + escHtml(item.message) + '</span>' +
                         (item.context ? '<div class="ssf-log-context" id="ssf-ctx-' + i + '">' + formatContext(item.context) + '</div>' : '') +
                         '</td>';
                html += '<td>' + contextBtn + '</td>';
                html += '</tr>';
            });
            
            $('#ssf-log-body').html(html);
            $('#ssf-log-table').show();
            
            if (totalPages > 1) {
                $('#ssf-log-pagination').show();
                $('#ssf-log-page-info').text(
                    '<?php esc_html_e('Page', 'smart-seo-fixer'); ?> ' + currentPage + ' / ' + totalPages
                );
                $('#ssf-log-prev').prop('disabled', currentPage <= 1);
                $('#ssf-log-next').prop('disabled', currentPage >= totalPages);
            }
        });
    }
    
    function escHtml(str) {
        return $('<span/>').text(str || '').html();
    }
    
    function formatContext(ctx) {
        if (typeof ctx === 'string') {
            try {
                ctx = JSON.parse(ctx);
            } catch(e) {
                return escHtml(ctx);
            }
        }
        return escHtml(JSON.stringify(ctx, null, 2));
    }
    
    function formatDate(dateStr) {
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
    
    // Toggle context
    $(document).on('click', '.ssf-toggle-context', function() {
        var idx = $(this).data('idx');
        $('#ssf-ctx-' + idx).slideToggle(200);
    });
    
    // Level card click -> filter
    $('.ssf-log-filter-card').on('click', function() {
        var level = $(this).data('level');
        $('.ssf-log-filter-card').removeClass('active');
        
        if ($('#ssf-log-level').val() === level) {
            $('#ssf-log-level').val('');
        } else {
            $(this).addClass('active');
            $('#ssf-log-level').val(level);
        }
        loadLogs(1);
    });
    
    // Filter button
    $('#ssf-log-filter').on('click', function() {
        loadLogs(1);
    });
    
    // Enter key
    $('#ssf-log-search').on('keypress', function(e) {
        if (e.which === 13) loadLogs(1);
    });
    
    // Pagination
    $('#ssf-log-prev').on('click', function() {
        if (currentPage > 1) loadLogs(currentPage - 1);
    });
    
    $('#ssf-log-next').on('click', function() {
        if (currentPage < totalPages) loadLogs(currentPage + 1);
    });
    
    // Clear all logs
    $('#ssf-log-clear').on('click', function() {
        if (!confirm('<?php esc_html_e('Are you sure you want to clear all log entries? This cannot be undone.', 'smart-seo-fixer'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'ssf_clear_logs',
            nonce: ssfAdmin.nonce
        }, function(response) {
            if (response.success) {
                loadLogs(1);
                $('#ssf-count-error, #ssf-count-warning, #ssf-count-info, #ssf-count-debug').text('0');
            }
        });
    });
    
    // Initial load
    loadLogs(1);
});
</script>
