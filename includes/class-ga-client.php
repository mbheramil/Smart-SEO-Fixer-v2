<?php
/**
 * Google Analytics 4 (GA4) API Client
 *
 * Handles OAuth2 for GA4, property/stream auto-setup via the
 * Google Analytics Admin API, data reporting via the
 * Google Analytics Data API, and gtag.js injection on the front-end.
 *
 * Reuses the Google OAuth client ID/secret that's already configured for
 * Search Console (stored under gsc_client_id / gsc_client_secret) — they
 * are standard Google OAuth credentials; only the scopes differ.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_GA_Client {

    private $client_id;
    private $client_secret;
    private $redirect_uri;

    private $auth_url   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $token_url  = 'https://oauth2.googleapis.com/token';
    private $admin_api  = 'https://analyticsadmin.googleapis.com/v1beta';
    private $data_api   = 'https://analyticsdata.googleapis.com/v1beta';

    private $scopes = [
        'https://www.googleapis.com/auth/analytics.edit',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    const TOKENS_OPTION        = 'ssf_ga_tokens';
    const ACCOUNT_OPTION       = 'ssf_ga_account_id';
    const PROPERTY_OPTION      = 'ssf_ga_property_id';
    const MEASUREMENT_ID_OPT   = 'ssf_ga_measurement_id';
    const STREAM_OPTION        = 'ssf_ga_stream_id';
    const AUTO_TAG_OPTION      = 'ssf_ga_auto_tag'; // bool — inject gtag.js
    const OAUTH_STATE          = 'ssf_ga_oauth_state';

    public function __construct() {
        // Reuse the same Google OAuth client credentials configured for GSC.
        $this->client_id     = Smart_SEO_Fixer::get_option('gsc_client_id', '');
        $this->client_secret = Smart_SEO_Fixer::get_option('gsc_client_secret', '');
        $this->redirect_uri  = admin_url('admin.php?page=smart-seo-fixer-settings');

        // OAuth callback
        add_action('admin_init', [$this, 'handle_oauth_callback']);

        // Inject gtag.js snippet on public pages when a measurement ID is set
        add_action('wp_head', [$this, 'output_gtag'], 2);
    }

    /* -------------------- Status helpers -------------------- */

    public function has_credentials() {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    public function is_connected() {
        $tokens = $this->get_tokens();
        return !empty($tokens['access_token']);
    }

    public function get_measurement_id() {
        return trim((string) get_option(self::MEASUREMENT_ID_OPT, ''));
    }

    public function get_property_id() {
        // Stored as "properties/123456789" (full resource name)
        return trim((string) get_option(self::PROPERTY_OPTION, ''));
    }

    public function get_property_numeric_id() {
        $prop = $this->get_property_id();
        if (preg_match('#^properties/(\d+)$#', $prop, $m)) {
            return $m[1];
        }
        return '';
    }

    /* -------------------- OAuth -------------------- */

    public function get_auth_url() {
        $state = wp_create_nonce('ssf_ga_oauth');
        // Prefix with "ga:" so our callback can distinguish from GSC flow.
        $state = 'ga_' . $state;
        set_transient(self::OAUTH_STATE, $state, 15 * MINUTE_IN_SECONDS);

        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ];

        return $this->auth_url . '?' . http_build_query($params);
    }

    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'smart-seo-fixer-settings') {
            return;
        }
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }

        $state = sanitize_text_field($_GET['state']);

        // Only handle the GA flow — GSC uses its own state format.
        if (strpos($state, 'ga_') !== 0) {
            return;
        }

        $expected = get_transient(self::OAUTH_STATE);
        if (!$expected || !hash_equals($expected, $state)) {
            return;
        }
        delete_transient(self::OAUTH_STATE);

        if (!current_user_can('manage_options')) {
            return;
        }

        $code   = sanitize_text_field($_GET['code']);
        $result = $this->exchange_code($code);

        if (is_wp_error($result)) {
            set_transient('ssf_ga_error', $result->get_error_message(), 60);
        } else {
            set_transient('ssf_ga_success', __('Google Analytics connected successfully!', 'smart-seo-fixer'), 60);
        }

        wp_redirect(admin_url('admin.php?page=smart-seo-fixer-settings&ssf_ga_connected=1'));
        exit;
    }

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
            return new WP_Error('ga_auth_error', $body['error_description'] ?? $body['error']);
        }
        if (empty($body['access_token'])) {
            return new WP_Error('ga_auth_error', __('No access token received.', 'smart-seo-fixer'));
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

    public function refresh_access_token() {
        $tokens = $this->get_tokens();
        if (empty($tokens['refresh_token'])) {
            return new WP_Error('ga_no_refresh', __('No refresh token. Please reconnect Google Analytics.', 'smart-seo-fixer'));
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
            if ($body['error'] === 'invalid_grant') {
                $this->disconnect(false);
            }
            return new WP_Error('ga_refresh_error', $body['error_description'] ?? $body['error']);
        }

        $tokens['access_token'] = $body['access_token'];
        $tokens['expires_at']   = time() + ($body['expires_in'] ?? 3600);
        if (!empty($body['refresh_token'])) {
            $tokens['refresh_token'] = $body['refresh_token'];
        }

        $this->save_tokens($tokens);
        return $tokens['access_token'];
    }

    private function get_access_token() {
        $tokens = $this->get_tokens();
        if (empty($tokens['access_token'])) {
            return new WP_Error('ga_not_connected', __('Not connected to Google Analytics.', 'smart-seo-fixer'));
        }
        if (time() >= ($tokens['expires_at'] - 60)) {
            return $this->refresh_access_token();
        }
        return $tokens['access_token'];
    }

    private function get_tokens() {
        $tokens = get_option(self::TOKENS_OPTION, []);
        return is_array($tokens) ? $tokens : [];
    }

    private function save_tokens($tokens) {
        update_option(self::TOKENS_OPTION, $tokens, false);
    }

    public function disconnect($clear_property = true) {
        delete_option(self::TOKENS_OPTION);
        if ($clear_property) {
            delete_option(self::ACCOUNT_OPTION);
            delete_option(self::PROPERTY_OPTION);
            delete_option(self::MEASUREMENT_ID_OPT);
            delete_option(self::STREAM_OPTION);
        }
        return true;
    }

    /* -------------------- HTTP helper -------------------- */

    private function request($method, $url, $body = null) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code    = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : ('HTTP ' . $code);
            $reason = '';
            if (!empty($decoded['error']['status'])) {
                $reason = $decoded['error']['status'];
            } elseif (!empty($decoded['error']['details'][0]['reason'])) {
                $reason = $decoded['error']['details'][0]['reason'];
            }
            return new WP_Error('ga_api_error', $msg, ['status' => $code, 'reason' => $reason]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /* -------------------- Admin API: accounts, properties, streams -------------------- */

    /**
     * List account summaries (accounts + their existing properties).
     * Helpful for the UI when user has existing GA4 setup.
     */
    public function list_account_summaries() {
        $result = $this->request('GET', $this->admin_api . '/accountSummaries');
        if (is_wp_error($result)) {
            return $result;
        }
        return $result['accountSummaries'] ?? [];
    }

    /**
     * List all GA4 properties the user has access to, flattened for a picker.
     *
     * For each property we also try to fetch the first web data stream's
     * Measurement ID so the UI can install the tracking snippet on save.
     * Accounts the user can read but not write still appear — selecting them
     * enables reporting-only use (no auto-property creation).
     *
     * @return array|WP_Error List of [
     *   'property'       => 'properties/123',
     *   'property_name'  => 'Example Site',
     *   'account'        => 'accounts/456',
     *   'account_name'   => 'My Agency',
     *   'measurement_id' => 'G-XXXXXXXXXX' (optional, may be empty),
     *   'stream'         => 'properties/123/dataStreams/456' (optional),
     *   'default_uri'    => 'https://example.com' (optional),
     * ]
     */
    public function list_all_properties() {
        $summaries = $this->list_account_summaries();
        if (is_wp_error($summaries)) {
            return $summaries;
        }

        $out = [];
        foreach ($summaries as $acct) {
            $account      = $acct['account'] ?? '';
            $account_name = $acct['displayName'] ?? '';
            $properties   = $acct['propertySummaries'] ?? [];
            foreach ($properties as $prop) {
                $row = [
                    'property'       => $prop['property'] ?? '',
                    'property_name'  => $prop['displayName'] ?? '',
                    'account'        => $account,
                    'account_name'   => $account_name,
                    'measurement_id' => '',
                    'stream'         => '',
                    'default_uri'    => '',
                ];
                if (!empty($row['property'])) {
                    // Best-effort: fetch first web stream for measurement ID.
                    // If the caller only has Viewer access this may 403 — that's
                    // fine, we just leave the IDs empty.
                    $streams = $this->request('GET', $this->admin_api . '/' . $row['property'] . '/dataStreams');
                    if (!is_wp_error($streams) && !empty($streams['dataStreams'])) {
                        foreach ($streams['dataStreams'] as $s) {
                            if (!empty($s['webStreamData']['measurementId'])) {
                                $row['measurement_id'] = $s['webStreamData']['measurementId'];
                                $row['stream']         = $s['name'] ?? '';
                                $row['default_uri']    = $s['webStreamData']['defaultUri'] ?? '';
                                break;
                            }
                        }
                    }
                }
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Save an existing property selection (no creation). Stores account,
     * property, stream, and measurement ID so reporting + gtag injection work
     * for a property owned by someone else (as long as this account can read it).
     *
     * @param string $property_name   "properties/123"
     * @param string $account_name    "accounts/456"
     * @param string $measurement_id  "G-XXXXXXXXXX" (optional)
     * @param string $stream_name     "properties/123/dataStreams/789" (optional)
     */
    public function select_existing_property($property_name, $account_name = '', $measurement_id = '', $stream_name = '') {
        if (!preg_match('#^properties/\d+$#', $property_name)) {
            return new WP_Error('ga_invalid_property', __('Invalid property identifier.', 'smart-seo-fixer'));
        }
        update_option(self::PROPERTY_OPTION, $property_name, false);
        if ($account_name) {
            update_option(self::ACCOUNT_OPTION, $account_name, false);
        }
        if ($stream_name) {
            update_option(self::STREAM_OPTION, $stream_name, false);
        }
        if ($measurement_id) {
            if (!preg_match('/^G-[A-Z0-9]{4,}$/i', $measurement_id)) {
                return new WP_Error('ga_invalid_mid', __('Invalid Measurement ID format.', 'smart-seo-fixer'));
            }
            update_option(self::MEASUREMENT_ID_OPT, strtoupper($measurement_id), false);
            update_option(self::AUTO_TAG_OPTION, true, false);
        }
        return true;
    }

    /**
     * The site's timezone as a real IANA identifier, for the GA4 Admin API's
     * timeZone field — it rejects anything else with "must be a valid value
     * from the IANA timezone database".
     *
     * wp_timezone_string() only returns an IANA name when Settings > General
     * has a city/region selected. If the site instead uses a manual UTC
     * offset, it returns a raw offset string (e.g. "+02:00"), which is not an
     * IANA identifier and fails GA4 property creation outright. Whole-hour
     * offsets are translated to the equivalent Etc/GMT zone (note: Etc/GMT
     * sign is inverted from the "UTC+N" convention); fractional offsets have
     * no Etc/GMT equivalent and fall back to UTC.
     *
     * @return string
     */
    private function get_valid_iana_timezone() {
        // timezone_identifiers_list() defaults to DateTimeZone::ALL, which
        // excludes the Etc/GMT* aliases needed below — ALL_WITH_BC includes them.
        $known = timezone_identifiers_list(DateTimeZone::ALL_WITH_BC);

        $tz = wp_timezone_string();
        if ($tz && in_array($tz, $known, true)) {
            return $tz;
        }

        $offset = (float) get_option('gmt_offset', 0);
        if ($offset === (float) (int) $offset) {
            $etc = 'Etc/GMT' . ($offset > 0 ? '-' : '+') . abs((int) $offset);
            if (in_array($etc, $known, true)) {
                return $etc;
            }
        }

        return 'UTC';
    }

    /**
     * Create a new GA4 property under the given account.
     * @param string $account_id   "accounts/123" resource name
     * @param string $display_name Property display name
     * @param string $timezone     e.g. "America/New_York"
     * @param string $currency     e.g. "USD"
     */
    public function create_property($account_id, $display_name, $timezone = 'UTC', $currency = 'USD') {
        $body = [
            'parent'       => $account_id,
            'displayName'  => $display_name,
            'timeZone'     => $timezone,
            'currencyCode' => $currency,
        ];
        return $this->request('POST', $this->admin_api . '/properties', $body);
    }

    /**
     * Create a Web Data Stream under the given property.
     * @param string $property  "properties/123" resource name
     * @param string $site_url  Public site URL
     * @param string $site_name Stream display name
     */
    public function create_web_data_stream($property, $site_url, $site_name) {
        $body = [
            'webStreamData' => [
                'defaultUri' => $site_url,
            ],
            'displayName' => $site_name,
            'type'        => 'WEB_DATA_STREAM',
        ];
        return $this->request('POST', $this->admin_api . '/' . $property . '/dataStreams', $body);
    }

    /* -------------------- Data API: reporting -------------------- */

    /**
     * Run a basic metrics report for the configured property.
     *
     * @param array $metrics     e.g. ['sessions', 'totalUsers', 'bounceRate', 'screenPageViews']
     * @param array $dimensions  e.g. [] or ['pagePath']
     * @param int   $days        Lookback window
     * @param int   $limit       Row limit
     */
    public function run_report($metrics, $dimensions = [], $days = 30, $limit = 10) {
        $property = $this->get_property_id();
        if (empty($property)) {
            return new WP_Error('ga_no_property', __('No GA4 property selected.', 'smart-seo-fixer'));
        }

        $days = max(1, min(365, (int) $days));

        $body = [
            'dateRanges' => [[
                'startDate' => $days . 'daysAgo',
                'endDate'   => 'today',
            ]],
            'metrics' => array_map(function ($m) { return ['name' => $m]; }, $metrics),
            'limit'   => (int) $limit,
        ];
        if (!empty($dimensions)) {
            $body['dimensions'] = array_map(function ($d) { return ['name' => $d]; }, $dimensions);
        }

        return $this->request('POST', $this->data_api . '/' . $property . ':runReport', $body);
    }

    /**
     * Summary for Client Report: sessions / users / pageviews / bounce + top pages + top sources.
     */
    public function get_report_summary($days = 30) {
        if (!$this->is_connected() || empty($this->get_property_id())) {
            return new WP_Error('ga_not_ready', __('Google Analytics is not connected or no property is selected.', 'smart-seo-fixer'));
        }

        $totals = $this->run_report(
            ['sessions', 'totalUsers', 'screenPageViews', 'bounceRate', 'averageSessionDuration', 'engagementRate'],
            [],
            $days,
            1
        );
        if (is_wp_error($totals)) {
            return $totals;
        }

        $out = [
            'days'                => $days,
            'sessions'            => 0,
            'users'               => 0,
            'pageviews'           => 0,
            'bounce_rate'         => 0.0,
            'avg_session_seconds' => 0.0,
            'engagement_rate'     => 0.0,
            'top_pages'           => [],
            'top_sources'         => [],
        ];

        if (!empty($totals['rows'][0]['metricValues'])) {
            $v = $totals['rows'][0]['metricValues'];
            $out['sessions']            = (int) ($v[0]['value'] ?? 0);
            $out['users']               = (int) ($v[1]['value'] ?? 0);
            $out['pageviews']           = (int) ($v[2]['value'] ?? 0);
            $out['bounce_rate']         = round(((float) ($v[3]['value'] ?? 0)) * 100, 1);
            $out['avg_session_seconds'] = round((float) ($v[4]['value'] ?? 0), 1);
            $out['engagement_rate']     = round(((float) ($v[5]['value'] ?? 0)) * 100, 1);
        }

        $top_pages = $this->run_report(['screenPageViews', 'sessions'], ['pagePath'], $days, 10);
        if (!is_wp_error($top_pages) && !empty($top_pages['rows'])) {
            foreach ($top_pages['rows'] as $row) {
                $out['top_pages'][] = [
                    'path'      => $row['dimensionValues'][0]['value'] ?? '',
                    'pageviews' => (int) ($row['metricValues'][0]['value'] ?? 0),
                    'sessions'  => (int) ($row['metricValues'][1]['value'] ?? 0),
                ];
            }
        }

        $top_sources = $this->run_report(['sessions'], ['sessionSource'], $days, 10);
        if (!is_wp_error($top_sources) && !empty($top_sources['rows'])) {
            foreach ($top_sources['rows'] as $row) {
                $out['top_sources'][] = [
                    'source'   => $row['dimensionValues'][0]['value'] ?? '',
                    'sessions' => (int) ($row['metricValues'][0]['value'] ?? 0),
                ];
            }
        }

        return $out;
    }

    /* -------------------- gtag.js injection -------------------- */

    public function output_gtag() {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (!get_option(self::AUTO_TAG_OPTION, true)) {
            return;
        }
        $mid = $this->get_measurement_id();
        if (empty($mid) || !preg_match('/^G-[A-Z0-9]+$/i', $mid)) {
            return;
        }

        // Do not tag wp-login, previews etc.
        if (is_preview() || is_customize_preview()) {
            return;
        }

        echo "\n<!-- Smart SEO Fixer: Google Analytics (GA4) -->\n";
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr($mid) . '"></script>' . "\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config'," . wp_json_encode($mid) . ");</script>\n";
    }

    /* -------------------- One-click auto-setup -------------------- */

    /**
     * End-to-end: pick/create account, create property + web stream, store measurement ID.
     *
     * For safety we don't create a new ACCOUNT (Google requires ToS acceptance
     * for that). If the user has no accounts we instruct them to visit
     * analytics.google.com once to accept ToS, then re-run.
     *
     * @param string|null $preferred_account_id Optional "accounts/123" to use; otherwise first account.
     */
    public function auto_setup_property($preferred_account_id = null) {
        $site_url  = home_url('/');
        $site_name = get_bloginfo('name');
        $tz        = $this->get_valid_iana_timezone();
        $currency  = get_option('woocommerce_currency', 'USD');

        $log = [
            'site_url' => $site_url,
            'steps'    => [],
            'success'  => false,
            'message'  => '',
        ];

        $add_step = function ($name, $success, $detail = '') use (&$log) {
            $log['steps'][] = ['name' => $name, 'success' => (bool) $success, 'detail' => $detail];
        };

        // 0. Precheck — public reachable domain
        $host = wp_parse_url($site_url, PHP_URL_HOST);
        if (!$host || $host === 'localhost' || preg_match('/\.(local|test|localhost)$/i', $host) || filter_var($host, FILTER_VALIDATE_IP)) {
            $add_step('precheck_domain', false, sprintf(__('Domain "%s" is not publicly reachable.', 'smart-seo-fixer'), $host));
            $log['message'] = __('GA4 requires a publicly reachable domain.', 'smart-seo-fixer');
            return $log;
        }
        $add_step('precheck_domain', true, $host);

        // 1. List accounts
        $summaries = $this->list_account_summaries();
        if (is_wp_error($summaries)) {
            $add_step('list_accounts', false, $this->maybe_scope_hint($summaries));
            $log['message'] = $summaries->get_error_message();
            return $log;
        }
        if (empty($summaries)) {
            $add_step('list_accounts', false, __('No GA4 accounts found.', 'smart-seo-fixer'));
            $log['message'] = __('You have no Google Analytics accounts. Please visit https://analytics.google.com/ once to accept the Terms of Service, then try again.', 'smart-seo-fixer');
            return $log;
        }

        // 2. Pick account
        $account_id = null;
        if ($preferred_account_id) {
            foreach ($summaries as $s) {
                if (!empty($s['account']) && $s['account'] === $preferred_account_id) {
                    $account_id = $s['account'];
                    break;
                }
            }
        }
        if (!$account_id) {
            $account_id = $summaries[0]['account'] ?? null;
        }
        if (!$account_id) {
            $add_step('pick_account', false, __('Could not determine an account to use.', 'smart-seo-fixer'));
            return $log;
        }
        $add_step('pick_account', true, $account_id);

        // 3. Create property
        $property = $this->create_property($account_id, $site_name, $tz, $currency);
        if (is_wp_error($property)) {
            $add_step('create_property', false, $this->maybe_scope_hint($property));
            $log['message'] = $property->get_error_message();
            return $log;
        }
        $property_name = $property['name'] ?? ''; // "properties/123"
        if (empty($property_name)) {
            $add_step('create_property', false, __('API returned no property name.', 'smart-seo-fixer'));
            return $log;
        }
        $add_step('create_property', true, $property_name);

        // 4. Create web data stream
        $stream = $this->create_web_data_stream($property_name, $site_url, $site_name);
        if (is_wp_error($stream)) {
            $add_step('create_stream', false, $stream->get_error_message());
            $log['message'] = $stream->get_error_message();
            return $log;
        }
        $measurement_id = $stream['webStreamData']['measurementId'] ?? '';
        $stream_name    = $stream['name'] ?? '';
        if (empty($measurement_id)) {
            $add_step('create_stream', false, __('Stream created but no measurement ID returned.', 'smart-seo-fixer'));
            return $log;
        }
        $add_step('create_stream', true, $measurement_id);

        // 5. Save config
        update_option(self::ACCOUNT_OPTION, $account_id, false);
        update_option(self::PROPERTY_OPTION, $property_name, false);
        update_option(self::STREAM_OPTION, $stream_name, false);
        update_option(self::MEASUREMENT_ID_OPT, $measurement_id, false);
        update_option(self::AUTO_TAG_OPTION, true, false);
        $add_step('save_config', true, $measurement_id);

        $log['success']        = true;
        $log['measurement_id'] = $measurement_id;
        $log['property']       = $property_name;
        $log['message']        = __('Google Analytics 4 property created and tracking code installed on your site.', 'smart-seo-fixer');
        return $log;
    }

    private function maybe_scope_hint($wp_error) {
        $data = $wp_error->get_error_data();
        $msg  = $wp_error->get_error_message();
        $status = is_array($data) && !empty($data['status']) ? (int) $data['status'] : 0;
        if ($status === 401 || $status === 403) {
            return $msg . ' ' . __('(If you connected before the Analytics scope was added, please disconnect and reconnect Google Analytics.)', 'smart-seo-fixer');
        }
        return $msg;
    }
}
