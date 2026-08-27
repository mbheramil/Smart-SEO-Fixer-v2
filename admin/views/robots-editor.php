<?php
/**
 * robots.txt Editor View
 */
if (!defined('ABSPATH')) exit;

$is_enabled = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::is_enabled() : false;
$content = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::get_content() : '';
$default_content = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::get_default_content() : '';
$recommended = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::get_recommended_content() : '';
$has_physical = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::has_physical_file() : false;
$robots_url = class_exists('SSF_Robots_Editor') ? SSF_Robots_Editor::get_robots_url() : home_url('/robots.txt');
$warnings = ($is_enabled && !empty($content)) ? SSF_Robots_Editor::validate($content) : [];

if (empty($content)) {
    $content = $default_content;
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-media-text"></span>
        <?php esc_html_e('robots.txt Editor', 'smart-seo-fixer'); ?>
    </h1>
    
    <?php if ($has_physical): ?>
    <div class="ssf-notice" style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <strong><?php esc_html_e('Warning:', 'smart-seo-fixer'); ?></strong>
        <?php esc_html_e('A physical robots.txt file exists in your site root. It will override any virtual robots.txt (including this editor). Delete the physical file to use this editor.', 'smart-seo-fixer'); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($warnings)): ?>
    <div class="ssf-notice" style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <?php foreach ($warnings as $warning): ?>
        <p style="margin: 4px 0;"><span class="dashicons dashicons-warning" style="color: #ef4444; font-size: 16px;"></span> <?php echo esc_html($warning); ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="ssf-robots-container">
        <div class="ssf-robots-main">
            <!-- Enable/Disable Toggle -->
            <div class="ssf-robots-toggle-card">
                <label class="ssf-robots-toggle">
                    <span>
                        <strong><?php esc_html_e('Use Custom robots.txt', 'smart-seo-fixer'); ?></strong>
                        <small><?php esc_html_e('Override WordPress default robots.txt with your custom content', 'smart-seo-fixer'); ?></small>
                    </span>
                    <input type="checkbox" id="ssf-robots-enabled" <?php checked($is_enabled); ?>>
                    <span class="ssf-toggle-switch"></span>
                </label>
            </div>
            
            <!-- Editor -->
            <div class="ssf-robots-editor-card">
                <div class="ssf-robots-editor-header">
                    <h3><?php esc_html_e('robots.txt Content', 'smart-seo-fixer'); ?></h3>
                    <div>
                        <button type="button" class="button ssf-btn-sm" id="ssf-robots-load-default"><?php esc_html_e('Load Default', 'smart-seo-fixer'); ?></button>
                        <button type="button" class="button ssf-btn-sm" id="ssf-robots-load-recommended"><?php esc_html_e('Load Recommended', 'smart-seo-fixer'); ?></button>
                    </div>
                </div>
                <textarea id="ssf-robots-content" class="ssf-robots-textarea" rows="20"><?php echo esc_textarea($content); ?></textarea>
                <div class="ssf-robots-footer">
                    <a href="<?php echo esc_url($robots_url); ?>" target="_blank" class="ssf-robots-preview-link">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('View live robots.txt', 'smart-seo-fixer'); ?>
                    </a>
                    <button type="button" class="button button-primary" id="ssf-robots-save">
                        <?php esc_html_e('Save robots.txt', 'smart-seo-fixer'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="ssf-robots-sidebar">
            <div class="ssf-robots-help">
                <h4><?php esc_html_e('Quick Reference', 'smart-seo-fixer'); ?></h4>
                <div class="ssf-robots-help-item">
                    <code>User-agent: *</code>
                    <small><?php esc_html_e('Apply rules to all bots', 'smart-seo-fixer'); ?></small>
                </div>
                <div class="ssf-robots-help-item">
                    <code>Disallow: /path/</code>
                    <small><?php esc_html_e('Block crawling of a path', 'smart-seo-fixer'); ?></small>
                </div>
                <div class="ssf-robots-help-item">
                    <code>Allow: /path/</code>
                    <small><?php esc_html_e('Allow crawling of a path', 'smart-seo-fixer'); ?></small>
                </div>
                <div class="ssf-robots-help-item">
                    <code>Sitemap: https://...</code>
                    <small><?php esc_html_e('Point to your XML sitemap', 'smart-seo-fixer'); ?></small>
                </div>
                <div class="ssf-robots-help-item">
                    <code>Crawl-delay: 10</code>
                    <small><?php esc_html_e('Seconds between requests (some bots)', 'smart-seo-fixer'); ?></small>
                </div>
            </div>
            
            <div class="ssf-robots-help" style="margin-top: 16px;">
                <h4><?php esc_html_e('Tips', 'smart-seo-fixer'); ?></h4>
                <ul style="margin: 0; padding-left: 16px; font-size: 12px; color: #6b7280;">
                    <li><?php esc_html_e('Never block your sitemap URL', 'smart-seo-fixer'); ?></li>
                    <li><?php esc_html_e('"Disallow: /" blocks everything - use carefully', 'smart-seo-fixer'); ?></li>
                    <li><?php esc_html_e('Changes take effect immediately', 'smart-seo-fixer'); ?></li>
                    <li><?php esc_html_e('robots.txt is a suggestion, not enforcement', 'smart-seo-fixer'); ?></li>
                    <li><?php esc_html_e('Use Google Search Console to test changes', 'smart-seo-fixer'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.ssf-robots-container { display: grid; grid-template-columns: 1fr 280px; gap: 20px; }
@media (max-width: 960px) { .ssf-robots-container { grid-template-columns: 1fr; } }

.ssf-robots-toggle-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
.ssf-robots-toggle { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.ssf-robots-toggle strong { display: block; font-size: 14px; color: #1e293b; }
.ssf-robots-toggle small { color: #6b7280; font-size: 12px; }
.ssf-robots-toggle input { display: none; }
.ssf-toggle-switch { width: 44px; height: 24px; background: #d1d5db; border-radius: 12px; position: relative; flex-shrink: 0; transition: background 0.2s; }
.ssf-toggle-switch::after { content: ''; width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 3px; left: 3px; transition: transform 0.2s; }
.ssf-robots-toggle input:checked ~ .ssf-toggle-switch { background: #3b82f6; }
.ssf-robots-toggle input:checked ~ .ssf-toggle-switch::after { transform: translateX(20px); }

.ssf-robots-editor-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.ssf-robots-editor-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid #e5e7eb; }
.ssf-robots-editor-header h3 { margin: 0; font-size: 14px; }
.ssf-robots-textarea { width: 100%; padding: 16px; border: none; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; line-height: 1.6; resize: vertical; box-sizing: border-box; background: #1e293b; color: #e2e8f0; }
.ssf-robots-textarea:focus { outline: none; }
.ssf-robots-footer { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-top: 1px solid #e5e7eb; background: #f9fafb; }
.ssf-robots-preview-link { color: #6b7280; font-size: 12px; text-decoration: none; display: flex; align-items: center; gap: 4px; }
.ssf-robots-preview-link:hover { color: #3b82f6; }
.ssf-robots-preview-link .dashicons { font-size: 14px; width: 14px; height: 14px; }

.ssf-robots-sidebar .ssf-robots-help { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
.ssf-robots-help h4 { margin: 0 0 12px; font-size: 13px; color: #374151; }
.ssf-robots-help-item { margin-bottom: 10px; }
.ssf-robots-help-item code { display: block; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 11px; color: #1e293b; }
.ssf-robots-help-item small { color: #6b7280; font-size: 11px; }
</style>

<script>
jQuery(document).ready(function($) {
    var defaultContent = <?php echo wp_json_encode($default_content); ?>;
    var recommendedContent = <?php echo wp_json_encode($recommended); ?>;
    
    $('#ssf-robots-load-default').on('click', function() {
        if (confirm('<?php echo esc_js(__('Replace editor content with WordPress default?', 'smart-seo-fixer')); ?>')) {
            $('#ssf-robots-content').val(defaultContent);
        }
    });
    
    $('#ssf-robots-load-recommended').on('click', function() {
        if (confirm('<?php echo esc_js(__('Replace editor content with optimized recommended template?', 'smart-seo-fixer')); ?>')) {
            $('#ssf-robots-content').val(recommendedContent);
        }
    });
    
    $('#ssf-robots-save').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'smart-seo-fixer')); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_save_robots',
            nonce: ssfAdmin.nonce,
            content: $('#ssf-robots-content').val(),
            enabled: $('#ssf-robots-enabled').is(':checked') ? 1 : 0
        }, function(response) {
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Save robots.txt', 'smart-seo-fixer')); ?>');
            if (response.success) {
                alert(response.data.message);
            } else {
                alert(response.data?.message || 'Error saving');
            }
        });
    });
    
    // Toggle also saves immediately
    $('#ssf-robots-enabled').on('change', function() {
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_save_robots',
            nonce: ssfAdmin.nonce,
            content: $('#ssf-robots-content').val(),
            enabled: $(this).is(':checked') ? 1 : 0
        });
    });
});
</script>
