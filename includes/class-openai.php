<?php
/**
 * OpenAI Integration Class
 * 
 * Handles all AI-powered SEO suggestions and content generation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_OpenAI {
    
    /**
     * API endpoint
     */
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Get API key
     */
    private function get_api_key() {
        return Smart_SEO_Fixer::get_option('openai_api_key');
    }
    
    /**
     * Get model
     */
    private function get_model() {
        return Smart_SEO_Fixer::get_option('openai_model', 'gpt-4o-mini');
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->get_api_key());
    }
    
    /**
     * Make API request
     */
    public function request($messages, $max_tokens = 500, $temperature = 0.7) {
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'smart-seo-fixer'));
        }
        
        $api_url = $this->api_url;
        $model   = $this->get_model();
        
        // Wrap the actual API call in the rate limiter for throttling + retry
        $make_request = function() use ($api_key, $api_url, $model, $messages, $max_tokens, $temperature) {
            $response = wp_remote_post($api_url, [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $max_tokens,
                    'temperature' => $temperature,
                ]),
            ]);
            
            if (is_wp_error($response)) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::error('OpenAI request failed: ' . $response->get_error_message(), 'ai');
                }
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::error('OpenAI API error: ' . $data['error']['message'], 'ai', [
                        'model' => $model,
                    ]);
                }
                return new WP_Error('api_error', $data['error']['message']);
            }
            
            if (isset($data['choices'][0]['message']['content'])) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::debug('OpenAI request successful', 'ai', [
                        'model'  => $model,
                        'tokens' => $data['usage']['total_tokens'] ?? null,
                    ]);
                }
                return $data['choices'][0]['message']['content'];
            }
            
            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('OpenAI returned invalid response', 'ai');
            }
            return new WP_Error('invalid_response', __('Invalid response from OpenAI.', 'smart-seo-fixer'));
        };
        
        // Use rate limiter if available, otherwise call directly
        if (class_exists('SSF_Rate_Limiter')) {
            return SSF_Rate_Limiter::execute('openai', $make_request);
        }
        
        return $make_request();
    }
    
    /**
     * Generate SEO title
     */
    public function generate_title($content, $current_title = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Generate an optimized SEO title for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Maximum 60 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Make it compelling and click-worthy\n";
        $prompt .= "- Avoid clickbait, be accurate to the content\n\n";
        
        if (!empty($focus_keyword)) {
            $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        }
        
        if (!empty($current_title)) {
            $prompt .= "Current Title: {$current_title}\n\n";
        }
        
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n" . $clean_content . "\n\n";
        if (class_exists('SSF_Bedrock')) { $prompt .= SSF_Bedrock::grounding_rules(); }
        $prompt .= "Respond with ONLY the optimized title, nothing else.";

        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert that generates concise, optimized titles grounded only in the supplied content. You never invent facts.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $this->request($messages, 100, 0.3);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Strip surrounding quotes that AI sometimes adds
        $result = trim($result);
        $result = trim($result, '"\'');
        
        return $result;
    }
    
    /**
     * Generate meta description
     */
    public function generate_meta_description($content, $current_description = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Generate an optimized meta description for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Between 150-160 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Include a subtle call-to-action\n";
        $prompt .= "- Accurately summarize the content\n";
        $prompt .= "- Make it compelling to increase click-through rate\n\n";
        
        if (!empty($focus_keyword)) {
            $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        }
        
        if (!empty($current_description)) {
            $prompt .= "Current Description: {$current_description}\n\n";
        }
        
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n" . $clean_content . "\n\n";
        if (class_exists('SSF_Bedrock')) { $prompt .= SSF_Bedrock::grounding_rules(); }
        $prompt .= "Respond with ONLY the meta description, nothing else.";

        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert that generates concise, compelling meta descriptions grounded only in the supplied content. You never invent facts.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $this->request($messages, 200, 0.3);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Strip surrounding quotes that AI sometimes adds
        $result = trim($result);
        $result = trim($result, '"\'');
        
        return $result;
    }
    
    /**
     * The model ID in use, for display and diagnostics.
     */
    public function get_model_id() {
        return (string) $this->get_model();
    }

    /**
     * Whether the selected model accepts image input.
     *
     * The GPT-4o and GPT-4.1 families offered in Settings are all multimodal,
     * but a stored option can name an older text-only model, so those are
     * excluded explicitly and anything unrecognised is assumed capable.
     */
    public function supports_vision() {
        $model = strtolower($this->get_model());

        $text_only = ['gpt-3.5', 'gpt-3', 'text-davinci', 'davinci', 'curie', 'babbage', 'ada', 'o1-mini', 'o1-preview'];
        foreach ($text_only as $prefix) {
            if (strpos($model, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate image alt text using GPT-4 vision.
     *
     * Sends the actual image (as a base64 data URL) so the model can SEE
     * what's in it rather than guessing from the file slug. If vision is not
     * available this fails with SSF_AI::VISION_ERROR instead of asking a
     * blind model to describe a picture, which produces invented contents.
     */
    public function generate_alt_text($image_url, $page_context = '', $focus_keyword = '') {
        if (!class_exists('SSF_AI')) {
            return new WP_Error('ssf_missing_ai', __('AI factory unavailable.', 'smart-seo-fixer'));
        }

        if (!$this->supports_vision()) {
            return SSF_AI::vision_error(
                'model_not_vision',
                sprintf(
                    /* translators: %s: OpenAI model ID */
                    __('The selected OpenAI model (%s) cannot analyse images. Choose GPT-4o or GPT-4.1 to get real descriptions.', 'smart-seo-fixer'),
                    $this->get_model_id()
                )
            );
        }

        $img = SSF_AI::fetch_image_as_base64($image_url);
        if (is_wp_error($img)) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::warning('OpenAI alt-text: image bytes unreadable, skipping AI: ' . $img->get_error_message(), 'ai');
            }
            return SSF_AI::vision_error(
                'image_unreadable',
                __('The image file could not be read, so the AI had nothing to look at.', 'smart-seo-fixer'),
                $img->get_error_message()
            );
        }

        $instruction  = "Look at this image and write descriptive, SEO-friendly alt text for it.\n\n";
        $instruction .= "Requirements:\n- Maximum 125 characters\n- Describe what is actually visible in the image\n- Include the focus keyword naturally only if it genuinely describes the image\n- Do NOT start with 'Image of' or 'Picture of'\n- Useful for screen readers\n";
        if (!empty($page_context)) {
            $instruction .= "Page Context: " . wp_trim_words($page_context, 100) . "\n";
        }
        if (!empty($focus_keyword)) {
            $instruction .= "Focus Keyword: {$focus_keyword}\n";
        }
        $instruction .= "\nRespond with ONLY the alt text, nothing else.";

        // Send the image bytes inline so the model can actually see it.
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert that generates accessible, descriptive image alt text based on what you see in the image.'],
            [
                'role'    => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $instruction],
                    [
                        'type'      => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $img['media_type'] . ';base64,' . $img['data'],
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->request($messages, 150, 0.5);

        if (is_wp_error($result)) {
            return $result;
        }

        // Strip surrounding quotes that AI sometimes adds
        $result = trim($result);
        $result = trim($result, '"\'');

        return $result;
    }
    
    /**
     * Analyze content and suggest improvements
     */
    public function analyze_content($content, $title = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Analyze this content and provide specific, actionable SEO recommendations.\n\n";
        
        if (!empty($title)) {
            $prompt .= "Title: {$title}\n";
        }
        
        if (!empty($focus_keyword)) {
            $prompt .= "Focus Keyword: {$focus_keyword}\n";
        }
        
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 1000);
        $prompt .= "\nContent:\n" . $clean_content . "\n\n";
        
        $prompt .= "Analyze and provide feedback on:\n";
        $prompt .= "1. Keyword usage and placement\n";
        $prompt .= "2. Content structure (headings, paragraphs)\n";
        $prompt .= "3. Readability and engagement\n";
        $prompt .= "4. Content length and depth\n";
        $prompt .= "5. Internal/external linking opportunities\n\n";
        
        $prompt .= "Format your response as JSON with this structure:\n";
        $prompt .= '{"score": 0-100, "issues": ["issue1", "issue2"], "suggestions": ["suggestion1", "suggestion2"], "strengths": ["strength1", "strength2"]}';
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert. Respond only with valid JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $response = $this->request($messages, 1000, 0.5);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Clean JSON response
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Failed to parse AI response.', 'smart-seo-fixer'));
        }
        
        return $data;
    }
    
    /**
     * Generate content outline/structure
     */
    public function generate_outline($topic, $focus_keyword = '') {
        $prompt = "You are an SEO expert. Create a comprehensive content outline for this topic.\n\n";
        $prompt .= "Topic: {$topic}\n";
        
        if (!empty($focus_keyword)) {
            $prompt .= "Focus Keyword: {$focus_keyword}\n";
        }
        
        $prompt .= "\nGenerate an SEO-optimized content outline.\n\n";
        $prompt .= "IMPORTANT: Do NOT include prefixes like 'H1:', 'H2:', 'H3:' in the text. Just write the actual heading text.\n\n";
        $prompt .= "Format as JSON:\n";
        $prompt .= '{"title": "Your Compelling Title Here", "sections": [{"heading": "First Section Heading", "subsections": ["Sub Topic One", "Sub Topic Two"]}], "suggested_word_count": 1500}';
        $prompt .= "\n\nProvide 4-6 sections, each with 2-3 subsections. No descriptions needed.";
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO content strategist. Respond only with valid JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $response = $this->request($messages, 1500, 0.7);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Clean JSON response
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        return json_decode($response, true);
    }
    
    /**
     * Suggest focus keywords
     */
    public function suggest_keywords($content, $title = '') {
        $prompt = "You are an SEO keyword research expert. Suggest the best focus keywords for this content.\n\n";
        
        if (!empty($title)) {
            $prompt .= "Title: {$title}\n\n";
        }
        
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n" . $clean_content . "\n\n";
        
        $prompt .= "CRITICAL: Every keyword you suggest (primary, secondary, long-tail) MUST appear as a VERBATIM substring in the title or content above — case-insensitive. Do NOT invent new phrases. Pick keywords that are already in the text.\n\n";
        $prompt .= "Suggest 5 focus keywords with:\n";
        $prompt .= "- Primary keyword (main focus)\n";
        $prompt .= "- Secondary keywords (supporting)\n";
        $prompt .= "- Long-tail variations\n\n";
        
        $prompt .= "Format as JSON:\n";
        $prompt .= '{"primary": "keyword", "secondary": ["kw1", "kw2"], "long_tail": ["phrase1", "phrase2"]}';
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO keyword expert. Respond only with valid JSON. Every keyword must be a verbatim phrase from the supplied content — you never invent phrases.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = $this->request($messages, 500, 0.3);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Clean JSON response
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        return json_decode($response, true);
    }
    
    /**
     * Generate schema markup suggestions
     */
    public function suggest_schema($content, $post_type = 'article', $post_url = '', $site_url = '', $post_title = '', $site_name = '', $logo_url = '') {
        $prompt = "You are a structured data expert. Suggest additional Schema.org markup for this content.\n\n";
        $prompt .= "=== REAL SITE DATA (use these exact values, do NOT make up alternatives) ===\n";
        if (!empty($site_name)) {
            $prompt .= "Organization Name: {$site_name}\n";
        }
        if (!empty($site_url)) {
            $prompt .= "Site URL: {$site_url}\n";
        }
        if (!empty($logo_url)) {
            $prompt .= "Logo URL: {$logo_url}\n";
        }
        if (!empty($post_url)) {
            $prompt .= "Post URL: {$post_url}\n";
        }
        if (!empty($post_title)) {
            $prompt .= "Title: {$post_title}\n";
        }
        $prompt .= "Content Type: {$post_type}\n";
        $prompt .= "=== END SITE DATA ===\n\n";
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 300);
        $prompt .= "Content:\n" . $clean_content . "\n\n";
        
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Do NOT suggest Article, BlogPosting, NewsArticle, WebPage, BreadcrumbList, Organization, or WebSite schemas — these are ALREADY generated automatically by the plugin.\n";
        $prompt .= "2. Only suggest schemas that add NEW value. Good examples: Review, Product, Service, Recipe, Event, FAQPage, HowTo, Person, VideoObject, Course, SoftwareApplication, MedicalEntity, LegalService.\n";
        $prompt .= "3. Use ONLY the real URLs provided in the SITE DATA section above. NEVER guess or fabricate URLs like example.com, /logo.png, or any URL not explicitly provided.\n";
        $prompt .= "4. For any logo or image property, use ONLY the Logo URL from SITE DATA. If no logo URL was provided, omit the logo property entirely.\n";
        $prompt .= "5. Do NOT include articleBody — it's unnecessary when the full content is on the page.\n";
        $prompt .= "6. If the content is a client review/testimonial, use a Review schema.\n";
        $prompt .= "7. If there is truly no additional schema that would add value beyond Article, respond with exactly: {\"_no_schema\": true}\n\n";
        $prompt .= "Format as valid JSON-LD with @context and @type.";
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a Schema.org expert. Respond only with valid JSON-LD. Never use placeholder or example.com URLs.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        return $this->request($messages, 1000, 0.5);
    }
    
    /**
     * Find a natural place in existing content to insert an internal link to a target page.
     * Returns JSON with the exact text to find and replace.
     */
    public function find_internal_link_placement($source_content, $target_title, $target_url, $target_summary = '') {
        $clean_content = wp_trim_words(wp_strip_all_tags(strip_shortcodes($source_content)), 800);
        
        $prompt = "You are an SEO expert specializing in internal linking strategy.\n\n";
        $prompt .= "I need to add an internal link to a TARGET PAGE from within the SOURCE CONTENT below.\n\n";
        $prompt .= "TARGET PAGE TO LINK TO:\n";
        $prompt .= "- Title: {$target_title}\n";
        $prompt .= "- URL: {$target_url}\n";
        if (!empty($target_summary)) {
            $prompt .= "- Topic: {$target_summary}\n";
        }
        $prompt .= "\nSOURCE CONTENT (find a natural anchor text phrase in here):\n";
        $prompt .= $clean_content . "\n\n";
        
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Find an existing phrase (2-6 words) in the SOURCE CONTENT that naturally relates to the target page topic\n";
        $prompt .= "2. The phrase MUST exist EXACTLY as-is in the source content — copy it character-for-character\n";
        $prompt .= "3. The phrase must make sense as a link — a reader clicking it should expect to land on the target page\n";
        $prompt .= "4. Do NOT choose phrases that are already inside <a> tags or HTML attributes\n";
        $prompt .= "5. Prefer phrases in the middle or body of the content, not in headings\n";
        $prompt .= "6. The anchor text should be descriptive, not generic (avoid 'click here', 'read more', etc.)\n\n";
        
        $prompt .= "Respond with ONLY valid JSON in this exact format:\n";
        $prompt .= '{"found": true, "anchor_text": "the exact phrase from content", "context": "...surrounding sentence for verification..."}' . "\n";
        $prompt .= "If no natural fit exists, respond with: {\"found\": false}\n";
        $prompt .= "Do NOT wrap in code blocks. Just raw JSON.";
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an internal linking expert. Respond only with valid JSON. Never wrap in code blocks.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        $response = $this->request($messages, 300, 0.3);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Failed to parse AI response for internal link.', 'smart-seo-fixer'));
        }
        
        return $data;
    }
    
    /**
     * Improve readability of content
     */
    public function improve_readability($content) {
        $prompt = "You are a content editor. Improve the readability of this content while maintaining SEO value.\n\n";
        $prompt .= "Content:\n{$content}\n\n";
        
        $prompt .= "Improvements to make:\n";
        $prompt .= "- Shorter sentences where appropriate\n";
        $prompt .= "- Active voice\n";
        $prompt .= "- Clearer structure\n";
        $prompt .= "- Better transitions\n";
        $prompt .= "- Maintain all keywords and SEO elements\n\n";
        
        $prompt .= "Return ONLY the improved content, nothing else.";
        
        $messages = [
            ['role' => 'system', 'content' => 'You are an expert content editor focused on readability.'],
            ['role' => 'user', 'content' => $prompt],
        ];
        
        return $this->request($messages, 2000, 0.6);
    }
}

