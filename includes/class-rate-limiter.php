<?php
/**
 * API Rate Limiter with Retry
 * 
 * Prevents hitting API rate limits by throttling requests and
 * automatically retrying with exponential backoff when limits are reached.
 * 
 * Uses WordPress transients for state tracking — no additional DB tables needed.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Rate_Limiter {
    
        /**
     * Default rate limit configurations per API
     */
    private static $defaults = [
        'openai' => [
            'requests_per_minute' => 30,
            'max_retries'         => 3,
            'base_delay'          => 2,     // seconds
            'max_delay'           => 60,    // seconds
        ],
        'bedrock' => [
            'requests_per_minute' => 20,    // conservative default; increase per your Bedrock quota
            'max_retries'         => 3,
            'base_delay'          => 2,
            'max_delay'           => 60,
        ],
        'gsc' => [
            'requests_per_minute' => 50,
            'max_retries'         => 3,
            'base_delay'          => 1,
            'max_delay'           => 30,
        ],
    ];
    
    /**
     * Check if we can make a request for the given API, and wait if needed.
     * Returns true when safe to proceed, or WP_Error after max retries.
     * 
     * @param string $api_name  'openai' or 'gsc'
     * @return true|WP_Error
     */
    public static function throttle($api_name) {
        $config = self::get_config($api_name);
        $key = 'ssf_rl_' . $api_name;
        
        $window = get_transient($key);
        if ($window === false) {
            $window = ['count' => 0, 'window_start' => time()];
        }
        
        $elapsed = time() - $window['window_start'];
        
        // If more than 60 seconds passed, reset the window
        if ($elapsed >= 60) {
            $window = ['count' => 0, 'window_start' => time()];
        }
        
        // If under the limit, allow and increment
        if ($window['count'] < $config['requests_per_minute']) {
            $window['count']++;
            set_transient($key, $window, 120);
            return true;
        }
        
        // Rate limit reached — wait until the window resets
        $wait_seconds = 60 - $elapsed;
        if ($wait_seconds > 0 && $wait_seconds <= 60) {
            if (class_exists('SSF_Logger')) {
                SSF_Logger::warning(sprintf(
                    'Rate limit reached for %s (%d/%d). Waiting %ds.',
                    $api_name, $window['count'], $config['requests_per_minute'], $wait_seconds
                ), 'rate_limit');
            }
            sleep($wait_seconds);
        }
        
        // Reset window after waiting
        $window = ['count' => 1, 'window_start' => time()];
        set_transient($key, $window, 120);
        
        return true;
    }
    
    /**
     * Execute a callable with rate limiting and automatic retry on failure.
     * 
     * @param string   $api_name  'openai' or 'gsc'
     * @param callable $callback  The API call to make (should return value or WP_Error)
     * @return mixed              Result from callback, or WP_Error after all retries exhausted
     */
    public static function execute($api_name, $callback) {
        $config = self::get_config($api_name);
        $max_retries = $config['max_retries'];
        $base_delay  = $config['base_delay'];
        $max_delay   = $config['max_delay'];
        
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            // Throttle before each attempt
            $throttle = self::throttle($api_name);
            if (is_wp_error($throttle)) {
                return $throttle;
            }
            
            $result = call_user_func($callback);
            
            // Success — return result
            if (!is_wp_error($result)) {
                return $result;
            }
            
            $error_code = $result->get_error_code();
            $error_msg  = $result->get_error_message();
            
            // Check if this is a retryable error
            if (!self::is_retryable($error_code, $error_msg)) {
                // Non-retryable error (auth failure, invalid input, etc.)
                return $result;
            }
            
            // Don't retry if this was the last attempt
            if ($attempt >= $max_retries) {
                break;
            }
            
            // Exponential backoff: base_delay * 2^attempt, capped at max_delay
            $delay = min($base_delay * pow(2, $attempt), $max_delay);
            
            // Add jitter (random 0-25% extra) to prevent thundering herd
            $jitter = $delay * (mt_rand(0, 25) / 100);
            $total_delay = (int) ($delay + $jitter);
            
            if (class_exists('SSF_Logger')) {
                SSF_Logger::warning(sprintf(
                    '%s API retry %d/%d after %ds: %s',
                    strtoupper($api_name),
                    $attempt + 1,
                    $max_retries,
                    $total_delay,
                    $error_msg
                ), 'rate_limit');
            }
            
            sleep($total_delay);
        }
        
        // All retries exhausted
        if (class_exists('SSF_Logger')) {
            SSF_Logger::error(sprintf(
                '%s API failed after %d retries: %s',
                strtoupper($api_name),
                $max_retries,
                $result->get_error_message()
            ), 'rate_limit');
        }
        
        return new WP_Error(
            'rate_limit_exhausted',
            sprintf(
                __('API request failed after %d retries: %s', 'smart-seo-fixer'),
                $max_retries,
                $result->get_error_message()
            )
        );
    }
    
    /**
     * Determine if an error is retryable
     * 
     * Retryable: rate limits (429), server errors (5xx), timeouts, network issues
     * Non-retryable: auth errors (401/403), bad request (400), not found (404)
     */
    private static function is_retryable($error_code, $error_msg) {
        // WP HTTP errors (timeout, connection refused) are retryable
        $retryable_wp_codes = ['http_request_failed', 'http_request_not_executed'];
        if (in_array($error_code, $retryable_wp_codes)) {
            return true;
        }
        
        // Rate limit keywords
        $rate_limit_phrases = ['rate limit', 'too many requests', '429', 'quota', 'throttl'];
        foreach ($rate_limit_phrases as $phrase) {
            if (stripos($error_msg, $phrase) !== false) {
                return true;
            }
        }
        
        // Server error keywords
        $server_error_phrases = ['500', '502', '503', '504', 'server error', 'gateway', 'timeout', 'timed out'];
        foreach ($server_error_phrases as $phrase) {
            if (stripos($error_msg, $phrase) !== false) {
                return true;
            }
        }
        
                // OpenAI / Bedrock-specific retryable
        if ($error_code === 'api_error' && stripos($error_msg, 'overloaded') !== false) {
            return true;
        }
        // Bedrock throttling
        if (stripos($error_msg, 'ThrottlingException') !== false || stripos($error_msg, 'ServiceUnavailableException') !== false) {
            return true;
        }
        
        // GSC token refresh is handled internally, but if it fails we can retry
        if ($error_code === 'gsc_refresh_error') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get rate limit config for an API
     */
    private static function get_config($api_name) {
        return self::$defaults[$api_name] ?? self::$defaults['openai'];
    }
    
    /**
     * Get current usage stats for an API
     * 
     * @param string $api_name
     * @return array ['count' => int, 'limit' => int, 'remaining' => int, 'resets_in' => int]
     */
    public static function get_usage($api_name) {
        $config = self::get_config($api_name);
        $key = 'ssf_rl_' . $api_name;
        
        $window = get_transient($key);
        if ($window === false) {
            return [
                'count'     => 0,
                'limit'     => $config['requests_per_minute'],
                'remaining' => $config['requests_per_minute'],
                'resets_in' => 0,
            ];
        }
        
        $elapsed = time() - $window['window_start'];
        $resets_in = max(0, 60 - $elapsed);
        
        return [
            'count'     => $window['count'],
            'limit'     => $config['requests_per_minute'],
            'remaining' => max(0, $config['requests_per_minute'] - $window['count']),
            'resets_in' => $resets_in,
        ];
    }
    
    /**
     * Reset the rate limiter for an API (useful after config changes)
     */
    public static function reset($api_name) {
        delete_transient('ssf_rl_' . $api_name);
    }
}
