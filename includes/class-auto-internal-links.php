<?php
/**
 * Auto Internal Linking
 *
 * Automatically adds internal links when a post is published for the first
 * time. Adds both outgoing links (from the new post to related existing
 * posts) and incoming links (from related existing posts to the new post).
 *
 * Triggered by transition_post_status and executed asynchronously via a
 * one-off cron event so the publish action itself is not slowed down.
 *
 * @package SmartSEOFixer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSF_Auto_Internal_Links {

    const CRON_HOOK = 'ssf_auto_internal_links_run';
    const META_DONE = '_ssf_auto_internal_linked';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'transition_post_status', [ __CLASS__, 'on_transition' ], 10, 3 );
        add_action( self::CRON_HOOK,          [ __CLASS__, 'run' ],           10, 1 );
    }

    /**
     * Fire when a post first becomes published.
     */
    public static function on_transition( $new_status, $old_status, $post ) {
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }
        if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        $allowed_types = Smart_SEO_Fixer::get_option( 'post_types', [ 'post', 'page' ] );
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return;
        }
        if ( ! Smart_SEO_Fixer::get_option( 'auto_internal_links', true ) ) {
            return;
        }
        if ( get_post_meta( $post->ID, self::META_DONE, true ) ) {
            return;
        }

        // Schedule a single event a short while in the future so the post-save
        // pipeline finishes cleanly (revisions, related-post caches, etc.).
        if ( ! wp_next_scheduled( self::CRON_HOOK, [ (int) $post->ID ] ) ) {
            wp_schedule_single_event( time() + 30, self::CRON_HOOK, [ (int) $post->ID ] );
        }
    }

    /**
     * Perform the auto internal linking work.
     *
     * @param int $post_id
     */
    public static function run( $post_id ) {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }
        if ( get_post_meta( $post_id, self::META_DONE, true ) ) {
            return;
        }
        if ( ! class_exists( 'SSF_AI' ) ) {
            return;
        }

        $ai = SSF_AI::get();
        if ( ! $ai->is_configured() ) {
            return;
        }

        if ( class_exists( 'SSF_History' ) ) {
            SSF_History::set_source( 'auto_internal_link' );
        }

        $focus_keyword = get_post_meta( $post_id, '_ssf_focus_keyword', true );
        $source_words  = self::word_set( $post->post_title . ' ' . ( $focus_keyword ?: '' ) . ' ' . wp_strip_all_tags( $post->post_content ) );

        // Pick candidates (other published posts of the same post types).
        global $wpdb;
        $post_types   = Smart_SEO_Fixer::get_option( 'post_types', [ 'post', 'page' ] );
        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $candidates   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish' AND post_type IN ($placeholders)
                 AND ID != %d AND LENGTH(post_content) > 300
                 ORDER BY post_date DESC LIMIT 60",
                ...array_merge( $post_types, [ $post_id ] )
            )
        );

        if ( empty( $candidates ) ) {
            update_post_meta( $post_id, self::META_DONE, time() );
            return;
        }

        // Score candidates by overlap with the new post's words.
        $scored = [];
        foreach ( $candidates as $c ) {
            $ctext = strtolower( $c->post_title );
            $score = 0;
            foreach ( $source_words as $w ) {
                if ( stripos( $ctext, $w ) !== false ) { $score++; }
            }
            if ( $score <= 0 ) { continue; }
            $scored[] = [ 'post' => $c, 'score' => $score ];
        }
        usort( $scored, function( $a, $b ) { return $b['score'] - $a['score']; } );
        $top_outgoing = array_slice( $scored, 0, 5 ); // for outgoing anchor search
        $top_incoming = array_slice( $scored, 0, 5 ); // candidates that might link TO this new post

        // Step 1 — add OUTGOING links from the new post to related targets.
        self::add_outgoing_links( $post, $top_outgoing, $ai );

        // Step 2 — add INCOMING links from related posts into the new post.
        self::add_incoming_links( $post, $top_incoming, $ai );

        update_post_meta( $post_id, self::META_DONE, time() );
    }

    /**
     * Add up to 3 outgoing links inside the new post, using request_multi on
     * Bedrock for concurrency where possible.
     */
    private static function add_outgoing_links( $post, $candidates, $ai ) {
        if ( empty( $candidates ) ) { return; }

        $source_content = $post->post_content;
        $use_parallel = (
            $ai instanceof SSF_Bedrock
            && function_exists( 'curl_multi_init' )
            && class_exists( 'SSF_AI' )
            && SSF_AI::active_provider() === 'bedrock'
            && method_exists( $ai, 'build_internal_link_messages' )
        );

        $placements = [];
        if ( $use_parallel ) {
            $jobs = [];
            foreach ( $candidates as $i => $c ) {
                $url = get_permalink( $c['post']->ID );
                if ( $url && stripos( $source_content, $url ) !== false ) { continue; }
                $jobs[ "o_{$i}" ] = [
                    'messages'    => $ai->build_internal_link_messages( $source_content, $c['post']->post_title, $url ),
                    'max_tokens'  => 150,
                    'temperature' => 0.3,
                ];
                $placements[ "o_{$i}" ] = [ 'candidate' => $c, 'url' => $url ];
            }
            if ( empty( $jobs ) ) { return; }
            $responses = $ai->request_multi( $jobs );
            $added = 0;
            foreach ( $placements as $key => $info ) {
                if ( $added >= 3 ) { break; }
                $r = $responses[ $key ] ?? null;
                if ( is_wp_error( $r ) || $r === null ) { continue; }
                $parsed = $ai->parse_internal_link_placement( $r );
                if ( is_wp_error( $parsed ) || empty( $parsed['found'] ) || empty( $parsed['anchor_text'] ) ) { continue; }
                $new_content = self::inject_anchor( $source_content, $parsed['anchor_text'], $info['url'] );
                if ( $new_content === null ) { continue; }
                $source_content = $new_content;
                $added++;
            }
        } else {
            $added = 0;
            foreach ( $candidates as $c ) {
                if ( $added >= 3 ) { break; }
                $url = get_permalink( $c['post']->ID );
                if ( $url && stripos( $source_content, $url ) !== false ) { continue; }
                $parsed = $ai->find_internal_link_placement( $source_content, $c['post']->post_title, $url );
                if ( is_wp_error( $parsed ) || empty( $parsed['found'] ) || empty( $parsed['anchor_text'] ) ) { continue; }
                $new_content = self::inject_anchor( $source_content, $parsed['anchor_text'], $url );
                if ( $new_content === null ) { continue; }
                $source_content = $new_content;
                $added++;
            }
        }

        if ( $source_content !== $post->post_content ) {
            // Avoid infinite loops: no transition_post_status fires for a simple content update.
            wp_update_post( [ 'ID' => $post->ID, 'post_content' => $source_content ] );
        }
    }

    /**
     * Add up to 3 incoming links from related posts back to the new post.
     */
    private static function add_incoming_links( $post, $candidates, $ai ) {
        if ( empty( $candidates ) ) { return; }

        $target_url   = get_permalink( $post->ID );
        $target_title = $post->post_title;

        $use_parallel = (
            $ai instanceof SSF_Bedrock
            && function_exists( 'curl_multi_init' )
            && class_exists( 'SSF_AI' )
            && SSF_AI::active_provider() === 'bedrock'
            && method_exists( $ai, 'build_internal_link_messages' )
        );

        $jobs       = [];
        $meta       = [];
        $path       = wp_parse_url( $target_url, PHP_URL_PATH );
        foreach ( $candidates as $i => $c ) {
            $source = $c['post']->post_content;
            if ( stripos( $source, $target_url ) !== false ) { continue; }
            if ( $path && stripos( $source, $path ) !== false ) { continue; }
            $meta[ "i_{$i}" ] = [ 'candidate' => $c, 'source' => $source ];
            if ( $use_parallel ) {
                $jobs[ "i_{$i}" ] = [
                    'messages'    => $ai->build_internal_link_messages( $source, $target_title, $target_url ),
                    'max_tokens'  => 150,
                    'temperature' => 0.3,
                ];
            }
        }
        if ( empty( $meta ) ) { return; }

        $responses = $use_parallel && ! empty( $jobs ) ? $ai->request_multi( $jobs ) : [];

        $added = 0;
        foreach ( $meta as $key => $info ) {
            if ( $added >= 3 ) { break; }
            if ( $use_parallel ) {
                $r = $responses[ $key ] ?? null;
                if ( is_wp_error( $r ) || $r === null ) { continue; }
                $parsed = $ai->parse_internal_link_placement( $r );
            } else {
                $parsed = $ai->find_internal_link_placement( $info['source'], $target_title, $target_url );
            }
            if ( is_wp_error( $parsed ) || empty( $parsed['found'] ) || empty( $parsed['anchor_text'] ) ) { continue; }

            $new_content = self::inject_anchor( $info['source'], $parsed['anchor_text'], $target_url );
            if ( $new_content === null ) { continue; }

            wp_update_post( [ 'ID' => $info['candidate']['post']->ID, 'post_content' => $new_content ] );
            $added++;
        }
    }

    /**
     * Inject an inline <a> around the first non-linked occurrence of $anchor in $content.
     *
     * @return string|null Updated content on success, null if anchor not found / already linked.
     */
    private static function inject_anchor( $content, $anchor, $url ) {
        if ( empty( $anchor ) || empty( $url ) ) { return null; }
        $idx = stripos( $content, $anchor );
        if ( $idx === false ) { return null; }

        $before     = substr( $content, 0, $idx );
        $last_open  = strripos( $before, '<a ' );
        $last_close = strripos( $before, '</a>' );
        if ( $last_open !== false && ( $last_close === false || $last_close < $last_open ) ) {
            return null; // inside an existing anchor
        }

        $exact = substr( $content, $idx, strlen( $anchor ) );
        $linked = '<a href="' . esc_url( $url ) . '">' . $exact . '</a>';
        return substr( $content, 0, $idx ) . $linked . substr( $content, $idx + strlen( $anchor ) );
    }

    /**
     * Pull a small set of significant words for overlap scoring.
     */
    private static function word_set( $text ) {
        $words = array_unique( array_filter(
            str_word_count( strtolower( (string) $text ), 1 ),
            function( $w ) { return strlen( $w ) > 3; }
        ) );
        // Cap to keep scoring cheap.
        return array_slice( $words, 0, 40 );
    }
}
