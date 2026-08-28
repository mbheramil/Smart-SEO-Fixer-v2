<?php
/**
 * AWS Bedrock AI Integration Class
 *
 * Handles all AI-powered SEO suggestions and content generation via AWS Bedrock.
 * Supports Claude (Anthropic) and Llama (Meta) model families.
 * Uses AWS Signature Version 4 (SigV4) — no SDK required.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Bedrock {

    /**
     * AWS region — constant SSF_BEDROCK_REGION in wp-config.php takes priority
     */
    private function get_region() {
        if (defined('SSF_BEDROCK_REGION') && SSF_BEDROCK_REGION !== '') {
            return SSF_BEDROCK_REGION;
        }
        return Smart_SEO_Fixer::get_option('bedrock_region', 'us-east-1');
    }

    /**
     * AWS Access Key ID — constant SSF_BEDROCK_ACCESS_KEY in wp-config.php takes priority
     */
    private function get_access_key() {
        if (defined('SSF_BEDROCK_ACCESS_KEY') && SSF_BEDROCK_ACCESS_KEY !== '') {
            return SSF_BEDROCK_ACCESS_KEY;
        }
        return Smart_SEO_Fixer::get_option('bedrock_access_key');
    }

    /**
     * AWS Secret Access Key — constant SSF_BEDROCK_SECRET_KEY in wp-config.php takes priority
     */
    private function get_secret_key() {
        if (defined('SSF_BEDROCK_SECRET_KEY') && SSF_BEDROCK_SECRET_KEY !== '') {
            return SSF_BEDROCK_SECRET_KEY;
        }
        return Smart_SEO_Fixer::get_option('bedrock_secret_key');
    }

    /**
     * Broker mode — for a fleet of sites, a real AWS key/secret pair on every
     * individual WordPress install means one migrated/cloned/backed-up site
     * leaks a credential that can bill against your AWS account indefinitely.
     *
     * When these two constants are set, this class stops signing requests
     * with a static AWS key entirely. Instead it sends the already-built
     * Bedrock request body to a small proxy (Lambda + API Gateway) that
     * holds the real credential ONLY there, via its Lambda execution role —
     * never a static key. Each site gets a cheap, disposable bearer token
     * instead, worth nothing to anyone who copies it, revocable per-site
     * without touching AWS at all.
     *
     * Both undefined (the default) = unchanged direct-to-AWS behavior.
     */
    private function get_broker_url() {
        return (defined('SSF_BEDROCK_BROKER_URL') && SSF_BEDROCK_BROKER_URL !== '') ? SSF_BEDROCK_BROKER_URL : '';
    }

    private function get_broker_token() {
        return (defined('SSF_BEDROCK_BROKER_TOKEN') && SSF_BEDROCK_BROKER_TOKEN !== '') ? SSF_BEDROCK_BROKER_TOKEN : '';
    }

    private function using_broker() {
        return $this->get_broker_url() !== '' && $this->get_broker_token() !== '';
    }

    /**
     * Default Bedrock model — Claude Haiku 4.5 cross-region inference profile.
     * Claude 4.x-tier models on Bedrock are only invokable on-demand through a
     * cross-region inference profile, so the ID carries the `us.` prefix.
     */
    const DEFAULT_MODEL = 'us.anthropic.claude-haiku-4-5-20251001-v1:0';

    /**
     * Fallback model, used automatically when the default model is not enabled
     * for this AWS account/region — so AI features keep working instead of
     * failing silently. This is the model the plugin shipped with previously.
     */
    const FALLBACK_MODEL = 'us.anthropic.claude-3-5-haiku-20241022-v1:0';

    /** Per-request model override, set during an automatic fallback retry. */
    private $model_override = null;

    /** Process-wide flag: the default model has been confirmed unavailable. */
    private static $primary_unavailable = false;

    /**
     * Bedrock model ID for the current request.
     */
    private function get_model() {
        if ( $this->model_override ) {
            return $this->model_override;
        }
        // Once we learn the default isn't enabled, go straight to the fallback
        // for the rest of this PHP process (avoids a failed primary call on
        // every item of a bulk run).
        if ( self::$primary_unavailable ) {
            return self::FALLBACK_MODEL;
        }
        return self::DEFAULT_MODEL;
    }

    /**
     * Detect the AWS error meaning "this model isn't available to you" — not
     * enabled in Bedrock model access, wrong region, or an unrecognized ID.
     */
    private function is_model_unavailable_error( $message ) {
        $message = strtolower( (string) $message );
        $needles = [
            'accessdenied', "don't have access", 'do not have access', 'not authorized',
            'invalid model', 'model identifier', 'could not be found', 'validationexception',
            "isn't supported", 'not supported', 'on-demand throughput', 'inference profile',
        ];
        foreach ( $needles as $needle ) {
            if ( strpos( $message, $needle ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * One-line, actionable notice logged when the fallback kicks in.
     */
    private function log_fallback_notice() {
        if ( class_exists( 'SSF_Logger' ) ) {
            SSF_Logger::warning(
                'AWS Bedrock model "' . self::DEFAULT_MODEL . '" is not available to this account/region. '
                . 'Falling back to "' . self::FALLBACK_MODEL . '". To use the newer model, open AWS Console → '
                . 'Bedrock → Model access and enable Claude Haiku 4.5 in your region.',
                'ai'
            );
        }
    }

    /**
     * Check if Bedrock is fully configured
     */
    public function is_configured() {
        if ($this->using_broker()) {
            return true;
        }
        return !empty($this->get_access_key()) && !empty($this->get_secret_key());
    }

    /**
     * The model ID this provider would actually use, for display and
     * diagnostics. Public counterpart to the private get_model().
     */
    public function get_model_id() {
        return (string) $this->get_model();
    }

    /**
     * Whether the selected model can analyse image bytes.
     *
     * On Bedrock only the Claude families accept multimodal image blocks;
     * Llama, Mistral and Titan are text-only here.
     */
    public function supports_vision() {
        return $this->get_model_family() === 'claude';
    }

    /**
     * Detect model family from model ID
     * Returns 'claude', 'llama', or 'titan'
     */
    private function get_model_family() {
        $model = $this->get_model();
        // Cross-region inference profiles (us.anthropic.*) and direct Anthropic models
        if (strpos($model, 'anthropic.claude') !== false) return 'claude';
        if (strpos($model, '.anthropic.claude') !== false) return 'claude'; // us./eu./ap. prefix
        if (strpos($model, 'meta.llama')       !== false) return 'llama';
        if (strpos($model, 'amazon.titan')     !== false) return 'titan';
        if (strpos($model, 'mistral.')         !== false) return 'mistral';
        return 'claude'; // safe default
    }

    /**
     * Build the Bedrock API endpoint URL for the selected model
     */
    private function get_endpoint() {
        $region = $this->get_region();
        $model  = $this->get_model();
        return "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/invoke";
    }

    /**
     * Build the request body for the selected model family
     *
     * @param array  $messages     [['role'=>'user','content'=>'...'}, ...]
     * @param string $system       Optional system prompt
     * @param int    $max_tokens
     * @param float  $temperature
     * @return array               Body array ready for wp_json_encode
     */
    private function build_body( $messages, $system, $max_tokens, $temperature ) {
        $family = $this->get_model_family();

        if ( $family === 'claude' ) {
            $body = [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens'        => $max_tokens,
                'temperature'       => $temperature,
                'messages'          => $messages,
            ];
            if ( ! empty( $system ) ) {
                $body['system'] = $system;
            }
            return $body;
        }

        if ( $family === 'llama' ) {
            // Llama 3 uses a single prompt string
            $prompt = '';
            if ( ! empty( $system ) ) {
                $prompt .= "<|begin_of_text|><|start_header_id|>system<|end_header_id|>\n{$system}<|eot_id|>";
            }
            foreach ( $messages as $msg ) {
                $role    = $msg['role'] === 'user' ? 'user' : 'assistant';
                $prompt .= "<|start_header_id|>{$role}<|end_header_id|>\n{$msg['content']}<|eot_id|>";
            }
            $prompt .= '<|start_header_id|>assistant<|end_header_id|>';
            return [
                'prompt'      => $prompt,
                'max_gen_len' => $max_tokens,
                'temperature' => $temperature,
            ];
        }

        if ( $family === 'mistral' ) {
            $prompt = '';
            if ( ! empty( $system ) ) {
                $prompt .= "[INST] <<SYS>>\n{$system}\n<</SYS>>\n\n";
            }
            $first = true;
            foreach ( $messages as $msg ) {
                if ( $msg['role'] === 'user' ) {
                    if ( $first && empty( $system ) ) {
                        $prompt .= "[INST] {$msg['content']} [/INST]";
                    } else {
                        $prompt .= "{$msg['content']} [/INST]";
                    }
                    $first = false;
                } else {
                    $prompt .= " {$msg['content']} [INST] ";
                }
            }
            return [
                'prompt'      => $prompt,
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
            ];
        }

        // Amazon Titan fallback
        $full_prompt = '';
        if ( ! empty( $system ) ) {
            $full_prompt .= $system . "\n\n";
        }
        foreach ( $messages as $msg ) {
            $full_prompt .= ucfirst( $msg['role'] ) . ': ' . $msg['content'] . "\n";
        }
        return [
            'inputText' => $full_prompt,
            'textGenerationConfig' => [
                'maxTokenCount' => $max_tokens,
                'temperature'   => $temperature,
            ],
        ];
    }

    /**
     * Extract the text content from the Bedrock response body
     *
     * @param array  $data   Decoded JSON response
     * @return string|WP_Error
     */
    private function extract_content( $data ) {
        $family = $this->get_model_family();

        if ( $family === 'claude' ) {
            if ( isset( $data['content'][0]['text'] ) ) {
                return $data['content'][0]['text'];
            }
        }

        if ( $family === 'llama' ) {
            if ( isset( $data['generation'] ) ) {
                return $data['generation'];
            }
        }

        if ( $family === 'mistral' ) {
            if ( isset( $data['outputs'][0]['text'] ) ) {
                return $data['outputs'][0]['text'];
            }
        }

        // Titan
        if ( isset( $data['results'][0]['outputText'] ) ) {
            return $data['results'][0]['outputText'];
        }

        return new WP_Error( 'invalid_response', __( 'Invalid response from AWS Bedrock.', 'smart-seo-fixer' ) );
    }

    /**
     * Make an AWS Bedrock API request using SigV4 signing (no SDK required).
     *
     * @param array  $messages    Chat messages array [['role'=>'...','content'=>'...']]
     * @param string $system      System prompt (extracted from messages[0] if role=system)
     * @param int    $max_tokens
     * @param float  $temperature
     * @return string|WP_Error
     */
    public function request( $messages, $max_tokens = 500, $temperature = 0.7 ) {
        $result = $this->request_with_model( $messages, $max_tokens, $temperature );

        // If the configured model isn't enabled for this AWS account/region,
        // transparently retry once with the previously-shipped model so AI keeps
        // working. We then remember it for the rest of this process.
        if ( is_wp_error( $result )
            && $this->model_override === null
            && ! self::$primary_unavailable
            && self::DEFAULT_MODEL !== self::FALLBACK_MODEL
            && $this->is_model_unavailable_error( $result->get_error_message() ) ) {
            $this->log_fallback_notice();
            self::$primary_unavailable = true;
            $this->model_override = self::FALLBACK_MODEL;
            $result = $this->request_with_model( $messages, $max_tokens, $temperature );
            $this->model_override = null;
        }

        return $result;
    }

    /**
     * Make an AWS Bedrock API request for the currently-selected model.
     */
    private function request_with_model( $messages, $max_tokens = 500, $temperature = 0.7 ) {
        $using_broker = $this->using_broker();

        if ( ! $using_broker ) {
            $access_key = $this->get_access_key();
            $secret_key = $this->get_secret_key();

            if ( empty( $access_key ) || empty( $secret_key ) ) {
                return new WP_Error( 'no_credentials', __( 'AWS Bedrock credentials not configured.', 'smart-seo-fixer' ) );
            }
        }

        $endpoint = $this->get_endpoint();

        // Separate out a system role message if present (Claude style)
        $system   = '';
        $chat     = [];
        foreach ( $messages as $msg ) {
            if ( $msg['role'] === 'system' ) {
                $system = $msg['content'];
            } else {
                $chat[] = $msg;
            }
        }

        $body_array = $this->build_body( $chat, $system, $max_tokens, $temperature );
        $body       = wp_json_encode( $body_array );

        $make_request = function() use ( $using_broker, $endpoint, $body ) {
            if ( $using_broker ) {
                // The broker holds the real AWS credential via its own Lambda
                // execution role — never a static key. It mirrors AWS's own
                // response (status code + body) verbatim, so everything below
                // this point is identical whether talking to AWS directly or
                // through the broker.
                $response = wp_remote_post( $this->get_broker_url(), [
                    'timeout' => 60,
                    'headers' => [
                        'Authorization'   => 'Bearer ' . $this->get_broker_token(),
                        'Content-Type'    => 'application/json',
                        'X-Bedrock-Model' => $this->get_model(),
                    ],
                    'body' => $body,
                ] );
            } else {
                $signed_headers = $this->sigv4_sign( $this->get_access_key(), $this->get_secret_key(), $endpoint, $body );

                $response = wp_remote_post( $endpoint, [
                    'timeout' => 60,
                    'headers' => $signed_headers,
                    'body'    => $body,
                ] );
            }

            if ( is_wp_error( $response ) ) {
                if ( class_exists( 'SSF_Logger' ) ) {
                    SSF_Logger::error( 'Bedrock request failed: ' . $response->get_error_message(), 'ai' );
                }
                return $response;
            }

            $http_code   = wp_remote_retrieve_response_code( $response );
            $body_string = wp_remote_retrieve_body( $response );
            $data        = json_decode( $body_string, true );

            if ( $http_code >= 400 ) {
                $message = $data['message'] ?? ( $data['error']['message'] ?? "HTTP {$http_code}" );
                if ( class_exists( 'SSF_Logger' ) ) {
                    SSF_Logger::error( "Bedrock API error {$http_code}: {$message}", 'ai' );
                }
                return new WP_Error( 'api_error', $message );
            }

            $content = $this->extract_content( $data );

            if ( ! is_wp_error( $content ) && class_exists( 'SSF_Logger' ) ) {
                SSF_Logger::debug( 'Bedrock request successful', 'ai', [
                    'model'        => $this->get_model(),
                    'input_tokens' => $data['usage']['input_tokens'] ?? null,
                    'output_tokens'=> $data['usage']['output_tokens'] ?? null,
                ] );
            }

            return $content;
        };

        // Wrap with retry logic for transient failures (timeouts, 429, 500, 503)
        $max_retries = 3;
        $attempt = 0;
        $last_result = null;

        while ( $attempt < $max_retries ) {
            $attempt++;

            if ( class_exists( 'SSF_Rate_Limiter' ) ) {
                $last_result = SSF_Rate_Limiter::execute( 'bedrock', $make_request );
            } else {
                $last_result = $make_request();
            }

            // Success — return immediately
            if ( ! is_wp_error( $last_result ) ) {
                return $last_result;
            }

            // Check if the error is retryable
            $error_msg = $last_result->get_error_message();
            $is_retryable = (
                strpos( $error_msg, 'cURL error 28' ) !== false ||   // timeout
                strpos( $error_msg, 'cURL error 7' ) !== false ||    // connection refused
                strpos( $error_msg, 'HTTP 429' ) !== false ||        // rate limited
                strpos( $error_msg, 'HTTP 500' ) !== false ||        // server error
                strpos( $error_msg, 'HTTP 502' ) !== false ||        // bad gateway
                strpos( $error_msg, 'HTTP 503' ) !== false ||        // service unavailable
                strpos( $error_msg, 'ThrottlingException' ) !== false
            );

            if ( ! $is_retryable || $attempt >= $max_retries ) {
                break;
            }

            if ( class_exists( 'SSF_Logger' ) ) {
                SSF_Logger::warning( sprintf( 'Bedrock request failed (attempt %d/%d): %s — retrying', $attempt, $max_retries, $error_msg ), 'ai' );
            }

            // Exponential backoff: 2s, 4s (capped at PHP max_execution_time safety)
            $delay = min( pow( 2, $attempt ), 8 );
            sleep( $delay );
        }

        return $last_result;
    }

    /**
     * Fire multiple Bedrock requests in parallel using curl_multi.
     *
     * Each "job" is an associative array with the same shape as the args to
     * request(): ['messages' => [...], 'max_tokens' => N, 'temperature' => F].
     * Returns an array keyed by the same keys as $jobs, containing either the
     * extracted text string or a WP_Error.
     *
     * Used by the job queue to process many posts concurrently.
     *
     * @param array $jobs  [ key => ['messages'=>..., 'max_tokens'=>..., 'temperature'=>...], ... ]
     * @return array       [ key => string|WP_Error ]
     */
    public function request_multi( $jobs ) {
        $results = $this->request_multi_with_model( $jobs );

        // If the whole batch failed because the model isn't enabled for this
        // account/region, retry the batch once with the fallback model.
        if ( $this->model_override === null && ! self::$primary_unavailable
            && self::DEFAULT_MODEL !== self::FALLBACK_MODEL ) {
            $needs_fallback = false;
            foreach ( $results as $r ) {
                if ( is_wp_error( $r ) && $this->is_model_unavailable_error( $r->get_error_message() ) ) {
                    $needs_fallback = true;
                    break;
                }
            }
            if ( $needs_fallback ) {
                $this->log_fallback_notice();
                self::$primary_unavailable = true;
                $this->model_override = self::FALLBACK_MODEL;
                $results = $this->request_multi_with_model( $jobs );
                $this->model_override = null;
            }
        }

        return $results;
    }

    /**
     * Parallel batch request for the currently-selected model.
     */
    private function request_multi_with_model( $jobs ) {
        // Broker mode signs nothing itself (that's the broker's job, via its
        // own Lambda role) — the SigV4 concurrency path below only knows how
        // to sign direct-to-AWS requests. Rather than duplicate that signing
        // logic for a bearer-token broker call, fall back to sequential
        // requests, each of which already knows how to use the broker via
        // request_with_model(). Slower, but correct; parallel-broker support
        // can be added later if bulk-through-broker throughput matters.
        if ( $this->using_broker() ) {
            $out = [];
            foreach ( $jobs as $k => $job ) {
                $out[ $k ] = $this->request( $job['messages'], $job['max_tokens'] ?? 500, $job['temperature'] ?? 0.7 );
            }
            return $out;
        }

        $access_key = $this->get_access_key();
        $secret_key = $this->get_secret_key();

        if ( empty( $access_key ) || empty( $secret_key ) ) {
            $err = new WP_Error( 'no_credentials', __( 'AWS Bedrock credentials not configured.', 'smart-seo-fixer' ) );
            return array_map( function() use ( $err ) { return $err; }, $jobs );
        }

        if ( ! function_exists( 'curl_multi_init' ) ) {
            // cURL multi unavailable — fall back to sequential.
            $out = [];
            foreach ( $jobs as $k => $job ) {
                $out[ $k ] = $this->request( $job['messages'], $job['max_tokens'] ?? 500, $job['temperature'] ?? 0.7 );
            }
            return $out;
        }

        $endpoint = $this->get_endpoint();
        $parsed   = wp_parse_url( $endpoint );
        $host     = $parsed['host'];

        $multi   = curl_multi_init();
        $handles = [];

        foreach ( $jobs as $key => $job ) {
            // Pull system out of messages (Claude separates it from the chat array)
            $system = '';
            $chat   = [];
            foreach ( $job['messages'] as $m ) {
                if ( $m['role'] === 'system' ) { $system = $m['content']; } else { $chat[] = $m; }
            }
            $body       = wp_json_encode( $this->build_body( $chat, $system, $job['max_tokens'] ?? 500, $job['temperature'] ?? 0.7 ) );
            $headers    = $this->sigv4_sign( $access_key, $secret_key, $endpoint, $body );

            $curl_headers = [];
            foreach ( $headers as $k => $v ) { $curl_headers[] = $k . ': ' . $v; }
            $curl_headers[] = 'Host: ' . $host;

            $ch = curl_init( $endpoint );
            curl_setopt_array( $ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $curl_headers,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ] );
            curl_multi_add_handle( $multi, $ch );
            $handles[ $key ] = $ch;
        }

        // Execute all requests concurrently.
        $running = null;
        do {
            $status = curl_multi_exec( $multi, $running );
            if ( $running ) {
                // Block until at least one handle has activity; avoids busy-wait.
                curl_multi_select( $multi, 1.0 );
            }
        } while ( $running > 0 && $status === CURLM_OK );

        $results = [];
        foreach ( $handles as $key => $ch ) {
            $raw       = curl_multi_getcontent( $ch );
            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $errno     = curl_errno( $ch );
            $errstr    = curl_error( $ch );

            curl_multi_remove_handle( $multi, $ch );
            curl_close( $ch );

            if ( $errno ) {
                $results[ $key ] = new WP_Error( 'curl_error', 'cURL error ' . $errno . ': ' . $errstr );
                continue;
            }
            if ( $http_code >= 400 ) {
                $data    = json_decode( $raw, true );
                $message = $data['message'] ?? ( $data['error']['message'] ?? "HTTP {$http_code}" );
                $results[ $key ] = new WP_Error( 'api_error', $message );
                continue;
            }

            $data    = json_decode( $raw, true );
            $content = $this->extract_content( is_array( $data ) ? $data : [] );
            $results[ $key ] = $content;
        }

        curl_multi_close( $multi );
        return $results;
    }

    /**
     * Build the messages array for a combined "SEO bundle" prompt that returns
     * keyword + title + description in one JSON response. Used for bulk fix
     * so we do ONE Bedrock call per post instead of three.
     *
     * @param string $content       Post body.
     * @param string $current_title Current post / SEO title.
     * @return array                messages array (ready for request / request_multi)
     */
    public function build_seo_bundle_messages( $content, $current_title = '' ) {
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );

        $prompt  = "You are an SEO expert. Produce a complete SEO bundle for this post in ONE response.\n\n";
        if ( ! empty( $current_title ) ) $prompt .= "Post Title: {$current_title}\n\n";
        $prompt .= "Content:\n{$clean}\n\n";
        $prompt .= self::grounding_rules();
        $prompt .= "Return a JSON object with exactly these keys:\n";
        $prompt .= "  \"keyword\": a focus keyword (2-4 words). CRITICAL: it MUST appear as a verbatim, case-insensitive substring somewhere in the title or content above. Do NOT invent phrases.\n";
        $prompt .= "  \"title\": an SEO title, max 60 characters, including the keyword naturally. Compelling but accurate, and grounded only in the content above. No surrounding quotes.\n";
        $prompt .= "  \"description\": a meta description, 150-160 characters, including the keyword naturally, with a subtle call-to-action, grounded only in the content above. No surrounding quotes.\n\n";
        $prompt .= 'Respond with ONLY a valid JSON object like: {"keyword":"...","title":"...","description":"..."}';

        return [
            [ 'role' => 'system', 'content' => 'You are an SEO expert. Respond only with valid JSON matching the requested schema. You never invent facts that are not present in the supplied content.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
    }

    /**
     * Shared anti-hallucination instruction block injected into every SEO
     * title / description / bundle prompt. This is the primary defense against
     * the model inventing facts (prices, locations, dates, awards, ratings) or
     * unfounded superlatives ("best", "#1", "leading") that aren't in the post.
     */
    public static function grounding_rules() {
        return "GROUNDING (critical): Use ONLY information that actually appears in the content above. "
             . "Do NOT invent or imply facts that aren't present — no statistics, prices, phone numbers, "
             . "addresses, dates, years, awards, certifications, ratings, guarantees, or quantities. "
             . "Do NOT add superlative or ranking claims (\"best\", \"#1\", \"top-rated\", \"leading\", "
             . "\"award-winning\") unless those exact words appear in the content. If the content is thin, "
             . "write a simpler accurate result rather than embellishing. Never mention a location, brand, "
             . "or service the content does not mention.\n\n";
    }

    /**
     * Parse a JSON-bundle response into [keyword, title, description].
     * Accepts either a raw string, WP_Error, or already-decoded array.
     *
     * @return array|WP_Error ['keyword'=>..., 'title'=>..., 'description'=>...]
     */
    public function parse_seo_bundle( $response ) {
        if ( is_wp_error( $response ) ) return $response;
        if ( is_array( $response ) ) $data = $response;
        else {
            $clean = preg_replace( '/```json\s*/', '', (string) $response );
            $clean = preg_replace( '/```\s*/', '', $clean );
            $clean = trim( $clean );
            $data  = json_decode( $clean, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error( 'json_error', 'Failed to parse SEO bundle JSON: ' . json_last_error_msg() );
            }
        }

        return [
            'keyword'     => isset( $data['keyword'] )     ? trim( (string) $data['keyword'] )     : '',
            'title'       => isset( $data['title'] )       ? trim( (string) $data['title'], " \t\n\r\0\x0B\"'" ) : '',
            'description' => isset( $data['description'] ) ? trim( (string) $data['description'], " \t\n\r\0\x0B\"'" ) : '',
        ];
    }

    /**
     * Convenience: one-call SEO bundle generation.
     */
    public function generate_seo_bundle( $content, $current_title = '' ) {
        $messages = $this->build_seo_bundle_messages( $content, $current_title );
        // Low temperature keeps the model close to the source content (less invention).
        $result   = $this->request( $messages, 400, 0.3 );
        return $this->parse_seo_bundle( $result );
    }

    // =========================================================================
    // AWS Signature Version 4 (SigV4) — pure PHP, no SDK required
    // =========================================================================

    /**
     * Sign a request with AWS Signature Version 4
     *
     * @param string $access_key
     * @param string $secret_key
     * @param string $endpoint      Full HTTPS URL
     * @param string $body          Raw request body (JSON string)
     * @return array                Headers to pass to wp_remote_post
     */
    private function sigv4_sign( $access_key, $secret_key, $endpoint, $body ) {
        $region  = $this->get_region();
        $service = 'bedrock';

        $parsed   = wp_parse_url( $endpoint );
        $host     = $parsed['host'];
        $raw_path = $parsed['path'] ?? '/';

        // SigV4 canonical URI: each path segment must be URI-encoded (RFC 3986).
        // The model ID contains a colon (e.g. v1:0) which must become %3A.
        // Split on '/' and encode each segment individually, then rejoin.
        $uri = implode( '/', array_map( 'rawurlencode', explode( '/', $raw_path ) ) );

        $amz_date   = gmdate( 'Ymd\THis\Z' );
        $date_stamp = gmdate( 'Ymd' );

        $content_type   = 'application/json';
        $payload_hash   = hash( 'sha256', $body );

        // Canonical headers (must be sorted and lowercase)
        $canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-date:{$amz_date}\n";
        $signed_headers    = 'content-type;host;x-amz-date';

        // Canonical request
        $canonical_request = implode( "\n", [
            'POST',
            $uri,
            '',                     // query string (empty)
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ] );

        // Credential scope
        $credential_scope = implode( '/', [ $date_stamp, $region, $service, 'aws4_request' ] );

        // String to sign
        $string_to_sign = implode( "\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ] );

        // Signing key — derived from secret_key → date → region → service → "aws4_request"
        $signing_key = $this->hmac_sha256(
            $this->hmac_sha256(
                $this->hmac_sha256(
                    $this->hmac_sha256( 'AWS4' . $secret_key, $date_stamp, true ),
                    $region,
                    true
                ),
                $service,
                true
            ),
            'aws4_request',
            true
        );

        $signature = $this->hmac_sha256( $signing_key, $string_to_sign );

        // Authorization header
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $access_key,
            $credential_scope,
            $signed_headers,
            $signature
        );

        return [
            'Content-Type'  => $content_type,
            'X-Amz-Date'    => $amz_date,
            'Authorization' => $authorization,
        ];
    }

    /**
     * HMAC-SHA256 helper
     *
     * @param string $key     Raw key (binary if $raw = true was used previously)
     * @param string $data
     * @param bool   $raw     Return raw binary (for chained derivation)
     * @return string
     */
    private function hmac_sha256( $key, $data, $raw = false ) {
        return hash_hmac( 'sha256', $data, $key, $raw );
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Build message array for title generation (no API call).
     * Use with request_multi() for concurrent processing.
     */
    public function build_title_messages( $content, $current_title = '', $focus_keyword = '' ) {
        $prompt = "You are an SEO expert. Generate an optimized SEO title for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Maximum 60 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Make it compelling and click-worthy\n";
        $prompt .= "- Avoid clickbait, be accurate to the content\n\n";
        if ( ! empty( $focus_keyword ) ) $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if ( ! empty( $current_title ) ) $prompt .= "Current Title: {$current_title}\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );
        $prompt .= "Content:\n{$clean}\n\n" . self::grounding_rules() . "Respond with ONLY the optimized title, nothing else.";
        return [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates concise, optimized titles grounded only in the supplied content. You never invent facts.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
    }

    /**
     * Build message array for meta description generation (no API call).
     */
    public function build_desc_messages( $content, $current_description = '', $focus_keyword = '' ) {
        $prompt = "You are an SEO expert. Generate an optimized meta description for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Between 150-160 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Include a subtle call-to-action\n";
        $prompt .= "- Accurately summarize the content\n";
        $prompt .= "- Make it compelling to increase click-through rate\n\n";
        if ( ! empty( $focus_keyword ) )       $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if ( ! empty( $current_description ) ) $prompt .= "Current Description: {$current_description}\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );
        $prompt .= "Content:\n{$clean}\n\n" . self::grounding_rules() . "Respond with ONLY the meta description, nothing else.";
        return [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates concise, compelling meta descriptions grounded only in the supplied content. You never invent facts.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
    }

    /**
     * Build message array for image alt-text generation (no API call).
     *
     * Used by the parallel (curl_multi) path. The image bytes are embedded as a
     * base64 block so the model actually SEES the picture — passing only the URL
     * would make it guess from the file slug and invent contents that aren't
     * there. When that isn't possible this returns a WP_Error so the caller can
     * skip the image rather than shipping a hallucinated description.
     *
     * @param string $image_url
     * @param string $page_context
     * @param string $focus_keyword
     * @return array|WP_Error
     */
    public function build_alt_messages( $image_url, $page_context = '', $focus_keyword = '' ) {
        $instruction  = "Look at this image and write descriptive, SEO-friendly alt text for it.\n\nRequirements:\n";
        $instruction .= "- Maximum 125 characters\n- Describe what is actually visible in the image\n";
        $instruction .= "- Include the focus keyword naturally only if it genuinely describes the image\n";
        $instruction .= "- Do NOT start with 'Image of' or 'Picture of'\n";
        $instruction .= "- Useful for screen readers\n";
        if ( ! empty( $page_context ) )  $instruction .= 'Page Context: ' . wp_trim_words( $page_context, 100 ) . "\n";
        if ( ! empty( $focus_keyword ) ) $instruction .= "Focus Keyword: {$focus_keyword}\n";
        $instruction .= "\nRespond with ONLY the alt text, nothing else.";

        if ( ! class_exists( 'SSF_AI' ) ) {
            return new WP_Error( 'ssf_missing_ai', __( 'AI factory unavailable.', 'smart-seo-fixer' ) );
        }

        if ( ! $this->supports_vision() ) {
            return SSF_AI::vision_error(
                'model_not_vision',
                sprintf(
                    /* translators: %s: Bedrock model ID */
                    __( 'The selected Bedrock model (%s) cannot analyse images.', 'smart-seo-fixer' ),
                    $this->get_model_id()
                )
            );
        }

        $img = SSF_AI::fetch_image_as_base64( $image_url );
        if ( is_wp_error( $img ) ) {
            if ( class_exists( 'SSF_Logger' ) ) {
                SSF_Logger::warning( 'Alt-text (parallel): image bytes unreadable, skipping: ' . $img->get_error_message(), 'ai' );
            }
            return SSF_AI::vision_error(
                'image_unreadable',
                __( 'The image file could not be read, so the AI had nothing to look at.', 'smart-seo-fixer' ),
                $img->get_error_message()
            );
        }

        return [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates accessible, descriptive image alt text based on what you see in the image.' ],
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $img['media_type'],
                            'data'       => $img['data'],
                        ],
                    ],
                    [ 'type' => 'text', 'text' => $instruction ],
                ],
            ],
        ];
    }

    /**
     * Generate SEO title
     */
    public function generate_title( $content, $current_title = '', $focus_keyword = '' ) {
        $prompt = "You are an SEO expert. Generate an optimized SEO title for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Maximum 60 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Make it compelling and click-worthy\n";
        $prompt .= "- Avoid clickbait, be accurate to the content\n\n";
        if ( ! empty( $focus_keyword ) ) $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if ( ! empty( $current_title ) ) $prompt .= "Current Title: {$current_title}\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );
        $prompt .= "Content:\n{$clean}\n\n" . self::grounding_rules() . "Respond with ONLY the optimized title, nothing else.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates concise, optimized titles grounded only in the supplied content. You never invent facts.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        $result = $this->request( $messages, 100, 0.3 );
        if ( is_wp_error( $result ) ) return $result;
        return trim( $result, " \t\n\r\0\x0B\"'" );
    }

    /**
     * Generate meta description
     */
    public function generate_meta_description( $content, $current_description = '', $focus_keyword = '' ) {
        $prompt = "You are an SEO expert. Generate an optimized meta description for the following content.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Between 150-160 characters (critical for Google display)\n";
        $prompt .= "- Include the focus keyword naturally if provided\n";
        $prompt .= "- Include a subtle call-to-action\n";
        $prompt .= "- Accurately summarize the content\n";
        $prompt .= "- Make it compelling to increase click-through rate\n\n";
        if ( ! empty( $focus_keyword ) )       $prompt .= "Focus Keyword: {$focus_keyword}\n\n";
        if ( ! empty( $current_description ) ) $prompt .= "Current Description: {$current_description}\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );
        $prompt .= "Content:\n{$clean}\n\n" . self::grounding_rules() . "Respond with ONLY the meta description, nothing else.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates concise, compelling meta descriptions grounded only in the supplied content. You never invent facts.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        $result = $this->request( $messages, 200, 0.3 );
        if ( is_wp_error( $result ) ) return $result;
        return trim( $result, " \t\n\r\0\x0B\"'" );
    }

    /**
     * Generate image alt text.
     *
     * The actual image bytes are sent as a base64 multimodal block so the model
     * can SEE the image and describe it accurately. If vision is unavailable
     * — a non-Claude family on Bedrock, or unreadable image bytes — this fails
     * with SSF_AI::VISION_ERROR rather than prompting the model with only a
     * URL. A text-only model asked to describe a picture it cannot see invents
     * the contents, and a confident wrong description is worse than the
     * deterministic filename fallback the caller uses instead.
     */
    public function generate_alt_text( $image_url, $page_context = '', $focus_keyword = '' ) {
        if ( ! class_exists( 'SSF_AI' ) ) {
            return new WP_Error( 'ssf_missing_ai', __( 'AI factory unavailable.', 'smart-seo-fixer' ) );
        }

        if ( ! $this->supports_vision() ) {
            return SSF_AI::vision_error(
                'model_not_vision',
                sprintf(
                    /* translators: %s: Bedrock model ID */
                    __( 'The selected Bedrock model (%s) cannot analyse images. Choose a Claude model to get real descriptions.', 'smart-seo-fixer' ),
                    $this->get_model_id()
                )
            );
        }

        $img = SSF_AI::fetch_image_as_base64( $image_url );
        if ( is_wp_error( $img ) ) {
            if ( class_exists( 'SSF_Logger' ) ) {
                SSF_Logger::warning( 'Alt-text: image bytes unreadable, skipping AI: ' . $img->get_error_message(), 'ai' );
            }
            return SSF_AI::vision_error(
                'image_unreadable',
                __( 'The image file could not be read, so the AI had nothing to look at.', 'smart-seo-fixer' ),
                $img->get_error_message()
            );
        }

        $instruction  = "Look at this image and write descriptive, SEO-friendly alt text for it.\n\nRequirements:\n";
        $instruction .= "- Maximum 125 characters\n- Describe what is actually visible in the image\n";
        $instruction .= "- Include the focus keyword naturally only if it genuinely describes the image\n";
        $instruction .= "- Do NOT start with 'Image of' or 'Picture of'\n";
        $instruction .= "- Useful for screen readers\n";
        if ( ! empty( $page_context ) )  $instruction .= 'Page Context: ' . wp_trim_words( $page_context, 100 ) . "\n";
        if ( ! empty( $focus_keyword ) ) $instruction .= "Focus Keyword: {$focus_keyword}\n";
        $instruction .= "\nRespond with ONLY the alt text, nothing else.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO expert that generates accessible, descriptive image alt text based on what you see in the image.' ],
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $img['media_type'],
                            'data'       => $img['data'],
                        ],
                    ],
                    [ 'type' => 'text', 'text' => $instruction ],
                ],
            ],
        ];

        $result = $this->request( $messages, 150, 0.5 );
        if ( is_wp_error( $result ) ) return $result;
        return trim( $result, " \t\n\r\0\x0B\"'" );
    }

    /**
     * Analyze content and suggest improvements
     */
    public function analyze_content( $content, $title = '', $focus_keyword = '' ) {
        $prompt = "You are an SEO expert. Analyze this content and provide specific, actionable SEO recommendations.\n\n";
        if ( ! empty( $title ) )         $prompt .= "Title: {$title}\n";
        if ( ! empty( $focus_keyword ) ) $prompt .= "Focus Keyword: {$focus_keyword}\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 1000 );
        $prompt .= "\nContent:\n{$clean}\n\n";
        $prompt .= "Analyze and provide feedback on: keyword usage, content structure, readability, content length, internal/external linking.\n\n";
        $prompt .= 'Format as JSON: {"score":0-100,"issues":["..."],"suggestions":["..."],"strengths":["..."]}';

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO expert. Respond only with valid JSON.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        $response = $this->request( $messages, 1000, 0.5 );
        if ( is_wp_error( $response ) ) return $response;
        $response = preg_replace( '/```json\s*/', '', $response );
        $response = preg_replace( '/```\s*/',     '', $response );
        $data = json_decode( trim( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Failed to parse AI response.', 'smart-seo-fixer' ) );
        }
        return $data;
    }

    /**
     * Generate content outline/structure
     */
    public function generate_outline( $topic, $focus_keyword = '' ) {
        $prompt  = "You are an SEO expert. Create a comprehensive content outline for this topic.\n\nTopic: {$topic}\n";
        if ( ! empty( $focus_keyword ) ) $prompt .= "Focus Keyword: {$focus_keyword}\n";
        $prompt .= "\nGenerate an SEO-optimized content outline.\n\n";
        $prompt .= "IMPORTANT: Do NOT include prefixes like 'H1:', 'H2:', 'H3:' in the text. Just write the actual heading text.\n\n";
        $prompt .= 'Format as JSON: {"title":"Your Compelling Title Here","sections":[{"heading":"First Section Heading","subsections":["Sub Topic One","Sub Topic Two"]}],"suggested_word_count":1500}';
        $prompt .= "\n\nProvide 4-6 sections, each with 2-3 subsections. No descriptions needed.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO content strategist. Respond only with valid JSON.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        $response = $this->request( $messages, 1500, 0.7 );
        if ( is_wp_error( $response ) ) return $response;
        $response = preg_replace( '/```json\s*/', '', $response );
        $response = preg_replace( '/```\s*/',     '', $response );
        return json_decode( trim( $response ), true );
    }

    /**
     * Suggest focus keywords
     */
    public function suggest_keywords( $content, $title = '' ) {
        $prompt = "You are an SEO keyword research expert. Suggest the best focus keywords for this content.\n\n";
        if ( ! empty( $title ) ) $prompt .= "Title: {$title}\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 500 );
        $prompt .= "Content:\n{$clean}\n\n";
        $prompt .= "CRITICAL: Every keyword you suggest MUST appear as a VERBATIM substring in the title or content above (case-insensitive). Do NOT invent new phrases.\n\n";
        $prompt .= "Suggest 5 focus keywords with primary, secondary, and long-tail variations.\n\n";
        $prompt .= 'Format as JSON: {"primary":"keyword","secondary":["kw1","kw2"],"long_tail":["phrase1","phrase2"]}';

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an SEO keyword expert. Respond only with valid JSON. Every keyword must be a verbatim phrase from the supplied content — you never invent phrases.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        $response = $this->request( $messages, 500, 0.3 );
        if ( is_wp_error( $response ) ) return $response;
        $response = preg_replace( '/```json\s*/', '', $response );
        $response = preg_replace( '/```\s*/',     '', $response );
        return json_decode( trim( $response ), true );
    }

    /**
     * Generate schema markup suggestions
     */
    public function suggest_schema( $content, $post_type = 'article', $post_url = '', $site_url = '', $post_title = '', $site_name = '', $logo_url = '' ) {
        $prompt  = "You are a structured data expert. Suggest additional Schema.org markup for this content.\n\n";
        $prompt .= "=== REAL SITE DATA (use these exact values, do NOT make up alternatives) ===\n";
        if ( ! empty( $site_name ) )  $prompt .= "Organization Name: {$site_name}\n";
        if ( ! empty( $site_url ) )   $prompt .= "Site URL: {$site_url}\n";
        if ( ! empty( $logo_url ) )   $prompt .= "Logo URL: {$logo_url}\n";
        if ( ! empty( $post_url ) )   $prompt .= "Post URL: {$post_url}\n";
        if ( ! empty( $post_title ) ) $prompt .= "Title: {$post_title}\n";
        $prompt .= "Content Type: {$post_type}\n=== END SITE DATA ===\n\n";
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 300 );
        $prompt .= "Content:\n{$clean}\n\nCRITICAL RULES:\n";
        $prompt .= "1. Do NOT suggest Article, BlogPosting, NewsArticle, WebPage, BreadcrumbList, Organization, or WebSite schemas.\n";
        $prompt .= "2. Use ONLY the real URLs provided. NEVER guess or fabricate URLs.\n";
        $prompt .= "3. For any logo/image, use ONLY the Logo URL from SITE DATA. Omit if not provided.\n";
        $prompt .= "4. Do NOT include articleBody.\n";
        $prompt .= '5. If no additional schema adds value, respond with exactly: {"_no_schema": true}' . "\n\n";
        $prompt .= "Format as valid JSON-LD with @context and @type.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are a Schema.org expert. Respond only with valid JSON-LD. Never use placeholder or example.com URLs.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        return $this->request( $messages, 1000, 0.5 );
    }

    /**
     * Build the message array for an internal-link anchor search (no API call).
     * Use with request_multi() for concurrent processing.
     */
    public function build_internal_link_messages( $source_content, $target_title, $target_url, $target_summary = '' ) {
        $clean = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $source_content ) ), 800 );

        $prompt  = "You are an SEO expert specializing in internal linking strategy.\n\n";
        $prompt .= "TARGET PAGE TO LINK TO:\n- Title: {$target_title}\n- URL: {$target_url}\n";
        if ( ! empty( $target_summary ) ) $prompt .= "- Topic: {$target_summary}\n";
        $prompt .= "\nSOURCE CONTENT (find a natural anchor text phrase in here):\n{$clean}\n\n";
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Find an existing phrase (2-6 words) that naturally relates to the target page topic\n";
        $prompt .= "2. The phrase MUST exist EXACTLY as-is in the source content\n";
        $prompt .= "3. Do NOT choose phrases already inside <a> tags\n";
        $prompt .= "4. Prefer body content, not headings\n\n";
        $prompt .= 'Respond with ONLY valid JSON: {"found":true,"anchor_text":"exact phrase","context":"...surrounding sentence..."}' . "\n";
        $prompt .= 'If no natural fit exists: {"found":false}' . "\nDo NOT wrap in code blocks.";

        return [
            [ 'role' => 'system', 'content' => 'You are an internal linking expert. Respond only with valid JSON. Never wrap in code blocks.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
    }

    /**
     * Parse a raw internal-link placement response (handles ```json fences).
     * @param string|WP_Error $response
     * @return array|WP_Error
     */
    public function parse_internal_link_placement( $response ) {
        if ( is_wp_error( $response ) ) return $response;
        $response = preg_replace( '/```json\s*/', '', (string) $response );
        $response = preg_replace( '/```\s*/',     '', $response );
        $data = json_decode( trim( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Failed to parse AI response for internal link.', 'smart-seo-fixer' ) );
        }
        return $data;
    }

    /**
     * Find a natural place in existing content to insert an internal link
     */
    public function find_internal_link_placement( $source_content, $target_title, $target_url, $target_summary = '' ) {
        $messages = $this->build_internal_link_messages( $source_content, $target_title, $target_url, $target_summary );
        $response = $this->request( $messages, 300, 0.3 );
        return $this->parse_internal_link_placement( $response );
    }

    /**
     * Improve readability of content
     */
    public function improve_readability( $content ) {
        $prompt  = "You are a content editor. Improve the readability of this content while maintaining SEO value.\n\n";
        $prompt .= "Content:\n{$content}\n\nImprovements: shorter sentences, active voice, clearer structure, better transitions. Maintain all keywords.\n\n";
        $prompt .= "Return ONLY the improved content, nothing else.";

        $messages = [
            [ 'role' => 'system', 'content' => 'You are an expert content editor focused on readability.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ];
        return $this->request( $messages, 2000, 0.6 );
    }
}
