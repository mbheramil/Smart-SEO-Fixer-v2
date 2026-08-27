<?php
/**
 * Meta Box View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ssf-metabox">
    <!-- Score Display -->
    <div class="ssf-metabox-score">
        <div class="ssf-score-circle <?php echo $seo_score ? 'ssf-score-' . ($seo_score >= 80 ? 'good' : ($seo_score >= 60 ? 'ok' : 'poor')) : ''; ?>" id="seo-score-circle">
            <span class="ssf-score-value" id="seo-score-value"><?php echo $seo_score ? esc_html($seo_score) : '—'; ?></span>
            <span class="ssf-score-label"><?php esc_html_e('SEO Score', 'smart-seo-fixer'); ?></span>
        </div>
        <button type="button" class="button" id="analyze-now-btn">
            <span class="dashicons dashicons-search"></span>
            <?php esc_html_e('Analyze Now', 'smart-seo-fixer'); ?>
        </button>
    </div>
    
    <!-- Focus Keyword -->
    <div class="ssf-field">
        <label for="_ssf_focus_keyword">
            <?php esc_html_e('Focus Keyword', 'smart-seo-fixer'); ?>
            <span class="ssf-tooltip" title="<?php esc_attr_e('The main keyword you want this page to rank for.', 'smart-seo-fixer'); ?>">?</span>
        </label>
        <div class="ssf-field-row">
            <input type="text" 
                   name="_ssf_focus_keyword" 
                   id="_ssf_focus_keyword" 
                   value="<?php echo esc_attr($focus_keyword); ?>" 
                   class="large-text"
                   placeholder="<?php esc_attr_e('Enter focus keyword...', 'smart-seo-fixer'); ?>">
            <button type="button" class="button ssf-ai-btn" id="suggest-keywords-btn" title="<?php esc_attr_e('AI Suggest Keywords', 'smart-seo-fixer'); ?>">
                <span class="dashicons dashicons-lightbulb"></span>
            </button>
        </div>
    </div>
    
    <!-- SEO Title -->
    <div class="ssf-field">
        <label for="_ssf_seo_title">
            <?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?>
            <span class="ssf-char-count"><span id="title-char-count"><?php echo strlen($seo_title); ?></span>/60</span>
        </label>
        <div class="ssf-field-row">
            <input type="text" 
                   name="_ssf_seo_title" 
                   id="_ssf_seo_title" 
                   value="<?php echo esc_attr($seo_title); ?>" 
                   class="large-text"
                   placeholder="<?php echo esc_attr($post->post_title); ?>">
            <button type="button" class="button ssf-ai-btn" id="generate-title-btn" title="<?php esc_attr_e('AI Generate Title', 'smart-seo-fixer'); ?>">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
        </div>
        <div class="ssf-preview">
            <div class="ssf-preview-title" id="preview-title"><?php echo esc_html($seo_title ?: $post->post_title); ?></div>
            <div class="ssf-preview-url"><?php echo esc_url(get_permalink($post->ID)); ?></div>
            <div class="ssf-preview-desc" id="preview-desc"><?php echo esc_html($meta_description ?: wp_trim_words($post->post_content, 25)); ?></div>
        </div>
    </div>
    
    <!-- Meta Description -->
    <div class="ssf-field">
        <label for="_ssf_meta_description">
            <?php esc_html_e('Meta Description', 'smart-seo-fixer'); ?>
            <span class="ssf-char-count"><span id="desc-char-count"><?php echo strlen($meta_description); ?></span>/160</span>
        </label>
        <div class="ssf-field-row">
            <textarea name="_ssf_meta_description" 
                      id="_ssf_meta_description" 
                      rows="3" 
                      class="large-text"
                      placeholder="<?php esc_attr_e('Enter meta description...', 'smart-seo-fixer'); ?>"><?php echo esc_textarea($meta_description); ?></textarea>
            <button type="button" class="button ssf-ai-btn" id="generate-desc-btn" title="<?php esc_attr_e('AI Generate Description', 'smart-seo-fixer'); ?>">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
        </div>
    </div>
    
    <!-- Social Preview Tabs -->
    <div class="ssf-social-preview">
        <div class="ssf-preview-tabs">
            <button type="button" class="ssf-preview-tab active" data-tab="google"><?php esc_html_e('Google', 'smart-seo-fixer'); ?></button>
            <button type="button" class="ssf-preview-tab" data-tab="facebook"><?php esc_html_e('Facebook', 'smart-seo-fixer'); ?></button>
            <button type="button" class="ssf-preview-tab" data-tab="twitter"><?php esc_html_e('Twitter / X', 'smart-seo-fixer'); ?></button>
        </div>
        
        <?php
        $preview_title = $seo_title ?: $post->post_title;
        $preview_desc = $meta_description ?: wp_trim_words(strip_tags($post->post_content), 25, '...');
        $preview_url = get_permalink($post->ID);
        $preview_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $preview_image = '';
        $thumb_id = get_post_thumbnail_id($post->ID);
        if ($thumb_id) {
            $img_data = wp_get_attachment_image_src($thumb_id, 'large');
            if ($img_data) $preview_image = $img_data[0];
        }
        $site_name = get_bloginfo('name');
        ?>
        
        <!-- Google Preview -->
        <div class="ssf-preview-panel active" data-panel="google">
            <div class="ssf-google-preview">
                <div class="ssf-gp-breadcrumb">
                    <img src="https://www.google.com/s2/favicons?domain=<?php echo esc_attr($preview_domain); ?>&sz=32" alt="" style="width:18px;height:18px;border-radius:50%;vertical-align:middle;margin-right:8px;">
                    <span><?php echo esc_html($preview_domain); ?></span>
                    <span style="color:#70757a;"> › ...</span>
                </div>
                <div class="ssf-gp-title" id="gp-title"><?php echo esc_html(wp_trim_words($preview_title, 10, '...')); ?></div>
                <div class="ssf-gp-desc" id="gp-desc"><?php echo esc_html(substr($preview_desc, 0, 160) . (strlen($preview_desc) > 160 ? '...' : '')); ?></div>
            </div>
        </div>
        
        <!-- Facebook Preview -->
        <div class="ssf-preview-panel" data-panel="facebook">
            <div class="ssf-fb-preview">
                <?php if ($preview_image): ?>
                <div class="ssf-fb-image" style="background-image:url('<?php echo esc_url($preview_image); ?>');"></div>
                <?php else: ?>
                <div class="ssf-fb-image ssf-fb-no-image"><span class="dashicons dashicons-format-image"></span></div>
                <?php endif; ?>
                <div class="ssf-fb-content">
                    <div class="ssf-fb-domain"><?php echo esc_html(strtoupper($preview_domain)); ?></div>
                    <div class="ssf-fb-title" id="fb-title"><?php echo esc_html(substr($preview_title, 0, 65) . (strlen($preview_title) > 65 ? '...' : '')); ?></div>
                    <div class="ssf-fb-desc" id="fb-desc"><?php echo esc_html(substr($preview_desc, 0, 155) . (strlen($preview_desc) > 155 ? '...' : '')); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Twitter Preview -->
        <div class="ssf-preview-panel" data-panel="twitter">
            <div class="ssf-tw-preview">
                <?php if ($preview_image): ?>
                <div class="ssf-tw-image" style="background-image:url('<?php echo esc_url($preview_image); ?>');"></div>
                <?php else: ?>
                <div class="ssf-tw-image ssf-tw-no-image"><span class="dashicons dashicons-format-image"></span></div>
                <?php endif; ?>
                <div class="ssf-tw-content">
                    <div class="ssf-tw-title" id="tw-title"><?php echo esc_html(substr($preview_title, 0, 70) . (strlen($preview_title) > 70 ? '...' : '')); ?></div>
                    <div class="ssf-tw-desc" id="tw-desc"><?php echo esc_html(substr($preview_desc, 0, 125) . (strlen($preview_desc) > 125 ? '...' : '')); ?></div>
                    <div class="ssf-tw-domain"><?php echo esc_html($preview_domain); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- AI Content Tools -->
    <div class="ssf-ai-tools">
        <h4>
            <span class="dashicons dashicons-superhero"></span>
            <?php esc_html_e('AI Content Tools', 'smart-seo-fixer'); ?>
        </h4>
        
        <div class="ssf-ai-tools-grid">
            <!-- SEO Content -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('SEO Content', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-generate-all-seo">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Generate All SEO', 'smart-seo-fixer'); ?>
                </button>
                <div class="ssf-ai-options">
                    <label><input type="checkbox" name="ai_opt_title" checked> <?php esc_html_e('Title', 'smart-seo-fixer'); ?></label>
                    <label><input type="checkbox" name="ai_opt_desc" checked> <?php esc_html_e('Description', 'smart-seo-fixer'); ?></label>
                    <label><input type="checkbox" name="ai_opt_keywords"> <?php esc_html_e('Keywords', 'smart-seo-fixer'); ?></label>
                </div>
                <label class="ssf-overwrite-option">
                    <input type="checkbox" name="ai_overwrite" id="ai_overwrite">
                    <?php esc_html_e('Overwrite existing SEO fields', 'smart-seo-fixer'); ?>
                </label>
                <p class="description" style="margin-top:4px; font-size:11px;"><?php esc_html_e('Fills the SEO Title, Description & Keyword fields below — not the post body.', 'smart-seo-fixer'); ?></p>
            </div>
            
            <!-- Content Tools -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('Content Tools', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-generate-outline">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('Content Outline', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-improve-readability">
                    <span class="dashicons dashicons-editor-spellcheck"></span>
                    <?php esc_html_e('Improve Readability', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-suggest-schema">
                    <span class="dashicons dashicons-shortcode"></span>
                    <?php esc_html_e('Add Schema Markup', 'smart-seo-fixer'); ?>
                </button>
            </div>
            
            <!-- Internal Links -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('Internal Links', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-suggest-internal-links">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Insert Internal Links', 'smart-seo-fixer'); ?>
                </button>
                <p class="description"><?php esc_html_e('Auto-insert related post links into content', 'smart-seo-fixer'); ?></p>
            </div>
            
            <!-- External Links -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('External Links', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-suggest-external-links">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Insert Authority Links', 'smart-seo-fixer'); ?>
                </button>
                <p class="description"><?php esc_html_e('Auto-insert authoritative reference links into content', 'smart-seo-fixer'); ?></p>
            </div>
            
            <!-- Images -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('Images', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-fix-image-alt">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e('Generate Missing Alt Text', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-suggest-images">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <?php esc_html_e('Suggest Images', 'smart-seo-fixer'); ?>
                </button>
            </div>
            
            <!-- Local SEO -->
            <div class="ssf-ai-tool-group">
                <h5><?php esc_html_e('Local SEO', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-insert-map">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php esc_html_e('Insert Google Map', 'smart-seo-fixer'); ?>
                </button>
                <button type="button" class="button ssf-ai-tool-btn" id="ai-add-local-schema">
                    <span class="dashicons dashicons-building"></span>
                    <?php esc_html_e('Add Location Schema', 'smart-seo-fixer'); ?>
                </button>
            </div>
        </div>
        
        <!-- AI Results Panel -->
        <div class="ssf-ai-results" id="ssf-ai-results" style="display: none;">
            <div class="ssf-ai-results-header">
                <h5 id="ai-results-title"><?php esc_html_e('AI Suggestions', 'smart-seo-fixer'); ?></h5>
                <button type="button" class="button-link" id="close-ai-results">&times;</button>
            </div>
            <div class="ssf-ai-results-content" id="ai-results-content"></div>
            <div class="ssf-ai-results-actions" id="ai-results-actions"></div>
        </div>
    </div>
    
    <!-- Analysis Results -->
    <div class="ssf-analysis" id="ssf-analysis" style="display: none;">
        <h4><?php esc_html_e('Analysis Results', 'smart-seo-fixer'); ?></h4>
        
        <div class="ssf-issues" id="ssf-issues"></div>
        <div class="ssf-warnings" id="ssf-warnings"></div>
        <div class="ssf-passed" id="ssf-passed"></div>
    </div>
    
    <!-- Advanced Settings Toggle -->
    <div class="ssf-advanced-toggle">
        <button type="button" class="button-link" id="toggle-advanced">
            <span class="dashicons dashicons-arrow-down-alt2"></span>
            <?php esc_html_e('Advanced Settings', 'smart-seo-fixer'); ?>
        </button>
    </div>
    
    <!-- Advanced Settings -->
    <div class="ssf-advanced" id="ssf-advanced" style="display: none;">
        <!-- Canonical URL -->
        <div class="ssf-field">
            <label for="_ssf_canonical_url"><?php esc_html_e('Canonical URL', 'smart-seo-fixer'); ?></label>
            <input type="url" 
                   name="_ssf_canonical_url" 
                   id="_ssf_canonical_url" 
                   value="<?php echo esc_url($canonical_url); ?>" 
                   class="large-text"
                   placeholder="<?php echo esc_url(get_permalink($post->ID)); ?>">
            <p class="description"><?php esc_html_e('Leave empty to use default permalink.', 'smart-seo-fixer'); ?></p>
        </div>
        
        <!-- Robots -->
        <div class="ssf-field ssf-checkboxes">
            <?php
            $auto_noindex_flag = get_post_meta($post->ID, '_ssf_auto_noindex', true);
            $thin_evaluated    = get_post_meta($post->ID, '_ssf_thin_evaluated', true);
            $content_words     = (int) get_post_meta($post->ID, '_ssf_content_word_count', true);
            $threshold_setting = (int) Smart_SEO_Fixer::get_option('thin_content_threshold', 50);
            if ($auto_noindex_flag && $noindex):
            ?>
            <div style="margin: 0 0 12px; padding: 10px 14px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong style="color: #856404;">
                    <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Auto-noindexed: thin content', 'smart-seo-fixer'); ?>
                </strong>
                <p class="description" style="margin: 4px 0 0; color: #856404;">
                    <?php printf(
                        esc_html__('This post has only %1$d words of real content (threshold: %2$d). Smart SEO Fixer set it to noindex automatically so Google won\'t flag your site for thin content. Uncheck the box below to override this, or add more content — the plugin will lift the noindex automatically once it\'s above the threshold.', 'smart-seo-fixer'),
                        $content_words,
                        $threshold_setting
                    ); ?>
                </p>
            </div>
            <?php elseif ($thin_evaluated && $content_words > 0 && $content_words < $threshold_setting && !$noindex): ?>
            <div style="margin: 0 0 12px; padding: 10px 14px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 4px;">
                <strong style="color: #721c24;">
                    <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
                    <?php esc_html_e('Thin content warning', 'smart-seo-fixer'); ?>
                </strong>
                <p class="description" style="margin: 4px 0 0; color: #721c24;">
                    <?php printf(
                        esc_html__('Only %1$d words of real content (recommended minimum: %2$d). Google may treat this as thin content and hurt your site\'s overall ranking. Consider either expanding the content or checking "No Index" below.', 'smart-seo-fixer'),
                        $content_words,
                        $threshold_setting
                    ); ?>
                </p>
            </div>
            <?php endif; ?>
            <label>
                <input type="checkbox" name="_ssf_noindex" value="1" <?php checked($noindex, 1); ?>>
                <?php esc_html_e('No Index', 'smart-seo-fixer'); ?>
                <span class="description"><?php esc_html_e('(Tell search engines not to index this page)', 'smart-seo-fixer'); ?></span>
            </label>
            <label>
                <input type="checkbox" name="_ssf_nofollow" value="1" <?php checked($nofollow, 1); ?>>
                <?php esc_html_e('No Follow', 'smart-seo-fixer'); ?>
                <span class="description"><?php esc_html_e('(Tell search engines not to follow links on this page)', 'smart-seo-fixer'); ?></span>
            </label>
        </div>
    </div>
    
    <?php
    // Allow extensions (e.g., WooCommerce) to add fields
    do_action('ssf_metabox_after_fields', $post);
    ?>
</div>

<style>
.ssf-metabox {
    padding: 10px 0;
}

.ssf-metabox-score {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 20px;
}

.ssf-score-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #e0e0e0;
    border: 4px solid #ccc;
}

.ssf-score-circle.ssf-score-good {
    background: #e8f5e9;
    border-color: #4caf50;
}

.ssf-score-circle.ssf-score-ok {
    background: #fff8e1;
    border-color: #ffc107;
}

.ssf-score-circle.ssf-score-poor {
    background: #ffebee;
    border-color: #f44336;
}

.ssf-score-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.ssf-score-label {
    font-size: 10px;
    color: #666;
    text-transform: uppercase;
}

.ssf-field {
    margin-bottom: 15px;
}

.ssf-field label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    margin-bottom: 5px;
}

.ssf-field-row {
    display: flex;
    gap: 5px;
}

.ssf-field-row .large-text,
.ssf-field-row textarea {
    flex: 1;
}

.ssf-ai-btn {
    padding: 0 8px !important;
    min-width: 36px;
}

.ssf-ai-btn .dashicons {
    margin: 0;
}

.ssf-char-count {
    font-weight: normal;
    font-size: 12px;
    color: #666;
}

.ssf-preview {
    margin-top: 10px;
    padding: 15px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-family: Arial, sans-serif;
}

.ssf-preview-title {
    color: #1a0dab;
    font-size: 18px;
    line-height: 1.3;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ssf-preview-url {
    color: #006621;
    font-size: 14px;
    margin-bottom: 2px;
}

.ssf-preview-desc {
    color: #545454;
    font-size: 13px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.ssf-tooltip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    background: #666;
    color: #fff;
    border-radius: 50%;
    font-size: 11px;
    cursor: help;
    margin-left: 5px;
}

.ssf-advanced-toggle {
    margin: 15px 0;
    border-top: 1px solid #e0e0e0;
    padding-top: 15px;
}

.ssf-advanced-toggle .button-link {
    text-decoration: none;
    color: #0073aa;
}

.ssf-checkboxes label {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-weight: normal;
}

.ssf-checkboxes input {
    margin-right: 8px;
}

.ssf-checkboxes .description {
    color: #666;
    font-size: 12px;
    margin-left: 5px;
}

.ssf-analysis {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
}

.ssf-analysis h4 {
    margin: 0 0 10px 0;
}

.ssf-issues .ssf-item,
.ssf-warnings .ssf-item,
.ssf-passed .ssf-item {
    padding: 8px 10px;
    margin-bottom: 5px;
    border-radius: 4px;
    font-size: 13px;
}

.ssf-issues .ssf-item {
    background: #ffebee;
    border-left: 3px solid #f44336;
}

.ssf-warnings .ssf-item {
    background: #fff8e1;
    border-left: 3px solid #ffc107;
}

.ssf-passed .ssf-item {
    background: #e8f5e9;
    border-left: 3px solid #4caf50;
}

.ssf-item-fix {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

/* AI Tools Section */
.ssf-ai-tools {
    margin-top: 20px;
    padding: 15px;
    background: linear-gradient(135deg, #f0f7ff 0%, #e8f4f8 100%);
    border-radius: 8px;
    border: 1px solid #c3dafe;
}

.ssf-ai-tools h4 {
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1e40af;
}

.ssf-ai-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.ssf-ai-tool-group {
    background: white;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.ssf-ai-tool-group h5 {
    margin: 0 0 10px 0;
    font-size: 12px;
    text-transform: uppercase;
    color: #6b7280;
    letter-spacing: 0.5px;
}

.ssf-ai-tool-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-bottom: 8px;
    padding: 8px 12px !important;
    font-size: 12px !important;
}

.ssf-ai-tool-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.ssf-ai-tool-group .description {
    font-size: 11px;
    color: #9ca3af;
    margin: 5px 0 0;
}

.ssf-ai-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.ssf-ai-options label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #374151;
}

