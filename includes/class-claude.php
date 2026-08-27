<?php
/**
 * Anthropic Claude Direct API Integration
 *
 * Handles AI-powered SEO via the Anthropic Messages API.
 * Same interface as SSF_Bedrock and SSF_OpenAI.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Claude {

    private $api_url = 'https://api.anthropic.com/v1/messages';

    private function get_api_key() {
        return Smart_SEO_Fixer::get_option('claude_api_key');
    }

    private function get_model() {
        return Smart_SEO_Fixer::get_option('claude_model', 'claude-sonnet-4-20250514');
    }

    public function is_configured() {
        return !empty($this->get_api_key());
    }

    /**
     * Make API request to Anthropic Messages API.
     * Accepts OpenAI-style messages array (system/user/assistant roles).
     */
    public function request($messages, $max_tokens = 500, $temperature = 0.7) {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Anthropic API key not configured.', 'smart-seo-fixer'));
        }

        // Anthropic separates the system prompt from the messages array
        $system = '';
        $api_messages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $api_messages[] = [
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        $model = $this->get_model();

        $make_request = function () use ($api_key, $model, $system, $api_messages, $max_tokens, $temperature) {
            $body = [
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'temperature'=> $temperature,
                'messages'   => $api_messages,
            ];

            if (!empty($system)) {
                $body['system'] = $system;
            }

            $response = wp_remote_post($this->api_url, [
                'timeout' => 60,
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version'  => '2023-06-01',
                    'Content-Type'       => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]);

            if (is_wp_error($response)) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::error('Claude request failed: ' . $response->get_error_message(), 'ai');
                }
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $raw    = wp_remote_retrieve_body($response);
            $data   = json_decode($raw, true);

            if (isset($data['error'])) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::error('Claude API error: ' . ($data['error']['message'] ?? 'Unknown'), 'ai', ['model' => $model]);
                }
                return new WP_Error('api_error', $data['error']['message'] ?? __('Unknown Claude API error.', 'smart-seo-fixer'));
            }

            if ($status >= 400) {
                return new WP_Error('api_error', sprintf(__('Claude API returned HTTP %d.', 'smart-seo-fixer'), $status));
            }

            // Anthropic returns content as an array of blocks
            if (isset($data['content'][0]['text'])) {
                if (class_exists('SSF_Logger')) {
                    SSF_Logger::debug('Claude request successful', 'ai', [
                        'model'  => $model,
                        'tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                    ]);
                }
                return $data['content'][0]['text'];
            }

            if (class_exists('SSF_Logger')) {
                SSF_Logger::error('Claude returned invalid response', 'ai');
            }
            return new WP_Error('invalid_response', __('Invalid response from Claude.', 'smart-seo-fixer'));
        };

        if (class_exists('SSF_Rate_Limiter')) {
            return SSF_Rate_Limiter::execute('claude', $make_request);
        }

        return $make_request();
    }

    /* ─── High-level SEO methods (identical interface to SSF_OpenAI / SSF_Bedrock) ─── */

    public function generate_title($content, $current_title = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Generate an optimized SEO title for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Maximum 60 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Make it compelling and click-worthy\n";
        $prompt .= "- Avoid clickbait, be accurate to the content\n\n";
        if (!empty($focus_keyword)) $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if (!empty($current_title)) $prompt .= "Current Title: {$current_title}\n\n";
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n{$clean}\n\nRespond with ONLY the optimized title, nothing else.";

        $result = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO expert that generates concise, optimized titles.'],
            ['role' => 'user',   'content' => $prompt],
        ], 100, 0.7);

        return is_wp_error($result) ? $result : trim(trim($result), '"\'');
    }

    public function generate_meta_description($content, $current_description = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Generate an optimized meta description for the following content.\n\n";
        $prompt .= "Requirements:\n- Between 150-160 characters (critical for Google display)\n- Include the focus keyword naturally if provided\n- Include a subtle call-to-action\n- Accurately summarize the content\n- Make it compelling to increase click-through rate\n\n";
        if (!empty($focus_keyword)) $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if (!empty($current_description)) $prompt .= "Current Description: {$current_description}\n\n";
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n{$clean}\n\nRespond with ONLY the meta description, nothing else.";

        $result = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO expert that generates concise, compelling meta descriptions.'],
            ['role' => 'user',   'content' => $prompt],
        ], 200, 0.7);

        return is_wp_error($result) ? $result : trim(trim($result), '"\'');
    }

    /**
     * Generate alt text from the actual image bytes.
     *
     * Fails with SSF_AI::VISION_ERROR when the image cannot be read rather than
     * prompting with only a URL — a model that cannot see the picture invents
     * its contents, and the caller's filename fallback is more honest.
     */
    public function generate_alt_text($image_url, $page_context = '', $focus_keyword = '') {
        if (!class_exists('SSF_AI')) {
            return new WP_Error('ssf_missing_ai', __('AI factory unavailable.', 'smart-seo-fixer'));
        }

        $img = SSF_AI::fetch_image_as_base64($image_url);
        if (is_wp_error($img)) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::warning('Claude alt-text: image bytes unreadable, skipping AI: ' . $img->get_error_message(), 'ai');
            }
            return SSF_AI::vision_error(
                'image_unreadable',
                __('The image file could not be read, so the AI had nothing to look at.', 'smart-seo-fixer'),
                $img->get_error_message()
            );
        }

        $instruction  = "Look at this image and write descriptive, SEO-friendly alt text for it.\n\n";
        $instruction .= "Requirements:\n- Maximum 125 characters\n- Describe what is actually visible in the image\n- Include the focus keyword naturally only if it genuinely describes the image\n- Do NOT start with 'Image of' or 'Picture of'\n- Useful for screen readers\n";
        if (!empty($page_context)) $instruction .= "Page Context: " . wp_trim_words($page_context, 100) . "\n";
        if (!empty($focus_keyword)) $instruction .= "Focus Keyword: {$focus_keyword}\n";
        $instruction .= "\nRespond with ONLY the alt text, nothing else.";

        $result = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO expert that generates accessible, descriptive image alt text based on what you see in the image.'],
            [
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['media_type'], 'data' => $img['data']]],
                    ['type' => 'text', 'text' => $instruction],
                ],
            ],
        ], 150, 0.5);

        return is_wp_error($result) ? $result : trim(trim($result), '"\'');
    }

    /**
     * Every Anthropic Messages-API model this plugin offers accepts image
     * blocks, so vision is always available once a key is set.
     */
    public function supports_vision() {
        return true;
    }

    /**
     * The model ID in use, for display and diagnostics.
     */
    public function get_model_id() {
        return (string) $this->get_model();
    }

    public function analyze_content($content, $title = '', $focus_keyword = '') {
        $prompt = "You are an SEO expert. Analyze this content and provide specific, actionable SEO recommendations.\n\n";
        if (!empty($title)) $prompt .= "Title: {$title}\n";
        if (!empty($focus_keyword)) $prompt .= "Focus Keyword: {$focus_keyword}\n";
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 1000);
        $prompt .= "\nContent:\n{$clean}\n\n";
        $prompt .= "Analyze and provide feedback on:\n1. Keyword usage and placement\n2. Content structure (headings, paragraphs)\n3. Readability and engagement\n4. Content length and depth\n5. Internal/external linking opportunities\n\n";
        $prompt .= "Format your response as JSON with this structure:\n";
        $prompt .= '{"score": 0-100, "issues": ["issue1", "issue2"], "suggestions": ["suggestion1", "suggestion2"], "strengths": ["strength1", "strength2"]}';

        $response = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO expert. Respond only with valid JSON.'],
            ['role' => 'user',   'content' => $prompt],
        ], 1000, 0.5);

        if (is_wp_error($response)) return $response;
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $data = json_decode(trim($response), true);
        return json_last_error() === JSON_ERROR_NONE ? $data : new WP_Error('json_error', __('Failed to parse AI response.', 'smart-seo-fixer'));
    }

    public function generate_outline($topic, $focus_keyword = '') {
        $prompt = "You are an SEO expert. Create a comprehensive content outline for this topic.\n\nTopic: {$topic}\n";
        if (!empty($focus_keyword)) $prompt .= "Focus Keyword: {$focus_keyword}\n";
        $prompt .= "\nGenerate an SEO-optimized content outline.\n\nIMPORTANT: Do NOT include prefixes like 'H1:', 'H2:', 'H3:' in the text. Just write the actual heading text.\n\n";
        $prompt .= "Format as JSON:\n";
        $prompt .= '{"title": "Your Compelling Title Here", "sections": [{"heading": "First Section Heading", "subsections": ["Sub Topic One", "Sub Topic Two"]}], "suggested_word_count": 1500}';
        $prompt .= "\n\nProvide 4-6 sections, each with 2-3 subsections. No descriptions needed.";

        $response = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO content strategist. Respond only with valid JSON.'],
            ['role' => 'user',   'content' => $prompt],
        ], 1500, 0.7);

        if (is_wp_error($response)) return $response;
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        return json_decode(trim($response), true);
    }

    public function suggest_keywords($content, $title = '') {
        $prompt = "You are an SEO keyword research expert. Suggest the best focus keywords for this content.\n\n";
        if (!empty($title)) $prompt .= "Title: {$title}\n\n";
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 500);
        $prompt .= "Content:\n{$clean}\n\n";
        $prompt .= "CRITICAL: Every keyword you suggest MUST appear as a VERBATIM substring in the title or content above (case-insensitive). Do NOT invent new phrases.\n\n";
        $prompt .= "Suggest 5 focus keywords with:\n- Primary keyword (main focus)\n- Secondary keywords (supporting)\n- Long-tail variations\n\n";
        $prompt .= "Format as JSON:\n";
        $prompt .= '{"primary": "keyword", "secondary": ["kw1", "kw2"], "long_tail": ["phrase1", "phrase2"]}';

        $response = $this->request([
            ['role' => 'system', 'content' => 'You are an SEO keyword expert. Respond only with valid JSON.'],
            ['role' => 'user',   'content' => $prompt],
        ], 500, 0.6);

        if (is_wp_error($response)) return $response;
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        return json_decode(trim($response), true);
    }

    public function suggest_schema($content, $post_type = 'article', $post_url = '', $site_url = '', $post_title = '', $site_name = '', $logo_url = '') {
        $prompt = "You are a structured data expert. Suggest additional Schema.org markup for this content.\n\n";
        $prompt .= "=== REAL SITE DATA (use these exact values, do NOT make up alternatives) ===\n";
        if (!empty($site_name))  $prompt .= "Organization Name: {$site_name}\n";
        if (!empty($site_url))   $prompt .= "Site URL: {$site_url}\n";
        if (!empty($logo_url))   $prompt .= "Logo URL: {$logo_url}\n";
        if (!empty($post_url))   $prompt .= "Post URL: {$post_url}\n";
        if (!empty($post_title)) $prompt .= "Title: {$post_title}\n";
        $prompt .= "Content Type: {$post_type}\n=== END SITE DATA ===\n\n";
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 300);
        $prompt .= "Content:\n{$clean}\n\n";
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Do NOT suggest Article, BlogPosting, NewsArticle, WebPage, BreadcrumbList, Organization, or WebSite schemas — these are ALREADY generated automatically by the plugin.\n";
        $prompt .= "2. Only suggest schemas that add NEW value. Good examples: Review, Product, Service, Recipe, Event, FAQPage, HowTo, Person, VideoObject, Course, SoftwareApplication, MedicalEntity, LegalService.\n";
        $prompt .= "3. Use ONLY the real URLs provided in the SITE DATA section above. NEVER guess or fabricate URLs.\n";
        $prompt .= "4. For any logo or image property, use ONLY the Logo URL from SITE DATA. If no logo URL was provided, omit the logo property entirely.\n";
        $prompt .= "5. Do NOT include articleBody.\n";
        $prompt .= "6. If the content is a client review/testimonial, use a Review schema.\n";
        $prompt .= "7. If there is truly no additional schema that would add value beyond Article, respond with exactly: {\"_no_schema\": true}\n\n";
        $prompt .= "Format as valid JSON-LD with @context and @type.";

        return $this->request([
            ['role' => 'system', 'content' => 'You are a Schema.org expert. Respond only with valid JSON-LD. Never use placeholder or example.com URLs.'],
            ['role' => 'user',   'content' => $prompt],
        ], 1000, 0.5);
    }

    public function find_internal_link_placement($source_content, $target_title, $target_url, $target_summary = '') {
        $clean = wp_trim_words(wp_strip_all_tags(strip_shortcodes($source_content)), 800);
        $prompt = "You are an SEO expert specializing in internal linking strategy.\n\n";
        $prompt .= "I need to add an internal link to a TARGET PAGE from within the SOURCE CONTENT below.\n\n";
        $prompt .= "TARGET PAGE TO LINK TO:\n- Title: {$target_title}\n- URL: {$target_url}\n";
        if (!empty($target_summary)) $prompt .= "- Topic: {$target_summary}\n";
        $prompt .= "\nSOURCE CONTENT (find a natural anchor text phrase in here):\n{$clean}\n\n";
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Find an existing phrase (2-6 words) in the SOURCE CONTENT that naturally relates to the target page topic\n";
        $prompt .= "2. The phrase MUST exist EXACTLY as-is in the source content\n";
        $prompt .= "3. The phrase must make sense as a link\n";
        $prompt .= "4. Do NOT choose phrases that are already inside <a> tags or HTML attributes\n";
        $prompt .= "5. Prefer phrases in the body of the content, not in headings\n";
        $prompt .= "6. The anchor text should be descriptive, not generic\n\n";
        $prompt .= "Respond with ONLY valid JSON in this exact format:\n";
        $prompt .= '{"found": true, "anchor_text": "the exact phrase from content", "context": "...surrounding sentence for verification..."}' . "\n";
        $prompt .= "If no natural fit exists, respond with: {\"found\": false}\nDo NOT wrap in code blocks. Just raw JSON.";

        $response = $this->request([
            ['role' => 'system', 'content' => 'You are an internal linking expert. Respond only with valid JSON. Never wrap in code blocks.'],
            ['role' => 'user',   'content' => $prompt],
        ], 300, 0.3);

        if (is_wp_error($response)) return $response;
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $data = json_decode(trim($response), true);
        return json_last_error() === JSON_ERROR_NONE ? $data : new WP_Error('json_error', __('Failed to parse AI response for internal link.', 'smart-seo-fixer'));
    }

    public function improve_readability($content) {
        $prompt = "You are a content editor. Improve the readability of this content while maintaining SEO value.\n\n";
        $prompt .= "Content:\n{$content}\n\n";
        $prompt .= "Improvements to make:\n- Shorter sentences where appropriate\n- Active voice\n- Clearer structure\n- Better transitions\n- Maintain all keywords and SEO elements\n\n";
        $prompt .= "Return ONLY the improved content, nothing else.";

        return $this->request([
            ['role' => 'system', 'content' => 'You are an expert content editor focused on readability.'],
            ['role' => 'user',   'content' => $prompt],
        ], 2000, 0.6);
    }
}
