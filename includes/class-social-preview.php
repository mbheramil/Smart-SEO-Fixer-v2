<?php
/**
 * Social Preview Cards
 * 
 * Manages custom OG/Twitter meta overrides per post and
 * provides a live preview UI in the post editor.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Social_Preview {
    
    /**
     * Initialize hooks
     */
    public function __construct() {
        // Save social fields from meta box
        add_action('ssf_metabox_save', [__CLASS__, 'save_meta']);
        
        // Override social tags in the frontend output
        add_filter('ssf_og_tags', [__CLASS__, 'filter_og_tags'], 10, 2);
    }
    
    /**
     * Get social data for a post
     */
    public static function get_data($post_id) {
        $post = get_post($post_id);
        if (!$post) return [];
        
        // Custom overrides
        $og_title       = get_post_meta($post_id, '_ssf_og_title', true);
        $og_description = get_post_meta($post_id, '_ssf_og_description', true);
        $og_image       = get_post_meta($post_id, '_ssf_og_image', true);
        $twitter_title  = get_post_meta($post_id, '_ssf_twitter_title', true);
        $twitter_desc   = get_post_meta($post_id, '_ssf_twitter_description', true);
        $twitter_image  = get_post_meta($post_id, '_ssf_twitter_image', true);
        
        // Fallbacks
        $seo_title  = get_post_meta($post_id, '_ssf_seo_title', true) ?: $post->post_title;
        $seo_desc   = get_post_meta($post_id, '_ssf_meta_description', true);
        $permalink  = get_permalink($post_id);
        $site_name  = get_bloginfo('name');
        
        // Featured image
        $featured_image = '';
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $img = wp_get_attachment_image_src($thumb_id, 'large');
            if ($img) $featured_image = $img[0];
        }
        
        return [
            'og_title'            => $og_title ?: $seo_title,
            'og_description'      => $og_description ?: $seo_desc,
            'og_image'            => $og_image ?: $featured_image,
            'twitter_title'       => $twitter_title ?: ($og_title ?: $seo_title),
            'twitter_description' => $twitter_desc ?: ($og_description ?: $seo_desc),
            'twitter_image'       => $twitter_image ?: ($og_image ?: $featured_image),
            'url'                 => $permalink,
            'site_name'           => $site_name,
            'has_overrides'       => !empty($og_title) || !empty($og_description) || !empty($og_image) || !empty($twitter_title) || !empty($twitter_desc) || !empty($twitter_image),
            // Raw overrides for the form
            '_og_title'            => $og_title,
            '_og_description'      => $og_description,
            '_og_image'            => $og_image,
            '_twitter_title'       => $twitter_title,
            '_twitter_description' => $twitter_desc,
            '_twitter_image'       => $twitter_image,
        ];
    }
    
    /**
     * Save social meta fields
     */
    public static function save_meta($post_id) {
        $fields = [
            '_ssf_og_title'            => 'sanitize_text_field',
            '_ssf_og_description'      => 'sanitize_textarea_field',
            '_ssf_og_image'            => 'esc_url_raw',
            '_ssf_twitter_title'       => 'sanitize_text_field',
            '_ssf_twitter_description' => 'sanitize_textarea_field',
            '_ssf_twitter_image'       => 'esc_url_raw',
        ];
        
        foreach ($fields as $key => $sanitize) {
            if (isset($_POST[$key])) {
                $value = call_user_func($sanitize, $_POST[$key]);
                if (!empty($value)) {
                    update_post_meta($post_id, $key, $value);
                } else {
                    delete_post_meta($post_id, $key);
                }
            }
        }
    }
    
    /**
     * Filter OG tags with custom overrides
     */
    public static function filter_og_tags($tags, $post_id) {
        $og_title = get_post_meta($post_id, '_ssf_og_title', true);
        $og_desc  = get_post_meta($post_id, '_ssf_og_description', true);
        $og_image = get_post_meta($post_id, '_ssf_og_image', true);
        
        // These will be picked up by the meta manager's output_social_tags
        // We add them as extra OG tags (the main ones are already set)
        // For overrides, we need to modify the core output - use pre-render filter
        if (!empty($og_title)) {
            $tags['og:title'] = $og_title;
        }
        if (!empty($og_desc)) {
            $tags['og:description'] = $og_desc;
        }
        if (!empty($og_image)) {
            $tags['og:image'] = $og_image;
        }
        
        return $tags;
    }
}
