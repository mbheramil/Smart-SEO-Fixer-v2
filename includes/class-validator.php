<?php
/**
 * Input Validator
 * 
 * Centralized sanitization and validation for all plugin inputs.
 * Use this instead of inline sanitization to ensure consistency.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Validator {
    
    /**
     * Sanitize and validate an SEO title
     * 
     * @param string $title Raw input
     * @return string|WP_Error Clean title or error
     */
    public static function seo_title($title) {
        $title = sanitize_text_field(trim($title));
        
        if (empty($title)) {
            return '';
        }
        
        // Strip any remaining HTML entities that shouldn't be in titles
        $title = wp_strip_all_tags($title);
        
        // Limit length (Google typically shows 50-60 chars)
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        
        return $title;
    }
    
    /**
     * Sanitize and validate a meta description
     */
    public static function meta_description($desc) {
        $desc = sanitize_textarea_field(trim($desc));
        
        if (empty($desc)) {
            return '';
        }
        
        // Strip tags
        $desc = wp_strip_all_tags($desc);
        
        // Collapse whitespace
        $desc = preg_replace('/\s+/', ' ', $desc);
        
        // Limit length (Google typically shows 150-160 chars)
        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 500);
        }
        
        return $desc;
    }
    
    /**
     * Sanitize a focus keyword
     */
    public static function focus_keyword($keyword) {
        $keyword = sanitize_text_field(trim($keyword));
        
        // Keywords shouldn't be too long
        if (mb_strlen($keyword) > 100) {
            $keyword = mb_substr($keyword, 0, 100);
        }
        
        return $keyword;
    }

    /**
     * Hard-enforce SEO title length (Google display limit = 60 chars).
     * Truncates on a word boundary and trims trailing punctuation.
     *
     * @param string $title
     * @param int    $max
     * @return string
     */
    public static function enforce_seo_title($title, $max = 60) {
        $title = trim(wp_strip_all_tags((string) $title));
        $title = preg_replace('/\s+/', ' ', $title);
        if (mb_strlen($title) <= $max) {
            return $title;
        }
        $truncated = mb_substr($title, 0, $max);
        // Prefer word boundary if it's not too short (keep at least 70% of max).
        $last_space = mb_strrpos($truncated, ' ');
        if ($last_space !== false && $last_space >= (int) round($max * 0.7)) {
            $truncated = mb_substr($truncated, 0, $last_space);
        }
        return rtrim($truncated, " \t\n\r\0\x0B.,;:|\"'-");
    }

    /**
     * Hard-enforce meta description length (Google display limit = 160 chars).
     * Truncates on a word boundary and trims trailing punctuation.
     *
     * @param string $desc
     * @param int    $max
     * @return string
     */
    public static function enforce_meta_description($desc, $max = 160) {
        $desc = trim(wp_strip_all_tags((string) $desc));
        $desc = preg_replace('/\s+/', ' ', $desc);
        if (mb_strlen($desc) <= $max) {
            return $desc;
        }
        $truncated = mb_substr($desc, 0, $max);
        $last_space = mb_strrpos($truncated, ' ');
        if ($last_space !== false && $last_space >= (int) round($max * 0.7)) {
            $truncated = mb_substr($truncated, 0, $last_space);
        }
        return rtrim($truncated, " \t\n\r\0\x0B.,;:|\"'-");
    }

    /**
     * Count real content words in a post.
     * Strips shortcodes, captions, tags, and collapses whitespace first so
     * that image-only or block-heavy posts get an honest count.
     *
     * @param WP_Post|int|string $post_or_content
     * @return int
     */
    public static function get_content_word_count($post_or_content) {
        if ($post_or_content instanceof WP_Post) {
            $content = $post_or_content->post_content;
        } elseif (is_numeric($post_or_content)) {
            $p = get_post((int) $post_or_content);
            $content = $p ? $p->post_content : '';
        } else {
            $content = (string) $post_or_content;
        }
        if ($content === '') {
            return 0;
        }
        $content = strip_shortcodes($content);
        $content = preg_replace('#\[caption[^\]]*\].*?\[/caption\]#is', '', $content);
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        $content = preg_replace('/\s+/u', ' ', trim($content));
        if ($content === '') {
            return 0;
        }
        return str_word_count($content);
    }

    /**
     * Is this post "thin content" — i.e. below the word threshold with no
     * redeeming image alt/caption text to describe it?
     *
     * @param WP_Post|int $post
     * @param int         $threshold
     * @return bool
     */
    public static function is_thin_content($post, $threshold = 50) {
        if (is_numeric($post)) {
            $post = get_post((int) $post);
        }
        if (!$post instanceof WP_Post) {
            return false;
        }
        $words = self::get_content_word_count($post);
        if ($words >= $threshold) {
            return false;
        }
        // Count the image alt/caption text as content too — if the post is
        // mostly images WITH good alt text, it isn't thin in Google's eyes.
        $image_ctx = self::extract_image_seo_context($post);
        $image_words = str_word_count($image_ctx);
        return ($words + $image_words) < $threshold;
    }

    /**
     * Pull alt text, captions, and title attributes from every image/gallery
     * referenced in the post. Useful for feeding AI on image-only posts so the
     * generated title/description is actually relevant.
     *
     * @param WP_Post|int $post
     * @return string Space-separated alt/caption text.
     */
    public static function extract_image_seo_context($post) {
        if (is_numeric($post)) {
            $post = get_post((int) $post);
        }
        if (!$post instanceof WP_Post) {
            return '';
        }
        $parts = [];
        $content = $post->post_content;

        // 1. <img alt="..."> and title="..." inside content.
        if (preg_match_all('/<img\b[^>]*>/i', $content, $imgs)) {
            foreach ($imgs[0] as $img) {
                if (preg_match('/\salt\s*=\s*"([^"]*)"/i', $img, $m) && !empty($m[1])) {
                    $parts[] = $m[1];
                }
                if (preg_match("/\salt\s*=\s*'([^']*)'/i", $img, $m) && !empty($m[1])) {
                    $parts[] = $m[1];
                }
                if (preg_match('/\stitle\s*=\s*"([^"]*)"/i', $img, $m) && !empty($m[1])) {
                    $parts[] = $m[1];
                }
            }
        }

        // 2. [caption]...[/caption] text.
        if (preg_match_all('#\[caption[^\]]*\](.*?)\[/caption\]#is', $content, $caps)) {
            foreach ($caps[1] as $cap) {
                $cap = wp_strip_all_tags($cap);
                if ($cap !== '') {
                    $parts[] = $cap;
                }
            }
        }

        // 3. Attached media — alt/caption/title/description on each attachment.
        $attachments = get_children([
            'post_parent' => $post->ID,
            'post_type'   => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 20,
        ]);
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                $alt = get_post_meta($att->ID, '_wp_attachment_image_alt', true);
                if (!empty($alt)) {
                    $parts[] = $alt;
                }
                if (!empty($att->post_excerpt)) {
                    $parts[] = $att->post_excerpt;
                }
                if (!empty($att->post_title)) {
                    // De-filename the title: "IMG_1234" -> skip, but keep real titles.
                    if (!preg_match('/^(IMG[_\-]?\d+|DSC[_\-]?\d+|image\d*|photo\d*)$/i', $att->post_title)) {
                        $parts[] = $att->post_title;
                    }
                }
                if (!empty($att->post_content)) {
                    $parts[] = $att->post_content;
                }
            }
        }

        // 4. Featured image alt/caption.
        $thumb_id = get_post_thumbnail_id($post->ID);
        if ($thumb_id) {
            $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
            if (!empty($alt)) {
                $parts[] = $alt;
            }
            $thumb = get_post($thumb_id);
            if ($thumb) {
                if (!empty($thumb->post_excerpt)) {
                    $parts[] = $thumb->post_excerpt;
                }
            }
        }

        $parts = array_unique(array_filter(array_map('trim', $parts)));
        return implode(' ', $parts);
    }

    /**
     * Validate and sanitize a URL
     */
    public static function url($url) {
        $url = trim($url);
        
        if (empty($url)) {
            return '';
        }
        
        $url = esc_url_raw($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        return $url;
    }
    
    /**
     * Validate a post ID
     * Returns the valid post ID or 0 if invalid.
     */
    public static function post_id($id) {
        $id = absint($id);
        
        if ($id <= 0) {
            return 0;
        }
        
        return $id;
    }
    
    /**
     * Validate and sanitize an array of post IDs
     */
    public static function post_ids($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        
        $clean = array_map('absint', $ids);
        $clean = array_filter($clean, function($id) {
            return $id > 0;
        });
        
        return array_values($clean);
    }
    
    /**
     * Sanitize an API key (preserve special chars but strip whitespace)
     */
    public static function api_key($key) {
        $key = trim($key);
        
        // API keys should only contain printable ASCII
        $key = preg_replace('/[^\x20-\x7E]/', '', $key);
        
        return $key;
    }
    
    /**
     * Validate an OpenAI API key format
     */
    public static function is_valid_openai_key($key) {
        $key = self::api_key($key);
        
        // OpenAI keys start with 'sk-' and are typically 40-60 chars
        return !empty($key) && (
            strpos($key, 'sk-') === 0 || 
            strpos($key, 'sk-proj-') === 0
        );
    }
    
    /**
     * Sanitize schema JSON
     */
    public static function schema_json($json) {
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return '';
            }
            return wp_json_encode($decoded);
        }
        
        if (is_array($json)) {
            return wp_json_encode($json);
        }
        
        return '';
    }
    
    /**
     * Sanitize redirect settings
     */
    public static function redirect_url($from, $to) {
        $from = self::url($from);
        $to   = self::url($to);
        
        if (empty($from) || empty($to)) {
            return new WP_Error('invalid_redirect', __('Both source and target URLs are required.', 'smart-seo-fixer'));
        }
        
        if ($from === $to) {
            return new WP_Error('redirect_loop', __('Source and target URLs cannot be the same.', 'smart-seo-fixer'));
        }
        
        return ['from' => $from, 'to' => $to];
    }
    
    /**
     * Sanitize post type selection
     */
    public static function post_types($types) {
        if (!is_array($types)) {
            return ['post', 'page'];
        }
        
        $valid = get_post_types(['public' => true]);
        $clean = array_filter($types, function($type) use ($valid) {
            return isset($valid[$type]);
        });
        
        return !empty($clean) ? array_values($clean) : ['post', 'page'];
    }
    
    /**
     * Sanitize a title separator
     */
    public static function title_separator($sep) {
        $allowed = ['|', '-', '—', '·', '>', '»', '/', '\\'];
        $sep = trim($sep);
        
        return in_array($sep, $allowed) ? $sep : '|';
    }
    
    /**
     * Sanitize robots meta value
     */
    public static function robots_meta($value) {
        $allowed = ['index', 'noindex', 'follow', 'nofollow', 'noindex, nofollow', 'index, follow'];
        return in_array($value, $allowed) ? $value : '';
    }
    
    /**
     * Validate pagination parameters
     */
    public static function pagination($page, $per_page, $max_per_page = 100) {
        return [
            'page'     => max(1, absint($page)),
            'per_page' => min($max_per_page, max(1, absint($per_page))),
        ];
    }
    
    /**
     * Sanitize search query
     */
    public static function search_query($query) {
        $query = sanitize_text_field(trim($query));
        
        // Limit length
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }
        
        return $query;
    }
    
    /**
     * Validate AJAX request (nonce + capability in one call)
     * Returns true or sends wp_send_json_error and dies.
     */
    public static function verify_ajax($capability = 'edit_posts') {
        if (!check_ajax_referer('ssf_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        return true;
    }
}
