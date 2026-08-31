<?php
/**
 * Image SEO Class
 * 
 * Enhances image output with lazy loading, missing width/height attributes,
 * and ensures all images have proper alt text for accessibility and SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Image_SEO {
    
    /**
     * Initialize frontend image optimization hooks
     */
    /**
     * Cron hook used to generate alt text just after an upload completes.
     */
    const UPLOAD_CRON_HOOK = 'ssf_generate_alt_for_upload';

    public static function init() {
        // The deferred generator must be registered in every context — WP-Cron
        // requests are not admin requests.
        add_action(self::UPLOAD_CRON_HOOK, [__CLASS__, 'auto_alt_on_upload']);

        if (is_admin()) {
            // Auto alt text on upload
            if (Smart_SEO_Fixer::get_option('auto_alt_text', false)) {
                add_action('add_attachment', [__CLASS__, 'schedule_alt_on_upload']);
            }
            return;
        }
        
        // Filter post content to add missing image attributes
        add_filter('the_content', [__CLASS__, 'optimize_content_images'], 99);
        
        // Filter post thumbnails
        add_filter('post_thumbnail_html', [__CLASS__, 'optimize_single_image'], 99);
        
        // Add native lazy loading to all images (WordPress 5.5+ does this for some,
        // but we catch any that slip through)
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'add_lazy_load_attr'], 10, 3);
    }
    
    /**
     * Process all images in post content
     * - Add missing width/height from attachment metadata
     * - Add loading="lazy" for below-fold images
     * - Add decoding="async" for performance
     */
    public static function optimize_content_images($content) {
        if (empty($content)) {
            return $content;
        }
        
        // Match all <img> tags
        if (!preg_match_all('/<img\s[^>]+>/i', $content, $matches)) {
            return $content;
        }
        
        $first_image = true;
        
        foreach ($matches[0] as $img_tag) {
            $new_tag = $img_tag;
            
            // Add loading="lazy" if not already present (skip first image — likely LCP)
            if ($first_image) {
                $first_image = false;
                // First image should load eagerly (likely LCP candidate)
                if (strpos($new_tag, 'loading=') === false) {
                    $new_tag = str_replace('<img ', '<img loading="eager" ', $new_tag);
                }
            } else {
                if (strpos($new_tag, 'loading=') === false) {
                    $new_tag = str_replace('<img ', '<img loading="lazy" ', $new_tag);
                }
            }
            
            // Add decoding="async" if not present
            if (strpos($new_tag, 'decoding=') === false) {
                $new_tag = str_replace('<img ', '<img decoding="async" ', $new_tag);
            }
            
            // Add missing width/height
            if (strpos($new_tag, 'width=') === false || strpos($new_tag, 'height=') === false) {
                $new_tag = self::add_dimensions($new_tag);
            }
            
            if ($new_tag !== $img_tag) {
                $content = str_replace($img_tag, $new_tag, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Optimize a single image tag (thumbnails, etc.)
     */
    public static function optimize_single_image($html) {
        if (empty($html)) {
            return $html;
        }
        
        // Add decoding="async" if not present
        if (strpos($html, 'decoding=') === false) {
            $html = str_replace('<img ', '<img decoding="async" ', $html);
        }
        
        return $html;
    }
    
    /**
     * Add lazy loading attribute to attachment images.
     */
    public static function add_lazy_load_attr($attr, $attachment, $size) {
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }
        return $attr;
    }
    
    /**
     * Try to add missing width/height attributes by resolving the image.
     * First checks WordPress attachment metadata, then falls back to getimagesize
     * for local files only (no remote calls).
     */
    private static function add_dimensions($img_tag) {
        // Already has both? Skip
        if (strpos($img_tag, ' width=') !== false && strpos($img_tag, ' height=') !== false) {
            return $img_tag;
        }
        
        // Extract src
        if (!preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
            return $img_tag;
        }
        
        $src = $src_match[1];
        $width = 0;
        $height = 0;
        
        // Try to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($src);
        
        if ($attachment_id > 0) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if ($meta && !empty($meta['width']) && !empty($meta['height'])) {
                $width = $meta['width'];
                $height = $meta['height'];
                
                // Check if it's a specific size (e.g., -300x200.jpg)
                if (preg_match('/-(\d+)x(\d+)\.[a-z]+$/i', $src, $size_match)) {
                    $width = intval($size_match[1]);
                    $height = intval($size_match[2]);
                }
            }
        }
        
        // Fallback: try getimagesize for local files only
        if (($width === 0 || $height === 0) && strpos($src, home_url()) === 0) {
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $src);
            
            if (file_exists($relative_path)) {
                $size_info = @getimagesize($relative_path);
                if ($size_info) {
                    $width = $size_info[0];
                    $height = $size_info[1];
                }
            }
        }
        
        if ($width > 0 && $height > 0) {
            if (strpos($img_tag, ' width=') === false) {
                $img_tag = str_replace('<img ', '<img width="' . intval($width) . '" ', $img_tag);
            }
            if (strpos($img_tag, ' height=') === false) {
                $img_tag = str_replace('<img ', '<img height="' . intval($height) . '" ', $img_tag);
            }
        }
        
        return $img_tag;
    }
    
    /**
     * Scan a post and return images missing alt text or dimensions.
     * Used by the analyzer/dashboard for reporting (not for auto-fix).
     * 
     * @param int $post_id
     * @return array List of image issues
     */
    public static function audit_post_images($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }
        
        $issues = [];
        $content = $post->post_content;
        
        if (!preg_match_all('/<img\s[^>]+>/i', $content, $matches)) {
            return $issues;
        }
        
        foreach ($matches[0] as $img_tag) {
            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $m)) {
                $src = $m[1];
            }
            
            $has_alt = preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
            $alt_text = $has_alt ? $alt_match[1] : '';
            $has_width = strpos($img_tag, ' width=') !== false;
            $has_height = strpos($img_tag, ' height=') !== false;
            $has_lazy = strpos($img_tag, 'loading=') !== false;
            
            $img_issues = [];
            
            if (!$has_alt || empty(trim($alt_text))) {
                $img_issues[] = 'missing_alt';
            }
            if (!$has_width || !$has_height) {
                $img_issues[] = 'missing_dimensions';
            }
            
            if (!empty($img_issues)) {
                $issues[] = [
                    'src'     => $src,
                    'issues'  => $img_issues,
                    'has_alt' => !empty(trim($alt_text)),
                    'has_dimensions' => $has_width && $has_height,
                    'has_lazy' => $has_lazy,
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Distinctive camera / device filename prefixes. Safe to strip when
     * followed by a separator OR a digit ("IMG_1234", "DSC 0001",
     * "IMG_beach-sunset") because no ordinary English word starts this way.
     *
     * A prefix immediately followed by LETTERS is never stripped — "imgur"
     * and "vidalia" keep their leading characters.
     */
    const FILENAME_NOISE_PREFIXES = ['IMG', 'DSCN', 'DSCF', 'DSC', 'MOV', 'VID', 'PXL'];

    /**
     * Short, ambiguous prefixes that are ALSO real words or abbreviations
     * ("P", "DC" as in direct current, "WP" as in WordPress). These are only
     * stripped when immediately followed by DIGITS ("P1010101", "DC0001") —
     * never before a separator, so "dc-power" and "wp-content" survive.
     *
     * The old code stripped these before letters too, which turned
     * "party-rentals" into "Arty Rentals" and "photo-booth" into "Hoto Booth".
     */
    const FILENAME_AMBIGUOUS_PREFIXES = ['PXL', 'WP', 'DC', 'P'];

    /**
     * Generate alt text from an image filename.
     * Strips extension, replaces separators with spaces, capitalizes words.
     *
     * This is a last-resort fallback used only when no vision-capable AI is
     * available. A filename is not a description, so callers should prefer
     * generate_alt_for_attachment() which actually looks at the image.
     *
     * @param string $filename  The image filename (e.g., "bounce-house_rental-nj.jpg")
     * @return string           Cleaned alt text (e.g., "Bounce House Rental Nj")
     */
    public static function generate_alt_from_filename($filename) {
        // Remove extension
        $name = pathinfo($filename, PATHINFO_FILENAME);
        // Remove size suffix like -300x200
        $name = preg_replace('/-\d+x\d+$/', '', $name);
        // Drop "scaled"/"rotated" suffixes WordPress appends
        $name = preg_replace('/[-_](scaled|rotated|copy|final|edited)$/i', '', $name);

        // Strip camera noise prefixes BEFORE separators collapse, so we can
        // still tell "IMG_1234" (prefix + digits) from "important-notice"
        // (ordinary word). Distinctive prefixes may be followed by a separator
        // or a digit; never by another letter.
        $distinct = implode('|', self::FILENAME_NOISE_PREFIXES);
        $name = preg_replace('/^(?:' . $distinct . ')(?=[\s\-_.]|\d)[\s\-_.]*/i', '', $name);

        // Ambiguous short prefixes only when glued directly to digits.
        $ambiguous = implode('|', self::FILENAME_AMBIGUOUS_PREFIXES);
        $name = preg_replace('/^(?:' . $ambiguous . ')(?=\d)[\s\-_.]*/i', '', $name);

        // Replace separators with spaces
        $name = str_replace(['-', '_', '.', '+'], ' ', $name);
        // Remove extra spaces
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Drop leading date/sequence numbers ("20240101 123456 beach")
        $name = preg_replace('/^(\d+\s+)+/', '', $name);
        // A name made only of digits/separators carries no meaning at all.
        if (preg_match('/^[\d\s]*$/', $name)) {
            return '';
        }

        // Capitalize words
        $name = ucwords(strtolower($name));
        return trim($name);
    }

    /**
     * Build the contextual hint sent alongside an image so the model can
     * ground its description in the page the image is actually used on.
     *
     * @param int $attachment_id
     * @return array ['context' => string, 'keyword' => string]
     */
    private static function attachment_context($attachment_id) {
        $context = [];

        $att = get_post($attachment_id);
        if ($att) {
            if (!empty($att->post_title))   { $context[] = 'Image title: ' . $att->post_title; }
            if (!empty($att->post_excerpt)) { $context[] = 'Caption: ' . $att->post_excerpt; }
        }

        $keyword = '';
        $parent  = $att && $att->post_parent ? get_post($att->post_parent) : null;
        if ($parent) {
            $context[] = 'Used on page: ' . $parent->post_title;
            $keyword = (string) get_post_meta($parent->ID, '_ssf_focus_keyword', true);
        }

        // Site name gives the model useful domain context (e.g. an
        // entertainment company vs. a law firm) without inventing facts.
        $site = get_bloginfo('name');
        if (!empty($site)) { $context[] = 'Website: ' . $site; }

        return [
            'context' => implode("\n", $context),
            'keyword' => $keyword,
        ];
    }

    /**
     * Generate alt text for a single attachment using a vision-capable AI,
     * falling back to the filename heuristic when AI is unavailable or fails.
     *
     * When the AI path is not taken, the returned 'reason' says WHY, so callers
     * can tell the user instead of silently shipping filename-derived text that
     * looks like it came from a real description. Reasons:
     *   ai_disabled      Caller asked for filename mode.
     *   not_configured   No provider credentials.
     *   model_not_vision The selected model cannot see images.
     *   image_unreadable The file could not be read or fetched.
     *   ai_empty         The model replied with nothing usable.
     *   ai_error         The API call failed (rate limit, auth, network).
     *   no_filename      No filename to fall back to either.
     *
     * @param int  $attachment_id
     * @param bool $use_ai  Whether to attempt an AI vision call.
     * @return array ['alt' => string, 'source' => 'ai'|'filename'|'none', 'error' => string, 'reason' => string]
     */
    public static function generate_alt_for_attachment($attachment_id, $use_ai = true) {
        $attachment_id = (int) $attachment_id;
        $error         = '';
        $reason        = '';

        if (!$use_ai) {
            $reason = 'ai_disabled';
        } elseif (!class_exists('SSF_AI')) {
            $reason = 'not_configured';
            $error  = __('AI provider unavailable.', 'smart-seo-fixer');
        } else {
            $vision = SSF_AI::vision_status();

            if (!$vision['ok']) {
                // No credentials, or a model that cannot see images. Either way
                // the AI cannot describe this picture — say so rather than
                // passing it a URL and accepting whatever it invents.
                $reason = $vision['reason'];
                $error  = $vision['message'];
            } else {
                $provider = SSF_AI::get();
                $url      = wp_get_attachment_url($attachment_id);

                if (!$provider || !method_exists($provider, 'generate_alt_text')) {
                    $reason = 'not_configured';
                    $error  = __('AI provider does not support image descriptions.', 'smart-seo-fixer');
                } elseif (empty($url)) {
                    $reason = 'image_unreadable';
                    $error  = __('The attachment has no URL.', 'smart-seo-fixer');
                } else {
                    $ctx    = self::attachment_context($attachment_id);
                    $result = $provider->generate_alt_text($url, $ctx['context'], $ctx['keyword']);

                    if (!is_wp_error($result)) {
                        $alt = self::sanitize_generated_alt($result);
                        if ($alt !== '') {
                            return ['alt' => $alt, 'source' => 'ai', 'error' => '', 'reason' => ''];
                        }
                        $reason = 'ai_empty';
                        $error  = __('AI returned an empty description.', 'smart-seo-fixer');
                    } else {
                        $error = $result->get_error_message();
                        // Providers report "I could not see the image" with a
                        // dedicated code carrying the specific cause.
                        if ($result->get_error_code() === SSF_AI::VISION_ERROR) {
                            $data   = $result->get_error_data();
                            $reason = is_array($data) && !empty($data['reason']) ? $data['reason'] : 'image_unreadable';
                        } else {
                            $reason = 'ai_error';
                        }
                    }
                }
            }
        }

        // Fallback: filename heuristic.
        $path     = get_attached_file($attachment_id);
        $filename = $path ? basename($path) : '';
        if ($filename === '') {
            $url      = wp_get_attachment_url($attachment_id);
            $filename = $url ? basename((string) wp_parse_url($url, PHP_URL_PATH)) : '';
        }

        $alt = $filename !== '' ? self::generate_alt_from_filename($filename) : '';

        return [
            'alt'    => $alt,
            'source' => $alt !== '' ? 'filename' : 'none',
            'error'  => $error,
            'reason' => $alt !== '' ? $reason : 'no_filename',
        ];
    }

    /**
     * Clean an AI-generated alt string: strip wrapping quotes, collapse
     * whitespace, drop the "Image of"/"Photo of" preamble models sometimes
     * emit despite instructions, and cap the length for screen readers.
     *
     * @param string $text
     * @return string
     */
    public static function sanitize_generated_alt($text) {
        $text = trim((string) $text);
        if ($text === '') { return ''; }

        // Models occasionally wrap the answer in quotes or prefix a label.
        $text = trim($text, " \t\n\r\0\x0B\"'");
        $text = preg_replace('/^(alt text|alt)\s*[:\-]\s*/i', '', $text);
        $text = preg_replace('/^(an?\s+)?(image|photo|picture|photograph|screenshot)\s+(of|showing|depicting)\s+/i', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        if ($text === '') { return ''; }

        // Keep it useful for screen readers (~125 chars is the accepted max).
        // mb_strlen()/mb_substr() are polyfilled by WordPress core (compat.php),
        // so they are always safe to call. mb_strtoupper() is NOT — guard it.
        if (mb_strlen($text) > 160) {
            $text = rtrim(mb_substr($text, 0, 157), " ,.;:-") . '…';
        }

        // Capitalize the first letter without touching the rest.
        $first = mb_substr($text, 0, 1);
        $rest  = mb_substr($text, 1);

        return (function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first)) . $rest;
    }

    /**
     * Meta flag set on attachments the generator could not describe, so they
     * are excluded from later batches. Without this the "remaining" count can
     * never reach zero and the bulk runner loops forever.
     */
    const SKIP_META = '_ssf_alt_unavailable';

    /**
     * Marks alt text this plugin wrote. Lets a later regenerate pass tell its
     * own output apart from text a human typed, so hand-written alt text is
     * never silently overwritten.
     */
    const GENERATED_META = '_ssf_alt_generated';

    /**
     * Stores the regenerate-pass token an image was last visited in. Acts as
     * the progress marker for "regenerate all", which cannot use emptiness as
     * a marker because it deliberately overwrites existing values.
     */
    const REGEN_META = '_ssf_alt_regen_pass';

    /** Option holding the token of the regenerate pass currently in progress. */
    const REGEN_PASS_OPTION = 'ssf_alt_regen_pass';

    /**
     * Queue alt-text generation shortly after upload.
     *
     * A vision request takes several seconds; running it inline would stall
     * the media uploader (and time out bulk drag-and-drop uploads), so the
     * work is deferred to a one-off cron event.
     *
     * @param int $attachment_id
     */
    public static function schedule_alt_on_upload($attachment_id) {
        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            return;
        }

        // Filename-only mode is instant — no need to defer it. This asks whether
        // vision will actually run, not merely whether a key is saved: a
        // text-only model makes no API call, so deferring would only delay the
        // alt text by 15 seconds for no benefit.
        if (!class_exists('SSF_AI') || empty(SSF_AI::vision_status()['ok'])) {
            self::auto_alt_on_upload($attachment_id);
            return;
        }

        if (!wp_next_scheduled(self::UPLOAD_CRON_HOOK, [(int) $attachment_id])) {
            wp_schedule_single_event(time() + 15, self::UPLOAD_CRON_HOOK, [(int) $attachment_id]);
        }
    }

    /**
     * Auto-generate alt text for an uploaded image.
     * Uses a vision-capable AI when configured, otherwise the filename.
     *
     * @param int $attachment_id
     */
    public static function auto_alt_on_upload($attachment_id) {
        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            return;
        }

        // Only set if no alt text exists
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty(trim((string) $existing_alt))) {
            return;
        }

        $result = self::generate_alt_for_attachment($attachment_id, true);

        if ($result['alt'] !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($result['alt']));
            update_post_meta($attachment_id, self::GENERATED_META, $result['source']);
        }

        // This runs on cron, where nobody sees the outcome. Log a degrade so
        // "why is my new upload's alt text just the filename?" is answerable.
        if ($result['source'] !== 'ai' && class_exists('SSF_Logger')) {
            SSF_Logger::warning(sprintf(
                'Auto alt text for attachment #%d did not use AI (%s)%s',
                (int) $attachment_id,
                !empty($result['reason']) ? $result['reason'] : 'unknown',
                !empty($result['error']) ? ': ' . $result['error'] : ''
            ), 'ai');
        }
    }

    /**
     * SQL fragment (and params) selecting image attachments that still need
     * alt text. Shared by the batch runner and the counter so they can never
     * disagree — a mismatch is what allowed the old runner to spin forever.
     *
     * @return array [string $sql_where, array $params]
     */
    private static function missing_alt_where() {
        global $wpdb;

        $sql = "FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm
                       ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                LEFT JOIN {$wpdb->postmeta} skip
                       ON p.ID = skip.post_id AND skip.meta_key = %s
                WHERE p.post_type = 'attachment'
                  AND p.post_mime_type LIKE %s
                  AND p.post_status != 'trash'
                  AND (pm.meta_value IS NULL OR TRIM(pm.meta_value) = '')
                  AND skip.post_id IS NULL";

        return [$sql, [self::SKIP_META, 'image/%']];
    }

    /**
     * SQL fragment (and params) selecting images not yet visited during the
     * current regenerate pass.
     *
     * Regeneration must be able to REPLACE existing alt text, so "has no alt
     * text" cannot serve as the progress marker. Instead every image visited in
     * a pass is stamped with the pass token; the batch query then asks for
     * anything not carrying the current token. Because the stamp is written
     * before generation is attempted, an image can never be visited twice —
     * the run always terminates, even if the AI call fails on every image.
     *
     * @param string $token Current regenerate-pass token.
     * @return array [string $sql_where, array $params]
     */
    private static function regen_where($token) {
        global $wpdb;

        $sql = "FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} regen
                       ON p.ID = regen.post_id AND regen.meta_key = %s
                WHERE p.post_type = 'attachment'
                  AND p.post_mime_type LIKE %s
                  AND p.post_status != 'trash'
                  AND (regen.meta_value IS NULL OR regen.meta_value != %s)";

        return [$sql, [self::REGEN_META, 'image/%', (string) $token]];
    }

    /**
     * Reproduce the pre-2.0.66 filename heuristic, which stripped a leading
     * P/DC/WP/VID/MOV even from ordinary words ("party-rentals" became
     * "Arty Rentals").
     *
     * Kept so we can RECOGNISE our own historical output: alt text matching
     * this exactly was machine-written and is safe to replace, while anything
     * else may be hand-written and must be preserved.
     *
     * @param string $filename
     * @return string
     */
    public static function legacy_alt_from_filename($filename) {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/-\d+x\d+$/', '', $name);
        $name = str_replace(['-', '_', '.', '+'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        $name = preg_replace('/^(IMG|DSC|DSCN|DSCF|P|DC|MOV|VID|WP|wp|Screenshot)\s*/i', '', $name);
        $name = preg_replace('/^\d+\s*/', '', $name);
        return ucwords(strtolower($name));
    }

    /**
     * Decide whether stored alt text was generated by this plugin (and is
     * therefore safe to overwrite) rather than written by a human.
     *
     * Three signals, cheapest first: our own generation marker, the current
     * filename heuristic, and the old buggy one.
     *
     * @param int    $attachment_id
     * @param string $stored  Existing alt text, already trimmed.
     * @return bool
     */
    private static function looks_machine_generated($attachment_id, $stored) {
        if ($stored === '') {
            return true;
        }

        if (get_post_meta($attachment_id, self::GENERATED_META, true)) {
            return true;
        }

        $path     = get_attached_file($attachment_id);
        $filename = $path ? basename($path) : '';
        if ($filename === '') {
            return false;
        }

        if (strcasecmp($stored, self::generate_alt_from_filename($filename)) === 0) {
            return true;
        }

        return strcasecmp($stored, self::legacy_alt_from_filename($filename)) === 0;
    }

    /**
     * Bulk generate alt text for all images missing it.
     *
     * Processes in small batches. When a vision-capable AI is configured the
     * image itself is sent to the model, so the description reflects what is
     * actually in the picture; otherwise it falls back to the filename.
     *
     * Guaranteed to terminate: any attachment we cannot describe is flagged
     * with SKIP_META and excluded from subsequent batches.
     *
     * @param int  $batch_size  Number of images to process per call
     * @param bool $use_ai      Attempt AI vision (false = filename only)
     * @return array            Results with counts
     */
    public static function bulk_generate_alt_text($batch_size = 10, $use_ai = true) {
        global $wpdb;

        $batch_size = max(1, (int) $batch_size);

        list($from_where, $params) = self::missing_alt_where();

        $attachment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID {$from_where} ORDER BY p.ID DESC LIMIT %d",
            array_merge($params, [$batch_size])
        ));

        // Ask whether the provider can actually SEE images, not merely whether
        // credentials exist — a text-only model would otherwise be counted as
        // "AI" while every description came from the filename.
        $vision       = (class_exists('SSF_AI') ? SSF_AI::vision_status() : ['ok' => false, 'reason' => 'not_configured', 'message' => '']);
        $ai_available = $use_ai && !empty($vision['ok']);

        // AI calls are slow (one vision request per image); give the request
        // room but stop cleanly before PHP's limit so progress is never lost.
        if ($ai_available && function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        $started   = time();
        $max_secs  = $ai_available ? 100 : 25;

        $updated     = 0;
        $skipped     = 0;
        $by_ai       = 0;
        $by_filename = 0;
        $last_error  = '';
        $samples     = [];
        $degraded    = [];

        foreach ($attachment_ids as $id) {
            $id     = (int) $id;
            $result = self::generate_alt_for_attachment($id, $ai_available);

            if ($result['alt'] !== '') {
                update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($result['alt']));
                // Record that WE wrote this, so a later regenerate pass can
                // safely replace it without risking hand-written alt text.
                update_post_meta($id, self::GENERATED_META, $result['source']);
                $updated++;
                if ($result['source'] === 'ai') {
                    $by_ai++;
                } else {
                    $by_filename++;
                    // Track why AI was bypassed so the UI can explain a run
                    // that "worked" but produced filename-quality text.
                    $why = !empty($result['reason']) ? $result['reason'] : 'unknown';
                    if (!isset($degraded[$why])) {
                        $degraded[$why] = ['count' => 0, 'message' => (string) $result['error']];
                    }
                    $degraded[$why]['count']++;
                    if (!empty($result['error'])) {
                        $last_error = $result['error'];
                    }
                }
                if (count($samples) < 5) {
                    $samples[] = [
                        'id'     => $id,
                        'alt'    => $result['alt'],
                        'source' => $result['source'],
                        'reason' => (string) ($result['reason'] ?? ''),
                    ];
                }
            } else {
                // Nothing usable — flag it so we never retry it in a loop.
                update_post_meta($id, self::SKIP_META, 1);
                $skipped++;
                if (!empty($result['error'])) {
                    $last_error = $result['error'];
                }
            }

            if ((time() - $started) >= $max_secs) {
                break;
            }
        }

        // Count remaining using the EXACT same predicate as the batch query.
        $remaining = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) {$from_where}",
            $params
        ));

        // Safety net: if a batch produced no writes and no skips, there is
        // nothing left we can act on — declare completion rather than spin.
        $stalled = (empty($attachment_ids) || ($updated === 0 && $skipped === 0));

        return [
            'mode'          => 'missing',
            'updated'       => $updated,
            'skipped'       => $skipped,
            'by_ai'         => $by_ai,
            'by_filename'   => $by_filename,
            'remaining'     => $remaining,
            'done'          => ($remaining === 0) || $stalled,
            'ai_used'       => $ai_available,
            'samples'       => $samples,
            'last_error'    => $last_error,
            'degraded'      => $degraded,
            'why_no_ai'     => self::explain_degradation($use_ai, $vision, $degraded),
            'stats'         => self::alt_stats(),
        ];
    }

    /**
     * Turn "AI was not used" into one sentence a site owner can act on.
     *
     * Returns '' when AI genuinely described every image — the caller shows
     * this only when there is something to explain.
     *
     * @param bool  $use_ai   Whether the caller asked for AI at all.
     * @param array $vision   SSF_AI::vision_status() result.
     * @param array $degraded reason => ['count' => int, 'message' => string]
     * @return string
     */
    public static function explain_degradation($use_ai, $vision, $degraded) {
        if (!$use_ai) {
            return __('Filename mode was selected, so no AI was used.', 'smart-seo-fixer');
        }

        // A provider-level problem affects every image; report it once.
        if (empty($vision['ok'])) {
            $hint = !empty($vision['message'])
                ? $vision['message']
                : __('The AI provider is not available.', 'smart-seo-fixer');
            return sprintf(
                /* translators: %s: reason the AI could not be used */
                __('Descriptions came from filenames, not the images themselves: %s', 'smart-seo-fixer'),
                $hint
            );
        }

        if (empty($degraded)) {
            return '';
        }

        // Per-image failures: lead with the most common cause.
        $top    = '';
        $best   = -1;
        $total  = 0;
        foreach ($degraded as $reason => $info) {
            $total += (int) $info['count'];
            if ((int) $info['count'] > $best) {
                $best = (int) $info['count'];
                $top  = !empty($info['message']) ? (string) $info['message'] : self::reason_label($reason);
            }
        }

        return sprintf(
            /* translators: 1: number of images, 2: reason */
            _n(
                '%1$d image fell back to its filename because the AI could not describe it: %2$s',
                '%1$d images fell back to their filenames because the AI could not describe them: %2$s',
                $total,
                'smart-seo-fixer'
            ),
            $total,
            $top
        );
    }

    /**
     * Human-readable label for a generate_alt_for_attachment() reason code.
     *
     * @param string $reason
     * @return string
     */
    public static function reason_label($reason) {
        switch ($reason) {
            case 'ai_disabled':
                return __('AI was turned off for this run.', 'smart-seo-fixer');
            case 'not_configured':
                return __('No AI provider is configured.', 'smart-seo-fixer');
            case 'model_not_vision':
                return __('The selected model cannot analyse images.', 'smart-seo-fixer');
            case 'image_unreadable':
                return __('The image file could not be read.', 'smart-seo-fixer');
            case 'ai_empty':
                return __('The AI returned an empty description.', 'smart-seo-fixer');
            case 'ai_error':
                return __('The AI request failed.', 'smart-seo-fixer');
            case 'no_filename':
                return __('There was no filename to fall back to.', 'smart-seo-fixer');
            default:
                return __('The AI could not describe this image.', 'smart-seo-fixer');
        }
    }

    /**
     * Count images missing alt text.
     *
     * @return int
     */
    public static function count_missing_alt() {
        global $wpdb;
        list($from_where, $params) = self::missing_alt_where();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) {$from_where}", $params));
    }

    /**
     * Count images still eligible for the current regenerate-alt-text pass
     * (same predicate regenerate_alt_text() batches against). Used by the
     * background job queue to report progress without an explicit item list.
     *
     * @return int
     */
    public static function count_regen_targets() {
        global $wpdb;
        $token = (string) get_option(self::REGEN_PASS_OPTION, '1');
        list($from_where, $params) = self::regen_where($token);
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) {$from_where}", $params));
    }

    /**
     * Media-library alt text statistics for the settings screen.
     *
     * Counted in one query so the numbers are always internally consistent
     * (total = with_alt + missing + skipped).
     *
     * @return array
     */
    public static function alt_stats() {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN skip.post_id IS NOT NULL THEN 1 ELSE 0 END) AS skipped,
                SUM(CASE WHEN skip.post_id IS NULL
                          AND pm.meta_value IS NOT NULL
                          AND TRIM(pm.meta_value) <> '' THEN 1 ELSE 0 END) AS with_alt,
                SUM(CASE WHEN skip.post_id IS NULL
                          AND (pm.meta_value IS NULL OR TRIM(pm.meta_value) = '') THEN 1 ELSE 0 END) AS missing,
                SUM(CASE WHEN skip.post_id IS NULL
                          AND gen.post_id IS NOT NULL
                          AND pm.meta_value IS NOT NULL
                          AND TRIM(pm.meta_value) <> '' THEN 1 ELSE 0 END) AS generated
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                    ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
             LEFT JOIN {$wpdb->postmeta} skip
                    ON p.ID = skip.post_id AND skip.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} gen
                    ON p.ID = gen.post_id AND gen.meta_key = %s
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE %s
               AND p.post_status != 'trash'",
            self::SKIP_META,
            self::GENERATED_META,
            'image/%'
        ));

        $total   = $row ? (int) $row->total    : 0;
        $with    = $row ? (int) $row->with_alt : 0;
        $missing = $row ? (int) $row->missing  : 0;
        $skipped = $row ? (int) $row->skipped  : 0;

        // "AI ready" means the provider can actually LOOK at an image, not just
        // that a key is saved. A text-only model produces filename-quality text
        // while reporting success, which is the failure this distinguishes.
        $vision = class_exists('SSF_AI')
            ? SSF_AI::vision_status()
            : ['ok' => false, 'reason' => 'not_configured', 'message' => '', 'provider' => '', 'model' => ''];

        return [
            'total'         => $total,
            'with_alt'      => $with,
            'missing'       => $missing,
            'skipped'       => $skipped,
            'generated'     => $row ? (int) $row->generated : 0,
            'percent'       => $total > 0 ? (int) round(($with / $total) * 100) : 0,
            'ai_ready'      => !empty($vision['ok']),
            'ai_label'      => !empty($vision['ok']) ? (string) $vision['provider'] : '',
            'ai_model'      => (string) $vision['model'],
            'ai_reason'     => (string) $vision['reason'],
            'ai_message'    => (string) $vision['message'],
        ];
    }

    /**
     * Regenerate alt text for images that ALREADY have it, replacing the old
     * value. Used to redo descriptions written by the buggy pre-2.0.66 filename
     * heuristic, or to upgrade filename-derived text once a vision AI is set up.
     *
     * Termination is guaranteed by stamping every visited image with the pass
     * token BEFORE attempting generation, so a failing image is never retried
     * within the same pass.
     *
     * @param int  $batch_size
     * @param bool $use_ai
     * @param bool $only_generated  Skip alt text that looks hand-written.
     * @param bool $start_new_pass  Begin a fresh pass (clears prior progress).
     * @return array
     */
    public static function regenerate_alt_text($batch_size = 5, $use_ai = true, $only_generated = true, $start_new_pass = false) {
        global $wpdb;

        $batch_size = max(1, (int) $batch_size);

        // One token per pass. Incrementing invalidates every prior stamp
        // without touching the postmeta table.
        if ($start_new_pass) {
            $token = (string) ((int) get_option(self::REGEN_PASS_OPTION, 0) + 1);
            update_option(self::REGEN_PASS_OPTION, $token, false);
        } else {
            $token = (string) get_option(self::REGEN_PASS_OPTION, '1');
        }

        list($from_where, $params) = self::regen_where($token);

        $attachment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID {$from_where} ORDER BY p.ID DESC LIMIT %d",
            array_merge($params, [$batch_size])
        ));

        // Vision capability, not just credentials — see bulk_generate_alt_text().
        $vision       = (class_exists('SSF_AI') ? SSF_AI::vision_status() : ['ok' => false, 'reason' => 'not_configured', 'message' => '']);
        $ai_available = $use_ai && !empty($vision['ok']);

        if ($ai_available && function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        $started  = time();
        $max_secs = $ai_available ? 100 : 25;

        $updated     = 0;
        $unchanged   = 0;
        $preserved   = 0;
        $by_ai       = 0;
        $by_filename = 0;
        $last_error  = '';
        $samples     = [];
        $degraded    = [];

        foreach ($attachment_ids as $id) {
            $id = (int) $id;

            // Stamp FIRST — this is what makes the loop finite.
            update_post_meta($id, self::REGEN_META, $token);

            $stored = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));

            // Respect human-authored alt text unless explicitly told otherwise.
            if ($only_generated && !self::looks_machine_generated($id, $stored)) {
                $preserved++;
                continue;
            }

            $result = self::generate_alt_for_attachment($id, $ai_available);

            if ($result['alt'] === '') {
                $unchanged++;
                if (!empty($result['error'])) {
                    $last_error = $result['error'];
                }
            } elseif ($result['alt'] === $stored) {
                $unchanged++;
            } else {
                update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($result['alt']));
                update_post_meta($id, self::GENERATED_META, $result['source']);
                delete_post_meta($id, self::SKIP_META);
                $updated++;
                if ($result['source'] === 'ai') {
                    $by_ai++;
                } else {
                    $by_filename++;
                    $why = !empty($result['reason']) ? $result['reason'] : 'unknown';
                    if (!isset($degraded[$why])) {
                        $degraded[$why] = ['count' => 0, 'message' => (string) $result['error']];
                    }
                    $degraded[$why]['count']++;
                    if (!empty($result['error'])) {
                        $last_error = $result['error'];
                    }
                }
                if (count($samples) < 5) {
                    $samples[] = [
                        'id'     => $id,
                        'alt'    => $result['alt'],
                        'old'    => $stored,
                        'source' => $result['source'],
                        'reason' => (string) ($result['reason'] ?? ''),
                    ];
                }
            }

            if ((time() - $started) >= $max_secs) {
                break;
            }
        }

        $remaining = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) {$from_where}",
            $params
        ));

        return [
            'mode'        => 'regenerate',
            'updated'     => $updated,
            'unchanged'   => $unchanged,
            'preserved'   => $preserved,
            'skipped'     => 0,
            'by_ai'       => $by_ai,
            'by_filename' => $by_filename,
            'remaining'   => $remaining,
            'done'        => ($remaining === 0) || empty($attachment_ids),
            'ai_used'     => $ai_available,
            'samples'     => $samples,
            'last_error'  => $last_error,
            'degraded'    => $degraded,
            'why_no_ai'   => self::explain_degradation($use_ai, $vision, $degraded),
            'stats'       => self::alt_stats(),
        ];
    }

    /**
     * Clear the "could not describe" flags so skipped images are retried
     * (e.g. after the user configures an AI provider).
     *
     * @return int Number of flags cleared.
     */
    public static function reset_skipped() {
        global $wpdb;
        return (int) $wpdb->delete($wpdb->postmeta, ['meta_key' => self::SKIP_META]);
    }
}
