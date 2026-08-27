<?php
/**
 * Content Readability Scorer
 * 
 * Analyzes post content for readability using Flesch-Kincaid,
 * sentence/paragraph metrics, and actionable suggestions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSF_Readability {
    
    /**
     * Analyze content readability
     * 
     * @param string $content Raw post content (HTML)
     * @return array Readability report
     */
    public static function analyze($content) {
        // Strip HTML, shortcodes, and normalize whitespace
        $text = wp_strip_all_tags(strip_shortcodes($content));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        if (empty($text)) {
            return self::empty_report();
        }
        
        // Core metrics
        $words      = self::count_words($text);
        $sentences  = self::count_sentences($text);
        $syllables  = self::count_syllables($text);
        $paragraphs = self::count_paragraphs($content);
        $characters = mb_strlen($text);
        
        // Avoid division by zero
        if ($words === 0 || $sentences === 0) {
            return self::empty_report();
        }
        
        // Flesch Reading Ease
        $flesch = self::flesch_reading_ease($words, $sentences, $syllables);
        
        // Flesch-Kincaid Grade Level
        $grade_level = self::flesch_kincaid_grade($words, $sentences, $syllables);
        
        // Additional metrics
        $avg_sentence_length  = round($words / $sentences, 1);
        $avg_word_length      = round($characters / $words, 1);
        $avg_paragraph_length = $paragraphs > 0 ? round($sentences / $paragraphs, 1) : $sentences;
        $long_sentences       = self::count_long_sentences($text);
        $passive_voice        = self::estimate_passive_voice($text);
        $transition_words     = self::count_transition_words($text);
        
        // Overall readability score (0-100)
        $score = self::calculate_score($flesch, $avg_sentence_length, $long_sentences, $sentences, $passive_voice, $transition_words);
        
        // Grade label
        $grade = self::score_to_grade($score);
        
        // Suggestions
        $suggestions = self::generate_suggestions($flesch, $avg_sentence_length, $long_sentences, $sentences, $paragraphs, $avg_paragraph_length, $passive_voice, $transition_words, $words);
        
        return [
            'score'                => $score,
            'grade'                => $grade,
            'flesch_reading_ease'  => round($flesch, 1),
            'flesch_kincaid_grade' => round($grade_level, 1),
            'word_count'           => $words,
            'sentence_count'       => $sentences,
            'paragraph_count'      => $paragraphs,
            'avg_sentence_length'  => $avg_sentence_length,
            'avg_word_length'      => $avg_word_length,
            'avg_paragraph_length' => $avg_paragraph_length,
            'long_sentences'       => $long_sentences,
            'passive_voice_pct'    => $passive_voice,
            'transition_word_pct'  => $transition_words,
            'suggestions'          => $suggestions,
        ];
    }
    
    /**
     * Empty report for content with no text
     */
    private static function empty_report() {
        return [
            'score' => 0, 'grade' => 'N/A',
            'flesch_reading_ease' => 0, 'flesch_kincaid_grade' => 0,
            'word_count' => 0, 'sentence_count' => 0, 'paragraph_count' => 0,
            'avg_sentence_length' => 0, 'avg_word_length' => 0, 'avg_paragraph_length' => 0,
            'long_sentences' => 0, 'passive_voice_pct' => 0, 'transition_word_pct' => 0,
            'suggestions' => [['type' => 'error', 'text' => __('No text content found to analyze.', 'smart-seo-fixer')]],
        ];
    }
    
    /**
     * Flesch Reading Ease score
     * Higher = easier. 60-70 is ideal for web content.
     */
    private static function flesch_reading_ease($words, $sentences, $syllables) {
        return 206.835 - (1.015 * ($words / $sentences)) - (84.6 * ($syllables / $words));
    }
    
    /**
     * Flesch-Kincaid Grade Level
     * Lower = easier. Target ~7-8 for web content.
     */
    private static function flesch_kincaid_grade($words, $sentences, $syllables) {
        return (0.39 * ($words / $sentences)) + (11.8 * ($syllables / $words)) - 15.59;
    }
    
    /**
     * Count words
     */
    private static function count_words($text) {
        return str_word_count($text);
    }
    
    /**
     * Count sentences (split on . ! ? and common abbreviations)
     */
    private static function count_sentences($text) {
        // Remove common abbreviations that end with periods
        $abbrevs = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.', 'Sr.', 'Jr.', 'vs.', 'etc.', 'e.g.', 'i.e.', 'Inc.', 'Ltd.', 'Co.', 'U.S.', 'U.K.'];
        foreach ($abbrevs as $abbr) {
            $text = str_replace($abbr, str_replace('.', '', $abbr), $text);
        }
        
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return max(1, count($sentences));
    }
    
    /**
     * Count syllables (English approximation)
     */
    private static function count_syllables($text) {
        $words_arr = str_word_count($text, 1);
        $total = 0;
        
        foreach ($words_arr as $word) {
            $total += self::syllable_count(strtolower($word));
        }
        
        return max(1, $total);
    }
    
    /**
     * Estimate syllables for a single word
     */
    private static function syllable_count($word) {
        $word = preg_replace('/[^a-z]/', '', $word);
        if (strlen($word) <= 3) return 1;
        
        // Remove silent e
        $word = preg_replace('/e$/', '', $word);
        
        // Count vowel groups
        preg_match_all('/[aeiouy]+/', $word, $matches);
        $count = count($matches[0]);
        
        return max(1, $count);
    }
    
    /**
     * Count paragraphs from HTML content
     */
    private static function count_paragraphs($content) {
        // Count <p> tags or double newlines
        $p_tags = preg_match_all('/<p[\s>]/i', $content);
        if ($p_tags > 0) return $p_tags;
        
        $blocks = preg_split('/\n\s*\n/', strip_tags($content), -1, PREG_SPLIT_NO_EMPTY);
        return max(1, count($blocks));
    }
    
    /**
     * Count sentences longer than 20 words
     */
    private static function count_long_sentences($text) {
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $long = 0;
        
        foreach ($sentences as $sentence) {
            if (str_word_count($sentence) > 20) {
                $long++;
            }
        }
        
        return $long;
    }
    
    /**
     * Estimate passive voice percentage
     */
    private static function estimate_passive_voice($text) {
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = count($sentences);
        if ($total === 0) return 0;
        
        $passive = 0;
        $passive_patterns = [
            '/\b(is|are|was|were|been|being|be)\s+\w+ed\b/i',
            '/\b(is|are|was|were|been|being|be)\s+\w+en\b/i',
            '/\b(has|have|had)\s+been\s+\w+ed\b/i',
            '/\b(will|shall|can|could|would|should|may|might)\s+be\s+\w+ed\b/i',
        ];
        
        foreach ($sentences as $sentence) {
            foreach ($passive_patterns as $pattern) {
                if (preg_match($pattern, $sentence)) {
                    $passive++;
                    break;
                }
            }
        }
        
        return round(($passive / $total) * 100, 1);
    }
    
    /**
     * Count transition word usage
     */
    private static function count_transition_words($text) {
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = count($sentences);
        if ($total === 0) return 0;
        
        $transitions = [
            'however', 'therefore', 'furthermore', 'moreover', 'additionally',
            'consequently', 'meanwhile', 'nevertheless', 'nonetheless', 'otherwise',
            'similarly', 'likewise', 'in addition', 'for example', 'for instance',
            'in other words', 'on the other hand', 'in contrast', 'as a result',
            'in conclusion', 'in summary', 'first', 'second', 'third', 'finally',
            'also', 'besides', 'yet', 'still', 'then', 'next', 'instead',
            'specifically', 'particularly', 'notably', 'importantly',
        ];
        
        $with_transitions = 0;
        $text_lower = strtolower($text);
        
        foreach ($sentences as $sentence) {
            $s = strtolower(trim($sentence));
            foreach ($transitions as $tw) {
                if (strpos($s, $tw) !== false) {
                    $with_transitions++;
                    break;
                }
            }
        }
        
        return round(($with_transitions / $total) * 100, 1);
    }
    
    /**
     * Calculate overall readability score (0-100)
     */
    private static function calculate_score($flesch, $avg_sentence_length, $long_sentences, $total_sentences, $passive_pct, $transition_pct) {
        $score = 0;
        
        // Flesch Reading Ease (40% weight) - ideal is 60-70
        if ($flesch >= 60 && $flesch <= 70) {
            $score += 40;
        } elseif ($flesch >= 50 && $flesch < 60) {
            $score += 32;
        } elseif ($flesch >= 70 && $flesch <= 80) {
            $score += 35;
        } elseif ($flesch >= 30 && $flesch < 50) {
            $score += 20;
        } elseif ($flesch > 80) {
            $score += 28;
        } else {
            $score += 10;
        }
        
        // Sentence length (20% weight) - ideal is 15-20 words
        if ($avg_sentence_length >= 12 && $avg_sentence_length <= 20) {
            $score += 20;
        } elseif ($avg_sentence_length > 20 && $avg_sentence_length <= 25) {
            $score += 14;
        } elseif ($avg_sentence_length < 12) {
            $score += 12;
        } else {
            $score += 6;
        }
        
        // Long sentences (15% weight)
        if ($total_sentences > 0) {
            $long_pct = ($long_sentences / $total_sentences) * 100;
            if ($long_pct <= 15) {
                $score += 15;
            } elseif ($long_pct <= 25) {
                $score += 10;
            } elseif ($long_pct <= 40) {
                $score += 6;
            } else {
                $score += 2;
            }
        }
        
        // Passive voice (10% weight) - less is better
        if ($passive_pct <= 10) {
            $score += 10;
        } elseif ($passive_pct <= 20) {
            $score += 7;
        } elseif ($passive_pct <= 30) {
            $score += 4;
        } else {
            $score += 1;
        }
        
        // Transition words (15% weight) - 20-40% is ideal
        if ($transition_pct >= 20 && $transition_pct <= 40) {
            $score += 15;
        } elseif ($transition_pct >= 10 && $transition_pct < 20) {
            $score += 10;
        } elseif ($transition_pct > 40) {
            $score += 10;
        } else {
            $score += 3;
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Convert score to grade label
     */
    private static function score_to_grade($score) {
        if ($score >= 80) return __('Excellent', 'smart-seo-fixer');
        if ($score >= 60) return __('Good', 'smart-seo-fixer');
        if ($score >= 40) return __('Needs Work', 'smart-seo-fixer');
        if ($score >= 20) return __('Poor', 'smart-seo-fixer');
        return __('Very Poor', 'smart-seo-fixer');
    }
    
    /**
     * Generate actionable suggestions
     */
    private static function generate_suggestions($flesch, $avg_sentence_length, $long_sentences, $total_sentences, $paragraphs, $avg_paragraph_length, $passive_pct, $transition_pct, $words) {
        $suggestions = [];
        
        // Word count
        if ($words < 300) {
            $suggestions[] = ['type' => 'warning', 'text' => sprintf(__('Content is only %d words. Aim for 300+ words for better SEO.', 'smart-seo-fixer'), $words)];
        } elseif ($words >= 300 && $words < 600) {
            $suggestions[] = ['type' => 'info', 'text' => sprintf(__('Content is %d words. 600-1500 words tends to rank better.', 'smart-seo-fixer'), $words)];
        } else {
            $suggestions[] = ['type' => 'good', 'text' => sprintf(__('Good content length: %d words.', 'smart-seo-fixer'), $words)];
        }
        
        // Flesch Reading Ease
        if ($flesch < 30) {
            $suggestions[] = ['type' => 'error', 'text' => __('Very difficult to read. Use shorter sentences and simpler words.', 'smart-seo-fixer')];
        } elseif ($flesch < 50) {
            $suggestions[] = ['type' => 'warning', 'text' => __('Fairly difficult to read. Try breaking up complex sentences.', 'smart-seo-fixer')];
        } elseif ($flesch >= 60 && $flesch <= 70) {
            $suggestions[] = ['type' => 'good', 'text' => __('Reading level is ideal for web content.', 'smart-seo-fixer')];
        } elseif ($flesch > 80) {
            $suggestions[] = ['type' => 'info', 'text' => __('Very easy to read. Consider if your audience needs more depth.', 'smart-seo-fixer')];
        }
        
        // Sentence length
        if ($avg_sentence_length > 25) {
            $suggestions[] = ['type' => 'warning', 'text' => sprintf(__('Average sentence length is %s words. Try to keep it under 20.', 'smart-seo-fixer'), $avg_sentence_length)];
        }
        
        // Long sentences
        if ($total_sentences > 0) {
            $long_pct = round(($long_sentences / $total_sentences) * 100);
            if ($long_pct > 25) {
                $suggestions[] = ['type' => 'warning', 'text' => sprintf(__('%d%% of sentences are over 20 words. Break long sentences up.', 'smart-seo-fixer'), $long_pct)];
            } elseif ($long_pct <= 15) {
                $suggestions[] = ['type' => 'good', 'text' => __('Good sentence length variety.', 'smart-seo-fixer')];
            }
        }
        
        // Paragraphs
        if ($avg_paragraph_length > 6) {
            $suggestions[] = ['type' => 'warning', 'text' => __('Paragraphs are long. Keep paragraphs to 3-5 sentences for better readability.', 'smart-seo-fixer')];
        }
        
        // Passive voice
        if ($passive_pct > 20) {
            $suggestions[] = ['type' => 'warning', 'text' => sprintf(__('%s%% passive voice detected. Use active voice for clearer writing.', 'smart-seo-fixer'), $passive_pct)];
        } elseif ($passive_pct <= 10) {
            $suggestions[] = ['type' => 'good', 'text' => __('Good use of active voice.', 'smart-seo-fixer')];
        }
        
        // Transition words
        if ($transition_pct < 10) {
            $suggestions[] = ['type' => 'warning', 'text' => __('Few transition words found. Use words like "however", "therefore", "for example" to improve flow.', 'smart-seo-fixer')];
        } elseif ($transition_pct >= 20) {
            $suggestions[] = ['type' => 'good', 'text' => __('Good use of transition words.', 'smart-seo-fixer')];
        }
        
        return $suggestions;
    }
}
