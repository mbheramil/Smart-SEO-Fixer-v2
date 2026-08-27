<?php
/**
 * Schema Markup Class
 * 
 * Generates and outputs JSON-LD schema markup.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Schema {
    
    /**
     * Constructor
     */
    public function __construct() {
        if (Smart_SEO_Fixer::get_option('enable_schema', true)) {
            add_action('wp_head', [$this, 'output_schema'], 10);
        }
    }
    
    /**
     * Output schema markup
     */
    public function output_schema() {
        $schema = $this->get_schema();
        
        if (!empty($schema)) {
            echo "\n<!-- Smart SEO Fixer - Schema Markup -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n</script>\n";
        }
        
        // Output breadcrumb schema (on all pages except homepage)
        $breadcrumb = $this->get_breadcrumb_schema();
        if (!empty($breadcrumb)) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n</script>\n";
        }
        
        // Output custom schema saved per-post (from the "Suggest Schema" AI tool)
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $custom_schema = get_post_meta($post->ID, '_ssf_custom_schema', true);
                if (!empty($custom_schema)) {
                    $decoded = json_decode($custom_schema, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                        echo '<script type="application/ld+json">' . "\n";
                        echo wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                        echo "\n</script>\n";
                    }
                }
            }
        }
        
        // Auto-detect and output FAQ/HowTo schema on singular pages
        if (is_singular()) {
            $post = get_post();
            if ($post && !empty($post->post_content)) {
                // Detect FAQ content
                $faq_schema = $this->detect_faq_schema($post->post_content);
                if (!empty($faq_schema)) {
                    echo '<script type="application/ld+json">' . "\n";
                    echo wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    echo "\n</script>\n";
                }
                
                // Detect HowTo content
                $howto_schema = $this->detect_howto_schema($post->post_content, get_the_title($post->ID));
                if (!empty($howto_schema)) {
                    echo '<script type="application/ld+json">' . "\n";
                    echo wp_json_encode($howto_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    echo "\n</script>\n";
                }
            }
        }
    }
    
    /**
     * Get schema for current page
     */
    public function get_schema() {
        // Site-wide Organization/Website schema
        if (is_front_page()) {
            return $this->get_website_schema();
        }
        
        // Product (if WooCommerce) — check before generic singular
        if (is_singular('product') && class_exists('WooCommerce')) {
            return $this->get_product_schema();
        }
        
        // Single post (Article schema)
        if (is_singular('post')) {
            return $this->get_article_schema();
        }
        
        // Single page (WebPage schema)
        if (is_singular('page')) {
            return $this->get_webpage_schema();
        }
        
        // Custom post types registered in plugin settings get Article schema
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        if (is_singular($post_types)) {
            return $this->get_article_schema();
        }
        
        // Author archive
        if (is_author()) {
            return $this->get_person_schema();
        }
        
        // Category/tag/taxonomy archives
        if (is_category() || is_tag() || is_tax()) {
            return $this->get_collection_schema();
        }
        
        return null;
    }
    
    /**
     * Get Website schema (homepage)
     */
    private function get_website_schema() {
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => home_url('/#website'),
                    'url' => home_url('/'),
                    'name' => get_bloginfo('name'),
                    'description' => get_bloginfo('description'),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => home_url('/?s={search_term_string}'),
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                $this->get_organization_schema(),
            ],
        ];
        
        return $schema;
    }
    
    /**
     * Get Article schema (blog posts)
     */
    private function get_article_schema() {
        $post = get_post();
        
        if (!$post) {
            return null;
        }
        
        $author = get_userdata($post->post_author);
        $image = $this->get_post_image($post->ID);
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            '@id' => get_permalink($post->ID) . '#article',
            'headline' => get_the_title($post->ID),
            'description' => $this->get_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink($post->ID),
            ],
            'author' => [
                '@type' => 'Person',
                'name' => $author ? $author->display_name : '',
                'url' => $author ? get_author_posts_url($author->ID) : '',
            ],
            'publisher' => $this->get_organization_schema(),
        ];
        
        if (!empty($image)) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $image['url'],
                'width' => $image['width'],
                'height' => $image['height'],
            ];
        }
        
        // Add article section (category)
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
        }
        
        // Add keywords (tags)
        $tags = get_the_tags($post->ID);
        if (!empty($tags)) {
            $schema['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
        }
        
        // Word count
        $word_count = str_word_count(strip_tags($post->post_content));
        $schema['wordCount'] = $word_count;
        
        return $schema;
    }
    
    /**
     * Get WebPage schema (pages)
     */
    private function get_webpage_schema() {
        $post = get_post();
        
        if (!$post) {
            return null;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => get_permalink($post->ID) . '#webpage',
            'name' => get_the_title($post->ID),
            'description' => $this->get_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'isPartOf' => [
                '@id' => home_url('/#website'),
            ],
        ];
        
        $image = $this->get_post_image($post->ID);
        if (!empty($image)) {
            $schema['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $image['url'],
            ];
        }
        
        return $schema;
    }
    
    /**
     * Get Person schema (author pages)
     */
    private function get_person_schema() {
        $author = get_queried_object();
        
        if (!$author) {
            return null;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $author->display_name,
            'url' => get_author_posts_url($author->ID),
            'description' => get_the_author_meta('description', $author->ID),
        ];
        
        // Add social profiles
        $social_fields = [
            'twitter' => 'twitter',
            'facebook' => 'facebook',
            'linkedin' => 'linkedin',
        ];
        
        $same_as = [];
        foreach ($social_fields as $field => $network) {
            $url = get_the_author_meta($field, $author->ID);
            if (!empty($url)) {
                $same_as[] = $url;
            }
        }
        
        if (!empty($same_as)) {
            $schema['sameAs'] = $same_as;
        }
        
        return $schema;
    }
    
    /**
     * Get Product schema (WooCommerce)
     */
    private function get_product_schema() {
        if (!function_exists('wc_get_product')) {
            return null;
        }
        
        global $product;
        
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product) {
            return null;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'url' => get_permalink($product->get_id()),
            'sku' => $product->get_sku(),
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => $product->is_in_stock() 
                    ? 'https://schema.org/InStock' 
                    : 'https://schema.org/OutOfStock',
                'url' => get_permalink($product->get_id()),
            ],
        ];
        
        // Add image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_data = wp_get_attachment_image_src($image_id, 'full');
            if ($image_data) {
                $schema['image'] = $image_data[0];
            }
        }
        
        // Add reviews if available
        if ($product->get_review_count() > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
            ];
        }
        
        return $schema;
    }
    
    /**
     * Get Organization schema (reusable)
     */
    private function get_organization_schema() {
        $org = [
            '@type' => 'Organization',
            '@id' => home_url('/#organization'),
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ];
        
        $logo_url = $this->get_site_logo();
        if (!empty($logo_url)) {
            $org['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo_url,
            ];
        }
        
        return $org;
    }
    
    /**
     * Get CollectionPage schema (category/tag/taxonomy archives)
     */
    private function get_collection_schema() {
        $term = get_queried_object();
        
        if (!$term || !is_a($term, 'WP_Term')) {
            return null;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $term->name,
            'url' => get_term_link($term),
            'isPartOf' => [
                '@id' => home_url('/#website'),
            ],
        ];
        
        if (!empty($term->description)) {
            $schema['description'] = $term->description;
        }
        
        return $schema;
    }
    
    /**
     * Get site logo URL
     */
    private function get_site_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');
        
        if ($custom_logo_id) {
            $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($logo_data) {
                return $logo_data[0];
            }
        }
        
        // Fallback to site icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon_data = wp_get_attachment_image_src($site_icon_id, 'full');
            if ($icon_data) {
                return $icon_data[0];
            }
        }
        
        return '';
    }
    
    /**
     * Get post featured image data
     */
    private function get_post_image($post_id) {
        $image_id = get_post_thumbnail_id($post_id);
        
        if (!$image_id) {
            return null;
        }
        
        $image_data = wp_get_attachment_image_src($image_id, 'full');
        
        if (!$image_data) {
            return null;
        }
        
        return [
            'url' => $image_data[0],
            'width' => $image_data[1],
            'height' => $image_data[2],
        ];
    }
    
    /**
     * Get post description
     */
    private function get_description($post) {
        // Try SEO meta description first
        $description = get_post_meta($post->ID, '_ssf_meta_description', true);
        
        if (!empty($description)) {
            return $description;
        }
        
        // Use excerpt
        if (!empty($post->post_excerpt)) {
            return wp_trim_words($post->post_excerpt, 25, '...');
        }
        
        // Use content
        $text = wp_strip_all_tags($post->post_content);
        return wp_trim_words($text, 25, '...');
    }
    
    /**
     * Generate breadcrumb schema
     */
    public function get_breadcrumb_schema() {
        if (is_front_page()) {
            return null;
        }
        
        $breadcrumbs = [];
        $position = 1;
        
        // Home
        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Home', 'smart-seo-fixer'),
            'item' => home_url('/'),
        ];
        
        if (is_singular('post')) {
            // Category
            $categories = get_the_category();
            if (!empty($categories)) {
                $breadcrumbs[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $categories[0]->name,
                    'item' => get_category_link($categories[0]->term_id),
                ];
            }
            
            // Post
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => get_the_title(),
                'item' => get_permalink(),
            ];
        } elseif (is_singular('page')) {
            // Get parent pages
            $post = get_post();
            $ancestors = get_post_ancestors($post);
            
            if (!empty($ancestors)) {
                $ancestors = array_reverse($ancestors);
                foreach ($ancestors as $ancestor_id) {
                    $breadcrumbs[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => get_the_title($ancestor_id),
                        'item' => get_permalink($ancestor_id),
                    ];
                }
            }
            
            // Current page
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => get_the_title(),
                'item' => get_permalink(),
            ];
        } elseif (is_category()) {
            $category = get_queried_object();
            
            // Parent categories
            if ($category->parent) {
                $parent_cats = get_ancestors($category->term_id, 'category');
                $parent_cats = array_reverse($parent_cats);
                
                foreach ($parent_cats as $parent_id) {
                    $parent = get_category($parent_id);
                    $breadcrumbs[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $parent->name,
                        'item' => get_category_link($parent_id),
                    ];
                }
            }
            
            // Current category
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $category->name,
                'item' => get_category_link($category->term_id),
            ];
        }
        
        if (count($breadcrumbs) <= 1) {
            return null;
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];
    }
    
    /**
     * Auto-detect FAQ content in post HTML
     * Looks for question headings (h2/h3/h4 ending with ?) followed by paragraph answers
     */
    private function detect_faq_schema($content) {
        $faqs = [];
        
        // Pattern: heading with a question mark, followed by content until next heading
        // Matches h2, h3, h4 that contain a question (ends with ?)
        if (preg_match_all(
            '/<h[2-4][^>]*>\s*(.*?\?)\s*<\/h[2-4]>\s*(.*?)(?=<h[2-4]|$)/is',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $question = wp_strip_all_tags(trim($match[1]));
                $answer = wp_strip_all_tags(trim($match[2]));
                
                // Skip if answer is too short (likely not a real Q&A)
                if (strlen($answer) < 20 || empty($question)) {
                    continue;
                }
                
                // Trim answer to reasonable length
                if (strlen($answer) > 500) {
                    $answer = wp_trim_words($answer, 80, '...');
                }
                
                $faqs[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }
        
        // Need at least 2 Q&A pairs to qualify as FAQ
        if (count($faqs) < 2) {
            return null;
        }
        
        return $this->generate_faq_schema($faqs);
    }
    
    /**
     * Auto-detect HowTo content in post HTML
     * Looks for ordered lists or step-pattern headings (Step 1, Step 2, etc.)
     */
    private function detect_howto_schema($content, $title = '') {
        $steps = [];
        
        // Check if title suggests a how-to
        $is_howto_title = preg_match('/^(how to|guide|tutorial|step.by.step|instructions)/i', $title);
        
        // Pattern 1: Ordered list items (ol > li)
        if (preg_match('/<ol[^>]*>(.*?)<\/ol>/is', $content, $ol_match)) {
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $ol_match[1], $li_matches)) {
                foreach ($li_matches[1] as $li) {
                    $text = wp_strip_all_tags(trim($li));
                    if (strlen($text) > 10) {
                        $steps[] = ['name' => wp_trim_words($text, 8, ''), 'text' => $text];
                    }
                }
            }
        }
        
        // Pattern 2: Headings with "Step N" pattern
        if (empty($steps) && preg_match_all(
            '/<h[2-4][^>]*>\s*((?:Step\s*\d+[:\.\s\-–—]*)?.*?)\s*<\/h[2-4]>\s*(.*?)(?=<h[2-4]|$)/is',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            $step_headings = [];
            foreach ($matches as $match) {
                $heading = wp_strip_all_tags(trim($match[1]));
                // Only count if the heading starts with "Step" or a number
                if (preg_match('/^(step\s*\d+|^\d+[\.\):])/i', $heading)) {
                    $text = wp_strip_all_tags(trim($match[2]));
                    if (strlen($text) > 10) {
                        $step_headings[] = ['name' => $heading, 'text' => wp_trim_words($text, 40, '...')];
                    }
                }
            }
            if (count($step_headings) >= 2) {
                $steps = $step_headings;
            }
        }
        
        // Need at least 3 steps for ordered list, or 2 for explicit step headings, plus a how-to title
        $min_steps = !empty($steps) && isset($steps[0]) && preg_match('/^step/i', $steps[0]['name'] ?? '') ? 2 : 3;
        if (count($steps) < $min_steps) {
            return null;
        }
        
        // Only output if title suggests how-to OR we found explicit "Step N" pattern
        if (!$is_howto_title && !preg_match('/^step/i', $steps[0]['name'] ?? '')) {
            return null;
        }
        
        return $this->generate_howto_schema($title, $steps);
    }
    
    /**
     * Generate FAQ schema from content
     */
    public function generate_faq_schema($faqs) {
        if (empty($faqs)) {
            return null;
        }
        
        $items = [];
        
        foreach ($faqs as $faq) {
            $items[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
    }
    
    /**
     * Generate HowTo schema
     */
    public function generate_howto_schema($title, $steps, $total_time = '', $description = '') {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $title,
        ];
        
        if (!empty($description)) {
            $schema['description'] = $description;
        }
        
        if (!empty($total_time)) {
            $schema['totalTime'] = $total_time;
        }
        
        $step_items = [];
        $position = 1;
        
        foreach ($steps as $step) {
            $step_items[] = [
                '@type' => 'HowToStep',
                'position' => $position++,
                'name' => $step['name'] ?? '',
                'text' => $step['text'] ?? $step,
            ];
        }
        
        $schema['step'] = $step_items;
        
        return $schema;
    }
}

