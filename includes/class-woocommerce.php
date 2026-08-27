<?php
/**
 * WooCommerce SEO Class
 * 
 * Enhanced Product schema, WooCommerce-specific meta box fields,
 * product category SEO, and Open Graph product tags.
 * 
 * Only loaded when WooCommerce is active.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_WooCommerce {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only load if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }
        
        // Enhanced Product schema
        add_action('wp_head', [$this, 'output_product_schema'], 5);
        
        // Add product-specific meta box fields
        add_action('ssf_metabox_after_fields', [$this, 'product_meta_fields']);
        add_action('ssf_metabox_save', [$this, 'save_product_meta'], 10, 1);
        
        // Product category SEO fields
        add_action('product_cat_edit_form_fields', [$this, 'category_seo_fields'], 10, 1);
        add_action('edited_product_cat', [$this, 'save_category_seo'], 10, 1);
        
        // Product category SEO meta output
        add_action('wp_head', [$this, 'output_category_meta'], 3);
        
        // Product Open Graph tags
        add_filter('ssf_og_tags', [$this, 'product_og_tags'], 10, 2);
        
        // Include 'product' in analyzed post types
        add_filter('ssf_post_types', [$this, 'add_product_type']);
        
        // AJAX for bulk product SEO
        add_action('wp_ajax_ssf_wc_bulk_generate', [$this, 'bulk_generate_product_seo']);
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce') || in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins', []))
        );
    }
    
    /**
     * Output enhanced Product schema (JSON-LD)
     */
    public function output_product_schema() {
        if (!is_singular('product')) return;
        
        global $product;
        
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product) return;
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
            'url' => get_permalink($product->get_id()),
            'sku' => $product->get_sku(),
        ];
        
        // Image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_ids) && isset($schema['image'])) {
            $images = [$schema['image']];
            foreach (array_slice($gallery_ids, 0, 5) as $gid) {
                $url = wp_get_attachment_url($gid);
                if ($url) $images[] = $url;
            }
            $schema['image'] = $images;
        }
        
        // Brand (from product attribute or custom meta)
        $brand = get_post_meta($product->get_id(), '_ssf_product_brand', true);
        if (!empty($brand)) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }
        
        // GTIN/MPN
        $gtin = get_post_meta($product->get_id(), '_ssf_product_gtin', true);
        if (!empty($gtin)) {
            $schema['gtin13'] = $gtin;
        }
        $mpn = get_post_meta($product->get_id(), '_ssf_product_mpn', true);
        if (!empty($mpn)) {
            $schema['mpn'] = $mpn;
        }
        
        // Price / Offers
        if ($product->is_type('variable')) {
            $prices = $product->get_variation_prices();
            if (!empty($prices['price'])) {
                $min = min($prices['price']);
                $max = max($prices['price']);
                $schema['offers'] = [
                    '@type' => 'AggregateOffer',
                    'lowPrice' => wc_format_decimal($min, 2),
                    'highPrice' => wc_format_decimal($max, 2),
                    'priceCurrency' => get_woocommerce_currency(),
                    'offerCount' => count($prices['price']),
                    'availability' => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => get_permalink($product->get_id()),
                ];
            }
        } else {
            $price = $product->get_price();
            if ($price !== '' && $price !== false) {
                $schema['offers'] = [
                    '@type' => 'Offer',
                    'price' => wc_format_decimal($price, 2),
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability' => $product->is_in_stock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => get_permalink($product->get_id()),
                    'priceValidUntil' => date('Y-12-31'),
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => get_bloginfo('name'),
                    ],
                ];
            }
        }
        
        // Reviews / Rating
        $review_count = $product->get_review_count();
        $avg_rating = $product->get_average_rating();
        
        if ($review_count > 0 && $avg_rating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $avg_rating,
                'reviewCount' => $review_count,
                'bestRating' => '5',
                'worstRating' => '1',
            ];
            
            // Get latest reviews
            $comments = get_comments([
                'post_id' => $product->get_id(),
                'status' => 'approve',
                'number' => 5,
                'type' => 'review',
            ]);
            
            if (!empty($comments)) {
                $schema['review'] = [];
                foreach ($comments as $comment) {
                    $rating = get_comment_meta($comment->comment_ID, 'rating', true);
                    $schema['review'][] = [
                        '@type' => 'Review',
                        'author' => [
                            '@type' => 'Person',
                            'name' => $comment->comment_author,
                        ],
                        'datePublished' => $comment->comment_date,
                        'reviewBody' => wp_strip_all_tags($comment->comment_content),
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => $rating ?: '5',
                            'bestRating' => '5',
                            'worstRating' => '1',
                        ],
                    ];
                }
            }
        }
        
        // Categories
        $cats = wp_get_post_terms($product->get_id(), 'product_cat');
        if (!empty($cats) && !is_wp_error($cats)) {
            $schema['category'] = $cats[0]->name;
        }
        
        // Weight
        if ($product->has_weight()) {
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $product->get_weight(),
                'unitCode' => get_option('woocommerce_weight_unit', 'kg'),
            ];
        }
        
        echo "\n<!-- Smart SEO Fixer - Product Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n</script>\n";
    }
    
    /**
     * Add product-specific fields to the SEO meta box
     */
    public function product_meta_fields($post) {
        if ($post->post_type !== 'product') return;
        
        $brand = get_post_meta($post->ID, '_ssf_product_brand', true);
        $gtin = get_post_meta($post->ID, '_ssf_product_gtin', true);
        $mpn = get_post_meta($post->ID, '_ssf_product_mpn', true);
        ?>
        <div class="ssf-wc-fields" style="margin-top:15px;padding-top:15px;border-top:1px solid #e5e7eb;">
            <h4 style="margin:0 0 10px;">
                <span class="dashicons dashicons-cart" style="color:#7c3aed;"></span>
                <?php esc_html_e('WooCommerce SEO', 'smart-seo-fixer'); ?>
            </h4>
            <p class="description" style="margin-bottom:10px;"><?php esc_html_e('These fields enhance Product rich results in Google.', 'smart-seo-fixer'); ?></p>
            
            <div class="ssf-field" style="margin-bottom:10px;">
                <label for="_ssf_product_brand" style="display:block;font-weight:500;margin-bottom:3px;font-size:13px;">
                    <?php esc_html_e('Brand', 'smart-seo-fixer'); ?>
                </label>
                <input type="text" name="_ssf_product_brand" id="_ssf_product_brand" value="<?php echo esc_attr($brand); ?>" class="large-text" placeholder="<?php esc_attr_e('e.g., Nike, Apple', 'smart-seo-fixer'); ?>">
            </div>
            
            <div style="display:flex;gap:10px;">
                <div class="ssf-field" style="flex:1;">
                    <label for="_ssf_product_gtin" style="display:block;font-weight:500;margin-bottom:3px;font-size:13px;">
                        <?php esc_html_e('GTIN / EAN / UPC', 'smart-seo-fixer'); ?>
                    </label>
                    <input type="text" name="_ssf_product_gtin" id="_ssf_product_gtin" value="<?php echo esc_attr($gtin); ?>" class="large-text" placeholder="<?php esc_attr_e('e.g., 0012345678905', 'smart-seo-fixer'); ?>">
                </div>
                <div class="ssf-field" style="flex:1;">
                    <label for="_ssf_product_mpn" style="display:block;font-weight:500;margin-bottom:3px;font-size:13px;">
                        <?php esc_html_e('MPN (Manufacturer Part Number)', 'smart-seo-fixer'); ?>
                    </label>
                    <input type="text" name="_ssf_product_mpn" id="_ssf_product_mpn" value="<?php echo esc_attr($mpn); ?>" class="large-text" placeholder="<?php esc_attr_e('e.g., ABC-12345', 'smart-seo-fixer'); ?>">
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save product-specific meta
     */
    public function save_product_meta($post_id) {
        if (get_post_type($post_id) !== 'product') return;
        
        $fields = ['_ssf_product_brand', '_ssf_product_gtin', '_ssf_product_mpn'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Add SEO fields to product category edit screen
     */
    public function category_seo_fields($term) {
        $seo_title = get_term_meta($term->term_id, '_ssf_seo_title', true);
        $seo_desc = get_term_meta($term->term_id, '_ssf_meta_description', true);
        $focus_kw = get_term_meta($term->term_id, '_ssf_focus_keyword', true);
        ?>
        <tr class="form-field">
            <th colspan="2">
                <h3 style="margin:0;padding:10px 0 0;border-top:2px solid #2563eb;">
                    <span class="dashicons dashicons-chart-line" style="color:#2563eb;"></span>
                    <?php esc_html_e('Smart SEO Fixer', 'smart-seo-fixer'); ?>
                </h3>
            </th>
        </tr>
        <tr class="form-field">
            <th><label for="ssf_cat_title"><?php esc_html_e('SEO Title', 'smart-seo-fixer'); ?></label></th>
            <td>
                <input type="text" name="ssf_cat_title" id="ssf_cat_title" value="<?php echo esc_attr($seo_title); ?>" style="width:100%;">
                <p class="description"><?php esc_html_e('Custom title tag for this category page (50-60 chars recommended).', 'smart-seo-fixer'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="ssf_cat_desc"><?php esc_html_e('Meta Description', 'smart-seo-fixer'); ?></label></th>
            <td>
                <textarea name="ssf_cat_desc" id="ssf_cat_desc" rows="3" style="width:100%;"><?php echo esc_textarea($seo_desc); ?></textarea>
                <p class="description"><?php esc_html_e('Custom meta description for this category page (150-160 chars recommended).', 'smart-seo-fixer'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="ssf_cat_keyword"><?php esc_html_e('Focus Keyword', 'smart-seo-fixer'); ?></label></th>
            <td>
                <input type="text" name="ssf_cat_keyword" id="ssf_cat_keyword" value="<?php echo esc_attr($focus_kw); ?>" style="width:100%;">
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save category SEO fields
     */
    public function save_category_seo($term_id) {
        if (isset($_POST['ssf_cat_title'])) {
            update_term_meta($term_id, '_ssf_seo_title', sanitize_text_field($_POST['ssf_cat_title']));
        }
        if (isset($_POST['ssf_cat_desc'])) {
            update_term_meta($term_id, '_ssf_meta_description', sanitize_textarea_field($_POST['ssf_cat_desc']));
        }
        if (isset($_POST['ssf_cat_keyword'])) {
            update_term_meta($term_id, '_ssf_focus_keyword', sanitize_text_field($_POST['ssf_cat_keyword']));
        }
    }
    
    /**
     * Output category SEO meta tags on product category pages
     */
    public function output_category_meta() {
        if (!is_tax('product_cat')) return;
        
        $term = get_queried_object();
        if (!$term) return;
        
        $title = get_term_meta($term->term_id, '_ssf_seo_title', true);
        $desc = get_term_meta($term->term_id, '_ssf_meta_description', true);
        
        if (!empty($title)) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        }
        
        if (!empty($desc)) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        }
    }
    
    /**
     * Enhance Open Graph tags for products
     */
    public function product_og_tags($tags, $post_id) {
        if (get_post_type($post_id) !== 'product') return $tags;
        
        $product = wc_get_product($post_id);
        if (!$product) return $tags;
        
        $tags['og:type'] = 'product';
        
        $price = $product->get_price();
        if ($price !== '' && $price !== false) {
            $tags['product:price:amount'] = wc_format_decimal($price, 2);
            $tags['product:price:currency'] = get_woocommerce_currency();
        }
        
        $tags['product:availability'] = $product->is_in_stock() ? 'in stock' : 'out of stock';
        
        return $tags;
    }
    
    /**
     * Add 'product' to analyzed post types
     */
    public function add_product_type($types) {
        if (!in_array('product', $types)) {
            $types[] = 'product';
        }
        return $types;
    }
    
    /**
     * Bulk generate SEO for all products
     */
    public function bulk_generate_product_seo() {
        check_ajax_referer('ssf_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'smart-seo-fixer')]);
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = 5;
        
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
        ]);
        
        $total = wp_count_posts('product')->publish ?? 0;
        
        if (empty($products)) {
            wp_send_json_success([
                'done' => true,
                'total' => $total,
                'processed' => $offset,
            ]);
        }
        
        $analyzer = new SSF_Analyzer();
        foreach ($products as $pid) {
            $analyzer->analyze_post($pid);
        }
        
        wp_send_json_success([
            'done' => false,
            'total' => $total,
            'processed' => $offset + count($products),
            'batch_size' => $batch_size,
        ]);
    }
}
