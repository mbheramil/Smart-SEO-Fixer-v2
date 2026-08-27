<?php
/**
 * Setup Wizard
 * 
 * First-run guided setup that helps users configure the plugin
 * after activation. Covers API key, post types, and feature toggles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Setup_Wizard {
    
    /**
     * Constructor - hooks into admin
     */
    public function __construct() {
        add_action('admin_init', [$this, 'maybe_redirect']);
        add_action('admin_menu', [$this, 'add_wizard_page']);
        add_action('wp_ajax_ssf_wizard_save', [$this, 'save_wizard']);
    }
    
    /**
     * Check if setup is completed
     */
    public static function is_completed() {
        return (bool) get_option('ssf_setup_completed', false);
    }
    
    /**
     * Mark setup as completed
     */
    public static function mark_completed() {
        update_option('ssf_setup_completed', true);
    }
    
    /**
     * Redirect to wizard on first activation
     */
    public function maybe_redirect() {
        if (self::is_completed()) {
            return;
        }
        
        // Only redirect once after activation
        if (!get_transient('ssf_activation_redirect')) {
            return;
        }
        
        delete_transient('ssf_activation_redirect');
        
        // Don't redirect during bulk activation, AJAX, or CLI
        if (
            wp_doing_ajax() ||
            (defined('WP_CLI') && WP_CLI) ||
            isset($_GET['activate-multi']) ||
            !current_user_can('manage_options')
        ) {
            return;
        }
        
        wp_safe_redirect(admin_url('admin.php?page=smart-seo-fixer-setup'));
        exit;
    }
    
    /**
     * Add hidden menu page for the wizard
     */
    public function add_wizard_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Setup Wizard', 'smart-seo-fixer'),
            __('Setup Wizard', 'smart-seo-fixer'),
            'manage_options',
            'smart-seo-fixer-setup',
            [$this, 'render']
        );
    }
    
    /**
     * Save wizard data via AJAX
     */
    public function save_wizard() {
        if (!check_ajax_referer('ssf_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'smart-seo-fixer')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $step = sanitize_key($_POST['step'] ?? '');
        
        switch ($step) {
            case 'api_key':
                $access_key = sanitize_text_field($_POST['bedrock_access_key'] ?? '');
                $secret_key = sanitize_text_field($_POST['bedrock_secret_key'] ?? '');
                $region     = sanitize_text_field($_POST['bedrock_region'] ?? 'us-east-1');
                $model      = 'us.anthropic.claude-haiku-4-5-20251001-v1:0';
                
                if (!empty($access_key)) {
                    update_option('ssf_bedrock_access_key', $access_key);
                }
                if (!empty($secret_key)) {
                    update_option('ssf_bedrock_secret_key', $secret_key);
                }
                update_option('ssf_bedrock_region', $region);
                update_option('ssf_bedrock_model', $model);
                update_option('ssf_ai_provider', 'bedrock');
                
                wp_send_json_success(['message' => __('AWS Bedrock settings saved.', 'smart-seo-fixer')]);
                break;
                
            case 'post_types':
                $post_types = isset($_POST['post_types']) ? array_map('sanitize_key', (array) $_POST['post_types']) : ['post', 'page'];
                if (empty($post_types)) {
                    $post_types = ['post', 'page'];
                }
                update_option('ssf_post_types', $post_types);
                
                wp_send_json_success(['message' => __('Post types saved.', 'smart-seo-fixer')]);
                break;
                
            case 'features':
                $features = $_POST['features'] ?? [];
                
                update_option('ssf_enable_schema', !empty($features['enable_schema']));
                update_option('ssf_enable_sitemap', !empty($features['enable_sitemap']));
                update_option('ssf_auto_meta', !empty($features['auto_meta']));
                update_option('ssf_auto_alt_text', !empty($features['auto_alt_text']));
                update_option('ssf_background_seo_cron', !empty($features['background_seo_cron']));
                
                $separator = sanitize_text_field($features['title_separator'] ?? '|');
                if (!empty($separator)) {
                    update_option('ssf_title_separator', $separator);
                }
                
                wp_send_json_success(['message' => __('Features configured.', 'smart-seo-fixer')]);
                break;
                
            case 'complete':
                self::mark_completed();
                
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::info('Setup wizard completed', 'general');
                }
                
                wp_send_json_success([
                    'message'  => __('Setup complete! Redirecting to dashboard...', 'smart-seo-fixer'),
                    'redirect' => admin_url('admin.php?page=smart-seo-fixer'),
                ]);
                break;
                
            default:
                wp_send_json_error(['message' => __('Unknown step.', 'smart-seo-fixer')]);
        }
    }
    
    /**
     * Render the wizard
     */
    public function render() {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $current_key = Smart_SEO_Fixer::get_option('bedrock_access_key', '');
        ?>
        <div class="ssf-wizard-wrap">
            <div class="ssf-wizard-container">
                <!-- Header -->
                <div class="ssf-wizard-header">
                    <h1>
                        <span class="dashicons dashicons-chart-line" style="font-size: 28px; color: #3b82f6;"></span>
                        <?php esc_html_e('Smart SEO Fixer Setup', 'smart-seo-fixer'); ?>
                    </h1>
                    <p><?php esc_html_e('Let\'s get your plugin configured in under a minute.', 'smart-seo-fixer'); ?></p>
                </div>
                
                <!-- Progress -->
                <div class="ssf-wizard-progress">
                    <div class="ssf-wizard-steps">
                        <div class="ssf-wizard-step active" data-step="1">
                            <span class="ssf-step-num">1</span>
                            <span class="ssf-step-label"><?php esc_html_e('API Key', 'smart-seo-fixer'); ?></span>
                        </div>
                        <div class="ssf-wizard-step" data-step="2">
                            <span class="ssf-step-num">2</span>
                            <span class="ssf-step-label"><?php esc_html_e('Content', 'smart-seo-fixer'); ?></span>
                        </div>
                        <div class="ssf-wizard-step" data-step="3">
                            <span class="ssf-step-num">3</span>
                            <span class="ssf-step-label"><?php esc_html_e('Features', 'smart-seo-fixer'); ?></span>
                        </div>
                        <div class="ssf-wizard-step" data-step="4">
                            <span class="ssf-step-num">4</span>
                            <span class="ssf-step-label"><?php esc_html_e('Done', 'smart-seo-fixer'); ?></span>
                        </div>
                    </div>
                    <div class="ssf-wizard-bar">
                        <div class="ssf-wizard-bar-fill" id="ssf-wizard-bar-fill" style="width: 25%;"></div>
                    </div>
                </div>
                
                <!-- Step 1: AWS Bedrock Credentials -->
                <div class="ssf-wizard-panel" id="ssf-step-1">
                    <h2><?php esc_html_e('Connect AWS Bedrock', 'smart-seo-fixer'); ?></h2>
                    <p class="description"><?php esc_html_e('Your AWS Bedrock credentials power all AI features — title generation, meta descriptions, keyword suggestions, and more.', 'smart-seo-fixer'); ?></p>
                    
                    <div class="ssf-wizard-field">
                        <label for="ssf-wiz-access-key"><?php esc_html_e('AWS Access Key ID', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="ssf-wiz-access-key" value="<?php echo esc_attr($current_key); ?>" placeholder="AKIA..." class="ssf-wizard-input" autocomplete="off">
                    </div>
                    
                    <div class="ssf-wizard-field">
                        <label for="ssf-wiz-secret-key"><?php esc_html_e('AWS Secret Access Key', 'smart-seo-fixer'); ?></label>
                        <input type="password" id="ssf-wiz-secret-key" placeholder="<?php esc_attr_e('Your secret access key', 'smart-seo-fixer'); ?>" class="ssf-wizard-input" autocomplete="off">
                    </div>
                    
                    <div class="ssf-wizard-field">
                        <label for="ssf-wiz-region"><?php esc_html_e('AWS Region', 'smart-seo-fixer'); ?></label>
                        <select id="ssf-wiz-region" class="ssf-wizard-input">
                            <option value="us-east-1">us-east-1 — US East (N. Virginia)</option>
                            <option value="us-west-2">us-west-2 — US West (Oregon)</option>
                            <option value="eu-west-1">eu-west-1 — EU (Ireland)</option>
                            <option value="eu-central-1">eu-central-1 — EU (Frankfurt)</option>
                            <option value="ap-southeast-1">ap-southeast-1 — Asia Pacific (Singapore)</option>
                            <option value="ap-northeast-1">ap-northeast-1 — Asia Pacific (Tokyo)</option>
                        </select>
                    </div>
                    

                </div>
                
                <!-- Step 2: Post Types -->
                <div class="ssf-wizard-panel" id="ssf-step-2" style="display:none;">
                    <h2><?php esc_html_e('What content to optimize?', 'smart-seo-fixer'); ?></h2>
                    <p class="description"><?php esc_html_e('Select which content types the plugin should manage SEO for.', 'smart-seo-fixer'); ?></p>
                    
                    <div class="ssf-wizard-checkboxes">
                        <?php foreach ($post_types as $pt): 
                            if (in_array($pt->name, ['attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles'])) continue;
                        ?>
                        <label class="ssf-wizard-checkbox">
                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt->name); ?>" 
                                <?php checked(in_array($pt->name, $selected_types)); ?>>
                            <span class="ssf-check-box"></span>
                            <span class="ssf-check-label">
                                <strong><?php echo esc_html($pt->label); ?></strong>
                                <small><?php echo esc_html($pt->name); ?></small>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Step 3: Features -->
                <div class="ssf-wizard-panel" id="ssf-step-3" style="display:none;">
                    <h2><?php esc_html_e('Enable Features', 'smart-seo-fixer'); ?></h2>
                    <p class="description"><?php esc_html_e('Toggle the features you want active. You can change these anytime in Settings.', 'smart-seo-fixer'); ?></p>
                    
                    <div class="ssf-wizard-toggles">
                        <label class="ssf-wizard-toggle">
                            <span class="ssf-toggle-info">
                                <strong><?php esc_html_e('Schema Markup', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Auto-generate structured data for rich search results', 'smart-seo-fixer'); ?></small>
                            </span>
                            <input type="checkbox" name="features[enable_schema]" value="1" checked>
                            <span class="ssf-toggle-switch"></span>
                        </label>
                        
                        <label class="ssf-wizard-toggle">
                            <span class="ssf-toggle-info">
                                <strong><?php esc_html_e('XML Sitemap', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Generate and serve an optimized sitemap for search engines', 'smart-seo-fixer'); ?></small>
                            </span>
                            <input type="checkbox" name="features[enable_sitemap]" value="1" checked>
                            <span class="ssf-toggle-switch"></span>
                        </label>
                        
                        <label class="ssf-wizard-toggle">
                            <span class="ssf-toggle-info">
                                <strong><?php esc_html_e('Auto-generate on Publish', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Automatically create SEO title and description when you publish a post', 'smart-seo-fixer'); ?></small>
                            </span>
                            <input type="checkbox" name="features[auto_meta]" value="1">
                            <span class="ssf-toggle-switch"></span>
                        </label>
                        
                        <label class="ssf-wizard-toggle">
                            <span class="ssf-toggle-info">
                                <strong><?php esc_html_e('Background SEO Cron', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('Auto-fill missing SEO data in background (twice daily, 10 posts/run)', 'smart-seo-fixer'); ?></small>
                            </span>
                            <input type="checkbox" name="features[background_seo_cron]" value="1" checked>
                            <span class="ssf-toggle-switch"></span>
                        </label>
                        
                        <label class="ssf-wizard-toggle">
                            <span class="ssf-toggle-info">
                                <strong><?php esc_html_e('Auto Alt Text', 'smart-seo-fixer'); ?></strong>
                                <small><?php esc_html_e('AI-generate alt text when images are uploaded', 'smart-seo-fixer'); ?></small>
                            </span>
                            <input type="checkbox" name="features[auto_alt_text]" value="1">
                            <span class="ssf-toggle-switch"></span>
                        </label>
                    </div>
                    
                    <div class="ssf-wizard-field" style="margin-top: 20px;">
                        <label for="ssf-wiz-separator"><?php esc_html_e('Title Separator', 'smart-seo-fixer'); ?></label>
                        <select id="ssf-wiz-separator" class="ssf-wizard-input" style="width: 200px;">
                            <option value="|">|</option>
                            <option value="-">-</option>
                            <option value="—">—</option>
                            <option value="·">·</option>
                            <option value=">">&gt;</option>
                        </select>
                        <p class="ssf-wizard-hint"><?php esc_html_e('Appears between page title and site name (e.g. "My Page | My Site")', 'smart-seo-fixer'); ?></p>
                    </div>
                </div>
                
                <!-- Step 4: Complete -->
                <div class="ssf-wizard-panel" id="ssf-step-4" style="display:none;">
                    <div style="text-align: center; padding: 40px 0;">
                        <div style="font-size: 64px; margin-bottom: 16px;">&#127881;</div>
                        <h2><?php esc_html_e('You\'re All Set!', 'smart-seo-fixer'); ?></h2>
                        <p class="description" style="font-size: 15px; max-width: 500px; margin: 0 auto 24px;">
                            <?php esc_html_e('Smart SEO Fixer is configured and ready. Here\'s what happens next:', 'smart-seo-fixer'); ?>
                        </p>
                        <div style="text-align: left; max-width: 400px; margin: 0 auto 30px; background: #f8fafc; padding: 20px; border-radius: 8px;">
                            <div style="margin-bottom: 12px;"><span class="dashicons dashicons-yes" style="color: #10b981;"></span> <?php esc_html_e('SEO meta box added to your post editor', 'smart-seo-fixer'); ?></div>
                            <div style="margin-bottom: 12px;"><span class="dashicons dashicons-yes" style="color: #10b981;"></span> <?php esc_html_e('AI-powered title and description generation', 'smart-seo-fixer'); ?></div>
                            <div style="margin-bottom: 12px;"><span class="dashicons dashicons-yes" style="color: #10b981;"></span> <?php esc_html_e('SEO score analysis for every post', 'smart-seo-fixer'); ?></div>
                            <div><span class="dashicons dashicons-yes" style="color: #10b981;"></span> <?php esc_html_e('Background cron fills gaps automatically', 'smart-seo-fixer'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="ssf-wizard-nav">
                    <button type="button" class="button" id="ssf-wiz-skip" style="color: #64748b;">
                        <?php esc_html_e('Skip Setup', 'smart-seo-fixer'); ?>
                    </button>
                    <div>
                        <button type="button" class="button" id="ssf-wiz-prev" style="display:none;">
                            &laquo; <?php esc_html_e('Back', 'smart-seo-fixer'); ?>
                        </button>
                        <button type="button" class="button button-primary button-hero" id="ssf-wiz-next">
                            <?php esc_html_e('Continue', 'smart-seo-fixer'); ?> &raquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .ssf-wizard-wrap { min-height: 100vh; background: #f1f5f9; display: flex; justify-content: center; padding: 40px 20px; }
        .ssf-wizard-container { max-width: 680px; width: 100%; }
        .ssf-wizard-header { text-align: center; margin-bottom: 32px; }
        .ssf-wizard-header h1 { font-size: 28px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
        .ssf-wizard-header p { color: #64748b; font-size: 15px; margin: 0; }
        
        .ssf-wizard-progress { margin-bottom: 32px; }
        .ssf-wizard-steps { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .ssf-wizard-step { display: flex; align-items: center; gap: 6px; color: #94a3b8; font-size: 13px; }
        .ssf-wizard-step.active { color: #3b82f6; font-weight: 600; }
        .ssf-wizard-step.done { color: #10b981; }
        .ssf-step-num { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: #e2e8f0; color: #64748b; }
        .ssf-wizard-step.active .ssf-step-num { background: #3b82f6; color: #fff; }
        .ssf-wizard-step.done .ssf-step-num { background: #10b981; color: #fff; }
        .ssf-wizard-bar { height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
        .ssf-wizard-bar-fill { height: 100%; background: #3b82f6; border-radius: 2px; transition: width 0.4s ease; }
        
        .ssf-wizard-panel { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .ssf-wizard-panel h2 { margin: 0 0 8px; font-size: 20px; color: #1e293b; }
        .ssf-wizard-panel .description { color: #64748b; margin: 0 0 24px; }
        
        .ssf-wizard-field { margin-bottom: 20px; }
        .ssf-wizard-field label { display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 14px; }
        .ssf-wizard-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .ssf-wizard-input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .ssf-wizard-hint { color: #94a3b8; font-size: 12px; margin-top: 6px; }
        .ssf-wizard-hint a { color: #3b82f6; }
        
        .ssf-wizard-checkboxes { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .ssf-wizard-checkbox { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .ssf-wizard-checkbox:hover { border-color: #3b82f6; background: #eff6ff; }
        .ssf-wizard-checkbox input { display: none; }
        .ssf-wizard-checkbox input:checked ~ .ssf-check-box { background: #3b82f6; border-color: #3b82f6; }
        .ssf-wizard-checkbox input:checked ~ .ssf-check-box::after { content: '\\2713'; color: #fff; font-size: 12px; }
        .ssf-check-box { width: 20px; height: 20px; border: 2px solid #d1d5db; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ssf-check-label strong { display: block; font-size: 14px; color: #1e293b; }
        .ssf-check-label small { color: #94a3b8; font-size: 11px; }
        
        .ssf-wizard-toggles { display: flex; flex-direction: column; gap: 12px; }
        .ssf-wizard-toggle { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; }
        .ssf-wizard-toggle:hover { background: #f8fafc; }
        .ssf-toggle-info strong { display: block; font-size: 14px; color: #1e293b; }
        .ssf-toggle-info small { color: #94a3b8; font-size: 12px; }
        .ssf-wizard-toggle input { display: none; }
        .ssf-toggle-switch { width: 44px; height: 24px; background: #d1d5db; border-radius: 12px; position: relative; flex-shrink: 0; transition: background 0.2s; }
        .ssf-toggle-switch::after { content: ''; width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 3px; left: 3px; transition: transform 0.2s; }
        .ssf-wizard-toggle input:checked ~ .ssf-toggle-switch { background: #3b82f6; }
        .ssf-wizard-toggle input:checked ~ .ssf-toggle-switch::after { transform: translateX(20px); }
        
        .ssf-wizard-nav { display: flex; justify-content: space-between; align-items: center; }
        .ssf-wizard-nav .button-hero { padding: 10px 32px !important; font-size: 15px !important; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var currentStep = 1;
            var totalSteps = 4;
            
            function showStep(step) {
                currentStep = step;
                
                $('.ssf-wizard-panel').hide();
                $('#ssf-step-' + step).show();
                
                // Progress
                $('#ssf-wizard-bar-fill').css('width', (step / totalSteps * 100) + '%');
                
                // Step indicators
                $('.ssf-wizard-step').each(function() {
                    var s = $(this).data('step');
                    $(this).toggleClass('active', s === step);
                    $(this).toggleClass('done', s < step);
                });
                
                // Nav buttons
                $('#ssf-wiz-prev').toggle(step > 1 && step < 4);
                
                if (step === 4) {
                    $('#ssf-wiz-next').text('<?php echo esc_js(__('Go to Dashboard', 'smart-seo-fixer')); ?>');
                    $('#ssf-wiz-skip').hide();
                } else {
                    $('#ssf-wiz-next').html('<?php echo esc_js(__('Continue', 'smart-seo-fixer')); ?> &raquo;');
                }
            }
            
            function saveStep(step, callback) {
                var data = { action: 'ssf_wizard_save', nonce: '<?php echo wp_create_nonce('ssf_nonce'); ?>' };
                
                if (step === 1) {
                    data.step = 'api_key';
                    data.bedrock_access_key = $('#ssf-wiz-access-key').val();
                    data.bedrock_secret_key = $('#ssf-wiz-secret-key').val();
                    data.bedrock_region = $('#ssf-wiz-region').val();
                    data.bedrock_model = $('#ssf-wiz-model').val();
                } else if (step === 2) {
                    data.step = 'post_types';
                    data.post_types = [];
                    $('input[name="post_types[]"]:checked').each(function() {
                        data.post_types.push($(this).val());
                    });
                } else if (step === 3) {
                    data.step = 'features';
                    data.features = {};
                    $('.ssf-wizard-toggles input[type="checkbox"]').each(function() {
                        var name = $(this).attr('name').replace('features[', '').replace(']', '');
                        data.features[name] = $(this).is(':checked') ? '1' : '';
                    });
                    data.features.title_separator = $('#ssf-wiz-separator').val();
                } else if (step === 4) {
                    data.step = 'complete';
                }
                
                $.post(ajaxurl, data, function(response) {
                    if (callback) callback(response);
                });
            }
            
            // Next button
            $('#ssf-wiz-next').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                if (currentStep === 4) {
                    saveStep(4, function(response) {
                        if (response.success && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    });
                    return;
                }
                
                saveStep(currentStep, function() {
                    $btn.prop('disabled', false);
                    showStep(currentStep + 1);
                });
            });
            
            // Back button
            $('#ssf-wiz-prev').on('click', function() {
                if (currentStep > 1) showStep(currentStep - 1);
            });
            
            // Skip button
            $('#ssf-wiz-skip').on('click', function() {
                if (confirm('<?php echo esc_js(__('Skip setup? You can configure everything in Settings later.', 'smart-seo-fixer')); ?>')) {
                    saveStep(4, function(response) {
                        if (response.success && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    });
                }
            });
            
            showStep(1);
        });
        </script>
        <?php
    }
}
