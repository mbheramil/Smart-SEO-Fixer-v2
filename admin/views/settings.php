<?php
/**
 * Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

$ai_provider     = Smart_SEO_Fixer::get_option('ai_provider', 'bedrock');
$bedrock_region  = Smart_SEO_Fixer::get_option('bedrock_region', 'us-east-1');
$bedrock_access  = Smart_SEO_Fixer::get_option('bedrock_access_key');
$bedrock_secret  = Smart_SEO_Fixer::get_option('bedrock_secret_key');

$openai_api_key  = Smart_SEO_Fixer::get_option('openai_api_key');
$openai_model    = Smart_SEO_Fixer::get_option('openai_model', 'gpt-4o-mini');

$claude_api_key  = Smart_SEO_Fixer::get_option('claude_api_key');
$claude_model    = Smart_SEO_Fixer::get_option('claude_model', 'claude-sonnet-4-20250514');

$gemini_api_key  = Smart_SEO_Fixer::get_option('gemini_api_key');
$gemini_model    = Smart_SEO_Fixer::get_option('gemini_model', 'gemini-2.0-flash');

// Check if credentials are set as wp-config.php constants
$const_access = defined('SSF_BEDROCK_ACCESS_KEY') && SSF_BEDROCK_ACCESS_KEY !== '';
$const_secret = defined('SSF_BEDROCK_SECRET_KEY') && SSF_BEDROCK_SECRET_KEY !== '';
$const_region = defined('SSF_BEDROCK_REGION')     && SSF_BEDROCK_REGION     !== '';
$using_consts = $const_access && $const_secret;

// For display purposes, use configured value from whichever source is active
$effective_access = $const_access ? SSF_BEDROCK_ACCESS_KEY : $bedrock_access;
$effective_secret = $const_secret ? SSF_BEDROCK_SECRET_KEY : $bedrock_secret;
$effective_region = $const_region ? SSF_BEDROCK_REGION     : $bedrock_region;
$bedrock_has_direct_keys = !empty($effective_access) && !empty($effective_secret);

// SSF_Bedrock::is_configured() also accounts for the broker — a site with
// no direct AWS key at all can still be fully configured because the
// broker authorizes it automatically (its hosting IP is recognized) or via
// a previously-issued broker token. Falls back to the raw key check if the
// class somehow isn't available.
$bedrock_configured     = class_exists('SSF_Bedrock') ? (new SSF_Bedrock())->is_configured() : $bedrock_has_direct_keys;
$bedrock_via_broker_only = $bedrock_configured && !$bedrock_has_direct_keys;

// Check if the ACTIVE provider is configured
switch ($ai_provider) {
    case 'openai':  $is_configured = !empty($openai_api_key); break;
    case 'claude':  $is_configured = !empty($claude_api_key); break;
    case 'gemini':  $is_configured = !empty($gemini_api_key); break;
    default:        $is_configured = $bedrock_configured; break;
}
$gsc_client_id = Smart_SEO_Fixer::get_option('gsc_client_id', '');
$gsc_client_secret = Smart_SEO_Fixer::get_option('gsc_client_secret', '');
$gsc_connected = false;
$gsc_site_url = '';
$gsc_sites = [];
$gsc_via_broker = false;
$gsc_broker_needs_setup = false;
if (class_exists('SSF_GSC_Client')) {
    $gsc_client = new SSF_GSC_Client();
    $gsc_connected = $gsc_client->is_connected();
    // Served by the central broker rather than this site's own OAuth client:
    // no Client ID/Secret needed here and no Connect step to click through.
    $gsc_via_broker = $gsc_client->is_using_broker() && !Smart_SEO_Fixer::get_option('gsc_client_id', '');
    $gsc_broker_needs_setup = $gsc_client->broker_needs_setup();
    $gsc_site_url = $gsc_client->get_site_url();
    if ($gsc_connected) {
        // Use cached site list first (fast), live fetch as fallback
        $cached = get_transient('ssf_gsc_sites_cache');
        if (!empty($cached) && is_array($cached)) {
            $gsc_sites = $cached;
        } else {
            $gsc_sites_result = $gsc_client->get_sites();
            if (!is_wp_error($gsc_sites_result) && !empty($gsc_sites_result)) {
                $gsc_sites = $gsc_sites_result;
                set_transient('ssf_gsc_sites_cache', $gsc_sites, DAY_IN_SECONDS);
            }
        }
    }
}

// Google Analytics 4 state
$ga_connected          = false;
$ga_measurement_id     = '';
$ga_property_id        = '';
$ga_auto_tag           = true;
$ga_client             = null;
$ga_via_broker         = false;
$ga_broker_needs_setup = false;
if (class_exists('SSF_GA_Client')) {
    $ga_client             = new SSF_GA_Client();
    $ga_connected          = $ga_client->is_connected();
    $ga_via_broker         = $ga_client->is_using_broker() && !Smart_SEO_Fixer::get_option('gsc_client_id', '');
    $ga_broker_needs_setup = $ga_client->broker_needs_setup();
    $ga_measurement_id     = $ga_client->get_measurement_id();
    $ga_property_id        = $ga_client->get_property_id();
    $ga_auto_tag           = (bool) get_option(SSF_GA_Client::AUTO_TAG_OPTION, true);
}

$auto_meta = Smart_SEO_Fixer::get_option('auto_meta', true);
$auto_alt_text = Smart_SEO_Fixer::get_option('auto_alt_text');
$auto_internal_links = Smart_SEO_Fixer::get_option('auto_internal_links', true);
$auto_noindex_thin = Smart_SEO_Fixer::get_option('auto_noindex_thin', true);
$enrich_image_posts = Smart_SEO_Fixer::get_option('enrich_image_posts', true);
$thin_content_threshold = (int) Smart_SEO_Fixer::get_option('thin_content_threshold', 50);
$enable_schema = Smart_SEO_Fixer::get_option('enable_schema', true);
$enable_sitemap = Smart_SEO_Fixer::get_option('enable_sitemap', true);
$disable_other_seo_output = Smart_SEO_Fixer::get_option('disable_other_seo_output', false);
$redirect_attachments = Smart_SEO_Fixer::get_option('redirect_attachments', '');
$title_separator = Smart_SEO_Fixer::get_option('title_separator', '|');
$homepage_title = Smart_SEO_Fixer::get_option('homepage_title');
$homepage_description = Smart_SEO_Fixer::get_option('homepage_description');
$post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);

// Detect conflicting SEO plugins
$conflicting_plugins = [];
if (defined('WPSEO_VERSION')) $conflicting_plugins[] = 'Yoast SEO';
if (defined('RANK_MATH_VERSION')) $conflicting_plugins[] = 'Rank Math';
if (defined('AIOSEO_VERSION') || class_exists('AIOSEOP_Core')) $conflicting_plugins[] = 'All in One SEO';
if (defined('THE_SEO_FRAMEWORK_VERSION')) $conflicting_plugins[] = 'The SEO Framework';
if (defined('SEOPRESS_VERSION')) $conflicting_plugins[] = 'SEOPress';

$available_post_types = get_post_types(['public' => true], 'objects');
unset($available_post_types['attachment']);
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('Smart SEO Fixer Settings', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <form id="ssf-settings-form" class="ssf-settings-form">
                <!-- AI Provider Settings -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-cloud"></span>
                    <?php esc_html_e('AI Provider', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <!-- Provider selector -->
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row"><label for="ai_provider"><?php esc_html_e('Provider', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <select name="ai_provider" id="ai_provider" style="min-width:220px;">
                                <option value="bedrock" <?php selected($ai_provider, 'bedrock'); ?>>AWS Bedrock (Claude)</option>
                                <option value="openai"  <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="claude"  <?php selected($ai_provider, 'claude'); ?>>Anthropic Claude (Direct)</option>
                                <option value="gemini"  <?php selected($ai_provider, 'gemini'); ?>>Google Gemini</option>
                            </select>
                            <span id="ssf-ai-status" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;padding:4px 12px;border-radius:20px;margin-left:10px;
                                <?php echo $is_configured ? 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;' : 'background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;'; ?>">
                                <span id="ssf-ai-status-dot" style="width:8px;height:8px;border-radius:50%;background:<?php echo $is_configured ? '#16a34a' : '#9ca3af'; ?>;"></span>
                                <span id="ssf-ai-status-text"><?php echo $is_configured ? esc_html__('Configured', 'smart-seo-fixer') : esc_html__('Not configured', 'smart-seo-fixer'); ?></span>
                            </span>
                        </td>
                    </tr>
                </table>

                <!-- AWS Bedrock Settings -->
                <div id="ssf-bedrock-settings" class="ssf-provider-panel" style="<?php echo $ai_provider !== 'bedrock' ? 'display:none;' : ''; ?>">
                    <hr style="margin:8px 0 16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
                        <h3 style="margin:0;"><?php esc_html_e('AWS Bedrock Configuration', 'smart-seo-fixer'); ?></h3>
                        <?php
                        // Deliberately never disabled by $bedrock_configured: a "not
                        // configured" verdict just means the broker declined last
                        // time (often a stale/wrong cached IP check) — the whole
                        // point of this button is to let the admin retry and see
                        // the real, current reason instead of being locked out of
                        // even trying.
                        ?>
                        <button type="button" class="button ssf-test-btn" id="ssf-test-bedrock" data-provider="bedrock">
                            <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                            <?php esc_html_e('Test Connection', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                    <?php if ($using_consts): ?>
                        <p class="description" style="margin:0 0 12px;">
                            <span style="color:#166534;font-weight:600;">&#128274; <?php esc_html_e('Credentials are set via wp-config.php constants.', 'smart-seo-fixer'); ?></span>
                        </p>
                        <p style="margin:0 0 4px;">
                            <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;padding:4px 12px;border-radius:20px;
                                <?php echo $bedrock_configured ? 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;' : 'background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;'; ?>">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $bedrock_configured ? '#16a34a' : '#9ca3af'; ?>;"></span>
                                <?php echo $bedrock_configured ? esc_html__('Configured', 'smart-seo-fixer') : esc_html__('Not configured', 'smart-seo-fixer'); ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <?php if ($bedrock_via_broker_only): ?>
                        <p style="margin:0 0 4px;">
                            <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;padding:4px 12px;border-radius:20px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">
                                <span style="width:8px;height:8px;border-radius:50%;background:#16a34a;"></span>
                                <?php esc_html_e('Configured', 'smart-seo-fixer'); ?>
                            </span>
                        </p>
                        <p class="description" style="margin:0 0 12px;">
                            <?php esc_html_e('This site is connected automatically — no AWS credentials are needed here. The fields below are optional; only fill them in if you want this site to use its own dedicated AWS account instead (also enables faster bulk processing).', 'smart-seo-fixer'); ?>
                        </p>
                        <?php else: ?>
                        <p class="description" style="margin:0 0 12px;">
                            <?php esc_html_e('Uses your own AWS account. Credentials are stored in WordPress options.', 'smart-seo-fixer'); ?>
                        </p>
                        <?php endif; ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="bedrock_access_key"><?php esc_html_e('Access Key ID', 'smart-seo-fixer'); ?></label></th>
                                <td>
                                    <input type="text" name="bedrock_access_key" id="bedrock_access_key"
                                           value="<?php echo esc_attr($bedrock_access); ?>"
                                           class="regular-text" autocomplete="off"
                                           placeholder="AKIA...">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bedrock_secret_key"><?php esc_html_e('Secret Access Key', 'smart-seo-fixer'); ?></label></th>
                                <td>
                                    <input type="password" name="bedrock_secret_key" id="bedrock_secret_key"
                                           value="<?php echo esc_attr($bedrock_secret); ?>"
                                           class="regular-text" autocomplete="off">
                                    <button type="button" class="button ssf-toggle-secret" data-target="bedrock_secret_key"><?php esc_html_e('Show', 'smart-seo-fixer'); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bedrock_region"><?php esc_html_e('AWS Region', 'smart-seo-fixer'); ?></label></th>
                                <td>
                                    <?php if ($const_region): ?>
                                        <input type="text" class="regular-text" value="<?php echo esc_attr($effective_region); ?>" disabled>
                                    <?php else: ?>
                                    <select name="bedrock_region" id="bedrock_region">
                                        <?php
                                        $regions = [
                                            'us-east-1'      => 'US East (N. Virginia)',
                                            'us-west-2'      => 'US West (Oregon)',
                                            'eu-west-1'      => 'EU (Ireland)',
                                            'eu-central-1'   => 'EU (Frankfurt)',
                                            'ap-southeast-1' => 'Asia Pacific (Singapore)',
                                            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                                        ];
                                        foreach ($regions as $code => $label):
                                        ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($bedrock_region, $code); ?>>
                                            <?php echo esc_html($code . ' — ' . $label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                    <div id="ssf-test-result-bedrock" class="ssf-test-result" style="display:none;margin-top:12px;padding:12px 16px;border-radius:6px;"></div>
                </div>

                <!-- OpenAI Settings -->
                <div id="ssf-openai-settings" class="ssf-provider-panel" style="<?php echo $ai_provider !== 'openai' ? 'display:none;' : ''; ?>">
                    <hr style="margin:8px 0 16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
                        <h3 style="margin:0;"><?php esc_html_e('OpenAI Configuration', 'smart-seo-fixer'); ?></h3>
                        <button type="button" class="button ssf-test-btn" id="ssf-test-openai" data-provider="openai" <?php echo empty($openai_api_key) ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                            <?php esc_html_e('Test Connection', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                    <p class="description" style="margin:0 0 12px;"><?php esc_html_e('Get your API key from platform.openai.com.', 'smart-seo-fixer'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="openai_api_key"><?php esc_html_e('API Key', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <input type="password" name="openai_api_key" id="openai_api_key"
                                       value="<?php echo esc_attr($openai_api_key); ?>"
                                       class="regular-text" autocomplete="off" placeholder="sk-...">
                                <button type="button" class="button ssf-toggle-secret" data-target="openai_api_key"><?php esc_html_e('Show', 'smart-seo-fixer'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="openai_model"><?php esc_html_e('Model', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <select name="openai_model" id="openai_model">
                                    <option value="gpt-4o-mini" <?php selected($openai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (fast, cheap)</option>
                                    <option value="gpt-4o" <?php selected($openai_model, 'gpt-4o'); ?>>GPT-4o (balanced)</option>
                                    <option value="gpt-4.1" <?php selected($openai_model, 'gpt-4.1'); ?>>GPT-4.1 (latest)</option>
                                    <option value="gpt-4.1-mini" <?php selected($openai_model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini</option>
                                    <option value="gpt-4.1-nano" <?php selected($openai_model, 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (fastest)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <div id="ssf-test-result-openai" class="ssf-test-result" style="display:none;margin-top:12px;padding:12px 16px;border-radius:6px;"></div>
                </div>

                <!-- Anthropic Claude Settings -->
                <div id="ssf-claude-settings" class="ssf-provider-panel" style="<?php echo $ai_provider !== 'claude' ? 'display:none;' : ''; ?>">
                    <hr style="margin:8px 0 16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
                        <h3 style="margin:0;"><?php esc_html_e('Anthropic Claude Configuration', 'smart-seo-fixer'); ?></h3>
                        <button type="button" class="button ssf-test-btn" id="ssf-test-claude" data-provider="claude" <?php echo empty($claude_api_key) ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                            <?php esc_html_e('Test Connection', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                    <p class="description" style="margin:0 0 12px;"><?php esc_html_e('Get your API key from console.anthropic.com.', 'smart-seo-fixer'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="claude_api_key"><?php esc_html_e('API Key', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <input type="password" name="claude_api_key" id="claude_api_key"
                                       value="<?php echo esc_attr($claude_api_key); ?>"
                                       class="regular-text" autocomplete="off" placeholder="sk-ant-...">
                                <button type="button" class="button ssf-toggle-secret" data-target="claude_api_key"><?php esc_html_e('Show', 'smart-seo-fixer'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="claude_model"><?php esc_html_e('Model', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <select name="claude_model" id="claude_model">
                                    <option value="claude-sonnet-4-20250514" <?php selected($claude_model, 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4 (recommended)</option>
                                    <option value="claude-3-5-haiku-20241022" <?php selected($claude_model, 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (fast, cheap)</option>
                                    <option value="claude-opus-4-20250514" <?php selected($claude_model, 'claude-opus-4-20250514'); ?>>Claude Opus 4 (most capable)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <div id="ssf-test-result-claude" class="ssf-test-result" style="display:none;margin-top:12px;padding:12px 16px;border-radius:6px;"></div>
                </div>

                <!-- Google Gemini Settings -->
                <div id="ssf-gemini-settings" class="ssf-provider-panel" style="<?php echo $ai_provider !== 'gemini' ? 'display:none;' : ''; ?>">
                    <hr style="margin:8px 0 16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
                        <h3 style="margin:0;"><?php esc_html_e('Google Gemini Configuration', 'smart-seo-fixer'); ?></h3>
                        <button type="button" class="button ssf-test-btn" id="ssf-test-gemini" data-provider="gemini" <?php echo empty($gemini_api_key) ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                            <?php esc_html_e('Test Connection', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                    <p class="description" style="margin:0 0 12px;"><?php esc_html_e('Get your API key from aistudio.google.com.', 'smart-seo-fixer'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="gemini_api_key"><?php esc_html_e('API Key', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <input type="password" name="gemini_api_key" id="gemini_api_key"
                                       value="<?php echo esc_attr($gemini_api_key); ?>"
                                       class="regular-text" autocomplete="off" placeholder="AIza...">
                                <button type="button" class="button ssf-toggle-secret" data-target="gemini_api_key"><?php esc_html_e('Show', 'smart-seo-fixer'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gemini_model"><?php esc_html_e('Model', 'smart-seo-fixer'); ?></label></th>
                            <td>
                                <select name="gemini_model" id="gemini_model">
                                    <option value="gemini-2.0-flash" <?php selected($gemini_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash (fast)</option>
                                    <option value="gemini-2.5-flash-preview-05-20" <?php selected($gemini_model, 'gemini-2.5-flash-preview-05-20'); ?>>Gemini 2.5 Flash (latest)</option>
                                    <option value="gemini-2.5-pro-preview-05-06" <?php selected($gemini_model, 'gemini-2.5-pro-preview-05-06'); ?>>Gemini 2.5 Pro (most capable)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <div id="ssf-test-result-gemini" class="ssf-test-result" style="display:none;margin-top:12px;padding:12px 16px;border-radius:6px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Google Search Console -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-google"></span>
                    <?php esc_html_e('Google Search Console', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <?php 
                $gsc_error = get_transient('ssf_gsc_error');
                $gsc_success = get_transient('ssf_gsc_success');
                if ($gsc_error): delete_transient('ssf_gsc_error'); ?>
                    <div class="notice notice-error inline" style="margin: 0 0 16px;">
                        <p><?php echo esc_html($gsc_error); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($gsc_success): delete_transient('ssf_gsc_success'); ?>
                    <div class="notice notice-success inline" style="margin: 0 0 16px;">
                        <p><?php echo esc_html($gsc_success); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($gsc_connected): ?>
                    <div style="padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 16px;">
                        <p style="margin: 0 0 8px; font-weight: 600; color: #15803d;">
                            <span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span>
                            <?php esc_html_e('Connected to Google Search Console', 'smart-seo-fixer'); ?>
                        </p>
                        <?php if ($gsc_site_url): ?>
                            <p style="margin: 0; color: #166534;">
                                <?php printf(esc_html__('Site: %s', 'smart-seo-fixer'), '<strong>' . esc_html($gsc_site_url) . '</strong>'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($gsc_via_broker): ?>
                            <p style="margin: 8px 0 0; color: #166534; font-size: 13px;">
                                <?php esc_html_e('This site is connected automatically — no Client ID, Client Secret or Google Cloud Console setup is needed here. Only the properties belonging to this domain are accessible.', 'smart-seo-fixer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gsc_site_url"><?php esc_html_e('Site Property', 'smart-seo-fixer'); ?></label>
                            </th>
                            <td>
                                <?php if (!empty($gsc_sites)): ?>
                                <select name="gsc_site_url" id="gsc_site_url" style="min-width: 300px;">
                                    <?php if (empty($gsc_site_url)): ?>
                                        <option value=""><?php esc_html_e('— Select a site —', 'smart-seo-fixer'); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($gsc_sites as $site): ?>
                                        <option value="<?php echo esc_attr($site['siteUrl']); ?>" <?php selected($gsc_site_url, $site['siteUrl']); ?>>
                                            <?php echo esc_html($site['siteUrl']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button button-small" id="ssf-gsc-refresh-sites" style="vertical-align: middle;">
                                    <span class="dashicons dashicons-update" style="vertical-align: text-bottom; font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php esc_html_e('Refresh', 'smart-seo-fixer'); ?>
                                </button>
                                <p class="description">
                                    <?php esc_html_e('Select which site property to use, then save settings.', 'smart-seo-fixer'); ?>
                                </p>
                                <?php else: ?>
                                <div style="margin-bottom: 8px;">
                                    <button type="button" class="button button-primary" id="ssf-gsc-refresh-sites">
                                        <span class="dashicons dashicons-update" style="vertical-align: text-bottom;"></span>
                                        <?php esc_html_e('Load Site List', 'smart-seo-fixer'); ?>
                                    </button>
                                    <span id="ssf-gsc-refresh-status" style="margin-left: 8px; color: #666;"></span>
                                </div>
                                <div id="ssf-gsc-sites-dropdown" style="display: none; margin-bottom: 8px;"></div>
                                <details style="margin-top: 8px;">
                                    <summary style="cursor: pointer; color: #666; font-size: 12px;"><?php esc_html_e('Or enter site URL manually', 'smart-seo-fixer'); ?></summary>
                                    <div style="margin-top: 8px;">
                                        <input type="text" name="gsc_site_url" id="gsc_site_url"
                                               value="<?php echo esc_attr($gsc_site_url); ?>"
                                               class="regular-text"
                                               placeholder="<?php esc_attr_e('e.g. https://example.com/ or sc-domain:example.com', 'smart-seo-fixer'); ?>" />
                                    </div>
                                </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-search-performance')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-chart-area" style="vertical-align: text-bottom;"></span>
                            <?php esc_html_e('View Search Performance', 'smart-seo-fixer'); ?>
                        </a>
                        <button type="button" class="button button-secondary" id="ssf-gsc-auto-setup">
                            <span class="dashicons dashicons-superhero" style="vertical-align: text-bottom;"></span>
                            <?php esc_html_e('Auto-Create Property for This Site', 'smart-seo-fixer'); ?>
                        </button>
                        <?php if (!$gsc_via_broker): ?>
                            <button type="button" class="button" id="ssf-gsc-disconnect" style="color: #dc2626; border-color: #dc2626;">
                                <?php esc_html_e('Disconnect', 'smart-seo-fixer'); ?>
                            </button>
                        <?php endif; ?>
                    </p>
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e('Auto-create will: add this site to Google Search Console, verify ownership via a meta tag, and submit your sitemap — all in one click. Use if the site isn\'t listed yet.', 'smart-seo-fixer'); ?>
                    </p>
                    <div id="ssf-gsc-auto-setup-log" style="display:none; margin-top:12px;"></div>

                    <?php if ($gsc_via_broker): ?>
                        <?php // Search Console needs no credentials here, but Google Analytics
                              // shares these same fields and is not brokered — so they stay
                              // reachable rather than disappearing with the Connect step. ?>
                        <details style="margin-top: 16px;">
                            <summary style="cursor: pointer; color: #555; font-size: 13px;">
                                <?php esc_html_e('Optional: Google OAuth credentials (only needed for Google Analytics)', 'smart-seo-fixer'); ?>
                            </summary>
                            <p class="description" style="margin: 8px 0 0;">
                                <?php esc_html_e('Search Console does not need these. Google Analytics is not covered by the shared connection, so it still uses a Client ID and Secret from your own Google Cloud project.', 'smart-seo-fixer'); ?>
                            </p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gsc_client_id"><?php esc_html_e('Client ID', 'smart-seo-fixer'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               name="gsc_client_id"
                                               id="gsc_client_id"
                                               value="<?php echo esc_attr($gsc_client_id); ?>"
                                               class="regular-text"
                                               placeholder="xxxxxx.apps.googleusercontent.com">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gsc_client_secret"><?php esc_html_e('Client Secret', 'smart-seo-fixer'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password"
                                               name="gsc_client_secret"
                                               id="gsc_client_secret"
                                               value="<?php echo esc_attr($gsc_client_secret); ?>"
                                               class="regular-text"
                                               autocomplete="off">
                                        <p class="description">
                                            <?php printf(
                                                __('Reuse the same pair across all your sites — add this redirect URI to that one OAuth client: %s', 'smart-seo-fixer'),
                                                '<br><code>' . esc_html(admin_url('admin.php?page=smart-seo-fixer-settings')) . '</code>'
                                            ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </details>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="description" style="margin-bottom: 16px;">
                        <?php esc_html_e('Connect your Google Search Console to see real search performance data, index status, and more — directly inside WordPress.', 'smart-seo-fixer'); ?>
                    </p>
                    <?php if ($gsc_broker_needs_setup): ?>
                        <div class="notice notice-warning inline" style="margin: 0 0 16px;">
                            <p>
                                <?php esc_html_e('This site is cleared to use the shared Search Console connection, but that connection needs to be re-authorized before it can serve data. Try Connect again shortly, or connect this site individually below in the meantime.', 'smart-seo-fixer'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <p>
                        <button type="button" class="button button-primary button-large" id="ssf-gsc-broker-connect">
                            <span class="dashicons dashicons-google" style="vertical-align: text-bottom;"></span>
                            <?php esc_html_e('Connect to Google', 'smart-seo-fixer'); ?>
                        </button>
                        <span id="ssf-gsc-broker-connect-status" style="margin-left: 8px; color: #666;"></span>
                    </p>
                    <p class="description" style="margin: 0 0 16px;">
                        <?php esc_html_e('No Google Cloud Console setup needed — click Connect and this site checks in with the shared Google connection automatically.', 'smart-seo-fixer'); ?>
                    </p>

                    <details id="ssf-gsc-manual-fallback"<?php echo (!empty($gsc_client_id) && !empty($gsc_client_secret)) ? ' open' : ''; ?>>
                        <summary style="cursor: pointer; color: #555; font-size: 13px;">
                            <?php esc_html_e('Advanced: use my own Google credentials instead', 'smart-seo-fixer'); ?>
                        </summary>
                        <div style="margin-top: 12px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gsc_client_id"><?php esc_html_e('Client ID', 'smart-seo-fixer'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               name="gsc_client_id"
                                               id="gsc_client_id"
                                               value="<?php echo esc_attr($gsc_client_id); ?>"
                                               class="regular-text"
                                               placeholder="xxxxxx.apps.googleusercontent.com">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gsc_client_secret"><?php esc_html_e('Client Secret', 'smart-seo-fixer'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password"
                                               name="gsc_client_secret"
                                               id="gsc_client_secret"
                                               value="<?php echo esc_attr($gsc_client_secret); ?>"
                                               class="regular-text"
                                               autocomplete="off">
                                    </td>
                                </tr>
                            </table>

                            <?php if (!empty($gsc_client_id) && !empty($gsc_client_secret)): ?>
                                <p>
                                    <a href="<?php echo esc_url($gsc_client->get_auth_url()); ?>" class="button button-primary">
                                        <span class="dashicons dashicons-google" style="vertical-align: text-bottom;"></span>
                                        <?php esc_html_e('Connect Google Search Console', 'smart-seo-fixer'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php esc_html_e('Enter your Client ID and Secret, save settings, then click Connect.', 'smart-seo-fixer'); ?>
                                </p>
                            <?php endif; ?>

                            <div style="margin-top: 12px; padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;">
                                <p style="margin: 0 0 8px; font-weight: 600; color: #1e40af;">
                                    <span class="dashicons dashicons-info" style="font-size: 16px;"></span>
                                    <?php esc_html_e('Already set this up on another site?', 'smart-seo-fixer'); ?>
                                </p>
                                <p style="margin: 0 0 10px; color: #1e3a5f; font-size: 13px;">
                                    <?php printf(
                                        /* translators: %s: this site's authorized redirect URI */
                                        __('You do not need a new credential per site. Paste the <strong>same</strong> Client ID and Secret you already use, then add just one more Authorized redirect URI to that existing OAuth client: %s', 'smart-seo-fixer'),
                                        '<br><code>' . esc_html(admin_url('admin.php?page=smart-seo-fixer-settings')) . '</code>'
                                    ); ?>
                                </p>
                                <p style="margin: 0 0 8px; font-weight: 600; color: #1e40af;">
                                    <?php esc_html_e('Setting it up for the first time:', 'smart-seo-fixer'); ?>
                                </p>
                                <ol style="margin: 0; padding-left: 20px; color: #1e3a5f; font-size: 13px;">
                                    <li><?php printf(
                                        __('Go to %s', 'smart-seo-fixer'),
                                        '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>'
                                    ); ?></li>
                                    <li><?php esc_html_e('Create a project (or select existing)', 'smart-seo-fixer'); ?></li>
                                    <li><?php esc_html_e('Enable "Google Search Console API"', 'smart-seo-fixer'); ?></li>
                                    <li><?php esc_html_e('Create OAuth 2.0 credentials (Web application type)', 'smart-seo-fixer'); ?></li>
                                    <li><?php printf(
                                        __('Add this as Authorized redirect URI: %s', 'smart-seo-fixer'),
                                        '<code>' . esc_html(admin_url('admin.php?page=smart-seo-fixer-settings')) . '</code>'
                                    ); ?></li>
                                    <li><?php esc_html_e('Copy the Client ID and Client Secret here', 'smart-seo-fixer'); ?></li>
                                    <li><?php esc_html_e('Set the OAuth consent screen to "In production" — while it is in "Testing", Google expires the connection every 7 days and the site silently disconnects', 'smart-seo-fixer'); ?></li>
                                </ol>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>

        <!-- Google Analytics (GA4) -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php esc_html_e('Google Analytics (GA4)', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <p class="description" style="margin-top:0;">
                    <?php esc_html_e('Connect GA4 to auto-create a property, install the tracking code, and show real visitor metrics in your Client Report.', 'smart-seo-fixer'); ?>
                </p>

                <?php if ($ga_connected): ?>
                    <div style="padding: 12px 16px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px; margin-bottom: 16px;">
                        <span class="dashicons dashicons-yes-alt" style="color: #10b981; vertical-align: middle;"></span>
                        <strong style="color: #047857;"><?php esc_html_e('Connected to Google Analytics', 'smart-seo-fixer'); ?></strong>
                        <?php if (!empty($ga_measurement_id)): ?>
                            <span style="margin-left:8px; color:#047857;">
                                <?php esc_html_e('Measurement ID:', 'smart-seo-fixer'); ?>
                                <code><?php echo esc_html($ga_measurement_id); ?></code>
                            </span>
                        <?php endif; ?>
                        <?php if ($ga_via_broker): ?>
                            <p style="margin: 8px 0 0; color: #166534; font-size: 13px;">
                                <?php esc_html_e('This site is connected automatically — no Client ID, Client Secret or Google Cloud Console setup is needed here.', 'smart-seo-fixer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <p style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="button button-primary" id="ssf-ga-auto-setup">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom;"></span>
                            <?php esc_html_e('Auto-Create GA4 Property for This Site', 'smart-seo-fixer'); ?>
                        </button>
                        <button type="button" class="button" id="ssf-ga-use-existing">
                            <span class="dashicons dashicons-admin-links" style="vertical-align:text-bottom;"></span>
                            <?php esc_html_e('Use Existing Property', 'smart-seo-fixer'); ?>
                        </button>
                        <button type="button" class="button" id="ssf-ga-test-report">
                            <span class="dashicons dashicons-chart-line" style="vertical-align:text-bottom;"></span>
                            <?php esc_html_e('Test Data Fetch (Last 7 Days)', 'smart-seo-fixer'); ?>
                        </button>
                        <?php if (!$ga_via_broker): ?>
                        <button type="button" class="button" id="ssf-ga-disconnect">
                            <?php esc_html_e('Disconnect', 'smart-seo-fixer'); ?>
                        </button>
                        <?php endif; ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('One-click setup creates a new GA4 property under your first Analytics account, adds a web data stream for this site, and installs the tracking code.', 'smart-seo-fixer'); ?>
                        <br>
                        <?php esc_html_e('Use "Use Existing Property" if the GA4 property for this site is in a different Google account you have access to (Viewer or higher).', 'smart-seo-fixer'); ?>
                    </p>
                    <div id="ssf-ga-auto-setup-log" style="display:none; margin-top:12px;"></div>
                    <div id="ssf-ga-existing-picker" style="display:none; margin-top:12px; padding:14px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                        <h4 style="margin:0 0 10px;"><?php esc_html_e('Choose an existing GA4 property', 'smart-seo-fixer'); ?></h4>
                        <p class="description" style="margin:0 0 10px;">
                            <?php esc_html_e('Shows every GA4 property the connected Google account can read — including properties owned by your clients, agencies, or other accounts that have granted you access.', 'smart-seo-fixer'); ?>
                        </p>
                        <div id="ssf-ga-picker-error" style="display:none; margin-bottom:12px;"></div>
                        <p>
                            <select id="ssf_ga_property_select" style="min-width:420px; max-width:100%;">
                                <option value=""><?php esc_html_e('Loading properties…', 'smart-seo-fixer'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" id="ssf_ga_install_tag_for_existing" checked>
                                <?php esc_html_e('Also install the tracking code (gtag.js) on this site using the selected property\'s Measurement ID', 'smart-seo-fixer'); ?>
                            </label>
                        </p>
                        <p>
                            <button type="button" class="button button-primary" id="ssf-ga-save-selection"><?php esc_html_e('Save Selection', 'smart-seo-fixer'); ?></button>
                            <button type="button" class="button" id="ssf-ga-cancel-selection"><?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>
                        </p>
                    </div>
                    <div id="ssf-ga-test-log" style="display:none; margin-top:12px;"></div>

                    <hr style="margin:16px 0;">
                    <h4 style="margin:0 0 8px;"><?php esc_html_e('Already have a GA4 property?', 'smart-seo-fixer'); ?></h4>
                    <p>
                        <label for="ssf_ga_manual_mid"><?php esc_html_e('Paste your Measurement ID:', 'smart-seo-fixer'); ?></label><br>
                        <input type="text" id="ssf_ga_manual_mid" class="regular-text" placeholder="G-XXXXXXXXXX" value="<?php echo esc_attr($ga_measurement_id); ?>">
                        <button type="button" class="button" id="ssf-ga-save-mid"><?php esc_html_e('Save', 'smart-seo-fixer'); ?></button>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" id="ssf_ga_auto_tag_toggle" <?php checked($ga_auto_tag); ?>>
                            <?php esc_html_e('Automatically inject gtag.js tracking code on every public page', 'smart-seo-fixer'); ?>
                        </label>
                    </p>
                <?php else: ?>
                    <?php if ($ga_broker_needs_setup): ?>
                        <div class="notice notice-warning inline" style="margin: 0 0 16px;">
                            <p>
                                <?php esc_html_e('This site is cleared to use the shared Google connection, but that connection itself needs to be re-authorized before it can serve Analytics data. Try Connect again shortly, or connect this site individually below in the meantime.', 'smart-seo-fixer'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <p>
                        <button type="button" class="button button-primary button-large" id="ssf-ga-broker-connect">
                            <span class="dashicons dashicons-google" style="vertical-align: text-bottom;"></span>
                            <?php esc_html_e('Connect to Google', 'smart-seo-fixer'); ?>
                        </button>
                        <span id="ssf-ga-broker-connect-status" style="margin-left: 8px; color: #666;"></span>
                    </p>
                    <p class="description" style="margin: 0 0 16px;">
                        <?php esc_html_e('No Google Cloud Console setup needed — click Connect and this site checks in with the shared Google connection automatically.', 'smart-seo-fixer'); ?>
                    </p>

                    <details id="ssf-ga-manual-fallback"<?php echo (!empty($gsc_client_id) && !empty($gsc_client_secret)) ? ' open' : ''; ?>>
                        <summary style="cursor: pointer; color: #555; font-size: 13px;">
                            <?php esc_html_e('Advanced: use my own Google credentials instead', 'smart-seo-fixer'); ?>
                        </summary>
                        <div style="margin-top: 12px;">
                            <?php if (!empty($gsc_client_id) && !empty($gsc_client_secret)): ?>
                                <p>
                                    <a href="<?php echo esc_url($ga_client ? $ga_client->get_auth_url() : '#'); ?>" class="button button-primary">
                                        <span class="dashicons dashicons-google" style="vertical-align: text-bottom;"></span>
                                        <?php esc_html_e('Connect Google Analytics', 'smart-seo-fixer'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php esc_html_e('Enter a Client ID and Secret in the Google Search Console section above (Analytics reuses the same credentials), save settings, then click Connect here.', 'smart-seo-fixer'); ?>
                                </p>
                            <?php endif; ?>
                            <div style="margin-top: 12px; padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;">
                                <p style="margin: 0 0 8px; font-weight: 600; color: #1e40af;">
                                    <span class="dashicons dashicons-info" style="font-size: 16px;"></span>
                                    <?php esc_html_e('One-time setup in Google Cloud:', 'smart-seo-fixer'); ?>
                                </p>
                                <ol style="margin: 0; padding-left: 20px; color: #1e3a5f; font-size: 13px;">
                                    <li><?php printf(
                                        __('In the same Google Cloud project used for Search Console, enable the %s and the %s', 'smart-seo-fixer'),
                                        '<a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank">Google Analytics Admin API</a>',
                                        '<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank">Google Analytics Data API</a>'
                                    ); ?></li>
                                    <li><?php esc_html_e('Click "Connect Google Analytics" above and grant the requested permissions', 'smart-seo-fixer'); ?></li>
                                    <li><?php printf(
                                        __('If you don\'t have any GA4 accounts yet, visit %s once to accept Google\'s Terms of Service first', 'smart-seo-fixer'),
                                        '<a href="https://analytics.google.com/" target="_blank">analytics.google.com</a>'
                                    ); ?></li>
                                </ol>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>

        <!-- General Settings -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('General Settings', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="title_separator"><?php esc_html_e('Title Separator', 'smart-seo-fixer'); ?></label>
                        </th>
                        <td>
                            <select name="title_separator" id="title_separator">
                                <option value="|" <?php selected($title_separator, '|'); ?>>|</option>
                                <option value="-" <?php selected($title_separator, '-'); ?>>-</option>
                                <option value="–" <?php selected($title_separator, '–'); ?>>–</option>
                                <option value="—" <?php selected($title_separator, '—'); ?>>—</option>
                                <option value="•" <?php selected($title_separator, '•'); ?>>•</option>
                                <option value="»" <?php selected($title_separator, '»'); ?>>»</option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Separator used between title parts.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Post Types', 'smart-seo-fixer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($available_post_types as $type): ?>
                                <label>
                                    <input type="checkbox" 
                                           name="post_types[]" 
                                           value="<?php echo esc_attr($type->name); ?>"
                                           <?php checked(in_array($type->name, $post_types)); ?>>
                                    <?php echo esc_html($type->labels->name); ?>
                                </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">
                                <?php esc_html_e('Select which post types to enable SEO features for.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Homepage SEO -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-home"></span>
                    <?php esc_html_e('Homepage SEO', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="homepage_title"><?php esc_html_e('Homepage Title', 'smart-seo-fixer'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="homepage_title" 
                                   id="homepage_title" 
                                   value="<?php echo esc_attr($homepage_title); ?>" 
                                   class="large-text">
                            <p class="description">
                                <?php esc_html_e('Custom title for the homepage. Leave empty to use default.', 'smart-seo-fixer'); ?>
                                <span class="ssf-char-count">
                                    <span id="homepage-title-count"><?php echo strlen($homepage_title); ?></span>/60
                                </span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="homepage_description"><?php esc_html_e('Homepage Description', 'smart-seo-fixer'); ?></label>
                        </th>
                        <td>
                            <textarea name="homepage_description" 
                                      id="homepage_description" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($homepage_description); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Meta description for the homepage.', 'smart-seo-fixer'); ?>
                                <span class="ssf-char-count">
                                    <span id="homepage-desc-count"><?php echo strlen($homepage_description); ?></span>/160
                                </span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Features -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e('Features', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Schema Markup', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_schema" 
                                       value="1" 
                                       <?php checked($enable_schema, true); ?>>
                                <?php esc_html_e('Enable JSON-LD schema markup', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Automatically add structured data to help search engines understand your content.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Attachment Pages', 'smart-seo-fixer'); ?></th>
                        <td>
                            <select name="redirect_attachments" id="redirect_attachments">
                                <option value="" <?php selected($redirect_attachments, ''); ?>>
                                    <?php esc_html_e('Disabled — keep attachment pages as-is', 'smart-seo-fixer'); ?>
                                </option>
                                <option value="parent" <?php selected($redirect_attachments, 'parent'); ?>>
                                    <?php esc_html_e('Redirect to parent post/page (recommended)', 'smart-seo-fixer'); ?>
                                </option>
                                <option value="file" <?php selected($redirect_attachments, 'file'); ?>>
                                    <?php esc_html_e('Redirect to the media file URL', 'smart-seo-fixer'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('WordPress creates an "attachment page" for every uploaded image/file. These pages show a single media item on a blog-like template and are bad for SEO. Redirecting them (301) to the parent post prevents thin content and wasted crawl budget.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('XML Sitemap', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_sitemap" 
                                       value="1" 
                                       <?php checked($enable_sitemap, true); ?>>
                                <?php esc_html_e('Enable XML sitemap', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Generate an XML sitemap for search engines.', 'smart-seo-fixer'); ?>
                                <?php if ($enable_sitemap): ?>
                                <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank">
                                    <?php esc_html_e('View Sitemap', 'smart-seo-fixer'); ?>
                                </a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Meta Generation', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="auto_meta" 
                                       value="1" 
                                       <?php checked($auto_meta, true); ?>>
                                <?php esc_html_e('Auto-generate SEO titles, descriptions & keywords on publish/update', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, AI will auto-generate missing SEO title, meta description, and focus keyword every time a post is published or updated. Existing values are never overwritten.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Background SEO Generation', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="background_seo_cron" 
                                       value="1" 
                                       <?php checked(Smart_SEO_Fixer::get_option('background_seo_cron', true), true); ?>>
                                <?php esc_html_e('Automatically fill missing SEO in the background (twice daily)', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('A background cron runs twice daily to catch any posts that still lack AI-generated titles or descriptions. Processes up to 10 posts per run to respect API rate limits.', 'smart-seo-fixer'); ?>
                            </p>
                            <?php
                            $cron_next = wp_next_scheduled('ssf_cron_generate_missing_seo');
                            $cron_last = get_option('ssf_cron_last_run', null);
                            if ($cron_next): ?>
                                <p class="description" style="margin-top: 6px; color: #059669;">
                                    <span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span>
                                    <?php printf(
                                        esc_html__('Next run: %s', 'smart-seo-fixer'),
                                        esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $cron_next))
                                    ); ?>
                                    <?php if ($cron_last): ?>
                                        &nbsp;|&nbsp;
                                        <?php printf(
                                            esc_html__('Last run: %s (%d generated)', 'smart-seo-fixer'),
                                            esc_html($cron_last['time'] ?? '—'),
                                            intval($cron_last['generated'] ?? 0)
                                        ); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Alt Text', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="auto_alt_text" 
                                       value="1" 
                                       <?php checked($auto_alt_text, true); ?>>
                                <?php esc_html_e('Automatically generate alt text for new image uploads', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php
                                // "Configured" is not the same as "can see images": a text-only
                                // model, or unreadable files, silently degrade to filename text.
                                // vision_status() answers the question that actually matters.
                                $ssf_vision = class_exists('SSF_AI')
                                    ? SSF_AI::vision_status()
                                    : ['ok' => false, 'reason' => 'not_configured', 'message' => '', 'provider' => '', 'model' => ''];
                                $ssf_ai_ready = !empty($ssf_vision['ok']);

                                if ($ssf_ai_ready) {
                                    printf(
                                        /* translators: %s: AI provider name */
                                        esc_html__('When enabled, the uploaded image is sent to %s, which looks at the picture and describes what it actually shows. Existing images with alt text are never changed.', 'smart-seo-fixer'),
                                        '<strong>' . esc_html($ssf_vision['provider']) . '</strong>'
                                    );
                                } else {
                                    esc_html_e('When enabled, alt text is generated on upload. AI vision is not available right now, so alt text is derived from the filename — a rough guess, not a description of the image.', 'smart-seo-fixer');
                                }
                                ?>
                            </p>

                            <?php if (!$ssf_ai_ready && !empty($ssf_vision['message'])) : ?>
                                <div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px 12px;">
                                    <p style="margin: 0;">
                                        <strong><?php esc_html_e('AI vision is not active.', 'smart-seo-fixer'); ?></strong>
                                        <?php echo esc_html($ssf_vision['message']); ?>
                                    </p>
                                    <p style="margin: 6px 0 0; color:#646970;">
                                        <?php esc_html_e('Until this is resolved, every description comes from the filename.', 'smart-seo-fixer'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <p style="margin: 10px 0 0;">
                                <button type="button" class="button" id="ssf-test-vision-btn">
                                    <span class="dashicons dashicons-visibility" style="margin-top: 4px;"></span>
                                    <?php esc_html_e('Test AI Vision', 'smart-seo-fixer'); ?>
                                </button>
                                <span id="ssf-test-vision-status" style="margin-left: 8px;"></span>
                            </p>
                            <p class="description" style="margin: 4px 0 0;">
                                <?php esc_html_e('Sends one image from your Media Library to the AI and shows what it describes. If the description matches the picture, vision is working; if the test fails, it says exactly why.', 'smart-seo-fixer'); ?>
                            </p>
                            <div id="ssf-test-vision-result" style="display:none; margin-top: 10px;"></div>
                            <div style="margin-top: 12px; padding: 14px; background: #f6f7f7; border: 1px solid #ddd; border-radius: 6px;">
                                <strong><?php esc_html_e('Bulk Generate Alt Text', 'smart-seo-fixer'); ?></strong>
                                <p class="description" style="margin: 6px 0 10px;">
                                    <?php esc_html_e('Fills in alt text for existing images that are missing it, and saves it to the Media Library so themes and page builders pick it up.', 'smart-seo-fixer'); ?>
                                </p>

                                <?php
                                $ssf_alt_stats = class_exists('SSF_Image_SEO')
                                    ? SSF_Image_SEO::alt_stats()
                                    : ['total' => 0, 'with_alt' => 0, 'missing' => 0, 'skipped' => 0, 'percent' => 0];
                                ?>
                                <div id="ssf-alt-stats" style="display:flex; flex-wrap:wrap; gap:10px; margin: 12px 0;">
                                    <div style="flex:1; min-width:110px; padding:10px 12px; background:#fff; border:1px solid #dcdcde; border-radius:5px;">
                                        <div style="font-size:20px; font-weight:600; line-height:1.2;" id="ssf-alt-stat-total"><?php echo esc_html(number_format_i18n($ssf_alt_stats['total'])); ?></div>
                                        <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.4px;"><?php esc_html_e('Total images', 'smart-seo-fixer'); ?></div>
                                    </div>
                                    <div style="flex:1; min-width:110px; padding:10px 12px; background:#fff; border:1px solid #dcdcde; border-radius:5px;">
                                        <div style="font-size:20px; font-weight:600; line-height:1.2; color:#00a32a;" id="ssf-alt-stat-with"><?php echo esc_html(number_format_i18n($ssf_alt_stats['with_alt'])); ?></div>
                                        <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.4px;"><?php esc_html_e('Have alt text', 'smart-seo-fixer'); ?></div>
                                    </div>
                                    <div style="flex:1; min-width:110px; padding:10px 12px; background:#fff; border:1px solid #dcdcde; border-radius:5px;">
                                        <div style="font-size:20px; font-weight:600; line-height:1.2; color:<?php echo $ssf_alt_stats['missing'] > 0 ? '#d63638' : '#646970'; ?>;" id="ssf-alt-stat-missing"><?php echo esc_html(number_format_i18n($ssf_alt_stats['missing'])); ?></div>
                                        <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.4px;"><?php esc_html_e('Missing alt text', 'smart-seo-fixer'); ?></div>
                                    </div>
                                    <div style="flex:1; min-width:110px; padding:10px 12px; background:#fff; border:1px solid #dcdcde; border-radius:5px;">
                                        <div style="font-size:20px; font-weight:600; line-height:1.2;" id="ssf-alt-stat-percent"><?php echo esc_html($ssf_alt_stats['percent']); ?>%</div>
                                        <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.4px;"><?php esc_html_e('Coverage', 'smart-seo-fixer'); ?></div>
                                    </div>
                                    <div style="flex:1; min-width:110px; padding:10px 12px; background:#fff; border:1px solid #dcdcde; border-radius:5px;<?php echo $ssf_alt_stats['skipped'] > 0 ? '' : ' display:none;'; ?>" id="ssf-alt-stat-skipped-box">
                                        <div style="font-size:20px; font-weight:600; line-height:1.2; color:#996800;" id="ssf-alt-stat-skipped"><?php echo esc_html(number_format_i18n($ssf_alt_stats['skipped'])); ?></div>
                                        <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.4px;"><?php esc_html_e('Skipped', 'smart-seo-fixer'); ?></div>
                                    </div>
                                </div>

                                <?php if ($ssf_ai_ready) : ?>
                                    <p style="margin: 6px 0 10px;">
                                        <label>
                                            <input type="checkbox" id="ssf-bulk-alt-use-ai" checked>
                                            <?php
                                            printf(
                                                /* translators: %s: AI provider name */
                                                esc_html__('Use AI vision via %s (recommended — describes the actual image; slower and uses API credits)', 'smart-seo-fixer'),
                                                esc_html($ssf_vision['provider'])
                                            );
                                            ?>
                                        </label>
                                    </p>
                                <?php else : ?>
                                    <p class="description" style="margin: 6px 0 10px; color: #996800;">
                                        <?php
                                        printf(
                                            /* translators: %s: reason AI vision is unavailable */
                                            esc_html__('AI vision is unavailable, so alt text will come from filenames only — a weak fallback. Reason: %s', 'smart-seo-fixer'),
                                            esc_html(!empty($ssf_vision['message'])
                                                ? $ssf_vision['message']
                                                : __('no AI provider configured.', 'smart-seo-fixer'))
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <button type="button" class="button button-secondary" id="ssf-bulk-alt-btn">
                                    <span class="dashicons dashicons-images-alt2" style="margin-top: 4px;"></span>
                                    <?php esc_html_e('Generate Missing Alt Text', 'smart-seo-fixer'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="ssf-regen-alt-btn">
                                    <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                                    <?php esc_html_e('Rewrite Existing Alt Text', 'smart-seo-fixer'); ?>
                                </button>
                                <button type="button" class="button" id="ssf-bulk-alt-stop" style="display:none;">
                                    <?php esc_html_e('Stop', 'smart-seo-fixer'); ?>
                                </button>
                                <button type="button" class="button-link" id="ssf-alt-stats-refresh" style="margin-left:8px;">
                                    <?php esc_html_e('Refresh counts', 'smart-seo-fixer'); ?>
                                </button>
                                <span id="ssf-bulk-alt-status" style="margin-left: 10px;"></span>

                                <p class="description" style="margin: 8px 0 0;">
                                    <?php esc_html_e('Rewrite Existing Alt Text replaces alt text this plugin generated before — use it to redo descriptions that came from filenames, or to fix text left over from the old naming bug.', 'smart-seo-fixer'); ?>
                                </p>
                                <p style="margin: 6px 0 0;">
                                    <label>
                                        <input type="checkbox" id="ssf-regen-alt-overwrite-all">
                                        <?php esc_html_e('Also replace alt text that may have been written by hand (not recommended)', 'smart-seo-fixer'); ?>
                                    </label>
                                </p>

                                <div id="ssf-bulk-alt-progress" style="display:none; margin-top: 10px;">
                                    <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                        <div id="ssf-bulk-alt-bar" style="height: 100%; width: 0%; background: #2271b1; transition: width .3s ease;"></div>
                                    </div>
                                    <div id="ssf-bulk-alt-samples" style="margin-top: 8px;"></div>
                                </div>

                                <p class="description" style="margin-top: 10px;">
                                    <?php esc_html_e('Images with no usable description (e.g. IMG_1234.jpg with no AI configured) are flagged and skipped so the run always finishes.', 'smart-seo-fixer'); ?>
                                    <button type="button" class="button-link" id="ssf-bulk-alt-reset">
                                        <?php esc_html_e('Retry skipped images', 'smart-seo-fixer'); ?>
                                    </button>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Internal Links', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="auto_internal_links"
                                       value="1"
                                       <?php checked($auto_internal_links, true); ?>>
                                <?php esc_html_e('Automatically add internal links when a post is first published', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Adds up to 3 outgoing links from the new post to related existing posts, and up to 3 incoming links from related posts back to the new post. Runs asynchronously ~30s after publish so it does not slow down saving.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Thin Content Auto-Noindex', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="auto_noindex_thin"
                                       value="1"
                                       <?php checked($auto_noindex_thin, true); ?>>
                                <?php esc_html_e('Automatically mark thin-content posts as noindex', 'smart-seo-fixer'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Posts below the word threshold (with no redeeming image alt/caption text) are automatically excluded from search engines so they won\'t hurt your site\'s SEO as thin content. If a post grows above the threshold later, the plugin automatically lifts the noindex.', 'smart-seo-fixer'); ?>
                            </p>
                            <p style="margin-top: 10px;">
                                <label>
                                    <?php esc_html_e('Thin content threshold:', 'smart-seo-fixer'); ?>
                                    <input type="number"
                                           name="thin_content_threshold"
                                           value="<?php echo esc_attr($thin_content_threshold); ?>"
                                           min="20" max="300" step="5"
                                           style="width: 80px;">
                                    <?php esc_html_e('words', 'smart-seo-fixer'); ?>
                                </label>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Posts with fewer real words than this (counting image alt/caption text too) will be auto-noindexed. Default: 50. Google typically treats anything under 50–100 words as thin content.', 'smart-seo-fixer'); ?>
                            </p>
                            <p style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox"
                                           name="enrich_image_posts"
                                           value="1"
                                           <?php checked($enrich_image_posts, true); ?>>
                                    <?php esc_html_e('Use image alt text & captions to generate SEO for image-only posts', 'smart-seo-fixer'); ?>
                                </label>
                            </p>
                            <p class="description">
                                <?php esc_html_e('When a post is mostly images (e.g. a client review gallery), the plugin feeds all image alt text, captions, and titles to the AI so it can still generate a relevant SEO title, description, and focus keyword.', 'smart-seo-fixer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Disable Other SEO Plugins Output', 'smart-seo-fixer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="disable_other_seo_output" 
                                       value="1" 
                                       <?php checked($disable_other_seo_output, true); ?>>
                                <?php esc_html_e('Prevent other SEO plugins from outputting duplicate meta tags', 'smart-seo-fixer'); ?>
                            </label>
                            <?php if (!empty($conflicting_plugins)): ?>
                            <div style="margin-top: 8px; padding: 10px 14px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;">
                                <strong style="color: #856404;">
                                    <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php esc_html_e('Conflicting plugin detected:', 'smart-seo-fixer'); ?>
                                </strong>
                                <span style="color: #856404;"><?php echo esc_html(implode(', ', $conflicting_plugins)); ?></span>
                                <p class="description" style="margin: 5px 0 0; color: #856404;">
                                    <?php esc_html_e('This will suppress ALL meta output from the plugin above. Run', 'smart-seo-fixer'); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-migration')); ?>"><?php esc_html_e('Migration', 'smart-seo-fixer'); ?></a>
                                    <?php esc_html_e('first to copy your existing SEO data into SSF, or those pages will show SSF\'s auto-generated fallback instead of your custom meta.', 'smart-seo-fixer'); ?>
                                </p>
                            </div>
                            <?php elseif ($disable_other_seo_output): ?>
                            <div style="margin-top: 8px; padding: 10px 14px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 6px;">
                                <strong style="color: #0c5460;">
                                    <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php esc_html_e('Other SEO plugin output is suppressed.', 'smart-seo-fixer'); ?>
                                </strong>
                                <p class="description" style="margin: 5px 0 0; color: #0c5460;">
                                    <?php esc_html_e('SSF will use your saved SSF meta fields for each page. If any pages still have data in Yoast/Rank Math that hasn\'t been imported, use the', 'smart-seo-fixer'); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-migration')); ?>"><?php esc_html_e('Migration page', 'smart-seo-fixer'); ?></a>
                                    <?php esc_html_e('to import it. SSF will automatically fall back to those values for any pages not yet migrated.', 'smart-seo-fixer'); ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <p class="description">
                                <?php esc_html_e('Suppresses meta output from Yoast SEO, Rank Math, All in One SEO, The SEO Framework, and SEOPress. Enable this if you see duplicate meta descriptions in your page source.', 'smart-seo-fixer'); ?>
                                <?php esc_html_e('Always run Migration first to copy your existing SEO data into SSF before enabling this.', 'smart-seo-fixer'); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-migration')); ?>"><?php esc_html_e('Go to Migration →', 'smart-seo-fixer'); ?></a>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary button-large" id="save-settings">
                <?php esc_html_e('Save Settings', 'smart-seo-fixer'); ?>
            </button>
            <span class="spinner" style="float: none; margin-top: 0;"></span>
            <span class="ssf-save-status" id="save-status"></span>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // ─── Provider panel switching ───
    $('#ai_provider').on('change', function() {
        var provider = $(this).val();
        $('.ssf-provider-panel').hide();
        $('#ssf-' + provider + '-settings').show();
        // Update status badge
        var $panel = $('#ssf-' + provider + '-settings');
        var hasKey = false;
        if (provider === 'bedrock') {
            var $bAccess = $('#bedrock_access_key'), $bSecret = $('#bedrock_secret_key');
            if ($bAccess.length && $bSecret.length) {
                hasKey = $bAccess.val().trim().length > 0 && $bSecret.val().trim().length > 0;
            } else {
                // Credentials come from wp-config.php constants — those fields
                // aren't rendered, so reflect the server-computed state instead.
                hasKey = <?php echo $bedrock_configured ? 'true' : 'false'; ?>;
            }
        } else {
            hasKey = $panel.find('input[type="password"]').first().val().trim().length > 0;
        }
        updateStatusBadge(hasKey);
    });

    function updateStatusBadge(configured) {
        var $dot = $('#ssf-ai-status-dot'), $text = $('#ssf-ai-status-text'), $badge = $('#ssf-ai-status');
        if (configured) {
            $badge.css({background:'#dcfce7', color:'#166534', border:'1px solid #bbf7d0'});
            $dot.css('background','#16a34a');
            $text.text('<?php echo esc_js(__('Configured', 'smart-seo-fixer')); ?>');
        } else {
            $badge.css({background:'#f3f4f6', color:'#6b7280', border:'1px solid #e5e7eb'});
            $dot.css('background','#9ca3af');
            $text.text('<?php echo esc_js(__('Not configured', 'smart-seo-fixer')); ?>');
        }
    }

    // ─── Toggle secret visibility (generic) ───
    $(document).on('click', '.ssf-toggle-secret', function() {
        var $input = $('#' + $(this).data('target'));
        var $btn = $(this);
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $btn.text('<?php echo esc_js(__('Hide', 'smart-seo-fixer')); ?>');
        } else {
            $input.attr('type', 'password');
            $btn.text('<?php echo esc_js(__('Show', 'smart-seo-fixer')); ?>');
        }
    });

    // ─── Enable/disable Test buttons when credentials change ───
    // Bedrock's test button is intentionally never disabled here — blank
    // fields still test a real path (the broker, or nothing), see class-ajax.php.
    $('#openai_api_key').on('input', function() {
        $('#ssf-test-openai').prop('disabled', !$(this).val().trim());
    });
    $('#claude_api_key').on('input', function() {
        $('#ssf-test-claude').prop('disabled', !$(this).val().trim());
    });
    $('#gemini_api_key').on('input', function() {
        $('#ssf-test-gemini').prop('disabled', !$(this).val().trim());
    });

    // ─── Test Connection (unified) ───
    $(document).on('click', '.ssf-test-btn', function() {
        var $btn = $(this).prop('disabled', true);
        var provider = $btn.data('provider');
        var $result = $('#ssf-test-result-' + provider);

        $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update ssf-spin');
        $result.hide();

        var postData = {
            action: 'ssf_test_ai_provider',
            nonce: ssfAdmin.nonce,
            provider: provider
        };

        // Send current field values so test uses unsaved data
        if (provider === 'bedrock') {
            postData.access_key = $('#bedrock_access_key').val();
            postData.secret_key = $('#bedrock_secret_key').val();
            postData.region     = $('#bedrock_region').val();
        } else if (provider === 'openai') {
            postData.api_key = $('#openai_api_key').val();
            postData.model   = $('#openai_model').val();
        } else if (provider === 'claude') {
            postData.api_key = $('#claude_api_key').val();
            postData.model   = $('#claude_model').val();
        } else if (provider === 'gemini') {
            postData.api_key = $('#gemini_api_key').val();
            postData.model   = $('#gemini_model').val();
        }

        $.post(ssfAdmin.ajax_url, postData, function(r) {
            $btn.prop('disabled', false);
            $btn.find('.dashicons').removeClass('dashicons-update ssf-spin').addClass('dashicons-controls-play');

            if (r.success) {
                $result.css({background:'#dcfce7', border:'1px solid #bbf7d0', color:'#166534'})
                       .html('<strong>&#10003; Connected!</strong> Model responded: <em>' + $('<div>').text(r.data.reply).html() + '</em>')
                       .show();
                updateStatusBadge(true);
            } else {
                var msg = r.data.message || 'Unknown error';
                $result.css({background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b'})
                       .html('<strong>&#10007; Failed:</strong> ' + $('<div>').text(msg).html())
                       .show();
                updateStatusBadge(false);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $btn.find('.dashicons').removeClass('dashicons-update ssf-spin').addClass('dashicons-controls-play');
            $result.css({background:'#fef2f2', border:'1px solid #fecaca', color:'#991b1b'})
                   .html('<strong>&#10007; Request failed.</strong> Check your server error log.')
                   .show();
        });
    });
    
    // Character counters
    $('#homepage_title').on('input', function() {
        $('#homepage-title-count').text($(this).val().length);
    });
    
    $('#homepage_description').on('input', function() {
        $('#homepage-desc-count').text($(this).val().length);
    });
    
    // GSC Connect via the shared broker — no Google Cloud Console needed
    $('#ssf-gsc-broker-connect').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $status = $('#ssf-gsc-broker-connect-status').text('<?php echo esc_js(__('Checking…', 'smart-seo-fixer')); ?>');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_gsc_broker_connect',
            nonce: ssfAdmin.nonce
        }, function(r) {
            var data = (r && r.data) || {};
            if (data.status === 'authorized') {
                $status.html(data.message || '<?php echo esc_js(__('Connected!', 'smart-seo-fixer')); ?>');
                location.reload();
                return;
            }
            // data.message may contain a <code> tag around an IP address — safe,
            // server-escaped, and only ever built from our own translated strings.
            $status.html(data.message || '<?php echo esc_js(__('Could not connect. Try again in a moment.', 'smart-seo-fixer')); ?>');
            $btn.prop('disabled', false);
            if (data.status && data.status !== 'needs_setup') {
                document.getElementById('ssf-gsc-manual-fallback').open = true;
            }
        }).fail(function() {
            $status.text('<?php echo esc_js(__('Network error. Please try again.', 'smart-seo-fixer')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // GA Connect via the shared broker — no Google Cloud Console needed
    $('#ssf-ga-broker-connect').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $status = $('#ssf-ga-broker-connect-status').text('<?php echo esc_js(__('Checking…', 'smart-seo-fixer')); ?>');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_broker_connect',
            nonce: ssfAdmin.nonce
        }, function(r) {
            var data = (r && r.data) || {};
            if (data.status === 'authorized') {
                $status.html(data.message || '<?php echo esc_js(__('Connected!', 'smart-seo-fixer')); ?>');
                location.reload();
                return;
            }
            $status.html(data.message || '<?php echo esc_js(__('Could not connect. Try again in a moment.', 'smart-seo-fixer')); ?>');
            $btn.prop('disabled', false);
            if (data.status && data.status !== 'needs_setup') {
                document.getElementById('ssf-ga-manual-fallback').open = true;
            }
        }).fail(function() {
            $status.text('<?php echo esc_js(__('Network error. Please try again.', 'smart-seo-fixer')); ?>');
            $btn.prop('disabled', false);
        });
    });

    // GSC Refresh Sites
    $('#ssf-gsc-refresh-sites').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $status = $('#ssf-gsc-refresh-status').text('Loading sites from Google...');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_gsc_refresh_sites',
            nonce: ssfAdmin.nonce
        }, function(r) {
            if (r.success && r.data.sites && r.data.sites.length) {
                var html = '<select name="gsc_site_url" id="gsc_site_url" style="min-width:300px;">';
                html += '<option value="">— Select a site —</option>';
                r.data.sites.forEach(function(s) {
                    html += '<option value="' + s.siteUrl + '">' + s.siteUrl + '</option>';
                });
                html += '</select>';
                var $target = $('#ssf-gsc-sites-dropdown');
                if ($target.length) {
                    $target.html(html).show();
                    $status.text(r.data.sites.length + ' site(s) found. Select one and save settings.');
                } else {
                    $('select#gsc_site_url').replaceWith(html);
                    $status.text('Refreshed! ' + r.data.sites.length + ' site(s) found.');
                }
            } else {
                $status.text(r.data?.message || 'No sites found. Check your GSC account.');
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            $status.text('Network error. Please try again.');
            $btn.prop('disabled', false);
        });
    });

    // GSC Disconnect
    $('#ssf-gsc-disconnect').on('click', function() {
        if (!confirm('<?php esc_html_e('Disconnect Google Search Console?', 'smart-seo-fixer'); ?>')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php esc_html_e('Disconnecting...', 'smart-seo-fixer'); ?>');
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_gsc_disconnect',
            nonce: ssfAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Error');
                $btn.prop('disabled', false).text('<?php esc_html_e('Disconnect', 'smart-seo-fixer'); ?>');
            }
        });
    });

    // GSC Auto-Setup — one-click create + verify + submit sitemap
    $('#ssf-gsc-auto-setup').on('click', function() {
        var $btn = $(this);
        var $log = $('#ssf-gsc-auto-setup-log');
        var originalHtml = $btn.html();

        if (!confirm('<?php echo esc_js(__("This will add this site to Google Search Console, verify ownership with a meta tag, and submit your sitemap. Continue?", "smart-seo-fixer")); ?>')) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: text-bottom; animation: ssf-spin 1s linear infinite;"></span> <?php echo esc_js(__("Setting up...", "smart-seo-fixer")); ?>');
        $log.show().html('<div style="padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#1e3a5f;">' +
            '<?php echo esc_js(__("Contacting Google... this can take 20-30 seconds.", "smart-seo-fixer")); ?>' +
            '</div>');

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_gsc_auto_setup',
            nonce: ssfAdmin.nonce
        }, function(response) {
            var data = (response && response.data) ? response.data : {};
            var steps = data.steps || [];
            var ok = !!(response && response.success);

            var stepLabels = {
                precheck_domain:  '<?php echo esc_js(__("Domain reachable", "smart-seo-fixer")); ?>',
                request_token:    '<?php echo esc_js(__("Request verification token", "smart-seo-fixer")); ?>',
                homepage_check:   '<?php echo esc_js(__("Meta tag live on homepage", "smart-seo-fixer")); ?>',
                verify:           '<?php echo esc_js(__("Google verifies ownership", "smart-seo-fixer")); ?>',
                add_to_gsc:       '<?php echo esc_js(__("Add property to Search Console", "smart-seo-fixer")); ?>',
                submit_sitemap:   '<?php echo esc_js(__("Submit sitemap", "smart-seo-fixer")); ?>'
            };

            var bgColor = ok ? '#f0fdf4' : '#fef2f2';
            var borderColor = ok ? '#bbf7d0' : '#fecaca';
            var titleColor = ok ? '#15803d' : '#b91c1c';

            var html = '<div style="padding:14px;background:' + bgColor + ';border:1px solid ' + borderColor + ';border-radius:6px;">';
            html += '<p style="margin:0 0 10px;font-weight:600;color:' + titleColor + ';font-size:14px;">';
            html += ok
                ? '<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_js(__("All set!", "smart-seo-fixer")); ?>'
                : '<span class="dashicons dashicons-warning"></span> <?php echo esc_js(__("Setup did not complete.", "smart-seo-fixer")); ?>';
            html += '</p>';

            if (data.message) {
                html += '<p style="margin:0 0 10px;color:#374151;">' + $('<div>').text(data.message).html() + '</p>';
            }

            if (steps.length) {
                html += '<ol style="margin:0;padding-left:22px;color:#374151;font-size:13px;">';
                steps.forEach(function(step) {
                    var label = stepLabels[step.name] || step.name;
                    var icon = step.success
                        ? '<span class="dashicons dashicons-yes" style="color:#16a34a;vertical-align:text-bottom;"></span>'
                        : '<span class="dashicons dashicons-no" style="color:#dc2626;vertical-align:text-bottom;"></span>';
                    html += '<li style="margin-bottom:4px;">' + icon + ' <strong>' + label + '</strong>';
                    if (step.detail) {
                        html += ' — <span style="color:#6b7280;">' + $('<div>').text(step.detail).html() + '</span>';
                    }
                    html += '</li>';
                });
                html += '</ol>';
            }

            if (ok) {
                html += '<p style="margin:12px 0 0;color:#166534;font-size:13px;"><?php echo esc_js(__("Reloading in 3 seconds to refresh the site list…", "smart-seo-fixer")); ?></p>';
            }

            html += '</div>';
            $log.html(html);

            $btn.prop('disabled', false).html(originalHtml);

            if (ok) {
                setTimeout(function() { location.reload(); }, 3000);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            $log.html('<div style="padding:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#b91c1c;">' +
                '<?php echo esc_js(__("Request failed:", "smart-seo-fixer")); ?> ' +
                (errorThrown || textStatus || 'Unknown error') +
                '</div>');
            $btn.prop('disabled', false).html(originalHtml);
        });
    });
    
    // Save settings
    $('#ssf-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#save-settings');
        var $spinner = $btn.siblings('.spinner');
        var $status = $('#save-status');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.text('');
        
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'ssf_save_settings'});
        formData.push({name: 'nonce', value: ssfAdmin.nonce});
        
        $.post(ssfAdmin.ajax_url, formData, function(response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            
            if (response.success) {
                $status.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span> ' + response.data.message);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : 'Save failed. Check your server error log.';
                $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span> ' + msg);
            }
            
            setTimeout(function() {
                $status.text('');
            }, 3000);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            var detail = errorThrown || textStatus || 'Unknown error';
            $status.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span> Request failed: ' + detail + '. Check your server error log or browser console.');
        });
    });

    // --- Google Analytics handlers ---
    function ssfGaRenderLog($container, result, isError) {
        var stepLabels = {
            precheck_domain: 'Checking domain is publicly reachable',
            list_accounts:   'Loading your GA4 accounts',
            pick_account:    'Selecting account',
            create_property: 'Creating GA4 property',
            create_stream:   'Creating web data stream',
            save_config:     'Saving configuration'
        };
        var color = isError ? '#dc3232' : '#46b450';
        var bg    = isError ? '#fef2f2' : '#ecfdf5';
        var html  = '<div style="padding:12px 14px; background:' + bg + '; border-left:4px solid ' + color + '; border-radius:4px;">';
        if (result && result.message) {
            html += '<p style="margin:0 0 8px; font-weight:600;">' + $('<div>').text(result.message).html() + '</p>';
        }
        if (result && result.steps && result.steps.length) {
            html += '<ol style="margin:8px 0 0 20px;">';
            result.steps.forEach(function(step) {
                var icon  = step.success ? '<span class="dashicons dashicons-yes" style="color:#10b981;"></span>' : '<span class="dashicons dashicons-no" style="color:#dc2626;"></span>';
                var label = stepLabels[step.name] || step.name;
                var det   = step.detail ? ' <small style="color:#555;">(' + $('<div>').text(step.detail).html() + ')</small>' : '';
                html += '<li>' + icon + ' ' + label + det + '</li>';
            });
            html += '</ol>';
        }
        html += '</div>';
        $container.html(html).show();
    }

    $('#ssf-ga-auto-setup').on('click', function() {
        if (!confirm('Create a new GA4 property for this site? A new property will be added under your first Analytics account and the tracking code will be installed automatically.')) {
            return;
        }
        var $btn = $(this);
        var $log = $('#ssf-ga-auto-setup-log');
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align:text-bottom; animation: ssf-spin 1s linear infinite;"></span> Setting up...');
        $log.hide();

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_auto_setup',
            nonce:  ssfAdmin.nonce
        }).done(function(resp) {
            if (resp && resp.success) {
                ssfGaRenderLog($log, resp.data, false);
                setTimeout(function() { window.location.reload(); }, 2500);
            } else {
                var data = resp && resp.data ? resp.data : {message: 'Setup failed.'};
                var detected = ssfGaDetectApiEnableError(data.message || '');
                if (detected) {
                    ssfGaRenderApiEnableBanner($log, data.message, detected);
                } else {
                    ssfGaRenderLog($log, data, true);
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom;"></span> Auto-Create GA4 Property for This Site');
            }
        }).fail(function() {
            ssfGaRenderLog($log, {message: 'Request failed. Please retry.'}, true);
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom;"></span> Auto-Create GA4 Property for This Site');
        });
    });

    $('#ssf-ga-test-report').on('click', function() {
        var $btn = $(this);
        var $log = $('#ssf-ga-test-log');
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align:text-bottom; animation: ssf-spin 1s linear infinite;"></span> Fetching...');
        $log.hide();
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_test_report',
            nonce:  ssfAdmin.nonce
        }).done(function(resp) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line" style="vertical-align:text-bottom;"></span> Test Data Fetch (Last 7 Days)');
            if (resp && resp.success) {
                var d = resp.data;
                $log.html(
                    '<div style="padding:12px 14px; background:#ecfdf5; border-left:4px solid #10b981; border-radius:4px;">' +
                    '<strong>Last 7 days:</strong> ' +
                    (d.sessions || 0) + ' sessions, ' +
                    (d.users || 0) + ' users, ' +
                    (d.pageviews || 0) + ' pageviews, ' +
                    (d.bounce_rate || 0) + '% bounce rate' +
                    '</div>'
                ).show();
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Test failed.';
                var detected = ssfGaDetectApiEnableError(msg);
                if (detected) {
                    ssfGaRenderApiEnableBanner($log, msg, detected);
                } else {
                    $log.html('<div style="padding:12px 14px; background:#fef2f2; border-left:4px solid #dc2626; border-radius:4px;">' + $('<div>').text(msg).html() + '</div>').show();
                }
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line" style="vertical-align:text-bottom;"></span> Test Data Fetch (Last 7 Days)');
            $log.html('<div style="padding:12px 14px; background:#fef2f2; border-left:4px solid #dc2626; border-radius:4px;">Request failed.</div>').show();
        });
    });

    $('#ssf-ga-disconnect').on('click', function() {
        if (!confirm('Disconnect Google Analytics? Your measurement ID and property link will be cleared.')) {
            return;
        }
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_disconnect',
            nonce:  ssfAdmin.nonce
        }).always(function() {
            window.location.reload();
        });
    });

    // Existing-property picker
    // Detect Google's "API not enabled" error message and extract its enable
    // URL so we can render a proper clickable link instead of raw text.
    function ssfGaDetectApiEnableError(msg) {
        if (!msg) return null;
        // Google error format:
        //   "Google ... API has not been used in project NNN before or it is disabled.
        //    Enable it by visiting https://console.developers.google.com/apis/api/X/overview?project=NNN then retry."
        var linkRe = /https:\/\/console\.(?:developers|cloud)\.google\.com\/apis\/api\/[^\s]+?project=\d+/i;
        var m = msg.match(linkRe);
        if (!m) return null;
        var apiName = 'Google Analytics API';
        var apiMatch = msg.match(/(Google [A-Za-z0-9 ]+API) has not been used/);
        if (apiMatch) apiName = apiMatch[1];
        return { url: m[0], apiName: apiName };
    }

    function ssfGaRenderApiEnableBanner($container, msg, detected) {
        var html = '<div style="padding:14px 16px; background:#fff7ed; border:1px solid #fdba74; border-left:4px solid #f97316; border-radius:6px; color:#7c2d12;">' +
            '<p style="margin:0 0 8px; font-weight:600;">' +
                '<span class="dashicons dashicons-warning" style="color:#f97316;"></span> ' +
                'Google API needs to be enabled' +
            '</p>' +
            '<p style="margin:0 0 10px;">' +
                'The <strong>' + $('<div>').text(detected.apiName).html() + '</strong> is not enabled in your Google Cloud project. ' +
                'Click below to enable it, wait ~1 minute, then retry.' +
            '</p>' +
            '<p style="margin:0 0 10px;">' +
                '<a href="' + detected.url + '" target="_blank" rel="noopener" class="button button-primary">' +
                    '<span class="dashicons dashicons-external" style="vertical-align:text-bottom;"></span> ' +
                    'Enable ' + $('<div>').text(detected.apiName).html() +
                '</a> ' +
                '<a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noopener" class="button">' +
                    'Also enable Analytics Data API' +
                '</a>' +
            '</p>' +
            '<details style="margin-top:6px;"><summary style="cursor:pointer; color:#7c2d12;">Raw error from Google</summary>' +
                '<pre style="white-space:pre-wrap; font-size:11px; background:#fff; padding:8px; border:1px solid #fed7aa; border-radius:4px; margin-top:6px; color:#7c2d12;">' +
                    $('<div>').text(msg).html() +
                '</pre>' +
            '</details>' +
            '</div>';
        $container.html(html).show();
    }

    $('#ssf-ga-use-existing').on('click', function() {
        var $picker = $('#ssf-ga-existing-picker');
        var $select = $('#ssf_ga_property_select');
        var $err    = $('#ssf-ga-picker-error');
        $picker.slideDown();
        $err.hide().empty();
        $select.html('<option value="">Loading properties…</option>').prop('disabled', true);

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_list_properties',
            nonce:  ssfAdmin.nonce
        }).done(function(resp) {
            if (resp && resp.success) {
                var props = resp.data.properties || [];
                var current = resp.data.current || '';
                if (!props.length) {
                    $select.html('<option value="">No properties found — this Google account has no GA4 access</option>').prop('disabled', true);
                    return;
                }
                var groups = {};
                props.forEach(function(p) {
                    var key = p.account_name || p.account || 'Unknown account';
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(p);
                });
                var html = '<option value="">— Select a property —</option>';
                Object.keys(groups).forEach(function(acct) {
                    html += '<optgroup label="' + $('<div>').text(acct).html() + '">';
                    groups[acct].forEach(function(p) {
                        var label = (p.property_name || p.property) + (p.measurement_id ? '  [' + p.measurement_id + ']' : '  (no web stream)');
                        var data  = 'data-measurement-id="' + $('<div>').text(p.measurement_id || '').html() + '"' +
                                    ' data-account="' + $('<div>').text(p.account || '').html() + '"' +
                                    ' data-stream="' + $('<div>').text(p.stream || '').html() + '"';
                        html += '<option value="' + $('<div>').text(p.property).html() + '" ' + data +
                                (p.property === current ? ' selected' : '') + '>' +
                                $('<div>').text(label).html() + '</option>';
                    });
                    html += '</optgroup>';
                });
                $select.html(html).prop('disabled', false);
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to load properties.';
                var detected = ssfGaDetectApiEnableError(msg);
                if (detected) {
                    $select.html('<option value="">API needs to be enabled — see instructions above</option>').prop('disabled', true);
                    ssfGaRenderApiEnableBanner($err, msg, detected);
                } else {
                    $select.html('<option value="">' + $('<div>').text(msg).html() + '</option>').prop('disabled', true);
                }
            }
        }).fail(function() {
            $select.html('<option value="">Request failed.</option>').prop('disabled', true);
        });
    });

    $('#ssf-ga-cancel-selection').on('click', function() {
        $('#ssf-ga-existing-picker').slideUp();
    });

    $('#ssf-ga-save-selection').on('click', function() {
        var $opt = $('#ssf_ga_property_select option:selected');
        var property = $opt.val();
        if (!property) {
            alert('Please select a property first.');
            return;
        }
        var account = $opt.attr('data-account') || '';
        var mid     = $opt.attr('data-measurement-id') || '';
        var stream  = $opt.attr('data-stream') || '';
        var installTag = $('#ssf_ga_install_tag_for_existing').is(':checked');

        var payload = {
            action:  'ssf_ga_select_property',
            nonce:   ssfAdmin.nonce,
            property: property,
            account:  account,
            stream:   stream,
            measurement_id: installTag ? mid : ''
        };
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');
        $.post(ssfAdmin.ajax_url, payload).done(function(resp) {
            if (resp && resp.success) {
                alert(resp.data.message || 'Saved.');
                window.location.reload();
            } else {
                $btn.prop('disabled', false).text('Save Selection');
                alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Save failed.');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Save Selection');
            alert('Request failed.');
        });
    });

    $('#ssf-ga-save-mid').on('click', function() {
        var mid = $.trim($('#ssf_ga_manual_mid').val());
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_ga_save_measurement_id',
            nonce:  ssfAdmin.nonce,
            measurement_id: mid
        }).done(function(resp) {
            if (resp && resp.success) {
                alert(resp.data.message || 'Saved.');
                window.location.reload();
            } else {
                alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Save failed.');
            }
        }).fail(function() { alert('Request failed.'); });
    });

    $('#ssf_ga_auto_tag_toggle').on('change', function() {
        // Piggyback on the save_measurement_id handler by keeping current MID
        // but toggling the auto-tag option via a tiny REST-free pattern: reuse
        // the settings save form — simpler is a dedicated option save via the
        // same measurement_id handler, but we want to preserve MID.
        // Use a dedicated setter via update_option through ssf_save_settings is
        // overkill; instead re-post the current MID (endpoint updates AUTO_TAG_OPTION too).
        var mid = $.trim($('#ssf_ga_manual_mid').val());
        if (!this.checked) {
            // Clear MID to disable injection without wiping property link.
            if (!confirm('Disable tracking code injection? The measurement ID will be cleared so gtag.js is no longer output. You can paste it back anytime.')) {
                $(this).prop('checked', true);
                return;
            }
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_ga_save_measurement_id',
                nonce:  ssfAdmin.nonce,
                measurement_id: ''
            }).always(function() { window.location.reload(); });
        } else if (mid) {
            $.post(ssfAdmin.ajax_url, {
                action: 'ssf_ga_save_measurement_id',
                nonce:  ssfAdmin.nonce,
                measurement_id: mid
            }).always(function() { window.location.reload(); });
        }
    });
});
</script>

<style>
@keyframes ssf-spin { 100% { transform: rotate(360deg); } }
</style>
