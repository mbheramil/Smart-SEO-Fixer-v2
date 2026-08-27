<?php
/**
 * Social Preview Cards View
 * Allows editing OG/Twitter meta per post with a live preview.
 */
if (!defined('ABSPATH')) exit;

$post_id = intval($_GET['post_id'] ?? 0);
$post = $post_id ? get_post($post_id) : null;
$data = [];
if ($post && class_exists('SSF_Social_Preview')) {
    $data = SSF_Social_Preview::get_data($post_id);
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-share-alt2"></span>
        <?php esc_html_e('Social Preview Cards', 'smart-seo-fixer'); ?>
    </h1>
    
    <!-- Post Selector -->
    <div class="ssf-social-selector">
        <label><?php esc_html_e('Select Post:', 'smart-seo-fixer'); ?></label>
        <input type="text" id="ssf-social-search" class="ssf-input" placeholder="<?php esc_attr_e('Type to search posts...', 'smart-seo-fixer'); ?>" style="min-width: 350px;">
        <div id="ssf-social-results" class="ssf-search-results" style="display: none;"></div>
    </div>
    
    <?php if ($post): ?>
    <div class="ssf-social-container" id="ssf-social-container">
        <input type="hidden" id="ssf-social-post-id" value="<?php echo esc_attr($post_id); ?>">
        
        <!-- Facebook/OG Preview -->
        <div class="ssf-social-card">
            <h3><span class="dashicons dashicons-facebook-alt" style="color: #1877F2;"></span> <?php esc_html_e('Facebook / Open Graph Preview', 'smart-seo-fixer'); ?></h3>
            <div class="ssf-og-preview">
                <div class="ssf-og-image" id="ssf-og-preview-img" style="background-image: url('<?php echo esc_url($data['og_image'] ?? ''); ?>');">
                    <?php if (empty($data['og_image'])): ?>
                    <span class="dashicons dashicons-format-image" style="font-size: 48px; color: #94a3b8;"></span>
                    <?php endif; ?>
                </div>
                <div class="ssf-og-text">
                    <div class="ssf-og-domain"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></div>
                    <div class="ssf-og-title" id="ssf-og-preview-title"><?php echo esc_html($data['og_title'] ?? ''); ?></div>
                    <div class="ssf-og-desc" id="ssf-og-preview-desc"><?php echo esc_html($data['og_description'] ?? ''); ?></div>
                </div>
            </div>
            
            <div class="ssf-social-fields">
                <div class="ssf-field">
                    <label><?php esc_html_e('OG Title', 'smart-seo-fixer'); ?></label>
                    <input type="text" id="ssf-og-title" class="ssf-input-full" value="<?php echo esc_attr($data['_og_title'] ?? ''); ?>" placeholder="<?php echo esc_attr($data['og_title'] ?? ''); ?>">
                </div>
                <div class="ssf-field">
                    <label><?php esc_html_e('OG Description', 'smart-seo-fixer'); ?></label>
                    <textarea id="ssf-og-description" class="ssf-input-full" rows="2" placeholder="<?php echo esc_attr($data['og_description'] ?? ''); ?>"><?php echo esc_textarea($data['_og_description'] ?? ''); ?></textarea>
                </div>
                <div class="ssf-field">
                    <label><?php esc_html_e('OG Image URL', 'smart-seo-fixer'); ?></label>
                    <input type="url" id="ssf-og-image" class="ssf-input-full" value="<?php echo esc_url($data['_og_image'] ?? ''); ?>" placeholder="<?php echo esc_url($data['og_image'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Twitter Preview -->
        <div class="ssf-social-card">
            <h3><span class="dashicons dashicons-twitter" style="color: #1DA1F2;"></span> <?php esc_html_e('Twitter / X Card Preview', 'smart-seo-fixer'); ?></h3>
            <div class="ssf-twitter-preview">
                <div class="ssf-twitter-image" id="ssf-tw-preview-img" style="background-image: url('<?php echo esc_url($data['twitter_image'] ?? ''); ?>');">
                    <?php if (empty($data['twitter_image'])): ?>
                    <span class="dashicons dashicons-format-image" style="font-size: 48px; color: #94a3b8;"></span>
                    <?php endif; ?>
                </div>
                <div class="ssf-twitter-text">
                    <div class="ssf-tw-title" id="ssf-tw-preview-title"><?php echo esc_html($data['twitter_title'] ?? ''); ?></div>
                    <div class="ssf-tw-desc" id="ssf-tw-preview-desc"><?php echo esc_html($data['twitter_description'] ?? ''); ?></div>
                    <div class="ssf-tw-domain"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></div>
                </div>
            </div>
            
            <div class="ssf-social-fields">
                <div class="ssf-field">
                    <label><?php esc_html_e('Twitter Title', 'smart-seo-fixer'); ?> <small style="color: #94a3b8;">(<?php esc_html_e('leave empty to use OG title', 'smart-seo-fixer'); ?>)</small></label>
                    <input type="text" id="ssf-twitter-title" class="ssf-input-full" value="<?php echo esc_attr($data['_twitter_title'] ?? ''); ?>" placeholder="<?php echo esc_attr($data['twitter_title'] ?? ''); ?>">
                </div>
                <div class="ssf-field">
                    <label><?php esc_html_e('Twitter Description', 'smart-seo-fixer'); ?></label>
                    <textarea id="ssf-twitter-description" class="ssf-input-full" rows="2" placeholder="<?php echo esc_attr($data['twitter_description'] ?? ''); ?>"><?php echo esc_textarea($data['_twitter_description'] ?? ''); ?></textarea>
                </div>
                <div class="ssf-field">
                    <label><?php esc_html_e('Twitter Image URL', 'smart-seo-fixer'); ?></label>
                    <input type="url" id="ssf-twitter-image" class="ssf-input-full" value="<?php echo esc_url($data['_twitter_image'] ?? ''); ?>" placeholder="<?php echo esc_url($data['twitter_image'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <button type="button" class="button button-primary button-hero" id="ssf-social-save" style="margin-top: 16px;">
            <?php esc_html_e('Save Social Data', 'smart-seo-fixer'); ?>
        </button>
        <span id="ssf-social-status" style="margin-left: 12px; color: #10b981; display: none;"></span>
    </div>
    <?php else: ?>
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px; text-align: center; color: #6b7280;">
        <?php esc_html_e('Select a post above to preview and edit its social sharing cards.', 'smart-seo-fixer'); ?>
    </div>
    <?php endif; ?>
</div>

<style>
.ssf-social-selector { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; position: relative; }
.ssf-social-selector label { font-weight: 600; font-size: 14px; white-space: nowrap; }
.ssf-search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 100; max-height: 300px; overflow-y: auto; }
.ssf-search-item { padding: 10px 16px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
.ssf-search-item:hover { background: #f3f4f6; }
.ssf-social-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 1100px) { .ssf-social-container { grid-template-columns: 1fr; } }
.ssf-social-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
.ssf-social-card h3 { margin: 0 0 16px; font-size: 15px; display: flex; align-items: center; gap: 6px; }

.ssf-og-preview { border: 1px solid #dadde1; border-radius: 4px; overflow: hidden; margin-bottom: 20px; }
.ssf-og-image { height: 200px; background: #f0f2f5; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; }
.ssf-og-text { padding: 10px 12px; }
.ssf-og-domain { font-size: 11px; color: #606770; text-transform: uppercase; }
.ssf-og-title { font-size: 15px; font-weight: 600; color: #1d2129; margin: 3px 0; line-height: 1.3; }
.ssf-og-desc { font-size: 13px; color: #606770; line-height: 1.3; }

.ssf-twitter-preview { border: 1px solid #e1e8ed; border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
.ssf-twitter-image { height: 200px; background: #e1e8ed; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; }
.ssf-twitter-text { padding: 10px 14px; }
.ssf-tw-title { font-size: 14px; font-weight: 700; color: #0f1419; line-height: 1.3; }
.ssf-tw-desc { font-size: 13px; color: #536471; margin: 2px 0; line-height: 1.3; }
.ssf-tw-domain { font-size: 12px; color: #536471; display: flex; align-items: center; gap: 4px; margin-top: 2px; }

.ssf-social-fields { display: flex; flex-direction: column; gap: 12px; }
.ssf-field label { display: block; font-weight: 600; font-size: 12px; color: #374151; margin-bottom: 4px; }
.ssf-input-full { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.ssf-input-full:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); }
</style>

<script>
jQuery(document).ready(function($) {
    // Live preview updates
    function updatePreviews() {
        var ogTitle = $('#ssf-og-title').val() || $('#ssf-og-title').attr('placeholder');
        var ogDesc = $('#ssf-og-description').val() || $('#ssf-og-description').attr('placeholder');
        var ogImg = $('#ssf-og-image').val() || $('#ssf-og-image').attr('placeholder');
        var twTitle = $('#ssf-twitter-title').val() || ogTitle;
        var twDesc = $('#ssf-twitter-description').val() || ogDesc;
        var twImg = $('#ssf-twitter-image').val() || ogImg;
        
        $('#ssf-og-preview-title').text(ogTitle);
        $('#ssf-og-preview-desc').text(ogDesc);
        if (ogImg) $('#ssf-og-preview-img').css('background-image', 'url(' + ogImg + ')').html('');
        
        $('#ssf-tw-preview-title').text(twTitle);
        $('#ssf-tw-preview-desc').text(twDesc);
        if (twImg) $('#ssf-tw-preview-img').css('background-image', 'url(' + twImg + ')').html('');
    }
    
    $('#ssf-og-title, #ssf-og-description, #ssf-og-image, #ssf-twitter-title, #ssf-twitter-description, #ssf-twitter-image').on('input', updatePreviews);
    
    // Save
    $('#ssf-social-save').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'smart-seo-fixer')); ?>');
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_save_social_data',
            nonce: ssfAdmin.nonce,
            post_id: $('#ssf-social-post-id').val(),
            og_title: $('#ssf-og-title').val(),
            og_description: $('#ssf-og-description').val(),
            og_image: $('#ssf-og-image').val(),
            twitter_title: $('#ssf-twitter-title').val(),
            twitter_description: $('#ssf-twitter-description').val(),
            twitter_image: $('#ssf-twitter-image').val()
        }, function(response) {
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Save Social Data', 'smart-seo-fixer')); ?>');
            if (response.success) {
                $('#ssf-social-status').text('Saved!').show().delay(2000).fadeOut();
            }
        });
    });
    
    // Post search
    var searchTimer;
    $('#ssf-social-search').on('input', function() {
        var q = $(this).val();
        clearTimeout(searchTimer);
        if (q.length < 2) { $('#ssf-social-results').hide(); return; }
        
        searchTimer = setTimeout(function() {
            $.post(ssfAdmin.ajax_url, { action: 'ssf_search_posts_for_schema', nonce: ssfAdmin.nonce, search: q }, function(response) {
                var posts = response.success ? (response.data.results || response.data || []) : [];
                if (!posts.length) { $('#ssf-social-results').hide(); return; }
                var html = '';
                $.each(posts, function(i, p) {
                    html += '<div class="ssf-search-item" data-id="' + (p.id || p.ID) + '">' + $('<span>').text(p.title || p.post_title).html() + ' <small style="color: #94a3b8;">(' + (p.post_type || '') + ')</small></div>';
                });
                $('#ssf-social-results').html(html).show();
            });
        }, 300);
    });
    
    $(document).on('click', '.ssf-search-item', function() {
        var id = $(this).data('id');
        window.location.href = '<?php echo admin_url('admin.php?page=smart-seo-fixer-social-preview&post_id='); ?>' + id;
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ssf-social-selector').length) {
            $('#ssf-social-results').hide();
        }
    });
});
</script>
