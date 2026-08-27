<?php
/**
 * IndexNow — instant indexing
 *
 * Pushes new and updated URLs to IndexNow-participating search engines
 * (Bing, Yandex, Seznam, Naver, and others) the moment a post is published
 * or updated, so they get crawled in minutes instead of days.
 *
 * Replaces the old sitemap "ping" calls to Google and Bing — Google removed
 * its sitemap ping endpoint in 2023 and Bing deprecated its own in favor of
 * IndexNow. Google has no instant-submit API; it discovers changes via the
 * sitemap + Search Console, which the plugin already supports.
 *
 * Protocol: https://www.indexnow.org/documentation
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_IndexNow {

    /** Option holding this site's IndexNow key. */
    const OPTION_KEY = 'ssf_indexnow_key';

    /** Shared IndexNow endpoint (distributes to all participating engines). */
    const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * Register hooks.
     */
    public static function init() {
        // Serve the key verification file at /{key}.txt (virtual file).
        add_action('init', [__CLASS__, 'maybe_serve_key_file'], 0);

        // Submit a URL whenever a post becomes (or is updated while) published.
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 10, 3);
    }

    /**
     * Whether IndexNow submission is enabled (default on).
     */
    public static function enabled() {
        return (bool) Smart_SEO_Fixer::get_option('enable_indexnow', true);
    }

    /**
     * Get (or lazily create) this site's IndexNow key.
     */
    public static function get_key() {
        $key = get_option(self::OPTION_KEY, '');
        if (empty($key)) {
            $key = self::generate_key();
            update_option(self::OPTION_KEY, $key, false);
        }
        return $key;
    }

    /**
     * Generate a fresh key (32 hex chars; IndexNow accepts 8–128 of [a-f0-9-]).
     */
    private static function generate_key() {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (Exception $e) {
                // fall through
            }
        }
        return md5(wp_generate_password(32, false, false) . microtime(true));
    }

    /**
     * Serve the key file at https://host/{key}.txt containing the key.
     * IndexNow fetches this to verify ownership before accepting submissions.
     */
    public static function maybe_serve_key_file() {
        $path = isset($_SERVER['REQUEST_URI'])
            ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
            : '';
        $path = ltrim($path, '/');

        // Strip a subdirectory install prefix if present.
        $home_path = trim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
        if ($home_path && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        }

        if ($path === '' || substr($path, -4) !== '.txt') {
            return;
        }

        $key = get_option(self::OPTION_KEY, '');
        if (!empty($key) && $path === $key . '.txt') {
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Robots-Tag: noindex');
            echo esc_html($key);
            exit;
        }
    }

    /**
     * On publish (new or updated), submit the URL.
     */
    public static function on_transition($new_status, $old_status, $post) {
        if (!self::enabled() || $new_status !== 'publish') {
            return;
        }
        if (!$post instanceof WP_Post) {
            return;
        }
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        // Only public post types.
        $pt = get_post_type_object($post->post_type);
        if (!$pt || empty($pt->public)) {
            return;
        }

        // Don't ask engines to index pages we tell them not to index.
        if (get_post_meta($post->ID, '_ssf_noindex', true)) {
            return;
        }

        $url = get_permalink($post);
        if ($url) {
            self::submit_url($url);
        }
    }

    /**
     * Submit one or more URLs to IndexNow (fire-and-forget, non-blocking).
     *
     * @param string|string[] $urls
     */
    public static function submit_url($urls) {
        if (!self::enabled()) {
            return;
        }

        $urls = array_values(array_filter(array_unique((array) $urls)));
        if (empty($urls)) {
            return;
        }

        $key  = self::get_key();
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($host) || empty($key)) {
            return;
        }

        $body = wp_json_encode([
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => home_url('/' . $key . '.txt'),
            'urlList'     => array_slice($urls, 0, 100), // IndexNow caps at 10,000; we keep it modest
        ]);

        wp_remote_post(self::ENDPOINT, [
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'     => $body,
        ]);

        if (class_exists('SSF_Logger')) {
            SSF_Logger::info(sprintf('IndexNow submitted %d URL(s): %s', count($urls), implode(', ', array_slice($urls, 0, 3))), 'general');
        }
    }
}