.ssf-overwrite-option {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #dc2626;
    margin-top: 5px;
}

/* AI Results Panel */
.ssf-ai-results {
    margin-top: 15px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.ssf-ai-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.ssf-ai-results-header h5 {
    margin: 0;
    color: #1f2937;
}

#close-ai-results {
    font-size: 20px;
    color: #9ca3af;
    text-decoration: none;
}

.ssf-ai-results-content {
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.ssf-ai-results-actions {
    padding: 10px 15px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Link suggestions */
.ssf-link-suggestion {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9fafb;
    border-radius: 4px;
    margin-bottom: 8px;
}

.ssf-link-suggestion:last-child {
    margin-bottom: 0;
}

.ssf-link-info {
    flex: 1;
}

.ssf-link-title {
    font-weight: 600;
    color: #1f2937;
    display: block;
}

.ssf-link-url {
    font-size: 11px;
    color: #6b7280;
}

.ssf-link-action {
    margin-left: 10px;
}

/* Spin animation for loading */
@keyframes ssf-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.dashicons.spin {
    animation: ssf-spin 1s linear infinite;
}

.ssf-ai-tool-btn:disabled {
    opacity: 0.7;
    cursor: wait;
}
</style>

<script>
jQuery(document).ready(function($) {
    var postId = <?php echo intval($post->ID); ?>;
    
    /**
     * Insert HTML content into the active editor (TinyMCE, textarea, or Gutenberg)
     * Returns true if successful
     */
    function ssfInsertContent(html, replace) {
        replace = replace || false;
        
        // Try TinyMCE (Classic Editor - Visual tab)
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                if (replace) {
                    editor.setContent(html);
                } else {
                    editor.execCommand('mceInsertContent', false, html);
                }
                editor.undoManager.add();
                return true;
            }
        }
        
        // Try textarea (Classic Editor - Text tab)
        var $textarea = $('#content');
        if ($textarea.length && $textarea.is(':visible')) {
            if (replace) {
                $textarea.val(html);
            } else {
                var ta = $textarea[0];
                var start = ta.selectionStart;
                var end = ta.selectionEnd;
                var val = ta.value;
                ta.value = val.substring(0, start) + html + val.substring(end);
                ta.selectionStart = ta.selectionEnd = start + html.length;
            }
            return true;
        }
        
        // Try Gutenberg (Block Editor)
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.blocks) {
            try {
                var blocks = wp.blocks.parse(html);
                if (replace) {
                    wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                } else {
                    wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                }
                return true;
            } catch(e) {
                console.error('Gutenberg insert failed:', e);
            }
        }
        
        return false;
    }
    
    /**
     * Flash a button green with a checkmark to indicate success
     */
    function ssfButtonSuccess($btn, text) {
        var original = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes-alt"></span> ' + (text || '<?php esc_html_e('Done!', 'smart-seo-fixer'); ?>')).css({'background':'#46b450','color':'#fff','border-color':'#46b450'});
        setTimeout(function() {
            $btn.html(original).css({'background':'','color':'','border-color':''});
        }, 2000);
    }

    /**
     * Find anchorText in the current editor content and wrap it inline as a hyperlink.
     * Only wraps the first occurrence that is NOT already inside an <a> tag.
     * Returns true on success, false if the phrase was not found.
     */
    function ssfInjectLinkInEditor(anchorText, url, rel) {
        if (!anchorText || !url) return false;

        function doReplace(content) {
            var idx = content.toLowerCase().indexOf(anchorText.toLowerCase());
            if (idx === -1) return null;

            // Reject if inside an existing <a> tag
            var before    = content.substring(0, idx);
            var lastOpen  = before.lastIndexOf('<a ');
            var lastClose = before.lastIndexOf('</a>');
            if (lastOpen !== -1 && (lastClose === -1 || lastClose < lastOpen)) {
                return null;
            }

            var exact  = content.substring(idx, idx + anchorText.length);
            var linked = '<a href="' + url + '"' + (rel ? ' rel="' + rel + '" target="_blank"' : '') + '>' + exact + '</a>';
            return content.substring(0, idx) + linked + content.substring(idx + anchorText.length);
        }

        // TinyMCE (Classic Editor — Visual tab)
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                var newContent = doReplace(editor.getContent());
                if (newContent) {
                    editor.setContent(newContent);
                    editor.undoManager.add();
                    return true;
                }
                return false;
            }
        }

        // Plain textarea (Classic Editor — Text tab)
        var $ta = $('#content');
        if ($ta.length && $ta.is(':visible')) {
            var newContent = doReplace($ta.val());
            if (newContent) { $ta.val(newContent); return true; }
            return false;
        }

        // Gutenberg block editor
        if (typeof wp !== 'undefined' && wp.data) {
            try {
                var current    = wp.data.select('core/editor').getEditedPostContent();
                var newContent = doReplace(current);
                if (newContent) {
                    wp.data.dispatch('core/editor').editPost({ content: newContent });
                    return true;
                }
            } catch (e) {
                console.error('Gutenberg inject failed:', e);
            }
        }

        return false;
    }
    
    // Character counters + live social previews
    $('#_ssf_seo_title').on('input', function() {
        var val = $(this).val() || '<?php echo esc_js($post->post_title); ?>';
        $('#title-char-count').text($(this).val().length);
        $('#preview-title').text(val);
        $('#gp-title').text(val.substring(0, 60) + (val.length > 60 ? '...' : ''));
        $('#fb-title').text(val.substring(0, 65) + (val.length > 65 ? '...' : ''));
        $('#tw-title').text(val.substring(0, 70) + (val.length > 70 ? '...' : ''));
    });
    
    $('#_ssf_meta_description').on('input', function() {
        var val = $(this).val() || '<?php echo esc_js(wp_trim_words(strip_tags($post->post_content), 25)); ?>';
        $('#desc-char-count').text($(this).val().length);
        $('#preview-desc').text(val);
        $('#gp-desc').text(val.substring(0, 160) + (val.length > 160 ? '...' : ''));
        $('#fb-desc').text(val.substring(0, 155) + (val.length > 155 ? '...' : ''));
        $('#tw-desc').text(val.substring(0, 125) + (val.length > 125 ? '...' : ''));
    });
    
    // Social preview tab switching
    $('.ssf-preview-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.ssf-preview-tab').removeClass('active');
        $(this).addClass('active');
        $('.ssf-preview-panel').removeClass('active');
        $('.ssf-preview-panel[data-panel="' + tab + '"]').addClass('active');
    });
    
    // Toggle advanced settings
    $('#toggle-advanced').on('click', function() {
        $('#ssf-advanced').slideToggle();
        $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });
    
    // Analyze now
    $('#analyze-now-btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_analyze_post',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data) {
                updateAnalysisDisplay(response.data);
            } else {
                showAiResults('<?php esc_html_e('Analysis Error', 'smart-seo-fixer'); ?>', 
                    '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Analysis failed.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
            console.error('Analyze AJAX Error:', xhr.responseText);
        });
    });
    
    // Generate title with AI
    $('#generate-title-btn').on('click', function() {
        var $btn = $(this);
        var focusKeyword = $('#_ssf_focus_keyword').val();
        
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_generate_title',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            focus_keyword: focusKeyword
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.title) {
                $('#_ssf_seo_title').val(response.data.title).trigger('input');
                showAiResults('<?php esc_html_e('Title Generated & Saved', 'smart-seo-fixer'); ?>', '<p>✅ ' + response.data.title + '</p>');
            } else {
                showAiResults('<?php esc_html_e('Error', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + ' - Check browser console for details</p>');
            console.error('AJAX Error:', xhr.responseText);
        });
    });
    
    // Generate description with AI
    $('#generate-desc-btn').on('click', function() {
        var $btn = $(this);
        var focusKeyword = $('#_ssf_focus_keyword').val();
        
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_generate_description',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            focus_keyword: focusKeyword
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.description) {
                $('#_ssf_meta_description').val(response.data.description).trigger('input');
                showAiResults('<?php esc_html_e('Description Generated & Saved', 'smart-seo-fixer'); ?>', '<p>✅ ' + response.data.description + '</p>');
            } else {
                showAiResults('<?php esc_html_e('Error', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + ' - Check browser console for details</p>');
            console.error('AJAX Error:', xhr.responseText);
        });
    });
    
    // Suggest keywords
    $('#suggest-keywords-btn').on('click', function() {
        var $btn = $(this);
        
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_keywords',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.primary) {
                $('#_ssf_focus_keyword').val(response.data.primary);
                
                var html = '<p>✅ <strong><?php esc_html_e('Primary:', 'smart-seo-fixer'); ?></strong> ' + response.data.primary + '</p>';
                if (response.data.secondary && response.data.secondary.length) {
                    html += '<p><strong><?php esc_html_e('Secondary:', 'smart-seo-fixer'); ?></strong> ' + response.data.secondary.join(', ') + '</p>';
                }
                if (response.data.long_tail && response.data.long_tail.length) {
                    html += '<p><strong><?php esc_html_e('Long-tail:', 'smart-seo-fixer'); ?></strong> ' + response.data.long_tail.join(', ') + '</p>';
                }
                showAiResults('<?php esc_html_e('Keyword Suggestions', 'smart-seo-fixer'); ?>', html);
            } else {
                showAiResults('<?php esc_html_e('Error', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not generate keywords.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
            console.error('Keywords AJAX Error:', xhr.responseText);
        });
    });
    
    function updateAnalysisDisplay(data) {
        // Update score
        var $circle = $('#seo-score-circle');
        var scoreClass = data.score >= 80 ? 'good' : (data.score >= 60 ? 'ok' : 'poor');
        
        $circle.removeClass('ssf-score-good ssf-score-ok ssf-score-poor')
               .addClass('ssf-score-' + scoreClass);
        $('#seo-score-value').text(data.score);
        
        // Show analysis section
        $('#ssf-analysis').show();
        
        // Render issues
        var $issues = $('#ssf-issues').empty();
        if (data.issues && data.issues.length) {
            data.issues.forEach(function(issue) {
                $issues.append(
                    '<div class="ssf-item">' +
                    '<strong>' + issue.message + '</strong>' +
                    (issue.fix ? '<div class="ssf-item-fix">💡 ' + issue.fix + '</div>' : '') +
                    '</div>'
                );
            });
        }
        
        // Render warnings
        var $warnings = $('#ssf-warnings').empty();
        if (data.warnings && data.warnings.length) {
            data.warnings.forEach(function(warning) {
                $warnings.append(
                    '<div class="ssf-item">' +
                    warning.message +
                    (warning.fix ? '<div class="ssf-item-fix">💡 ' + warning.fix + '</div>' : '') +
                    '</div>'
                );
            });
        }
        
        // Render passed
        var $passed = $('#ssf-passed').empty();
        if (data.passed && data.passed.length) {
            data.passed.forEach(function(item) {
                $passed.append('<div class="ssf-item">✓ ' + item.message + '</div>');
            });
        }
    }
    
    // ========== AI TOOLS ==========
    
    // Close AI results
    $('#close-ai-results').on('click', function() {
        $('#ssf-ai-results').slideUp();
    });
    
    // Generate All SEO — sequential processing to ensure keywords are available for title/desc
    $('#ai-generate-all-seo').on('click', function() {
        var $btn = $(this);
        var overwrite = $('#ai_overwrite').is(':checked');
        var generateTitle = $('input[name="ai_opt_title"]').is(':checked');
        var generateDesc = $('input[name="ai_opt_desc"]').is(':checked');
        var generateKeywords = $('input[name="ai_opt_keywords"]').is(':checked');
        
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        var generated = [];
        var errors = [];
        var tasks = [];
        
        // Build task queue — keywords first so they can feed into title/desc
        if (generateKeywords && (overwrite || !$('#_ssf_focus_keyword').val())) {
            tasks.push({ type: 'keywords', action: 'ssf_suggest_keywords' });
        }
        if (generateTitle && (overwrite || !$('#_ssf_seo_title').val())) {
            tasks.push({ type: 'title', action: 'ssf_generate_title' });
        }
        if (generateDesc && (overwrite || !$('#_ssf_meta_description').val())) {
            tasks.push({ type: 'description', action: 'ssf_generate_description' });
        }
        
        if (tasks.length === 0) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Nothing to Generate', 'smart-seo-fixer'); ?>', 
                '<p><?php esc_html_e('All fields already have content. Check "Overwrite existing content" to regenerate.', 'smart-seo-fixer'); ?></p>');
            return;
        }
        
        var taskIndex = 0;
        
        function processNextTask() {
            if (taskIndex >= tasks.length) {
                // All done
                $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                
                var html = '';
                if (generated.length > 0) {
                    html += '<p>✅ <?php esc_html_e('Generated & Saved:', 'smart-seo-fixer'); ?> ' + generated.join(', ') + '</p>';
                    html += '<p class="description"><?php esc_html_e('The SEO fields below have been updated and saved to the database.', 'smart-seo-fixer'); ?></p>';
                    
                    // Briefly highlight the updated fields so user can see what changed
                    if (generated.indexOf('title') !== -1) {
                        $('#_ssf_seo_title').css('background-color', '#e8f5e9').delay(2000).queue(function(n) { $(this).css('background-color', ''); n(); });
                    }
                    if (generated.indexOf('description') !== -1) {
                        $('#_ssf_meta_description').css('background-color', '#e8f5e9').delay(2000).queue(function(n) { $(this).css('background-color', ''); n(); });
                    }
                    if (generated.indexOf('keywords') !== -1) {
                        $('#_ssf_focus_keyword').css('background-color', '#e8f5e9').delay(2000).queue(function(n) { $(this).css('background-color', ''); n(); });
                    }
                }
                if (errors.length > 0) {
                    html += '<p style="color:red;">❌ <?php esc_html_e('Errors:', 'smart-seo-fixer'); ?><br>' + errors.join('<br>') + '</p>';
                }
                if (html === '') {
                    html = '<p><?php esc_html_e('No content was generated. Check your AWS Bedrock credentials in settings.', 'smart-seo-fixer'); ?></p>';
                }
                
                showAiResults(
                    generated.length > 0 ? '<?php esc_html_e('Generation Complete', 'smart-seo-fixer'); ?>' : '<?php esc_html_e('Generation Failed', 'smart-seo-fixer'); ?>', 
                    html,
                    '<button type="button" class="button button-primary" onclick="jQuery(\'#ssf-ai-results\').slideUp();"><?php esc_html_e('OK', 'smart-seo-fixer'); ?></button>'
                );
                return;
            }
            
            var task = tasks[taskIndex];
            var data = {
                action: task.action,
                nonce: ssfAdmin.nonce,
                post_id: postId,
                focus_keyword: $('#_ssf_focus_keyword').val()
            };
            
            $.post(ssfAdmin.ajax_url, data, function(response) {
                if (response.success) {
                    if (task.type === 'keywords' && response.data && response.data.primary) {
                        $('#_ssf_focus_keyword').val(response.data.primary);
                        generated.push('keywords');
                    } else if (task.type === 'title' && response.data && response.data.title) {
                        $('#_ssf_seo_title').val(response.data.title).trigger('input');
                        generated.push('title');
                    } else if (task.type === 'description' && response.data && response.data.description) {
                        $('#_ssf_meta_description').val(response.data.description).trigger('input');
                        generated.push('description');
                    } else {
                        errors.push(task.type + ': ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Empty response', 'smart-seo-fixer'); ?>'));
                    }
                } else {
                    errors.push(task.type + ': ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Failed', 'smart-seo-fixer'); ?>'));
                }
                
                taskIndex++;
                processNextTask();
            }).fail(function(xhr, status, error) {
                errors.push(task.type + ': <?php esc_html_e('Request failed', 'smart-seo-fixer'); ?> (' + error + ')');
                console.error(task.type + ' generation failed:', xhr.responseText);
                taskIndex++;
                processNextTask();
            });
        }
        
        processNextTask();
    });
    
    // Insert Internal Links — wrap existing phrase(s) in the content with <a> tags inline
    $('#ai-suggest-internal-links').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        // Grab live editor content so the backend can find anchor phrases that
        // haven't been saved to the database yet.
        var liveContent = '';
        try {
            if (typeof tinymce !== 'undefined') {
                var ed = tinymce.get('content');
                if (ed && !ed.isHidden()) { liveContent = ed.getContent(); }
            }
            if (!liveContent) {
                var $ta = $('#content');
                if ($ta.length && $ta.is(':visible')) { liveContent = $ta.val() || ''; }
            }
            if (!liveContent && typeof wp !== 'undefined' && wp.data) {
                var gb = wp.data.select('core/editor');
                if (gb) { liveContent = gb.getEditedPostContent() || ''; }
            }
        } catch (e) { liveContent = ''; }

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_internal_links',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            content: liveContent
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.links && response.data.links.length) {
                var inserted = 0;
                response.data.links.forEach(function(link) {
                    if (link.anchor_text && ssfInjectLinkInEditor(link.anchor_text, link.url)) {
                        inserted++;
                    }
                });
                
                if (inserted > 0) {
                    ssfButtonSuccess($btn, inserted + ' <?php esc_html_e('link(s) added!', 'smart-seo-fixer'); ?>');
                } else {
                    alert('<?php esc_html_e('Anchor phrases could not be located in the editor. The post may need to be saved and reopened first.', 'smart-seo-fixer'); ?>');
                }
            } else {
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('No related posts found for internal linking.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            alert('<?php esc_html_e('Request failed:', 'smart-seo-fixer'); ?> ' + error);
        });
    });
    
    // Insert External Links — wrap existing phrase(s) in the content with outbound <a> tags inline
    $('#ai-suggest-external-links').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_external_links',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.suggestions && response.data.suggestions.length) {
                var inserted = 0;
                response.data.suggestions.forEach(function(s) {
                    if (s.anchor_text && s.url && s.url.indexOf('http') === 0) {
                        if (ssfInjectLinkInEditor(s.anchor_text, s.url, 'noopener noreferrer')) {
                            inserted++;
                        }
                    }
                });
                
                if (inserted > 0) {
                    ssfButtonSuccess($btn, inserted + ' <?php esc_html_e('link(s) added!', 'smart-seo-fixer'); ?>');
                } else {
                    alert('<?php esc_html_e('Anchor phrases could not be located in the editor. The post may need to be saved and reopened first.', 'smart-seo-fixer'); ?>');
                }
            } else {
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not generate external links.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            alert('<?php esc_html_e('Request failed:', 'smart-seo-fixer'); ?> ' + error);
        });
    });
    
    // Fix Image Alt Text
    $('#ai-fix-image-alt').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_fix_image_alt_texts',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success) {
                var html = '<p>✅ ' + response.data.message + '</p>';
                if (response.data.fixed && response.data.fixed.length > 0) {
                    html += '<p style="color:#16a34a; font-weight:600;"><?php esc_html_e('Alt text has been saved directly to these images:', 'smart-seo-fixer'); ?></p>';
                    response.data.fixed.forEach(function(img) {
                        html += '<div style="padding:6px 10px; margin-bottom:4px; background:#f0fdf4; border-radius:4px; border-left:3px solid #16a34a;">';
                        html += '<strong>' + img.filename + '</strong><br>';
                        html += '<span style="color:#4b5563;">alt="' + img.alt + '"</span>';
                        html += '</div>';
                    });
                    html += '<p class="description"><?php esc_html_e('No further action needed — alt text is already applied.', 'smart-seo-fixer'); ?></p>';
                }
                showAiResults('<?php esc_html_e('Alt Text — Applied ✓', 'smart-seo-fixer'); ?>', html);
            } else {
                showAiResults('<?php esc_html_e('Image Alt Text', 'smart-seo-fixer'); ?>', 
                    '<p>' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('No images need alt text.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Insert Google Map — directly into content
    $('#ai-insert-map').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_map_embed',
            nonce: ssfAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.embed) {
                var embedCode = response.data.embed;
                
                if (ssfInsertContent('\n' + embedCode + '\n')) {
                    ssfButtonSuccess($btn, '<?php esc_html_e('Map Inserted!', 'smart-seo-fixer'); ?>');
                    showAiResults('<?php esc_html_e('Google Map', 'smart-seo-fixer'); ?>', 
                        '<p>✅ <?php esc_html_e('Map embed code has been inserted into your content.', 'smart-seo-fixer'); ?></p>');
                } else {
                    // Fallback: show copy option
                    showAiResults('<?php esc_html_e('Google Map', 'smart-seo-fixer'); ?>',
                        '<p><?php esc_html_e('Could not auto-insert. Copy the code below:', 'smart-seo-fixer'); ?></p>' +
                        '<textarea class="large-text" rows="4" readonly onclick="this.select();">' + embedCode + '</textarea>',
                        '<button type="button" class="button button-primary" onclick="navigator.clipboard.writeText(jQuery(\'#ai-results-content textarea\').val());alert(\'<?php esc_html_e('Copied!', 'smart-seo-fixer'); ?>\');"><?php esc_html_e('Copy', 'smart-seo-fixer'); ?></button>');
                }
            } else {
                showAiResults('<?php esc_html_e('Google Map', 'smart-seo-fixer'); ?>', 
                    '<p><?php esc_html_e('Please configure your business address in Local SEO settings first.', 'smart-seo-fixer'); ?> <a href="<?php echo admin_url('admin.php?page=smart-seo-fixer-local'); ?>"><?php esc_html_e('Go to Local SEO', 'smart-seo-fixer'); ?></a></p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Suggest Images — with insert placeholder and media library trigger
    $('#ai-suggest-images').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_images',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.suggestions) {
                var html = '';
                response.data.suggestions.forEach(function(suggestion, i) {
                    html += '<div style="display:flex; align-items:flex-start; justify-content:space-between; padding:10px; margin-bottom:8px; background:#f9fafb; border-radius:6px;">';
                    html += '<div style="flex:1;">';
                    html += '<strong>' + suggestion.type + '</strong>';
                    html += '<br><span style="color:#6b7280;">' + suggestion.description + '</span>';
                    if (suggestion.search_term) {
                        html += '<br><a href="https://unsplash.com/s/photos/' + encodeURIComponent(suggestion.search_term) + '" target="_blank" style="font-size:12px;"><?php esc_html_e('Find on Unsplash →', 'smart-seo-fixer'); ?></a>';
                        html += ' · <a href="https://www.pexels.com/search/' + encodeURIComponent(suggestion.search_term) + '/" target="_blank" style="font-size:12px;"><?php esc_html_e('Pexels →', 'smart-seo-fixer'); ?></a>';
                    }
                    html += '</div>';
                    html += '<button type="button" class="button button-small ssf-insert-img-placeholder" data-alt="' + (suggestion.search_term || suggestion.type).replace(/"/g, '&quot;') + '" data-desc="' + suggestion.description.replace(/"/g, '&quot;') + '" style="margin-left:8px; white-space:nowrap;"><?php esc_html_e('Insert Placeholder', 'smart-seo-fixer'); ?></button>';
                    html += '</div>';
                });
                html += '<p class="description"><?php esc_html_e('Tip: Click "Insert Placeholder" to add an image comment in your content, then replace it with a real image using the media library.', 'smart-seo-fixer'); ?></p>';
                
                showAiResults('<?php esc_html_e('Image Suggestions', 'smart-seo-fixer'); ?>', html,
                    '<button type="button" class="button button-primary" id="ssf-open-media-library"><?php esc_html_e('Open Media Library', 'smart-seo-fixer'); ?></button>');
            } else {
                showAiResults('<?php esc_html_e('Image Suggestions', 'smart-seo-fixer'); ?>', 
                    '<p>' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not generate suggestions.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Insert image placeholder into content
    $(document).on('click', '.ssf-insert-img-placeholder', function() {
        var $btn = $(this);
        var alt = $btn.data('alt');
        var desc = $btn.data('desc');
        var placeholder = '\n<figure>\n  <img src="" alt="' + alt + '" />\n  <figcaption>' + desc + '</figcaption>\n</figure>\n';
        
        if (ssfInsertContent(placeholder)) {
            ssfButtonSuccess($btn, '<?php esc_html_e('Added!', 'smart-seo-fixer'); ?>');
        } else {
            alert('<?php esc_html_e('Could not detect editor.', 'smart-seo-fixer'); ?>');
        }
    });
    
    // Open WordPress media library
    $(document).on('click', '#ssf-open-media-library', function() {
        if (typeof wp !== 'undefined' && wp.media) {
            var frame = wp.media({
                title: '<?php esc_html_e('Select Image', 'smart-seo-fixer'); ?>',
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var imgHtml = '\n<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '" />\n';
                ssfInsertContent(imgHtml);
            });
            frame.open();
        }
    });
    
    // Generate Content Outline
    $('#ai-generate-outline').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_generate_outline',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data) {
                var data = response.data;
                
                // Strip "H1:", "H2:", "H3:" prefixes the AI sometimes adds
                function cleanHeading(text) {
                    return text.replace(/^H[1-6]:\s*/i, '').trim();
                }
                
                // Build the editor-ready HTML structure (clean headings only, no descriptions)
                var editorHtml = '';
                if (data.sections && data.sections.length) {
                    data.sections.forEach(function(section) {
                        editorHtml += '<h2>' + cleanHeading(section.heading) + '</h2>\n';
                        editorHtml += '<p>&nbsp;</p>\n';
                        if (section.subsections && section.subsections.length) {
                            section.subsections.forEach(function(sub) {
                                editorHtml += '<h3>' + cleanHeading(sub) + '</h3>\n';
                                editorHtml += '<p>&nbsp;</p>\n';
                            });
                        }
                    });
                }
                window.ssfOutlineHtml = editorHtml;
                
                // Build preview display (with cleaned headings)
                var cleanTitle = data.title ? cleanHeading(data.title) : '';
                var html = '';
                if (cleanTitle) {
                    html += '<p><strong style="font-size:15px;">' + cleanTitle + '</strong></p>';
                }
                if (data.sections && data.sections.length) {
                    html += '<div style="margin-top:10px;">';
                    data.sections.forEach(function(section) {
                        var h2 = cleanHeading(section.heading);
                        html += '<div style="margin-bottom:10px; padding:8px; background:#f9fafb; border-radius:4px;">';
                        html += '<strong style="color:#1e40af;">' + h2 + '</strong>';
                        if (section.subsections && section.subsections.length) {
                            html += '<ul style="margin:5px 0 0 15px; list-style:disc;">';
                            section.subsections.forEach(function(sub) {
                                html += '<li style="color:#4b5563;">' + cleanHeading(sub) + '</li>';
                            });
                            html += '</ul>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                }
                if (data.suggested_word_count) {
                    html += '<p class="description"><?php esc_html_e('Suggested word count:', 'smart-seo-fixer'); ?> ~' + data.suggested_word_count + '</p>';
                }
                
                // Update post title if provided
                var titleAction = '';
                if (cleanTitle) {
                    titleAction = '<button type="button" class="button" id="ssf-apply-outline-title" data-title="' + cleanTitle.replace(/"/g, '&quot;') + '"><?php esc_html_e('Set as Post Title', 'smart-seo-fixer'); ?></button> ';
                }
                
                showAiResults('<?php esc_html_e('Content Outline', 'smart-seo-fixer'); ?>', html,
                    titleAction + 
                    '<button type="button" class="button button-primary" id="ssf-insert-outline"><?php esc_html_e('Insert into Editor', 'smart-seo-fixer'); ?></button> ' +
                    '<button type="button" class="button" id="ssf-replace-outline"><?php esc_html_e('Replace Content', 'smart-seo-fixer'); ?></button>');
            } else {
                showAiResults('<?php esc_html_e('Content Outline', 'smart-seo-fixer'); ?>', 
                    '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not generate outline.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Improve Readability
    $('#ai-improve-readability').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_improve_readability',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.improved_content) {
                // Store improved content for potential apply
                window.ssfImprovedContent = response.data.improved_content;
                
                var html = '<p><strong><?php esc_html_e('AI-improved version of your content:', 'smart-seo-fixer'); ?></strong></p>';
                html += '<div id="ssf-improved-content" style="max-height:250px; overflow-y:auto; padding:12px; background:#f9fafb; border-radius:6px; font-size:13px; line-height:1.6; white-space:pre-wrap;">' + response.data.improved_content + '</div>';
                html += '<p class="description"><?php esc_html_e('Review the content above, then apply it to the editor or copy it.', 'smart-seo-fixer'); ?></p>';
                
                var actions = '<button type="button" class="button button-primary" id="ssf-apply-to-editor"><?php esc_html_e('Apply to Editor', 'smart-seo-fixer'); ?></button> ';
                actions += '<button type="button" class="button" onclick="navigator.clipboard.writeText(window.ssfImprovedContent).then(function(){alert(\'<?php esc_html_e('Copied to clipboard!', 'smart-seo-fixer'); ?>\');});"><?php esc_html_e('Copy to Clipboard', 'smart-seo-fixer'); ?></button>';
                
                showAiResults('<?php esc_html_e('Readability Improvements', 'smart-seo-fixer'); ?>', html, actions);
            } else {
                showAiResults('<?php esc_html_e('Readability', 'smart-seo-fixer'); ?>', 
                    '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not improve content.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Suggest Schema — generate and save to post
    $('#ai-suggest-schema').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            schema_action: 'generate'
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data && response.data.no_schema) {
                // AI says no additional schema needed
                showAiResults('<?php esc_html_e('Schema Markup', 'smart-seo-fixer'); ?>', 
                    '<p>✅ ' + response.data.message + '</p>');
                return;
            }
            
            if (response.success && response.data && response.data.schema) {
                window.ssfPendingSchema = response.data.schema;
                
                // Try to pretty-print for display
                var display = response.data.schema;
                try { display = JSON.stringify(JSON.parse(display), null, 2); } catch(e) {}
                
                var html = '<p><strong><?php esc_html_e('AI-generated schema for this post:', 'smart-seo-fixer'); ?></strong></p>';
                html += '<textarea id="ssf-schema-preview" class="large-text" rows="8" style="font-family:monospace; font-size:12px;">' + display + '</textarea>';
                html += '<p class="description"><?php esc_html_e('This schema will be automatically output on the frontend with proper script tags. Article, WebPage, Breadcrumb, FAQ, and HowTo schemas are already handled — this is for additional types (Product, Event, Recipe, etc.).', 'smart-seo-fixer'); ?></p>';
                
                if (response.data.has_existing) {
                    html += '<p style="color:#b45309;">⚠️ <?php esc_html_e('This post already has a custom schema. Saving will replace it.', 'smart-seo-fixer'); ?></p>';
                }
                
                showAiResults('<?php esc_html_e('Schema Markup', 'smart-seo-fixer'); ?>', html,
                    '<button type="button" class="button button-primary" id="ssf-save-schema"><?php esc_html_e('Save Schema to Post', 'smart-seo-fixer'); ?></button> ' +
                    (response.data.has_existing ? '<button type="button" class="button" id="ssf-remove-schema" style="color:#dc2626;"><?php esc_html_e('Remove Existing Schema', 'smart-seo-fixer'); ?></button>' : ''));
            } else {
                showAiResults('<?php esc_html_e('Schema', 'smart-seo-fixer'); ?>', 
                    '<p style="color:red;">❌ ' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e('Could not suggest schema.', 'smart-seo-fixer'); ?>') + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Save schema to post
    $(document).on('click', '#ssf-save-schema', function() {
        var $btn = $(this);
        // Use the textarea content (user may have edited it)
        var schema = $('#ssf-schema-preview').val() || window.ssfPendingSchema;
        if (!schema) return;
        
        $btn.prop('disabled', true).text('<?php esc_html_e('Saving...', 'smart-seo-fixer'); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            schema_action: 'save',
            schema_json: schema
        }, function(response) {
            if (response.success) {
                ssfButtonSuccess($btn, '<?php esc_html_e('Saved!', 'smart-seo-fixer'); ?>');
                showAiResults('<?php esc_html_e('Schema Saved ✓', 'smart-seo-fixer'); ?>', 
                    '<p>✅ <?php esc_html_e('Schema has been saved to this post. It will automatically appear in the page source code with proper <script type="application/ld+json"> tags.', 'smart-seo-fixer'); ?></p>' +
                    '<p class="description"><?php esc_html_e('No further action needed — visitors and search engines will see it automatically.', 'smart-seo-fixer'); ?></p>');
            } else {
                $btn.prop('disabled', false).text('<?php esc_html_e('Save Schema to Post', 'smart-seo-fixer'); ?>');
                alert(response.data && response.data.message ? response.data.message : '<?php esc_html_e('Save failed.', 'smart-seo-fixer'); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('<?php esc_html_e('Save Schema to Post', 'smart-seo-fixer'); ?>');
            alert('<?php esc_html_e('Request failed.', 'smart-seo-fixer'); ?>');
        });
    });
    
    // Remove existing schema from post
    $(document).on('click', '#ssf-remove-schema', function() {
        if (!confirm('<?php esc_html_e('Remove the custom schema from this post?', 'smart-seo-fixer'); ?>')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_suggest_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId,
            schema_action: 'remove'
        }, function(response) {
            if (response.success) {
                showAiResults('<?php esc_html_e('Schema Removed', 'smart-seo-fixer'); ?>', 
                    '<p>✅ ' + response.data.message + '</p>');
            }
        });
    });
    
    // Add Location Schema toggle
    $('#ai-add-local-schema').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_toggle_local_schema',
            nonce: ssfAdmin.nonce,
            post_id: postId
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            
            if (response.success && response.data) {
                var html = '<p>' + (response.data.enabled ? '✅' : '🔴') + ' ' + response.data.message + '</p>';
                if (response.data.enabled && response.data.business_name) {
                    html += '<p><strong><?php esc_html_e('Business:', 'smart-seo-fixer'); ?></strong> ' + response.data.business_name + ' (' + response.data.business_type + ')</p>';
                    html += '<p class="description"><?php esc_html_e('The LocalBusiness schema will be included when this page loads on the frontend.', 'smart-seo-fixer'); ?></p>';
                }
                showAiResults('<?php esc_html_e('Location Schema', 'smart-seo-fixer'); ?>', html);
                
                // Update button text to reflect current state
                if (response.data.enabled) {
                    $btn.find('.dashicons').removeClass('dashicons-building').addClass('dashicons-yes');
                    $btn.css('background-color', '#e8f5e9');
                } else {
                    $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-building');
                    $btn.css('background-color', '');
                }
            } else {
                var errMsg = (response.data && response.data.message) ? response.data.message : '<?php esc_html_e('Failed to toggle schema.', 'smart-seo-fixer'); ?>';
                showAiResults('<?php esc_html_e('Location Schema', 'smart-seo-fixer'); ?>', 
                    '<p style="color:red;">❌ ' + errMsg + '</p>' +
                    '<p><a href="<?php echo admin_url('admin.php?page=smart-seo-fixer-local'); ?>"><?php esc_html_e('Go to Local SEO Settings', 'smart-seo-fixer'); ?></a></p>');
            }
        }).fail(function(xhr, status, error) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            showAiResults('<?php esc_html_e('Request Failed', 'smart-seo-fixer'); ?>', '<p style="color:red;">❌ ' + error + '</p>');
        });
    });
    
    // Initialize local schema button state
    <?php 
    $local_schema_enabled = get_post_meta($post->ID, '_ssf_include_local_schema', true);
    if ($local_schema_enabled): ?>
    (function() {
        var $btn = $('#ai-add-local-schema');
        $btn.find('.dashicons').removeClass('dashicons-building').addClass('dashicons-yes');
        $btn.css('background-color', '#e8f5e9');
    })();
    <?php endif; ?>
    
    // Helper function to show AI results
    function showAiResults(title, content, actions) {
        $('#ai-results-title').text(title);
        $('#ai-results-content').html(content);
        $('#ai-results-actions').html(actions || '');
        $('#ssf-ai-results').slideDown();
    }
    
    // Insert outline into editor (append)
    $(document).on('click', '#ssf-insert-outline', function() {
        var $btn = $(this);
        if (window.ssfOutlineHtml && ssfInsertContent(window.ssfOutlineHtml)) {
            ssfButtonSuccess($btn, '<?php esc_html_e('Inserted!', 'smart-seo-fixer'); ?>');
        } else {
            alert('<?php esc_html_e('Could not detect editor.', 'smart-seo-fixer'); ?>');
        }
    });
    
    // Replace content with outline
    $(document).on('click', '#ssf-replace-outline', function() {
        if (!confirm('<?php esc_html_e('This will replace your current post content with the outline structure. Continue?', 'smart-seo-fixer'); ?>')) return;
        var $btn = $(this);
        if (window.ssfOutlineHtml && ssfInsertContent(window.ssfOutlineHtml, true)) {
            ssfButtonSuccess($btn, '<?php esc_html_e('Replaced!', 'smart-seo-fixer'); ?>');
        } else {
            alert('<?php esc_html_e('Could not detect editor.', 'smart-seo-fixer'); ?>');
        }
    });
    
    // Set outline title as post title
    $(document).on('click', '#ssf-apply-outline-title', function() {
        var $btn = $(this);
        var title = $btn.data('title');
        // Classic editor
        if ($('#title').length) {
            $('#title').val(title);
        }
        // Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try { wp.data.dispatch('core/editor').editPost({title: title}); } catch(e) {}
        }
        ssfButtonSuccess($btn, '<?php esc_html_e('Title Set!', 'smart-seo-fixer'); ?>');
    });
    
    // Apply improved content to the post editor
    $(document).on('click', '#ssf-apply-to-editor', function() {
        if (!window.ssfImprovedContent) {
            alert('<?php esc_html_e('No improved content available.', 'smart-seo-fixer'); ?>');
            return;
        }
        
        if (!confirm('<?php esc_html_e('This will replace your current post content with the AI-improved version. Continue?', 'smart-seo-fixer'); ?>')) {
            return;
        }
        
        var content = window.ssfImprovedContent;
        
        // Convert plain text to paragraphs for the editor
        var paragraphs = content.split(/\n\n+/).filter(function(p) { return p.trim().length > 0; });
        var htmlContent = paragraphs.map(function(p) { return '<p>' + p.replace(/\n/g, '<br>') + '</p>'; }).join('\n');
        
        var applied = false;
        
        // Try TinyMCE (Classic Editor / Visual tab)
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                editor.setContent(htmlContent);
                editor.undoManager.add();
                applied = true;
            }
        }
        
        // Try plain textarea (Text tab in Classic Editor)
        if (!applied) {
            var $textarea = $('#content');
            if ($textarea.length) {
                $textarea.val(content);
                applied = true;
            }
        }
        
        // Try Gutenberg (Block Editor)
        if (!applied && typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                var blocks = wp.blocks.parse(htmlContent);
                wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                applied = true;
            } catch(e) {
                console.error('Gutenberg apply failed:', e);
            }
        }
        
        if (applied) {
            $(this).text('<?php esc_html_e('Applied!', 'smart-seo-fixer'); ?>').prop('disabled', true).css('background', '#46b450').css('color', '#fff');
            window.ssfImprovedContent = null;
        } else {
            alert('<?php esc_html_e('Could not detect editor type. Please copy and paste manually.', 'smart-seo-fixer'); ?>');
        }
    });
});

function copyMapEmbed() {
    var textarea = document.querySelector('#ai-results-content textarea');
    textarea.select();
    document.execCommand('copy');
    alert('<?php esc_html_e('Map embed code copied!', 'smart-seo-fixer'); ?>');
}
</script>

