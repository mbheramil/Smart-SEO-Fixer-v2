<?php
/**
 * Redirect Manager Class
 * 
 * Handles 301/302 redirects, auto-detect slug changes, and 404 tracking.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Redirects {
    
    /**
     * Option key for storing redirects
     */
    const OPTION_KEY = 'ssf_redirects';
    
    /**
     * Constructor
     */
        public function __construct() {
        // Intercept requests to check for redirects (runs early)
        add_action('template_redirect', [$this, 'maybe_redirect'], 1);
        
        // Redirect attachment pages (runs before 404 logging)
        add_action('template_redirect', [$this, 'maybe_redirect_attachment'], 2);
        
        // Track slug changes
        add_action('post_updated', [$this, 'detect_slug_change'], 10, 3);
        
        // AJAX handlers
        add_action('wp_ajax_ssf_get_redirects', [$this, 'ajax_get_redirects']);
        add_action('wp_ajax_ssf_add_redirect', [$this, 'ajax_add_redirect']);
        add_action('wp_ajax_ssf_delete_redirect', [$this, 'ajax_delete_redirect']);
        add_action('wp_ajax_ssf_toggle_redirect', [$this, 'ajax_toggle_redirect']);
        add_action('wp_ajax_ssf_get_404_log', [$this, 'ajax_get_404_log']);
        add_action('wp_ajax_ssf_clear_404_log', [$this, 'ajax_clear_404_log']);

        // 404 logging is handled by SSF_404_Monitor (DB-backed). The old
        // option-based logger here double-logged every 404 and wrote a
        // growing array to wp_options on each hit.
    }
    
    /**
     * Check if current request matches a redirect rule
     */
    public function maybe_redirect() {
        if (is_admin()) return;

        $request_path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $redirects = $this->get_redirects();

        foreach ($redirects as $redirect) {
            if (empty($redirect['enabled'])) continue;

            // Normalize "From": accept either a relative path (/old/) or a full
            // URL (https://site.com/old/) — use the path part either way.
            $from = (string) ($redirect['from'] ?? '');
            if (strpos($from, '://') !== false) {
                $from = (string) wp_parse_url($from, PHP_URL_PATH);
            }
            $from = trim($from, '/');
            if ($from === '') continue;

            $to   = (string) ($redirect['to'] ?? '');
            $code = intval($redirect['type'] ?? 301);

            // Exact match
            if ($from === $request_path) {
                wp_redirect($to, $code);
                exit;
            }

            // Wildcard match (e.g., /old-path/* → /new-path/)
            if (substr($from, -1) === '*') {
                $prefix = rtrim($from, '*');
                if ($prefix === '' || strpos($request_path, $prefix) === 0) {
                    // Path-preserving: when the destination ends with '/' or '*',
                    // append the part of the request matched by the wildcard so the
                    // rest of the path (e.g. a filename) carries across. This is
                    // what makes "move a whole folder / offloaded uploads to a CDN"
                    // work with a single rule, e.g.
                    //   /wp-content/uploads/*  →  https://cdn.example/wp-content/uploads/
                    // Otherwise, redirect to the fixed destination URL.
                    if ($to !== '' && (substr($to, -1) === '/' || substr($to, -1) === '*')) {
                        $suffix = ltrim(substr($request_path, strlen(rtrim($prefix, '/'))), '/');
                        $to = rtrim(rtrim($to, '*'), '/') . '/' . $suffix;
                    }
                    wp_redirect($to, $code);
                    exit;
                }
            }
        }
    }
    
        /**
     * Redirect WordPress attachment pages (media pages) to parent post or file
     *
     * Attachment pages are auto-created for every media upload and typically
     * show a single image on a blog-like template — bad for SEO.
     * Setting: ssf_redirect_attachments = 'parent' | 'file' | '' (disabled)
     */
    public function maybe_redirect_attachment() {
        if ( ! is_attachment() ) {
            return;
        }

        $mode = Smart_SEO_Fixer::get_option( 'redirect_attachments', '' );

        if ( empty( $mode ) ) {
            return; // Feature disabled
        }

        $attachment = get_post();
        if ( ! $attachment ) {
            return;
        }

        $redirect_url = '';

        if ( $mode === 'parent' ) {
            // Redirect to the parent post/page the media was attached to
            if ( $attachment->post_parent && get_post_status( $attachment->post_parent ) === 'publish' ) {
                $redirect_url = get_permalink( $attachment->post_parent );
            } else {
                // No parent or parent not published — fall back to homepage
                $redirect_url = home_url( '/' );
            }
        } elseif ( $mode === 'file' ) {
            // Redirect to the actual media file URL (the image/PDF itself)
            $file_url = wp_get_attachment_url( $attachment->ID );
            if ( $file_url ) {
                $redirect_url = $file_url;
            }
        }

        if ( ! empty( $redirect_url ) ) {
            wp_redirect( $redirect_url, 301 );
            exit;
        }
    }

    /**
     * Detect when a post slug changes and auto-create a redirect
     */
    public function detect_slug_change($post_id, $post_after, $post_before) {
        // Only for published posts
        if ($post_after->post_status !== 'publish') return;
        if ($post_before->post_status !== 'publish') return;
        
        // Check if slug actually changed
        if ($post_before->post_name === $post_after->post_name) return;
        
        // Build old and new URLs. Only swap the trailing path segment — a
        // blind str_replace over the whole permalink corrupts the URL when
        // the slug string also appears earlier in the path (e.g. a CPT
        // rewrite base or parent page with the same name).
        $new_url  = get_permalink($post_id);
        $new_path = wp_parse_url($new_url, PHP_URL_PATH);
        $new_path = $new_path !== null ? $new_path : '/';

        $pattern = '#/' . preg_quote($post_after->post_name, '#') . '(/?)$#';
        if (!preg_match($pattern, $new_path)) {
            return; // Permalink structure doesn't end with the slug — can't infer the old URL safely.
        }
        $old_path = preg_replace($pattern, '/' . $post_before->post_name . '$1', $new_path);

        if ($old_path === $new_path) return;
        
        // Don't create duplicate redirects
        $redirects = $this->get_redirects();
        foreach ($redirects as $r) {
            if (trim($r['from'], '/') === trim($old_path, '/')) {
                return; // Already exists
            }
        }
        
        // Auto-add redirect
        $this->add_redirect([
            'from' => $old_path,
            'to' => $new_url,
            'type' => 301,
            'enabled' => true,
            'auto' => true,
            'note' => sprintf(
                __('Auto-created: "%s" slug changed', 'smart-seo-fixer'),
                $post_after->post_title
            ),
            'created' => current_time('mysql'),
        ]);
    }
    
    /**
     * Read the 404 log from the DB-backed monitor in the legacy array shape
     * (url / hits / last_hit / referer) still used by the Redirects page tab
     * and the Search Console site-issues scan.
     *
     * @param int $limit Max entries to return (sorted by hit count desc).
     * @return array[]
     */
    public static function get_404_entries($limit = 200) {
        if (!class_exists('SSF_404_Monitor')) {
            return [];
        }

        $result = SSF_404_Monitor::query([
            'page'     => 1,
            'per_page' => max(1, intval($limit)),
            'status'   => 'active',
            'order_by' => 'hit_count',
            'order'    => 'DESC',
        ]);

        $entries = [];
        foreach ((array) ($result['items'] ?? []) as $row) {
            $entries[] = [
                'url'       => $row->url,
                'referer'   => $row->referrer,
                'referrer'  => $row->referrer,
                'hits'      => intval($row->hit_count),
                'first_hit' => $row->first_hit,
                'last_hit'  => $row->last_hit,
            ];
        }

        return $entries;
    }

    /**
     * Get all redirects
     */
    public function get_redirects() {
        return get_option(self::OPTION_KEY, []);
    }
    
    /**
     * Add a redirect
     */
    public function add_redirect($redirect) {
        $redirects = $this->get_redirects();
        $redirect['id'] = uniqid('r_');
        $redirect['created'] = $redirect['created'] ?? current_time('mysql');
        $redirect['enabled'] = $redirect['enabled'] ?? true;
        $redirects[] = $redirect;
        update_option(self::OPTION_KEY, $redirects);
        return $redirect['id'];
    }
    
    /**
     * Delete a redirect by ID
     */
    public function delete_redirect($id) {
        $redirects = $this->get_redirects();
        $redirects = array_filter($redirects, function($r) use ($id) {
            return ($r['id'] ?? '') !== $id;
        });
        update_option(self::OPTION_KEY, array_values($redirects));
    }
    
    /**
     * Toggle a redirect on/off
     */
    public function toggle_redirect($id) {
        $redirects = $this->get_redirects();
        foreach ($redirects as &$r) {
            if (($r['id'] ?? '') === $id) {
                $r['enabled'] = !$r['enabled'];
                break;
            }
        }
        update_option(self::OPTION_KEY, $redirects);
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
    
    public function ajax_get_redirects() {
        $this->verify();
        wp_send_json_success([
            'redirects' => $this->get_redirects(),
            'total' => count($this->get_redirects()),
        ]);
    }
    
    public function ajax_add_redirect() {
        $this->verify();
        
        $from = sanitize_text_field($_POST['from'] ?? '');
        $to = esc_url_raw($_POST['to'] ?? '');
        $type = intval($_POST['redirect_type'] ?? 301);
        $note = sanitize_text_field($_POST['note'] ?? '');
        
        if (empty($from) || empty($to)) {
            wp_send_json_error(['message' => __('Both "From" and "To" URLs are required.', 'smart-seo-fixer')]);
        }
        
        if (!in_array($type, [301, 302, 307])) {
            $type = 301;
        }
        
        // Check for duplicates
        $existing = $this->get_redirects();
        foreach ($existing as $r) {
            if (trim($r['from'], '/') === trim($from, '/')) {
                wp_send_json_error(['message' => __('A redirect for this URL already exists.', 'smart-seo-fixer')]);
            }
        }
        
        $id = $this->add_redirect([
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'note' => $note,
            'auto' => false,
        ]);
        
        wp_send_json_success(['message' => __('Redirect added.', 'smart-seo-fixer'), 'id' => $id]);
    }
    
    public function ajax_delete_redirect() {
        $this->verify();
        $id = sanitize_text_field($_POST['redirect_id'] ?? '');
        if (empty($id)) {
            wp_send_json_error(['message' => __('Invalid redirect ID.', 'smart-seo-fixer')]);
        }
        $this->delete_redirect($id);
        wp_send_json_success(['message' => __('Redirect deleted.', 'smart-seo-fixer')]);
    }
    
    public function ajax_toggle_redirect() {
        $this->verify();
        $id = sanitize_text_field($_POST['redirect_id'] ?? '');
        if (empty($id)) {
            wp_send_json_error(['message' => __('Invalid redirect ID.', 'smart-seo-fixer')]);
        }
        $this->toggle_redirect($id);
        wp_send_json_success(['message' => __('Redirect toggled.', 'smart-seo-fixer')]);
    }
    
    public function ajax_get_404_log() {
        $this->verify();
        $log = self::get_404_entries(200);

        // Flag each entry that's now covered by an active redirect, so the UI
        // can show which 404s have already been resolved.
        foreach ($log as &$entry) {
            $entry['redirected'] = $this->url_has_active_redirect($entry['url'] ?? '');
        }
        unset($entry);

        wp_send_json_success(['log' => $log, 'total' => count($log)]);
    }

    /**
     * Whether a given request path/URL is already covered by an active
     * redirect rule. Mirrors the matching logic in maybe_redirect() (exact +
     * path-preserving wildcard, with full-URL "From" normalization) so the
     * 404 log can accurately show "Redirected" after the user adds a rule.
     *
     * @param string $url  A logged 404 URL or path.
     * @return bool
     */
    public function url_has_active_redirect($url) {
        if (empty($url)) {
            return false;
        }

        $request_path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        if ($request_path === '') {
            return false;
        }

        foreach ($this->get_redirects() as $redirect) {
            if (empty($redirect['enabled'])) {
                continue;
            }

            $from = (string) ($redirect['from'] ?? '');
            if (strpos($from, '://') !== false) {
                $from = (string) wp_parse_url($from, PHP_URL_PATH);
            }
            $from = trim($from, '/');
            if ($from === '') {
                continue;
            }

            if ($from === $request_path) {
                return true;
            }

            if (substr($from, -1) === '*') {
                $prefix = rtrim($from, '*');
                if ($prefix === '' || strpos($request_path, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ajax_clear_404_log() {
        $this->verify();
        if (class_exists('SSF_404_Monitor')) {
            SSF_404_Monitor::clear_all();
        }
        delete_option('ssf_404_log'); // Remove the legacy option-based log too.
        wp_send_json_success(['message' => __('404 log cleared.', 'smart-seo-fixer')]);
    }
}
