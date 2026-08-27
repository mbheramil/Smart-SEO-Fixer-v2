<?php
/**
 * Search Console Fixer Class
 * 
 * Detects and fixes common Search Console issues:
 * - Trailing slash inconsistencies
 * - URL parameter canonicalization
 * - Duplicate content issues
 * - Redirect chains
 * - noindex conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Search_Console {
    
    /**
     * Site's preferred trailing slash setting
     */
    private $use_trailing_slash = true;
    
    /**
     * Constructor
     */
    /**
     * Cached home URL to avoid repeated option lookups
     */
    private $home_url_host = '';
    
    public function __construct() {
        // Wait for init to detect trailing slash (needs permalink_structure option)
        add_action('init', [$this, 'init_settings'], 1);
        
        // Lightweight frontend hooks only
        if (!is_admin()) {
            // Let WordPress handle trailing slash redirects natively via redirect_canonical().
            // We only hook in to strip tracking parameters from the redirect target.
            add_filter('redirect_canonical', [$this, 'filter_canonical_redirect'], 10, 2);
            
            // Strip UTM parameters from our canonical output only
            add_filter('ssf_canonical_url', [$this, 'strip_tracking_params']);
            
            // Remove default WordPress canonical (let meta-manager handle it).
            // Hooked on 'wp' (fires before wp_head) AND on 'template_redirect' as a
            // belt-and-suspenders fallback for edge cases (page builders, caching plugins)
            // where the 'wp' hook may not fire in the expected order.
            add_action('wp', [$this, 'remove_default_canonical']);
            add_action('template_redirect', [$this, 'remove_default_canonical'], 1);
            
            // Fix sitemap URLs to match canonical format
            add_filter('ssf_sitemap_url', [$this, 'normalize_sitemap_url']);
        }
        
        // Admin AJAX handlers — only register in admin context
        if (is_admin()) {
            add_action('wp_ajax_ssf_scan_url_issues', [$this, 'ajax_scan_url_issues']);
            add_action('wp_ajax_ssf_fix_url_issues', [$this, 'ajax_fix_url_issues']);
            add_action('wp_ajax_ssf_get_gsc_summary', [$this, 'ajax_get_gsc_summary']);
            add_action('wp_ajax_ssf_fix_indexability_issue', [$this, 'ajax_fix_indexability_issue']);
            add_action('wp_ajax_ssf_fix_orphaned_page', [$this, 'ajax_fix_orphaned_page']);
        }
    }
    
    /**
     * Initialize settings after WordPress is loaded
     */
    public function init_settings() {
        $this->use_trailing_slash = $this->detect_trailing_slash_preference();
        $this->home_url_host = wp_parse_url(home_url(), PHP_URL_HOST);
    }
    
    /**
     * Remove default WordPress canonical
     */
    public function remove_default_canonical() {
        remove_action('wp_head', 'rel_canonical');
    }
    
    /**
     * Detect if site uses trailing slashes in permalinks
     */
    private function detect_trailing_slash_preference() {
        $permalink_structure = get_option('permalink_structure');
        
        // If permalink structure ends with /, use trailing slashes
        if (!empty($permalink_structure)) {
            return substr($permalink_structure, -1) === '/';
        }
        
        // Default WordPress behavior uses trailing slashes
        return true;
    }
    
    /**
     * Normalize permalink to consistent format
     */
    public function normalize_permalink($permalink, $post = null) {
        if (empty($permalink)) {
            return $permalink;
        }
        
        // Don't modify external URLs
        if (strpos($permalink, home_url()) !== 0) {
            return $permalink;
        }
        
        // Parse URL
        $parsed = wp_parse_url($permalink);
        $path = $parsed['path'] ?? '';
        
        // Skip if it's a file (has extension)
        if (preg_match('/\.[a-zA-Z0-9]+$/', $path)) {
            return $permalink;
        }
        
        // Normalize trailing slash
        if ($this->use_trailing_slash) {
            if (!empty($path) && substr($path, -1) !== '/') {
                $path .= '/';
            }
        } else {
            $path = rtrim($path, '/');
            if (empty($path)) {
                $path = '/';
            }
        }
        
        // Rebuild URL without query params for canonical purposes
        $normalized = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port']) && $parsed['port'] != 80 && $parsed['port'] != 443) {
            $normalized .= ':' . $parsed['port'];
        }
        $normalized .= $path;
        
        // Keep query string if present (but canonical will strip tracking params)
        if (!empty($parsed['query'])) {
            $normalized .= '?' . $parsed['query'];
        }
        
        return $normalized;
    }
    
    /**
     * Normalize page link
     */
    public function normalize_page_link($link, $post_id) {
        return $this->normalize_permalink($link, get_post($post_id));
    }
    
    /**
     * Filter WordPress's native redirect_canonical to strip tracking params
     * 
     * WordPress already handles trailing slash normalization, www vs non-www,
     * and other URL canonicalization. We only need to strip UTM/tracking
     * parameters from the redirect target. This avoids redirect loops that
     * occur when a custom redirect function conflicts with WordPress's own.
     *
     * @param string $redirect_url The URL WordPress wants to redirect to
     * @param string $requested_url The originally requested URL
     * @return string|false Modified redirect URL or false to cancel redirect
     */
    public function filter_canonical_redirect($redirect_url, $requested_url) {
        if (empty($redirect_url)) {
            return $redirect_url;
        }
        
        // Strip tracking parameters from the redirect target
        $parsed = wp_parse_url($redirect_url);
        if (!empty($parsed['query'])) {
            $clean_query = $this->strip_tracking_params_from_query($parsed['query']);
            if ($clean_query !== $parsed['query']) {
                $base = strtok($redirect_url, '?');
                $redirect_url = !empty($clean_query) ? $base . '?' . $clean_query : $base;
            }
        }
        
        return $redirect_url;
    }
    
    /**
     * Strip tracking parameters from URL
     */
    public function strip_tracking_params($url) {
        if (empty($url)) {
            return $url;
        }
        
        $parsed = wp_parse_url($url);
        
        if (empty($parsed['query'])) {
            return $url;
        }
        
        $clean_query = $this->strip_tracking_params_from_query($parsed['query']);
        
        $clean_url = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port']) && $parsed['port'] != 80 && $parsed['port'] != 443) {
            $clean_url .= ':' . $parsed['port'];
        }
        $clean_url .= $parsed['path'] ?? '/';
        
        if (!empty($clean_query)) {
            $clean_url .= '?' . $clean_query;
        }
        
        return $clean_url;
    }
    
    /**
     * Strip tracking parameters from query string
     */
    private function strip_tracking_params_from_query($query) {
        if (empty($query)) {
            return '';
        }
        
        parse_str($query, $params);
        
        // Common tracking parameters to remove
        $tracking_params = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'utm_id', 'utm_source_platform', 'utm_creative_format',
            'fbclid', 'gclid', 'gclsrc', 'dclid', 'gbraid', 'wbraid',
            'msclkid', 'twclid', 'igshid', 'mc_cid', 'mc_eid',
            'ref', 'referrer', 'source', 'campaign',
            '_ga', '_gl', '_hsenc', '_hsmi', 'hsa_acc', 'hsa_cam', 'hsa_grp',
            'hsa_ad', 'hsa_src', 'hsa_tgt', 'hsa_kw', 'hsa_mt', 'hsa_net', 'hsa_ver'
        ];
        
        // Allow filtering of tracking params
        $tracking_params = apply_filters('ssf_tracking_params', $tracking_params);
        
        foreach ($tracking_params as $param) {
            unset($params[$param]);
        }
        
        return http_build_query($params);
    }
    
    /**
     * Normalize sitemap URLs
     */
    public function normalize_sitemap_url($url) {
        return $this->normalize_permalink($url);
    }
    
    /**
     * Comprehensive indexability audit — maps to all Google Search Console issue types
     * 
     * GSC Issue Types Covered:
     * 1. Page with redirect
     * 2. Excluded by 'noindex' tag
     * 3. Alternate page with proper canonical tag
     * 4. Duplicate without user-selected canonical
     * 5. Not found (404)
     * 6. Crawled - currently not indexed (thin content)
     * 7. Duplicate, Google chose different canonical than user
     * 8. Blocked by robots.txt
     * 9. Discovered - currently not indexed (missing SEO / orphaned)
     */
    public function scan_url_issues() {
        global $wpdb;
        
        $issues = [
            'noindex_conflict'     => [],
            'custom_canonical'     => [],
            'redirect_chains'      => [],
            'redirected_pages'     => [],
            'thin_content'         => [],
            'missing_seo'         => [],
            'duplicate_titles'     => [],
            'duplicate_descs'      => [],
            'blocked_by_robots'    => [],
            'orphaned_pages'       => [],
            'not_found_404'        => [],
        ];
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Get all published posts with content
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_type, post_title, post_content 
                FROM {$wpdb->posts} 
                WHERE post_status = 'publish' 
                AND post_type IN ($placeholders)
                ORDER BY post_date DESC",
                ...$post_types
            )
        );
        
        // Pre-load meta for all posts in one query (performance)
        $post_ids = wp_list_pluck($posts, 'ID');
        $meta_cache = [];
        if (!empty($post_ids)) {
            $ids_str = implode(',', array_map('intval', $post_ids));
            $all_meta = $wpdb->get_results(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                WHERE post_id IN ($ids_str) 
                AND meta_key IN ('_ssf_seo_title','_ssf_meta_description','_ssf_focus_keyword','_ssf_noindex','_ssf_canonical_url')"
            );
            foreach ($all_meta as $m) {
                $meta_cache[$m->post_id][$m->meta_key] = $m->meta_value;
            }
        }
        
        // Track titles and descriptions for duplicate detection
        $title_map = [];
        $desc_map = [];
        
        // Build internal link map for orphaned page detection
        $linked_ids = [];
        foreach ($posts as $post) {
            if (preg_match_all('/href=["\']([^"\']+)["\']/', $post->post_content, $matches)) {
                foreach ($matches[1] as $link) {
                    $link_post_id = url_to_postid($link);
                    if ($link_post_id > 0) {
                        $linked_ids[$link_post_id] = true;
                    }
                }
            }
        }
        
        // Parse robots.txt rules
        $robots_rules = $this->parse_robots_txt();
        
        // Get active redirects
        $redirects = get_option('ssf_redirects', []);
        $redirect_froms = [];
        foreach ($redirects as $r) {
            if (!empty($r['enabled'])) {
                $redirect_froms[trim($r['from'], '/')] = $r;
            }
        }
        
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            $parsed = wp_parse_url($permalink);
            $path = $parsed['path'] ?? '';
            $meta = $meta_cache[$post->ID] ?? [];
            
            $seo_title = $meta['_ssf_seo_title'] ?? '';
            $meta_desc = $meta['_ssf_meta_description'] ?? '';
            $noindex = $meta['_ssf_noindex'] ?? '';
            $canonical = $meta['_ssf_canonical_url'] ?? '';
            $word_count = str_word_count(strip_tags($post->post_content));
            
            // --- 1. Trailing slash consistency ---
            // Note: Our canonical tag and sitemap already enforce correct trailing slashes (v1.8.2+)
            // WordPress also auto-redirects. So these are informational only.
            
            // --- 2. Noindex conflict (noindex but should be indexed) ---
            if ($noindex) {
                $issues['noindex_conflict'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'issue' => __('Page has noindex tag — excluded from Google index', 'smart-seo-fixer'),
                    'fixable' => true,
                ];
            }
            
            // --- 3. Custom canonical (alternate page) ---
            if (!empty($canonical) && $canonical !== $permalink) {
                $issues['custom_canonical'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'canonical' => $canonical,
                    'issue' => __('Custom canonical points to different URL', 'smart-seo-fixer'),
                ];
            }
            
            // --- 4 & 7. Duplicate titles (causes "Duplicate without canonical" / "Google chose different canonical") ---
            $effective_title = !empty($seo_title) ? $seo_title : $post->post_title;
            if (!empty($effective_title)) {
                $title_key = strtolower(trim($effective_title));
                if (isset($title_map[$title_key])) {
                    $issues['duplicate_titles'][] = [
                        'post_id' => $post->ID,
                        'title' => $post->post_title,
                        'seo_title' => $effective_title,
                        'url' => $permalink,
                        'duplicate_of' => $title_map[$title_key]['post_id'],
                        'duplicate_title' => $title_map[$title_key]['title'],
                        'issue' => sprintf(__('Same title as "%s"', 'smart-seo-fixer'), $title_map[$title_key]['title']),
                        'fixable' => true,
                    ];
                } else {
                    $title_map[$title_key] = ['post_id' => $post->ID, 'title' => $post->post_title];
                }
            }
            
            // --- 4. Duplicate descriptions ---
            if (!empty($meta_desc)) {
                $desc_key = strtolower(trim($meta_desc));
                if (isset($desc_map[$desc_key])) {
                    $issues['duplicate_descs'][] = [
                        'post_id' => $post->ID,
                        'title' => $post->post_title,
                        'url' => $permalink,
                        'duplicate_of' => $desc_map[$desc_key]['post_id'],
                        'issue' => sprintf(__('Same description as "%s"', 'smart-seo-fixer'), $desc_map[$desc_key]['title']),
                        'fixable' => true,
                    ];
                } else {
                    $desc_map[$desc_key] = ['post_id' => $post->ID, 'title' => $post->post_title];
                }
            }
            
            // --- 6. Thin content (causes "Crawled - currently not indexed") ---
            if ($word_count < 300 && $post->post_type !== 'page') {
                $issues['thin_content'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'word_count' => $word_count,
                    'issue' => sprintf(__('%d words (Google prefers 300+)', 'smart-seo-fixer'), $word_count),
                ];
            }
            
            // --- 9. Missing SEO data (causes "Discovered - currently not indexed") ---
            if (empty($seo_title) || empty($meta_desc)) {
                $missing = [];
                if (empty($seo_title)) $missing[] = __('SEO title', 'smart-seo-fixer');
                if (empty($meta_desc)) $missing[] = __('meta description', 'smart-seo-fixer');
                
                $issues['missing_seo'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'missing' => $missing,
                    'issue' => sprintf(__('Missing: %s', 'smart-seo-fixer'), implode(', ', $missing)),
                    'fixable' => true,
                ];
            }
            
            // --- 8. Blocked by robots.txt ---
            if (!empty($path) && $this->is_blocked_by_robots($path, $robots_rules)) {
                $issues['blocked_by_robots'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'path' => $path,
                    'issue' => __('URL matches a Disallow rule in robots.txt', 'smart-seo-fixer'),
                ];
            }
            
            // --- 9. Orphaned pages (no internal links = hard for Google to discover) ---
            if (!isset($linked_ids[$post->ID]) && $post->post_type !== 'page') {
                $issues['orphaned_pages'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'issue' => __('No internal links point to this page', 'smart-seo-fixer'),
                ];
            }
            
            // --- 1. Check if published page has a redirect FROM it ---
            $post_path = trim($path, '/');
            if (isset($redirect_froms[$post_path])) {
                $issues['redirected_pages'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'redirect_to' => $redirect_froms[$post_path]['to'],
                    'issue' => sprintf(__('Published page has active redirect to %s', 'smart-seo-fixer'), $redirect_froms[$post_path]['to']),
                ];
            }
        }
        
        // --- Redirect chains ---
        foreach ($redirects as $redirect) {
            if (empty($redirect['enabled'])) continue;
            $to_path = trim(wp_parse_url($redirect['to'], PHP_URL_PATH) ?? '', '/');
            foreach ($redirects as $r2) {
                if (empty($r2['enabled'])) continue;
                if (trim($r2['from'], '/') === $to_path) {
                    $issues['redirect_chains'][] = [
                        'from' => $redirect['from'],
                        'to' => $redirect['to'],
                        'chain_to' => $r2['to'],
                        'issue' => __('Redirect chain detected', 'smart-seo-fixer'),
                    ];
                }
            }
        }
        
        // --- 5. Not found (404) log ---
        $top_404s = class_exists('SSF_Redirects') ? SSF_Redirects::get_404_entries(20) : [];
        foreach ($top_404s as $entry) {
            $issues['not_found_404'][] = [
                'url' => $entry['url'] ?? '',
                'hits' => $entry['hits'] ?? 1,
                'last_hit' => $entry['last_hit'] ?? '',
                'referrer' => $entry['referrer'] ?? '',
                'issue' => sprintf(__('404 error — %d hits', 'smart-seo-fixer'), $entry['hits'] ?? 1),
                'fixable' => true,
            ];
        }
        
        return $issues;
    }
    
    /**
     * Parse robots.txt and extract Disallow rules for all user-agents
     */
    private function parse_robots_txt() {
        $robots_url = home_url('/robots.txt');
        $response = wp_remote_get($robots_url, ['timeout' => 5, 'sslverify' => false]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $rules = [];
        $current_agent = '*';
        
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            
            if (preg_match('/^User-agent:\s*(.+)/i', $line, $m)) {
                $current_agent = trim($m[1]);
            } elseif (preg_match('/^Disallow:\s*(.+)/i', $line, $m)) {
                $path = trim($m[1]);
                if (!empty($path)) {
                    $rules[] = [
                        'agent' => $current_agent,
                        'path' => $path,
                    ];
                }
            }
        }
        
        return $rules;
    }
    
    /**
     * Check if a URL path is blocked by any robots.txt Disallow rule
     */
    private function is_blocked_by_robots($path, $rules) {
        foreach ($rules as $rule) {
            // Only check rules for all agents or Googlebot
            if ($rule['agent'] !== '*' && stripos($rule['agent'], 'googlebot') === false) {
                continue;
            }
            
            $disallow = $rule['path'];
            
            // Wildcard matching
            if (strpos($disallow, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($disallow, '/'));
                if (preg_match('/^' . $pattern . '/', $path)) {
                    return true;
                }
            } elseif (strpos($path, $disallow) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fix URL issues automatically
     */
    public function fix_url_issues($issue_type = 'all') {
        $fixed = [];
        
        switch ($issue_type) {
            case 'trailing_slash':
            case 'all':
                // Trailing slashes are auto-enforced by canonical tags, sitemap, and OG URLs (v1.8.2+)
                // WordPress also auto-redirects. No manual fix needed.
                $fixed[] = 'Trailing slashes are automatically enforced via canonical tags, sitemap URLs, and Open Graph tags. WordPress auto-redirects as well.';
                
                if ($issue_type !== 'all') break;
                // Fall through
                
            case 'redirect_chains':
                // Fix redirect chains by updating to final destination
                $redirects = get_option('ssf_redirects', []);
                $redirect_map = [];
                
                foreach ($redirects as $r) {
                    if (!empty($r['enabled'])) {
                        $redirect_map[trim($r['from'], '/')] = $r['to'];
                    }
                }
                
                $updated = false;
                foreach ($redirects as &$redirect) {
                    if (empty($redirect['enabled'])) continue;
                    
                    $final_to = $redirect['to'];
                    $visited = [$redirect['from']];
                    $iterations = 0;
                    
                    // Follow the chain to find final destination
                    while ($iterations < 10) {
                        $to_path = trim(wp_parse_url($final_to, PHP_URL_PATH), '/');
                        if (isset($redirect_map[$to_path]) && !in_array($to_path, $visited)) {
                            $final_to = $redirect_map[$to_path];
                            $visited[] = $to_path;
                            $iterations++;
                        } else {
                            break;
                        }
                    }
                    
                    if ($final_to !== $redirect['to']) {
                        $redirect['to'] = $final_to;
                        $redirect['note'] = ($redirect['note'] ?? '') . ' [Chain fixed]';
                        $updated = true;
                    }
                }
                
                if ($updated) {
                    update_option('ssf_redirects', $redirects);
                    $fixed[] = 'Redirect chains resolved';
                }
                
                if ($issue_type !== 'all') break;
                // Fall through
                
            case 'noindex_in_sitemap':
                // This is handled by the sitemap generator which excludes noindex pages
                $fixed[] = 'Sitemap automatically excludes noindex pages';
                break;
                
            case 'duplicate_titles':
                // Regenerate unique SEO titles for posts with duplicate titles
                $fixed = array_merge($fixed, $this->fix_duplicate_seo_field('_ssf_seo_title', 'title'));
                break;
                
            case 'duplicate_descs':
                // Regenerate unique meta descriptions for posts with duplicate descriptions
                $fixed = array_merge($fixed, $this->fix_duplicate_seo_field('_ssf_meta_description', 'description'));
                break;
                
            case 'missing_seo':
                // Generate missing SEO data via AI for all posts that lack it
                $fixed = array_merge($fixed, $this->fix_missing_seo_data());
                break;
        }
        
        return $fixed;
    }
    
    /**
     * Fix duplicate SEO field (title or description) using AI regeneration.
     * Keeps the first post's value, regenerates for the duplicates.
     */
    private function fix_duplicate_seo_field($meta_key, $field_label) {
        global $wpdb;
        $fixed = [];
        
        if (!class_exists('SSF_AI')) {
            return [__('AI module not available.', 'smart-seo-fixer')];
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            return [SSF_AI::not_configured_message()];
        }
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Find duplicate groups
        $duplicates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value, GROUP_CONCAT(pm.post_id ORDER BY pm.post_id ASC) AS post_ids
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = %s
                 AND pm.meta_value != ''
                 AND p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 GROUP BY pm.meta_value
                 HAVING COUNT(*) > 1
                 LIMIT 20",
                $meta_key, ...$post_types
            )
        );
        
        if (empty($duplicates)) {
            return [sprintf(__('No duplicate %s found.', 'smart-seo-fixer'), $field_label)];
        }
        
        if (class_exists('SSF_History')) {
            SSF_History::set_source('ai');
        }
        
        $regen_count = 0;
        
        foreach ($duplicates as $group) {
            $ids = array_map('intval', explode(',', $group->post_ids));
            // Keep the first post's value, regenerate for the rest
            array_shift($ids);
            
            foreach ($ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) continue;
                
                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

                if ($meta_key === '_ssf_seo_title') {
                    $new_value = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                } else {
                    $new_value = $openai->generate_meta_description($enriched, '', $focus_keyword);
                }
                
                if (!is_wp_error($new_value) && !empty(trim($new_value))) {
                    update_post_meta($post_id, $meta_key, sanitize_text_field(trim($new_value)));
                    $regen_count++;
                }
                
                // Cap at 10 regenerations per batch to avoid API rate limits
                if ($regen_count >= 10) break 2;
            }
        }
        
        $fixed[] = sprintf(
            __('Regenerated unique %s for %d posts.', 'smart-seo-fixer'),
            $field_label, $regen_count
        );
        
        return $fixed;
    }
    
    /**
     * Fix missing SEO data (title + description) for up to 10 posts.
     */
    private function fix_missing_seo_data() {
        global $wpdb;
        $fixed = [];
        
        if (!class_exists('SSF_AI')) {
            return [__('AI module not available.', 'smart-seo-fixer')];
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            return [SSF_AI::not_configured_message()];
        }
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID 
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm_t ON p.ID = pm_t.post_id AND pm_t.meta_key = '_ssf_seo_title'
                 LEFT JOIN {$wpdb->postmeta} pm_d ON p.ID = pm_d.post_id AND pm_d.meta_key = '_ssf_meta_description'
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 AND ((pm_t.meta_value IS NULL OR pm_t.meta_value = '')
                   OR (pm_d.meta_value IS NULL OR pm_d.meta_value = ''))
                 ORDER BY p.post_date DESC
                 LIMIT 10",
                ...$post_types
            )
        );
        
        if (empty($posts)) {
            return [__('All posts have SEO data.', 'smart-seo-fixer')];
        }
        
        if (class_exists('SSF_History')) {
            SSF_History::set_source('ai');
        }
        
        $gen_count = 0;
        
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
            if (str_word_count(strip_tags($enriched)) < 10) continue;

            $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
            
            $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
            if (empty($seo_title)) {
                $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                if (!is_wp_error($title) && !empty(trim($title))) {
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field(trim($title)));
                    $gen_count++;
                }
            }
            
            $meta_desc = get_post_meta($post_id, '_ssf_meta_description', true);
            if (empty($meta_desc)) {
                $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                if (!is_wp_error($desc) && !empty(trim($desc))) {
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field(trim($desc)));
                    $gen_count++;
                }
            }
        }
        
        $fixed[] = sprintf(__('Generated missing SEO data for %d fields.', 'smart-seo-fixer'), $gen_count);
        
        return $fixed;
    }
    
    /**
     * Get summary of potential GSC issues
     */
    public function get_gsc_summary() {
        global $wpdb;
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Count posts with redirects pointing to them
        $redirects = get_option('ssf_redirects', []);
        $redirect_count = count(array_filter($redirects, function($r) {
            return !empty($r['enabled']);
        }));
        
        // Count noindex pages
        $noindex_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND pm.meta_key = '_ssf_noindex'
                AND pm.meta_value = '1'",
                ...$post_types
            )
        );
        
        // Count pages with custom canonicals
        $custom_canonical_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND pm.meta_key = '_ssf_canonical_url'
                AND pm.meta_value != ''",
                ...$post_types
            )
        );
        
        // 404 log count (active, non-redirected entries from the 404 Monitor)
        $count_404 = 0;
        if (class_exists('SSF_404_Monitor')) {
            $stats_404 = SSF_404_Monitor::get_stats();
            $count_404 = intval($stats_404['total_active'] ?? 0);
        }
        
        // Count missing SEO
        $missing_seo = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_seo_title'
                WHERE p.post_status = 'publish'
                AND p.post_type IN ($placeholders)
                AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                ...$post_types
            )
        );
        
        // Count thin content (< 300 words is approximate via char count)
        $thin_content = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type IN ($placeholders)
                AND LENGTH(TRIM(post_content)) < 1500",
                ...$post_types
            )
        );
        
        return [
            'trailing_slash_mode' => $this->use_trailing_slash ? 'with slash' : 'without slash',
            'active_redirects' => $redirect_count,
            'noindex_pages' => intval($noindex_count),
            'custom_canonicals' => intval($custom_canonical_count),
            'tracked_404s' => $count_404,
            'missing_seo' => intval($missing_seo),
            'thin_content' => intval($thin_content),
        ];
    }
    
    // ========================================
    // AJAX Handlers
    // ========================================
    
    private function verify() {
        check_ajax_referer('ssf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
    }
    
    public function ajax_scan_url_issues() {
        $this->verify();
        
        $issues = $this->scan_url_issues();
        
        wp_send_json_success([
            'issues' => $issues,
            'total' => array_sum(array_map('count', $issues)),
        ]);
    }
    
    public function ajax_fix_url_issues() {
        $this->verify();
        
        $issue_type = sanitize_text_field($_POST['issue_type'] ?? 'all');
        $fixed = $this->fix_url_issues($issue_type);
        
        wp_send_json_success([
            'fixed' => $fixed,
            'message' => __('Issues fixed successfully.', 'smart-seo-fixer'),
        ]);
    }
    
    public function ajax_get_gsc_summary() {
        $this->verify();
        
        $summary = $this->get_gsc_summary();
        
        wp_send_json_success($summary);
    }
    
    /**
     * Fix a specific indexability issue
     */
    public function ajax_fix_indexability_issue() {
        $this->verify();
        
        $fix_type = sanitize_text_field($_POST['fix_type'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        $fixed = [];
        
        switch ($fix_type) {
            case 'remove_noindex':
                if ($post_id > 0) {
                    delete_post_meta($post_id, '_ssf_noindex');
                    $fixed[] = sprintf(__('Removed noindex from "%s"', 'smart-seo-fixer'), get_the_title($post_id));
                }
                break;
                
            case 'generate_seo':
                if ($post_id <= 0) {
                    wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
                }
                if (!class_exists('SSF_AI')) {
                    wp_send_json_error(['message' => __('AI module not available.', 'smart-seo-fixer')]);
                }
                $openai = SSF_AI::get();
                if (!$openai->is_configured()) {
                      wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
                }
                $post = get_post($post_id);
                if (!$post) {
                    wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
                }
                
                $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                $errors = [];
                $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

                // Generate focus keyword first (improves title/desc quality)
                if (empty($focus_keyword)) {
                    $keywords = $openai->suggest_keywords($enriched, $post->post_title);
                    if (!is_wp_error($keywords) && is_array($keywords) && !empty($keywords['primary'])) {
                        $kw = sanitize_text_field(trim($keywords['primary']));
                        if (!empty($kw)) {
                            update_post_meta($post_id, '_ssf_focus_keyword', $kw);
                            $focus_keyword = $kw;
                            $fixed[] = sprintf(__('Focus keyword: "%s"', 'smart-seo-fixer'), $kw);
                        }
                    } elseif (is_wp_error($keywords)) {
                        $errors[] = $keywords->get_error_message();
                    }
                }
                
                // Generate SEO title
                $seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
                if (empty($seo_title)) {
                    $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
                    if (!is_wp_error($title) && !empty(trim($title))) {
                        $clean = sanitize_text_field(trim($title));
                        if (!empty($clean)) {
                            update_post_meta($post_id, '_ssf_seo_title', $clean);
                            $fixed[] = sprintf(__('SEO title: "%s"', 'smart-seo-fixer'), $clean);
                        }
                    } elseif (is_wp_error($title)) {
                        $errors[] = __('Title: ', 'smart-seo-fixer') . $title->get_error_message();
                    }
                }
                
                // Generate meta description
                $meta_desc = get_post_meta($post_id, '_ssf_meta_description', true);
                if (empty($meta_desc)) {
                    $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
                    if (!is_wp_error($desc) && !empty(trim($desc))) {
                        $clean = sanitize_textarea_field(trim($desc));
                        if (!empty($clean)) {
                            update_post_meta($post_id, '_ssf_meta_description', $clean);
                            $fixed[] = __('Meta description generated', 'smart-seo-fixer');
                        }
                    } elseif (is_wp_error($desc)) {
                        $errors[] = __('Description: ', 'smart-seo-fixer') . $desc->get_error_message();
                    }
                }
                
                // If nothing was generated and there were errors, return error
                if (empty($fixed) && !empty($errors)) {
                    wp_send_json_error(['message' => implode('. ', $errors)]);
                }
                break;
                
            case 'generate_unique_title':
                if ($post_id > 0 && class_exists('SSF_AI')) {
                    $openai = SSF_AI::get();
                    if (!$openai->is_configured()) {
                        wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
                    }
                    $post = get_post($post_id);
                    if (!$post) {
                        wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
                    }
                    $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                    $current_seo_title = get_post_meta($post_id, '_ssf_seo_title', true);
                    
                    // Tell AI to generate a DIFFERENT title than the current one
                    $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                    $content = wp_trim_words(wp_strip_all_tags($enriched), 500);
                    $prompt = "You are an SEO expert. Generate a NEW, UNIQUE SEO title for this page.\n\n";
                    $prompt .= "CRITICAL: The title MUST be completely different from the current one. Do NOT reuse the same wording.\n\n";
                    $prompt .= "Requirements:\n- Maximum 60 characters\n- Include focus keyword naturally if provided\n- Make it compelling and click-worthy\n- Must be DIFFERENT from current title\n\n";
                    if (!empty($focus_keyword)) {
                        $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
                    }
                    $prompt .= "Current Title (DO NOT repeat this): " . (!empty($current_seo_title) ? $current_seo_title : $post->post_title) . "\n\n";
                    $prompt .= "Page Content:\n{$content}\n\n";
                    $prompt .= "Respond with ONLY the new unique title, nothing else.";
                    
                    $messages = [
                        ['role' => 'system', 'content' => 'You generate unique, SEO-optimized titles. Never repeat the existing title.'],
                        ['role' => 'user', 'content' => $prompt],
                    ];
                    
                    $title = $openai->request($messages, 100, 0.9);
                    
                    if (is_wp_error($title)) {
                        wp_send_json_error(['message' => $title->get_error_message()]);
                    }
                    
                    $title = trim(trim($title), '"\'');
                    
                    if (empty($title)) {
                        wp_send_json_error(['message' => __('AI returned empty title. Try again.', 'smart-seo-fixer')]);
                    }
                    
                    // Ensure it's actually different
                    if (strtolower($title) === strtolower($current_seo_title)) {
                        wp_send_json_error(['message' => __('AI generated the same title. Try again.', 'smart-seo-fixer')]);
                    }
                    
                    update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                    $fixed[] = sprintf(__('New title: "%s"', 'smart-seo-fixer'), $title);
                }
                break;
                
            case 'generate_unique_desc':
                if ($post_id > 0 && class_exists('SSF_AI')) {
                    $openai = SSF_AI::get();
                    if (!$openai->is_configured()) {
                        wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
                    }
                    $post = get_post($post_id);
                    if (!$post) {
                        wp_send_json_error(['message' => __('Post not found.', 'smart-seo-fixer')]);
                    }
                    $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
                    $current_desc = get_post_meta($post_id, '_ssf_meta_description', true);
                    
                    // Tell AI to generate a DIFFERENT description
                    $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;
                    $content = wp_trim_words(wp_strip_all_tags($enriched), 500);
                    $prompt = "You are an SEO expert. Generate a NEW, UNIQUE meta description for this page.\n\n";
                    $prompt .= "CRITICAL: The description MUST be completely different from the current one. Do NOT reuse the same wording.\n\n";
                    $prompt .= "Requirements:\n- Between 150-160 characters\n- Include focus keyword naturally if provided\n- Include a subtle call-to-action\n- Must be DIFFERENT from current description\n\n";
                    if (!empty($focus_keyword)) {
                        $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
                    }
                    if (!empty($current_desc)) {
                        $prompt .= "Current Description (DO NOT repeat this): {$current_desc}\n\n";
                    }
                    $prompt .= "Page Content:\n{$content}\n\n";
                    $prompt .= "Respond with ONLY the new unique meta description, nothing else.";
                    
                    $messages = [
                        ['role' => 'system', 'content' => 'You generate unique, SEO-optimized meta descriptions. Never repeat the existing description.'],
                        ['role' => 'user', 'content' => $prompt],
                    ];
                    
                    $desc = $openai->request($messages, 200, 0.9);
                    
                    if (is_wp_error($desc)) {
                        wp_send_json_error(['message' => $desc->get_error_message()]);
                    }
                    
                    $desc = trim(trim($desc), '"\'');
                    
                    if (empty($desc)) {
                        wp_send_json_error(['message' => __('AI returned empty description. Try again.', 'smart-seo-fixer')]);
                    }
                    
                    // Ensure it's actually different
                    if (strtolower($desc) === strtolower($current_desc)) {
                        wp_send_json_error(['message' => __('AI generated the same description. Try again.', 'smart-seo-fixer')]);
                    }
                    
                    update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                    $fixed[] = __('New unique description generated', 'smart-seo-fixer');
                }
                break;
                
            case 'fix_redirect_chains':
                $result = $this->fix_url_issues('redirect_chains');
                $fixed = $result;
                break;
                
            default:
                wp_send_json_error(['message' => __('Unknown fix type.', 'smart-seo-fixer')]);
        }
        
        wp_send_json_success([
            'fixed' => $fixed,
            'message' => !empty($fixed) ? implode('. ', $fixed) : __('No changes needed.', 'smart-seo-fixer'),
        ]);
    }
    
    /**
     * Fix an orphaned page by adding an internal link from a relevant post.
     * AI finds a natural anchor text phrase in existing content and converts it to a link.
     */
    public function ajax_fix_orphaned_page() {
        $this->verify();
        if (class_exists('SSF_History')) SSF_History::set_source('orphan_fix');
        
        $orphan_id = intval($_POST['post_id'] ?? 0);
        if ($orphan_id <= 0) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'smart-seo-fixer')]);
        }
        
        if (!class_exists('SSF_AI')) {
            wp_send_json_error(['message' => __('AI module not available.', 'smart-seo-fixer')]);
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
                      wp_send_json_error(['message' => SSF_AI::not_configured_message()]);
        }
        
        $orphan_post = get_post($orphan_id);
        if (!$orphan_post || $orphan_post->post_status !== 'publish') {
            wp_send_json_error(['message' => __('Post not found or not published.', 'smart-seo-fixer')]);
        }
        
        $orphan_url = get_permalink($orphan_id);
        $orphan_title = $orphan_post->post_title;
        $orphan_summary = wp_trim_words(wp_strip_all_tags(strip_shortcodes($orphan_post->post_content)), 50);
        
        global $wpdb;
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Find candidate posts to link FROM (exclude the orphan itself)
        // Prefer posts with similar content (shared words in title/content)
        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content, post_type
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type IN ($placeholders)
                AND ID != %d
                AND LENGTH(post_content) > 500
                ORDER BY post_date DESC
                LIMIT 50",
                ...array_merge($post_types, [$orphan_id])
            )
        );
        
        if (empty($candidates)) {
            wp_send_json_error(['message' => __('No candidate posts found for internal linking.', 'smart-seo-fixer')]);
        }
        
        // Score candidates by keyword relevance to the orphaned page
        $orphan_words = array_unique(array_filter(
            str_word_count(strtolower($orphan_title), 1),
            function($w) { return strlen($w) > 3; }
        ));
        
        $scored = [];
        foreach ($candidates as $c) {
            // Skip if already links to the orphan
            if (stripos($c->post_content, $orphan_url) !== false) {
                continue;
            }
            
            // Also check for the relative path
            $orphan_path = wp_parse_url($orphan_url, PHP_URL_PATH);
            if ($orphan_path && stripos($c->post_content, $orphan_path) !== false) {
                continue;
            }
            
            $c_text = strtolower($c->post_title . ' ' . wp_trim_words(wp_strip_all_tags($c->post_content), 200));
            $score = 0;
            foreach ($orphan_words as $word) {
                if (stripos($c_text, $word) !== false) {
                    $score++;
                }
            }
            $scored[] = ['post' => $c, 'score' => $score];
        }
        
        // Sort by relevance score descending
        usort($scored, function($a, $b) { return $b['score'] - $a['score']; });
        
        // Try top candidates with AI until we find a good placement
        $max_attempts = 5;
        $attempts = 0;
        $link_added = false;
        $result_message = '';
        
        foreach ($scored as $item) {
            if ($attempts >= $max_attempts) break;
            $attempts++;
            
            $candidate = $item['post'];
            
            $ai_result = $openai->find_internal_link_placement(
                $candidate->post_content,
                $orphan_title,
                $orphan_url,
                $orphan_summary
            );
            
            if (is_wp_error($ai_result)) {
                continue;
            }
            
            if (empty($ai_result['found']) || empty($ai_result['anchor_text'])) {
                continue;
            }
            
            $anchor = $ai_result['anchor_text'];
            
            // Verify the anchor text exists in the raw content (not inside an existing link)
            $content = $candidate->post_content;
            
            // Check if anchor text exists in content
            $pos = strpos($content, $anchor);
            if ($pos === false) {
                // Try case-insensitive
                $pos = stripos($content, $anchor);
                if ($pos !== false) {
                    // Use the actual case from the content
                    $anchor = substr($content, $pos, strlen($anchor));
                }
            }
            
            if ($pos === false) {
                continue;
            }
            
            // Ensure anchor text is NOT already inside an <a> tag
            // Check if there's an unclosed <a> tag before this position
            $before = substr($content, 0, $pos);
            $last_a_open = strrpos($before, '<a ');
            $last_a_close = strrpos($before, '</a>');
            
            if ($last_a_open !== false && ($last_a_close === false || $last_a_close < $last_a_open)) {
                // Anchor text is inside an existing link — skip
                continue;
            }
            
            // Build the replacement link
            $link_html = '<a href="' . esc_url($orphan_url) . '">' . $anchor . '</a>';
            
            // Replace only the FIRST occurrence of the anchor text
            $new_content = substr_replace($content, $link_html, $pos, strlen($anchor));
            
            // Track content change in history
            if (class_exists('SSF_History')) {
                SSF_History::record_content($candidate->ID, $content, $new_content, 'orphan_fix');
            }
            
            // Save the updated content
            $update_result = wp_update_post([
                'ID' => $candidate->ID,
                'post_content' => $new_content,
            ], true);
            
            if (is_wp_error($update_result)) {
                continue;
            }
            
            $link_added = true;
            $result_message = sprintf(
                __('Added internal link from "%1$s" → "%2$s" (anchor: "%3$s")', 'smart-seo-fixer'),
                $candidate->post_title,
                $orphan_title,
                $anchor
            );
            break;
        }
        
        // Fallback: if AI couldn't find a natural placement, link from blog or contact page
        if (!$link_added) {
            $fallback_post = $this->find_fallback_page();
            
            if ($fallback_post) {
                $content = $fallback_post->post_content;
                
                // Check if already linked
                if (stripos($content, $orphan_url) === false) {
                    // Find a natural phrase in the fallback page, or append a related links section
                    $ai_result = $openai->find_internal_link_placement(
                        $content,
                        $orphan_title,
                        $orphan_url,
                        $orphan_summary
                    );
                    
                    $fallback_linked = false;
                    
                    if (!is_wp_error($ai_result) && !empty($ai_result['found']) && !empty($ai_result['anchor_text'])) {
                        $anchor = $ai_result['anchor_text'];
                        $pos = strpos($content, $anchor);
                        if ($pos === false) {
                            $pos = stripos($content, $anchor);
                            if ($pos !== false) {
                                $anchor = substr($content, $pos, strlen($anchor));
                            }
                        }
                        
                        if ($pos !== false) {
                            // Verify not inside existing link
                            $before = substr($content, 0, $pos);
                            $last_a_open = strrpos($before, '<a ');
                            $last_a_close = strrpos($before, '</a>');
                            
                            if ($last_a_open === false || ($last_a_close !== false && $last_a_close > $last_a_open)) {
                                $link_html = '<a href="' . esc_url($orphan_url) . '">' . $anchor . '</a>';
                                $new_content = substr_replace($content, $link_html, $pos, strlen($anchor));
                                
                                if (class_exists('SSF_History')) {
                                    SSF_History::record_content($fallback_post->ID, $content, $new_content, 'orphan_fix');
                                }
                                
                                $update_result = wp_update_post([
                                    'ID' => $fallback_post->ID,
                                    'post_content' => $new_content,
                                ], true);
                                
                                if (!is_wp_error($update_result)) {
                                    $fallback_linked = true;
                                    $link_added = true;
                                    $result_message = sprintf(
                                        __('Added internal link from fallback page "%1$s" → "%2$s" (anchor: "%3$s")', 'smart-seo-fixer'),
                                        $fallback_post->post_title,
                                        $orphan_title,
                                        $anchor
                                    );
                                }
                            }
                        }
                    }
                    
                    if (!$fallback_linked) {
                        $result_message = sprintf(
                            __('Could not find a natural placement for a link to "%s". Consider adding a manual internal link.', 'smart-seo-fixer'),
                            $orphan_title
                        );
                    }
                } else {
                    $result_message = sprintf(
                        __('Fallback page "%s" already links to this post.', 'smart-seo-fixer'),
                        $fallback_post->post_title
                    );
                }
            } else {
                $result_message = sprintf(
                    __('Could not find a natural placement for a link to "%s". No fallback page available.', 'smart-seo-fixer'),
                    $orphan_title
                );
            }
        }

        // Last resort: force-append a "Related" link to the best candidate.
        // Handles location pages and other cases where no natural anchor exists in any candidate.
        if ( ! $link_added ) {
            $force_candidate = null;
            foreach ( $scored as $item ) {
                if ( stripos( $item['post']->post_content, $orphan_url ) === false ) {
                    $force_candidate = $item['post'];
                    break;
                }
            }
            if ( ! $force_candidate ) {
                foreach ( $candidates as $c ) {
                    if ( stripos( $c->post_content, $orphan_url ) === false ) {
                        $force_candidate = $c;
                        break;
                    }
                }
            }
            if ( $force_candidate ) {
                $fc_old = $force_candidate->post_content;
                $fc_new = $fc_old . "\n<!-- wp:paragraph -->\n<p>" . __( 'Related:', 'smart-seo-fixer' ) . ' <a href="' . esc_url( $orphan_url ) . '">' . esc_html( $orphan_title ) . "</a></p>\n<!-- /wp:paragraph -->";
                if ( class_exists( 'SSF_History' ) ) {
                    SSF_History::record_content( $force_candidate->ID, $fc_old, $fc_new, 'orphan_fix' );
                }
                $upd = wp_update_post( [ 'ID' => $force_candidate->ID, 'post_content' => $fc_new ], true );
                if ( ! is_wp_error( $upd ) ) {
                    $link_added     = true;
                    $result_message = sprintf(
                        __( 'Appended "Related" link on "%1$s" → "%2$s" (no natural anchor found in content)', 'smart-seo-fixer' ),
                        $force_candidate->post_title,
                        $orphan_title
                    );
                }
            }
        }

        // === STEP 2: Add outgoing internal links WITHIN the orphaned page's content ===
        // This ensures the page links TO other relevant pages (fixes "No internal links found" analysis)
        $outgoing_messages = [];
        $orphan_post = get_post($orphan_id); // Re-fetch in case it was modified
        $orphan_content = $orphan_post->post_content;
        
        // Check if the page already has internal links
        $has_internal_links = false;
        if (preg_match_all('/href=["\']([^"\']+)["\']/', $orphan_content, $link_matches)) {
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            foreach ($link_matches[1] as $href) {
                $link_host = wp_parse_url($href, PHP_URL_HOST);
                if ($link_host === $site_host || (empty($link_host) && strpos($href, '/') === 0)) {
                    $has_internal_links = true;
                    break;
                }
            }
        }
        
        if (!$has_internal_links) {
            // Find related pages to link TO from this orphaned page
            $related = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title, post_content, post_type
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type IN ($placeholders)
                    AND ID != %d
                    AND LENGTH(post_content) > 500
                    ORDER BY post_date DESC
                    LIMIT 30",
                    ...array_merge($post_types, [$orphan_id])
                )
            );
            
            // Score by relevance
            $related_scored = [];
            foreach ($related as $r) {
                $r_text = strtolower($r->post_title . ' ' . wp_trim_words(wp_strip_all_tags($r->post_content), 100));
                $score = 0;
                foreach ($orphan_words as $word) {
                    if (stripos($r_text, $word) !== false) {
                        $score++;
                    }
                }
                if ($score > 0) {
                    $related_scored[] = ['post' => $r, 'score' => $score];
                }
            }
            usort($related_scored, function($a, $b) { return $b['score'] - $a['score']; });
            
            // Try to add up to 3 outgoing links
            $outgoing_added = 0;
            $max_outgoing = 3;
            $out_attempts = 0;
            
            foreach ($related_scored as $rel_item) {
                if ($outgoing_added >= $max_outgoing || $out_attempts >= 8) break;
                $out_attempts++;
                
                $target = $rel_item['post'];
                $target_url = get_permalink($target->ID);
                
                // Skip if orphan page already links to this target
                if (stripos($orphan_content, $target_url) !== false) continue;
                $target_path = wp_parse_url($target_url, PHP_URL_PATH);
                if ($target_path && stripos($orphan_content, $target_path) !== false) continue;
                
                $target_summary = wp_trim_words(wp_strip_all_tags(strip_shortcodes($target->post_content)), 50);
                
                $ai_result = $openai->find_internal_link_placement(
                    $orphan_content,
                    $target->post_title,
                    $target_url,
                    $target_summary
                );
                
                if (is_wp_error($ai_result) || empty($ai_result['found']) || empty($ai_result['anchor_text'])) {
                    continue;
                }
                
                $anchor = $ai_result['anchor_text'];
                $pos = strpos($orphan_content, $anchor);
                if ($pos === false) {
                    $pos = stripos($orphan_content, $anchor);
                    if ($pos !== false) {
                        $anchor = substr($orphan_content, $pos, strlen($anchor));
                    }
                }
                if ($pos === false) continue;
                
                // Verify not inside an existing <a> tag
                $before = substr($orphan_content, 0, $pos);
                $last_a_open = strrpos($before, '<a ');
                $last_a_close = strrpos($before, '</a>');
                if ($last_a_open !== false && ($last_a_close === false || $last_a_close < $last_a_open)) {
                    continue;
                }
                
                $out_link = '<a href="' . esc_url($target_url) . '">' . $anchor . '</a>';
                $orphan_content = substr_replace($orphan_content, $out_link, $pos, strlen($anchor));
                $outgoing_added++;
                $outgoing_messages[] = sprintf('"%s"', $anchor);
            }
            
            if ($outgoing_added > 0) {
                // Track content change in history
                if (class_exists('SSF_History')) {
                    SSF_History::record_content($orphan_id, $orphan_post->post_content, $orphan_content, 'orphan_fix');
                }
                
                $update_result = wp_update_post([
                    'ID' => $orphan_id,
                    'post_content' => $orphan_content,
                ], true);
                
                if (!is_wp_error($update_result)) {
                    $out_msg = sprintf(
                        __(' + Added %d outgoing link(s) within page content: %s', 'smart-seo-fixer'),
                        $outgoing_added,
                        implode(', ', $outgoing_messages)
                    );
                    $result_message .= $out_msg;
                }
            }
        }
        
        if ($link_added || !empty($outgoing_messages)) {
            wp_send_json_success([
                'message' => $result_message,
                'linked' => true,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result_message,
                'linked' => false,
            ]);
        }
    }
    
    /**
     * Find a fallback page (blog index or contact page) for orphan linking
     */
    private function find_fallback_page() {
        // Try the blog/posts page
        $blog_page_id = get_option('page_for_posts');
        if ($blog_page_id) {
            $page = get_post($blog_page_id);
            if ($page && $page->post_status === 'publish' && strlen($page->post_content) > 100) {
                return $page;
            }
        }
        
        // Try to find a "Contact" or "About" page
        global $wpdb;
        $fallback = $wpdb->get_row(
            "SELECT ID, post_title, post_content, post_type
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'page'
            AND (
                LOWER(post_name) LIKE '%contact%'
                OR LOWER(post_name) LIKE '%about%'
                OR LOWER(post_name) LIKE '%blog%'
                OR LOWER(post_name) LIKE '%resources%'
            )
            AND LENGTH(post_content) > 100
            ORDER BY 
                CASE 
                    WHEN LOWER(post_name) LIKE '%blog%' THEN 1
                    WHEN LOWER(post_name) LIKE '%resources%' THEN 2
                    WHEN LOWER(post_name) LIKE '%about%' THEN 3
                    WHEN LOWER(post_name) LIKE '%contact%' THEN 4
                    ELSE 5
                END
            LIMIT 1"
        );
        
        return $fallback;
    }
}
