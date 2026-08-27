<?php
/**
 * Local SEO View
 */

if (!defined('ABSPATH')) {
    exit;
}

$local_seo = new SSF_Local_SEO();
$settings = $local_seo->get_settings();
$business_types = $local_seo->get_business_types();
$days = $local_seo->get_days();
$locations = $local_seo->get_locations();
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-location"></span>
        <?php esc_html_e('Local SEO', 'smart-seo-fixer'); ?>
        <span class="ssf-version">v<?php echo esc_html(SSF_VERSION); ?></span>
    </h1>
    
    <p class="ssf-intro"><?php esc_html_e('Configure your local business information to generate LocalBusiness schema markup. This helps Google show your business in local search results and Google Maps.', 'smart-seo-fixer'); ?></p>
    
    <form id="local-seo-form">
        <!-- Enable Local SEO -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Enable Local SEO', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <label class="ssf-toggle">
                    <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                    <span class="ssf-toggle-slider"></span>
                    <span class="ssf-toggle-label"><?php esc_html_e('Enable Local Business Schema', 'smart-seo-fixer'); ?></span>
                </label>
                <p class="description"><?php esc_html_e('When enabled, LocalBusiness schema will be added to your homepage.', 'smart-seo-fixer'); ?></p>
            </div>
        </div>
        
        <!-- Business Information -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-building"></span>
                    <?php esc_html_e('Business Information', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th><label for="business_name"><?php esc_html_e('Business Name', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="business_name" id="business_name" 
                                   value="<?php echo esc_attr($settings['business_name']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="business_type"><?php esc_html_e('Business Type', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <select name="business_type" id="business_type">
                                <?php foreach ($business_types as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['business_type'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php esc_html_e('Description', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea($settings['description']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="phone"><?php esc_html_e('Phone Number', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo esc_attr($settings['phone']); ?>" class="regular-text"
                                   placeholder="+1 (555) 123-4567">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email"><?php esc_html_e('Email', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="email" name="email" id="email" 
                                   value="<?php echo esc_attr($settings['email']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="price_range"><?php esc_html_e('Price Range', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <select name="price_range" id="price_range">
                                <option value=""><?php esc_html_e('Select...', 'smart-seo-fixer'); ?></option>
                                <option value="$" <?php selected($settings['price_range'], '$'); ?>>$ (Budget)</option>
                                <option value="$$" <?php selected($settings['price_range'], '$$'); ?>>$$ (Moderate)</option>
                                <option value="$$$" <?php selected($settings['price_range'], '$$$'); ?>>$$$ (Expensive)</option>
                                <option value="$$$$" <?php selected($settings['price_range'], '$$$$'); ?>>$$$$ (Luxury)</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Address -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php esc_html_e('Address', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th><label for="address_street"><?php esc_html_e('Street Address', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="address[street]" id="address_street" autocomplete="off"
                                   value="<?php echo esc_attr($settings['address']['street']); ?>" class="regular-text">
                            <?php if ($local_seo->get_maps_api_key() === '') : ?>
                            <p class="description"><?php esc_html_e('You can also type the full address manually — city, state, ZIP, and coordinates below.', 'smart-seo-fixer'); ?></p>
                            <?php else : ?>
                            <p class="description"><?php esc_html_e('Start typing and pick a suggestion to fill in the rest of the address automatically — or just type it all in by hand.', 'smart-seo-fixer'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="address_city"><?php esc_html_e('City', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="address[city]" id="address_city" 
                                   value="<?php echo esc_attr($settings['address']['city']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="address_state"><?php esc_html_e('State/Province', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="address[state]" id="address_state" 
                                   value="<?php echo esc_attr($settings['address']['state']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="address_zip"><?php esc_html_e('ZIP/Postal Code', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="address[zip]" id="address_zip" 
                                   value="<?php echo esc_attr($settings['address']['zip']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="address_country"><?php esc_html_e('Country', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <input type="text" name="address[country]" id="address_country" 
                                   value="<?php echo esc_attr($settings['address']['country']); ?>" class="regular-text"
                                   placeholder="US">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Coordinates', 'smart-seo-fixer'); ?></label></th>
                        <td>
                            <div class="ssf-coords-row">
                                <input type="text" name="geo[latitude]" 
                                       value="<?php echo esc_attr($settings['geo']['latitude']); ?>" 
                                       placeholder="<?php esc_attr_e('Latitude', 'smart-seo-fixer'); ?>" class="small-text">
                                <input type="text" name="geo[longitude]" 
                                       value="<?php echo esc_attr($settings['geo']['longitude']); ?>" 
                                       placeholder="<?php esc_attr_e('Longitude', 'smart-seo-fixer'); ?>" class="small-text">
                            </div>
                            <p class="description">
                                <?php esc_html_e('Optional. Find coordinates at', 'smart-seo-fixer'); ?>
                                <a href="https://www.google.com/maps" target="_blank">Google Maps</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Business Hours -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Business Hours', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="ssf-hours-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Day', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Open', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Opening Time', 'smart-seo-fixer'); ?></th>
                            <th><?php esc_html_e('Closing Time', 'smart-seo-fixer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $key => $label): 
                            $day_hours = $settings['hours'][$key] ?? [];
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td>
                                <input type="checkbox" name="hours[<?php echo esc_attr($key); ?>][open]" value="1" 
                                       <?php checked(!empty($day_hours['open'])); ?>>
                            </td>
                            <td>
                                <input type="time" name="hours[<?php echo esc_attr($key); ?>][open_time]" 
                                       value="<?php echo esc_attr($day_hours['open_time'] ?? '09:00'); ?>">
                            </td>
                            <td>
                                <input type="time" name="hours[<?php echo esc_attr($key); ?>][close_time]" 
                                       value="<?php echo esc_attr($day_hours['close_time'] ?? '17:00'); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Service Areas -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php esc_html_e('Service Areas', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <p class="description"><?php esc_html_e('List the cities or areas you serve (comma-separated).', 'smart-seo-fixer'); ?></p>
                <textarea name="service_areas" id="service_areas" rows="4" class="large-text" placeholder="Chicago, Naperville, Aurora, Joliet, Evanston"><?php echo esc_textarea(implode(', ', $settings['service_areas'])); ?></textarea>
            </div>
        </div>
        
        <!-- Practice Areas (for Law Firms) -->
        <div class="ssf-card ssf-card-legal" id="practice-areas-card" style="<?php echo (strpos($settings['business_type'], 'Legal') !== false || $settings['business_type'] === 'Attorney') ? '' : 'display:none;'; ?>">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e('Practice Areas (Law Firms)', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <p class="description"><?php esc_html_e('List your practice areas. This helps with legal SEO and rich snippets.', 'smart-seo-fixer'); ?></p>
                <textarea name="practice_areas" id="practice_areas" rows="4" class="large-text" placeholder="Personal Injury, Family Law, Criminal Defense, Estate Planning, Business Law"><?php echo esc_textarea(implode(', ', $settings['practice_areas'] ?? [])); ?></textarea>
                
                <h4 style="margin-top: 20px;"><?php esc_html_e('Common Practice Areas (click to add):', 'smart-seo-fixer'); ?></h4>
                <div class="ssf-practice-tags">
                    <?php 
                    $common_practices = [
                        'Personal Injury', 'Car Accidents', 'Slip and Fall', 'Medical Malpractice',
                        'Family Law', 'Divorce', 'Child Custody', 'Child Support',
                        'Criminal Defense', 'DUI/DWI', 'Drug Crimes', 'Domestic Violence',
                        'Estate Planning', 'Wills & Trusts', 'Probate',
                        'Business Law', 'Contracts', 'Business Formation',
                        'Real Estate', 'Immigration', 'Employment Law', 'Workers Compensation',
                        'Bankruptcy', 'Social Security Disability', 'Civil Litigation'
                    ];
                    foreach ($common_practices as $practice): ?>
                    <span class="ssf-practice-tag" data-practice="<?php echo esc_attr($practice); ?>"><?php echo esc_html($practice); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Social Profiles -->
        <div class="ssf-card">
            <div class="ssf-card-header">
                <h2>
                    <span class="dashicons dashicons-share"></span>
                    <?php esc_html_e('Social Profiles', 'smart-seo-fixer'); ?>
                </h2>
            </div>
            <div class="ssf-card-body">
                <table class="form-table">
                    <tr>
                        <th><label for="social_facebook"><?php esc_html_e('Facebook', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[facebook]" id="social_facebook" value="<?php echo esc_url($settings['social']['facebook']); ?>" class="regular-text" placeholder="https://facebook.com/yourpage"></td>
                    </tr>
                    <tr>
                        <th><label for="social_twitter"><?php esc_html_e('Twitter/X', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[twitter]" id="social_twitter" value="<?php echo esc_url($settings['social']['twitter']); ?>" class="regular-text" placeholder="https://twitter.com/yourhandle"></td>
                    </tr>
                    <tr>
                        <th><label for="social_instagram"><?php esc_html_e('Instagram', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[instagram]" id="social_instagram" value="<?php echo esc_url($settings['social']['instagram']); ?>" class="regular-text" placeholder="https://instagram.com/yourhandle"></td>
                    </tr>
                    <tr>
                        <th><label for="social_linkedin"><?php esc_html_e('LinkedIn', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[linkedin]" id="social_linkedin" value="<?php echo esc_url($settings['social']['linkedin']); ?>" class="regular-text" placeholder="https://linkedin.com/company/yourcompany"></td>
                    </tr>
                    <tr>
                        <th><label for="social_youtube"><?php esc_html_e('YouTube', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[youtube]" id="social_youtube" value="<?php echo esc_url($settings['social']['youtube']); ?>" class="regular-text" placeholder="https://youtube.com/@yourchannel"></td>
                    </tr>
                    <tr>
                        <th><label for="social_yelp"><?php esc_html_e('Yelp', 'smart-seo-fixer'); ?></label></th>
                        <td><input type="url" name="social[yelp]" id="social_yelp" value="<?php echo esc_url($settings['social']['yelp']); ?>" class="regular-text" placeholder="https://yelp.com/biz/yourbusiness"></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php esc_html_e('Save Local SEO Settings', 'smart-seo-fixer'); ?>
            </button>
            <span class="spinner" style="float: none;"></span>
            <span class="ssf-save-status"></span>
        </p>
    </form>

    <!-- Generated Schema Preview -->
    <div class="ssf-card" id="ssf-schema-preview-card" style="display:none;">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-editor-code"></span>
                <?php esc_html_e('Generated Schema', 'smart-seo-fixer'); ?>
            </h2>
        </div>
        <div class="ssf-card-body">
            <div id="ssf-schema-disabled-notice" class="notice notice-warning inline" style="display:none;margin:0 0 12px;padding:10px 12px;">
                <p style="margin:0;"><?php esc_html_e('"Enable Local Business Schema" is currently OFF. The markup below shows what would be added to your site once you turn it on.', 'smart-seo-fixer'); ?></p>
            </div>
            <p class="description" style="margin-top:0;">
                <?php esc_html_e('This is exactly what was just saved. It appears in your homepage\'s <head> as JSON-LD — view your site\'s page source and search for "Local Business Schema" to confirm it\'s live.', 'smart-seo-fixer'); ?>
            </p>
            <pre id="ssf-schema-json" style="background:#1e293b;color:#e2e8f0;padding:16px;border-radius:6px;overflow-x:auto;font-size:12px;line-height:1.6;max-height:400px;"></pre>
        </div>
    </div>

    <!-- Multiple Locations -->
    <div class="ssf-card">
        <div class="ssf-card-header">
            <h2>
                <span class="dashicons dashicons-location"></span>
                <?php esc_html_e('Additional Locations', 'smart-seo-fixer'); ?>
                <span id="loc-count" class="ssf-badge" style="<?php echo empty($locations) ? 'display:none;' : ''; ?>"><?php echo count($locations); ?></span>
            </h2>
            <button type="button" class="button button-primary" id="add-location-btn">
                <span class="dashicons dashicons-plus-alt2" style="line-height:1.4;"></span>
                <?php esc_html_e('Add Location', 'smart-seo-fixer'); ?>
            </button>
        </div>
        <div class="ssf-card-body">
            <p class="description"><?php esc_html_e('If you have multiple business locations, add them here. Each location gets its own LocalBusiness schema markup on the frontend.', 'smart-seo-fixer'); ?></p>
            
            <div id="locations-list">
                <?php if (empty($locations)): ?>
                <p class="ssf-empty" id="no-locations-msg"><?php esc_html_e('No additional locations added yet.', 'smart-seo-fixer'); ?></p>
                <?php else: ?>
                <?php foreach ($locations as $location): ?>
                <div class="ssf-location-item" data-id="<?php echo esc_attr($location['id']); ?>" data-location="<?php echo esc_attr(wp_json_encode($location)); ?>">
                    <div class="ssf-location-info">
                        <strong><?php echo esc_html($location['name']); ?></strong>
                        <span><?php echo esc_html(($location['address']['street'] ? $location['address']['street'] . ', ' : '') . $location['address']['city'] . ', ' . $location['address']['state'] . ' ' . $location['address']['zip']); ?></span>
                        <?php if (!empty($location['phone'])): ?>
                        <span style="color:#2563eb;"><?php echo esc_html($location['phone']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ssf-location-actions">
                        <button type="button" class="button button-small edit-location-btn" title="<?php esc_attr_e('Edit', 'smart-seo-fixer'); ?>">
                            <span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;line-height:1.6;"></span> <?php esc_html_e('Edit', 'smart-seo-fixer'); ?>
                        </button>
                        <button type="button" class="button button-small delete-location-btn" style="color:#dc2626;border-color:#dc2626;" title="<?php esc_attr_e('Delete', 'smart-seo-fixer'); ?>">
                            <span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:1.6;"></span> <?php esc_html_e('Delete', 'smart-seo-fixer'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Location Add/Edit Modal -->
<div id="ssf-location-modal" style="display:none;">
    <div class="ssf-modal-overlay"></div>
    <div class="ssf-modal-content">
        <div class="ssf-modal-header">
            <h2 id="ssf-modal-title"><?php esc_html_e('Add Location', 'smart-seo-fixer'); ?></h2>
            <button type="button" class="ssf-modal-close" id="close-location-modal">&times;</button>
        </div>
        <div class="ssf-modal-body">
            <form id="location-form">
                <input type="hidden" name="id" id="loc-id" value="">
                
                <!-- Location Name -->
                <div class="ssf-loc-field">
                    <label for="loc-name"><?php esc_html_e('Location Name', 'smart-seo-fixer'); ?> <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="loc-name" name="name" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('e.g., Downtown Office', 'smart-seo-fixer'); ?>" required>
                </div>
                
                <!-- Contact -->
                <div style="display:flex;gap:12px;">
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-phone"><?php esc_html_e('Phone', 'smart-seo-fixer'); ?></label>
                        <input type="tel" id="loc-phone" name="phone" class="regular-text" style="width:100%;" placeholder="+1 (555) 123-4567">
                    </div>
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-email"><?php esc_html_e('Email', 'smart-seo-fixer'); ?></label>
                        <input type="email" id="loc-email" name="email" class="regular-text" style="width:100%;" placeholder="office@example.com">
                    </div>
                </div>
                
                <!-- Address -->
                <h4 style="margin:15px 0 8px;padding-top:10px;border-top:1px solid #e5e7eb;">
                    <span class="dashicons dashicons-location-alt" style="color:#2563eb;"></span>
                    <?php esc_html_e('Address', 'smart-seo-fixer'); ?>
                </h4>
                
                <div class="ssf-loc-field">
                    <label for="loc-street"><?php esc_html_e('Street Address', 'smart-seo-fixer'); ?></label>
                    <input type="text" id="loc-street" name="address[street]" autocomplete="off" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('123 Main St, Suite 100', 'smart-seo-fixer'); ?>">
                </div>
                
                <div style="display:flex;gap:12px;">
                    <div class="ssf-loc-field" style="flex:2;">
                        <label for="loc-city"><?php esc_html_e('City', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-city" name="address[city]" class="regular-text" style="width:100%;">
                    </div>
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-state"><?php esc_html_e('State', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-state" name="address[state]" class="regular-text" style="width:100%;" placeholder="FL">
                    </div>
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-zip"><?php esc_html_e('ZIP', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-zip" name="address[zip]" class="regular-text" style="width:100%;">
                    </div>
                </div>
                
                <div style="display:flex;gap:12px;">
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-country"><?php esc_html_e('Country', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-country" name="address[country]" class="regular-text" style="width:100%;" placeholder="US" value="US">
                    </div>
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-lat"><?php esc_html_e('Latitude', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-lat" name="geo[latitude]" class="regular-text" style="width:100%;" placeholder="26.3584">
                    </div>
                    <div class="ssf-loc-field" style="flex:1;">
                        <label for="loc-lng"><?php esc_html_e('Longitude', 'smart-seo-fixer'); ?></label>
                        <input type="text" id="loc-lng" name="geo[longitude]" class="regular-text" style="width:100%;" placeholder="-80.0834">
                    </div>
                </div>
                
                <!-- Business Hours -->
                <h4 style="margin:15px 0 8px;padding-top:10px;border-top:1px solid #e5e7eb;">
                    <span class="dashicons dashicons-clock" style="color:#2563eb;"></span>
                    <?php esc_html_e('Business Hours', 'smart-seo-fixer'); ?>
                </h4>
                
                <table class="ssf-loc-hours-table">
                    <?php foreach ($days as $key => $label): ?>
                    <tr>
                        <td style="width:100px;font-weight:600;"><?php echo esc_html($label); ?></td>
                        <td style="width:40px;"><input type="checkbox" name="hours[<?php echo esc_attr($key); ?>][open]" value="1" class="loc-day-check" data-day="<?php echo esc_attr($key); ?>"></td>
                        <td><input type="time" name="hours[<?php echo esc_attr($key); ?>][open_time]" value="09:00" class="loc-time-input" data-day="<?php echo esc_attr($key); ?>"></td>
                        <td style="padding:0 5px;color:#9ca3af;">–</td>
                        <td><input type="time" name="hours[<?php echo esc_attr($key); ?>][close_time]" value="17:00" class="loc-time-input" data-day="<?php echo esc_attr($key); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <p class="description" style="margin-top:6px;"><?php esc_html_e('Check the days this location is open.', 'smart-seo-fixer'); ?></p>
            </form>
        </div>
        <div class="ssf-modal-footer">
            <button type="button" class="button" id="cancel-location-modal"><?php esc_html_e('Cancel', 'smart-seo-fixer'); ?></button>
            <button type="button" class="button button-primary" id="save-location-btn">
                <span class="dashicons dashicons-saved" style="line-height:1.4;"></span>
                <?php esc_html_e('Save Location', 'smart-seo-fixer'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.ssf-intro {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
}

.ssf-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.ssf-toggle-slider {
    width: 50px;
    height: 26px;
    background: #ccc;
    border-radius: 13px;
    position: relative;
    transition: background 0.3s;
}

.ssf-toggle-slider::after {
    content: '';
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: left 0.3s;
}

.ssf-toggle input:checked + .ssf-toggle-slider {
    background: #2563eb;
}

.ssf-toggle input:checked + .ssf-toggle-slider::after {
    left: 26px;
}

.ssf-toggle input {
    display: none;
}

.ssf-coords-row {
    display: flex;
    gap: 10px;
}

.ssf-hours-table {
    width: 100%;
    border-collapse: collapse;
}

.ssf-hours-table th,
.ssf-hours-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.ssf-hours-table input[type="time"] {
    width: 130px;
}

.ssf-location-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    margin-bottom: 10px;
}

.ssf-location-info strong {
    display: block;
}

.ssf-location-info span {
    font-size: 13px;
    color: #666;
}

.ssf-location-actions {
    display: flex;
    gap: 5px;
}

.ssf-empty {
    text-align: center;
    color: #666;
    padding: 20px;
}

/* Practice Areas Tags */
.ssf-practice-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.ssf-practice-tag {
    background: #e8f4fc;
    color: #1e40af;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #bfdbfe;
}

.ssf-practice-tag:hover {
    background: #1e40af;
    color: white;
}

.ssf-practice-tag.added {
    background: #22c55e;
    color: white;
    border-color: #22c55e;
}

.ssf-card-legal {
    border-left: 4px solid #1e40af;
}

.ssf-card-legal .ssf-card-header h2 {
    color: #1e40af;
}

/* Google Places autocomplete dropdown — must render above the location
   modal (z-index 100100 below), or suggestions appear hidden behind it. */
.pac-container { z-index: 100101 !important; }

/* Location Modal */
#ssf-location-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 100100; display: flex; align-items: center; justify-content: center; }
.ssf-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.55); }
.ssf-modal-content { position: relative; background: #fff; border-radius: 10px; width: 680px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.ssf-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
.ssf-modal-header h2 { margin: 0; font-size: 18px; }
.ssf-modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: #6b7280; line-height: 1; padding: 0 4px; }
.ssf-modal-close:hover { color: #dc2626; }
.ssf-modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
.ssf-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 10px 10px; }
.ssf-loc-field { margin-bottom: 12px; }
.ssf-loc-field label { display: block; font-weight: 500; margin-bottom: 4px; font-size: 13px; color: #374151; }
.ssf-loc-hours-table { width: 100%; border-collapse: collapse; }
.ssf-loc-hours-table td { padding: 5px 4px; }
.ssf-loc-hours-table input[type="time"] { width: 120px; }

/* Location list items */
.ssf-location-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; transition: box-shadow 0.2s; }
.ssf-location-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.ssf-location-info { display: flex; flex-direction: column; gap: 2px; }
.ssf-location-info strong { font-size: 14px; }
.ssf-location-info span { font-size: 12px; color: #6b7280; }
.ssf-location-actions { display: flex; gap: 6px; }
.ssf-badge { background: #2563eb; color: #fff; font-size: 12px; padding: 1px 8px; border-radius: 10px; font-weight: 600; margin-left: 6px; vertical-align: middle; }
</style>

<script>
jQuery(document).ready(function($) {

    // =============================================
    // Google Places Autocomplete (progressive enhancement)
    // Only runs if SSF_GOOGLE_MAPS_API_KEY is set in wp-config.php — the
    // enqueue in class-local-seo.php only loads the Google script and sets
    // this callback name when a key exists. Manual typing always works
    // regardless; this just fills in city/state/zip/country/coordinates
    // for you when you pick a suggestion.
    // =============================================
    function fillFromPlace(place, sel) {
        if (!place || !place.address_components) { return; }

        var comp = {};
        place.address_components.forEach(function(c) {
            c.types.forEach(function(t) { comp[t] = c; });
        });

        var streetNumber = comp.street_number ? comp.street_number.long_name : '';
        var route = comp.route ? comp.route.long_name : '';
        var street = (streetNumber + ' ' + route).trim();
        if (street) { $(sel.street).val(street); }

        var city = comp.locality || comp.sublocality_level_1 || comp.postal_town || comp.administrative_area_level_2;
        if (city) { $(sel.city).val(city.long_name); }

        if (comp.administrative_area_level_1) { $(sel.state).val(comp.administrative_area_level_1.short_name); }
        if (comp.postal_code) { $(sel.zip).val(comp.postal_code.long_name); }
        if (comp.country) { $(sel.country).val(comp.country.short_name); }

        if (sel.lat && sel.lng && place.geometry && place.geometry.location) {
            $(sel.lat).val(place.geometry.location.lat());
            $(sel.lng).val(place.geometry.location.lng());
        }
    }

    window.ssfInitPlacesAutocomplete = function() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) { return; }

        var opts = { types: ['address'], fields: ['address_components', 'geometry'] };

        if (document.getElementById('address_street')) {
            var mainAutocomplete = new google.maps.places.Autocomplete(document.getElementById('address_street'), opts);
            mainAutocomplete.addListener('place_changed', function() {
                fillFromPlace(mainAutocomplete.getPlace(), {
                    street: '#address_street', city: '#address_city', state: '#address_state',
                    zip: '#address_zip', country: '#address_country',
                    lat: 'input[name="geo[latitude]"]', lng: 'input[name="geo[longitude]"]'
                });
            });
        }

        if (document.getElementById('loc-street')) {
            var locAutocomplete = new google.maps.places.Autocomplete(document.getElementById('loc-street'), opts);
            locAutocomplete.addListener('place_changed', function() {
                fillFromPlace(locAutocomplete.getPlace(), {
                    street: '#loc-street', city: '#loc-city', state: '#loc-state',
                    zip: '#loc-zip', country: '#loc-country',
                    lat: '#loc-lat', lng: '#loc-lng'
                });
            });
        }
    };

    // Save form
    $('#local-seo-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $(this).find('button[type="submit"]');
        var $spinner = $(this).find('.spinner');
        var $status = $(this).find('.ssf-save-status');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'ssf_save_local_seo'});
        formData.push({name: 'nonce', value: ssfAdmin.nonce});
        
        $.post(ssfAdmin.ajax_url, formData, function(response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            
            if (response.success) {
                $status.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');

                // Show exactly what was generated, so saving has visible proof
                // instead of just a confirmation message.
                if (response.data.schema) {
                    $('#ssf-schema-json').text(JSON.stringify(response.data.schema, null, 2));
                    $('#ssf-schema-disabled-notice').toggle(!response.data.settings.enabled);
                    $('#ssf-schema-preview-card').show();
                    $('html, body').animate({ scrollTop: $('#ssf-schema-preview-card').offset().top - 60 }, 400);
                }
            } else {
                $status.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
            }

            setTimeout(function() {
                $status.html('');
            }, 3000);
        }).fail(function(xhr) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            var detail = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
            $status.html('<span style="color: #dc3232;">✗ <?php echo esc_js(__('Request failed', 'smart-seo-fixer')); ?>' + detail + '.</span>');
        });
    });

    // =============================================
    // Location Modal — Add / Edit / Delete
    // =============================================
    var $modal = $('#ssf-location-modal');
    
    function openModal(title) {
        $('#ssf-modal-title').text(title || '<?php echo esc_js(__('Add Location', 'smart-seo-fixer')); ?>');
        $modal.fadeIn(200);
        $('body').css('overflow','hidden');
    }
    
    function closeModal() {
        $modal.fadeOut(150);
        $('body').css('overflow','');
        resetForm();
    }
    
    function resetForm() {
        $('#location-form')[0].reset();
        $('#loc-id').val('');
        // Reset times to defaults
        $('.loc-time-input').each(function() {
            var name = $(this).attr('name');
            if (name.indexOf('open_time') !== -1) $(this).val('09:00');
            else $(this).val('17:00');
        });
        $('.loc-day-check').prop('checked', false);
        $('#loc-country').val('US');
    }
    
    function populateForm(loc) {
        $('#loc-id').val(loc.id || '');
        $('#loc-name').val(loc.name || '');
        $('#loc-phone').val(loc.phone || '');
        $('#loc-email').val(loc.email || '');
        $('#loc-street').val(loc.address ? loc.address.street : '');
        $('#loc-city').val(loc.address ? loc.address.city : '');
        $('#loc-state').val(loc.address ? loc.address.state : '');
        $('#loc-zip').val(loc.address ? loc.address.zip : '');
        $('#loc-country').val(loc.address ? (loc.address.country || 'US') : 'US');
        $('#loc-lat').val(loc.geo ? loc.geo.latitude : '');
        $('#loc-lng').val(loc.geo ? loc.geo.longitude : '');
        
        // Hours
        $('.loc-day-check').prop('checked', false);
        if (loc.hours) {
            $.each(loc.hours, function(day, h) {
                if (h.open) {
                    $('input[name="hours['+day+'][open]"]').prop('checked', true);
                }
                if (h.open_time) {
                    $('input[name="hours['+day+'][open_time]"]').val(h.open_time);
                }
                if (h.close_time) {
                    $('input[name="hours['+day+'][close_time]"]').val(h.close_time);
                }
            });
        }
    }
    
    function buildLocationRow(loc) {
        var addrParts = [];
        if (loc.address.street) addrParts.push(loc.address.street);
        if (loc.address.city) addrParts.push(loc.address.city);
        if (loc.address.state) addrParts.push(loc.address.state);
        if (loc.address.zip) addrParts.push(loc.address.zip);
        
        var html = '<div class="ssf-location-item" data-id="'+esc(loc.id)+'" data-location="'+esc(JSON.stringify(loc))+'">';
        html += '<div class="ssf-location-info">';
        html += '<strong>'+esc(loc.name)+'</strong>';
        html += '<span>'+esc(addrParts.join(', '))+'</span>';
        if (loc.phone) html += '<span style="color:#2563eb;">'+esc(loc.phone)+'</span>';
        html += '</div>';
        html += '<div class="ssf-location-actions">';
        html += '<button type="button" class="button button-small edit-location-btn"><span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;line-height:1.6;"></span> <?php echo esc_js(__('Edit', 'smart-seo-fixer')); ?></button>';
        html += '<button type="button" class="button button-small delete-location-btn" style="color:#dc2626;border-color:#dc2626;"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:1.6;"></span> <?php echo esc_js(__('Delete', 'smart-seo-fixer')); ?></button>';
        html += '</div></div>';
        return html;
    }
    
    function updateCount() {
        var n = $('.ssf-location-item').length;
        if (n > 0) {
            $('#loc-count').text(n).show();
            $('#no-locations-msg').hide();
        } else {
            $('#loc-count').hide();
            if (!$('#no-locations-msg').length) {
                $('#locations-list').html('<p class="ssf-empty" id="no-locations-msg"><?php echo esc_js(__('No additional locations added yet.', 'smart-seo-fixer')); ?></p>');
            }
            $('#no-locations-msg').show();
        }
    }
    
    function esc(t) { var d=document.createElement('div'); d.textContent=t||''; return d.innerHTML.replace(/"/g,'&quot;'); }
    
    // Open Add modal
    $('#add-location-btn').on('click', function() {
        resetForm();
        openModal('<?php echo esc_js(__('Add Location', 'smart-seo-fixer')); ?>');
    });
    
    // Open Edit modal
    $(document).on('click', '.edit-location-btn', function() {
        var $item = $(this).closest('.ssf-location-item');
        var loc = $item.data('location');
        if (typeof loc === 'string') loc = JSON.parse(loc);
        populateForm(loc);
        openModal('<?php echo esc_js(__('Edit Location', 'smart-seo-fixer')); ?>');
    });
    
    // Close modal
    $('#close-location-modal, #cancel-location-modal, .ssf-modal-overlay').on('click', closeModal);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
    
    // Save location
    $('#save-location-btn').on('click', function() {
        var name = $('#loc-name').val().trim();
        if (!name) {
            $('#loc-name').focus();
            alert('<?php echo esc_js(__('Location name is required.', 'smart-seo-fixer')); ?>');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'smart-seo-fixer')); ?>');
        
        var formData = $('#location-form').serializeArray();
        formData.push({name:'action', value:'ssf_save_location'});
        formData.push({name:'nonce', value: ssfAdmin.nonce});
        
        $.post(ssfAdmin.ajax_url, formData, function(response) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="line-height:1.4;"></span> <?php echo esc_js(__('Save Location', 'smart-seo-fixer')); ?>');
            
            if (response.success && response.data.location) {
                var loc = response.data.location;
                var existingId = $('#loc-id').val();
                
                if (existingId) {
                    // Update existing row
                    var $existing = $('.ssf-location-item[data-id="'+existingId+'"]');
                    $existing.replaceWith(buildLocationRow(loc));
                } else {
                    // Add new row
                    $('#no-locations-msg').remove();
                    $('#locations-list').append(buildLocationRow(loc));
                }
                
                updateCount();
                closeModal();
            } else {
                alert(response.data ? response.data.message : '<?php echo esc_js(__('Error saving location.', 'smart-seo-fixer')); ?>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="line-height:1.4;"></span> <?php echo esc_js(__('Save Location', 'smart-seo-fixer')); ?>');
            alert('<?php echo esc_js(__('Network error. Please try again.', 'smart-seo-fixer')); ?>');
        });
    });
    
    // Delete location
    $(document).on('click', '.delete-location-btn', function() {
        if (!confirm('<?php echo esc_js(__('Delete this location? This cannot be undone.', 'smart-seo-fixer')); ?>')) {
            return;
        }
        
        var $item = $(this).closest('.ssf-location-item');
        var locationId = $item.data('id');
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_delete_location',
            nonce: ssfAdmin.nonce,
            location_id: locationId
        }, function(response) {
            if (response.success) {
                $item.slideUp(200, function() {
                    $(this).remove();
                    updateCount();
                });
            } else {
                $btn.prop('disabled', false);
                alert(response.data ? response.data.message : 'Error');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });
    
    // Show/hide practice areas based on business type
    $('#business_type').on('change', function() {
        var type = $(this).val();
        var isLegal = (type === 'LegalService' || type === 'Attorney' || type === 'Notary');
        
        if (isLegal) {
            $('#practice-areas-card').slideDown();
        } else {
            $('#practice-areas-card').slideUp();
        }
    });
    
    // Practice area tags - click to add
    $('.ssf-practice-tag').on('click', function() {
        var practice = $(this).data('practice');
        var $textarea = $('#practice_areas');
        var current = $textarea.val().trim();
        
        // Check if already added
        var practices = current.split(',').map(function(p) { return p.trim().toLowerCase(); });
        
        if (practices.indexOf(practice.toLowerCase()) !== -1) {
            // Already exists - remove it
            var newPractices = current.split(',').map(function(p) { return p.trim(); }).filter(function(p) {
                return p.toLowerCase() !== practice.toLowerCase();
            });
            $textarea.val(newPractices.join(', '));
            $(this).removeClass('added');
        } else {
            // Add it
            if (current) {
                $textarea.val(current + ', ' + practice);
            } else {
                $textarea.val(practice);
            }
            $(this).addClass('added');
        }
    });
    
    // Mark already-added practice areas on page load
    var currentPractices = $('#practice_areas').val().toLowerCase();
    if (currentPractices) {
        $('.ssf-practice-tag').each(function() {
            var practice = $(this).data('practice').toLowerCase();
            if (currentPractices.indexOf(practice) !== -1) {
                $(this).addClass('added');
            }
        });
    }
});
</script>

