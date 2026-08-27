<?php
/**
 * GitHub Plugin Updater
 * 
 * Checks a GitHub repository (public or private) for new releases 
 * and integrates with the WordPress plugin update system.
 * 
 * For private repos, a GitHub Personal Access Token is required.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Updater {
    
    private $github_user = 'mbheramil';
    // Repo migrated in v2.0.82 — this points every existing install at the
    // new repo from here on. The old repo stays up as a fallback until sites
    // have had time to pick up this bridge release via their auto-update cron.
    private $github_repo = 'Smart-SEO-Fixer-v2';
    private $plugin_slug;
    private $plugin_file;
    private $current_version;
    private $github_response = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_file     = SSF_PLUGIN_BASENAME;
        $this->plugin_slug     = dirname($this->plugin_file);
        $this->current_version = SSF_VERSION;
        
        // Hook into the WordPress update system
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
        
        // Add "Check for updates" link on plugins page
        add_filter('plugin_action_links_' . $this->plugin_file, [$this, 'action_links']);
        
        // Show admin notice after manual check
        add_action('admin_notices', [$this, 'update_check_notice']);
        
        // Allow WordPress to download from GitHub (private repo auth)
        add_filter('http_request_args', [$this, 'authorize_download'], 10, 2);

        // Register 5-minute cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Enable WP's native auto-update for this plugin as a fallback
        add_filter('auto_update_plugin', [$this, 'enable_auto_update'], 10, 2);

        // Cron callback for instant auto-update
        add_action('ssf_auto_update_check', [$this, 'run_auto_update']);
    }
    
    /**
     * Get the stored GitHub PAT token
     */
    private function get_token() {
        return Smart_SEO_Fixer::get_option('github_token', '');
    }
    
    /**
     * Build request headers (with auth if token exists)
     */
    private function get_headers() {
        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'Smart-SEO-Fixer-Updater/' . $this->current_version,
        ];
        
        $token = $this->get_token();
        if (!empty($token)) {
            $headers['Authorization'] = 'token ' . $token;
        }
        
        return $headers;
    }
    
    /**
     * Fetch the latest release info from GitHub API
     * Falls back to tags if no formal Release exists
     */
    private function get_github_release($force = false) {
        if (!$force && $this->github_response !== null) {
            return $this->github_response;
        }
        
        // Check transient cache (6 hours) unless forced
        if (!$force) {
            $cached = get_transient('ssf_github_release');
            if ($cached !== false) {
                $this->github_response = $cached;
                return $cached;
            }
        }
        
        // Try 1: Check for a formal GitHub Release
        $body = $this->fetch_latest_release();
        
        // Try 2: If no release exists, fall back to latest tag
        if (!$body) {
            $body = $this->fetch_latest_tag();
        }
        
        if (!$body || !isset($body->tag_name)) {
            $this->github_response = false;
            return false;
        }
        
        $this->github_response = $body;
        delete_transient('ssf_update_error');
        set_transient('ssf_github_release', $body, 6 * HOUR_IN_SECONDS);
        
        return $body;
    }
    
    /**
     * Try to fetch the latest formal GitHub Release
     */
    private function fetch_latest_release() {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_user,
            $this->github_repo
        );
        
        $response = wp_remote_get($url, [
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 401 || $code === 403) {
            set_transient('ssf_update_error', 'auth', HOUR_IN_SECONDS);
            return false;
        }
        
        if ($code !== 200) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response));
        
        if (empty($body) || !isset($body->tag_name)) {
            return false;
        }
        
        return $body;
    }
    
    /**
     * Fallback: fetch the latest tag and build a release-like object
     */
    private function fetch_latest_tag() {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/tags?per_page=1',
            $this->github_user,
            $this->github_repo
        );
        
        $response = wp_remote_get($url, [
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 401 || $code === 403) {
            set_transient('ssf_update_error', 'auth', HOUR_IN_SECONDS);
            return false;
        }
        
        if ($code !== 200) {
            set_transient('ssf_update_error', 'no_release', HOUR_IN_SECONDS);
            return false;
        }
        
        $tags = json_decode(wp_remote_retrieve_body($response));
        
        if (empty($tags) || !is_array($tags) || !isset($tags[0]->name)) {
            set_transient('ssf_update_error', 'no_release', HOUR_IN_SECONDS);
            return false;
        }
        
        $tag = $tags[0];
        
        // Build a release-like object from the tag
        $release = new stdClass();
        $release->tag_name = $tag->name;
        $release->html_url = sprintf(
            'https://github.com/%s/%s/releases/tag/%s',
            $this->github_user,
            $this->github_repo,
            $tag->name
        );
        $release->zipball_url = $tag->zipball_url;
        $release->assets = [];
        $release->body = 'See GitHub for changelog.';
        $release->published_at = '';
        
        return $release;
    }
    
    /**
     * Check for plugin updates
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $release = $this->get_github_release();
        
        if (!$release) {
            return $transient;
        }
        
        $remote_version = ltrim($release->tag_name, 'v');
        
        if (version_compare($remote_version, $this->current_version, '>')) {
            $download_url = $this->get_download_url($release);
            
            if ($download_url) {
                $plugin_data = new stdClass();
                $plugin_data->slug         = $this->plugin_slug;
                $plugin_data->plugin       = $this->plugin_file;
                $plugin_data->new_version  = $remote_version;
                $plugin_data->url          = $release->html_url ?? '';
                $plugin_data->package      = $download_url;
                $plugin_data->icons        = [];
                $plugin_data->banners      = [];
                $plugin_data->tested       = '';
                $plugin_data->requires_php = '7.4';
                
                $transient->response[$this->plugin_file] = $plugin_data;
            }
        }
        
        return $transient;
    }
    
    /**
     * Provide plugin info for the "View Details" popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $release = $this->get_github_release();
        
        if (!$release) {
            return $result;
        }
        
        $remote_version = ltrim($release->tag_name, 'v');
        
        $info = new stdClass();
        $info->name          = 'Smart SEO Fixer';
        $info->slug          = $this->plugin_slug;
        $info->version       = $remote_version;
        $info->author        = 'mbh';
        $info->requires      = '5.8';
        $info->tested        = '';
        $info->requires_php  = '7.4';
        $info->downloaded    = 0;
        $info->last_updated  = $release->published_at ?? '';
        $info->download_link = $this->get_download_url($release);
        
        $info->sections = [
            'description' => 'AI-powered SEO optimization plugin that analyzes and fixes SEO issues using OpenAI.',
            'changelog'   => nl2br(esc_html($release->body ?? 'See GitHub for changelog.')),
        ];
        
        $info->banners = [];
        
        return $info;
    }
    
    /**
     * Fix directory name after install
     */
    public function after_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $result;
        }
        
        global $wp_filesystem;
        
        $install_dir = rtrim($result['destination'], '/\\');
        $proper_dir  = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        
        if ($install_dir !== $proper_dir) {
            // Remove target if it somehow still exists
            if ($wp_filesystem->is_dir($proper_dir)) {
                $wp_filesystem->delete($proper_dir, true);
            }
            $wp_filesystem->move($install_dir, $proper_dir);
            $result['destination']      = $proper_dir;
            $result['destination_name'] = $this->plugin_slug;
        }
        
        // Verify critical files exist after installation
        $critical_file = $proper_dir . '/smart-seo-fixer.php';
        if (!$wp_filesystem->exists($critical_file)) {
            $error_context = [
                'critical_file' => $critical_file,
                'install_dir'   => $install_dir,
                'source'        => $result['source'] ?? 'unknown',
            ];
            error_log('Smart SEO Fixer: Critical file missing after update: ' . $critical_file);
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('Critical file missing after update', 'updater', $error_context);
            }
        } else {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::info('Plugin updated successfully', 'updater', [
                    'destination' => $result['destination'] ?? $proper_dir,
                ]);
            }
        }
        
        // Re-activate if it was active
        if (is_plugin_active($this->plugin_file)) {
            activate_plugin($this->plugin_file);
        }
        
        return $result;
    }
    
    /**
     * Inject auth header and download settings for GitHub requests
     */
    public function authorize_download($args, $url) {
        // Only apply to genuine GitHub hosts — substring checks could be
        // tricked into sending the token to a third-party URL that merely
        // contains "github.com" somewhere in its path or query string.
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $github_hosts = ['github.com', 'api.github.com', 'codeload.github.com', 'objects.githubusercontent.com'];
        if (!in_array($host, $github_hosts, true)) {
            return $args;
        }

        if (strpos($url, $this->github_repo) === false) {
            return $args;
        }

        $token = $this->get_token();
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        // GitHub redirects — follow them and increase timeout
        $args['timeout'] = 60;
        $args['redirection'] = 10;

        return $args;
    }
    
    /**
     * Get the best download URL from a release
     */
    private function get_download_url($release) {
        // Prefer uploaded .zip asset
        if (!empty($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (isset($asset->browser_download_url) && substr($asset->name, -4) === '.zip') {
                    return $asset->browser_download_url;
                }
            }
        }
        
        // Use direct GitHub archive URL (more reliable than API zipball_url which
        // requires API auth headers and redirects through api.github.com)
        if (!empty($release->tag_name)) {
            return sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                $this->github_user,
                $this->github_repo,
                $release->tag_name
            );
        }
        
        // Last resort: API zipball
        if (!empty($release->zipball_url)) {
            return $release->zipball_url;
        }
        
        return false;
    }
    
    /**
     * Add action links on the Plugins page
     */
    public function action_links($links) {
        $check_link = '<a href="' . esc_url(wp_nonce_url(
            admin_url('plugins.php?ssf_force_update_check=1'),
            'ssf_force_check'
        )) . '">' . __('Check for updates', 'smart-seo-fixer') . '</a>';
        
        array_unshift($links, $check_link);
        return $links;
    }
    
    /**
     * Handle force update check + show admin notice with result
     */
    public static function force_check() {
        if (!isset($_GET['ssf_force_update_check'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ssf_force_check')) {
            return;
        }
        
        // Clear all caches
        delete_transient('ssf_github_release');
        delete_transient('ssf_update_error');
        delete_site_transient('update_plugins');
        
        // Force a fresh check right now
        $updater = new self();
        $release = $updater->get_github_release(true);
        
        if ($release) {
            $remote_version = ltrim($release->tag_name, 'v');
            if (version_compare($remote_version, SSF_VERSION, '>')) {
                set_transient('ssf_update_notice', 'update_available|' . $remote_version, 60);
            } else {
                set_transient('ssf_update_notice', 'up_to_date|' . SSF_VERSION, 60);
            }
        } else {
            $error = get_transient('ssf_update_error');
            if ($error === 'auth') {
                set_transient('ssf_update_notice', 'auth_error', 60);
            } elseif ($error === 'no_release') {
                set_transient('ssf_update_notice', 'no_release', 60);
            } else {
                set_transient('ssf_update_notice', 'connection_error', 60);
            }
        }
        
        wp_redirect(admin_url('plugins.php?ssf_checked=1'));
        exit;
    }
    
    /**
     * Register 5-minute cron schedule
     */
    public function add_cron_interval($schedules) {
        if (!isset($schedules['ssf_five_minutes'])) {
            $schedules['ssf_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 Minutes (Smart SEO Fixer update check)', 'smart-seo-fixer'),
            ];
        }
        return $schedules;
    }

    /**
     * Enable WP native auto-update for this plugin
     */
    public function enable_auto_update($update, $item) {
        if (isset($item->plugin) && $item->plugin === $this->plugin_file) {
            return true;
        }
        return $update;
    }

    /**
     * Schedule the 5-minute auto-update cron event
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ssf_auto_update_check')) {
            wp_schedule_event(time(), 'ssf_five_minutes', 'ssf_auto_update_check');
        }
    }

    /**
     * Unschedule the auto-update cron event
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled('ssf_auto_update_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ssf_auto_update_check');
        }
    }

    /**
     * Cron callback: check GitHub and immediately apply update if a newer version exists
     */
    public function run_auto_update() {
        // Force a fresh version check (bypass transient cache)
        delete_transient('ssf_github_release');
        $release = $this->get_github_release(true);

        if (!$release || !isset($release->tag_name)) {
            return;
        }

        $remote_version = ltrim($release->tag_name, 'v');

        if (!version_compare($remote_version, $this->current_version, '>')) {
            return; // Already up to date
        }

        $download_url = $this->get_download_url($release);
        if (!$download_url) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('Auto-update: could not resolve download URL', 'updater', ['tag' => $release->tag_name]);
            }
            return;
        }

        // Load upgrader classes (not available on frontend by default)
        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Use a silent (non-interactive) upgrader skin
        $skin    = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result   = $upgrader->upgrade($this->plugin_file);

        if (is_wp_error($result)) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('Auto-update failed', 'updater', [
                    'version' => $remote_version,
                    'error'   => $result->get_error_message(),
                ]);
            }
        } elseif ($result !== false) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::info('Auto-update applied', 'updater', [
                    'from' => $this->current_version,
                    'to'   => $remote_version,
                ]);
            }
            // Re-schedule with the new version loaded
            delete_transient('ssf_github_release');
        }
    }

    /**
     * Display admin notice after update check
     */
    public function update_check_notice() {
        if (!isset($_GET['ssf_checked'])) {
            return;
        }
        
        $notice = get_transient('ssf_update_notice');
        if (!$notice) {
            return;
        }
        
        delete_transient('ssf_update_notice');
        
        $parts = explode('|', $notice);
        $type = $parts[0];
        $version = $parts[1] ?? '';
        
        switch ($type) {
            case 'update_available':
                $class = 'notice-info';
                $msg = sprintf(
                    __('Smart SEO Fixer: Update available! Version %s is ready. You can update it below.', 'smart-seo-fixer'),
                    '<strong>' . esc_html($version) . '</strong>'
                );
                break;
                
            case 'up_to_date':
                $class = 'notice-success';
                $msg = sprintf(
                    __('Smart SEO Fixer: You are running the latest version (%s). No update needed.', 'smart-seo-fixer'),
                    '<strong>' . esc_html($version) . '</strong>'
                );
                break;
                
            case 'auth_error':
                $class = 'notice-error';
                $msg = sprintf(
                    __('Smart SEO Fixer: Could not check for updates — GitHub authentication failed. The repository is private. Please add your GitHub Personal Access Token in %sSettings%s.', 'smart-seo-fixer'),
                    '<a href="' . admin_url('admin.php?page=smart-seo-fixer-settings') . '">',
                    '</a>'
                );
                break;
                
            case 'no_release':
                $class = 'notice-warning';
                $msg = __('Smart SEO Fixer: No GitHub releases found. Create a Release on GitHub for auto-updates to work.', 'smart-seo-fixer');
                break;
                
            default:
                $class = 'notice-error';
                $msg = __('Smart SEO Fixer: Could not connect to GitHub. Check your internet connection and try again.', 'smart-seo-fixer');
                break;
        }
        
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $msg . '</p></div>';
    }
}
