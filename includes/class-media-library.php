<?php
/**
 * Media Library alt text integration
 *
 * Surfaces alt-text generation where images actually live:
 *  - an "Alt Text" column in the Media Library list view, with a per-image
 *    Generate / Rewrite button and the current text inline
 *  - a "Generate alt text" bulk action for the list view
 *  - a Generate button under the Alternative Text field on the attachment
 *    edit screen and in the media modal
 *
 * Everything routes through SSF_Image_SEO so there is one implementation of
 * the description logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Media_Library {

    const BULK_ACTION = 'ssf_generate_alt';

    /**
     * Register hooks. Admin-only — none of this runs on the front end.
     */
    public static function init() {
        if (!is_admin()) {
            return;
        }

        // List view column.
        add_filter('manage_media_columns', [__CLASS__, 'add_alt_column']);
        add_action('manage_media_custom_column', [__CLASS__, 'render_alt_column'], 10, 2);

        // Bulk action on the list view.
        add_filter('bulk_actions-upload', [__CLASS__, 'add_bulk_action']);
        add_filter('handle_bulk_actions-upload', [__CLASS__, 'handle_bulk_action'], 10, 3);
        add_action('admin_notices', [__CLASS__, 'bulk_action_notice']);

        // Button beside the Alternative Text field (edit screen + media modal).
        add_filter('attachment_fields_to_edit', [__CLASS__, 'add_generate_button'], 10, 2);

        // Assets for the media screens.
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue'], 20);
    }

    /**
     * Load the shared admin script on the Media Library.
     *
     * Only upload.php is handled here. SSF_Admin::enqueue_assets() already
     * covers post.php / post-new.php (which includes the attachment edit
     * screen), and localizing the same handle twice would REPLACE the first
     * payload rather than merge with it — clobbering the editor's post_id and
     * strings. Running at priority 20 also means SSF_Admin has already gone.
     */
    public static function enqueue($hook) {
        if ($hook !== 'upload.php') {
            return;
        }

        wp_enqueue_style('ssf-admin', SSF_PLUGIN_URL . 'admin/css/admin.css', [], SSF_VERSION);
        wp_enqueue_script('ssf-admin', SSF_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], SSF_VERSION, true);

        // Guard anyway, in case another screen ever adds upload.php support.
        if (!wp_script_is('ssf-admin', 'done')
            && !isset(wp_scripts()->registered['ssf-admin']->extra['data'])) {
            wp_localize_script('ssf-admin', 'ssfAdmin', [
                'ajax_url'  => admin_url('admin-ajax.php'),
                'admin_url' => admin_url(),
                'nonce'     => wp_create_nonce('ssf_nonce'),
                'post_id'   => 0,
                'strings'   => [
                    'generating' => __('Generating...', 'smart-seo-fixer'),
                    'error'      => __('An error occurred.', 'smart-seo-fixer'),
                ],
            ]);
        }
    }

    /**
     * Add the Alt Text column, placed right after the title.
     */
    public static function add_alt_column($columns) {
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'title') {
                $out['ssf_alt'] = __('Alt Text', 'smart-seo-fixer');
            }
        }
        // Fallback if there is no title column (unusual, but don't lose the column).
        if (!isset($out['ssf_alt'])) {
            $out['ssf_alt'] = __('Alt Text', 'smart-seo-fixer');
        }
        return $out;
    }

    /**
     * Render the alt text and its action button for one row.
     */
    public static function render_alt_column($column, $post_id) {
        if ($column !== 'ssf_alt') {
            return;
        }

        $mime = get_post_mime_type($post_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            echo '<span style="color:#8c8f94;">&mdash;</span>';
            return;
        }

        $alt     = trim((string) get_post_meta($post_id, '_wp_attachment_image_alt', true));
        $skipped = (bool) get_post_meta($post_id, SSF_Image_SEO::SKIP_META, true);
        $has     = ($alt !== '');

        echo '<div class="ssf-alt-cell" data-id="' . esc_attr($post_id) . '">';

        echo '<div class="ssf-alt-cell-text" style="margin-bottom:4px;">';
        if ($has) {
            echo '<span class="ssf-alt-value">' . esc_html($alt) . '</span>';

            // Flag descriptions this plugin derived from the filename, so text
            // that never came from looking at the image is obvious.
            $source = (string) get_post_meta($post_id, SSF_Image_SEO::GENERATED_META, true);
            if ($source === 'filename') {
                echo ' <span class="ssf-alt-source" style="color:#996800;font-size:11px;white-space:nowrap;" title="'
                    . esc_attr__('Derived from the filename, not from looking at the image. Use Rewrite once AI vision is working.', 'smart-seo-fixer')
                    . '">' . esc_html__('(from filename)', 'smart-seo-fixer') . '</span>';
            }
        } elseif ($skipped) {
            echo '<em style="color:#996800;">' . esc_html__('Skipped — could not describe', 'smart-seo-fixer') . '</em>';
        } else {
            echo '<em style="color:#d63638;">' . esc_html__('Missing', 'smart-seo-fixer') . '</em>';
        }
        echo '</div>';

        printf(
            '<button type="button" class="button button-small ssf-alt-generate" data-id="%d" data-force="%s">%s</button>',
            (int) $post_id,
            $has ? '1' : '0',
            $has
                ? esc_html__('Rewrite', 'smart-seo-fixer')
                : esc_html__('Generate', 'smart-seo-fixer')
        );
        echo ' <span class="ssf-alt-cell-status" style="font-size:12px;"></span>';

        echo '</div>';
    }

    /**
     * Register the bulk action.
     */
    public static function add_bulk_action($actions) {
        $actions[self::BULK_ACTION] = __('Generate alt text (Smart SEO)', 'smart-seo-fixer');
        return $actions;
    }

    /**
     * Handle the bulk action. Only fills images that are MISSING alt text —
     * a bulk action has no confirmation step, so it must not overwrite.
     * Use "Rewrite Existing Alt Text" in Settings for that.
     *
     * @param string $redirect_to
     * @param string $action
     * @param array  $post_ids
     * @return string
     */
    public static function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== self::BULK_ACTION) {
            return $redirect_to;
        }

        $done       = 0;
        $skipped    = 0;
        $kept       = 0;
        $filename   = 0;
        $why_no_ai  = '';

        // A vision request per image adds up; keep the request inside PHP limits.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $started  = time();
        $max_secs = 120;
        $stopped  = 0;

        foreach ((array) $post_ids as $post_id) {
            $post_id = (int) $post_id;

            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }

            $mime = get_post_mime_type($post_id);
            if (!$mime || strpos($mime, 'image/') !== 0) {
                continue;
            }

            if ((time() - $started) >= $max_secs) {
                $stopped++;
                continue;
            }

            $existing = trim((string) get_post_meta($post_id, '_wp_attachment_image_alt', true));
            if ($existing !== '') {
                $kept++;
                continue;
            }

            $result = SSF_Image_SEO::generate_alt_for_attachment($post_id, true);

            if ($result['alt'] !== '') {
                update_post_meta($post_id, '_wp_attachment_image_alt', sanitize_text_field($result['alt']));
                update_post_meta($post_id, SSF_Image_SEO::GENERATED_META, $result['source']);
                delete_post_meta($post_id, SSF_Image_SEO::SKIP_META);
                $done++;

                // Filename-derived text is a degraded result. Capture the first
                // cause so the notice can say why AI was not used, instead of
                // reporting a clean success for guessed descriptions.
                if ($result['source'] !== 'ai') {
                    $filename++;
                    if ($why_no_ai === '') {
                        $why_no_ai = !empty($result['error'])
                            ? $result['error']
                            : SSF_Image_SEO::reason_label((string) ($result['reason'] ?? ''));
                    }
                }
            } else {
                update_post_meta($post_id, SSF_Image_SEO::SKIP_META, 1);
                $skipped++;
            }
        }

        return add_query_arg([
            'ssf_alt_done'     => $done,
            'ssf_alt_skipped'  => $skipped,
            'ssf_alt_kept'     => $kept,
            'ssf_alt_stopped'  => $stopped,
            'ssf_alt_filename' => $filename,
            'ssf_alt_why'      => $why_no_ai !== '' ? rawurlencode($why_no_ai) : '',
        ], $redirect_to);
    }

    /**
     * Report the bulk action result.
     */
    public static function bulk_action_notice() {
        if (!isset($_GET['ssf_alt_done'])) {
            return;
        }

        $done     = absint($_GET['ssf_alt_done']);
        $skipped  = isset($_GET['ssf_alt_skipped']) ? absint($_GET['ssf_alt_skipped']) : 0;
        $kept     = isset($_GET['ssf_alt_kept']) ? absint($_GET['ssf_alt_kept']) : 0;
        $stopped  = isset($_GET['ssf_alt_stopped']) ? absint($_GET['ssf_alt_stopped']) : 0;
        $filename = isset($_GET['ssf_alt_filename']) ? absint($_GET['ssf_alt_filename']) : 0;
        $why      = isset($_GET['ssf_alt_why'])
            ? sanitize_text_field(rawurldecode(wp_unslash($_GET['ssf_alt_why'])))
            : '';

        $parts = [];
        $parts[] = sprintf(
            /* translators: %d: number of images */
            _n('%d image described.', '%d images described.', $done, 'smart-seo-fixer'),
            $done
        );
        if ($kept > 0) {
            $parts[] = sprintf(
                /* translators: %d: number of images */
                _n('%d already had alt text and was left alone.', '%d already had alt text and were left alone.', $kept, 'smart-seo-fixer'),
                $kept
            );
        }
        if ($skipped > 0) {
            $parts[] = sprintf(
                /* translators: %d: number of images */
                _n('%d could not be described.', '%d could not be described.', $skipped, 'smart-seo-fixer'),
                $skipped
            );
        }
        if ($stopped > 0) {
            $parts[] = sprintf(
                /* translators: %d: number of images */
                _n('%d was not reached before the time limit — run the action again.', '%d were not reached before the time limit — run the action again.', $stopped, 'smart-seo-fixer'),
                $stopped
            );
        }
        if ($filename > 0) {
            $parts[] = sprintf(
                /* translators: 1: number of images, 2: reason the AI was not used */
                _n(
                    '%1$d description came from the filename rather than the image itself%2$s',
                    '%1$d descriptions came from filenames rather than the images themselves%2$s',
                    $filename,
                    'smart-seo-fixer'
                ),
                $filename,
                $why !== '' ? ' — ' . $why : '.'
            );
        }

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            ($skipped > 0 || $stopped > 0 || $filename > 0) ? 'warning' : 'success',
            esc_html(implode(' ', $parts))
        );
    }

    /**
     * Add a Generate button as its own row in the media modal's attachment
     * details ("compat" fields).
     *
     * Deliberately registered under our OWN field key rather than appended to
     * image_alt's help text: the modal renders the alt input from its Backbone
     * template and drops some of the PHP-side field definitions, so anything
     * attached to image_alt can silently disappear. A distinct key always
     * renders.
     *
     * The attachment edit screen (post.php) renders its Alternative Text input
     * directly rather than through this filter, so the button is injected there
     * by admin.js instead — see the media-library block in admin/js/admin.js.
     *
     * @param array   $fields
     * @param WP_Post $post
     * @return array
     */
    public static function add_generate_button($fields, $post) {
        if (!$post instanceof WP_Post) {
            return $fields;
        }

        $mime = get_post_mime_type($post->ID);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            return $fields;
        }

        if (!current_user_can('edit_post', $post->ID)) {
            return $fields;
        }

        // The button needs admin.js to do anything. It is loaded on upload.php,
        // post.php and post-new.php; media-modal field markup instead arrives
        // through an AJAX request from one of those screens. Anywhere else
        // (Customizer, third-party screens) the button would be inert, so it is
        // omitted rather than shown as a dead control.
        if (!wp_doing_ajax()
            && !wp_script_is('ssf-admin', 'enqueued')
            && !wp_script_is('ssf-admin', 'done')) {
            return $fields;
        }

        // Say what will actually happen. "Configured" is not the same as "can
        // see images" — a text-only model silently yields filename-quality text.
        $vision = class_exists('SSF_AI')
            ? SSF_AI::vision_status()
            : ['ok' => false, 'message' => '', 'provider' => ''];

        $hint = !empty($vision['ok'])
            ? sprintf(
                /* translators: %s: AI provider name */
                __('Describes the actual image using %s.', 'smart-seo-fixer'),
                $vision['provider']
            )
            : sprintf(
                /* translators: %s: reason AI vision is unavailable */
                __('The description will come from the filename, not the image: %s', 'smart-seo-fixer'),
                !empty($vision['message'])
                    ? $vision['message']
                    : __('no AI provider configured.', 'smart-seo-fixer')
            );

        $fields['ssf_alt_generate'] = [
            'label' => __('Smart SEO', 'smart-seo-fixer'),
            'input' => 'html',
            'html'  => sprintf(
                '<button type="button" class="button button-small ssf-alt-generate-field" data-id="%d">%s</button>
                 <span class="ssf-alt-field-status" style="margin-left:6px;font-size:12px;"></span>
                 <p class="description" style="margin-top:4px;">%s</p>',
                (int) $post->ID,
                esc_html__('Generate alt text with AI', 'smart-seo-fixer'),
                esc_html($hint)
            ),
        ];

        return $fields;
    }
}
