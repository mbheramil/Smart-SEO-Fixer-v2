<?php
/**
 * Migration View
 */

if (!defined('ABSPATH')) {
    exit;
}

$migration = new SSF_Migration();
$detected_plugins = $migration->detect_plugins();
$last_migration = get_option('ssf_last_migration', null);
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-migrate"></span>
        <?php esc_html_e('Migration Tool', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <div class="ssf-migration-intro">
        <p><?php esc_html_e('Import your existing SEO data from Yoast SEO, Rank Math, All in One SEO, SEOPress, or The SEO Framework.', 'smart-seo-fixer'); ?></p>
        <p>
            <strong><?php esc_html_e('What gets migrated:', 'smart-seo-fixer'); ?></strong>
            <?php esc_html_e('SEO titles, meta descriptions, focus keywords, canonical URLs, noindex/nofollow flags, and social media (Open Graph + Twitter) titles, descriptions, and images — for posts of every status (drafts and private pages included). Template variables like %%title%% or %sitename% are resolved into real text during import.', 'smart-seo-fixer'); ?>
        </p>
        <p style="color:#64748b;font-size:13px;">
            <strong><?php esc_html_e('Not migrated:', 'smart-seo-fixer'); ?></strong>
            <?php esc_html_e('redirects managed by the old plugin, category/taxonomy SEO, and sitewide title templates — review those manually after import.', 'smart-seo-fixer'); ?>
        </p>
    </div>
    
    <?php if ($last_migration): ?>
    <div class="ssf-notice ssf-notice-info">
        <p>
            <strong><?php esc_html_e('Last Migration:', 'smart-seo-fixer'); ?></strong>
            <?php 
            $plugin_name = $last_migration['plugin'] ?? 'Unknown';
            $date = $last_migration['date'] ?? '';
            $migrated = $last_migration['results']['migrated'] ?? 0;
            
            printf(
                __('Imported %d posts from %s on %s', 'smart-seo-fixer'),
                $migrated,
                ucfirst($plugin_name),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date))
            );
            ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (empty($detected_plugins)): ?>
    <div class="ssf-card">
        <div class="ssf-card-body">
            <div class="ssf-empty-state">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3><?php esc_html_e('No SEO plugins detected', 'smart-seo-fixer'); ?></h3>
                <p><?php esc_html_e('We didn\'t find any other SEO plugins with data to migrate. You\'re starting fresh!', 'smart-seo-fixer'); ?></p>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Detected Plugins -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-plugins-checked"></span>
                <?php esc_html_e('Detected SEO Plugins', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <div class="ssf-plugins-grid">
                <?php foreach ($detected_plugins as $key => $plugin): ?>
                <div class="ssf-plugin-card" data-plugin="<?php echo esc_attr($key); ?>">
                    <div class="ssf-plugin-header">
                        <h3><?php echo esc_html($plugin['name']); ?></h3>
                        <?php if ($plugin['active']): ?>
                        <span class="ssf-badge ssf-badge-warning"><?php esc_html_e('Active', 'smart-seo-fixer'); ?></span>
                        <?php else: ?>
                        <span class="ssf-badge ssf-badge-info"><?php esc_html_e('Data Found', 'smart-seo-fixer'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ssf-plugin-stats">
                        <div class="ssf-stat">
                            <span class="ssf-stat-number"><?php echo esc_html($plugin['post_count']); ?></span>
                            <span class="ssf-stat-label"><?php esc_html_e('Posts with SEO data', 'smart-seo-fixer'); ?></span>
                        </div>
                    </div>
                    <div class="ssf-plugin-actions">
                        <button type="button" class="button preview-migration-btn" data-plugin="<?php echo esc_attr($key); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Preview', 'smart-seo-fixer'); ?>
                        </button>
                        <button type="button" class="button button-primary start-migration-btn" data-plugin="<?php echo esc_attr($key); ?>">
                            <span class="dashicons dashicons-migrate"></span>
                            <?php esc_html_e('Migrate', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- Migration Instructions -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('Migration Instructions', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <ol class="ssf-instructions">
                <li>
                    <strong><?php esc_html_e('Preview first', 'smart-seo-fixer'); ?></strong>
                    <p><?php esc_html_e('Click "Preview" to see what data will be imported before migrating.', 'smart-seo-fixer'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Run the migration', 'smart-seo-fixer'); ?></strong>
                    <p><?php esc_html_e('Click "Migrate" to import all SEO data. Existing Smart SEO Fixer data will NOT be overwritten unless you check the overwrite option.', 'smart-seo-fixer'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Verify the import', 'smart-seo-fixer'); ?></strong>
                    <p><?php esc_html_e('Check a few posts to make sure the data was imported correctly.', 'smart-seo-fixer'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Disable the old plugin', 'smart-seo-fixer'); ?></strong>
                    <p><?php esc_html_e('Once verified, deactivate your old SEO plugin to prevent conflicts.', 'smart-seo-fixer'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Run bulk analysis (optional)', 'smart-seo-fixer'); ?></strong>
                    <p><?php esc_html_e('Go to the Dashboard and run "Analyze All" to get SEO scores and AI suggestions for improvements.', 'smart-seo-fixer'); ?></p>
                </li>
            </ol>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="ssf-modal" id="preview-modal" style="display: none;">
    <div class="ssf-modal-content ssf-modal-large">
        <div class="ssf-modal-header">
            <h3><?php esc_html_e('Migration Preview', 'smart-seo-fixer'); ?></h3>
            <button type="button" class="ssf-modal-close">&times;</button>
        </div>
        <div class="ssf-modal-body">
            <div id="preview-loading" class="ssf-loading">
                <span class="spinner is-active"></span>
                <p><?php esc_html_e('Loading preview...', 'smart-seo-fixer'); ?></p>
            </div>
            <div id="preview-content" style="display: none;">
                <p class="ssf-preview-summary">
                    <?php esc_html_e('Found', 'smart-seo-fixer'); ?> <strong id="preview-total">0</strong> <?php esc_html_e('posts with SEO data.', 'smart-seo-fixer'); ?>
                    <?php esc_html_e('Showing first 20:', 'smart-seo-fixer'); ?>
                </p>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Post', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Meta Description', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Status', 'smart-seo-fixer'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="preview-table-body">
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ssf-modal-footer">
            <button type="button" class="button ssf-modal-close"><?php esc_html_e('Close', 'smart-seo-fixer'); ?></button>
        </div>
    </div>
</div>

<!-- Migration Modal -->
<div class="ssf-modal" id="migration-modal" style="display: none;">
    <div class="ssf-modal-content">
        <div class="ssf-modal-header">
            <h3><?php esc_html_e('Start Migration', 'smart-seo-fixer'); ?></h3>
            <button type="button" class="ssf-modal-close">&times;</button>
        </div>
        <div class="ssf-modal-body">
            <form id="migration-form">
                <input type="hidden" name="plugin" id="migration-plugin" value="">
                
                <p><?php esc_html_e('Ready to migrate SEO data from', 'smart-seo-fixer'); ?> <strong id="migration-plugin-name"></strong>.</p>
                
                <div class="ssf-form-field">
                    <label>
                        <input type="checkbox" name="overwrite" value="1">
                        <?php esc_html_e('Overwrite existing Smart SEO Fixer data', 'smart-seo-fixer'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('If unchecked, posts that already have SEO data in Smart SEO Fixer will be skipped.', 'smart-seo-fixer'); ?></p>
                </div>
                
                <div class="ssf-form-field">
                    <label>
                        <input type="checkbox" name="auto_fix" value="1">
                        <?php esc_html_e('Run SEO analysis after migration', 'smart-seo-fixer'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Recommended: Leave unchecked for faster migration. You can run analysis from the Dashboard afterwards.', 'smart-seo-fixer'); ?></p>
                </div>
            </form>
            
            <div id="migration-progress" style="display: none;">
                <div class="ssf-progress-container">
                    <div class="ssf-progress-bar">
                        <div class="ssf-progress-fill" id="migration-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="ssf-progress-percent" id="migration-progress-percent">0%</div>
                </div>
                <p class="ssf-progress-text" id="migration-progress-text"><?php esc_html_e('Starting migration...', 'smart-seo-fixer'); ?></p>
            </div>
            
            <div id="migration-results" style="display: none;">
                <div class="ssf-results-summary">
                    <div class="ssf-result-item ssf-result-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span id="result-migrated">0</span> <?php esc_html_e('migrated', 'smart-seo-fixer'); ?>
                    </div>
                    <div class="ssf-result-item ssf-result-skipped">
                        <span class="dashicons dashicons-minus"></span>
                        <span id="result-skipped">0</span> <?php esc_html_e('skipped', 'smart-seo-fixer'); ?>
                    </div>
                </div>
                <div class="ssf-next-steps">
                    <p><strong><?php esc_html_e('✓ Migration complete!', 'smart-seo-fixer'); ?></strong></p>
                    <p><?php esc_html_e('Next steps:', 'smart-seo-fixer'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Deactivate your old SEO plugin (Yoast, etc.)', 'smart-seo-fixer'); ?></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer')); ?>"><?php esc_html_e('Go to Dashboard → Run "Analyze All" to get SEO scores', 'smart-seo-fixer'); ?></a></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="ssf-modal-footer">
            <button type="button" class="button ssf-modal-close"><?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>
            <button type="button" class="button button-primary" id="run-migration-btn">
                <span class="dashicons dashicons-migrate"></span>
                <?php esc_html_e('Start Migration', 'smart-seo-fixer'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.ssf-migration-intro {
    margin-bottom: 20px;
    font-size: 14px;
    color: #666;
}

.ssf-empty-state {
    text-align: center;
    padding: 40px;
}

.ssf-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #46b450;
}

.ssf-empty-state h3 {
    margin: 15px 0 10px;
}

.ssf-plugins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.ssf-plugin-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    background: #fafafa;
}

.ssf-plugin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ssf-plugin-header h3 {
    margin: 0;
    font-size: 16px;
}

.ssf-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.ssf-badge-warning {
    background: #fff3cd;
    color: #856404;
}

.ssf-badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.ssf-plugin-stats {
    margin-bottom: 15px;
}

.ssf-plugin-stats .ssf-stat {
    display: flex;
    flex-direction: column;
}

.ssf-plugin-stats .ssf-stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #2563eb;
}

.ssf-plugin-stats .ssf-stat-label {
    font-size: 12px;
    color: #666;
}

.ssf-plugin-actions {
    display: flex;
    gap: 10px;
}

.ssf-plugin-actions .button .dashicons {
    margin-right: 3px;
    vertical-align: middle;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.ssf-instructions {
    margin: 0;
    padding-left: 20px;
}

.ssf-instructions li {
    margin-bottom: 15px;
}

.ssf-instructions li strong {
    display: block;
    margin-bottom: 5px;
}

.ssf-instructions li p {
    margin: 0;
    color: #666;
}

.ssf-modal-large {
    max-width: 800px;
}

.ssf-preview-summary {
    margin-bottom: 15px;
}

.ssf-form-field {
    margin-bottom: 15px;
}

.ssf-form-field label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.ssf-form-field .description {
    margin: 5px 0 0 26px;
    font-size: 12px;
    color: #666;
}

.ssf-results-summary {
    display: flex;
    gap: 30px;
    justify-content: center;
    padding: 20px;
}

.ssf-result-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
}

.ssf-result-success .dashicons { color: #46b450; }
.ssf-result-skipped .dashicons { color: #f0ad4e; }

.ssf-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.ssf-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    position: absolute;
    right: 15px;
    top: 15px;
}

.ssf-notice-info {
    background: #d1ecf1;
    border-left: 4px solid #0c5460;
}

.ssf-next-steps {
    margin-top: 20px;
    padding: 15px;
    background: #e8f5e9;
    border-radius: 6px;
    border-left: 4px solid #46b450;
}

.ssf-next-steps ol {
    margin: 10px 0 0 20px;
}

.ssf-next-steps li {
    margin-bottom: 5px;
}

.ssf-progress-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}

.ssf-progress-container .ssf-progress-bar {
    flex: 1;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.ssf-progress-container .ssf-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2563eb, #3b82f6);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.ssf-progress-percent {
    font-size: 18px;
    font-weight: bold;
    color: #2563eb;
    min-width: 50px;
}

.ssf-progress-text {
    text-align: center;
    color: #666;
    margin: 10px 0 0;
    line-height: 1.6;
}

.ssf-progress-text small {
    color: #888;
}
</style>

<script>
jQuery(document).ready(function($) {
    var currentPlugin = '';
    
    // Preview button
    $('.preview-migration-btn').on('click', function() {
        currentPlugin = $(this).data('plugin');
        
        $('#preview-loading').show();
        $('#preview-content').hide();
        $('#preview-modal').show();
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_migration_preview',
            nonce: ssfAdmin.nonce,
            plugin: currentPlugin
        }, function(response) {
            $('#preview-loading').hide();
            
            if (response.success) {
                $('#preview-total').text(response.data.total);
                
                var html = '';
                response.data.preview.forEach(function(item) {
                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(item.title) + '</strong><br><small>' + item.type + '</small></td>';
                    html += '<td>' + escapeHtml(item.seo_title || '—') + '</td>';
                    html += '<td>' + escapeHtml(truncate(item.seo_description || '—', 60)) + '</td>';
                    html += '<td>' + (item.already_migrated ? '<span class="ssf-badge ssf-badge-info">Already migrated</span>' : '<span class="ssf-badge ssf-badge-warning">Will import</span>') + '</td>';
                    html += '</tr>';
                });
                
                $('#preview-table-body').html(html);
                $('#preview-content').show();
            }
        });
    });
    
    // Start migration button (opens modal)
    $('.start-migration-btn').on('click', function() {
        currentPlugin = $(this).data('plugin');
        var pluginName = $(this).closest('.ssf-plugin-card').find('h3').text();
        
        $('#migration-plugin').val(currentPlugin);
        $('#migration-plugin-name').text(pluginName);
        $('#migration-form').show();
        $('#migration-progress').hide();
        $('#migration-results').hide();
        $('#run-migration-btn').show();
        $('#migration-modal').show();
    });
    
    // Run migration with batch processing
    $('#run-migration-btn').on('click', function() {
        var $btn = $(this);
        var overwrite = $('input[name="overwrite"]').is(':checked');
        
        $btn.hide();
        $('#migration-form').hide();
        $('#migration-progress').show();
        $('#migration-progress-fill').css('width', '0%');
        $('#migration-progress-percent').text('0%');
        $('#migration-progress-text').text('<?php esc_html_e('Starting migration...', 'smart-seo-fixer'); ?>');
        
        // Start migration directly (skip reset to avoid extra request)
        runMigrationBatch(0, overwrite);
    });
    
    function runMigrationBatch(offset, overwrite) {
        console.log('Starting batch at offset:', offset);
        
        $.ajax({
            url: ssfAdmin.ajax_url,
            type: 'POST',
            timeout: 60000, // 60 second timeout
            data: {
                action: 'ssf_migrate_from_plugin',
                nonce: ssfAdmin.nonce,
                plugin: currentPlugin,
                overwrite: overwrite ? 1 : 0,
                offset: offset
            },
            success: function(response) {
                console.log('Response:', response);
                
                if (!response.success) {
                    $('#migration-progress').hide();
                    $('#migration-form').show();
                    $('#run-migration-btn').show();
                    alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Migration failed. Check console for details.', 'smart-seo-fixer'); ?>');
                    return;
                }
                
                var data = response.data;
                
                // Update progress bar
                $('#migration-progress-fill').css('width', data.percent + '%');
                $('#migration-progress-percent').text(data.percent + '%');
                $('#migration-progress-text').html(
                    data.processed + ' / ' + data.total + ' <?php esc_html_e('posts processed', 'smart-seo-fixer'); ?>' +
                    '<br><small><?php esc_html_e('Migrated:', 'smart-seo-fixer'); ?> ' + data.migrated + 
                    ' | <?php esc_html_e('Skipped:', 'smart-seo-fixer'); ?> ' + data.skipped + '</small>'
                );
                
                if (data.done) {
                    // Migration complete
                    setTimeout(function() {
                        $('#migration-progress').hide();
                        $('#result-migrated').text(data.migrated);
                        $('#result-skipped').text(data.skipped);
                        $('#migration-results').show();
                    }, 500);
                } else {
                    // Continue with next batch (small delay to prevent overwhelming server)
                    setTimeout(function() {
                        runMigrationBatch(data.next_offset, overwrite);
                    }, 100);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                $('#migration-progress').hide();
                $('#migration-form').show();
                $('#run-migration-btn').show();
                
                var errorMsg = '<?php esc_html_e('Connection error.', 'smart-seo-fixer'); ?>';
                if (status === 'timeout') {
                    errorMsg = '<?php esc_html_e('Request timed out. The server may be slow.', 'smart-seo-fixer'); ?>';
                }
                alert(errorMsg + ' ' + error);
            }
        });
    }
    
    // Close modals
    $('.ssf-modal-close').on('click', function() {
        $(this).closest('.ssf-modal').hide();
    });
    
    // Close on background click
    $('.ssf-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function truncate(text, length) {
        if (!text || text.length <= length) return text;
        return text.substring(0, length) + '...';
    }
});
</script>

