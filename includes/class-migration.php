<?php
/**
 * Migration Class
 *
 * Handles migration from other SEO plugins:
 * Yoast SEO, Rank Math, All in One SEO (v3 + v4), SEOPress, The SEO Framework.
 *
 * Architecture:
 * - Each plugin defines candidate postmeta keys per normalized field
 *   (first non-empty candidate wins), so old + new storage formats both work.
 * - AIOSEO v4 stores data in a custom table (wp_aioseo_posts) — read from it
 *   when present, fall back to postmeta otherwise.
 * - All sources are normalized into one shape, then a single shared apply
 *   step writes the _ssf_* meta. One code path = one set of bugs to fix.
 * - Template variables (%%title%%, %title%, #post_title, …) are resolved
 *   per-plugin syntax so migrated titles never contain raw placeholders.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Migration {

    /**
     * Post statuses that are never worth migrating.
     */
    const EXCLUDED_STATUSES = ['trash', 'auto-draft', 'inherit'];

    /**
     * Supported plugins for migration.
     *
     * meta_keys: normalized field => array of candidate postmeta keys
     *            (checked in order; first non-empty value wins).
     * var_syntax: which template-variable dialect the plugin uses.
     */
    private $supported_plugins = [
        'yoast' => [
            'name' => 'Yoast SEO',
            'file' => 'wordpress-seo/wp-seo.php',
            'var_syntax' => 'yoast',
            'meta_keys' => [
                'title'               => ['_yoast_wpseo_title'],
                'description'         => ['_yoast_wpseo_metadesc'],
                'focus_keyword'       => ['_yoast_wpseo_focuskw'],
                'canonical'           => ['_yoast_wpseo_canonical'],
                'noindex'             => ['_yoast_wpseo_meta-robots-noindex'],
                'nofollow'            => ['_yoast_wpseo_meta-robots-nofollow'],
                'og_title'            => ['_yoast_wpseo_opengraph-title'],
                'og_description'      => ['_yoast_wpseo_opengraph-description'],
                'og_image'            => ['_yoast_wpseo_opengraph-image'],
                'twitter_title'       => ['_yoast_wpseo_twitter-title'],
                'twitter_description' => ['_yoast_wpseo_twitter-description'],
                'twitter_image'       => ['_yoast_wpseo_twitter-image'],
            ],
        ],
        'rankmath' => [
            'name' => 'Rank Math',
            'file' => 'seo-by-rank-math/rank-math.php',
            'var_syntax' => 'rankmath',
            'meta_keys' => [
                'title'               => ['rank_math_title'],
                'description'         => ['rank_math_description'],
                'focus_keyword'       => ['rank_math_focus_keyword'],
                'canonical'           => ['rank_math_canonical_url'],
                // rank_math_robots is ONE serialized array holding both
                // 'noindex' and 'nofollow' — handled by normalize_robots().
                'noindex'             => ['rank_math_robots'],
                'nofollow'            => ['rank_math_robots'],
                'og_title'            => ['rank_math_facebook_title'],
                'og_description'      => ['rank_math_facebook_description'],
                'og_image'            => ['rank_math_facebook_image'],
                'twitter_title'       => ['rank_math_twitter_title'],
                'twitter_description' => ['rank_math_twitter_description'],
                'twitter_image'       => ['rank_math_twitter_image'],
            ],
        ],
        'aioseo' => [
            'name' => 'All in One SEO',
            'file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'var_syntax' => 'aioseo',
            // Postmeta fallback for installs without the v4 custom table.
            // v4 mirror keys first, then v3 (_aioseop_*) legacy keys.
            'meta_keys' => [
                'title'               => ['_aioseo_title', '_aioseop_title'],
                'description'         => ['_aioseo_description', '_aioseop_description'],
                'focus_keyword'       => ['_aioseo_keywords', '_aioseop_keywords'],
                'canonical'           => ['_aioseo_canonical_url'],
                'noindex'             => ['_aioseo_noindex', '_aioseop_noindex'],
                'nofollow'            => ['_aioseo_nofollow', '_aioseop_nofollow'],
                'og_title'            => ['_aioseo_og_title'],
                'og_description'      => ['_aioseo_og_description'],
                'og_image'            => [],
                'twitter_title'       => ['_aioseo_twitter_title'],
                'twitter_description' => ['_aioseo_twitter_description'],
                'twitter_image'       => [],
            ],
        ],
        'seopress' => [
            'name' => 'SEOPress',
            'file' => 'wp-seopress/seopress.php',
            'var_syntax' => 'seopress',
            'meta_keys' => [
                'title'               => ['_seopress_titles_title'],
                'description'         => ['_seopress_titles_desc'],
                'focus_keyword'       => ['_seopress_analysis_target_kw'],
                'canonical'           => ['_seopress_robots_canonical'],
                // SEOPress stores 'yes' when noindex/nofollow is enabled.
                'noindex'             => ['_seopress_robots_index'],
                'nofollow'            => ['_seopress_robots_follow'],
                'og_title'            => ['_seopress_social_fb_title'],
                'og_description'      => ['_seopress_social_fb_desc'],
                'og_image'            => ['_seopress_social_fb_img'],
                'twitter_title'       => ['_seopress_social_twitter_title'],
                'twitter_description' => ['_seopress_social_twitter_desc'],
                'twitter_image'       => ['_seopress_social_twitter_img'],
            ],
        ],
        'tsf' => [
            'name' => 'The SEO Framework',
            'file' => 'autodescription/autodescription.php',
            'var_syntax' => 'none', // TSF stores literal text, no placeholders
            'meta_keys' => [
                'title'               => ['_genesis_title'],
                'description'         => ['_genesis_description'],
                'focus_keyword'       => [],
                'canonical'           => ['_genesis_canonical_uri'],
                'noindex'             => ['_genesis_noindex'],
                'nofollow'            => ['_genesis_nofollow'],
                'og_title'            => ['_open_graph_title'],
                'og_description'      => ['_open_graph_description'],
                'og_image'            => ['_social_image_url'],
                'twitter_title'       => ['_twitter_title'],
                'twitter_description' => ['_twitter_description'],
                'twitter_image'       => [],
            ],
        ],
    ];

    /**
     * Normalized source field => SSF destination meta key.
     */
    private $destination_keys = [
        'title'               => '_ssf_seo_title',
        'description'         => '_ssf_meta_description',
        'focus_keyword'       => '_ssf_focus_keyword',
        'canonical'           => '_ssf_canonical_url',
        'noindex'             => '_ssf_noindex',
        'nofollow'            => '_ssf_nofollow',
        'og_title'            => '_ssf_og_title',
        'og_description'      => '_ssf_og_description',
        'og_image'            => '_ssf_og_image',
        'twitter_title'       => '_ssf_twitter_title',
        'twitter_description' => '_ssf_twitter_description',
        'twitter_image'       => '_ssf_twitter_image',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_ssf_detect_seo_plugins', [$this, 'ajax_detect_plugins']);
        add_action('wp_ajax_ssf_migrate_from_plugin', [$this, 'ajax_migrate']);
        add_action('wp_ajax_ssf_get_migration_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_ssf_reset_migration', [$this, 'ajax_reset_migration']);
    }

    /**
     * AJAX: Reset migration progress
     */
    public function ajax_reset_migration() {
        check_ajax_referer('ssf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        delete_transient('ssf_migration_progress');
        wp_send_json_success();
    }

    // =========================================================================
    // Detection
    // =========================================================================

    /**
     * Detect installed SEO plugins (active OR leftover data in the DB).
     */
    public function detect_plugins() {
        $detected = [];

        foreach ($this->supported_plugins as $key => $plugin) {
            $is_active  = function_exists('is_plugin_active') ? is_plugin_active($plugin['file']) : false;
            $post_count = $this->count_posts_with_data($key);

            if ($is_active || $post_count > 0) {
                $detected[$key] = [
                    'name'       => $plugin['name'],
                    'active'     => $is_active,
                    'has_data'   => $post_count > 0,
                    'post_count' => $post_count,
                ];
            }
        }

        return $detected;
    }

    /**
     * Whether the AIOSEO v4 custom table exists (preferred data source).
     */
    private function aioseo_table() {
        global $wpdb;
        static $table = null;
        if ($table === null) {
            $candidate = $wpdb->prefix . 'aioseo_posts';
            $table = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate)) === $candidate) ? $candidate : false;
        }
        return $table;
    }

    /**
     * Flat list of every selection-relevant source meta key for a plugin
     * (used to find posts that have ANY data worth migrating — not just
     * title/description, which previously dropped keyword/robots-only posts).
     */
    private function selection_meta_keys($plugin_key) {
        $keys = [];
        foreach ($this->supported_plugins[$plugin_key]['meta_keys'] as $candidates) {
            foreach ($candidates as $k) {
                $keys[$k] = true;
            }
        }
        return array_keys($keys);
    }

    /**
     * Count posts that have any migratable data from a plugin.
     */
    public function count_posts_with_data($plugin_key) {
        global $wpdb;

        if (!isset($this->supported_plugins[$plugin_key])) {
            return 0;
        }

        if ($plugin_key === 'aioseo' && $this->aioseo_table()) {
            $table = $this->aioseo_table();
            $excluded = "'" . implode("','", self::EXCLUDED_STATUSES) . "'";
            return intval($wpdb->get_var(
                "SELECT COUNT(DISTINCT a.post_id)
                 FROM $table a
                 INNER JOIN {$wpdb->posts} p ON p.ID = a.post_id
                 WHERE p.post_status NOT IN ($excluded)
                 AND (
                     COALESCE(a.title, '') != '' OR COALESCE(a.description, '') != ''
                     OR COALESCE(a.keyphrases, '') NOT IN ('', '[]', '{}')
                     OR COALESCE(a.canonical_url, '') != ''
                     OR a.robots_noindex = 1 OR a.robots_nofollow = 1
                 )"
            ));
        }

        $keys = $this->selection_meta_keys($plugin_key);
        if (empty($keys)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $excluded = "'" . implode("','", self::EXCLUDED_STATUSES) . "'";

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN ($placeholders)
             AND pm.meta_value != ''
             AND p.post_status NOT IN ($excluded)",
            ...$keys
        )));
    }

    /**
     * Get a batch of post IDs that have migratable data, ordered stably.
     */
    private function get_post_ids_batch($plugin_key, $limit, $offset) {
        global $wpdb;

        if ($plugin_key === 'aioseo' && $this->aioseo_table()) {
            $table = $this->aioseo_table();
            $excluded = "'" . implode("','", self::EXCLUDED_STATUSES) . "'";
            return array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT a.post_id
                 FROM $table a
                 INNER JOIN {$wpdb->posts} p ON p.ID = a.post_id
                 WHERE p.post_status NOT IN ($excluded)
                 AND (
                     COALESCE(a.title, '') != '' OR COALESCE(a.description, '') != ''
                     OR COALESCE(a.keyphrases, '') NOT IN ('', '[]', '{}')
                     OR COALESCE(a.canonical_url, '') != ''
                     OR a.robots_noindex = 1 OR a.robots_nofollow = 1
                 )
                 ORDER BY a.post_id ASC
                 LIMIT %d OFFSET %d",
                $limit, $offset
            )));
        }

        $keys = $this->selection_meta_keys($plugin_key);
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $excluded = "'" . implode("','", self::EXCLUDED_STATUSES) . "'";
        $params = array_merge($keys, [$limit, $offset]);

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN ($placeholders)
             AND pm.meta_value != ''
             AND p.post_status NOT IN ($excluded)
             ORDER BY pm.post_id ASC
             LIMIT %d OFFSET %d",
            ...$params
        )));
    }

    // =========================================================================
    // Normalized extraction
    // =========================================================================

    /**
     * Extract all migratable data for one post into the normalized shape:
     * ['title' => string, 'description' => string, 'focus_keyword' => string,
     *  'canonical' => string, 'noindex' => bool, 'nofollow' => bool,
     *  'og_title' => string, ... ]
     *
     * Template variables are already resolved here.
     */
    public function get_source_data($plugin_key, $post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        if ($plugin_key === 'aioseo' && $this->aioseo_table()) {
            $data = $this->get_aioseo_table_data($post_id);
        } else {
            $data = $this->get_postmeta_data($plugin_key, $post_id);
        }

        if (empty($data)) {
            return [];
        }

        // Resolve template variables on text fields.
        $syntax = $this->supported_plugins[$plugin_key]['var_syntax'];
        foreach (['title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->parse_seo_variables($data[$field], $post, $syntax);
            }
        }

        // Focus keyword: take the first one if comma-separated.
        if (!empty($data['focus_keyword'])) {
            $data['focus_keyword'] = trim(explode(',', $data['focus_keyword'])[0]);
        }

        return $data;
    }

    /**
     * Build normalized data from postmeta candidate keys.
     */
    private function get_postmeta_data($plugin_key, $post_id) {
        $config = $this->supported_plugins[$plugin_key]['meta_keys'];
        $data = [];

        foreach ($config as $field => $candidates) {
            $value = '';
            foreach ($candidates as $key) {
                $raw = get_post_meta($post_id, $key, true);
                if ($raw !== '' && $raw !== false && $raw !== null && $raw !== []) {
                    $value = $raw;
                    break;
                }
            }

            if ($field === 'noindex') {
                $data[$field] = $this->normalize_robots($value, 'noindex', $plugin_key);
            } elseif ($field === 'nofollow') {
                $data[$field] = $this->normalize_robots($value, 'nofollow', $plugin_key);
            } else {
                $data[$field] = is_scalar($value) ? trim((string) $value) : '';
            }
        }

        return $data;
    }

    /**
     * Build normalized data from the AIOSEO v4 custom table.
     */
    private function get_aioseo_table_data($post_id) {
        global $wpdb;
        $table = $this->aioseo_table();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d", $post_id
        ), ARRAY_A);

        if (!$row) {
            return [];
        }

        // Focus keyphrase lives in a JSON blob: {"focus":{"keyphrase":"..."},"additional":[...]}
        $focus_kw = '';
        if (!empty($row['keyphrases'])) {
            $kp = json_decode($row['keyphrases'], true);
            if (is_array($kp) && !empty($kp['focus']['keyphrase'])) {
                $focus_kw = (string) $kp['focus']['keyphrase'];
            }
        }

        // robots_default = 1 means "use sitewide defaults" — per-post flags only
        // apply when defaults are overridden.
        $robots_custom = isset($row['robots_default']) ? !intval($row['robots_default']) : true;

        return [
            'title'               => trim((string) ($row['title'] ?? '')),
            'description'         => trim((string) ($row['description'] ?? '')),
            'focus_keyword'       => trim($focus_kw),
            'canonical'           => trim((string) ($row['canonical_url'] ?? '')),
            'noindex'             => $robots_custom && !empty($row['robots_noindex']),
            'nofollow'            => $robots_custom && !empty($row['robots_nofollow']),
            'og_title'            => trim((string) ($row['og_title'] ?? '')),
            'og_description'      => trim((string) ($row['og_description'] ?? '')),
            'og_image'            => trim((string) ($row['og_image_custom_url'] ?? '')),
            'twitter_title'       => trim((string) ($row['twitter_title'] ?? '')),
            'twitter_description' => trim((string) ($row['twitter_description'] ?? '')),
            'twitter_image'       => trim((string) ($row['twitter_image_custom_url'] ?? '')),
        ];
    }

    /**
     * Normalize the wildly different robots formats into a boolean.
     *
     * - Yoast:     '1' = on, '2' = explicitly off, '' = default
     * - Rank Math: serialized/unserialized ARRAY like ['noindex','nofollow']
     *              (the old code called strpos() on it — fatal on PHP 8)
     * - AIOSEO v3: 'on' / '1'
     * - SEOPress:  'yes'
     * - TSF:       '1' (qubit: 0 default, 1 on)
     */
    private function normalize_robots($value, $directive, $plugin_key) {
        if (is_array($value)) {
            return in_array($directive, $value, true);
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '0' || $value === '2' || $value === '-1') {
                return false;
            }
            if (in_array(strtolower($value), ['1', 'on', 'yes', 'true'], true)) {
                return true;
            }
            // Serialized array that get_post_meta didn't unserialize, or a
            // robots string like "noindex,nofollow".
            $maybe = maybe_unserialize($value);
            if (is_array($maybe)) {
                return in_array($directive, $maybe, true);
            }
            return strpos($value, $directive) !== false;
        }

        return !empty($value) && $value !== 2;
    }

    // =========================================================================
    // Template variable parsing
    // =========================================================================

    /**
     * Resolve SEO plugin template variables into real text so migrated
     * titles/descriptions never contain raw placeholders.
     *
     * Each plugin family uses a different syntax:
     * - Yoast / SEOPress: %%var%%
     * - Rank Math / AIOSEO v3: %var%
     * - AIOSEO v4: #tag_name
     * - TSF: literal text (no parsing)
     */
    private function parse_seo_variables($string, $post, $syntax = 'yoast') {
        if ($syntax === 'none' || strpos($string, '%') === false && strpos($string, '#') === false) {
            return $this->clean_separators($string);
        }

        $values = $this->variable_values($post);

        if ($syntax === 'yoast' || $syntax === 'seopress') {
            $map = [
                // shared names
                'title' => 'title', 'sitename' => 'sitename', 'sitetitle' => 'sitename',
                'sitedesc' => 'sitedesc', 'sitedescription' => 'sitedesc', 'tagline' => 'sitedesc',
                'sep' => 'sep', 'separator' => 'sep',
                'excerpt' => 'excerpt', 'excerpt_only' => 'excerpt',
                'category' => 'category', 'primary_category' => 'category',
                'tag' => 'tag', 'name' => 'author', 'author' => 'author',
                'currentyear' => 'year', 'currentmonth' => 'month', 'currentdate' => 'date',
                'date' => 'post_date',
                // SEOPress aliases
                'post_title' => 'title', 'post_excerpt' => 'excerpt',
                'post_category' => 'category', 'post_tag' => 'tag',
                'post_author' => 'author', 'post_date' => 'post_date',
                'current_year' => 'year', 'current_month' => 'month',
                'wc_single_price' => '', 'focuskw' => 'focus_keyword',
            ];
            foreach ($map as $var => $value_key) {
                $string = str_replace('%%' . $var . '%%', $value_key !== '' ? ($values[$value_key] ?? '') : '', $string);
            }
            // Strip any unknown %%var%% leftovers.
            $string = preg_replace('/%%[a-z0-9_\-]+%%/i', '', $string);
        } elseif ($syntax === 'rankmath' || $syntax === 'aioseo_v3') {
            $map = [
                'title' => 'title', 'post_title' => 'title',
                'sitename' => 'sitename', 'blog_title' => 'sitename',
                'sitedesc' => 'sitedesc', 'blog_description' => 'sitedesc',
                'sep' => 'sep', 'excerpt' => 'excerpt', 'excerpt_only' => 'excerpt',
                'category' => 'category', 'primary_category' => 'category',
                'categories' => 'category', 'tag' => 'tag', 'tags' => 'tag',
                'name' => 'author', 'author' => 'author',
                'currentyear' => 'year', 'currentmonth' => 'month',
                'currentdate' => 'date', 'currentday' => '', 'date' => 'post_date',
                'focuskeyword' => 'focus_keyword', 'keywords' => 'focus_keyword',
                'page' => '', 'pagenumber' => '', 'pagetotal' => '',
                'seo_title' => '', 'seo_description' => '',
            ];
            foreach ($map as $var => $value_key) {
                $string = str_replace('%' . $var . '%', $value_key !== '' ? ($values[$value_key] ?? '') : '', $string);
            }
            // Strip remaining single-percent variables (conservative pattern:
            // letters/underscores only, so literal "50% off" is untouched).
            $string = preg_replace('/%[a-z_]+%/', '', $string);
        } elseif ($syntax === 'aioseo') {
            // AIOSEO v4 smart tags (#tag). Replace ONLY known tags — a generic
            // #\w+ strip would eat real hashtags in descriptions.
            $map = [
                '#post_title' => 'title', '#site_title' => 'sitename',
                '#tagline' => 'sitedesc', '#separator_sa' => 'sep', '#separator' => 'sep',
                '#post_excerpt_only' => 'excerpt', '#post_excerpt' => 'excerpt',
                '#post_content' => 'excerpt', '#categories' => 'category',
                '#taxonomy_title' => 'category', '#author_name' => 'author',
                '#author_first_name' => 'author', '#current_year' => 'year',
                '#current_month' => 'month', '#current_date' => 'date',
                '#post_date' => 'post_date', '#post_year' => 'year',
                '#post_month' => 'month', '#permalink' => '',
            ];
            // Longest tags first so #post_excerpt_only wins over #post_excerpt.
            uksort($map, function ($a, $b) { return strlen($b) - strlen($a); });
            foreach ($map as $tag => $value_key) {
                $string = str_replace($tag, $value_key !== '' ? ($values[$value_key] ?? '') : '', $string);
            }
            // Also handle v3-style %var% if legacy meta sneaks through.
            if (strpos($string, '%') !== false) {
                return $this->parse_seo_variables($string, $post, 'aioseo_v3');
            }
        }

        return $this->clean_separators($string);
    }

    /**
     * Concrete values for template variables, computed once per post.
     */
    private function variable_values($post) {
        $post_id = $post->ID;
        $sep = (string) Smart_SEO_Fixer::get_option('title_separator', '|');
        if ($sep === '') {
            $sep = '|';
        }

        return [
            'title'         => $post->post_title,
            'sitename'      => get_bloginfo('name'),
            'sitedesc'      => get_bloginfo('description'),
            'sep'           => $sep,
            'excerpt'       => wp_trim_words($post->post_excerpt ?: wp_strip_all_tags($post->post_content), 25),
            'category'      => $this->get_primary_category($post_id),
            'tag'           => $this->get_primary_tag($post_id),
            'author'        => $post->post_author ? get_the_author_meta('display_name', $post->post_author) : '',
            'year'          => date('Y'),
            'month'         => date('F'),
            'date'          => date_i18n(get_option('date_format')),
            'post_date'     => date_i18n(get_option('date_format'), strtotime($post->post_date)),
            'focus_keyword' => (string) get_post_meta($post_id, '_ssf_focus_keyword', true),
        ];
    }

    /**
     * Collapse the artifacts left behind by removed variables:
     * doubled separators, dangling separators, repeated whitespace.
     */
    private function clean_separators($string) {
        $string = preg_replace('/\s*([|\x{2013}\x{2014}\-\x{00BB}\x{2022}~])\s*\1+\s*/u', ' $1 ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        return trim($string, " |–—-»•~\t\n\r");
    }

    /**
     * Get primary category name (honors Yoast/Rank Math primary term).
     */
    private function get_primary_category($post_id) {
        foreach (['_yoast_wpseo_primary_category', 'rank_math_primary_category'] as $key) {
            $primary = get_post_meta($post_id, $key, true);
            if ($primary) {
                $term = get_term((int) $primary, 'category');
                if ($term && !is_wp_error($term)) {
                    return $term->name;
                }
            }
        }

        $categories = get_the_category($post_id);
        return !empty($categories) ? $categories[0]->name : '';
    }

    /**
     * Get primary tag name.
     */
    private function get_primary_tag($post_id) {
        $tags = get_the_tags($post_id);
        return (!empty($tags) && !is_wp_error($tags)) ? $tags[0]->name : '';
    }

    // =========================================================================
    // Preview
    // =========================================================================

    /**
     * Get migration preview (template variables already resolved, so the
     * user sees exactly what will be written).
     */
    public function get_preview($plugin_key, $limit = 10) {
        if (!isset($this->supported_plugins[$plugin_key])) {
            return [];
        }

        $post_ids = $this->get_post_ids_batch($plugin_key, $limit, 0);
        $preview = [];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $data = $this->get_source_data($plugin_key, $post_id);

            $preview[] = [
                'id'               => $post_id,
                'title'            => $post->post_title,
                'type'             => $post->post_type,
                'seo_title'        => $data['title'] ?? '',
                'seo_description'  => $data['description'] ?? '',
                'focus_keyword'    => $data['focus_keyword'] ?? '',
                'already_migrated' => !empty(get_post_meta($post_id, '_ssf_seo_title', true)),
            ];
        }

        return $preview;
    }

    // =========================================================================
    // Migration
    // =========================================================================

    /**
     * Apply normalized source data to a post. Returns the list of fields
     * actually written (empty array = nothing to migrate).
     */
    private function apply_to_post($post_id, $data, $overwrite) {
        $written = [];

        foreach ($this->destination_keys as $field => $dest_key) {
            $value = $data[$field] ?? '';

            // Booleans (noindex / nofollow)
            if ($field === 'noindex' || $field === 'nofollow') {
                if ($value === true) {
                    if ($overwrite || get_post_meta($post_id, $dest_key, true) === '') {
                        update_post_meta($post_id, $dest_key, 1);
                        $written[] = $field;
                    }
                }
                continue;
            }

            if (!is_string($value) || $value === '') {
                continue;
            }

            // Respect existing SSF data unless overwriting.
            if (!$overwrite && get_post_meta($post_id, $dest_key, true) !== '') {
                continue;
            }

            // Sanitize per field type.
            if (in_array($field, ['canonical', 'og_image', 'twitter_image'], true)) {
                $value = esc_url_raw($value);
            } elseif (in_array($field, ['description', 'og_description', 'twitter_description'], true)) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }

            if ($value === '') {
                continue;
            }

            update_post_meta($post_id, $dest_key, $value);
            $written[] = $field;
        }

        return $written;
    }

    /**
     * Migrate a batch of posts (called repeatedly by the AJAX progress loop).
     */
    public function migrate_batch($plugin_key, $offset = 0, $batch_size = 25, $overwrite = false) {
        if (!isset($this->supported_plugins[$plugin_key])) {
            return new WP_Error('invalid_plugin', __('Unknown plugin for migration.', 'smart-seo-fixer'));
        }

        $total = $this->count_posts_with_data($plugin_key);

        if ($total === 0) {
            return [
                'done'      => true,
                'total'     => 0,
                'processed' => 0,
                'migrated'  => 0,
                'skipped'   => 0,
                'percent'   => 100,
            ];
        }

        $post_ids = $this->get_post_ids_batch($plugin_key, $batch_size, $offset);

        $migrated = 0;
        $skipped  = 0;

        foreach ($post_ids as $post_id) {
            $data = $this->get_source_data($plugin_key, $post_id);

            if (empty($data)) {
                $skipped++;
                continue;
            }

            $written = $this->apply_to_post($post_id, $data, $overwrite);

            if (!empty($written)) {
                $migrated++;
            } else {
                $skipped++;
            }
        }

        $processed = $offset + count($post_ids);
        $done = ($processed >= $total) || empty($post_ids);

        // Cumulative progress across batches.
        $cumulative = get_transient('ssf_migration_progress') ?: ['migrated' => 0, 'skipped' => 0];
        $cumulative['migrated'] += $migrated;
        $cumulative['skipped']  += $skipped;

        if ($done) {
            update_option('ssf_last_migration', [
                'plugin'  => $plugin_key,
                'date'    => current_time('mysql'),
                'results' => $cumulative,
            ]);
            delete_transient('ssf_migration_progress');

            if (class_exists('SSF_Logger')) {
                SSF_Logger::info(sprintf(
                    'Migration from %s completed: %d migrated, %d skipped (of %d)',
                    $this->supported_plugins[$plugin_key]['name'],
                    $cumulative['migrated'], $cumulative['skipped'], $total
                ), 'migration');
            }

            return [
                'done'      => true,
                'total'     => $total,
                'processed' => $processed,
                'migrated'  => $cumulative['migrated'],
                'skipped'   => $cumulative['skipped'],
                'percent'   => 100,
            ];
        }

        set_transient('ssf_migration_progress', $cumulative, HOUR_IN_SECONDS);

        return [
            'done'        => false,
            'total'       => $total,
            'processed'   => $processed,
            'migrated'    => $cumulative['migrated'],
            'skipped'     => $cumulative['skipped'],
            'percent'     => $total > 0 ? round(($processed / $total) * 100) : 0,
            'next_offset' => $processed,
        ];
    }

    // =========================================================================
    // AJAX handlers
    // =========================================================================

    /**
     * AJAX: Detect plugins
     */
    public function ajax_detect_plugins() {
        check_ajax_referer('ssf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        wp_send_json_success($this->detect_plugins());
    }

    /**
     * AJAX: Get migration preview
     */
    public function ajax_preview() {
        check_ajax_referer('ssf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $plugin = sanitize_text_field($_POST['plugin'] ?? '');

        if (empty($plugin) || !isset($this->supported_plugins[$plugin])) {
            wp_send_json_error(['message' => __('No plugin specified.', 'smart-seo-fixer')]);
        }

        wp_send_json_success([
            'preview' => $this->get_preview($plugin, 20),
            'total'   => $this->count_posts_with_data($plugin),
        ]);
    }

    /**
     * AJAX: Run migration (chunked for progress bar)
     */
    public function ajax_migrate() {
        // Disable error display to prevent JSON corruption
        @ini_set('display_errors', 0);

        check_ajax_referer('ssf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }

        $plugin    = sanitize_text_field($_POST['plugin'] ?? '');
        $overwrite = !empty($_POST['overwrite']);
        $offset    = intval($_POST['offset'] ?? 0);
        $batch_size = 25;

        if (empty($plugin) || !isset($this->supported_plugins[$plugin])) {
            wp_send_json_error(['message' => __('No plugin specified.', 'smart-seo-fixer')]);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        // Reset progress on first batch
        if ($offset === 0) {
            delete_transient('ssf_migration_progress');
        }

        try {
            $result = $this->migrate_batch($plugin, $offset, $batch_size, $overwrite);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success($result);
        } catch (Throwable $e) {
            // Throwable (not Exception): a TypeError mid-migration must surface
            // as a clean JSON error, not a white-screen 500 that kills the
            // progress loop with "undefined".
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('Migration batch failed: ' . $e->getMessage(), 'migration', [
                    'plugin' => $plugin,
                    'offset' => $offset,
                ]);
            }
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

// Initialize
new SSF_Migration();
