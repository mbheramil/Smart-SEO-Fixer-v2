<?php
/**
 * SEO Analyzer Class
 * 
 * Analyzes posts/pages for SEO issues and calculates health scores.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Analyzer {
    
    /**
     * Analyze a single post
     */
    public function analyze_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('invalid_post', __('Post not found.', 'smart-seo-fixer'));
        }

        // Posts that are intentionally (or automatically) excluded from search
        // shouldn't count as having SEO issues — they're not supposed to rank.
        $noindex      = get_post_meta($post_id, '_ssf_noindex', true);
        $auto_noindex = get_post_meta($post_id, '_ssf_auto_noindex', true);
        if (!empty($noindex)) {
            $word_count = class_exists('SSF_Validator')
                ? SSF_Validator::get_content_word_count($post)
                : str_word_count(wp_strip_all_tags($post->post_content));
            $reason = $auto_noindex
                ? __('Automatically excluded from search — content is below the thin-content threshold (image-only or very short post).', 'smart-seo-fixer')
                : __('Manually excluded from search via the noindex setting.', 'smart-seo-fixer');
            $result = [
                'post_id'     => $post_id,
                'score'       => null,
                'grade'       => 'N/A',
                'noindex'     => true,
                'auto_noindex'=> (bool) $auto_noindex,
                'issues'      => [],
                'warnings'    => [],
                'passed'      => [[
                    'code'    => 'noindex_excluded',
                    'message' => $reason,
                ]],
                'stats'       => [
                    'word_count' => $word_count,
                ],
                'analyzed_at' => current_time('mysql'),
            ];
            $this->save_analysis($post_id, $result);
            return $result;
        }

        $content = $post->post_content;
        $title = $post->post_title;
        $seo_title = get_post_meta($post_id, '_ssf_seo_title', true) ?: $title;
        $meta_desc = get_post_meta($post_id, '_ssf_meta_description', true);
        $focus_keyword = get_post_meta($post_id, '_ssf_focus_keyword', true);
        
        $issues = [];
        $warnings = [];
        $passed = [];
        $score = 100;
        
        // Title Analysis
        $title_analysis = $this->analyze_title($seo_title, $focus_keyword);
        $issues = array_merge($issues, $title_analysis['issues']);
        $warnings = array_merge($warnings, $title_analysis['warnings']);
        $passed = array_merge($passed, $title_analysis['passed']);
        $score -= $title_analysis['deduction'];
        
        // Meta Description Analysis
        $meta_analysis = $this->analyze_meta_description($meta_desc, $focus_keyword);
        $issues = array_merge($issues, $meta_analysis['issues']);
        $warnings = array_merge($warnings, $meta_analysis['warnings']);
        $passed = array_merge($passed, $meta_analysis['passed']);
        $score -= $meta_analysis['deduction'];
        
        // Content Analysis
        $content_analysis = $this->analyze_content($content, $focus_keyword);
        $issues = array_merge($issues, $content_analysis['issues']);
        $warnings = array_merge($warnings, $content_analysis['warnings']);
        $passed = array_merge($passed, $content_analysis['passed']);
        $score -= $content_analysis['deduction'];
        
        // Heading Structure Analysis
        $heading_analysis = $this->analyze_headings($content);
        $issues = array_merge($issues, $heading_analysis['issues']);
        $warnings = array_merge($warnings, $heading_analysis['warnings']);
        $passed = array_merge($passed, $heading_analysis['passed']);
        $score -= $heading_analysis['deduction'];
        
        // Image Analysis
        $image_analysis = $this->analyze_images($content, $post_id);
        $issues = array_merge($issues, $image_analysis['issues']);
        $warnings = array_merge($warnings, $image_analysis['warnings']);
        $passed = array_merge($passed, $image_analysis['passed']);
        $score -= $image_analysis['deduction'];
        
        // Link Analysis
        $link_analysis = $this->analyze_links($content, $post_id);
        $issues = array_merge($issues, $link_analysis['issues']);
        $warnings = array_merge($warnings, $link_analysis['warnings']);
        $passed = array_merge($passed, $link_analysis['passed']);
        $score -= $link_analysis['deduction'];
        
        // URL/Slug Analysis
        $slug_analysis = $this->analyze_slug($post->post_name, $focus_keyword);
        $issues = array_merge($issues, $slug_analysis['issues']);
        $warnings = array_merge($warnings, $slug_analysis['warnings']);
        $passed = array_merge($passed, $slug_analysis['passed']);
        $score -= $slug_analysis['deduction'];
        
        // Readability Analysis
        $readability = $this->analyze_readability($content);
        $issues = array_merge($issues, $readability['issues']);
        $warnings = array_merge($warnings, $readability['warnings']);
        $passed = array_merge($passed, $readability['passed']);
        $score -= $readability['deduction'];
        
        // Ensure score is between 0-100
        $score = max(0, min(100, $score));
        
        $text = strip_tags($content);
        
        $result = [
            'post_id' => $post_id,
            'score' => $score,
            'grade' => $this->get_grade($score),
            'issues' => $issues,
            'warnings' => $warnings,
            'passed' => $passed,
            'stats' => [
                'word_count' => str_word_count($text),
                'character_count' => strlen($text),
                'title_length' => strlen($seo_title),
                'meta_length' => strlen($meta_desc),
                'image_count' => $this->count_images($content),
                'link_count' => $this->count_links($content),
                'flesch_score' => $readability['flesch_score'] ?? 0,
                'avg_sentence_length' => $readability['avg_sentence_length'] ?? 0,
                'passive_voice_pct' => $readability['passive_voice_pct'] ?? 0,
                'reading_level' => $readability['reading_level'] ?? '',
            ],
            'analyzed_at' => current_time('mysql'),
        ];
        
        // Save to database
        $this->save_analysis($post_id, $result);
        
        return $result;
    }
    
    /**
     * Analyze title
     */
    private function analyze_title($title, $focus_keyword = '') {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        $length = strlen($title);
        
        // Check if title exists
        if (empty($title)) {
            $result['issues'][] = [
                'code' => 'no_title',
                'message' => __('No SEO title set.', 'smart-seo-fixer'),
                'fix' => __('Add an SEO title between 50-60 characters.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 15;
            return $result;
        }
        
        // Check title length
        if ($length < 30) {
            $result['issues'][] = [
                'code' => 'title_too_short',
                'message' => sprintf(__('Title is too short (%d characters). Aim for 50-60 characters.', 'smart-seo-fixer'), $length),
                'fix' => __('Expand your title to be more descriptive.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 10;
        } elseif ($length > 60) {
            $result['warnings'][] = [
                'code' => 'title_too_long',
                'message' => sprintf(__('Title may be truncated in search results (%d characters). Keep under 60.', 'smart-seo-fixer'), $length),
                'fix' => __('Shorten your title to prevent truncation.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } else {
            $result['passed'][] = [
                'code' => 'title_length_good',
                'message' => __('Title length is optimal.', 'smart-seo-fixer'),
            ];
        }
        
        // Check for focus keyword in title
        if (!empty($focus_keyword)) {
            if (stripos($title, $focus_keyword) !== false) {
                // Check if keyword is at the beginning
                if (stripos($title, $focus_keyword) < 20) {
                    $result['passed'][] = [
                        'code' => 'keyword_in_title_front',
                        'message' => __('Focus keyword appears near the beginning of the title.', 'smart-seo-fixer'),
                    ];
                } else {
                    $result['warnings'][] = [
                        'code' => 'keyword_not_front',
                        'message' => __('Focus keyword should appear closer to the beginning of the title.', 'smart-seo-fixer'),
                        'fix' => __('Move your focus keyword to the front of the title.', 'smart-seo-fixer'),
                    ];
                    $result['deduction'] += 3;
                }
            } else {
                $result['issues'][] = [
                    'code' => 'keyword_not_in_title',
                    'message' => __('Focus keyword not found in title.', 'smart-seo-fixer'),
                    'fix' => __('Include your focus keyword in the title.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 10;
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze meta description
     */
    private function analyze_meta_description($description, $focus_keyword = '') {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        $length = strlen($description);
        
        // Check if description exists
        if (empty($description)) {
            $result['issues'][] = [
                'code' => 'no_meta_description',
                'message' => __('No meta description set. Search engines will auto-generate one.', 'smart-seo-fixer'),
                'fix' => __('Add a meta description between 150-160 characters.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 15;
            return $result;
        }
        
        // Check description length
        if ($length < 120) {
            $result['warnings'][] = [
                'code' => 'meta_too_short',
                'message' => sprintf(__('Meta description is short (%d characters). Aim for 150-160.', 'smart-seo-fixer'), $length),
                'fix' => __('Expand your meta description to fully utilize the space.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } elseif ($length > 160) {
            $result['warnings'][] = [
                'code' => 'meta_too_long',
                'message' => sprintf(__('Meta description may be truncated (%d characters). Keep under 160.', 'smart-seo-fixer'), $length),
                'fix' => __('Shorten your meta description to prevent truncation.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } else {
            $result['passed'][] = [
                'code' => 'meta_length_good',
                'message' => __('Meta description length is optimal.', 'smart-seo-fixer'),
            ];
        }
        
        // Check for focus keyword
        if (!empty($focus_keyword)) {
            if (stripos($description, $focus_keyword) !== false) {
                $result['passed'][] = [
                    'code' => 'keyword_in_meta',
                    'message' => __('Focus keyword found in meta description.', 'smart-seo-fixer'),
                ];
            } else {
                $result['warnings'][] = [
                    'code' => 'keyword_not_in_meta',
                    'message' => __('Focus keyword not found in meta description.', 'smart-seo-fixer'),
                    'fix' => __('Include your focus keyword in the meta description.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 5;
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze content
     */
    private function analyze_content($content, $focus_keyword = '') {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        $text = strip_tags($content);
        $word_count = str_word_count($text);
        
        // Check content length
        if ($word_count < 300) {
            $result['issues'][] = [
                'code' => 'content_too_short',
                'message' => sprintf(__('Content is too short (%d words). Aim for at least 300 words.', 'smart-seo-fixer'), $word_count),
                'fix' => __('Add more comprehensive content to improve rankings.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 15;
        } elseif ($word_count < 600) {
            $result['warnings'][] = [
                'code' => 'content_short',
                'message' => sprintf(__('Content is relatively short (%d words). Consider expanding.', 'smart-seo-fixer'), $word_count),
                'fix' => __('Longer, comprehensive content tends to rank better.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } else {
            $result['passed'][] = [
                'code' => 'content_length_good',
                'message' => sprintf(__('Content length is good (%d words).', 'smart-seo-fixer'), $word_count),
            ];
        }
        
        // Check for focus keyword
        if (!empty($focus_keyword)) {
            $keyword_count = substr_count(strtolower($text), strtolower($focus_keyword));
            $density = ($word_count > 0) ? ($keyword_count / $word_count) * 100 : 0;
            
            if ($keyword_count === 0) {
                $result['issues'][] = [
                    'code' => 'keyword_not_in_content',
                    'message' => __('Focus keyword not found in content.', 'smart-seo-fixer'),
                    'fix' => __('Use your focus keyword naturally throughout the content.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 15;
            } elseif ($density < 0.5) {
                $result['warnings'][] = [
                    'code' => 'keyword_density_low',
                    'message' => sprintf(__('Keyword density is low (%.1f%%). Aim for 1-2%%.', 'smart-seo-fixer'), $density),
                    'fix' => __('Use your focus keyword a few more times naturally.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 5;
            } elseif ($density > 3) {
                $result['warnings'][] = [
                    'code' => 'keyword_density_high',
                    'message' => sprintf(__('Keyword density is high (%.1f%%). This may look like keyword stuffing.', 'smart-seo-fixer'), $density),
                    'fix' => __('Reduce keyword usage to appear more natural.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 10;
            } else {
                $result['passed'][] = [
                    'code' => 'keyword_density_good',
                    'message' => sprintf(__('Keyword density is good (%.1f%%).', 'smart-seo-fixer'), $density),
                ];
            }
            
            // Check first paragraph
            $paragraphs = preg_split('/\n\s*\n/', $text);
            $first_para = isset($paragraphs[0]) ? $paragraphs[0] : '';
            
            if (stripos($first_para, $focus_keyword) !== false) {
                $result['passed'][] = [
                    'code' => 'keyword_in_intro',
                    'message' => __('Focus keyword found in the introduction.', 'smart-seo-fixer'),
                ];
            } else {
                $result['warnings'][] = [
                    'code' => 'keyword_not_in_intro',
                    'message' => __('Focus keyword not found in the first paragraph.', 'smart-seo-fixer'),
                    'fix' => __('Include your focus keyword in the opening paragraph.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 5;
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze heading structure
     */
    private function analyze_headings($content) {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        // Count headings
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $content, $matches);
        
        $heading_counts = [
            'h1' => 0,
            'h2' => 0,
            'h3' => 0,
            'h4' => 0,
            'h5' => 0,
            'h6' => 0,
        ];
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $level) {
                $heading_counts['h' . $level]++;
            }
        }
        
        // Check H1
        if ($heading_counts['h1'] > 1) {
            $result['issues'][] = [
                'code' => 'multiple_h1',
                'message' => sprintf(__('Multiple H1 tags found (%d). Use only one H1 per page.', 'smart-seo-fixer'), $heading_counts['h1']),
                'fix' => __('Convert extra H1 tags to H2 or other appropriate headings.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 10;
        }
        
        // Check for subheadings
        if ($heading_counts['h2'] === 0) {
            $result['warnings'][] = [
                'code' => 'no_subheadings',
                'message' => __('No H2 subheadings found. Use headings to structure content.', 'smart-seo-fixer'),
                'fix' => __('Add H2 subheadings to break up your content.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } else {
            $result['passed'][] = [
                'code' => 'has_subheadings',
                'message' => sprintf(__('Good use of subheadings (%d H2 tags).', 'smart-seo-fixer'), $heading_counts['h2']),
            ];
        }
        
        // Check heading hierarchy
        $has_h3_without_h2 = $heading_counts['h3'] > 0 && $heading_counts['h2'] === 0;
        $has_h4_without_h3 = $heading_counts['h4'] > 0 && $heading_counts['h3'] === 0;
        
        if ($has_h3_without_h2 || $has_h4_without_h3) {
            $result['warnings'][] = [
                'code' => 'heading_hierarchy',
                'message' => __('Heading hierarchy is not sequential.', 'smart-seo-fixer'),
                'fix' => __('Use headings in order: H1 → H2 → H3, etc.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        }
        
        return $result;
    }
    
    /**
     * Analyze images
     */
    private function analyze_images($content, $post_id) {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        // Find all images
        preg_match_all('/<img[^>]+>/i', $content, $images);
        
        $total_images = count($images[0]);
        $images_without_alt = 0;
        $images_with_empty_alt = 0;
        
        foreach ($images[0] as $img) {
            if (!preg_match('/alt\s*=/i', $img)) {
                $images_without_alt++;
            } elseif (preg_match('/alt\s*=\s*["\']["\']/', $img)) {
                $images_with_empty_alt++;
            }
        }
        
        // Check featured image
        $featured_id = get_post_thumbnail_id($post_id);
        if (!$featured_id) {
            $result['warnings'][] = [
                'code' => 'no_featured_image',
                'message' => __('No featured image set.', 'smart-seo-fixer'),
                'fix' => __('Add a featured image to improve social sharing and visual appeal.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        } else {
            $result['passed'][] = [
                'code' => 'has_featured_image',
                'message' => __('Featured image is set.', 'smart-seo-fixer'),
            ];
            
            // Check featured image alt
            $featured_alt = get_post_meta($featured_id, '_wp_attachment_image_alt', true);
            if (empty($featured_alt)) {
                $result['warnings'][] = [
                    'code' => 'featured_no_alt',
                    'message' => __('Featured image has no alt text.', 'smart-seo-fixer'),
                    'fix' => __('Add descriptive alt text to your featured image.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 3;
            }
        }
        
        // Report on content images
        if ($total_images === 0) {
            $result['warnings'][] = [
                'code' => 'no_content_images',
                'message' => __('No images in content. Images can improve engagement.', 'smart-seo-fixer'),
                'fix' => __('Consider adding relevant images to your content.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 2;
        } else {
            if ($images_without_alt > 0 || $images_with_empty_alt > 0) {
                $missing = $images_without_alt + $images_with_empty_alt;
                $result['issues'][] = [
                    'code' => 'images_missing_alt',
                    'message' => sprintf(__('%d of %d images are missing alt text.', 'smart-seo-fixer'), $missing, $total_images),
                    'fix' => __('Add descriptive alt text to all images.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += min(15, $missing * 3);
            } else {
                $result['passed'][] = [
                    'code' => 'all_images_have_alt',
                    'message' => __('All images have alt text.', 'smart-seo-fixer'),
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze links
     */
    private function analyze_links($content, $post_id) {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        $site_url = home_url();
        
        // Find all links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
        
        $internal_links = 0;
        $external_links = 0;
        $broken_links = [];
        
        if (!empty($links[1])) {
            foreach ($links[1] as $url) {
                if (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) {
                    $internal_links++;
                } elseif (strpos($url, 'http') === 0) {
                    $external_links++;
                }
            }
        }
        
        // Check internal links
        if ($internal_links === 0) {
            $result['warnings'][] = [
                'code' => 'no_internal_links',
                'message' => __('No internal links found. Internal linking helps SEO.', 'smart-seo-fixer'),
                'fix' => __('Add links to related content on your site.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 5;
        } else {
            $result['passed'][] = [
                'code' => 'has_internal_links',
                'message' => sprintf(__('%d internal links found.', 'smart-seo-fixer'), $internal_links),
            ];
        }
        
        // Check external links
        if ($external_links === 0) {
            $result['warnings'][] = [
                'code' => 'no_external_links',
                'message' => __('No external links found. Linking to authoritative sources can help.', 'smart-seo-fixer'),
                'fix' => __('Consider linking to relevant, authoritative external resources.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 2;
        } else {
            $result['passed'][] = [
                'code' => 'has_external_links',
                'message' => sprintf(__('%d external links found.', 'smart-seo-fixer'), $external_links),
            ];
        }
        
        return $result;
    }
    
    /**
     * Analyze URL/slug
     */
    private function analyze_slug($slug, $focus_keyword = '') {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
        ];
        
        // Check slug length
        if (strlen($slug) > 75) {
            $result['warnings'][] = [
                'code' => 'slug_too_long',
                'message' => __('URL slug is quite long. Shorter URLs are better for SEO.', 'smart-seo-fixer'),
                'fix' => __('Shorten the URL slug while keeping key words.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        } else {
            $result['passed'][] = [
                'code' => 'slug_length_good',
                'message' => __('URL length is good.', 'smart-seo-fixer'),
            ];
        }
        
        // Check for stop words
        $stop_words = ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
        $slug_words = explode('-', $slug);
        $found_stop_words = array_intersect($slug_words, $stop_words);
        
        if (count($found_stop_words) > 2) {
            $result['warnings'][] = [
                'code' => 'slug_stop_words',
                'message' => __('URL contains unnecessary stop words.', 'smart-seo-fixer'),
                'fix' => __('Remove words like "the", "and", "in" from the URL.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 2;
        }
        
        // Check for focus keyword
        if (!empty($focus_keyword)) {
            $keyword_slug = sanitize_title($focus_keyword);
            if (strpos($slug, $keyword_slug) !== false) {
                $result['passed'][] = [
                    'code' => 'keyword_in_slug',
                    'message' => __('Focus keyword found in URL.', 'smart-seo-fixer'),
                ];
            } else {
                $result['warnings'][] = [
                    'code' => 'keyword_not_in_slug',
                    'message' => __('Focus keyword not found in URL.', 'smart-seo-fixer'),
                    'fix' => __('Consider including your focus keyword in the URL slug.', 'smart-seo-fixer'),
                ];
                $result['deduction'] += 5;
            }
        }
        
        return $result;
    }
    
    /**
     * Analyze content readability
     */
    private function analyze_readability($content) {
        $result = [
            'issues' => [],
            'warnings' => [],
            'passed' => [],
            'deduction' => 0,
            'flesch_score' => 0,
            'avg_sentence_length' => 0,
            'passive_voice_pct' => 0,
            'reading_level' => '',
        ];
        
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (strlen($text) < 100) {
            return $result; // Too short to analyze meaningfully
        }
        
        // --- Count syllables, words, sentences ---
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $word_count = count($words);
        
        if ($word_count < 10) {
            return $result;
        }
        
        // Count sentences (split by . ! ? and filter empties)
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter($sentences, function($s) { return strlen(trim($s)) > 2; });
        $sentence_count = max(1, count($sentences));
        
        // Count syllables
        $total_syllables = 0;
        foreach ($words as $word) {
            $total_syllables += $this->count_syllables($word);
        }
        
        // --- Flesch Reading Ease ---
        $avg_sentence_length = $word_count / $sentence_count;
        $avg_syllables_per_word = $total_syllables / $word_count;
        $flesch = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        $flesch = max(0, min(100, round($flesch, 1)));
        
        $result['flesch_score'] = $flesch;
        $result['avg_sentence_length'] = round($avg_sentence_length, 1);
        
        // Determine reading level
        if ($flesch >= 80) {
            $result['reading_level'] = __('Very Easy', 'smart-seo-fixer');
        } elseif ($flesch >= 70) {
            $result['reading_level'] = __('Easy', 'smart-seo-fixer');
        } elseif ($flesch >= 60) {
            $result['reading_level'] = __('Standard', 'smart-seo-fixer');
        } elseif ($flesch >= 50) {
            $result['reading_level'] = __('Fairly Difficult', 'smart-seo-fixer');
        } elseif ($flesch >= 30) {
            $result['reading_level'] = __('Difficult', 'smart-seo-fixer');
        } else {
            $result['reading_level'] = __('Very Difficult', 'smart-seo-fixer');
        }
        
        // Score the Flesch result
        if ($flesch >= 60) {
            $result['passed'][] = [
                'code' => 'readability_good',
                'message' => sprintf(__('Readability is good (Flesch score: %s — %s).', 'smart-seo-fixer'), $flesch, $result['reading_level']),
            ];
        } elseif ($flesch >= 40) {
            $result['warnings'][] = [
                'code' => 'readability_moderate',
                'message' => sprintf(__('Content is fairly difficult to read (Flesch score: %s). Aim for 60+.', 'smart-seo-fixer'), $flesch),
                'fix' => __('Use shorter sentences and simpler words.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        } else {
            $result['issues'][] = [
                'code' => 'readability_poor',
                'message' => sprintf(__('Content is very difficult to read (Flesch score: %s). This may hurt engagement.', 'smart-seo-fixer'), $flesch),
                'fix' => __('Simplify language, break up long sentences, and use common words.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 7;
        }
        
        // --- Average sentence length ---
        if ($avg_sentence_length > 25) {
            $result['warnings'][] = [
                'code' => 'sentences_too_long',
                'message' => sprintf(__('Average sentence length is %d words. Aim for under 20.', 'smart-seo-fixer'), round($avg_sentence_length)),
                'fix' => __('Break long sentences into shorter, clearer ones.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        } elseif ($avg_sentence_length <= 20) {
            $result['passed'][] = [
                'code' => 'sentence_length_good',
                'message' => sprintf(__('Average sentence length is good (%d words).', 'smart-seo-fixer'), round($avg_sentence_length)),
            ];
        }
        
        // --- Passive voice detection ---
        $passive_count = $this->count_passive_voice($text);
        $passive_pct = ($sentence_count > 0) ? round(($passive_count / $sentence_count) * 100, 1) : 0;
        $result['passive_voice_pct'] = $passive_pct;
        
        if ($passive_pct > 30) {
            $result['warnings'][] = [
                'code' => 'too_much_passive',
                'message' => sprintf(__('%.0f%% of sentences use passive voice. Aim for under 15%%.', 'smart-seo-fixer'), $passive_pct),
                'fix' => __('Rewrite passive sentences to active voice (e.g., "The ball was thrown" → "He threw the ball").', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 3;
        } elseif ($passive_pct <= 15) {
            $result['passed'][] = [
                'code' => 'passive_voice_good',
                'message' => sprintf(__('Good use of active voice (%.0f%% passive).', 'smart-seo-fixer'), $passive_pct),
            ];
        }
        
        // --- Paragraph length ---
        $paragraphs = preg_split('/\n\s*\n|\<\/p\>\s*\<p|\<br\s*\/?\>\s*\<br/', $content);
        $long_paragraphs = 0;
        foreach ($paragraphs as $para) {
            $para_text = strip_tags($para);
            $para_words = str_word_count($para_text);
            if ($para_words > 150) {
                $long_paragraphs++;
            }
        }
        
        if ($long_paragraphs > 0) {
            $result['warnings'][] = [
                'code' => 'long_paragraphs',
                'message' => sprintf(__('%d paragraph(s) are too long (150+ words). Break them up for better readability.', 'smart-seo-fixer'), $long_paragraphs),
                'fix' => __('Split long paragraphs into 2-3 shorter ones. Online readers prefer short, scannable blocks.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 2;
        } else {
            $result['passed'][] = [
                'code' => 'paragraph_length_good',
                'message' => __('Paragraph lengths are reader-friendly.', 'smart-seo-fixer'),
            ];
        }
        
        // --- Transition words ---
        $transition_count = $this->count_transition_words($text);
        $transition_pct = ($sentence_count > 0) ? round(($transition_count / $sentence_count) * 100, 1) : 0;
        
        if ($transition_pct < 20 && $sentence_count > 5) {
            $result['warnings'][] = [
                'code' => 'few_transition_words',
                'message' => sprintf(__('Only %.0f%% of sentences contain transition words. Aim for 30%%+.', 'smart-seo-fixer'), $transition_pct),
                'fix' => __('Use words like "however", "therefore", "for example", "additionally" to improve flow.', 'smart-seo-fixer'),
            ];
            $result['deduction'] += 2;
        } elseif ($transition_pct >= 30) {
            $result['passed'][] = [
                'code' => 'transition_words_good',
                'message' => sprintf(__('Good use of transition words (%.0f%% of sentences).', 'smart-seo-fixer'), $transition_pct),
            ];
        }
        
        return $result;
    }
    
    /**
     * Count syllables in a word (English approximation)
     */
    private function count_syllables($word) {
        $word = strtolower(trim($word));
        $word = preg_replace('/[^a-z]/', '', $word);
        
        if (strlen($word) <= 2) {
            return 1;
        }
        
        // Remove trailing e (silent)
        $word = preg_replace('/e$/', '', $word);
        
        // Count vowel groups
        preg_match_all('/[aeiouy]+/', $word, $matches);
        $count = count($matches[0]);
        
        return max(1, $count);
    }
    
    /**
     * Count sentences with passive voice
     */
    private function count_passive_voice($text) {
        $passive_patterns = [
            '/\b(is|are|was|were|been|be|being)\s+(being\s+)?\w+ed\b/i',
            '/\b(is|are|was|were|been|be|being)\s+(being\s+)?\w+en\b/i',
            '/\b(got|get|gets|getting)\s+\w+ed\b/i',
        ];
        
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $passive_count = 0;
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 5) continue;
            
            foreach ($passive_patterns as $pattern) {
                if (preg_match($pattern, $sentence)) {
                    $passive_count++;
                    break; // Count each sentence only once
                }
            }
        }
        
        return $passive_count;
    }
    
    /**
     * Count sentences containing transition words
     */
    private function count_transition_words($text) {
        $transitions = [
            'additionally', 'also', 'moreover', 'furthermore', 'besides',
            'however', 'nevertheless', 'nonetheless', 'although', 'though',
            'therefore', 'consequently', 'thus', 'hence', 'accordingly',
            'for example', 'for instance', 'such as', 'specifically', 'in particular',
            'in addition', 'as a result', 'on the other hand', 'in contrast',
            'meanwhile', 'similarly', 'likewise', 'in fact', 'indeed',
            'first', 'second', 'third', 'finally', 'next', 'then',
            'in conclusion', 'to summarize', 'overall', 'in summary',
            'because', 'since', 'while', 'whereas', 'unless',
            'instead', 'rather', 'otherwise', 'still', 'yet',
        ];
        
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $count = 0;
        
        foreach ($sentences as $sentence) {
            $sentence_lower = strtolower(trim($sentence));
            if (strlen($sentence_lower) < 5) continue;
            
            foreach ($transitions as $word) {
                if (strpos($sentence_lower, $word) !== false) {
                    $count++;
                    break;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get grade from score
     */
    private function get_grade($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Count images in content
     */
    private function count_images($content) {
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        return count($matches[0]);
    }
    
    /**
     * Count links in content
     */
    private function count_links($content) {
        preg_match_all('/<a[^>]+>/i', $content, $matches);
        return count($matches[0]);
    }
    
    /**
     * Save analysis to database
     */
    private function save_analysis($post_id, $result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        $wpdb->replace($table, [
            'post_id' => $post_id,
            'score' => $result['score'],
            'issues' => wp_json_encode($result['issues']),
            'suggestions' => wp_json_encode([
                'warnings' => $result['warnings'],
                'passed' => $result['passed'],
            ]),
            'last_analyzed' => $result['analyzed_at'],
        ]);
        
        // Also save score to post meta for quick access
        update_post_meta($post_id, '_ssf_seo_score', $result['score']);
        update_post_meta($post_id, '_ssf_seo_grade', $result['grade']);
    }
    
    /**
     * Get saved analysis
     */
    public function get_analysis($post_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d",
            $post_id
        ));
    }
    
    /**
     * Bulk analyze posts
     */
    public function bulk_analyze($post_ids) {
        $results = [];
        
        foreach ($post_ids as $post_id) {
            $results[$post_id] = $this->analyze_post($post_id);
        }
        
        return $results;
    }
    
    /**
     * Get overall site score
     */
    public function get_site_score() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        $avg = $wpdb->get_var("SELECT AVG(score) FROM $table");
        
        return $avg ? round($avg) : 0;
    }
    
    /**
     * Get posts by score range
     */
    public function get_posts_by_score($min = 0, $max = 100, $limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ssf_seo_scores';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE score >= %d AND score <= %d ORDER BY score ASC LIMIT %d",
            $min, $max, $limit
        ));
    }
    
    /**
     * Detect duplicate SEO titles and meta descriptions across all published posts.
     * 
     * Returns grouped arrays of posts sharing the same title or description.
     * 
     * @return array ['duplicate_titles' => [...], 'duplicate_descriptions' => [...]]
     */
    public function detect_duplicates() {
        global $wpdb;
        
        $post_types = Smart_SEO_Fixer::get_option('post_types', ['post', 'page']);
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        
        // Find duplicate SEO titles
        $dup_titles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS seo_title, GROUP_CONCAT(p.ID) AS post_ids, COUNT(*) as cnt
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_ssf_seo_title'
                 AND pm.meta_value != ''
                 AND p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 GROUP BY pm.meta_value
                 HAVING cnt > 1
                 ORDER BY cnt DESC
                 LIMIT 50",
                ...$post_types
            )
        );
        
        // Find duplicate meta descriptions
        $dup_descs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value AS meta_description, GROUP_CONCAT(p.ID) AS post_ids, COUNT(*) as cnt
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_ssf_meta_description'
                 AND pm.meta_value != ''
                 AND p.post_status = 'publish'
                 AND p.post_type IN ($placeholders)
                 GROUP BY pm.meta_value
                 HAVING cnt > 1
                 ORDER BY cnt DESC
                 LIMIT 50",
                ...$post_types
            )
        );
        
        // Also check posts sharing the same wp post_title (even without SEO title)
        $dup_post_titles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_title, GROUP_CONCAT(ID) AS post_ids, COUNT(*) as cnt
                 FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                 AND post_type IN ($placeholders)
                 AND post_title != ''
                 GROUP BY post_title
                 HAVING cnt > 1
                 ORDER BY cnt DESC
                 LIMIT 50",
                ...$post_types
            )
        );
        
        // Enrich with post details
        $title_groups = [];
        foreach ($dup_titles as $group) {
            $ids = array_map('intval', explode(',', $group->post_ids));
            $posts = [];
            foreach ($ids as $id) {
                $posts[] = [
                    'post_id' => $id,
                    'title'   => get_the_title($id),
                    'url'     => get_permalink($id),
                ];
            }
            $title_groups[] = [
                'seo_title' => $group->seo_title,
                'count'     => intval($group->cnt),
                'posts'     => $posts,
            ];
        }
        
        $desc_groups = [];
        foreach ($dup_descs as $group) {
            $ids = array_map('intval', explode(',', $group->post_ids));
            $posts = [];
            foreach ($ids as $id) {
                $posts[] = [
                    'post_id' => $id,
                    'title'   => get_the_title($id),
                    'url'     => get_permalink($id),
                ];
            }
            $desc_groups[] = [
                'meta_description' => mb_substr($group->meta_description, 0, 160),
                'count'            => intval($group->cnt),
                'posts'            => $posts,
            ];
        }
        
        $post_title_groups = [];
        foreach ($dup_post_titles as $group) {
            $ids = array_map('intval', explode(',', $group->post_ids));
            $posts = [];
            foreach ($ids as $id) {
                $posts[] = [
                    'post_id' => $id,
                    'url'     => get_permalink($id),
                ];
            }
            $post_title_groups[] = [
                'post_title' => $group->post_title,
                'count'      => intval($group->cnt),
                'posts'      => $posts,
            ];
        }
        
        return [
            'duplicate_seo_titles'       => $title_groups,
            'duplicate_descriptions'     => $desc_groups,
            'duplicate_post_titles'      => $post_title_groups,
            'total_duplicate_titles'     => count($title_groups),
            'total_duplicate_descs'      => count($desc_groups),
            'total_duplicate_post_titles'=> count($post_title_groups),
        ];
    }
}

