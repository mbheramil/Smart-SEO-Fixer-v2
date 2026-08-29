<?php
/**
 * Admin Class
 * 
 * Handles admin menus, pages, and post editor metabox.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Admin {

    /**
     * Consistent, icon-free page header: title + version badge, optional
     * subtitle and right-aligned actions. Rolling out page by page — only
     * Dashboard and Settings use this so far; other views keep their own
     * markup until migrated.
     *
     * @param string $title
     * @param string $subtitle   Optional description shown under the title.
     * @param string $actions_html Optional pre-rendered HTML (buttons/links) for the right side.
     */
    public static function page_header($title, $subtitle = '', $actions_html = '') {
        echo '<div class="ssf-page-header-row" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">';
        echo '<div>';
        echo '<h1 class="ssf-page-title ssf-page-title--plain">'
            . esc_html($title)
            . ' <span class="ssf-version">v' . esc_html(SSF_VERSION) . '</span>'
            . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="description" style="margin-top:-10px;">' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';
        if ($actions_html !== '') {
            echo '<div class="ssf-page-header-actions">' . $actions_html . '</div>';
        }
        echo '</div>';
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box'], 10, 2);
        
        // Auto-generate SEO meta on publish (when auto_meta is enabled)
        add_action('transition_post_status', [$this, 'auto_generate_meta'], 10, 3);
        // Async worker for the above — avoids slowing down the publish request.
        add_action('ssf_auto_generate_meta_run', [$this, 'cron_auto_generate_meta']);
        
        // Show conflict warning if other SEO plugins detected and output not disabled
        add_action('admin_notices', [$this, 'conflict_notice']);
        
        // Auto-generate alt text on image upload (when auto_alt_text is enabled)
        // NOTE: alt text on upload is handled by SSF_Image_SEO::init(), which
        // defers the AI vision call to cron so the media uploader is not blocked
        // for several seconds per file. Hooking auto_generate_alt_text() here as
        // well made both run on the same upload: the synchronous one won the race,
        // wrote alt text inline, and the deferred job then found the field already
        // filled and did nothing — so the deferral never took effect.
        
        // Add SEO score column to posts list
        add_filter('manage_posts_columns', [$this, 'add_seo_column']);
        add_filter('manage_pages_columns', [$this, 'add_seo_column']);
        add_action('manage_posts_custom_column', [$this, 'render_seo_column'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'render_seo_column'], 10, 2);
        
        // Make column sortable
        add_filter('manage_edit-post_sortable_columns', [$this, 'sortable_seo_column']);
        add_filter('manage_edit-page_sortable_columns', [$this, 'sortable_seo_column']);
        add_action('pre_get_posts', [$this, 'sort_by_seo_score']);
        
        // Grouped admin menu (collapsible categories in sidebar)
        add_action('admin_head', [$this, 'admin_menu_group_css']);
        add_action('admin_footer', [$this, 'admin_menu_group_js']);
    }
    
    /**
     * CSS for grouped admin menu with hover flyouts (native WP style)
     */
    public function admin_menu_group_css() {
        ?>
        <style>
            /* Hide grouped child items from the inline submenu */
            #adminmenu .wp-submenu .ssf-menu-group-item { display: none !important; }
            
            /* Group header as a normal submenu item with arrow */
            #adminmenu .wp-submenu .ssf-flyout-trigger {
                position: relative !important;
                cursor: pointer !important;
            }
            #adminmenu .wp-submenu .ssf-flyout-trigger > a {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
            }
            #adminmenu .wp-submenu .ssf-flyout-trigger .ssf-fly-arrow {
                font-size: 8px;
                opacity: 0.5;
                margin-left: 6px;
            }
            #adminmenu .wp-submenu .ssf-flyout-trigger:hover .ssf-fly-arrow { opacity: 1; }
            
            /* Flyout panel — matches native WP admin submenu */
            .ssf-flyout-panel {
                display: none;
                position: absolute;
                left: 100%;
                min-width: 160px;
                background: #2c3338;
                box-shadow: 0 3px 5px rgba(0,0,0,.2);
                padding: 7px 0;
                z-index: 10000;
                border-radius: 0 4px 4px 0;
            }
            .ssf-flyout-trigger:hover > .ssf-flyout-panel,
            .ssf-flyout-trigger.ssf-fly-open > .ssf-flyout-panel {
                display: block;
            }
            .ssf-flyout-panel a,
            #adminmenu .ssf-flyout-panel a {
                display: block !important;
                padding: 7px 24px !important;
                color: #c3c4c7 !important;
                text-decoration: none !important;
                font-size: 13px !important;
                line-height: 1.5 !important;
                white-space: nowrap;
                margin: 0 !important;
            }
            .ssf-flyout-panel a:hover,
            .ssf-flyout-panel a:focus,
            #adminmenu .ssf-flyout-panel a:hover,
            #adminmenu .ssf-flyout-panel a:focus {
                color: #72aee6 !important;
                background: transparent !important;
            }
            .ssf-flyout-panel a.ssf-fly-current,
            #adminmenu .ssf-flyout-panel a.ssf-fly-current {
                color: #fff !important;
                font-weight: 600;
            }
            
            /* Make the current page's group header look active */
            #adminmenu .wp-submenu .ssf-flyout-trigger.ssf-has-current > a {
                color: #fff !important;
                font-weight: 600;
            }
        </style>
        <?php
    }
    
    /**
     * JS for grouped admin menu with hover flyouts + smart vertical positioning
     */
    public function admin_menu_group_js() {
        ?>
        <script>
        (function($) {
            var $menuLi = $('#adminmenu a[href="admin.php?page=smart-seo-fixer"]').first().closest('li.menu-top');
            if (!$menuLi.length) return;
            var $sub = $menuLi.find('ul.wp-submenu');
            if (!$sub.length) return;

            var groups = [
                { label: '<?php echo esc_js(__('Analyze & Fix', 'smart-seo-fixer')); ?>', pages: ['smart-seo-fixer-analyzer','smart-seo-fixer-bulk-fix','smart-seo-fixer-posts','smart-seo-fixer-content-suggestions'] },
                { label: '<?php echo esc_js(__('Technical SEO', 'smart-seo-fixer')); ?>', pages: ['smart-seo-fixer-schema','smart-seo-fixer-local','smart-seo-fixer-redirects','smart-seo-fixer-broken-links','smart-seo-fixer-404-monitor','smart-seo-fixer-robots'] },
                { label: '<?php echo esc_js(__('Search & Social', 'smart-seo-fixer')); ?>', pages: ['smart-seo-fixer-search-performance','smart-seo-fixer-gsc','smart-seo-fixer-social-preview','smart-seo-fixer-keywords'] },
                { label: '<?php echo esc_js(__('System', 'smart-seo-fixer')); ?>', pages: ['smart-seo-fixer-jobs','smart-seo-fixer-history','smart-seo-fixer-migration','smart-seo-fixer-wp-standards','smart-seo-fixer-performance','smart-seo-fixer-debug-log'] }
            ];

            // Detect current page
            var currentSlug = '';
            var $curLi = $sub.find('li.current');
            if ($curLi.length) {
                var m = ($curLi.find('a').attr('href') || '').match(/page=([\w-]+)/);
                if (m) currentSlug = m[1];
            }

            $.each(groups, function(idx, group) {
                var $items = $();
                var hasCurrent = false;
                var flyLinks = '';

                $.each(group.pages, function(_, slug) {
                    var $li = $sub.find('a[href="admin.php?page=' + slug + '"]').closest('li');
                    if ($li.length) {
                        $li.addClass('ssf-menu-group-item');
                        $items = $items.add($li);
                        var txt = $li.find('a').text().trim();
                        var isCur = (slug === currentSlug);
                        if (isCur) hasCurrent = true;
                        flyLinks += '<a href="admin.php?page=' + slug + '"' + (isCur ? ' class="ssf-fly-current"' : '') + '>' + txt + '</a>';
                    }
                });

                if (!$items.length) return;

                // Build the flyout trigger <li>
                var $trigger = $('<li class="ssf-flyout-trigger' + (hasCurrent ? ' ssf-has-current' : '') + '">' +
                    '<a href="#">' + group.label + ' <span class="ssf-fly-arrow">&#9654;</span></a>' +
                    '<div class="ssf-flyout-panel">' + flyLinks + '</div></li>');

                // Prevent the # link from navigating
                $trigger.find('> a').on('click', function(e) { e.preventDefault(); });

                // Insert before the first item of this group
                $items.first().before($trigger);
            });

            // Smart flyout positioning — open upward if not enough space below
            $sub.on('mouseenter', '.ssf-flyout-trigger', function() {
                var $panel = $(this).find('.ssf-flyout-panel');
                if (!$panel.length) return;

                // Reset position so we can measure naturally
                $panel.css({ top: '', bottom: '' });

                var triggerRect = this.getBoundingClientRect();
                var panelHeight = $panel.outerHeight();
                var viewportHeight = window.innerHeight;

                // If the panel would overflow the bottom of the viewport, anchor to bottom
                if (triggerRect.top + panelHeight > viewportHeight - 8) {
                    $panel.css({ top: 'auto', bottom: '-7px' });
                } else {
                    $panel.css({ top: '-7px', bottom: 'auto' });
                }
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Show admin notice when conflicting SEO plugins are detected
     */
    public function conflict_notice() {
        // Only show on our plugin pages or the plugins page
        $screen = get_current_screen();
        if (!$screen) return;
        
        $our_pages = [
            'toplevel_page_smart-seo-fixer',
            'smart-seo_page_smart-seo-fixer-analyzer',
            'smart-seo_page_smart-seo-fixer-bulk-fix',
            'smart-seo_page_smart-seo-fixer-settings',
            'smart-seo_page_smart-seo-fixer-posts',
            'smart-seo_page_smart-seo-fixer-schema',
            'smart-seo_page_smart-seo-fixer-local',
            'smart-seo_page_smart-seo-fixer-search-performance',
            'smart-seo_page_smart-seo-fixer-migration',
            'smart-seo_page_smart-seo-fixer-history',
            'smart-seo_page_smart-seo-fixer-debug-log',
            'smart-seo_page_smart-seo-fixer-jobs',
            'smart-seo_page_smart-seo-fixer-broken-links',
            'smart-seo_page_smart-seo-fixer-404-monitor',
            'smart-seo_page_smart-seo-fixer-robots',
            'smart-seo_page_smart-seo-fixer-social-preview',
            'smart-seo_page_smart-seo-fixer-keywords',
            'smart-seo_page_smart-seo-fixer-content-suggestions',
            'smart-seo_page_smart-seo-fixer-wp-standards',
            'smart-seo_page_smart-seo-fixer-performance',
            'plugins',
        ];
        
        if (!in_array($screen->id, $our_pages)) return;
        
        // Already disabled? Don't show
        if (Smart_SEO_Fixer::get_option('disable_other_seo_output', false)) return;
        
        // Detect conflicting plugins
        $conflicts = [];
        if (defined('WPSEO_VERSION')) $conflicts[] = 'Yoast SEO';
        if (defined('RANK_MATH_VERSION')) $conflicts[] = 'Rank Math';
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEOP_Core')) $conflicts[] = 'All in One SEO';
        if (defined('THE_SEO_FRAMEWORK_VERSION')) $conflicts[] = 'The SEO Framework';
        if (defined('SEOPRESS_VERSION')) $conflicts[] = 'SEOPress';
        
        if (empty($conflicts)) return;
        
        $dismiss_key = 'ssf_conflict_dismissed';
        if (get_option($dismiss_key)) return;
        
        $plugins_list = '<strong>' . esc_html(implode(', ', $conflicts)) . '</strong>';
        $settings_url = admin_url('admin.php?page=smart-seo-fixer-settings');
        $migration_url = admin_url('admin.php?page=smart-seo-fixer-migration');
        
        echo '<div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 12px 15px;">';
        echo '<p style="font-size: 14px; margin: 0 0 8px;"><strong>Smart SEO Fixer — ' . esc_html__('Duplicate Meta Tags Detected', 'smart-seo-fixer') . '</strong></p>';
        echo '<p style="margin: 0 0 8px;">';
        printf(
            /* translators: %s: list of conflicting plugins */
            esc_html__('You have %s active alongside Smart SEO Fixer. This causes duplicate meta descriptions, Open Graph tags, and schema markup — which hurts your SEO.', 'smart-seo-fixer'),
            $plugins_list
        );
        echo '</p>';
        echo '<p style="margin: 0;">';
        echo '<a href="' . esc_url($migration_url) . '" class="button" style="margin-right: 8px;">' . esc_html__('1. Import SEO Data', 'smart-seo-fixer') . '</a>';
        echo '<a href="' . esc_url($settings_url) . '" class="button button-primary">' . esc_html__('2. Disable Duplicate Output', 'smart-seo-fixer') . '</a>';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Smart SEO Fixer', 'smart-seo-fixer'),
            __('Smart SEO', 'smart-seo-fixer'),
            'edit_posts',
            'smart-seo-fixer',
            [$this, 'render_dashboard'],
            'dashicons-chart-line',
            80
        );
        
        // Dashboard submenu
        add_submenu_page(
            'smart-seo-fixer',
            __('Dashboard', 'smart-seo-fixer'),
            __('Dashboard', 'smart-seo-fixer'),
            'edit_posts',
            'smart-seo-fixer',
            [$this, 'render_dashboard']
        );
        
        // ── Analyze & Fix ──
        add_submenu_page('smart-seo-fixer', __('SEO Analyzer', 'smart-seo-fixer'), __('SEO Analyzer', 'smart-seo-fixer'), 'edit_posts', 'smart-seo-fixer-analyzer', [$this, 'render_analyzer']);
        add_submenu_page('smart-seo-fixer', __('Bulk AI Fix', 'smart-seo-fixer'), __('Bulk AI Fix', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-bulk-fix', [$this, 'render_bulk_fix']);
        add_submenu_page('smart-seo-fixer', __('All Posts', 'smart-seo-fixer'), __('All Posts', 'smart-seo-fixer'), 'edit_posts', 'smart-seo-fixer-posts', [$this, 'render_posts_page']);
        add_submenu_page('smart-seo-fixer', __('Content Suggestions', 'smart-seo-fixer'), __('Content Tips', 'smart-seo-fixer'), 'edit_posts', 'smart-seo-fixer-content-suggestions', [$this, 'render_content_suggestions']);
        
        // ── Technical SEO ──
        add_submenu_page('smart-seo-fixer', __('Schema', 'smart-seo-fixer'), __('Schema', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-schema', [$this, 'render_schema_page']);
        add_submenu_page('smart-seo-fixer', __('Local SEO', 'smart-seo-fixer'), __('Local SEO', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-local', [$this, 'render_local_seo']);
        add_submenu_page('smart-seo-fixer', __('Redirects', 'smart-seo-fixer'), __('Redirects', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-redirects', [$this, 'render_redirects_page']);
        add_submenu_page('smart-seo-fixer', __('Broken Links', 'smart-seo-fixer'), __('Broken Links', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-broken-links', [$this, 'render_broken_links']);
        add_submenu_page('smart-seo-fixer', __('404 Monitor', 'smart-seo-fixer'), __('404 Monitor', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-404-monitor', [$this, 'render_404_monitor']);
        add_submenu_page('smart-seo-fixer', __('robots.txt', 'smart-seo-fixer'), __('robots.txt', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-robots', [$this, 'render_robots_editor']);
        
        // ── Search & Social ──
        add_submenu_page('smart-seo-fixer', __('Search Performance', 'smart-seo-fixer'), __('Search Perf.', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-search-performance', [$this, 'render_search_performance']);
        add_submenu_page('smart-seo-fixer', __('Indexability Auditor', 'smart-seo-fixer'), __('Indexability', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-gsc', [$this, 'render_gsc_page']);
        add_submenu_page('smart-seo-fixer', __('Social Preview', 'smart-seo-fixer'), __('Social Preview', 'smart-seo-fixer'), 'edit_posts', 'smart-seo-fixer-social-preview', [$this, 'render_social_preview']);
        add_submenu_page('smart-seo-fixer', __('Keyword Tracker', 'smart-seo-fixer'), __('Keywords', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-keywords', [$this, 'render_keyword_tracker']);
        
        // ── Reports ──
        // Registered (so the page itself still works) but hidden from the
        // sidebar — it's linked from Settings instead of being a top-level
        // nav item.
        add_submenu_page('smart-seo-fixer', __('Client Report', 'smart-seo-fixer'), __('Client Report', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-client-report', [$this, 'render_client_report']);
        remove_submenu_page('smart-seo-fixer', 'smart-seo-fixer-client-report');

        // ── System ──
        add_submenu_page('smart-seo-fixer', __('Background Jobs', 'smart-seo-fixer'), __('Jobs', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-jobs', [$this, 'render_job_queue']);
        add_submenu_page('smart-seo-fixer', __('Change History', 'smart-seo-fixer'), __('History', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-history', [$this, 'render_change_history']);
        add_submenu_page('smart-seo-fixer', __('Migration', 'smart-seo-fixer'), __('Migration', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-migration', [$this, 'render_migration']);
        add_submenu_page('smart-seo-fixer', __('Debug Log', 'smart-seo-fixer'), __('Debug Log', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-debug-log', [$this, 'render_debug_log']);
        
        // ── Always visible ──
        add_submenu_page('smart-seo-fixer', __('Settings', 'smart-seo-fixer'), __('Settings', 'smart-seo-fixer'), 'manage_options', 'smart-seo-fixer-settings', [$this, 'render_settings']);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        global $post;
        
        // Only on our pages or post editor
        $our_pages = [
            'toplevel_page_smart-seo-fixer',
            'smart-seo_page_smart-seo-fixer-analyzer',
            'smart-seo_page_smart-seo-fixer-bulk-fix',
            'smart-seo_page_smart-seo-fixer-posts',
            'smart-seo_page_smart-seo-fixer-settings',
            'smart-seo_page_smart-seo-fixer-local',
            'smart-seo_page_smart-seo-fixer-schema',
            'smart-seo_page_smart-seo-fixer-redirects',
            'smart-seo_page_smart-seo-fixer-gsc',
            'smart-seo_page_smart-seo-fixer-search-performance',
            'smart-seo_page_smart-seo-fixer-migration',
            'smart-seo_page_smart-seo-fixer-history',
            'smart-seo_page_smart-seo-fixer-debug-log',
            'smart-seo_page_smart-seo-fixer-jobs',
            'smart-seo_page_smart-seo-fixer-broken-links',
            'smart-seo_page_smart-seo-fixer-404-monitor',
            'smart-seo_page_smart-seo-fixer-robots',
            'smart-seo_page_smart-seo-fixer-social-preview',
            'smart-seo_page_smart-seo-fixer-keywords',
            'smart-seo_page_smart-seo-fixer-content-suggestions',
            'smart-seo_page_smart-seo-fixer-wp-standards',
            'smart-seo_page_smart-seo-fixer-performance',
            'smart-seo_page_smart-seo-fixer-client-report',
        ];
        
        $is_our_page = in_array($hook, $our_pages);
        $is_editor = in_array($hook, ['post.php', 'post-new.php']);
        
        if (!$is_our_page && !$is_editor) {
            return;
        }
        
        wp_enqueue_style(
            'ssf-admin',
            SSF_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SSF_VERSION
        );
        
        // Real PDF generator — only on Client Report page (~900KB)
        if ($hook === 'smart-seo_page_smart-seo-fixer-client-report') {
            wp_enqueue_script(
                'ssf-html2pdf',
                SSF_PLUGIN_URL . 'admin/js/vendor/html2pdf.bundle.min.js',
                [],
                '0.10.2',
                true
            );
        }

        wp_enqueue_script(
            'ssf-admin',
            SSF_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            SSF_VERSION,
            true
        );
        
        wp_localize_script('ssf-admin', 'ssfAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('ssf_nonce'),
            'post_id' => $post ? $post->ID : 0,
            'strings' => [
                'analyzing' => __('Analyzing...', 'smart-seo-fixer'),
                'generating' => __('Generating...', 'smart-seo-fixer'),
                'saving' => __('Saving...', 'smart-seo-fixer'),
                'fixing' => __('Fixing...', 'smart-seo-fixer'),
                'error' => __('An error occurred.', 'smart-seo-fixer'),
                'confirm_fix' => __('Apply this AI-generated content?', 'smart-seo-fixer'),
            ],
        ]);
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        include SSF_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render SEO Analyzer page
     */
    public function render_analyzer() {
        include SSF_PLUGIN_DIR . 'admin/views/analyzer.php';
    }
    
    /**
     * Render Bulk AI Fix page
     */
    public function render_bulk_fix() {
        include SSF_PLUGIN_DIR . 'admin/views/bulk-fix.php';
    }
    
    /**
     * Render posts page
     */
    public function render_posts_page() {
        include SSF_PLUGIN_DIR . 'admin/views/posts.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        include SSF_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Render local SEO page
     */
    public function render_local_seo() {
        include SSF_PLUGIN_DIR . 'admin/views/local-seo.php';
    }
    
    /**
     * Render redirects page
     */
    public function render_redirects_page() {
        include SSF_PLUGIN_DIR . 'admin/views/redirects.php';
    }
    
    /**
     * Render schema management page
     */
    public function render_schema_page() {
        include SSF_PLUGIN_DIR . 'admin/views/schema.php';
    }
    
    /**
     * Render migration page
     */
    public function render_migration() {
        include SSF_PLUGIN_DIR . 'admin/views/migration.php';
    }
    
    /**
     * Render Search Console Fixer page
     */
    public function render_search_performance() {
        include SSF_PLUGIN_DIR . 'admin/views/search-performance.php';
    }
    
    public function render_gsc_page() {
        include SSF_PLUGIN_DIR . 'admin/views/search-console.php';
    }
    
    /**
     * Render Job Queue page
     */
    public function render_job_queue() {
        include SSF_PLUGIN_DIR . 'admin/views/job-queue.php';
    }
    
    /**
     * Render Change History page
     */
    public function render_change_history() {
        include SSF_PLUGIN_DIR . 'admin/views/change-history.php';
    }
    
    /**
     * Render Broken Links page
     */
    public function render_broken_links() {
        include SSF_PLUGIN_DIR . 'admin/views/broken-links.php';
    }
    
    /**
     * Render 404 Monitor page
     */
    public function render_404_monitor() {
        include SSF_PLUGIN_DIR . 'admin/views/404-monitor.php';
    }
    
    /**
     * Render robots.txt Editor page
     */
    public function render_robots_editor() {
        include SSF_PLUGIN_DIR . 'admin/views/robots-editor.php';
    }
    
    /**
     * Render Social Preview page
     */
    public function render_social_preview() {
        include SSF_PLUGIN_DIR . 'admin/views/social-preview.php';
    }
    
    /**
     * Render Keyword Tracker page
     */
    public function render_keyword_tracker() {
        include SSF_PLUGIN_DIR . 'admin/views/keyword-tracker.php';
    }
    
    /**
     * Render Content Suggestions page
     */
    public function render_content_suggestions() {
        include SSF_PLUGIN_DIR . 'admin/views/content-suggestions.php';
    }
    
    /**
     * Render WP Coding Standards page
     */
    public function render_wp_standards() {
        include SSF_PLUGIN_DIR . 'admin/views/wp-standards.php';
    }
    
    /**
     * Render Performance Profiler page
     */
    public function render_performance() {
        include SSF_PLUGIN_DIR . 'admin/views/performance.php';
    }
    
    /**
     * Render Client Report page
     */
    public function render_client_report() {
        include SSF_PLUGIN_DIR . 'admin/views/client-report.php';
    }
    
    /**
     * Render Debug Log page
     */
    public function render_debug_log() {
        include SSF_PLUGIN_DIR . 'admin/views/debug-log.php';
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'ssf_seo_metabox',
                __('Smart SEO Fixer', 'smart-seo-fixer'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('ssf_meta_box', 'ssf_meta_box_nonce');
        
        $seo_title = get_post_meta($post->ID, '_ssf_seo_title', true);
        $meta_description = get_post_meta($post->ID, '_ssf_meta_description', true);
        $focus_keyword = get_post_meta($post->ID, '_ssf_focus_keyword', true);
        $canonical_url = get_post_meta($post->ID, '_ssf_canonical_url', true);
        $noindex = get_post_meta($post->ID, '_ssf_noindex', true);
        $nofollow = get_post_meta($post->ID, '_ssf_nofollow', true);
        $seo_score = get_post_meta($post->ID, '_ssf_seo_score', true);
        $seo_grade = get_post_meta($post->ID, '_ssf_seo_grade', true);
        
        include SSF_PLUGIN_DIR . 'admin/views/meta-box.php';
    }
    
    /**
     * Save meta box
     */
    public function save_meta_box($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['ssf_meta_box_nonce']) || !wp_verify_nonce($_POST['ssf_meta_box_nonce'], 'ssf_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save fields
        $fields = [
            '_ssf_seo_title' => 'sanitize_text_field',
            '_ssf_meta_description' => 'sanitize_textarea_field',
            '_ssf_focus_keyword' => 'sanitize_text_field',
            '_ssf_canonical_url' => 'esc_url_raw',
        ];
        
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, $sanitize($_POST[$field]));
            }
        }
        
        // Checkboxes
        $user_noindex = !empty($_POST['_ssf_noindex']) ? 1 : 0;
        update_post_meta($post_id, '_ssf_noindex', $user_noindex);
        update_post_meta($post_id, '_ssf_nofollow', !empty($_POST['_ssf_nofollow']) ? 1 : 0);
        // When the user explicitly saves the noindex checkbox (on or off) via
        // the meta box, clear the auto-noindex marker so our cron won't flip
        // their choice back later.
        delete_post_meta($post_id, '_ssf_auto_noindex');
        
        // Allow extensions to save their own fields
        do_action('ssf_metabox_save', $post_id);
        
        // Run analysis
        $analyzer = new SSF_Analyzer();
        $analyzer->analyze_post($post_id);
    }
    
    /**
     * Add SEO score column
     */
    public function add_seo_column($columns) {
        $columns['seo_score'] = __('SEO', 'smart-seo-fixer');
        return $columns;
    }
    
    /**
     * Render SEO score column
     */
    public function render_seo_column($column, $post_id) {
        if ($column !== 'seo_score') {
            return;
        }
        
        $score = get_post_meta($post_id, '_ssf_seo_score', true);
        $grade = get_post_meta($post_id, '_ssf_seo_grade', true);
        
        if ($score !== '') {
            $class = $this->get_score_class($score);
            echo '<span class="ssf-score ssf-score-' . esc_attr($class) . '">';
            echo esc_html($score) . ' (' . esc_html($grade) . ')';
            echo '</span>';
        } else {
            echo '<span class="ssf-score ssf-score-none">—</span>';
        }
    }
    
    /**
     * Make SEO column sortable
     */
    public function sortable_seo_column($columns) {
        $columns['seo_score'] = 'seo_score';
        return $columns;
    }
    
    /**
     * Sort by SEO score
     */
    public function sort_by_seo_score($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('orderby') === 'seo_score') {
            $query->set('meta_key', '_ssf_seo_score');
            $query->set('orderby', 'meta_value_num');
        }
    }
    
    /**
     * Get score class
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'ok';
        return 'poor';
    }
    
    /**
     * Auto-generate SEO meta when a post is published or updated.
     *
     * Schedules an async job so the save/publish request stays fast. The
     * actual AI work happens in cron_auto_generate_meta() ~5 seconds later.
     */
    public function auto_generate_meta($new_status, $old_status, $post) {
        if ($new_status !== 'publish') {
            return;
        }

        // Default ON: auto-fill title/description if missing.
        if (!Smart_SEO_Fixer::get_option('auto_meta', true)) {
            return;
        }

        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (!in_array($post->post_type, $post_types, true)) {
            return;
        }

        // Previously we skipped posts with <20 words. We now process them so
        // thin-content auto-noindex + image-only enrichment can run. The
        // cron_auto_generate_meta worker decides what to do based on the
        // validator's thin-content check.

        // If everything is already filled AND we've already evaluated thin
        // content, nothing to do (avoid a wasted cron).
        $seo_title      = get_post_meta($post->ID, '_ssf_seo_title', true);
        $meta_desc      = get_post_meta($post->ID, '_ssf_meta_description', true);
        $focus_keyword  = get_post_meta($post->ID, '_ssf_focus_keyword', true);
        $thin_evaluated = get_post_meta($post->ID, '_ssf_thin_evaluated', true);
        if (!empty($seo_title) && !empty($meta_desc) && !empty($focus_keyword) && !empty($thin_evaluated)) {
            return;
        }

        $hook = 'ssf_auto_generate_meta_run';
        $args = [(int) $post->ID];
        if (!wp_next_scheduled($hook, $args)) {
            wp_schedule_single_event(time() + 5, $hook, $args);
        }
    }

    /**
     * Async worker — does the actual AI title/description/keyword generation.
     * Uses the Bedrock SEO bundle for a single parallel-capable call when the
     * active provider is Bedrock; falls back to sequential calls otherwise.
     *
     * Also handles thin-content detection: posts below the configured word
     * threshold (default 50) with no redeeming image alt/caption text are
     * automatically flagged `_ssf_noindex = 1` so Google won't index them as
     * thin-content pages.
     */
    public function cron_auto_generate_meta($post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (!in_array($post->post_type, $post_types, true)) {
            return;
        }

        if (class_exists('SSF_History')) {
            SSF_History::set_source('auto_publish');
        }

        // --- Thin-content auto-noindex ---------------------------------------
        // If the post is below the word threshold AND image context can't save
        // it, mark it noindex so reports/Search Console don't flag it as an
        // issue. User can still manually override via the meta box.
        $auto_noindex = (bool) Smart_SEO_Fixer::get_option('auto_noindex_thin', true);
        $threshold    = (int) Smart_SEO_Fixer::get_option('thin_content_threshold', 50);
        $threshold    = max(20, min(300, $threshold));
        $word_count   = class_exists('SSF_Validator')
            ? SSF_Validator::get_content_word_count($post)
            : str_word_count(wp_strip_all_tags($post->post_content));
        $is_thin      = class_exists('SSF_Validator')
            ? SSF_Validator::is_thin_content($post, $threshold)
            : ($word_count < $threshold);

        if ($auto_noindex) {
            $current_noindex = get_post_meta($post_id, '_ssf_noindex', true);
            $was_auto        = get_post_meta($post_id, '_ssf_auto_noindex', true);
            if ($is_thin) {
                // Only set noindex if it isn't already set, or if we set it
                // automatically before (so we don't overwrite a user choice).
                if (empty($current_noindex) || $was_auto) {
                    update_post_meta($post_id, '_ssf_noindex', 1);
                    update_post_meta($post_id, '_ssf_auto_noindex', 1);
                }
            } else {
                // Post grew out of thin state — if WE set noindex, lift it.
                if ($was_auto && $current_noindex) {
                    update_post_meta($post_id, '_ssf_noindex', 0);
                    delete_post_meta($post_id, '_ssf_auto_noindex');
                }
            }
        }
        update_post_meta($post_id, '_ssf_thin_evaluated', time());
        update_post_meta($post_id, '_ssf_content_word_count', $word_count);

        // --- Meta generation -------------------------------------------------
        $seo_title     = get_post_meta($post_id, '_ssf_seo_title', true);
        $meta_desc     = get_post_meta($post_id, '_ssf_meta_description', true);
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);

        if (!empty($seo_title) && !empty($meta_desc) && !empty($focus_keyword)) {
            if (class_exists('SSF_Analyzer')) {
                (new SSF_Analyzer())->analyze_post($post_id);
            }
            return;
        }

        $ai = SSF_AI::get();
        if (!$ai->is_configured()) {
            return;
        }

        // Build the content we send to the AI. For image-heavy / thin posts,
        // append extracted image alt/caption/title so the AI has something
        // real to work with. Disabled if the user turned enrich off.
        $enrich_images = (bool) Smart_SEO_Fixer::get_option('enrich_image_posts', true);
        $ai_content    = $post->post_content;
        if ($enrich_images && class_exists('SSF_Validator')) {
            $image_ctx = SSF_Validator::extract_image_seo_context($post);
            if ($image_ctx !== '' && ($is_thin || $word_count < 150)) {
                $ai_content = trim($post->post_content)
                    . "\n\n[Image descriptions: " . $image_ctx . "]";
            }
        }

        // Try the one-call SEO bundle first (Bedrock only, cheapest + fastest).
        if ($ai instanceof SSF_Bedrock && method_exists($ai, 'generate_seo_bundle')) {
            $bundle = $ai->generate_seo_bundle($ai_content, $post->post_title);
            if (!is_wp_error($bundle)) {
                $haystack = strtolower(wp_strip_all_tags(strip_shortcodes($post->post_title . "\n" . $ai_content)));
                if (empty($focus_keyword) && !empty($bundle['keyword'])) {
                    $kw = trim((string) $bundle['keyword']);
                    if ($kw === '' || strpos($haystack, strtolower($kw)) === false) {
                        $kw = SSF_AI::pick_grounded_keyword($ai_content, $post->post_title);
                    }
                    if (!empty($kw)) {
                        update_post_meta($post_id, '_ssf_focus_keyword', sanitize_text_field($kw));
                        $focus_keyword = $kw;
                    }
                }
                if (empty($seo_title) && !empty($bundle['title'])) {
                    $title = SSF_Validator::enforce_seo_title(trim((string) $bundle['title']), 60);
                    if ($title !== '') {
                        update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
                        $seo_title = $title;
                    }
                }
                if (empty($meta_desc) && !empty($bundle['description'])) {
                    $desc = SSF_Validator::enforce_meta_description(trim((string) $bundle['description']), 160);
                    if ($desc !== '') {
                        update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
                        $meta_desc = $desc;
                    }
                }
            }
        }

        // Fill anything still missing with single per-field calls.
        if (empty($seo_title)) {
            $title = $ai->generate_title($ai_content, $post->post_title, $focus_keyword);
            if (!is_wp_error($title) && !empty(trim($title))) {
                $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                update_post_meta($post_id, '_ssf_seo_title', sanitize_text_field($title));
            }
        }
        if (empty($meta_desc)) {
            $desc = $ai->generate_meta_description($ai_content, '', $focus_keyword);
            if (!is_wp_error($desc) && !empty(trim($desc))) {
                $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                update_post_meta($post_id, '_ssf_meta_description', sanitize_textarea_field($desc));
            }
        }
        if (empty($focus_keyword)) {
            $kw = SSF_AI::pick_grounded_keyword($ai_content, $post->post_title);
            if (!empty($kw)) {
                update_post_meta($post_id, '_ssf_focus_keyword', $kw);
            }
        }

        if (class_exists('SSF_Analyzer')) {
            (new SSF_Analyzer())->analyze_post($post_id);
        }
    }

    /**
     * (Legacy synchronous helper — kept for backwards compat with any callers
     * but no longer wired to transition_post_status.)
     */
    public function _legacy_auto_generate_meta($new_status, $old_status, $post) {
        // Only process published posts
        if ($new_status !== 'publish') {
            return;
        }
        
        // Check if auto_meta is enabled
        if (!Smart_SEO_Fixer::get_option('auto_meta')) {
            return;
        }
        
        // Prevent infinite loops (flag so we only run once per request)
        static $processed = [];
        if (isset($processed[$post->ID])) {
            return;
        }
        $processed[$post->ID] = true;
        
        // Check if this post type is in our managed types
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (!in_array($post->post_type, $post_types)) {
            return;
        }
        
        // Skip if content is too short
        if (str_word_count(strip_tags($post->post_content)) < 20) {
            return;
        }
        
        // Check if any SEO data is actually missing — skip if all filled
        $seo_title = get_post_meta($post->ID, '_ssf_seo_title', true);
        $meta_desc = get_post_meta($post->ID, '_ssf_meta_description', true);
        $focus_keyword = get_post_meta($post->ID, '_ssf_focus_keyword', true);
        
        if (!empty($seo_title) && !empty($meta_desc) && !empty($focus_keyword)) {
            // Everything already filled — just re-analyze for score
            if (class_exists('SSF_Analyzer')) {
                $analyzer = new SSF_Analyzer();
                $analyzer->analyze_post($post->ID);
            }
            return;
        }
        
        $openai = SSF_AI::get();
        if (!$openai->is_configured()) {
            return;
        }

        // Enrich with excerpt / public meta / image alts so page-builder and
        // location CPTs with empty post_content still get real AI output.
        $enriched = class_exists('SSF_Job_Queue') ? SSF_Job_Queue::enrich_post_context($post) : (string) $post->post_content;

        // Auto-generate SEO title if empty
        if (empty($seo_title)) {
            $title = $openai->generate_title($enriched, $post->post_title, $focus_keyword);
            if (!is_wp_error($title) && !empty(trim($title))) {
                $title = SSF_Validator::enforce_seo_title(trim($title), 60);
                update_post_meta($post->ID, '_ssf_seo_title', sanitize_text_field($title));
            }
        }
        
        // Auto-generate meta description if empty
        if (empty($meta_desc)) {
            $desc = $openai->generate_meta_description($enriched, '', $focus_keyword);
            if (!is_wp_error($desc) && !empty(trim($desc))) {
                $desc = SSF_Validator::enforce_meta_description(trim($desc), 160);
                update_post_meta($post->ID, '_ssf_meta_description', sanitize_textarea_field($desc));
            }
        }
        
        // Auto-generate focus keyword if empty
        if (empty($focus_keyword)) {
            $kw = SSF_AI::pick_grounded_keyword($enriched, $post->post_title);
            if (!empty($kw)) {
                update_post_meta($post->ID, '_ssf_focus_keyword', $kw);
            }
        }
        
        // Run analysis
        if (class_exists('SSF_Analyzer')) {
            $analyzer = new SSF_Analyzer();
            $analyzer->analyze_post($post->ID);
        }
    }
    
    /**
     * Auto-generate alt text for uploaded images.
     *
     * @deprecated 2.0.67 No longer hooked. Superseded by
     * SSF_Image_SEO::schedule_alt_on_upload(), which defers the vision call to
     * cron, shares the cleaned-up filename heuristic, and tags its output so a
     * later regenerate pass can replace it. Kept only so any third-party code
     * calling this method directly does not fatal.
     */
    public function auto_generate_alt_text($attachment_id) {
        // Check if auto_alt_text is enabled
        if (!Smart_SEO_Fixer::get_option('auto_alt_text')) {
            return;
        }
        
        // Only process images
        $mime = get_post_mime_type($attachment_id);
        if (strpos($mime, 'image/') !== 0) {
            return;
        }
        
        // Skip if already has alt text
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return;
        }
        
        // Delegate to the single implementation so this path cannot drift from
        // the maintained one (context building, output sanitising, the fixed
        // filename heuristic, and the generated-by-us marker all live there).
        if (class_exists('SSF_Image_SEO')) {
            SSF_Image_SEO::auto_alt_on_upload($attachment_id);
        }
    }
}

