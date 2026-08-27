<?php
/**
 * robots.txt Editor
 * 
 * Allows managing the virtual robots.txt served by WordPress
 * through the plugin admin interface.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Robots_Editor {
    
    const OPTION_KEY = 'ssf_robots_txt_custom';
    const ENABLED_KEY = 'ssf_robots_txt_enabled';
    
    /**
     * Initialize hooks
     */
    public static function init() {
        if (self::is_enabled()) {
            add_filter('robots_txt', [__CLASS__, 'serve_custom_robots'], 999, 2);
        }
    }
    
    /**
     * Check if custom robots.txt is enabled
     */
    public static function is_enabled() {
        return (bool) get_option(self::ENABLED_KEY, false);
    }
    
    /**
     * Enable/disable custom robots.txt
     */
    public static function set_enabled($enabled) {
        update_option(self::ENABLED_KEY, (bool) $enabled);
    }
    
    /**
     * Serve our custom robots.txt content
     */
    public static function serve_custom_robots($output, $public) {
        $custom = self::get_content();
        if (!empty($custom)) {
            return $custom;
        }
        return $output;
    }
    
    /**
     * Get the saved custom robots.txt content
     */
    public static function get_content() {
        return get_option(self::OPTION_KEY, '');
    }
    
    /**
     * Save custom robots.txt content
     */
    public static function save_content($content) {
        // Sanitize: only allow printable ASCII and newlines
        $content = preg_replace('/[^\x20-\x7E\r\n\t]/', '', $content);
        
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        
        update_option(self::OPTION_KEY, $content);
        
        if (class_exists('SSF_Logger')) {
            SSF_Logger::info('robots.txt content updated', 'general');
        }
        
        return true;
    }
    
    /**
     * Get the default WordPress robots.txt content
     */
    public static function get_default_content() {
        $public = get_option('blog_public');
        
        $output = "User-agent: *\n";
        
        if ('0' == $public) {
            $output .= "Disallow: /\n";
        } else {
            $output .= "Disallow: /wp-admin/\n";
            $output .= "Allow: /wp-admin/admin-ajax.php\n";
        }
        
        $site_url = home_url('/');
        $output .= "\nSitemap: " . $site_url . "sitemap.xml\n";
        
        return $output;
    }
    
    /**
     * Generate a recommended robots.txt based on the site
     */
    public static function get_recommended_content() {
        $site_url = home_url('/');
        
        $output = "# Smart SEO Fixer - Optimized robots.txt\n";
        $output .= "# Generated: " . current_time('Y-m-d') . "\n\n";
        
        $output .= "User-agent: *\n";
        $output .= "Disallow: /wp-admin/\n";
        $output .= "Allow: /wp-admin/admin-ajax.php\n";
        $output .= "Disallow: /wp-includes/\n";
        $output .= "Disallow: /wp-content/plugins/\n";
        $output .= "Disallow: /wp-content/cache/\n";
        $output .= "Disallow: /trackback/\n";
        $output .= "Disallow: /feed/\n";
        $output .= "Disallow: /comments/feed/\n";
        $output .= "Disallow: /?s=\n";
        $output .= "Disallow: /search/\n";
        $output .= "Disallow: /tag/*/feed/\n";
        $output .= "Disallow: /category/*/feed/\n";
        $output .= "Disallow: /author/*/feed/\n";
        $output .= "\n# Allow important resources\n";
        $output .= "Allow: /wp-content/uploads/\n";
        $output .= "Allow: /wp-content/themes/\n";
        $output .= "\n# Sitemaps\n";
        $output .= "Sitemap: " . $site_url . "sitemap.xml\n";
        
        // Add WooCommerce-specific rules if active
        if (class_exists('WooCommerce')) {
            $output .= "\n# WooCommerce\n";
            $output .= "Disallow: /cart/\n";
            $output .= "Disallow: /checkout/\n";
            $output .= "Disallow: /my-account/\n";
        }
        
        return $output;
    }
    
    /**
     * Check if a physical robots.txt file exists (which would override WordPress's virtual one)
     */
    public static function has_physical_file() {
        return file_exists(ABSPATH . 'robots.txt');
    }
    
    /**
     * Get the live robots.txt URL
     */
    public static function get_robots_url() {
        return home_url('/robots.txt');
    }
    
    /**
     * Validate robots.txt content for common issues
     */
    public static function validate($content) {
        $warnings = [];
        
        if (empty(trim($content))) {
            $warnings[] = __('robots.txt is empty. Search engines will crawl everything by default.', 'smart-seo-fixer');
            return $warnings;
        }
        
        // Check for "Disallow: /" which blocks everything
        if (preg_match('/^Disallow:\s*\/\s*$/m', $content) && !preg_match('/^Disallow:\s*\/\w/m', $content)) {
            // Has "Disallow: /" without specific paths after
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === 'Disallow: /') {
                    $warnings[] = __('Warning: "Disallow: /" blocks ALL crawling. Your site will not be indexed.', 'smart-seo-fixer');
                    break;
                }
            }
        }
        
        // Check for missing User-agent
        if (stripos($content, 'user-agent') === false) {
            $warnings[] = __('Missing "User-agent" directive. Add at least "User-agent: *" for rules to apply.', 'smart-seo-fixer');
        }
        
        // Check for Sitemap directive
        if (stripos($content, 'sitemap:') === false) {
            $warnings[] = __('No Sitemap directive found. Consider adding your sitemap URL.', 'smart-seo-fixer');
        }
        
        // Check for blocking of important resources
        if (stripos($content, 'disallow: /wp-content/uploads') !== false) {
            $warnings[] = __('Blocking /wp-content/uploads/ prevents search engines from accessing your images.', 'smart-seo-fixer');
        }
        
        return $warnings;
    }
}
