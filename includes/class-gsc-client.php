<?php
/**
 * Google Search Console API Client
 * 
 * Handles OAuth2 authentication, token management,
 * and all Google Search Console API interactions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_GSC_Client {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    private $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $token_url = 'https://oauth2.googleapis.com/token';
    private $api_base  = 'https://www.googleapis.com/webmasters/v3';
    private $inspection_api = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
    private $verification_api = 'https://www.googleapis.com/siteVerification/v1';
    
    private $scopes = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/webmasters',
        // Needed to create+verify Search Console properties on behalf of the user.
        'https://www.googleapis.com/auth/siteverification',
    ];
    
    public function __construct() {
        $this->client_id     = Smart_SEO_Fixer::get_option('gsc_client_id', '');
        $this->client_secret = Smart_SEO_Fixer::get_option('gsc_client_secret', '');
        $this->redirect_uri  = admin_url('admin.php?page=smart-seo-fixer-settings');
        
        // Handle OAuth callback
        add_action('admin_init', [$this, 'handle_oauth_callback']);

        // Output the site-verification meta tag on the front-end whenever one is
        // stored. The tag must remain present even after verification — Google
        // re-checks periodically and will revoke ownership if it disappears.
        add_action('wp_head', [$this, 'output_verification_meta'], 1);
    }
    
    /**
     * Check if OAuth credentials are configured
     */
    public function has_credentials() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }
    
    /**
     * Check if we have a valid access token (connected)
     */
    public function is_connected() {
        $tokens = $this->get_tokens();
        return !empty($tokens['access_token']);
    }
    
    /**
     * Get the OAuth2 authorization URL
     */
    public function get_auth_url() {
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce('ssf_gsc_oauth'),
        ];
        
        return $this->auth_url . '?' . http_build_query($params);
    }
    
    /**
     * Handle the OAuth callback from Google
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'smart-seo-fixer-settings') {
            return;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['state'], 'ssf_gsc_oauth')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $code = sanitize_text_field($_GET['code']);
        $result = $this->exchange_code($code);
        
        if (is_wp_error($result)) {
            set_transient('ssf_gsc_error', $result->get_error_message(), 60);
        } else {
            // Fetch site list and cache it
            $sites = $this->get_sites();
            if (!is_wp_error($sites) && !empty($sites)) {
                set_transient('ssf_gsc_sites_cache', $sites, DAY_IN_SECONDS);

                // Try to auto-match the current site
                $site_url = home_url('/');
                $host = wp_parse_url($site_url, PHP_URL_HOST);
                $matched = false;
                foreach ($sites as $site) {
                    $s = $site['siteUrl'];
                    if (rtrim($s, '/') === rtrim($site_url, '/')
                        || $s === $site_url
                        || strpos($site_url, rtrim($s, '/')) === 0
                        || $s === 'sc-domain:' . $host
                        || $s === 'sc-domain:' . preg_replace('/^www\./', '', $host)) {
                        Smart_SEO_Fixer::update_option('gsc_site_url', $s);
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    set_transient('ssf_gsc_success', __('Google Search Console connected successfully!', 'smart-seo-fixer'), 60);
                } else {
                    set_transient('ssf_gsc_success', __('Connected! Please select your site property below.', 'smart-seo-fixer'), 60);
                }
            } else {
                set_transient('ssf_gsc_success', __('Connected to Google! Please select your site property below.', 'smart-seo-fixer'), 60);
                if (class_exists('SSF_Logger')) {
                    $err_msg = is_wp_error($sites) ? $sites->get_error_message() : 'Empty site list';
                    SSF_Logger::warning('GSC connected but site list failed: ' . $err_msg, 'gsc');
                }
            }
        }
        
        wp_redirect(admin_url('admin.php?page=smart-seo-fixer-settings&ssf_gsc_connected=1'));
        exit;
    }
    
    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code($code) {
        $response = wp_remote_post($this->token_url, [
            'timeout' => 30,
            'body'    => [
                'code'          => $code,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri'  => $this->redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('gsc_auth_error', $body['error_description'] ?? $body['error']);
        }
        
        if (empty($body['access_token'])) {
            return new WP_Error('gsc_auth_error', __('No access token received.', 'smart-seo-fixer'));
        }
        
        $tokens = [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + ($body['expires_in'] ?? 3600),
            'token_type'    => $body['token_type'] ?? 'Bearer',
            'scope'         => $body['scope'] ?? '',
        ];
        
        $this->save_tokens($tokens);
        
        return true;
    }
    
    /**
     * Refresh the access token using the refresh token
     */
    public function refresh_access_token() {
        $tokens = $this->get_tokens();
        
        if (empty($tokens['refresh_token'])) {
            return new WP_Error('gsc_no_refresh', __('No refresh token available. Please reconnect.', 'smart-seo-fixer'));
        }
        
        $response = wp_remote_post($this->token_url, [
            'timeout' => 30,
            'body'    => [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $tokens['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            // If refresh fails, disconnect
            if ($body['error'] === 'invalid_grant') {
                $this->disconnect();
            }
            return new WP_Error('gsc_refresh_error', $body['error_description'] ?? $body['error']);
        }
        
        $tokens['access_token'] = $body['access_token'];
        $tokens['expires_at']   = time() + ($body['expires_in'] ?? 3600);
        if (!empty($body['refresh_token'])) {
            $tokens['refresh_token'] = $body['refresh_token'];
        }
        
        $this->save_tokens($tokens);
        
        return $tokens['access_token'];
    }
    
    /**
     * Get a valid access token (auto-refreshing if expired)
     */
    private function get_access_token() {
        $tokens = $this->get_tokens();
        
        if (empty($tokens['access_token'])) {
            return new WP_Error('gsc_not_connected', __('Not connected to Google Search Console.', 'smart-seo-fixer'));
        }
        
        // Refresh if expired (with 60s buffer)
        if (time() >= ($tokens['expires_at'] - 60)) {
            $result = $this->refresh_access_token();
            if (is_wp_error($result)) {
                return $result;
            }
            return $result;
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Make an authenticated API request
     */
    private function api_request($url, $method = 'GET', $body = null) {
        $access_token = $this->get_access_token();
        
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $that = $this;
        
        $make_request = function() use ($url, $method, $body, &$access_token, $that) {
            $args = [
                'method'  => $method,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ];
            
            if ($body !== null) {
                $args['body'] = wp_json_encode($body);
            } elseif ($method === 'PUT') {
                $args['body'] = '';
                $args['headers']['Content-Length'] = '0';
            }
            
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            $raw_body = wp_remote_retrieve_body($response);
            $data = json_decode($raw_body, true);
            
            if ($code === 401) {
                $new_token = $that->refresh_access_token();
                if (is_wp_error($new_token)) {
                    return $new_token;
                }
                $access_token = $new_token;
                $args['headers']['Authorization'] = 'Bearer ' . $new_token;
                $response = wp_remote_request($url, $args);
                if (is_wp_error($response)) {
                    return $response;
                }
                $code = wp_remote_retrieve_response_code($response);
                $raw_body = wp_remote_retrieve_body($response);
                $data = json_decode($raw_body, true);
            }
            
            // 2xx = success
            if ($code >= 200 && $code < 300) {
                return $data ?: ['success' => true];
            }
            
            // Extract the most useful error message
            $error_msg = '';
            if (!empty($data['error']['message'])) {
                $error_msg = $data['error']['message'];
            } elseif (!empty($data['error']['status'])) {
                $error_msg = $data['error']['status'];
            } elseif (!empty($raw_body)) {
                $error_msg = substr($raw_body, 0, 200);
            } else {
                $error_msg = sprintf(__('HTTP %d error from Google API.', 'smart-seo-fixer'), $code);
            }
            
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('GSC API error: ' . $error_msg, 'gsc', [
                    'url'    => $url,
                    'method' => $method,
                    'code'   => $code,
                ]);
            }
            
            return new WP_Error('gsc_api_error', $error_msg);
        };
        
        // Use rate limiter if available, otherwise call directly
        if (class_exists('SSF_Rate_Limiter')) {
            return SSF_Rate_Limiter::execute('gsc', $make_request);
        }
        
        return $make_request();
    }
    
    // ========================================================
    // Public API Methods
    // ========================================================
    
    /**
     * Get list of verified sites
     */
    public function get_sites() {
        $url = $this->api_base . '/sites';
        $result = $this->api_request($url);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $sites = [];
        if (!empty($result['siteEntry'])) {
            foreach ($result['siteEntry'] as $site) {
                $sites[] = [
                    'siteUrl'         => $site['siteUrl'],
                    'permissionLevel' => $site['permissionLevel'] ?? '',
                ];
            }
        }
        
        return $sites;
    }
    
    /**
     * Get the currently selected site URL
     */
    public function get_site_url() {
        return Smart_SEO_Fixer::get_option('gsc_site_url', '');
    }
    
    /**
     * Search Analytics query
     * 
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function get_search_analytics($params = []) {
        $site_url = $this->get_site_url();
        if (empty($site_url)) {
            return new WP_Error('gsc_no_site', __('No site selected. Please connect GSC first.', 'smart-seo-fixer'));
        }
        
        $defaults = [
            'startDate'  => date('Y-m-d', strtotime('-28 days')),
            'endDate'    => date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['date'],
            'rowLimit'   => 25000,
        ];
        
        $query = array_merge($defaults, $params);
        
        $url = $this->api_base . '/sites/' . urlencode($site_url) . '/searchAnalytics/query';
        
        return $this->api_request($url, 'POST', $query);
    }
    
    /**
     * Get search performance overview (totals)
     */
    public function get_performance_overview($days = 28) {
        $result = $this->get_search_analytics([
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['date'],
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $totals = [
            'clicks'      => 0,
            'impressions' => 0,
            'ctr'         => 0,
            'position'    => 0,
            'days'        => [],
        ];
        
        if (!empty($result['rows'])) {
            $position_sum = 0;
            foreach ($result['rows'] as $row) {
                $totals['clicks']      += $row['clicks'] ?? 0;
                $totals['impressions'] += $row['impressions'] ?? 0;
                $position_sum          += $row['position'] ?? 0;
                $totals['days'][] = [
                    'date'        => $row['keys'][0] ?? '',
                    'clicks'      => $row['clicks'] ?? 0,
                    'impressions' => $row['impressions'] ?? 0,
                    'ctr'         => round(($row['ctr'] ?? 0) * 100, 2),
                    'position'    => round($row['position'] ?? 0, 1),
                ];
            }
            $count = count($result['rows']);
            $totals['ctr']      = $totals['impressions'] > 0 ? round(($totals['clicks'] / $totals['impressions']) * 100, 2) : 0;
            $totals['position'] = $count > 0 ? round($position_sum / $count, 1) : 0;
        }
        
        return $totals;
    }
    
    /**
     * Get top queries (keywords)
     */
    public function get_top_queries($days = 28, $limit = 50) {
        return $this->get_search_analytics([
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['query'],
            'rowLimit'   => $limit,
        ]);
    }
    
    /**
     * Get top pages
     */
    public function get_top_pages($days = 28, $limit = 50) {
        return $this->get_search_analytics([
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-1 day')),
            'dimensions' => ['page'],
            'rowLimit'   => $limit,
        ]);
    }
    
    /**
     * URL Inspection — check if a URL is indexed
     */
    public function inspect_url($url) {
        $site_url = $this->get_site_url();
        if (empty($site_url)) {
            return new WP_Error('gsc_no_site', __('No site selected.', 'smart-seo-fixer'));
        }
        
        $body = [
            'inspectionUrl' => $url,
            'siteUrl'       => $site_url,
        ];
        
        return $this->api_request($this->inspection_api, 'POST', $body);
    }
    
    /**
     * Submit a sitemap
     */
    public function submit_sitemap($sitemap_url) {
        $site_url = $this->get_site_url();
        if (empty($site_url)) {
            return new WP_Error('gsc_no_site', __('No site selected.', 'smart-seo-fixer'));
        }
        
        $url = $this->api_base . '/sites/' . urlencode($site_url) . '/sitemaps/' . urlencode($sitemap_url);
        
        return $this->api_request($url, 'PUT');
    }
    
    /**
     * List sitemaps
     */
    public function get_sitemaps() {
        $site_url = $this->get_site_url();
        if (empty($site_url)) {
            return new WP_Error('gsc_no_site', __('No site selected.', 'smart-seo-fixer'));
        }
        
        $url = $this->api_base . '/sites/' . urlencode($site_url) . '/sitemaps';
        
        return $this->api_request($url);
    }
    
    /**
     * Get index status summary for the site
     */
    public function get_index_status_summary() {
        $pages = $this->get_top_pages(28, 1000);
        
        if (is_wp_error($pages)) {
            return $pages;
        }
        
        $indexed_urls = [];
        if (!empty($pages['rows'])) {
            foreach ($pages['rows'] as $row) {
                if (!empty($row['keys'][0])) {
                    $indexed_urls[] = $row['keys'][0];
                }
            }
        }
        
        return [
            'indexed_count' => count($indexed_urls),
            'urls'          => $indexed_urls,
        ];
    }
    
    // ========================================================
    // Token Storage
    // ========================================================
    
    /**
     * Get stored tokens
     */
    private function get_tokens() {
        $tokens = get_option('ssf_gsc_tokens', []);
        if (!is_array($tokens)) {
            return [];
        }
        return $tokens;
    }
    
    /**
     * Save tokens
     */
    private function save_tokens($tokens) {
        update_option('ssf_gsc_tokens', $tokens, false);
    }
    
    /**
     * Disconnect — clear all GSC data
     */
    public function disconnect() {
        delete_option('ssf_gsc_tokens');
        delete_option('ssf_gsc_verification_token');
        Smart_SEO_Fixer::update_option('gsc_site_url', '');
        delete_transient('ssf_gsc_performance');
        delete_transient('ssf_gsc_queries');
        delete_transient('ssf_gsc_pages');
        delete_transient('ssf_gsc_sites_cache');
    }
    
    /**
     * Get connected account info
     */
    public function get_account_info() {
        if (!$this->is_connected()) {
            return null;
        }
        
        $site_url = $this->get_site_url();
        $tokens = $this->get_tokens();
        
        return [
            'connected' => true,
            'site_url'  => $site_url,
            'expires'   => date('Y-m-d H:i:s', $tokens['expires_at'] ?? 0),
        ];
    }

    // ========================================================
    // Auto-Setup: create + verify a Search Console property
    // ========================================================

    /**
     * Return true if the connected OAuth token was granted the
     * siteverification scope. Existing connections predating this feature
     * will not have it and must reconnect.
     */
    public function has_siteverification_scope() {
        $tokens = $this->get_tokens();
        if (empty($tokens['scope'])) {
            // Older connections did not persist scope — fall back to a live
            // capability check the first time auto-setup is attempted.
            return true; // optimistic; actual API call will tell us
        }
        return strpos($tokens['scope'], 'siteverification') !== false;
    }

    /**
     * Output the saved google-site-verification meta tag on the front-end.
     * Hosted permanently (not just until verified) because Google re-checks
     * and will revoke ownership if the tag disappears.
     */
    public function output_verification_meta() {
        if (is_admin()) {
            return;
        }
        $token = get_option('ssf_gsc_verification_token', '');
        if (empty($token)) {
            return;
        }
        // Accept any of: raw token, "google-site-verification=xxx",
        // or a full <meta> tag — always emit canonical form.
        $content = self::extract_verification_content($token);
        if ($content === '') {
            return;
        }
        echo '<meta name="google-site-verification" content="' . esc_attr($content) . '" />' . "\n";
    }

    /**
     * Normalise whatever Google returned into just the attribute value.
     */
    private static function extract_verification_content($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        // Full <meta ... content="X" ...> form.
        if (stripos($raw, '<meta') !== false) {
            if (preg_match('/content\s*=\s*"([^"]+)"/i', $raw, $m)) {
                return $m[1];
            }
            if (preg_match("/content\s*=\s*'([^']+)'/i", $raw, $m)) {
                return $m[1];
            }
            return '';
        }
        // "google-site-verification=xxx" form.
        if (stripos($raw, 'google-site-verification=') === 0) {
            return substr($raw, strlen('google-site-verification='));
        }
        // Already just the value.
        return $raw;
    }

    /**
     * Resolve the homepage URL into a canonical URL-prefix property.
     * e.g. https://www.example.com -> https://www.example.com/
     * We intentionally DO NOT auto-generate sc-domain: properties because
     * those require DNS verification which the plugin cannot self-host.
     */
    public function get_property_url_for_site() {
        $url = home_url('/');
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        return $url;
    }

    /**
     * Ask Google for a verification token (META method).
     *
     * @param string $site_url URL-prefix property (must end with /)
     * @return array|WP_Error ['token' => 'content value to put in meta tag']
     */
    public function request_verification_token($site_url) {
        $endpoint = $this->verification_api . '/token';
        $body = [
            'verificationMethod' => 'META',
            'site' => [
                'type'       => 'SITE',
                'identifier' => $site_url,
            ],
        ];
        $result = $this->api_request($endpoint, 'POST', $body);
        if (is_wp_error($result)) {
            return $result;
        }
        if (empty($result['token'])) {
            return new WP_Error('gsc_no_token', __('Google did not return a verification token.', 'smart-seo-fixer'));
        }
        return $result;
    }

    /**
     * Ask Google to verify the site (META method).
     * The verification meta tag must already be live on the homepage.
     */
    public function verify_site($site_url) {
        $endpoint = $this->verification_api . '/webResource?verificationMethod=META';
        $body = [
            'site' => [
                'type'       => 'SITE',
                'identifier' => $site_url,
            ],
        ];
        return $this->api_request($endpoint, 'POST', $body);
    }

    /**
     * Check if the homepage currently serves the verification meta tag
     * that we expect. Called before asking Google to verify so we can
     * fail fast with a helpful message instead of hitting the API.
     */
    private function homepage_has_verification_tag($expected_content) {
        $response = wp_remote_get(home_url('/'), [
            'timeout'     => 15,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => [
                // Some hosts serve different HTML to bots; mimic a real browser.
                'User-Agent' => 'Mozilla/5.0 (compatible; SmartSEOFixer-Verifier/1.0)',
            ],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('gsc_fetch_home', $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('gsc_home_status', sprintf(
                __('Homepage returned HTTP %d — Google must be able to fetch it (200 OK) to verify ownership.', 'smart-seo-fixer'),
                $code
            ));
        }
        $html = wp_remote_retrieve_body($response);
        if (strpos($html, 'google-site-verification') === false) {
            return new WP_Error('gsc_tag_missing', __('The verification meta tag was not found on the homepage. Make sure no caching plugin is stripping it and that the site is public.', 'smart-seo-fixer'));
        }
        if (strpos($html, $expected_content) === false) {
            return new WP_Error('gsc_tag_stale', __('A google-site-verification tag is on the homepage but it is a different token than expected — likely a cached copy. Please purge your cache (Cloudflare, LiteSpeed, WP Rocket, etc.) and try again.', 'smart-seo-fixer'));
        }
        return true;
    }

    /**
     * Add a URL-prefix site to Search Console (requires prior verification).
     */
    public function add_site_to_search_console($site_url) {
        $url = $this->api_base . '/sites/' . rawurlencode($site_url);
        return $this->api_request($url, 'PUT');
    }

    /**
     * Orchestrator: create + verify + submit-sitemap in one call.
     *
     * Returns a structured log of each step so the UI can show clearly
     * what succeeded and what failed.
     */
    public function auto_setup_property() {
        $log = [
            'site_url' => '',
            'steps'    => [],
            'success'  => false,
            'message'  => '',
        ];

        if (!$this->is_connected()) {
            $log['message'] = __('Not connected to Google.', 'smart-seo-fixer');
            return $log;
        }

        if (!$this->has_siteverification_scope()) {
            $log['message'] = __('Your existing connection is missing the site-verification permission. Please disconnect and reconnect Google to re-grant permissions.', 'smart-seo-fixer');
            return $log;
        }

        $site_url = $this->get_property_url_for_site();
        $log['site_url'] = $site_url;

        // Refuse to try on non-public URLs — verification will always fail.
        $host = wp_parse_url($site_url, PHP_URL_HOST);
        if (empty($host)
            || $host === 'localhost'
            || strpos($host, '.local') !== false
            || strpos($host, '.test') !== false
            || filter_var($host, FILTER_VALIDATE_IP)
        ) {
            $log['message'] = sprintf(
                __('Auto-setup requires a public, internet-reachable domain. "%s" is not accessible to Google.', 'smart-seo-fixer'),
                $host
            );
            $log['steps'][] = ['name' => 'precheck_domain', 'success' => false, 'detail' => $log['message']];
            return $log;
        }
        $log['steps'][] = ['name' => 'precheck_domain', 'success' => true, 'detail' => $host];

        // ── Step 1: request a verification token ─────────────────────────
        $token_result = $this->request_verification_token($site_url);
        if (is_wp_error($token_result)) {
            // Missing scope shows up here with 403 "insufficientPermissions".
            $msg = $token_result->get_error_message();
            if (stripos($msg, 'insufficient') !== false || stripos($msg, 'scope') !== false) {
                $msg .= ' ' . __('Please disconnect and reconnect Google to grant verification permission.', 'smart-seo-fixer');
            }
            $log['message'] = $msg;
            $log['steps'][] = ['name' => 'request_token', 'success' => false, 'detail' => $msg];
            return $log;
        }
        $content = self::extract_verification_content($token_result['token']);
        if ($content === '') {
            $log['message'] = __('Could not parse the verification token returned by Google.', 'smart-seo-fixer');
            $log['steps'][] = ['name' => 'request_token', 'success' => false, 'detail' => $log['message']];
            return $log;
        }
        update_option('ssf_gsc_verification_token', $content, false);
        // Bust common page caches so the tag goes live immediately.
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        $log['steps'][] = ['name' => 'request_token', 'success' => true, 'detail' => __('Token received and saved to site header.', 'smart-seo-fixer')];

        // ── Step 2: confirm the homepage actually serves the tag ─────────
        $local_check = $this->homepage_has_verification_tag($content);
        if (is_wp_error($local_check)) {
            $log['message'] = $local_check->get_error_message();
            $log['steps'][] = ['name' => 'homepage_check', 'success' => false, 'detail' => $log['message']];
            return $log;
        }
        $log['steps'][] = ['name' => 'homepage_check', 'success' => true, 'detail' => __('Meta tag is live on the homepage.', 'smart-seo-fixer')];

        // ── Step 3: ask Google to verify ─────────────────────────────────
        $verify_result = $this->verify_site($site_url);
        if (is_wp_error($verify_result)) {
            $log['message'] = $verify_result->get_error_message();
            $log['steps'][] = ['name' => 'verify', 'success' => false, 'detail' => $log['message']];
            return $log;
        }
        $log['steps'][] = ['name' => 'verify', 'success' => true, 'detail' => __('Google confirmed ownership.', 'smart-seo-fixer')];

        // ── Step 4: add to Search Console ────────────────────────────────
        $add_result = $this->add_site_to_search_console($site_url);
        if (is_wp_error($add_result)) {
            // Most common non-fatal: "alreadyExists" style — treat as success.
            $msg = $add_result->get_error_message();
            if (stripos($msg, 'already') === false) {
                $log['message'] = $msg;
                $log['steps'][] = ['name' => 'add_to_gsc', 'success' => false, 'detail' => $msg];
                return $log;
            }
            $log['steps'][] = ['name' => 'add_to_gsc', 'success' => true, 'detail' => __('Property already existed in Search Console.', 'smart-seo-fixer')];
        } else {
            $log['steps'][] = ['name' => 'add_to_gsc', 'success' => true, 'detail' => __('Property added to Search Console.', 'smart-seo-fixer')];
        }

        // Persist the selected property immediately.
        Smart_SEO_Fixer::update_option('gsc_site_url', $site_url);
        delete_transient('ssf_gsc_sites_cache');

        // ── Step 5: submit the sitemap (best-effort, non-fatal) ──────────
        $sitemap_url = home_url('/sitemap.xml');
        $sitemap_result = $this->submit_sitemap($sitemap_url);
        if (is_wp_error($sitemap_result)) {
            $log['steps'][] = ['name' => 'submit_sitemap', 'success' => false, 'detail' => $sitemap_result->get_error_message()];
        } else {
            $log['steps'][] = ['name' => 'submit_sitemap', 'success' => true, 'detail' => sprintf(__('Sitemap submitted: %s', 'smart-seo-fixer'), $sitemap_url)];
        }

        $log['success'] = true;
        $log['message'] = __('Search Console property created and verified successfully.', 'smart-seo-fixer');
        return $log;
    }
}
