<?php
/**
 * Breadcrumbs Class
 * 
 * Provides shortcode [ssf_breadcrumbs] and PHP function ssf_breadcrumbs()
 * with Schema.org BreadcrumbList markup.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Breadcrumbs {
    
    /**
     * Default options
     */
    private $defaults = [
        'separator'   => '›',
        'home_label'  => 'Home',
        'show_home'   => true,
        'show_current' => true,
        'wrapper_class' => 'ssf-breadcrumbs',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('ssf_breadcrumbs', [$this, 'shortcode']);
        
        // Output breadcrumb CSS on frontend when needed
        add_action('wp_head', [$this, 'output_css']);
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode($atts = []) {
        $atts = shortcode_atts($this->defaults, $atts, 'ssf_breadcrumbs');
        return $this->render($atts);
    }
    
    /**
     * Render breadcrumbs HTML with Schema.org markup
     */
    public function render($args = []) {
        $args = wp_parse_args($args, $this->defaults);
        
        if (is_front_page()) {
            return ''; // No breadcrumbs on homepage
        }
        
        $items = $this->build_trail($args);
        
        if (empty($items)) {
            return '';
        }
        
        $sep = '<span class="ssf-bc-sep">' . esc_html($args['separator']) . '</span>';
        
        $html = '<nav class="' . esc_attr($args['wrapper_class']) . '" aria-label="' . esc_attr__('Breadcrumb', 'smart-seo-fixer') . '">';
        $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
        
        $count = count($items);
        foreach ($items as $i => $item) {
            $position = $i + 1;
            $is_last = ($i === $count - 1);
            
            $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            
            if ($is_last && $args['show_current']) {
                // Current page (no link)
                $html .= '<span itemprop="name" class="ssf-bc-current">' . esc_html($item['label']) . '</span>';
            } else {
                $html .= '<a itemprop="item" href="' . esc_url($item['url']) . '">';
                $html .= '<span itemprop="name">' . esc_html($item['label']) . '</span>';
                $html .= '</a>';
            }
            
            $html .= '<meta itemprop="position" content="' . $position . '">';
            $html .= '</li>';
            
            if (!$is_last) {
                $html .= $sep;
            }
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Build the breadcrumb trail
     */
    private function build_trail($args) {
        $trail = [];
        
        // Home
        if ($args['show_home']) {
            $trail[] = [
                'label' => $args['home_label'],
                'url' => home_url('/'),
            ];
        }
        
        // Singular post/page/CPT
        if (is_singular()) {
            $post = get_queried_object();
            
            if ($post->post_type === 'post') {
                // Blog > Category > Post
                $categories = get_the_category($post->ID);
                
                if (!empty($categories)) {
                    // Get primary category (Yoast primary or first)
                    $primary_id = get_post_meta($post->ID, '_yoast_wpseo_primary_category', true);
                    $category = null;
                    
                    if ($primary_id) {
                        $category = get_term($primary_id, 'category');
                    }
                    
                    if (!$category || is_wp_error($category)) {
                        $category = $categories[0];
                    }
                    
                    // Add parent categories
                    $ancestors = get_ancestors($category->term_id, 'category');
                    $ancestors = array_reverse($ancestors);
                    
                    foreach ($ancestors as $ancestor_id) {
                        $ancestor = get_term($ancestor_id, 'category');
                        if ($ancestor && !is_wp_error($ancestor)) {
                            $trail[] = [
                                'label' => $ancestor->name,
                                'url' => get_term_link($ancestor),
                            ];
                        }
                    }
                    
                    $trail[] = [
                        'label' => $category->name,
                        'url' => get_term_link($category),
                    ];
                }
                
            } elseif ($post->post_type === 'page') {
                // Page ancestors
                $ancestors = get_post_ancestors($post->ID);
                $ancestors = array_reverse($ancestors);
                
                foreach ($ancestors as $ancestor_id) {
                    $ancestor = get_post($ancestor_id);
                    if ($ancestor) {
                        $trail[] = [
                            'label' => $ancestor->post_title,
                            'url' => get_permalink($ancestor_id),
                        ];
                    }
                }
                
            } else {
                // Custom Post Type
                $post_type_obj = get_post_type_object($post->post_type);
                if ($post_type_obj && $post_type_obj->has_archive) {
                    $trail[] = [
                        'label' => $post_type_obj->labels->name,
                        'url' => get_post_type_archive_link($post->post_type),
                    ];
                }
                
                // Check for taxonomy
                $taxonomies = get_object_taxonomies($post->post_type, 'objects');
                foreach ($taxonomies as $tax) {
                    if (!$tax->hierarchical) continue;
                    $terms = get_the_terms($post->ID, $tax->name);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $trail[] = [
                            'label' => $terms[0]->name,
                            'url' => get_term_link($terms[0]),
                        ];
                        break;
                    }
                }
            }
            
            // Current post
            $trail[] = [
                'label' => $post->post_title,
                'url' => get_permalink($post->ID),
            ];
            
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            
            if ($term) {
                // Parent terms
                if (is_taxonomy_hierarchical($term->taxonomy)) {
                    $ancestors = get_ancestors($term->term_id, $term->taxonomy);
                    $ancestors = array_reverse($ancestors);
                    
                    foreach ($ancestors as $ancestor_id) {
                        $ancestor = get_term($ancestor_id, $term->taxonomy);
                        if ($ancestor && !is_wp_error($ancestor)) {
                            $trail[] = [
                                'label' => $ancestor->name,
                                'url' => get_term_link($ancestor),
                            ];
                        }
                    }
                }
                
                $trail[] = [
                    'label' => $term->name,
                    'url' => get_term_link($term),
                ];
            }
            
        } elseif (is_post_type_archive()) {
            $post_type = get_queried_object();
            if ($post_type) {
                $trail[] = [
                    'label' => $post_type->labels->name,
                    'url' => get_post_type_archive_link($post_type->name),
                ];
            }
            
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $trail[] = [
                    'label' => $author->display_name,
                    'url' => get_author_posts_url($author->ID),
                ];
            }
            
        } elseif (is_date()) {
            if (is_year()) {
                $trail[] = ['label' => get_the_date('Y'), 'url' => ''];
            } elseif (is_month()) {
                $trail[] = ['label' => get_the_date('F Y'), 'url' => ''];
            } elseif (is_day()) {
                $trail[] = ['label' => get_the_date(), 'url' => ''];
            }
            
        } elseif (is_search()) {
            $trail[] = [
                'label' => sprintf(__('Search: "%s"', 'smart-seo-fixer'), get_search_query()),
                'url' => '',
            ];
            
        } elseif (is_404()) {
            $trail[] = [
                'label' => __('Page Not Found', 'smart-seo-fixer'),
                'url' => '',
            ];
        }
        
        return $trail;
    }
    
    /**
     * Output minimal breadcrumb CSS on frontend
     */
    public function output_css() {
        if (is_front_page() || is_admin()) return;
        ?>
        <style>
        .ssf-breadcrumbs { font-size: 14px; color: #6b7280; margin: 10px 0 15px; }
        .ssf-breadcrumbs ol { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 0; margin: 0; padding: 0; }
        .ssf-breadcrumbs li { display: inline-flex; align-items: center; }
        .ssf-breadcrumbs a { color: #2563eb; text-decoration: none; }
        .ssf-breadcrumbs a:hover { text-decoration: underline; }
        .ssf-bc-sep { margin: 0 8px; color: #9ca3af; }
        .ssf-bc-current { color: #374151; font-weight: 500; }
        </style>
        <?php
    }
}

/**
 * Template function for use in themes
 * Usage: <?php if (function_exists('ssf_breadcrumbs')) ssf_breadcrumbs(); ?>
 */
if (!function_exists('ssf_breadcrumbs')) {
    function ssf_breadcrumbs($args = []) {
        $breadcrumbs = new SSF_Breadcrumbs();
        echo $breadcrumbs->render($args);
    }
}
