<?php
/**
 * Local SEO Class
 * 
 * Handles local business schema, multiple locations, and local SEO features.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Local_SEO {
    
    /**
     * Business types for schema.org
     */
    private $business_types = [
        // General
        'LocalBusiness' => 'Local Business (General)',
        'ProfessionalService' => 'Professional Service',
        
        // Legal Services (Law Firms)
        'LegalService' => '⚖️ Law Firm / Legal Service',
        'Attorney' => '⚖️ Attorney / Lawyer',
        'Notary' => '⚖️ Notary',
        
        // Medical & Health
        'Dentist' => 'Dentist',
        'Physician' => 'Doctor / Physician',
        'MedicalClinic' => 'Medical Clinic',
        'Hospital' => 'Hospital',
        'Pharmacy' => 'Pharmacy',
        'VeterinaryCare' => 'Veterinarian',
        'Optician' => 'Optician',
        'Chiropractor' => 'Chiropractor',
        
        // Financial & Professional
        'AccountingService' => 'Accountant / CPA',
        'FinancialService' => 'Financial Services',
        'InsuranceAgency' => 'Insurance Agency',
        'RealEstateAgent' => 'Real Estate Agent',
        
        // Home Services
        'Plumber' => 'Plumber',
        'Electrician' => 'Electrician',
        'HVACBusiness' => 'HVAC',
        'RoofingContractor' => 'Roofing Contractor',
        'Locksmith' => 'Locksmith',
        'MovingCompany' => 'Moving Company',
        'HomeAndConstructionBusiness' => 'Home & Construction',
        'GeneralContractor' => 'General Contractor',
        
        // Food & Dining
        'Restaurant' => 'Restaurant',
        'Bakery' => 'Bakery',
        'BarOrPub' => 'Bar / Pub',
        'CafeOrCoffeeShop' => 'Cafe / Coffee Shop',
        
        // Beauty & Wellness
        'BeautySalon' => 'Beauty Salon',
        'HairSalon' => 'Hair Salon',
        'BarberShop' => 'Barber Shop',
        'DaySpa' => 'Day Spa',
        'GymOrFitnessCenter' => 'Gym / Fitness Center',
        'HealthClub' => 'Health Club',
        
        // Retail & Services
        'Store' => 'Store (Retail)',
        'AutoRepair' => 'Auto Repair',
        'AutoDealer' => 'Auto Dealer',
        'PetStore' => 'Pet Store',
        'Florist' => 'Florist',
        'Photographer' => 'Photographer',
        'TravelAgency' => 'Travel Agency',
        'ChildCare' => 'Child Care / Daycare',
        'DryCleaningOrLaundry' => 'Dry Cleaning / Laundry',
    ];
    
    /**
     * Days of the week
     */
    private $days = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add local business schema to head
        add_action('wp_head', [$this, 'output_local_schema'], 5);

        // AJAX handlers
        add_action('wp_ajax_ssf_save_local_seo', [$this, 'ajax_save']);
        add_action('wp_ajax_ssf_save_location', [$this, 'ajax_save_location']);
        add_action('wp_ajax_ssf_delete_location', [$this, 'ajax_delete_location']);
        add_action('wp_ajax_ssf_get_locations', [$this, 'ajax_get_locations']);

        // Address autocomplete on the Local SEO admin page only.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_maps_autocomplete']);
    }

    /**
     * Google Maps/Places API key — read from a wp-config.php constant only.
     *
     * Never stored as a plugin option or shown in a Settings field: this repo
     * is public, so anything committed to it (or saved through an AJAX save
     * handler into the options table, which is still visible to anyone with
     * DB access) is a real exposure path for a key that's billed per request.
     * A wp-config.php constant lives outside version control and outside the
     * database, matching how SSF_BEDROCK_ACCESS_KEY is handled.
     *
     * @return string
     */
    public function get_maps_api_key() {
        return (defined('SSF_GOOGLE_MAPS_API_KEY') && SSF_GOOGLE_MAPS_API_KEY !== '')
            ? SSF_GOOGLE_MAPS_API_KEY
            : '';
    }

    /**
     * Load the Google Places Autocomplete script on the Local SEO page only,
     * and only when a key is configured. Manual entry always works either
     * way — this is a progressive enhancement over the plain text fields.
     *
     * @param string $hook
     */
    public function enqueue_maps_autocomplete($hook) {
        if ($hook !== 'smart-seo_page_smart-seo-fixer-local' && strpos((string) $hook, 'smart-seo-fixer-local') === false) {
            return;
        }

        $api_key = $this->get_maps_api_key();
        if ($api_key === '') {
            return;
        }

        // The 'callback' param is Google's documented way to run code once
        // this async script finishes loading — window.ssfInitPlacesAutocomplete
        // is defined in the view's inline script, which prints before this
        // footer-queued script does.
        wp_enqueue_script(
            'ssf-google-places',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places&callback=ssfInitPlacesAutocomplete',
            [],
            null,
            true
        );
    }
    
    /**
     * Get business types
     */
    public function get_business_types() {
        return $this->business_types;
    }
    
    /**
     * Get days
     */
    public function get_days() {
        return $this->days;
    }
    
    /**
     * Get local SEO settings
     */
    public function get_settings() {
        $defaults = [
            'enabled' => false,
            'business_name' => get_bloginfo('name'),
            'business_type' => 'LocalBusiness',
            'phone' => '',
            'email' => get_option('admin_email'),
            'website' => home_url(),
            'price_range' => '',
            'description' => get_bloginfo('description'),
            'logo' => '',
            'image' => '',
            'address' => [
                'street' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => 'US',
            ],
            'geo' => [
                'latitude' => '',
                'longitude' => '',
            ],
            'hours' => $this->get_default_hours(),
            'social' => [
                'facebook' => '',
                'twitter' => '',
                'instagram' => '',
                'linkedin' => '',
                'youtube' => '',
                'pinterest' => '',
                'yelp' => '',
            ],
            'service_areas' => [],
            'practice_areas' => [],
            'same_as' => [],
        ];
        
        $saved = get_option('ssf_local_seo', []);
        
        return wp_parse_args($saved, $defaults);
    }
    
    /**
     * Get default business hours
     */
    private function get_default_hours() {
        $hours = [];
        
        foreach ($this->days as $key => $label) {
            $hours[$key] = [
                'open' => ($key === 'saturday' || $key === 'sunday') ? false : true,
                'open_time' => '09:00',
                'close_time' => '17:00',
                'open_time_2' => '',
                'close_time_2' => '',
            ];
        }
        
        return $hours;
    }
    
    /**
     * Save settings
     */
    public function save_settings($data) {
        $settings = [
            'enabled' => !empty($data['enabled']),
            'business_name' => sanitize_text_field($data['business_name'] ?? ''),
            'business_type' => sanitize_text_field($data['business_type'] ?? 'LocalBusiness'),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'website' => esc_url_raw($data['website'] ?? ''),
            'price_range' => sanitize_text_field($data['price_range'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'logo' => esc_url_raw($data['logo'] ?? ''),
            'image' => esc_url_raw($data['image'] ?? ''),
        ];
        
        // Address
        if (isset($data['address']) && is_array($data['address'])) {
            $settings['address'] = [
                'street' => sanitize_text_field($data['address']['street'] ?? ''),
                'city' => sanitize_text_field($data['address']['city'] ?? ''),
                'state' => sanitize_text_field($data['address']['state'] ?? ''),
                'zip' => sanitize_text_field($data['address']['zip'] ?? ''),
                'country' => sanitize_text_field($data['address']['country'] ?? 'US'),
            ];
        }
        
        // Geo coordinates
        if (isset($data['geo']) && is_array($data['geo'])) {
            $settings['geo'] = [
                'latitude' => sanitize_text_field($data['geo']['latitude'] ?? ''),
                'longitude' => sanitize_text_field($data['geo']['longitude'] ?? ''),
            ];
        }
        
        // Hours
        if (isset($data['hours']) && is_array($data['hours'])) {
            $settings['hours'] = [];
            foreach ($this->days as $key => $label) {
                if (isset($data['hours'][$key])) {
                    $settings['hours'][$key] = [
                        'open' => !empty($data['hours'][$key]['open']),
                        'open_time' => sanitize_text_field($data['hours'][$key]['open_time'] ?? '09:00'),
                        'close_time' => sanitize_text_field($data['hours'][$key]['close_time'] ?? '17:00'),
                        'open_time_2' => sanitize_text_field($data['hours'][$key]['open_time_2'] ?? ''),
                        'close_time_2' => sanitize_text_field($data['hours'][$key]['close_time_2'] ?? ''),
                    ];
                }
            }
        }
        
        // Social profiles
        if (isset($data['social']) && is_array($data['social'])) {
            $settings['social'] = [
                'facebook' => esc_url_raw($data['social']['facebook'] ?? ''),
                'twitter' => esc_url_raw($data['social']['twitter'] ?? ''),
                'instagram' => esc_url_raw($data['social']['instagram'] ?? ''),
                'linkedin' => esc_url_raw($data['social']['linkedin'] ?? ''),
                'youtube' => esc_url_raw($data['social']['youtube'] ?? ''),
                'pinterest' => esc_url_raw($data['social']['pinterest'] ?? ''),
                'yelp' => esc_url_raw($data['social']['yelp'] ?? ''),
            ];
        }
        
        // Service areas
        if (isset($data['service_areas'])) {
            if (is_array($data['service_areas'])) {
                $settings['service_areas'] = array_map('sanitize_text_field', $data['service_areas']);
            } else {
                // Handle comma-separated string
                $areas = explode(',', $data['service_areas']);
                $settings['service_areas'] = array_filter(array_map('trim', array_map('sanitize_text_field', $areas)));
            }
        }
        
        // Practice areas (for law firms)
        if (isset($data['practice_areas'])) {
            if (is_array($data['practice_areas'])) {
                $settings['practice_areas'] = array_map('sanitize_text_field', $data['practice_areas']);
            } else {
                // Handle comma-separated string
                $areas = explode(',', $data['practice_areas']);
                $settings['practice_areas'] = array_filter(array_map('trim', array_map('sanitize_text_field', $areas)));
            }
        }
        
        update_option('ssf_local_seo', $settings);
        
        return $settings;
    }
    
    /**
     * Get all locations
     */
    public function get_locations() {
        return get_option('ssf_locations', []);
    }
    
    /**
     * Save a location
     */
    public function save_location($data) {
        $locations = $this->get_locations();
        
        $location_id = !empty($data['id']) ? sanitize_text_field($data['id']) : 'loc_' . uniqid();
        
        $location = [
            'id' => $location_id,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'address' => [
                'street' => sanitize_text_field($data['address']['street'] ?? ''),
                'city' => sanitize_text_field($data['address']['city'] ?? ''),
                'state' => sanitize_text_field($data['address']['state'] ?? ''),
                'zip' => sanitize_text_field($data['address']['zip'] ?? ''),
                'country' => sanitize_text_field($data['address']['country'] ?? 'US'),
            ],
            'geo' => [
                'latitude' => sanitize_text_field($data['geo']['latitude'] ?? ''),
                'longitude' => sanitize_text_field($data['geo']['longitude'] ?? ''),
            ],
            'hours' => [],
        ];
        
        // Hours
        if (isset($data['hours']) && is_array($data['hours'])) {
            foreach ($this->days as $key => $label) {
                if (isset($data['hours'][$key])) {
                    $location['hours'][$key] = [
                        'open' => !empty($data['hours'][$key]['open']),
                        'open_time' => sanitize_text_field($data['hours'][$key]['open_time'] ?? '09:00'),
                        'close_time' => sanitize_text_field($data['hours'][$key]['close_time'] ?? '17:00'),
                    ];
                }
            }
        }
        
        $locations[$location_id] = $location;
        
        update_option('ssf_locations', $locations);
        
        return $location;
    }
    
    /**
     * Delete a location
     */
    public function delete_location($location_id) {
        $locations = $this->get_locations();
        
        if (isset($locations[$location_id])) {
            unset($locations[$location_id]);
            update_option('ssf_locations', $locations);
            return true;
        }
        
        return false;
    }
    
    /**
     * Output local business schema
     */
    public function output_local_schema() {
        $settings = $this->get_settings();
        
        if (!$settings['enabled']) {
            return;
        }
        
        // Output on homepage/front page
        $should_output = is_front_page() || is_home();
        
        // Also output on individual posts/pages where "Add Location Schema" was enabled
        if (!$should_output && is_singular()) {
            $post_id = get_the_ID();
            $should_output = !empty(get_post_meta($post_id, '_ssf_include_local_schema', true));
        }
        
        if (!$should_output) {
            return;
        }
        
        $schema = $this->generate_schema($settings);
        
        if (!empty($schema)) {
            echo "\n<!-- Smart SEO Fixer - Local Business Schema -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n</script>\n";
        }
        
        // Output additional locations
        $locations = $this->get_locations();
        
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $loc_schema = $this->generate_location_schema($location, $settings);
                if (!empty($loc_schema)) {
                    echo '<script type="application/ld+json">' . "\n";
                    echo wp_json_encode($loc_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    echo "\n</script>\n";
                }
            }
        }
    }
    
    /**
     * Generate schema for main business
     */
    public function generate_schema($settings) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $settings['business_type'],
            '@id' => home_url('/#localbusiness'),
            'name' => $settings['business_name'],
            'url' => $settings['website'] ?: home_url(),
        ];
        
        // Description
        if (!empty($settings['description'])) {
            $schema['description'] = $settings['description'];
        }
        
        // Phone
        if (!empty($settings['phone'])) {
            $schema['telephone'] = $settings['phone'];
        }
        
        // Email
        if (!empty($settings['email'])) {
            $schema['email'] = $settings['email'];
        }
        
        // Price range
        if (!empty($settings['price_range'])) {
            $schema['priceRange'] = $settings['price_range'];
        }
        
        // Logo
        if (!empty($settings['logo'])) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $settings['logo'],
            ];
        }
        
        // Image
        if (!empty($settings['image'])) {
            $schema['image'] = $settings['image'];
        }
        
        // Address
        if (!empty($settings['address']['street']) || !empty($settings['address']['city'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings['address']['street'],
                'addressLocality' => $settings['address']['city'],
                'addressRegion' => $settings['address']['state'],
                'postalCode' => $settings['address']['zip'],
                'addressCountry' => $settings['address']['country'],
            ];
        }
        
        // Geo coordinates
        if (!empty($settings['geo']['latitude']) && !empty($settings['geo']['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $settings['geo']['latitude'],
                'longitude' => $settings['geo']['longitude'],
            ];
        }
        
        // Opening hours
        $opening_hours = $this->format_opening_hours($settings['hours'] ?? []);
        if (!empty($opening_hours)) {
            $schema['openingHoursSpecification'] = $opening_hours;
        }
        
        // Social profiles (sameAs)
        $same_as = [];
        foreach (($settings['social'] ?? []) as $network => $url) {
            if (!empty($url)) {
                $same_as[] = $url;
            }
        }
        if (!empty($same_as)) {
            $schema['sameAs'] = $same_as;
        }
        
        // Service areas
        if (!empty($settings['service_areas'])) {
            $schema['areaServed'] = [];
            foreach ($settings['service_areas'] as $area) {
                $schema['areaServed'][] = [
                    '@type' => 'City',
                    'name' => $area,
                ];
            }
        }
        
        // Practice areas (for law firms - uses knowsAbout)
        if (!empty($settings['practice_areas'])) {
            $schema['knowsAbout'] = $settings['practice_areas'];
        }
        
        // For legal services, add hasOfferCatalog with practice areas
        if (in_array($settings['business_type'], ['LegalService', 'Attorney']) && !empty($settings['practice_areas'])) {
            $offers = [];
            foreach ($settings['practice_areas'] as $practice) {
                $offers[] = [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $practice,
                    ],
                ];
            }
            $schema['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => __('Legal Services', 'smart-seo-fixer'),
                'itemListElement' => $offers,
            ];
        }
        
        return $schema;
    }
    
    /**
     * Generate schema for additional location
     */
    private function generate_location_schema($location, $main_settings) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $main_settings['business_type'],
            '@id' => home_url('/#location-' . $location['id']),
            'name' => $main_settings['business_name'] . ' - ' . $location['name'],
            'parentOrganization' => [
                '@id' => home_url('/#localbusiness'),
            ],
        ];
        
        // Phone
        if (!empty($location['phone'])) {
            $schema['telephone'] = $location['phone'];
        }
        
        // Email
        if (!empty($location['email'])) {
            $schema['email'] = $location['email'];
        }
        
        // Address
        if (!empty($location['address']['street']) || !empty($location['address']['city'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $location['address']['street'],
                'addressLocality' => $location['address']['city'],
                'addressRegion' => $location['address']['state'],
                'postalCode' => $location['address']['zip'],
                'addressCountry' => $location['address']['country'],
            ];
        }
        
        // Geo
        if (!empty($location['geo']['latitude']) && !empty($location['geo']['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $location['geo']['latitude'],
                'longitude' => $location['geo']['longitude'],
            ];
        }
        
        // Hours
        if (!empty($location['hours'])) {
            $opening_hours = $this->format_opening_hours($location['hours']);
            if (!empty($opening_hours)) {
                $schema['openingHoursSpecification'] = $opening_hours;
            }
        }
        
        return $schema;
    }
    
    /**
     * Format opening hours for schema
     */
    private function format_opening_hours($hours) {
        $specs = [];
        $day_map = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
        
        foreach ($hours as $day => $data) {
            if (empty($data['open'])) {
                continue;
            }
            
            $spec = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $day_map[$day],
                'opens' => $data['open_time'],
                'closes' => $data['close_time'],
            ];
            
            $specs[] = $spec;
            
            // Second opening period (lunch break scenario)
            if (!empty($data['open_time_2']) && !empty($data['close_time_2'])) {
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $day_map[$day],
                    'opens' => $data['open_time_2'],
                    'closes' => $data['close_time_2'],
                ];
            }
        }
        
        return $specs;
    }
    
    /**
     * AJAX: Save local SEO settings
     */
    public function ajax_save() {
        check_ajax_referer('ssf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $data = $_POST;
        unset($data['action'], $data['nonce']);
        
        $settings = $this->save_settings($data);

        // Return the exact schema this save will produce, so the admin sees
        // tangible proof of what was generated instead of a bare "saved" message.
        wp_send_json_success([
            'message' => __('Local SEO settings saved.', 'smart-seo-fixer'),
            'settings' => $settings,
            'schema'   => $this->generate_schema($settings),
        ]);
    }
    
    /**
     * AJAX: Save location
     */
    public function ajax_save_location() {
        check_ajax_referer('ssf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $data = $_POST;
        unset($data['action'], $data['nonce']);
        
        $location = $this->save_location($data);
        
        wp_send_json_success([
            'message' => __('Location saved.', 'smart-seo-fixer'),
            'location' => $location,
        ]);
    }
    
    /**
     * AJAX: Delete location
     */
    public function ajax_delete_location() {
        check_ajax_referer('ssf_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $location_id = sanitize_text_field($_POST['location_id'] ?? '');
        
        if (empty($location_id)) {
            wp_send_json_error(['message' => __('No location specified.', 'smart-seo-fixer')]);
        }
        
        $this->delete_location($location_id);
        
        wp_send_json_success(['message' => __('Location deleted.', 'smart-seo-fixer')]);
    }
    
    /**
     * AJAX: Get locations
     */
    public function ajax_get_locations() {
        check_ajax_referer('ssf_nonce', 'nonce');
        
        wp_send_json_success($this->get_locations());
    }
}

