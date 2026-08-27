<?php
/**
 * Meta Manager Class
 * 
 * Handles SEO titles, meta descriptions, and other meta tags.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Meta_Manager {
    
    /**
     * Constructor
     */
    
    public function __construct() {
        // Skip all frontend hooks if this is an admin or AJAX request
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Remove WordPress's default title tag — we handle it ourselves via output_title_tag().
        // Do this directly here (not via after_setup_theme callback) because SSF is instantiated
        // on 'init' and after_setup_theme has already fired by then, so a callback would never run.
        remove_action('wp_head', '_wp_render_title_tag', 1);
        if (!current_theme_supports('title-tag')) {
            add_theme_support('title-tag');
        }
        
        // Also keep filters as backup for plugins that call wp_get_document_title() directly
        add_filter('pre_get_document_title', [$this, 'filter_title'], 9999);
        add_filter('document_title_parts', [$this, 'filter_title_parts'], 9999);
        add_filter('wp_title', [$this, 'filter_title'], 9999);
        
        // Output our own <title> tag directly — most reliable approach
        add_action('wp_head', [$this, 'output_title_tag'], 0);
        
        // Add meta tags to head
        add_action('wp_head', [$this, 'output_meta_tags'], 1);
        
        // Open Graph and Twitter Cards
        add_action('wp_head', [$this, 'output_social_tags'], 2);
        
        // Robots meta
        add_action('wp_head', [$this, 'output_robots_meta'], 3);
        
        // Canonical URL
        add_action('wp_head', [$this, 'output_canonical'], 4);
        
        // Always suppress canonical tags from other SEO plugins — having two canonical
        // outputs causes "Google chose different canonical than user" in Search Console.
        // The full output suppression (titles, OG, etc.) is only done when the setting is on.
        $this->suppress_competing_canonicals();
        
        // Disable ALL conflicting SEO plugins output (runs early so hooks are removed before they fire)
        if (Smart_SEO_Fixer::get_option('disable_other_seo_output', false)) {
            $this->disable_conflicting_plugins();
        }
    }
    
    /**
     * Read SEO title from other plugins' meta keys as a fallback.
     * Used when SSF's own _ssf_seo_title field is empty.
     */
    private function get_other_plugin_title($post_id) {
        $keys = [
            '_yoast_wpseo_title',   // Yoast SEO
            'rank_math_title',       // Rank Math
            '_aioseo_title',         // All in One SEO
            '_seopress_titles_title', // SEOPress
        ];
        foreach ($keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (!empty($val)) {
                return $val;
            }
        }
        return '';
    }

    /**
     * Read meta description from other plugins' meta keys as a fallback.
     * Used when SSF's own _ssf_meta_description field is empty.
     */
    private function get_other_plugin_description($post_id) {
        $keys = [
            '_yoast_wpseo_metadesc',   // Yoast SEO
            'rank_math_description',    // Rank Math
            '_aioseo_description',      // All in One SEO
            '_seopress_titles_desc',    // SEOPress
        ];
        foreach ($keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if (!empty($val)) {
                return $val;
            }
        }
        return '';
    }

    /**
     * Output our own <title> tag directly in wp_head
     * 
     * This is the most reliable approach — used by Yoast, Rank Math, etc.
     * No dependency on title-tag theme support, no filter chain issues,
     * no output buffer hacks. Just a direct, guaranteed <title> tag.
     */
    public function output_title_tag() {
        $title = $this->get_current_page_title();
        
        if (!empty($title)) {
            echo '<title>' . esc_html($title) . '</title>' . "\n";
        }
    }
    
    /**
     * Get the SEO title for the current page (used by title tag and social tags)
     */
    public function get_current_page_title() {
        if (is_singular()) {
            $post_id = get_the_ID();
            $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
            
            if (empty($seo_title)) {
                $seo_title = $this->get_other_plugin_title($post_id);
            }
            
            if (!empty($seo_title)) {
                return $seo_title;
            }
            
            $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
            return get_the_title($post_id) . ' ' . $separator . ' ' . get_bloginfo('name');
        }
        
        if (is_front_page() || is_home()) {
            $homepage_title = Smart_SEO_Fixer::get_option('homepage_title');
            if (!empty($homepage_title)) {
                return $homepage_title;
            }
            $site_name = get_bloginfo('name');
            $tagline = get_bloginfo('description');
            return !empty($tagline) ? $site_name . ' - ' . $tagline : $site_name;
        }
        
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
            return ($term ? $term->name : '') . ' ' . $separator . ' ' . get_bloginfo('name');
        }
        
        if (is_author()) {
            $author = get_queried_object();
            $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
            return ($author ? $author->display_name : '') . ' ' . $separator . ' ' . get_bloginfo('name');
        }
        
        if (is_search()) {
            $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
            return __('Search Results', 'smart-seo-fixer') . ' ' . $separator . ' ' . get_bloginfo('name');
        }
        
        if (is_404()) {
            $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
            return __('Page Not Found', 'smart-seo-fixer') . ' ' . $separator . ' ' . get_bloginfo('name');
        }
        
        return get_bloginfo('name');
    }
    
    /**
     * Suppress canonical output from other SEO plugins.
     * 
     * Unlike disable_conflicting_plugins() (which silences ALL their output and is
     * gated by a user setting), this runs unconditionally and only targets canonical
     * tags. Having two canonical tags on the same page is always wrong — it causes
     * "Google chose different canonical than user" errors in Search Console even
     * when both plugins point to the same URL, because Google interprets competing
     * signals as ambiguity.
     */
    private function suppress_competing_canonicals() {
        // Yoast SEO — filter returns false to suppress canonical tag output
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) {
            add_filter('wpseo_canonical', '__return_false', 9999);
        }
        
        // Rank Math — disable their canonical output
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            add_filter('rank_math/frontend/canonical', '__return_false', 9999);
        }
        
        // All in One SEO — suppress canonical
        if (defined('AIOSEO_VERSION')) {
            add_filter('aioseo_canonical_url', '__return_false', 9999);
        }
        
        // The SEO Framework — suppress canonical
        if (function_exists('the_seo_framework')) {
            add_filter('the_seo_framework_rel_canonical_output', '__return_empty_string', 9999);
        }
        
        // SEOPress — suppress canonical
        if (defined('SEOPRESS_VERSION')) {
            add_filter('seopress_titles_canonical', '__return_false', 9999);
        }
    }
    
    /**
     * Disable meta output from other SEO plugins to prevent duplicates
     */
    public function disable_conflicting_plugins() {
        // === Yoast SEO ===
        // remove_action during init fires BEFORE Yoast has a chance to add_action to wp_head,
        // so the removal is silently ignored. Instead, hook to wp_head at priority 0 so we
        // remove Yoast's priority-1 hook right before it would fire — guaranteed to work.
        add_action('wp_head', function() {
            remove_action('wp_head', 'wpseo_head', 1);
            // Old-style Yoast (pre-v14)
            if (class_exists('WPSEO_Frontend')) {
                $instance = WPSEO_Frontend::get_instance();
                remove_action('wp_head', [$instance, 'head'], 1);
            }
        }, 0);
        
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) {
            // Belt-and-suspenders filters for any Yoast output that leaks through other paths
            add_filter('wpseo_frontend_presenters', '__return_empty_array', 9999);
            add_filter('wpseo_title', '__return_empty_string', 9999);
            add_filter('wpseo_metadesc', '__return_empty_string', 9999);
            add_filter('wpseo_opengraph_title', '__return_empty_string', 9999);
            add_filter('wpseo_opengraph_desc', '__return_empty_string', 9999);
            add_filter('wpseo_opengraph_url', '__return_empty_string', 9999);
            add_filter('wpseo_opengraph_image', '__return_empty_string', 9999);
            add_filter('wpseo_twitter_title', '__return_empty_string', 9999);
            add_filter('wpseo_twitter_description', '__return_empty_string', 9999);
            add_filter('wpseo_canonical', '__return_false', 9999);
            add_filter('wpseo_robots', '__return_empty_string', 9999);
            add_filter('wpseo_json_ld_output', '__return_empty_array', 9999);
            add_filter('wpseo_schema_graph', '__return_empty_array', 9999);
        }
        
        // === Rank Math ===
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            // Remove Rank Math head action
            add_action('template_redirect', function() {
                if (class_exists('RankMath\Frontend\Frontend')) {
                    remove_all_actions('rank_math/head');
                }
            }, 1);
            
            // Rank Math filters
            add_filter('rank_math/frontend/title', '__return_empty_string', 9999);
            add_filter('rank_math/frontend/description', '__return_empty_string', 9999);
            add_filter('rank_math/opengraph/facebook', '__return_false', 9999);
            add_filter('rank_math/opengraph/twitter', '__return_false', 9999);
            add_filter('rank_math/json_ld', '__return_empty_array', 9999);
        }
        
        // === All in One SEO ===
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEOP_Core')) {
            add_action('template_redirect', function() {
                // AIOSEO v4+
                if (function_exists('aioseo')) {
                    remove_action('wp_head', [aioseo()->head, 'output'], 1);
                }
                // AIOSEO v3 (legacy)
                if (class_exists('All_in_One_SEO_Pack')) {
                    $aioseop = All_in_One_SEO_Pack::get_instance();
                    if ($aioseop) {
                        remove_action('wp_head', [$aioseop, 'wp_head']);
                    }
                }
            }, 1);
        }
        
        // === The SEO Framework ===
        if (defined('THE_SEO_FRAMEWORK_VERSION') || function_exists('the_seo_framework')) {
            add_action('template_redirect', function() {
                if (function_exists('the_seo_framework')) {
                    $tsf = the_seo_framework();
                    remove_action('wp_head', [$tsf, 'html_output'], 1);
                }
            }, 1);
        }
        
        // === SEOPress ===
        if (defined('SEOPRESS_VERSION') || class_exists('SEOPRESS')) {
            add_filter('seopress_titles_title', '__return_empty_string', 9999);
            add_filter('seopress_titles_desc', '__return_empty_string', 9999);
            add_action('template_redirect', function() {
                remove_action('wp_head', 'seopress_social_fb_og_title');
                remove_action('wp_head', 'seopress_social_fb_og_desc');
            }, 1);
        }
    }
    
    /**
     * Filter document title
     */
    public function filter_title($title) {
        if (is_singular()) {
            $post_id = get_the_ID();
            $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
            
            if (empty($seo_title)) {
                $seo_title = $this->get_other_plugin_title($post_id);
            }
            
            if (!empty($seo_title)) {
                return class_exists('SSF_Validator') ? SSF_Validator::enforce_seo_title($seo_title, 60) : $seo_title;
            }
            
            // If no SSF title is set, always return something meaningful
            if (empty($title)) {
                $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
                $post_title = get_the_title($post_id);
                $site_name = get_bloginfo('name');
                $fallback = $post_title . ' ' . $separator . ' ' . $site_name;
                return class_exists('SSF_Validator') ? SSF_Validator::enforce_seo_title($fallback, 60) : $fallback;
            }
        }
        
        // Homepage
        if (is_front_page() || is_home()) {
            $homepage_title = Smart_SEO_Fixer::get_option('homepage_title');
            if (!empty($homepage_title)) {
                return $homepage_title;
            }
            
            // Fallback for homepage
            if (empty($title)) {
                $site_name = get_bloginfo('name');
                $tagline = get_bloginfo('description');
                return !empty($tagline) ? $site_name . ' - ' . $tagline : $site_name;
            }
        }
        
        return $title;
    }
    
    /**
     * Filter title parts
     */
    public function filter_title_parts($title_parts) {
        $separator = Smart_SEO_Fixer::get_option('title_separator', '|');
        
        // The separator is handled by WordPress, but we can modify parts
        if (isset($title_parts['title']) && is_singular()) {
            $post_id = get_the_ID();
            $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
            
            if (empty($seo_title)) {
                $seo_title = $this->get_other_plugin_title($post_id);
            }
            
            if (!empty($seo_title)) {
                // If SEO title is set, use it as-is without site name
                return ['title' => $seo_title];
            }
        }
        
        return $title_parts;
    }
    
    /**
     * Output meta description
     */
    public function output_meta_tags() {
        $description = $this->get_meta_description();
        
        if (!empty($description)) {
            if (class_exists('SSF_Validator')) {
                $description = SSF_Validator::enforce_meta_description($description, 160);
            }
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        // Output focus keyword as meta keywords (optional, mostly deprecated)
        if (is_singular()) {
            $post_id = get_the_ID();
            $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
            
            if (!empty($focus_keyword)) {
                echo '<meta name="keywords" content="' . esc_attr($focus_keyword) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Get meta description for current page
     */
    public function get_meta_description() {
        if (is_singular()) {
            $post_id = get_the_ID();
            $description = get_post_meta($post_id, '_ssf_meta_description', true);
            
            if (empty($description)) {
                $description = $this->get_other_plugin_description($post_id);
            }
            
            if (!empty($description)) {
                return $description;
            }
            
            // Auto-generate from excerpt/content
            $post = get_post($post_id);
            if ($post) {
                $text = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
                $text = wp_strip_all_tags($text);
                $text = str_replace(["\n", "\r", "\t"], ' ', $text);
                $text = preg_replace('/\s+/', ' ', $text);
                return wp_trim_words($text, 25, '...');
            }
        }
        
        // Homepage
        if (is_front_page() || is_home()) {
            $description = Smart_SEO_Fixer::get_option('homepage_description');
            if (!empty($description)) {
                return $description;
            }
            return get_bloginfo('description');
        }
        
        // Archive pages
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !empty($term->description)) {
                return wp_trim_words($term->description, 25, '...');
            }
        }
        
        // Author archive
        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $bio = get_the_author_meta('description', $author->ID);
                if (!empty($bio)) {
                    return wp_trim_words($bio, 25, '...');
                }
            }
        }
        
        return '';
    }
    
    /**
     * Output Open Graph and Twitter Card tags
     */
    public function output_social_tags() {
        if (!is_singular()) {
            return;
        }
        
        $post_id = get_the_ID();
        $post = get_post($post_id);
        
        if (!$post) {
            return;
        }
        
        // Get SEO data
        $title = get_post_meta($post_id, '_ssf_seo_title', true) ?: get_the_title($post_id);
        $description = get_post_meta($post_id, '_ssf_meta_description', true) ?: $this->get_meta_description();
        $url = $this->normalize_url_slashes(get_permalink($post_id));
        
        // Get image — featured image → first content image → site logo fallback.
        // Track real width/height when we know them (from the attachment) so the
        // og:image:width/height tags are accurate instead of a hardcoded guess.
        $image = '';
        $image_w = 0;
        $image_h = 0;
        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            $image_data = wp_get_attachment_image_src($image_id, 'large');
            if ($image_data) {
                $image   = $image_data[0];
                $image_w = isset($image_data[1]) ? (int) $image_data[1] : 0;
                $image_h = isset($image_data[2]) ? (int) $image_data[2] : 0;
            }
        }

        // Fallback: first image in content (dimensions unknown).
        if (empty($image) && !empty($post->post_content)) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $img_match)) {
                $image = $img_match[1];
            }
        }

        // Fallback: site logo (from Customizer).
        if (empty($image)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo_data) {
                    $image   = $logo_data[0];
                    $image_w = isset($logo_data[1]) ? (int) $logo_data[1] : 0;
                    $image_h = isset($logo_data[2]) ? (int) $logo_data[2] : 0;
                }
            }
        }
        
        // Open Graph
        echo '<!-- Smart SEO Fixer - Open Graph -->' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        if (!empty($image)) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            // Only emit dimensions we actually know (from the attachment).
            if ($image_w > 0 && $image_h > 0) {
                echo '<meta property="og:image:width" content="' . esc_attr($image_w) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($image_h) . '" />' . "\n";
            }
        }
        
        // Allow extensions (e.g., WooCommerce) to add OG tags
        $extra_tags = apply_filters('ssf_og_tags', [], $post_id);
        if (!empty($extra_tags) && is_array($extra_tags)) {
            foreach ($extra_tags as $property => $content) {
                echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($content) . '" />' . "\n";
            }
        }
        
        // Twitter Card
        echo '<!-- Smart SEO Fixer - Twitter Card -->' . "\n";
        echo '<meta name="twitter:card" content="' . (!empty($image) ? 'summary_large_image' : 'summary') . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if (!empty($image)) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
    }
    
    /**
     * Output robots meta tag
     */
    public function output_robots_meta() {
        $robots = null; // [index/noindex, follow/nofollow]

        if (is_singular()) {
            $post_id  = get_the_ID();
            $noindex  = get_post_meta($post_id, '_ssf_noindex', true) ? 'noindex' : 'index';
            $nofollow = get_post_meta($post_id, '_ssf_nofollow', true) ? 'nofollow' : 'follow';
            $robots   = [$noindex, $nofollow];
        } elseif (is_search()) {
            // Internal search results should never be indexed (Google guideline).
            $robots = ['noindex', 'follow'];
        } elseif (is_404()) {
            $robots = ['noindex', 'follow'];
        } elseif (is_paged() && (is_archive() || is_home())) {
            // Page 2+ of an archive: keep crawlable for discovery but out of the
            // index, so only the first page competes (avoids thin paginated dupes).
            $robots = ['noindex', 'follow'];
        }

        // Let integrations override (e.g. force-index a specific archive).
        $robots = apply_filters('ssf_robots_meta', $robots);

        if (is_array($robots) && !empty($robots)) {
            echo '<meta name="robots" content="' . esc_attr(implode(', ', $robots)) . '" />' . "\n";
        }
    }
    
    /**
     * Output canonical URL
     */
    public function output_canonical() {
        // Get canonical URL based on page type
        $canonical = '';
        
        if (is_singular()) {
            $post_id = get_the_ID();
            $canonical = get_post_meta($post_id, '_ssf_canonical_url', true);
            
            if (empty($canonical)) {
                $canonical = get_permalink($post_id);
            }
        } elseif (is_front_page() || is_home()) {
            $canonical = home_url('/');
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $canonical = get_term_link($term);
            }
        } elseif (is_post_type_archive()) {
            $canonical = get_post_type_archive_link(get_query_var('post_type'));
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $canonical = get_author_posts_url($author->ID);
            }
        }
        
        // Apply canonical URL filter (handles UTM stripping via search console class)
        if (!empty($canonical) && !is_wp_error($canonical)) {
            $canonical = apply_filters('ssf_canonical_url', $canonical);
            
            // Enforce trailing slash consistency to match WordPress permalink structure
            $canonical = $this->normalize_url_slashes($canonical);
            
            // Enforce scheme consistency: canonical must match the site's actual scheme.
            // This prevents "Google chose different canonical" when a post was saved with
            // http:// but the site now serves https:// (or vice versa after a migration).
            $site_url   = get_option('siteurl', '');
            $site_https = (strpos($site_url, 'https://') === 0);
            if ($site_https && strpos($canonical, 'http://') === 0) {
                $canonical = 'https://' . substr($canonical, 7);
            } elseif (!$site_https && strpos($canonical, 'https://') === 0) {
                $canonical = 'http://' . substr($canonical, 8);
            }
            
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }
    
    /**
     * Normalize trailing slashes on a URL to match WordPress permalink settings
     * Prevents "Google chose different canonical" errors from slash inconsistencies
     */
    private function normalize_url_slashes($url) {
        if (empty($url)) return $url;
        
        $parsed = wp_parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Don't modify URLs with file extensions (e.g., /sitemap.xml, /feed)
        if (preg_match('/\.\w{2,5}$/', $path)) {
            return $url;
        }
        
        // Check WordPress permalink structure for trailing slash preference
        $permalink_structure = get_option('permalink_structure', '');
        $uses_trailing_slash = !empty($permalink_structure) && substr($permalink_structure, -1) === '/';
        
        if ($uses_trailing_slash) {
            return trailingslashit($url);
        } else {
            return untrailingslashit($url);
        }
    }
    
    /**
     * Get post SEO data
     */
    public function get_post_seo_data($post_id) {
        return [
            'seo_title' => get_post_meta($post_id, '_ssf_seo_title', true),
            'meta_description' => get_post_meta($post_id, '_ssf_meta_description', true),
            'focus_keyword' => get_post_meta($post_id, '_ssf_focus_keyword', true),
            'canonical_url' => get_post_meta($post_id, '_ssf_canonical_url', true),
            'noindex' => get_post_meta($post_id, '_ssf_noindex', true),
            'nofollow' => get_post_meta($post_id, '_ssf_nofollow', true),
            'seo_score' => get_post_meta($post_id, '_ssf_seo_score', true),
            'seo_grade' => get_post_meta($post_id, '_ssf_seo_grade', true),
        ];
    }
    
    /**
     * Save post SEO data
     */
    public function save_post_seo_data($post_id, $data) {
        $textarea_fields = ['_ssf_meta_description'];

        $fields = [
            '_ssf_seo_title',
            '_ssf_meta_description',
            '_ssf_focus_keyword',
            '_ssf_canonical_url',
            '_ssf_noindex',
            '_ssf_nofollow',
        ];
        
        foreach ($fields as $field) {
            $key = str_replace('_ssf_', '', $field);
            if (isset($data[$key])) {
                $sanitized = in_array($field, $textarea_fields, true)
                    ? sanitize_textarea_field($data[$key])
                    : sanitize_text_field($data[$key]);
                update_post_meta($post_id, $field, $sanitized);
            }
        }
    }
}

