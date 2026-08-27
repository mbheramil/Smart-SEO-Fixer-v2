<?php
/**
 * Client Report Generator
 *
 * Generates SEO reports for client presentations.
 * Supports two modes: 'positive' (highlights only) and 'full' (everything including issues).
 * Admin-only access. Supports HTML preview, print, and PDF export.
 * Optional Google Doc template injection.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Client_Report {

    /**
     * Gather report data for the site.
     *
     * @param string $date_range 'all'|'30'|'60'|'90'|'custom'
     * @param string $start_date Y-m-d (for custom range)
     * @param string $end_date   Y-m-d (for custom range)
     * @param array  $sections   Which sections to include
     * @param string $mode       'positive' or 'full'
     * @return array
     */
    public static function generate($date_range = '30', $start_date = '', $end_date = '', $sections = [], $mode = 'positive') {
        $dates = self::resolve_dates($date_range, $start_date, $end_date);

        $default_sections = [
            'overview',
            'meta_coverage',
            'score_distribution',
            'top_pages',
            'content_health',
            'schema_coverage',
            'image_seo',
            'redirects',
            'keywords',
            'broken_links_fixed',
            'optimizations',
            'sitemap_status',
            'data_freshness',
            'analytics',
        ];

        // Full mode adds extra sections
        if ($mode === 'full') {
            $default_sections[] = 'worst_pages';
            $default_sections[] = 'issues';
        }

        if (empty($sections)) {
            $sections = $default_sections;
        }

        $data = [
            'generated_at' => current_time('mysql'),
            'site_name'    => get_bloginfo('name'),
            'site_url'     => home_url(),
            'site_tagline' => get_bloginfo('description'),
            'site_icon'    => get_site_icon_url(512),
            'date_range'   => $dates,
            'mode'         => $mode,
            'sections'     => [],
        ];

        // Check for cached template
        $template = get_option('ssf_report_template', '');
        if (!empty($template)) {
            $data['template'] = $template;
        }

        $method_map = [
            'overview'           => 'get_overview',
            'meta_coverage'      => 'get_meta_coverage',
            'score_distribution' => 'get_score_distribution',
            'top_pages'          => 'get_top_pages',
            'content_health'     => 'get_content_health',
            'schema_coverage'    => 'get_schema_coverage',
            'image_seo'          => 'get_image_seo',
            'redirects'          => 'get_redirect_stats',
            'keywords'           => 'get_keyword_highlights',
            'broken_links_fixed' => 'get_broken_links_fixed',
            'optimizations'      => 'get_optimization_count',
            'sitemap_status'     => 'get_sitemap_status',
            'data_freshness'     => 'get_data_freshness',
            'analytics'          => 'get_analytics',
            'score_factors'      => 'get_score_factors',
            'worst_pages'        => 'get_worst_pages',
            'issues'             => 'get_issues',
        ];

        foreach ($sections as $section) {
            if (!isset($method_map[$section])) {
                continue;
            }
            $method = $method_map[$section];
            $needs_dates = in_array($method, ['get_overview', 'get_keyword_highlights', 'get_optimization_count', 'get_broken_links_fixed', 'get_analytics']);
            $needs_mode  = in_array($method, ['get_overview', 'get_score_distribution', 'get_broken_links_fixed', 'get_meta_coverage', 'get_image_seo']);

            if ($needs_dates && $needs_mode) {
                $result = self::$method($dates, $mode);
            } elseif ($needs_dates) {
                $result = self::$method($dates);
            } elseif ($needs_mode) {
                $result = self::$method($mode);
            } else {
                $result = self::$method();
            }

            // In positive mode, hide empty sections. In full mode, show everything.
            if ($mode === 'positive' && !self::section_has_value($section, $result)) {
                continue;
            }
            $data['sections'][$section] = $result;
        }

        return $data;
    }

    /**
     * Check if a section has meaningful data worth showing.
     */
    private static function section_has_value($section, $result) {
        if (empty($result)) {
            return false;
        }

        switch ($section) {
            case 'overview':
                return ($result['total_analyzed'] ?? 0) > 0;

            case 'meta_coverage':
                return ($result['with_title'] ?? 0) > 0 || ($result['with_description'] ?? 0) > 0;

            case 'score_distribution':
                return !empty($result) && is_array($result);

            case 'top_pages':
                return !empty($result) && is_array($result);

            case 'content_health':
                return ($result['total_analyzed'] ?? 0) > 0;

            case 'schema_coverage':
                return ($result['custom_schema'] ?? 0) > 0 || !empty($result['auto_schema_types']);

            case 'image_seo':
                return ($result['total_images'] ?? 0) > 0;

            case 'redirects':
                return ($result['total_active'] ?? 0) > 0;

            case 'keywords':
                return ($result['total_tracked'] ?? 0) > 0 && !empty($result['top_keywords']);

            case 'broken_links_fixed':
                return ($result['fixed'] ?? 0) > 0
                    || ($result['dismissed'] ?? 0) > 0
                    || ($result['fixed_total'] ?? 0) > 0;

            case 'optimizations':
                return ($result['total'] ?? 0) > 0;

            case 'sitemap_status':
                return !empty($result['url']);

            case 'data_freshness':
                return ($result['analyzed_count'] ?? 0) > 0;

            case 'analytics':
                return !empty($result['connected']);

            case 'score_factors':
                return !empty($result['factors']);

            default:
                return true;
        }
    }

    /**
     * Resolve date range to start/end dates.
     */
    private static function resolve_dates($range, $start, $end) {
        $end_date = !empty($end) ? $end : current_time('Y-m-d');

        switch ($range) {
            case 'all':
                $start_date = '2000-01-01';
                break;
            case 'custom':
                $start_date = !empty($start) ? $start : date('Y-m-d', strtotime('-30 days'));
                break;
            default:
                $days = intval($range) > 0 ? intval($range) : 30;
                $start_date = date('Y-m-d', strtotime("-{$days} days"));
                break;
        }

        return [
            'start'      => $start_date,
            'end'        => $end_date,
            'label'      => $range === 'all'
                ? __('All Time', 'smart-seo-fixer')
                : sprintf('%s – %s', date_i18n(get_option('date_format'), strtotime($start_date)), date_i18n(get_option('date_format'), strtotime($end_date))),
        ];
    }

    /* ─────────── Section Data Gatherers ─────────── */

    /**
     * Overview: avg score, total analyzed, total published, grade, percentages.
     */
    private static function get_overview($dates, $mode = 'positive') {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [
                'avg_score'        => 0,
                'grade'            => '',
                'total_analyzed'   => 0,
                'total_posts'      => 0,
                'good_count'       => 0,
                'ok_count'         => 0,
                'healthy_pct'      => 0,
                'analyzed_pct'     => 0,
            ];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $total_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
                ...$post_types
            )
        );

        // Use latest score per post — only for currently published posts in active post types
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) as total, ROUND(AVG(score), 0) as avg_score,
                        SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as good,
                        SUM(CASE WHEN score >= 60 AND score < 80 THEN 1 ELSE 0 END) as ok
                 FROM (
                    SELECT t1.post_id, t1.score
                    FROM $table t1
                    INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                 ) latest",
                ...$post_types
            )
        );

        $avg = intval($stats->avg_score ?? 0);
        $total_analyzed = intval($stats->total ?? 0);
        $good = intval($stats->good ?? 0);
        $ok = intval($stats->ok ?? 0);
        $healthy = $good + $ok;

        $result = [
            'avg_score'        => $avg,
            'grade'            => self::score_to_grade($avg),
            'grade_label'      => self::score_to_label($avg),
            'total_analyzed'   => $total_analyzed,
            'total_posts'      => intval($total_posts),
            'good_count'       => $good,
            'ok_count'         => $ok,
            'healthy_pct'      => $total_analyzed > 0 ? round(($healthy / $total_analyzed) * 100) : 0,
            'analyzed_pct'     => intval($total_posts) > 0 ? min(100, round(($total_analyzed / intval($total_posts)) * 100)) : 0,
        ];

        // Full mode: add needs-work and not-analyzed counts
        if ($mode === 'full') {
            $needs_work = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM (
                        SELECT t1.post_id, t1.score
                        FROM $table t1
                        INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                        WHERE p.post_status = 'publish'
                        AND p.post_type IN ($placeholders)
                        AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                     ) latest
                     WHERE score < 60",
                    ...$post_types
                )
            );
            $result['needs_work_count'] = intval($needs_work);
            $result['not_analyzed']     = max(0, intval($total_posts) - $total_analyzed);
        }

        return $result;
    }

    /**
     * Meta coverage: % of posts with title, description, focus keyword.
     */
    private static function get_meta_coverage($mode = 'positive') {
        global $wpdb;

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        // Exclude posts intentionally hidden from search — they don't need SEO.
        $exclude_noindex = " AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} nx
            WHERE nx.post_id = p.ID
              AND nx.meta_key = '_ssf_noindex'
              AND nx.meta_value = '1'
        )";

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
                 $exclude_noindex",
                ...$post_types
            )
        );

        $total = intval($total);
        if ($total === 0) {
            return ['total' => 0, 'with_title' => 0, 'with_description' => 0, 'with_keyword' => 0];
        }

        $title_key = '_ssf_seo_title';
        $desc_key  = '_ssf_meta_description';
        $kw_key    = '_ssf_focus_keyword';

        $with_title = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
             AND pm.meta_key = %s AND pm.meta_value != ''
             $exclude_noindex",
            ...array_merge($post_types, [$title_key])
        )));

        $with_desc = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
             AND pm.meta_key = %s AND pm.meta_value != ''
             $exclude_noindex",
            ...array_merge($post_types, [$desc_key])
        )));

        $with_kw = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
             AND pm.meta_key = %s AND pm.meta_value != ''
             $exclude_noindex",
            ...array_merge($post_types, [$kw_key])
        )));

        $result = [
            'total'               => $total,
            'with_title'          => $with_title,
            'with_description'    => $with_desc,
            'with_keyword'        => $with_kw,
            'title_pct'           => round(($with_title / $total) * 100),
            'description_pct'     => round(($with_desc / $total) * 100),
            'keyword_pct'         => round(($with_kw / $total) * 100),
        ];

        // Full mode: add missing counts
        if ($mode === 'full') {
            $result['missing_title']       = $total - $with_title;
            $result['missing_description'] = $total - $with_desc;
            $result['missing_keyword']     = $total - $with_kw;
        }

        return $result;
    }

    /**
     * Score distribution — positive mode skips needs-work, full mode includes all.
     */
    private static function get_score_distribution($mode = 'positive') {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    CASE
                        WHEN score >= 90 THEN 'excellent'
                        WHEN score >= 80 THEN 'good'
                        WHEN score >= 70 THEN 'fair'
                        WHEN score >= 60 THEN 'ok'
                        ELSE 'skip'
                    END as bucket,
                    COUNT(*) as cnt
                 FROM (
                    SELECT t1.post_id, t1.score
                    FROM $table t1
                    INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                 ) latest
                 GROUP BY bucket
                 ORDER BY FIELD(bucket, 'excellent', 'good', 'fair', 'ok', 'skip')",
                ...$post_types
            )
        );

        $dist = [];
        $total_count = 0;
        foreach ($rows as $row) {
            if ($row->bucket === 'skip' && $mode === 'positive') {
                continue;
            }
            $cnt = intval($row->cnt);
            $total_count += $cnt;
            $label = $row->bucket === 'skip' ? 'Needs Work' : ucfirst($row->bucket);
            $dist[] = [
                'label'  => $label,
                'count'  => $cnt,
                'bucket' => $row->bucket,
            ];
        }

        // Add percentage
        foreach ($dist as &$item) {
            $item['pct'] = $total_count > 0 ? round(($item['count'] / $total_count) * 100) : 0;
        }

        return $dist;
    }

    /**
     * Top 20 pages by SEO score (only score >= 70).
     * Uses latest score per post to avoid duplicates.
     */
    private static function get_top_pages() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT latest.post_id, latest.score, p.post_title, p.post_type
                 FROM (
                    SELECT t1.post_id, t1.score
                    FROM $table t1
                    INNER JOIN {$wpdb->posts} p2 ON t1.post_id = p2.ID
                    WHERE p2.post_status = 'publish'
                    AND p2.post_type IN ($placeholders)
                    AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                 ) latest
                 INNER JOIN {$wpdb->posts} p ON latest.post_id = p.ID
                 WHERE latest.score >= 70
                 ORDER BY latest.score DESC
                 LIMIT 20",
                ...$post_types
            )
        );

        $pages = [];
        foreach ($rows as $row) {
            $score = intval($row->score);
            $pages[] = [
                'title'     => $row->post_title,
                'url'       => get_permalink($row->post_id),
                'score'     => $score,
                'grade'     => self::score_to_grade($score),
                'post_type' => $row->post_type,
            ];
        }
        return $pages;
    }

    /**
     * Content health: average word count, readability, stats from analyzed posts.
     */
    private static function get_content_health() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total_analyzed' => 0];
        }

        $stats = $wpdb->get_row(
            "SELECT COUNT(*) as total,
                    ROUND(AVG(word_count), 0) as avg_words,
                    ROUND(AVG(flesch_score), 0) as avg_readability,
                    ROUND(AVG(image_count), 1) as avg_images,
                    ROUND(AVG(link_count), 1) as avg_links,
                    SUM(word_count) as total_words
             FROM (
                SELECT t1.post_id, t1.word_count, t1.flesch_score, t1.image_count, t1.link_count
                FROM $table t1
                WHERE t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
             ) latest
             WHERE word_count > 0"
        );

        $avg_read = intval($stats->avg_readability ?? 0);

        return [
            'total_analyzed'   => intval($stats->total ?? 0),
            'avg_word_count'   => intval($stats->avg_words ?? 0),
            'total_words'      => intval($stats->total_words ?? 0),
            'avg_readability'  => $avg_read,
            'readability_label'=> self::readability_label($avg_read),
            'avg_images'       => floatval($stats->avg_images ?? 0),
            'avg_links'        => floatval($stats->avg_links ?? 0),
        ];
    }

    /**
     * Image SEO: alt-text coverage scoped to images actually used by the site.
     *
     * We intentionally exclude orphan uploads (images in the media library that
     * are never referenced in any published content) because including them
     * dilutes the alt-text % with noise the client can't act on.
     *
     * "Used" = attachment is either (a) attached to a published post via
     * post_parent, (b) set as a featured image, or (c) referenced in published
     * content by ID (wp-image-{id} class or ?attachment_id={id}).
     */
    private static function get_image_seo($mode = 'positive') {
        global $wpdb;

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (empty($post_types)) {
            $post_types = ['post', 'page'];
        }
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        // ── Build the set of attachment IDs that are actually "used" ──
        // (a) attached via post_parent to a published post in active types
        $attached_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT a.ID
                 FROM {$wpdb->posts} a
                 INNER JOIN {$wpdb->posts} parent ON a.post_parent = parent.ID
                 WHERE a.post_type = 'attachment'
                 AND a.post_mime_type LIKE 'image/%%'
                 AND parent.post_status = 'publish'
                 AND parent.post_type IN ($placeholders)",
                ...$post_types
            )
        );

        // (b) featured images (_thumbnail_id) of published posts
        $featured_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_value + 0
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_thumbnail_id'
                 AND p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)",
                ...$post_types
            )
        );

        // (c) referenced in content via wp-image-{id}
        $content_ids = [];
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)
                 AND post_content LIKE %s",
                ...array_merge($post_types, ['%wp-image-%'])
            )
        );
        if (is_array($rows)) {
            foreach ($rows as $content) {
                if (empty($content)) continue;
                if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
                    foreach ($m[1] as $id) {
                        $content_ids[] = (int) $id;
                    }
                }
            }
        }

        $used_ids = array_unique(array_filter(array_map('intval', array_merge(
            (array) $attached_ids,
            (array) $featured_ids,
            $content_ids
        ))));

        $total_images = count($used_ids);

        if ($total_images === 0) {
            return [
                'total_images'      => 0,
                'with_alt'          => 0,
                'alt_pct'           => 0,
                'posts_with_images' => 0,
                'scope'             => 'used_in_published_content',
            ];
        }

        // Count used images that have non-empty alt text.
        $id_placeholders = implode(',', array_fill(0, count($used_ids), '%d'));
        $with_alt = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attachment_image_alt'
                 AND meta_value != ''
                 AND post_id IN ($id_placeholders)",
                ...$used_ids
            )
        );

        // Posts with at least one image: detect <img>, Gutenberg image blocks,
        // a featured image, or an image attachment.
        $posts_with_images = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
                 WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
                 AND (
                    p.post_content LIKE '%%<img%%'
                    OR p.post_content LIKE '%%wp:image%%'
                    OR p.post_content LIKE '%%wp-image-%%'
                    OR p.post_content LIKE '%%[gallery%%'
                    OR (pm.meta_value IS NOT NULL AND pm.meta_value != '' AND pm.meta_value != '0')
                 )",
                ...$post_types
            )
        );

        $alt_pct = $total_images > 0 ? round(($with_alt / $total_images) * 100) : 0;

        $result = [
            'total_images'      => $total_images,
            'with_alt'          => $with_alt,
            'alt_pct'           => $alt_pct,
            'posts_with_images' => $posts_with_images,
            'scope'             => 'used_in_published_content',
        ];

        if ($mode === 'full') {
            $result['without_alt'] = $total_images - $with_alt;
        }

        return $result;
    }

    /**
     * Schema coverage: how many posts have schema markup.
     */
    private static function get_schema_coverage() {
        global $wpdb;

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
                ...$post_types
            )
        );

        $with_schema = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 AND pm.meta_key = '_ssf_custom_schema'
                 AND pm.meta_value != ''
                 AND pm.meta_value != '[]'",
                ...$post_types
            )
        );

        $auto_types = [];
        if (class_exists('SSF_Schema')) {
            $auto_types = ['Article (posts)', 'WebPage (pages)', 'BreadcrumbList', 'WebSite', 'Organization'];
        }

        return [
            'total_posts'        => intval($total),
            'custom_schema'      => intval($with_schema),
            'auto_schema_types'  => $auto_types,
            'auto_coverage_note' => !empty($auto_types)
                ? sprintf(__('All %d published pages receive automatic structured data markup.', 'smart-seo-fixer'), intval($total))
                : '',
        ];
    }

    /**
     * Redirect stats.
     */
    private static function get_redirect_stats() {
        $redirects = get_option('ssf_redirects', []);
        $active = array_filter($redirects, function ($r) {
            return !empty($r['enabled']);
        });

        $auto = array_filter($active, function ($r) {
            return !empty($r['auto']);
        });

        return [
            'total_active'  => count($active),
            'auto_created'  => count($auto),
            'manual'        => count($active) - count($auto),
        ];
    }

    /**
     * Keyword highlights — top performing (positive only).
     */
    private static function get_keyword_highlights($dates) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_keyword_tracking';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['top_keywords' => [], 'total_tracked' => 0];
        }

        $top = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword,
                    ROUND(AVG(position), 1) as avg_position,
                    SUM(clicks) as total_clicks,
                    SUM(impressions) as total_impressions
             FROM $table
             WHERE tracked_date >= %s AND tracked_date <= %s
             GROUP BY keyword
             HAVING total_clicks > 0
             ORDER BY total_clicks DESC
             LIMIT 10",
            $dates['start'], $dates['end']
        ));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT keyword) FROM $table WHERE tracked_date >= %s AND tracked_date <= %s",
            $dates['start'], $dates['end']
        ));

        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(clicks) as clicks, SUM(impressions) as impressions
             FROM $table
             WHERE tracked_date >= %s AND tracked_date <= %s",
            $dates['start'], $dates['end']
        ));

        $keywords = [];
        foreach ($top as $kw) {
            $keywords[] = [
                'keyword'     => $kw->keyword,
                'position'    => floatval($kw->avg_position),
                'clicks'      => intval($kw->total_clicks),
                'impressions' => intval($kw->total_impressions),
            ];
        }

        return [
            'top_keywords'      => $keywords,
            'total_tracked'     => intval($total),
            'total_clicks'      => intval($totals->clicks ?? 0),
            'total_impressions' => intval($totals->impressions ?? 0),
        ];
    }

    /**
     * Broken link activity — honest numbers.
     *
     * "Fixed" = links that were previously broken and, on a later re-scan, were
     * found working (rows are deleted from the broken-links table at that
     * moment, and a resolved-log is appended by SSF_Broken_Links). We count
     * entries in that log inside the selected date range.
     *
     * "Dismissed" = links the admin manually hid (not necessarily fixed) —
     * shown separately so the report doesn't conflate the two.
     * "Outstanding" = rows still present and not dismissed (full mode only).
     */
    private static function get_broken_links_fixed($dates, $mode = 'positive') {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_broken_links';

        // Count real fixes inside the date window from the resolved log.
        $fixed_in_range = 0;
        $log = get_option('ssf_broken_links_resolved_log', []);
        if (is_array($log) && !empty($log)) {
            $start_ts = strtotime($dates['start'] . ' 00:00:00');
            $end_ts   = strtotime($dates['end']   . ' 23:59:59');
            foreach ($log as $entry) {
                if (!is_array($entry) || empty($entry['t'])) {
                    continue;
                }
                $ts = strtotime($entry['t']);
                if ($ts === false) {
                    continue;
                }
                if ($ts >= $start_ts && $ts <= $end_ts) {
                    $fixed_in_range++;
                }
            }
        }

        // Lifetime resolved count (for "all time" context).
        $fixed_total = (int) get_option('ssf_broken_links_resolved_total', 0);

        $result = [
            // Period metric — actual links that went from broken → working.
            'fixed'           => $fixed_in_range,
            'fixed_total'     => $fixed_total,
            'period_label'    => $dates['label'],
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            // Dismissed in window (manually hidden by admin).
            $dismissed_in_range = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                 WHERE dismissed = 1
                 AND last_checked >= %s AND last_checked <= %s",
                $dates['start'] . ' 00:00:00',
                $dates['end']   . ' 23:59:59'
            ));
            $result['dismissed'] = $dismissed_in_range;

            if ($mode === 'full') {
                $result['outstanding'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM $table WHERE dismissed = 0"
                );
                $result['total_tracked'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM $table"
                );
            }
        }

        return $result;
    }

    /**
     * Count of SEO optimizations made in the date range (from history).
     */
    private static function get_optimization_count($dates) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_history';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['total' => 0, 'ai_generated' => 0, 'manual' => 0];
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN source = 'ai' THEN 1 ELSE 0 END) as ai,
                    SUM(CASE WHEN source != 'ai' THEN 1 ELSE 0 END) as manual_count,
                    COUNT(DISTINCT post_id) as posts_optimized
             FROM $table
             WHERE created_at >= %s AND created_at <= %s
             AND reverted = 0",
            $dates['start'] . ' 00:00:00',
            $dates['end'] . ' 23:59:59'
        ));

        $breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN field_key LIKE '%%title%%' THEN 'titles'
                    WHEN field_key LIKE '%%description%%' THEN 'descriptions'
                    WHEN field_key LIKE '%%keyword%%' THEN 'keywords'
                    WHEN field_key LIKE '%%schema%%' THEN 'schema'
                    WHEN field_key LIKE '%%og_%%' THEN 'social'
                    ELSE 'other'
                END as category,
                COUNT(*) as cnt
             FROM $table
             WHERE created_at >= %s AND created_at <= %s AND reverted = 0
             GROUP BY category
             ORDER BY cnt DESC",
            $dates['start'] . ' 00:00:00',
            $dates['end'] . ' 23:59:59'
        ));

        $by_type = [];
        foreach ($breakdown as $row) {
            if ($row->category !== 'other' && intval($row->cnt) > 0) {
                $by_type[] = [
                    'category' => ucfirst($row->category),
                    'count'    => intval($row->cnt),
                ];
            }
        }

        return [
            'total'           => intval($stats->total ?? 0),
            'ai_generated'    => intval($stats->ai ?? 0),
            'manual'          => intval($stats->manual_count ?? 0),
            'posts_optimized' => intval($stats->posts_optimized ?? 0),
            'by_type'         => $by_type,
        ];
    }

    /**
     * Sitemap status.
     */
    private static function get_sitemap_status() {
        global $wpdb;

        $url = home_url('/sitemap.xml');
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $indexable_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ssf_noindex'
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 AND (pm.meta_value IS NULL OR pm.meta_value != '1')",
                ...$post_types
            )
        );

        return [
            'url'             => $url,
            'enabled'         => true,
            'indexable_pages'  => intval($indexable_count),
            'post_types'      => $post_types,
        ];
    }

    /**
     * Google Analytics (GA4) summary for the report.
     * Honors the requested date range by computing a lookback window.
     */
    private static function get_analytics($dates) {
        if (!class_exists('SSF_GA_Client')) {
            return ['connected' => false, 'reason' => 'module_unavailable'];
        }
        $ga = new SSF_GA_Client();
        if (!$ga->is_connected() || empty($ga->get_property_id())) {
            return ['connected' => false, 'reason' => 'not_connected'];
        }

        // Convert the report date range to a lookback window in days.
        $days = 30;
        if (!empty($dates['start']) && !empty($dates['end'])) {
            $start = strtotime($dates['start']);
            $end   = strtotime($dates['end']);
            if ($start && $end && $end >= $start) {
                $days = max(1, min(365, (int) round(($end - $start) / 86400) + 1));
            }
        } elseif (!empty($dates['label']) && is_numeric($dates['label'])) {
            $days = (int) $dates['label'];
        }

        $summary = $ga->get_report_summary($days);
        if (is_wp_error($summary)) {
            return [
                'connected' => true,
                'error'     => $summary->get_error_message(),
                'days'      => $days,
            ];
        }

        return array_merge(['connected' => true, 'property' => $ga->get_property_id()], $summary);
    }

    /**
     * Data freshness: tells the client how current the analyzed data is.
     * Without this, snapshot sections (scores, content health, factors) can
     * quietly show months-old numbers — this section flags that explicitly.
     */
    private static function get_data_freshness() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['analyzed_count' => 0];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (empty($post_types)) {
            $post_types = ['post', 'page'];
        }
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $total_posts = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)",
                ...$post_types
            )
        );

        // Freshness buckets based on latest analysis per post.
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as analyzed_count,
                    MAX(last_analyzed) as last_analyzed,
                    SUM(CASE WHEN last_analyzed >= (NOW() - INTERVAL 7 DAY)  THEN 1 ELSE 0 END) as fresh_7d,
                    SUM(CASE WHEN last_analyzed >= (NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) as fresh_30d,
                    SUM(CASE WHEN last_analyzed <  (NOW() - INTERVAL 90 DAY) THEN 1 ELSE 0 END) as stale_90d
                 FROM (
                    SELECT t1.post_id, t1.last_analyzed
                    FROM $table t1
                    INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                    WHERE p.post_status = 'publish'
                    AND p.post_type IN ($placeholders)
                    AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                 ) latest",
                ...$post_types
            )
        );

        $analyzed = (int) ($stats->analyzed_count ?? 0);
        $fresh_30d = (int) ($stats->fresh_30d ?? 0);
        $stale_90d = (int) ($stats->stale_90d ?? 0);
        $never     = max(0, $total_posts - $analyzed);

        $fresh_pct = $total_posts > 0 ? round(($fresh_30d / $total_posts) * 100) : 0;

        // Quality signal so the UI / client can see at a glance.
        if ($total_posts === 0) {
            $quality = 'unknown';
        } elseif ($analyzed === 0) {
            $quality = 'none';
        } elseif ($fresh_pct >= 80) {
            $quality = 'good';
        } elseif ($fresh_pct >= 40) {
            $quality = 'partial';
        } else {
            $quality = 'stale';
        }

        return [
            'total_posts'    => $total_posts,
            'analyzed_count' => $analyzed,
            'never_analyzed' => $never,
            'last_analyzed'  => $stats->last_analyzed ?? null,
            'fresh_7d'       => (int) ($stats->fresh_7d ?? 0),
            'fresh_30d'      => $fresh_30d,
            'stale_over_90d' => $stale_90d,
            'fresh_pct'      => $fresh_pct,
            'quality'        => $quality,
        ];
    }

    /**
     * Score Factors: aggregate the most common issues dragging down SEO scores.
     * Reads the `issues` JSON column from seo_scores to find the most frequent problems.
     */
    private static function get_score_factors() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return ['factors' => []];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        // Get issues JSON for all published posts (latest score per post)
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t1.issues, t1.suggestions, t1.score
                 FROM $table t1
                 INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)",
                ...$post_types
            )
        );

        $total_pages = count($rows);
        if ($total_pages === 0) {
            return ['factors' => [], 'total_pages' => 0];
        }

        // Count how many pages have each issue
        $issue_counts = [];
        foreach ($rows as $row) {
            $issues = json_decode($row->issues, true);
            if (!is_array($issues)) continue;
            $seen = []; // deduplicate per page
            foreach ($issues as $issue) {
                $text = is_array($issue) ? ($issue['message'] ?? ($issue['text'] ?? '')) : (string) $issue;
                if (empty($text) || isset($seen[$text])) continue;
                $seen[$text] = true;
                if (!isset($issue_counts[$text])) {
                    $issue_counts[$text] = 0;
                }
                $issue_counts[$text]++;
            }
        }

        // Sort by frequency
        arsort($issue_counts);

        // Categorize issues
        $categories = [
            'Content'     => ['content', 'word', 'paragraph', 'short', 'thin', 'keyword density', 'first paragraph'],
            'Title'       => ['title', 'seo title'],
            'Description' => ['description', 'meta description'],
            'Keywords'    => ['keyword', 'focus keyword'],
            'Images'      => ['image', 'alt text', 'featured image', 'alt'],
            'Links'       => ['link', 'internal link', 'external link'],
            'Headings'    => ['heading', 'h1', 'h2', 'h3', 'hierarchy'],
            'URL'         => ['slug', 'url', 'permalink'],
            'Readability' => ['readabil', 'flesch', 'sentence', 'passive'],
        ];

        $factors = [];
        $top_issues = array_slice($issue_counts, 0, 10, true);
        foreach ($top_issues as $issue_text => $count) {
            $pct = round(($count / $total_pages) * 100);
            $category = 'Other';
            $text_lower = strtolower($issue_text);
            foreach ($categories as $cat => $keywords) {
                foreach ($keywords as $kw) {
                    if (strpos($text_lower, $kw) !== false) {
                        $category = $cat;
                        break 2;
                    }
                }
            }

            $factors[] = [
                'issue'    => $issue_text,
                'count'    => $count,
                'pct'      => $pct,
                'category' => $category,
            ];
        }

        return [
            'factors'     => $factors,
            'total_pages' => $total_pages,
        ];
    }

    /* ─────────── Full-Mode Sections ─────────── */

    /**
     * Worst 20 pages by SEO score (full mode only).
     */
    private static function get_worst_pages() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssf_seo_scores';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT latest.post_id, latest.score, p.post_title, p.post_type
                 FROM (
                    SELECT t1.post_id, t1.score
                    FROM $table t1
                    INNER JOIN {$wpdb->posts} p2 ON t1.post_id = p2.ID
                    WHERE p2.post_status = 'publish'
                    AND p2.post_type IN ($placeholders)
                    AND t1.id = (SELECT MAX(t2.id) FROM $table t2 WHERE t2.post_id = t1.post_id)
                 ) latest
                 INNER JOIN {$wpdb->posts} p ON latest.post_id = p.ID
                 WHERE latest.score < 60
                 ORDER BY latest.score ASC
                 LIMIT 20",
                ...$post_types
            )
        );

        $pages = [];
        foreach ($rows as $row) {
            $score = intval($row->score);
            $issues = [];
            // Check what's missing for this post
            $title = get_post_meta($row->post_id, '_ssf_seo_title', true);
            $desc  = get_post_meta($row->post_id, '_ssf_meta_description', true);
            $kw    = get_post_meta($row->post_id, '_ssf_focus_keyword', true);
            if (empty($title)) $issues[] = 'Missing SEO title';
            if (empty($desc))  $issues[] = 'Missing meta description';
            if (empty($kw))    $issues[] = 'No focus keyword';

            $pages[] = [
                'title'     => $row->post_title,
                'url'       => get_permalink($row->post_id),
                'score'     => $score,
                'grade'     => self::score_to_grade($score),
                'post_type' => $row->post_type,
                'issues'    => $issues,
            ];
        }
        return $pages;
    }

    /**
     * Aggregate issues and recommendations (full mode only).
     */
    private static function get_issues() {
        global $wpdb;
        $issues = [];

        // 1. Check for pages without SEO titles
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $total = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
                ...$post_types
            )
        ));

        $without_title = $total - intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
             AND pm.meta_key = '_ssf_seo_title' AND pm.meta_value != ''",
            ...$post_types
        )));

        $without_desc = $total - intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)
             AND pm.meta_key = '_ssf_meta_description' AND pm.meta_value != ''",
            ...$post_types
        )));

        if ($without_title > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => sprintf(__('%d pages missing SEO titles', 'smart-seo-fixer'), $without_title),
                'detail'   => __('Custom SEO titles help search engines understand your content. Use AI Bulk Fix to generate them quickly.', 'smart-seo-fixer'),
            ];
        }

        if ($without_desc > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => sprintf(__('%d pages missing meta descriptions', 'smart-seo-fixer'), $without_desc),
                'detail'   => __('Meta descriptions appear in search results and improve click-through rates.', 'smart-seo-fixer'),
            ];
        }

        // 2. Check for unfixed broken links
        $bl_table = $wpdb->prefix . 'ssf_broken_links';
        if ($wpdb->get_var("SHOW TABLES LIKE '$bl_table'") === $bl_table) {
            $unfixed = intval($wpdb->get_var("SELECT COUNT(*) FROM $bl_table WHERE dismissed = 0"));
            if ($unfixed > 0) {
                $issues[] = [
                    'severity' => 'error',
                    'title'    => sprintf(__('%d broken links need attention', 'smart-seo-fixer'), $unfixed),
                    'detail'   => __('Broken links hurt user experience and search rankings. Review and fix them in the Broken Links page.', 'smart-seo-fixer'),
                ];
            }
        }

        // 3. Check for low-scoring pages (only published posts in active types)
        $scores_table = $wpdb->prefix . 'ssf_seo_scores';
        if ($wpdb->get_var("SHOW TABLES LIKE '$scores_table'") === $scores_table) {
            $low_score = intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM (
                        SELECT t1.post_id, t1.score
                        FROM $scores_table t1
                        INNER JOIN {$wpdb->posts} p ON t1.post_id = p.ID
                        WHERE p.post_status = 'publish'
                        AND p.post_type IN ($placeholders)
                        AND t1.id = (SELECT MAX(t2.id) FROM $scores_table t2 WHERE t2.post_id = t1.post_id)
                     ) latest WHERE score < 40",
                    ...$post_types
                )
            ));
            if ($low_score > 0) {
                $issues[] = [
                    'severity' => 'error',
                    'title'    => sprintf(__('%d pages scoring below 40', 'smart-seo-fixer'), $low_score),
                    'detail'   => __('These pages have significant SEO issues. Prioritize them for optimization.', 'smart-seo-fixer'),
                ];
            }

            // 4. Not analyzed pages
            $analyzed = intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.post_id)
                     FROM $scores_table t
                     INNER JOIN {$wpdb->posts} p ON t.post_id = p.ID
                     WHERE p.post_status = 'publish' AND p.post_type IN ($placeholders)",
                    ...$post_types
                )
            ));
            $not_analyzed = $total - $analyzed;
            if ($not_analyzed > 0) {
                $issues[] = [
                    'severity' => 'info',
                    'title'    => sprintf(__('%d pages not yet analyzed', 'smart-seo-fixer'), $not_analyzed),
                    'detail'   => __('Run a bulk analysis to get SEO scores for all your content.', 'smart-seo-fixer'),
                ];
            }
        }

        // 5. Image alt text coverage — check actual attachment metadata
        $imgs_without_alt = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'
             AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );
        if ($imgs_without_alt > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title'    => sprintf(__('%d images missing alt text', 'smart-seo-fixer'), $imgs_without_alt),
                'detail'   => __('Alt text improves accessibility and helps search engines understand your images.', 'smart-seo-fixer'),
            ];
        }

        // Sort: errors first, then warnings, then info
        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, function($a, $b) use ($order) {
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });

        return $issues;
    }

    /* ─────────── Template ─────────── */

    /**
     * Fetch a Google Doc (or any URL) as HTML and cache it.
     *
     * @param string $url The document URL.
     * @return array ['success' => bool, 'html' => string, 'message' => string]
     */
    public static function fetch_template($url) {
        // Validate URL
        $url = esc_url_raw($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => __('Invalid URL.', 'smart-seo-fixer')];
        }

        // Restrict to http/https only (prevent file://, ftp://, etc.)
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['success' => false, 'message' => __('Only http and https URLs are allowed.', 'smart-seo-fixer')];
        }

        // Block SSRF: disallow requests to private/loopback IP ranges
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!empty($host)) {
            $ip = gethostbyname($host);
            if (
                $ip !== false &&
                (
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false ||
                    $ip === '127.0.0.1' || $ip === '::1'
                )
            ) {
                return ['success' => false, 'message' => __('Requests to internal network addresses are not allowed.', 'smart-seo-fixer')];
            }
        }

        // Auto-convert Google Docs edit URL to export URL
        if (preg_match('#docs\.google\.com/document/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            $doc_id = $m[1];
            $url = 'https://docs.google.com/document/d/' . $doc_id . '/export?format=html';
        }

        $response = wp_remote_get($url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (WordPress/' . get_bloginfo('version') . '; Smart SEO Fixer)',
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if ($code === 401 || $code === 403) {
                return ['success' => false, 'message' => __('Access denied. Please make the document publicly viewable (Share > Anyone with the link > Viewer).', 'smart-seo-fixer')];
            }
            return ['success' => false, 'message' => sprintf(__('HTTP error %d.', 'smart-seo-fixer'), $code)];
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html) || strlen($html) < 50) {
            return ['success' => false, 'message' => __('The document appears to be empty.', 'smart-seo-fixer')];
        }

        // Extract just the <style> and <body> content
        $style = '';
        if (preg_match('/<style[^>]*>(.*?)<\/style>/si', $html, $sm)) {
            $style = '<style>' . $sm[1] . '</style>';
        }
        $body = '';
        if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $bm)) {
            $body = $bm[1];
        } else {
            $body = $html;
        }

        $template_html = $style . "\n" . $body;

        // Cache in options
        update_option('ssf_report_template', $template_html);
        update_option('ssf_report_template_url', $url);

        return ['success' => true, 'html' => $template_html, 'message' => __('Template loaded and cached.', 'smart-seo-fixer')];
    }

    /**
     * Clear cached template.
     */
    public static function clear_template() {
        delete_option('ssf_report_template');
        delete_option('ssf_report_template_url');
    }

    /* ─────────── Helpers ─────────── */

    private static function score_to_grade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C+';
        if ($score >= 60) return 'C';
        return 'D';
    }

    private static function score_to_label($score) {
        if ($score >= 90) return __('Excellent', 'smart-seo-fixer');
        if ($score >= 80) return __('Good', 'smart-seo-fixer');
        if ($score >= 70) return __('Fair', 'smart-seo-fixer');
        if ($score >= 60) return __('Needs Improvement', 'smart-seo-fixer');
        return __('Getting Started', 'smart-seo-fixer');
    }

    private static function readability_label($flesch) {
        if ($flesch >= 80) return __('Very Easy to Read', 'smart-seo-fixer');
        if ($flesch >= 60) return __('Easy to Read', 'smart-seo-fixer');
        if ($flesch >= 40) return __('Moderate', 'smart-seo-fixer');
        if ($flesch >= 20) return __('Somewhat Difficult', 'smart-seo-fixer');
        return '';
    }
}
