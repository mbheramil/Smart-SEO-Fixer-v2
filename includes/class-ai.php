<?php
/**
 * AI Provider Factory
 *
 * Routes all AI operations to the configured provider.
 * Supports: AWS Bedrock, OpenAI, Anthropic Claude, Google Gemini.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_AI {

    /**
     * Provider registry: key => class name.
     */
    private static $providers = [
        'bedrock' => 'SSF_Bedrock',
        'openai'  => 'SSF_OpenAI',
        'claude'  => 'SSF_Claude',
        'gemini'  => 'SSF_Gemini',
    ];

    /**
     * Return the slug of the active provider.
     *
     * @return string
     */
    public static function active_provider() {
        $provider = Smart_SEO_Fixer::get_option('ai_provider', 'bedrock');
        return isset(self::$providers[$provider]) ? $provider : 'bedrock';
    }

    /**
     * Get the currently configured AI provider instance.
     *
     * @return SSF_Bedrock|SSF_OpenAI|SSF_Claude|SSF_Gemini
     */
    public static function get() {
        $class = self::$providers[self::active_provider()];
        return new $class();
    }

    /**
     * Check whether the active AI provider is configured (has credentials).
     *
     * @return bool
     */
    public static function is_configured() {
        return self::get()->is_configured();
    }

    /**
     * Human-readable labels for each provider.
     */
    private static $labels = [
        'bedrock' => 'AWS Bedrock',
        'openai'  => 'OpenAI',
        'claude'  => 'Anthropic Claude',
        'gemini'  => 'Google Gemini',
    ];

    /**
     * Return a human-readable label for the active provider.
     *
     * @return string
     */
    public static function provider_label() {
        return self::$labels[self::active_provider()] ?? 'AI';
    }

    /**
     * Return all available providers as slug => label.
     *
     * @return array
     */
    public static function available_providers() {
        return self::$labels;
    }

    /**
     * Return the not-configured error message for the active provider.
     *
     * @return string
     */
    public static function not_configured_message() {
        $label = self::provider_label();
        return sprintf(
            __('%s credentials not configured. Please add your API key in Settings.', 'smart-seo-fixer'),
            $label
        );
    }

    /**
     * Pick a focus keyword that is "grounded" — i.e. actually appears in the
     * post title or content. Prevents the AI from inventing orphan keywords
     * that tank the SEO score because the analyzer can't find them.
     *
     * Strategy:
     *   1. Ask AI for candidates (primary + secondary + long_tail).
     *   2. Return the first candidate that is a substring of title or content.
     *   3. If none match, fall back to the most common 2-4 word phrase
     *      appearing in the title/content (simple n-gram frequency).
     *
     * @param string $content Post content (HTML or plain).
     * @param string $title   Post title.
     * @return string Focus keyword (may be empty string if nothing works).
     */
    public static function pick_grounded_keyword($content, $title = '') {
        $haystack = strtolower(wp_strip_all_tags(strip_shortcodes(
            ($title ? $title . "\n" : '') . $content
        )));

        $candidates = [];

        // Try the AI first.
        $ai = self::get();
        if (method_exists($ai, 'suggest_keywords')) {
            $resp = $ai->suggest_keywords($content, $title);
            if (!is_wp_error($resp) && is_array($resp)) {
                if (!empty($resp['primary'])) {
                    $candidates[] = (string) $resp['primary'];
                }
                if (!empty($resp['secondary']) && is_array($resp['secondary'])) {
                    foreach ($resp['secondary'] as $kw) { $candidates[] = (string) $kw; }
                }
                if (!empty($resp['long_tail']) && is_array($resp['long_tail'])) {
                    foreach ($resp['long_tail'] as $kw) { $candidates[] = (string) $kw; }
                }
            }
        }

        // Return first candidate that actually appears in the content.
        foreach ($candidates as $kw) {
            $kw = trim($kw);
            if ($kw === '') { continue; }
            if (strpos($haystack, strtolower($kw)) !== false) {
                return sanitize_text_field($kw);
            }
        }

        // Fallback: extract most common 2-3 word phrase from title + content.
        $extracted = self::extract_keyword_from_text($haystack);
        if (!empty($extracted)) {
            return sanitize_text_field($extracted);
        }

        // Last resort: return the first AI candidate even if ungrounded, so we
        // still have *something*. Better than empty.
        return !empty($candidates[0]) ? sanitize_text_field(trim($candidates[0])) : '';
    }

    /**
     * Extract the most "keyword-like" 2-3 word phrase from a body of text.
     * Used as a fallback when the AI's suggestions don't appear in the content.
     */
    private static function extract_keyword_from_text($text) {
        if (empty($text)) { return ''; }

        // Common stopwords we never want as a focus keyword.
        $stop = [
            'the','a','an','and','or','but','of','to','in','on','at','by','for',
            'with','from','as','is','are','was','were','be','been','being','have',
            'has','had','do','does','did','will','would','could','should','may',
            'might','must','can','this','that','these','those','it','its','our',
            'we','you','your','they','them','their','he','she','his','her','him',
            'i','my','me','us','if','then','than','so','not','no','yes','about',
            'into','over','under','out','up','down','all','any','some','such',
            'only','also','more','most','other','same','too','very','just','now',
            'when','where','why','how','what','who','whom','which','while','what','after','before','because','during','through','between'
        ];

        // Normalize to alphanumeric + spaces
        $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $words = array_values(array_filter(explode(' ', trim($text)), function($w) use ($stop) {
            return strlen($w) >= 3 && !in_array($w, $stop, true) && !is_numeric($w);
        }));

        if (count($words) < 2) {
            return !empty($words[0]) ? $words[0] : '';
        }

        // Build bigrams and trigrams, count frequency.
        $ngrams = [];
        $count  = count($words);
        for ($i = 0; $i < $count - 1; $i++) {
            $bi = $words[$i] . ' ' . $words[$i + 1];
            if (!isset($ngrams[$bi])) { $ngrams[$bi] = 0; }
            $ngrams[$bi]++;
            if ($i < $count - 2) {
                $tri = $bi . ' ' . $words[$i + 2];
                if (!isset($ngrams[$tri])) { $ngrams[$tri] = 0; }
                // Slight bias toward trigrams (more specific = better SEO keyword).
                $ngrams[$tri] += 2;
            }
        }

        if (empty($ngrams)) { return $words[0]; }

        arsort($ngrams);
        return (string) key($ngrams);
    }

    /**
     * Error code every provider uses when it cannot actually LOOK at an image.
     *
     * Alt text is the one operation where a text-only model is worse than
     * useless: asked to "describe what is visible" while given nothing but a
     * URL, it invents contents. Providers therefore fail loudly with this code
     * instead of quietly answering from the file slug, so the caller can fall
     * back to the deterministic filename heuristic and tell the user why.
     */
    const VISION_ERROR = 'ssf_vision_unavailable';

    /**
     * Build the standard "cannot see the image" error.
     *
     * @param string $reason  Machine-readable cause (see vision_status()).
     * @param string $message Human-readable explanation.
     * @param string $detail  Optional underlying error text.
     * @return WP_Error
     */
    public static function vision_error($reason, $message, $detail = '') {
        return new WP_Error(self::VISION_ERROR, $message, [
            'reason' => $reason,
            'detail' => $detail,
        ]);
    }

    /**
     * Report whether the active provider can actually see images.
     *
     * Returns the same shape in every case:
     *   ok       bool    Vision requests should work.
     *   reason   string  '' when ok, else not_configured|model_not_vision|no_support
     *   message  string  '' when ok, else what to do about it.
     *   provider string  Human-readable provider name.
     *   model    string  The model ID that would be used.
     *
     * @return array
     */
    public static function vision_status() {
        $label = self::provider_label();

        if (!isset(self::$providers[self::active_provider()])) {
            return [
                'ok'       => false,
                'reason'   => 'not_configured',
                'message'  => __('No AI provider selected.', 'smart-seo-fixer'),
                'provider' => $label,
                'model'    => '',
            ];
        }

        $provider = self::get();
        $model    = method_exists($provider, 'get_model_id') ? (string) $provider->get_model_id() : '';

        if (!$provider->is_configured()) {
            return [
                'ok'       => false,
                'reason'   => 'not_configured',
                'message'  => self::not_configured_message(),
                'provider' => $label,
                'model'    => $model,
            ];
        }

        // A provider without the capability probe predates this check; assume
        // it can see rather than disabling a setup that already works.
        if (method_exists($provider, 'supports_vision') && !$provider->supports_vision()) {
            return [
                'ok'       => false,
                'reason'   => 'model_not_vision',
                'message'  => sprintf(
                    /* translators: 1: provider name, 2: model ID */
                    __('The selected %1$s model (%2$s) cannot analyse images. Switch to a vision-capable model in Settings to get real descriptions.', 'smart-seo-fixer'),
                    $label,
                    $model !== '' ? $model : __('unknown', 'smart-seo-fixer')
                ),
                'provider' => $label,
                'model'    => $model,
            ];
        }

        return [
            'ok'       => true,
            'reason'   => '',
            'message'  => '',
            'provider' => $label,
            'model'    => $model,
        ];
    }

    /**
     * Fetch an image by URL (or attachment ID) and return base64-encoded
     * bytes + media type, suitable for Claude / Bedrock / OpenAI vision
     * multimodal messages.
     *
     * Prefers reading the file from the local filesystem (much faster and
     * avoids outbound HTTP on single-site installs). Falls back to
     * wp_remote_get() for external URLs.
     *
     * Returns ['data' => base64_string, 'media_type' => 'image/jpeg']
     * or a WP_Error on failure.
     *
     * @param string|int $url_or_id
     * @return array|WP_Error
     */
    public static function fetch_image_as_base64($url_or_id) {
        $bytes     = null;
        $media     = null;

        // Attachment ID path
        if (is_numeric($url_or_id)) {
            $att_id = (int) $url_or_id;
            $path   = get_attached_file($att_id);
            if ($path && file_exists($path)) {
                $bytes = @file_get_contents($path);
                $media = get_post_mime_type($att_id) ?: null;
            }
            if (empty($bytes)) {
                $url_or_id = wp_get_attachment_url($att_id);
            }
        }

        // URL path
        if (empty($bytes) && is_string($url_or_id) && $url_or_id !== '') {
            // Try mapping the URL to a local file first.
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['baseurl']) && strpos($url_or_id, $upload_dir['baseurl']) === 0) {
                $relative = ltrim(substr($url_or_id, strlen($upload_dir['baseurl'])), '/');
                $local    = trailingslashit($upload_dir['basedir']) . $relative;
                if (file_exists($local)) {
                    $bytes = @file_get_contents($local);
                    if (function_exists('wp_check_filetype')) {
                        $ft    = wp_check_filetype($local);
                        $media = $ft['type'] ?? null;
                    }
                }
            }

            // Fallback: outbound HTTP fetch
            if (empty($bytes)) {
                $response = wp_remote_get($url_or_id, ['timeout' => 30]);
                if (is_wp_error($response)) {
                    return $response;
                }
                $code = wp_remote_retrieve_response_code($response);
                if ($code >= 400) {
                    return new WP_Error('image_fetch_failed', "HTTP {$code} fetching image");
                }
                $bytes = wp_remote_retrieve_body($response);
                $media = wp_remote_retrieve_header($response, 'content-type');
            }
        }

        if (empty($bytes)) {
            return new WP_Error('image_empty', __('Unable to read image bytes.', 'smart-seo-fixer'));
        }

        // Normalize media type.
        if (empty($media) || strpos($media, 'image/') !== 0) {
            // Sniff from magic bytes.
            $header = substr($bytes, 0, 12);
            if (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
                $media = 'image/jpeg';
            } elseif (strncmp($header, "\x89PNG\r\n\x1a\n", 8) === 0) {
                $media = 'image/png';
            } elseif (strncmp($header, 'GIF8', 4) === 0) {
                $media = 'image/gif';
            } elseif (strncmp(substr($header, 0, 4), 'RIFF', 4) === 0 && strncmp(substr($header, 8, 4), 'WEBP', 4) === 0) {
                $media = 'image/webp';
            } else {
                $media = 'image/jpeg';
            }
        }
        $media = strtolower(trim(explode(';', $media)[0]));

        // Claude vision accepts only jpeg/png/gif/webp. Reject anything else.
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($media, $allowed, true)) {
            return new WP_Error('image_unsupported_type', sprintf(__('Unsupported image type: %s', 'smart-seo-fixer'), $media));
        }

        // Claude/Bedrock vision hard limit is ~5 MB per image. Downscale if
        // needed using WP's image editor so we don't blow the request budget.
        $max_bytes = 4 * 1024 * 1024; // 4 MB safety margin
        if (strlen($bytes) > $max_bytes) {
            $tmp = wp_tempnam('ssf_img');
            if ($tmp && @file_put_contents($tmp, $bytes)) {
                $editor = function_exists('wp_get_image_editor') ? wp_get_image_editor($tmp) : null;
                if ($editor && !is_wp_error($editor)) {
                    $editor->resize(1568, 1568, false); // Claude's recommended max edge
                    $editor->set_quality(82);
                    $saved = $editor->save($tmp, 'image/jpeg');
                    if (!is_wp_error($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
                        $bytes = @file_get_contents($saved['path']);
                        $media = 'image/jpeg';
                        @unlink($saved['path']);
                    }
                }
                if (file_exists($tmp)) { @unlink($tmp); }
            }
        }

        return [
            'data'       => base64_encode($bytes),
            'media_type' => $media,
        ];
    }
}
