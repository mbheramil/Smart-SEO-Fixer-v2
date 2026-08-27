<?php
/**
 * Sitemap Class
 * 
 * Generates XML sitemaps for the website.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Sitemap {
    
    /**
     * Max URLs per sitemap file (Google's limit is 50,000)
     */
    const MAX_URLS_PER_SITEMAP = 2000;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (Smart_SEO_Fixer::get_option('enable_sitemap', true)) {
            // Register rewrite rules now (constructor runs during init priority 10;
            // hooking init at priority 1 would be too late, so call directly)
            $this->add_rewrite_rules();
            add_filter('query_vars', [$this, 'add_query_vars']);
            add_action('template_redirect', [$this, 'render_sitemap'], 1);
            
            // Intercept sitemap + XSL requests early, before other plugins can serve theirs
            add_action('parse_request', [$this, 'intercept_sitemap_request'], 1);
            
            // Instant indexing on publish is handled by SSF_IndexNow
            // (transition_post_status). The old Google/Bing sitemap "ping"
            // endpoints were removed by both engines in 2023, so they're gone.

            // Disable conflicting sitemaps from other plugins
            add_action('init', [$this, 'disable_conflicting_sitemaps'], 99);
            
            // Disable WordPress core sitemaps (WP 5.5+)
            add_filter('wp_sitemaps_enabled', '__return_false');
        }
    }
    
    /**
     * Get all public post types that should be in the sitemap.
     */
    private function get_sitemap_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $result = [];
        foreach ($post_types as $pt) {
            // Skip attachments — they are just media files
            if ($pt->name === 'attachment') {
                continue;
            }
            $result[] = $pt->name;
        }
        return $result;
    }
    
    /**
     * Get all public taxonomies that should be in the sitemap.
     */
    private function get_sitemap_taxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $result = [];
        foreach ($taxonomies as $tax) {
            // Skip post_format
            if ($tax->name === 'post_format') {
                continue;
            }
            $result[] = $tax->name;
        }
        return $result;
    }
    
    /**
     * Intercept sitemap requests early before other plugins can serve theirs.
     */
    public function intercept_sitemap_request($wp) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
        
        // Strip the site subdirectory if WordPress is in a subdirectory
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
        if ($home_path && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        }
        
        // XSL stylesheets
        if ($path === 'ssf-sitemap-index.xsl') {
            $wp->query_vars['ssf_sitemap'] = 'xsl-index';
            return;
        }
        if ($path === 'ssf-sitemap.xsl') {
            $wp->query_vars['ssf_sitemap'] = 'xsl';
            return;
        }
        
        if ($path === 'sitemap.xml') {
            $wp->query_vars['ssf_sitemap'] = 'index';
            return;
        }
        
        // Match post type sitemaps: sitemap-{type}.xml or sitemap-{type}{page}.xml
        if (preg_match('/^sitemap-(.+?)(\d*)\.xml$/', $path, $m)) {
            $slug = $m[1];
            $page = $m[2] !== '' ? intval($m[2]) : 1;
            
            // Check post types
            $post_types = $this->get_sitemap_post_types();
            foreach ($post_types as $pt) {
                $pt_slug = $this->post_type_slug($pt);
                if ($slug === $pt_slug || $slug === $pt_slug . '-') {
                    $wp->query_vars['ssf_sitemap'] = 'pt:' . $pt . ':' . $page;
                    return;
                }
            }
            
            // Check taxonomies
            $taxonomies = $this->get_sitemap_taxonomies();
            foreach ($taxonomies as $tax) {
                $tax_slug = $this->taxonomy_slug($tax);
                if ($slug === $tax_slug || $slug === $tax_slug . '-') {
                    $wp->query_vars['ssf_sitemap'] = 'tax:' . $tax . ':' . $page;
                    return;
                }
            }
            
            // Authors
            if ($slug === 'authors' || $slug === 'authors-') {
                $wp->query_vars['ssf_sitemap'] = 'authors:' . $page;
                return;
            }
        }
    }
    
    /**
     * Disable sitemaps from other SEO plugins to avoid conflicts.
     */
    public function disable_conflicting_sitemaps() {
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            add_filter('wpseo_sitemaps_enabled', '__return_false');
            // Remove Yoast sitemap rewrite rules
            global $wp_rewrite;
            if (isset($wp_rewrite->extra_rules_top)) {
                foreach ($wp_rewrite->extra_rules_top as $rule => $rewrite) {
                    if (strpos($rewrite, 'wpseo_sitemap') !== false || strpos($rewrite, 'sitemap_xsl') !== false) {
                        unset($wp_rewrite->extra_rules_top[$rule]);
                    }
                }
            }
            // Prevent Yoast from serving its sitemap via template_redirect
            if (class_exists('WPSEO_Sitemaps')) {
                remove_action('template_redirect', 'redirect_canonical');
            }
        }
        
        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            add_filter('rank_math/sitemap/enable', '__return_false');
        }
        
        // All in One SEO
        if (defined('AIOSEO_VERSION')) {
            add_filter('aioseo_sitemap_enabled', '__return_false');
        }
    }
    
    /**
     * Add rewrite rules for sitemap
     */
    public function add_rewrite_rules() {
        // Index
        add_rewrite_rule('^sitemap\.xml$', 'index.php?ssf_sitemap=index', 'top');
        
        // XSL stylesheets
        add_rewrite_rule('^ssf-sitemap-index\.xsl$', 'index.php?ssf_sitemap=xsl-index', 'top');
        add_rewrite_rule('^ssf-sitemap\.xsl$', 'index.php?ssf_sitemap=xsl', 'top');
        
        // Catch-all for any sub-sitemap: sitemap-{anything}.xml
        // The actual parsing is done in intercept_sitemap_request
        add_rewrite_rule('^sitemap-([a-zA-Z0-9_-]+?)(\d*)\.xml$', 'index.php?ssf_sitemap=dynamic', 'top');
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'ssf_sitemap';
        return $vars;
    }
    
    /**
     * Render sitemap
     */
    public function render_sitemap() {
        $sitemap_type = get_query_var('ssf_sitemap');
        
        // Fallback: re-parse from REQUEST_URI if query var is missing or still 'dynamic'
        if (empty($sitemap_type) || $sitemap_type === 'dynamic') {
            $sitemap_type = $this->detect_sitemap_type_from_uri();
        }
        
        if (empty($sitemap_type)) {
            return;
        }
        
        $output = '';

        if ($sitemap_type === 'index') {
            $output = $this->generate_index_sitemap();
            header('Content-Type: application/xml; charset=UTF-8');
        } elseif ($sitemap_type === 'xsl-index') {
            $this->force_ok_status();
            header('Content-Type: text/xsl; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
            echo $this->get_xsl_stylesheet(true);
            exit;
        } elseif ($sitemap_type === 'xsl') {
            $this->force_ok_status();
            header('Content-Type: text/xsl; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
            echo $this->get_xsl_stylesheet(false);
            exit;
        } elseif (strpos($sitemap_type, 'pt:') === 0) {
            // Post type: pt:{post_type}:{page}
            $parts = explode(':', $sitemap_type);
            $pt = $parts[1] ?? 'post';
            $page = intval($parts[2] ?? 1);
            $output = $this->generate_post_type_sitemap($pt, $page);
        } elseif (strpos($sitemap_type, 'tax:') === 0) {
            // Taxonomy: tax:{taxonomy}:{page}
            $parts = explode(':', $sitemap_type);
            $tax = $parts[1] ?? 'category';
            $page = intval($parts[2] ?? 1);
            $output = $this->generate_taxonomy_sitemap($tax, $page);
        } elseif (strpos($sitemap_type, 'authors') === 0) {
            $parts = explode(':', $sitemap_type);
            $page = intval($parts[1] ?? 1);
            $output = $this->generate_authors_sitemap($page);
        }
        
        if (empty($output)) {
            return;
        }

        // Critical: WordPress runs handle_404() before template_redirect, and a
        // request that matched no posts (which a virtual sitemap URL does) is
        // flagged 404 with a 404 status header already queued. Without forcing
        // 200 here, /sitemap.xml is served as valid XML but with an HTTP 404 —
        // and Google rejects any sitemap that doesn't return 200.
        $this->force_ok_status();

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        echo $output;
        exit;
    }

    /**
     * Force a 200 OK status for sitemap output and clear the 404 flag that
     * WordPress may have set during handle_404() for this no-posts request.
     */
    private function force_ok_status() {
        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $wp_query->is_404 = false;
        }
        if (!headers_sent()) {
            status_header(200);
        }
    }
    
    /**
     * Detect the sitemap type by parsing REQUEST_URI directly.
     * Used as a fallback when query vars aren't reliably available.
     */
    private function detect_sitemap_type_from_uri() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
        
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
        if ($home_path && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        }
        
        if ($path === 'sitemap.xml')              return 'index';
        if ($path === 'ssf-sitemap-index.xsl')    return 'xsl-index';
        if ($path === 'ssf-sitemap.xsl')          return 'xsl';
        
        if (preg_match('/^sitemap-(.+?)(\d*)\.xml$/', $path, $m)) {
            $slug = $m[1];
            $page = $m[2] !== '' ? intval($m[2]) : 1;
            
            foreach ($this->get_sitemap_post_types() as $pt) {
                $pt_slug = $this->post_type_slug($pt);
                if ($slug === $pt_slug) {
                    return 'pt:' . $pt . ':' . $page;
                }
            }
            foreach ($this->get_sitemap_taxonomies() as $tax) {
                $tax_slug = $this->taxonomy_slug($tax);
                if ($slug === $tax_slug) {
                    return 'tax:' . $tax . ':' . $page;
                }
            }
            if ($slug === 'authors') {
                return 'authors:' . $page;
            }
        }
        
        return '';
    }
    
    /**
     * Get a URL-friendly slug for a post type sitemap.
     */
    private function post_type_slug($post_type) {
        $map = [
            'post' => 'post',
            'page' => 'page',
        ];
        return isset($map[$post_type]) ? $map[$post_type] : sanitize_title($post_type);
    }
    
    /**
     * Get a URL-friendly slug for a taxonomy sitemap.
     */
    private function taxonomy_slug($taxonomy) {
        $map = [
            'category' => 'category',
            'post_tag' => 'post_tag',
        ];
        return isset($map[$taxonomy]) ? $map[$taxonomy] : sanitize_title($taxonomy);
    }
    
    /**
     * Count published posts for a post type.
     */
    private function count_posts_for_type($post_type) {
        global $wpdb;
        // Exclude noindex posts so the count matches the URLs actually emitted by
        // generate_post_type_sitemap() (which skips noindex) — otherwise the index
        // can list a sub-sitemap that renders empty, which Google flags as an error.
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             WHERE p.post_type = %s AND p.post_status = 'publish'
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm
                 WHERE pm.post_id = p.ID AND pm.meta_key = '_ssf_noindex' AND pm.meta_value = '1'
             )",
            $post_type
        ));
    }
    
    /**
     * Count terms for a taxonomy.
     */
    private function count_terms_for_tax($taxonomy) {
        return (int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
    }
    
    /**
     * Generate sitemap index — automatically includes all public post types and taxonomies.
     */
    private function generate_index_sitemap() {
        $xsl_url = esc_url(home_url('/?ssf_sitemap=xsl-index'));
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<?xml-stylesheet type="text/xsl" href="' . $xsl_url . '"?>' . "\n";
        $output .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Post type sitemaps
        foreach ($this->get_sitemap_post_types() as $pt) {
            $count = $this->count_posts_for_type($pt);
            if ($count === 0) {
                continue;
            }
            
            $pages = max(1, ceil($count / self::MAX_URLS_PER_SITEMAP));
            $slug = $this->post_type_slug($pt);
            
            // Get latest modified date for this post type
            $last_post = get_posts([
                'numberposts' => 1,
                'post_type' => $pt,
                'post_status' => 'publish',
                'orderby' => 'modified',
                'order' => 'DESC',
            ]);
            $lastmod = !empty($last_post) ? get_post_modified_time('c', true, $last_post[0]) : '';
            
            for ($p = 1; $p <= $pages; $p++) {
                $suffix = $pages > 1 ? $p : '';
                $output .= $this->sitemap_entry(
                    home_url('/sitemap-' . $slug . $suffix . '.xml'),
                    $lastmod
                );
            }
        }
        
        // Taxonomy sitemaps
        foreach ($this->get_sitemap_taxonomies() as $tax) {
            $count = $this->count_terms_for_tax($tax);
            if ($count === 0) {
                continue;
            }
            
            $pages = max(1, ceil($count / self::MAX_URLS_PER_SITEMAP));
            $slug = $this->taxonomy_slug($tax);
            
            for ($p = 1; $p <= $pages; $p++) {
                $suffix = $pages > 1 ? $p : '';
                $output .= $this->sitemap_entry(
                    home_url('/sitemap-' . $slug . $suffix . '.xml')
                );
            }
        }
        
        // Authors sitemap
        $author_count = count(get_users([
            'has_published_posts' => true,
            'fields' => 'ID',
        ]));
        if ($author_count > 0) {
            $output .= $this->sitemap_entry(home_url('/sitemap-authors.xml'));
        }
        
        $output .= '</sitemapindex>';
        
        return $output;
    }
    
    /**
     * Generate sitemap for any post type, with pagination.
     */
    private function generate_post_type_sitemap($post_type, $page = 1) {
        $output = $this->sitemap_header();
        
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;
        
        // Add homepage for page type, page 1
        if ($post_type === 'page' && $page === 1) {
            $output .= $this->url_entry(
                home_url('/'),
                current_time('c'),
                'daily',
                '1.0'
            );
        }
        
        $posts = get_posts([
            'numberposts' => self::MAX_URLS_PER_SITEMAP,
            'offset'      => $offset,
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);
        
        $is_page = ($post_type === 'page');
        $front_page_id = $is_page ? (int) get_option('page_on_front') : 0;
        
        foreach ($posts as $post) {
            // Skip noindex
            if (get_post_meta($post->ID, '_ssf_noindex', true)) {
                continue;
            }
            // Skip homepage (already added above)
            if ($is_page && $post->ID === $front_page_id) {
                continue;
            }
            
            $priority = $is_page ? '0.6' : '0.8';
            $freq = $is_page ? 'monthly' : 'weekly';

            $output .= $this->url_entry(
                get_permalink($post->ID),
                get_post_modified_time('c', true, $post),
                $freq,
                $priority,
                $this->get_post_images($post)
            );
        }
        
        $output .= '</urlset>';
        return $output;
    }
    
    /**
     * Generate sitemap for any taxonomy, with pagination.
     */
    private function generate_taxonomy_sitemap($taxonomy, $page = 1) {
        $output = $this->sitemap_header();
        
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;
        
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => self::MAX_URLS_PER_SITEMAP,
            'offset'     => $offset,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $output .= $this->url_entry(
                    get_term_link($term),
                    '',
                    'weekly',
                    '0.5'
                );
            }
        }
        
        $output .= '</urlset>';
        return $output;
    }
    
    /**
     * Generate authors sitemap, with pagination.
     */
    private function generate_authors_sitemap($page = 1) {
        $output = $this->sitemap_header();
        
        $offset = ($page - 1) * self::MAX_URLS_PER_SITEMAP;
        
        $authors = get_users([
            'has_published_posts' => true,
            'number'  => self::MAX_URLS_PER_SITEMAP,
            'offset'  => $offset,
            'orderby' => 'post_count',
            'order'   => 'DESC',
        ]);
        
        foreach ($authors as $author) {
            $output .= $this->url_entry(
                get_author_posts_url($author->ID),
                '',
                'weekly',
                '0.4'
            );
        }
        
        $output .= '</urlset>';
        return $output;
    }
    
    /**
     * Generate XSL stylesheet for styled sitemap display in browsers.
     */
    private function get_xsl_stylesheet($is_index = false) {
        $site_name = esc_html(get_bloginfo('name'));
        $plugin_name = 'Smart SEO Fixer';
        $home = esc_url(home_url('/'));
        $sitemap_url = esc_url(home_url('/sitemap.xml'));
        
        $xsl = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        // Must be XSLT 1.0 — every browser's built-in XSLT engine (libxslt) is
        // 1.0 only. Declaring version="2.0" makes Chrome/Firefox refuse to
        // render the stylesheet, so the sitemap shows as a blank or raw page.
        $xsl .= '<xsl:stylesheet version="1.0"
            xmlns:html="http://www.w3.org/TR/REC-html40"
            xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
            xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
        <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
        <xsl:template match="/">';
        
        $xsl .= '<html xmlns="http://www.w3.org/1999/xhtml"><head>
        <title>' . ($is_index ? 'XML Sitemap Index' : 'XML Sitemap') . ' — ' . $site_name . '</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="robots" content="noindex, follow"/>
        <style type="text/css">
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                color: #1e293b;
                background: #f1f5f9;
                line-height: 1.6;
            }
            .container {
                max-width: 1024px;
                margin: 0 auto;
                padding: 32px 20px;
            }
            .header {
                background: linear-gradient(135deg, #0f172a, #1e40af);
                color: #fff;
                padding: 32px 40px;
                border-radius: 12px;
                margin-bottom: 24px;
            }
            .header h1 {
                font-size: 22px;
                font-weight: 700;
                margin-bottom: 6px;
            }
            .header p {
                font-size: 13px;
                color: #94a3b8;
                margin: 0;
            }
            .header p a {
                color: #60a5fa;
                text-decoration: none;
            }
            .header p a:hover {
                text-decoration: underline;
            }
            .badge {
                display: inline-block;
                background: rgba(255,255,255,0.15);
                color: #e2e8f0;
                font-size: 11px;
                padding: 3px 10px;
                border-radius: 20px;
                margin-left: 10px;
                font-weight: 500;
                vertical-align: middle;
            }
            .info {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 14px 20px;
                margin-bottom: 20px;
                font-size: 13px;
                color: #64748b;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                border-radius: 10px;
                overflow: hidden;
                border: 1px solid #e2e8f0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            th {
                background: #f8fafc;
                color: #475569;
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 12px 16px;
                text-align: left;
                border-bottom: 2px solid #e2e8f0;
            }
            td {
                padding: 10px 16px;
                font-size: 13px;
                border-bottom: 1px solid #f1f5f9;
                color: #334155;
            }
            tr:last-child td { border-bottom: none; }
            tr:hover td { background: #f8fafc; }
            td a {
                color: #2563eb;
                text-decoration: none;
                word-break: break-all;
            }
            td a:hover { text-decoration: underline; }
            .row-num {
                color: #94a3b8;
                font-size: 12px;
                font-variant-numeric: tabular-nums;
            }
            .date {
                color: #64748b;
                font-size: 12px;
                white-space: nowrap;
            }
            .priority {
                font-weight: 600;
                font-size: 12px;
            }
            .freq {
                font-size: 12px;
                color: #64748b;
                text-transform: capitalize;
            }
            .footer {
                text-align: center;
                padding: 20px;
                font-size: 12px;
                color: #94a3b8;
            }
            .footer a { color: #64748b; text-decoration: none; }
            .footer a:hover { text-decoration: underline; }
        </style>
        </head><body><div class="container">';
        
        // Header
        $xsl .= '<div class="header">';
        $xsl .= '<h1>' . ($is_index ? 'XML Sitemap Index' : 'XML Sitemap');
        
        if ($is_index) {
            $xsl .= '<span class="badge"><xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/> sitemaps</span>';
        } else {
            $xsl .= '<span class="badge"><xsl:value-of select="count(sitemap:urlset/sitemap:url)"/> URLs</span>';
        }
        
        $xsl .= '</h1>';
        $xsl .= '<p>Generated by <strong>' . $plugin_name . '</strong> for <a href="' . $home . '">' . $site_name . '</a></p>';
        $xsl .= '</div>';
        
        // Info box
        if ($is_index) {
            $xsl .= '<div class="info">This XML sitemap index lists all sub-sitemaps for this website. It is used by search engines like Google and Bing to discover and crawl your content.</div>';
        } else {
            $xsl .= '<div class="info">This XML sitemap contains the list of URLs for this section. <a href="' . $sitemap_url . '">← Back to Sitemap Index</a></div>';
        }
        
        // Table
        if ($is_index) {
            $xsl .= '<table>';
            $xsl .= '<thead><tr><th style="width:50px">#</th><th>Sitemap</th><th style="width:200px">Last Modified</th></tr></thead>';
            $xsl .= '<tbody>';
            $xsl .= '<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">';
            $xsl .= '<tr>';
            $xsl .= '<td class="row-num"><xsl:value-of select="position()"/></td>';
            $xsl .= '<td><a><xsl:attribute name="href"><xsl:value-of select="sitemap:loc"/></xsl:attribute><xsl:value-of select="sitemap:loc"/></a></td>';
            $xsl .= '<td class="date"><xsl:if test="sitemap:lastmod"><xsl:value-of select="concat(substring(sitemap:lastmod,1,10),\' \',substring(sitemap:lastmod,12,5))"/></xsl:if></td>';
            $xsl .= '</tr>';
            $xsl .= '</xsl:for-each>';
            $xsl .= '</tbody></table>';
        } else {
            $xsl .= '<table>';
            $xsl .= '<thead><tr><th style="width:50px">#</th><th>URL</th><th style="width:100px">Priority</th><th style="width:100px">Frequency</th><th style="width:180px">Last Modified</th></tr></thead>';
            $xsl .= '<tbody>';
            $xsl .= '<xsl:for-each select="sitemap:urlset/sitemap:url">';
            $xsl .= '<tr>';
            $xsl .= '<td class="row-num"><xsl:value-of select="position()"/></td>';
            $xsl .= '<td><a><xsl:attribute name="href"><xsl:value-of select="sitemap:loc"/></xsl:attribute><xsl:value-of select="sitemap:loc"/></a></td>';
            $xsl .= '<td class="priority"><xsl:value-of select="sitemap:priority"/></td>';
            $xsl .= '<td class="freq"><xsl:value-of select="sitemap:changefreq"/></td>';
            $xsl .= '<td class="date"><xsl:if test="sitemap:lastmod"><xsl:value-of select="concat(substring(sitemap:lastmod,1,10),\' \',substring(sitemap:lastmod,12,5))"/></xsl:if></td>';
            $xsl .= '</tr>';
            $xsl .= '</xsl:for-each>';
            $xsl .= '</tbody></table>';
        }
        
        $xsl .= '<div class="footer">Generated by <a href="https://github.com/mbheramil/Smart-SEO-Fixer-v2">' . $plugin_name . '</a></div>';
        $xsl .= '</div></body></html>';
        
        $xsl .= '</xsl:template></xsl:stylesheet>';
        
        return $xsl;
    }
    
    /**
     * Sitemap header
     */
    private function sitemap_header() {
        $xsl_url = esc_url(home_url('/?ssf_sitemap=xsl'));
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="' . $xsl_url . '"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    }

    /**
     * Collect indexable image URLs for a post (featured image + in-content
     * images), so they can be listed in the sitemap for Google Images.
     * Capped to keep sitemap entries small.
     *
     * @param WP_Post $post
     * @return string[] Absolute image URLs (deduped, max 10).
     */
    private function get_post_images($post) {
        $images = [];

        $thumb_id = get_post_thumbnail_id($post->ID);
        if ($thumb_id) {
            $src = wp_get_attachment_image_url($thumb_id, 'full');
            if ($src) {
                $images[] = $src;
            }
        }

        if (!empty($post->post_content)) {
            $content = do_shortcode($post->post_content);
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
                foreach ($m[1] as $src) {
                    // Skip data URIs and tracking pixels.
                    if (stripos($src, 'data:') === 0) {
                        continue;
                    }
                    // Resolve protocol-relative / relative URLs to absolute.
                    if (strpos($src, '//') === 0) {
                        $src = (is_ssl() ? 'https:' : 'http:') . $src;
                    } elseif (strpos($src, 'http') !== 0) {
                        $src = home_url(ltrim($src, '/'));
                    }
                    $images[] = $src;
                }
            }
        }

        $images = array_values(array_unique(array_filter($images)));
        return array_slice($images, 0, 10);
    }
    
    /**
     * Sitemap index entry
     */
    private function sitemap_entry($loc, $lastmod = '') {
        $output = "  <sitemap>\n";
        $output .= "    <loc>" . esc_url($loc) . "</loc>\n";
        
        if (!empty($lastmod)) {
            $output .= "    <lastmod>{$lastmod}</lastmod>\n";
        }
        
        $output .= "  </sitemap>\n";
        
        return $output;
    }
    
    /**
     * URL entry
     */
    private function url_entry($loc, $lastmod = '', $changefreq = 'weekly', $priority = '0.5', $images = []) {
        // Normalize URL for consistency (trailing slashes, etc.)
        $loc = apply_filters('ssf_sitemap_url', $loc);

        // Enforce trailing slash consistency to match WordPress permalink structure
        $loc = $this->normalize_url_slash($loc);

        $output = "  <url>\n";
        $output .= "    <loc>" . esc_url($loc) . "</loc>\n";

        if (!empty($lastmod)) {
            $output .= "    <lastmod>{$lastmod}</lastmod>\n";
        }

        $output .= "    <changefreq>{$changefreq}</changefreq>\n";
        $output .= "    <priority>{$priority}</priority>\n";

        // Image entries (Google Images discovery).
        if (!empty($images) && is_array($images)) {
            foreach ($images as $img) {
                if (empty($img)) {
                    continue;
                }
                $output .= "    <image:image>\n";
                $output .= "      <image:loc>" . esc_url($img) . "</image:loc>\n";
                $output .= "    </image:image>\n";
            }
        }

        $output .= "  </url>\n";

        return $output;
    }
    
    /**
     * Deprecated: the Google and Bing sitemap "ping" endpoints were both
     * retired in 2023. Instant indexing now goes through IndexNow. Kept as a
     * thin delegate in case anything still calls it.
     *
     * @param int $post_id
     */
    public function ping_search_engines($post_id = 0) {
        if ($post_id && class_exists('SSF_IndexNow')) {
            $url = get_permalink($post_id);
            if ($url) {
                SSF_IndexNow::submit_url($url);
            }
        }
    }
    
    /**
     * Get sitemap URL
     */
    public function get_sitemap_url() {
        return home_url('/sitemap.xml');
    }
    
    /**
     * Normalize trailing slashes on a URL to match WordPress permalink settings
     */
    private function normalize_url_slash($url) {
        if (empty($url)) return $url;
        
        $parsed = wp_parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        // Don't modify URLs with file extensions (e.g., /sitemap.xml)
        if (preg_match('/\.\w{2,5}$/', $path)) {
            return $url;
        }
        
        $permalink_structure = get_option('permalink_structure', '');
        $uses_trailing_slash = !empty($permalink_structure) && substr($permalink_structure, -1) === '/';
        
        if ($uses_trailing_slash) {
            return trailingslashit($url);
        } else {
            return untrailingslashit($url);
        }
    }
    
    /**
     * Flush rewrite rules
     */
    public static function flush_rules() {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}

