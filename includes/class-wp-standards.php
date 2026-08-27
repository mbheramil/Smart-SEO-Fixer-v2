<?php
/**
 * WordPress Coding Standards Checker
 * 
 * Lightweight self-audit of plugin code for common WP coding standard issues.
 * NOT a replacement for phpcs — this is a quick health check visible in admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_WP_Standards {
    
    /**
     * Run a standards audit on the plugin files
     */
    public static function audit() {
        $results = [
            'files_checked' => 0,
            'issues'        => [],
            'summary'       => [],
            'score'         => 100,
        ];
        
        $plugin_dir = SSF_PLUGIN_DIR;
        $files = self::get_php_files($plugin_dir);
        $results['files_checked'] = count($files);
        
        foreach ($files as $file) {
            $relative = str_replace($plugin_dir, '', $file);
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            $file_issues = self::check_file($relative, $content, $lines);
            if (!empty($file_issues)) {
                $results['issues'][$relative] = $file_issues;
            }
        }
        
        // Summarize
        $total_issues = 0;
        $by_severity = ['error' => 0, 'warning' => 0, 'info' => 0];
        
        foreach ($results['issues'] as $file_issues) {
            foreach ($file_issues as $issue) {
                $total_issues++;
                $by_severity[$issue['severity']]++;
            }
        }
        
        $results['summary'] = [
            'total_issues' => $total_issues,
            'errors'       => $by_severity['error'],
            'warnings'     => $by_severity['warning'],
            'info'         => $by_severity['info'],
            'clean_files'  => $results['files_checked'] - count($results['issues']),
        ];
        
        // Score: deduct points per issue
        $results['score'] = max(0, 100 - ($by_severity['error'] * 5) - ($by_severity['warning'] * 2) - ($by_severity['info'] * 1));
        
        return $results;
    }
    
    /**
     * Check a single file for standards issues
     */
    private static function check_file($filename, $content, $lines) {
        $issues = [];
        
        // 1. Direct database queries without prepare
        if (preg_match_all('/\$wpdb->(query|get_var|get_row|get_results|get_col)\s*\(\s*["\']/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                // Check if ->prepare is used nearby
                $context = substr($content, max(0, $match[1] - 100), 200);
                if (strpos($context, 'prepare') === false) {
                    $issues[] = [
                        'severity' => 'warning',
                        'line'     => $line,
                        'rule'     => 'DB.PreparedSQL',
                        'message'  => __('Direct database query without $wpdb->prepare(). Use prepared statements to prevent SQL injection.', 'smart-seo-fixer'),
                    ];
                }
            }
        }
        
        // 2. Missing nonce verification in AJAX handlers
        if (preg_match_all('/function\s+\w+.*\{[^}]*\$_POST\b/s', $content, $matches)) {
            // This is a rough heuristic; more detailed checking would need AST parsing
        }
        
        // 3. Direct $_GET/$_POST/$_REQUEST without sanitization
        if (preg_match_all('/\$_(GET|POST|REQUEST)\s*\[\s*[\'"][^\'"]+[\'"]\s*\](?!\s*\?\?)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $context_after = substr($content, $match[1], 200);
                // Check if it's wrapped in sanitize/esc/intval/absint
                if (!preg_match('/^\$_(GET|POST|REQUEST)\[.*?\].*?(sanitize|esc_|intval|absint|wp_unslash|check_ajax)/s', $context_after)) {
                    // Skip if it's inside a ternary or null coalesce that applies sanitization
                    $line_content = $lines[$line - 1] ?? '';
                    if (!preg_match('/(sanitize|esc_|intval|absint)/', $line_content)) {
                        $issues[] = [
                            'severity' => 'info',
                            'line'     => $line,
                            'rule'     => 'Security.ValidatedInput',
                            'message'  => __('Superglobal accessed directly. Ensure it is sanitized before use.', 'smart-seo-fixer'),
                        ];
                    }
                }
            }
        }
        
        // 4. echo without escaping
        if (preg_match_all('/echo\s+["\']</', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $line_content = $lines[$line - 1] ?? '';
                if (strpos($line_content, 'esc_') === false && strpos($line_content, 'wp_kses') === false) {
                    $issues[] = [
                        'severity' => 'info',
                        'line'     => $line,
                        'rule'     => 'Security.EscapeOutput',
                        'message'  => __('HTML output via echo. Verify all dynamic values are escaped with esc_html(), esc_attr(), etc.', 'smart-seo-fixer'),
                    ];
                }
            }
        }
        
        // 5. File missing ABSPATH check
        if (strpos($filename, '.php') !== false && strpos($content, "defined('ABSPATH')") === false && strpos($content, 'defined("ABSPATH")') === false) {
            if (strpos($filename, 'uninstall.php') === false) {
                $issues[] = [
                    'severity' => 'error',
                    'line'     => 1,
                    'rule'     => 'Security.DirectAccess',
                    'message'  => __('Missing direct access prevention. Add: if (!defined(\'ABSPATH\')) exit;', 'smart-seo-fixer'),
                ];
            }
        }
        
        // 6. Deprecated WordPress functions
        $deprecated = [
            'get_bloginfo(\'wpurl\')' => 'site_url()',
            'get_bloginfo(\'siteurl\')' => 'home_url()',
            'get_bloginfo("wpurl")' => 'site_url()',
            'get_bloginfo("siteurl")' => 'home_url()',
            'mysql2date' => 'wp_date()',
            'get_userdatabylogin' => 'get_user_by()',
        ];
        
        foreach ($deprecated as $old => $new) {
            if (strpos($content, $old) !== false) {
                $issues[] = [
                    'severity' => 'warning',
                    'line'     => 0,
                    'rule'     => 'WP.DeprecatedFunction',
                    'message'  => sprintf(__('Deprecated function "%s" found. Use "%s" instead.', 'smart-seo-fixer'), $old, $new),
                ];
            }
        }
        
        // 7. Use of error_log (should use SSF_Logger)
        if (preg_match_all('/\berror_log\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $issues[] = [
                    'severity' => 'info',
                    'line'     => $line,
                    'rule'     => 'Plugin.UseLogger',
                    'message'  => __('Use SSF_Logger instead of error_log() for consistent plugin logging.', 'smart-seo-fixer'),
                ];
            }
        }
        
        // 8. Check for PHP short tags
        if (preg_match('/<\?\s(?!php)/', $content)) {
            $issues[] = [
                'severity' => 'error',
                'line'     => 0,
                'rule'     => 'PHP.ShortTags',
                'message'  => __('PHP short tags detected. Use full <?php tags for compatibility.', 'smart-seo-fixer'),
            ];
        }
        
        return $issues;
    }
    
    /**
     * Get all PHP files in the plugin directory
     */
    private static function get_php_files($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip vendor/node_modules if they exist
                $path = $file->getPathname();
                if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false) {
                    continue;
                }
                $files[] = $path;
            }
        }
        
        return $files;
    }
}
