<?php
/**
 * Content Suggestions View
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-lightbulb"></span>
        <?php esc_html_e('Content Suggestions', 'smart-seo-fixer'); ?>
    </h1>
    
    <div class="ssf-cs-selector">
        <label><?php esc_html_e('Select a post to analyze:', 'smart-seo-fixer'); ?></label>
        <input type="text" id="ssf-cs-search" class="ssf-input" placeholder="<?php esc_attr_e('Type to search posts...', 'smart-seo-fixer'); ?>" style="min-width: 350px;">
        <div id="ssf-cs-results" class="ssf-search-dropdown" style="display: none;"></div>
    </div>
    
    <div id="ssf-cs-output" style="display: none;">
        <div class="ssf-cs-header">
            <div>
                <h2 id="ssf-cs-title" style="margin: 0;"></h2>
                <small id="ssf-cs-meta" style="color: #6b7280;"></small>
            </div>
            <div class="ssf-cs-score-circle" id="ssf-cs-score">—</div>
        </div>
        
        <div id="ssf-cs-list"></div>
    </div>
</div>

<style>
.ssf-cs-selector { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; position: relative; }
.ssf-cs-selector label { font-weight: 600; font-size: 14px; white-space: nowrap; }
.ssf-input { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
.ssf-search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 100; max-height: 300px; overflow-y: auto; }
.ssf-search-dropdown .item { padding: 10px 16px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
.ssf-search-dropdown .item:hover { background: #f3f4f6; }

.ssf-cs-header { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
.ssf-cs-score-circle { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: #fff; flex-shrink: 0; }

.ssf-cs-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin-bottom: 10px; display: flex; gap: 14px; align-items: flex-start; }
.ssf-cs-priority { width: 8px; border-radius: 4px; flex-shrink: 0; min-height: 40px; }
.ssf-cs-priority.high { background: #ef4444; }
.ssf-cs-priority.medium { background: #f59e0b; }
.ssf-cs-priority.low { background: #3b82f6; }
.ssf-cs-body { flex: 1; }
.ssf-cs-body h4 { margin: 0 0 4px; font-size: 14px; color: #1e293b; }
.ssf-cs-body p { margin: 0; font-size: 13px; color: #64748b; line-height: 1.5; }
.ssf-cs-tags { display: flex; gap: 6px; margin-top: 8px; }
.ssf-cs-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; background: #f3f4f6; color: #64748b; }
.ssf-cs-tag.cat-seo { background: #dbeafe; color: #1d4ed8; }
.ssf-cs-tag.cat-content { background: #dcfce7; color: #166534; }
.ssf-cs-tag.cat-structure { background: #fef3c7; color: #92400e; }
.ssf-cs-tag.cat-media { background: #fce7f3; color: #9d174d; }
.ssf-cs-tag.cat-engagement { background: #ede9fe; color: #5b21b6; }
.ssf-cs-tag.cat-topical { background: #e0e7ff; color: #3730a3; }
.ssf-cs-loading { text-align: center; padding: 40px; color: #94a3b8; }
</style>

<script>
jQuery(document).ready(function($) {
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    function scoreColor(s) {
        if (s >= 80) return '#10b981';
        if (s >= 60) return '#f59e0b';
        if (s >= 40) return '#f97316';
        return '#ef4444';
    }
    
    // Post search
    var timer;
    var searchSeq = 0;
    $('#ssf-cs-search').on('input', function() {
        var q = $(this).val();
        clearTimeout(timer);
        if (q.length < 2) { $('#ssf-cs-results').hide(); return; }

        // Show something immediately so the field never looks unresponsive
        // while the debounced request is in flight.
        $('#ssf-cs-results').html('<div class="item" style="cursor:default;color:#9ca3af;">' +
            '<?php echo esc_js(__('Searching…', 'smart-seo-fixer')); ?></div>').show();

        var thisSeq = ++searchSeq;
        timer = setTimeout(function() {
            $.post(ssfAdmin.ajax_url, { action: 'ssf_search_posts_for_schema', nonce: ssfAdmin.nonce, search: q })
                .done(function(r) {
                    if (thisSeq !== searchSeq) { return; } // a newer keystroke superseded this request

                    if (!r || !r.success) {
                        var msg = (r && r.data && r.data.message) ? r.data.message : '<?php echo esc_js(__('Search failed.', 'smart-seo-fixer')); ?>';
                        $('#ssf-cs-results').html('<div class="item" style="cursor:default;color:#dc2626;">' + esc(msg) + '</div>').show();
                        return;
                    }

                    var posts = r.data.results || r.data || [];
                    if (!posts.length) {
                        $('#ssf-cs-results').html('<div class="item" style="cursor:default;color:#9ca3af;">' +
                            '<?php echo esc_js(__('No posts found.', 'smart-seo-fixer')); ?></div>').show();
                        return;
                    }
                    var html = '';
                    $.each(posts, function(i, p) { html += '<div class="item" data-id="' + (p.id || p.ID) + '">' + esc(p.title || p.post_title) + '</div>'; });
                    $('#ssf-cs-results').html(html).show();
                })
                .fail(function(xhr) {
                    if (thisSeq !== searchSeq) { return; }
                    var detail = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
                    $('#ssf-cs-results').html('<div class="item" style="cursor:default;color:#dc2626;">' +
                        '<?php echo esc_js(__('Search request failed', 'smart-seo-fixer')); ?>' + esc(detail) + '.</div>').show();
                });
        }, 300);
    });
    
    $(document).on('click', '.ssf-search-dropdown .item', function() {
        var id = $(this).data('id');
        if (!id) { return; } // "Searching…" / "No posts found" placeholder rows aren't selectable
        var title = $(this).text();
        $('#ssf-cs-results').hide();
        $('#ssf-cs-search').val(title);
        analyzeSuggestions(id, title);
    });
    
    $(document).on('click', function(e) { if (!$(e.target).closest('.ssf-cs-selector').length) $('#ssf-cs-results').hide(); });
    
    var currentPostId = 0;
    var currentPostPermalink = '';

    function renderSuggestions(suggestions) {
        var html = '';
        if (!suggestions.length) {
            return '<div class="ssf-cs-loading">No suggestions — your content looks great!</div>';
        }
        $.each(suggestions, function(i, s) {
            html += '<div class="ssf-cs-card" data-priority="' + esc(s.priority) + '" ' +
                    'data-category="' + esc(s.category) + '" data-title="' + esc(s.title) + '" data-desc="' + esc(s.description) + '">';
            html += '<div class="ssf-cs-priority ' + s.priority + '"></div>';
            html += '<div class="ssf-cs-body">';
            html += '<h4>' + esc(s.title) + '</h4>';
            html += '<p>' + esc(s.description) + '</p>';
            html += '<div class="ssf-cs-tags">';
            html += '<span class="ssf-cs-tag cat-' + s.category + '">' + s.category + '</span>';
            html += '<span class="ssf-cs-tag">' + s.priority + '</span>';
            html += '</div>';
            html += '<div style="margin-top:10px;">';
            html += '<button type="button" class="button button-small ssf-cs-apply-btn">' +
                    '<?php echo esc_js(__('Generate & Insert', 'smart-seo-fixer')); ?></button> ';
            html += '<span class="ssf-cs-apply-status" style="font-size:12px;margin-left:6px;"></span>';
            html += '</div>';
            html += '</div></div>';
        });
        return html;
    }

    // Recompute the score from every suggestion card currently on the page —
    // rules and AI suggestions combine into one number. Without this the
    // circle only ever reflected the rules pass and went stale the moment AI
    // suggestions were added below it.
    function recomputeScore() {
        var high = 0, medium = 0;
        $('#ssf-cs-list .ssf-cs-card').each(function() {
            var p = $(this).data('priority');
            if (p === 'high') { high++; }
            else if (p === 'medium') { medium++; }
        });
        var score = Math.max(0, 100 - (high * 15) - (medium * 8));
        $('#ssf-cs-score').css('background', scoreColor(score)).text(score);
    }
    
    function analyzeSuggestions(postId, title) {
        currentPostId = postId;
        currentPostPermalink = '';
        $('#ssf-cs-output').show();
        $('#ssf-cs-title').text(title);
        $('#ssf-cs-meta').text('Analyzing...');
        $('#ssf-cs-score').css('background', '#94a3b8').text('...');
        $('#ssf-cs-list').html('<div class="ssf-cs-loading">Analyzing content...</div>');

        // Fast rule-based analysis first
        $.post(ssfAdmin.ajax_url, { action: 'ssf_content_suggestions', nonce: ssfAdmin.nonce, post_id: postId, mode: 'rules' }, function(r) {
            if (!r.success) {
                $('#ssf-cs-list').html('<div class="ssf-cs-loading">' + esc(r.data?.message || 'Error') + '</div>');
                return;
            }

            var d = r.data;
            currentPostPermalink = d.permalink || '';
            $('#ssf-cs-meta').text(d.count + ' suggestions · Rule-based analysis');

            var html = renderSuggestions(d.suggestions);

            // Show "Enhance with AI" button if OpenAI is configured
            if (d.has_ai) {
                html += '<div id="ssf-cs-ai-section" style="text-align: center; padding: 16px;">';
                html += '<button type="button" id="ssf-cs-ai-btn" class="button" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-size: 13px;">';
                html += '<span class="dashicons dashicons-admin-generic" style="margin-top: 2px; font-size: 16px;"></span> Enhance with AI Suggestions';
                html += '</button>';
                html += '<div id="ssf-cs-ai-status" style="margin-top: 8px; font-size: 12px; color: #94a3b8;"></div>';
                html += '</div>';
            }

            $('#ssf-cs-list').html(html);
            // Score is computed from the cards actually on the page, not the
            // server's rules-only number, so it stays correct as AI
            // suggestions are added later.
            recomputeScore();
        }).fail(function(xhr) {
            var detail = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
            $('#ssf-cs-meta').text('Error');
            $('#ssf-cs-list').html('<div class="ssf-cs-loading">Request failed' + detail + '. Check your server error log or try again.</div>');
        });
    }
    
    // Enhance with AI button (loads AI suggestions async)
    $(document).on('click', '#ssf-cs-ai-btn', function() {
        var $btn = $(this).prop('disabled', true).css('opacity', '0.6');
        $('#ssf-cs-ai-status').text('Asking AI to analyze your content... (5-15 seconds)');
        
        $.post(ssfAdmin.ajax_url, { action: 'ssf_content_suggestions', nonce: ssfAdmin.nonce, post_id: currentPostId, mode: 'ai' }, function(r) {
            if (!r.success || !r.data.suggestions.length) {
                $('#ssf-cs-ai-status').text(r.data?.message || 'AI had no additional suggestions.');
                $btn.prop('disabled', false).css('opacity', '1');
                return;
            }
            
            // Append AI suggestions below existing ones
            var aiHtml = '<div style="border-top: 2px solid #8b5cf6; margin-top: 4px; padding-top: 12px;">';
            aiHtml += '<div style="font-size: 11px; font-weight: 700; color: #8b5cf6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">AI Suggestions</div>';
            aiHtml += renderSuggestions(r.data.suggestions);
            aiHtml += '</div>';
            
            $('#ssf-cs-ai-section').replaceWith(aiHtml);

            // Update meta and recompute the score to include the AI findings —
            // otherwise the circle keeps showing the rules-only number forever.
            var totalCount = $('#ssf-cs-list .ssf-cs-card').length;
            $('#ssf-cs-meta').text(totalCount + ' suggestions · Rules + AI');
            recomputeScore();
        }).fail(function(xhr) {
            var msg = 'Network request failed.';
            try { var r = JSON.parse(xhr.responseText); if (r.data && r.data.message) msg = r.data.message; } catch(e) {}
            $('#ssf-cs-ai-status').text(msg);
            $btn.prop('disabled', false).css('opacity', '1');
        });
    });

    // Generate & Insert — asks AI to write the fix for one suggestion and
    // saves it straight to the post (SEO title, meta description, or an
    // appended content section, depending on what the suggestion is about).
    $(document).on('click', '.ssf-cs-apply-btn', function() {
        var $btn    = $(this);
        var $card   = $btn.closest('.ssf-cs-card');
        var $status = $card.find('.ssf-cs-apply-status');

        if (!currentPostId) { return; }

        var label = $btn.text();
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Generating…', 'smart-seo-fixer')); ?>');
        $status.css('color', '').text('');

        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_apply_content_suggestion',
            nonce: ssfAdmin.nonce,
            post_id: currentPostId,
            category: $card.data('category'),
            title: $card.data('title'),
            description: $card.data('desc')
        }).done(function(r) {
            if (!r || !r.success) {
                var msg = (r && r.data && r.data.message) ? r.data.message : '<?php echo esc_js(__('Could not apply this suggestion.', 'smart-seo-fixer')); ?>';
                $btn.prop('disabled', false).text(label);
                // Suggestions we can't auto-generate (missing image/link target)
                // still need a real next step, not just a dead-end error —
                // link straight to the live page so it can be fixed by hand.
                var liveLink = currentPostPermalink
                    ? ' <a href="' + currentPostPermalink + '" target="_blank">' +
                      '<?php echo esc_js(__('View Live', 'smart-seo-fixer')); ?></a>'
                    : '';
                $status.css('color', '#dc2626').html(esc(msg) + liveLink);
                return;
            }

            var editUrl = '<?php echo esc_js(admin_url('post.php?action=edit&post=')); ?>' + currentPostId;
            var labels = {
                title: '<?php echo esc_js(__('SEO title updated.', 'smart-seo-fixer')); ?>',
                meta_description: '<?php echo esc_js(__('Meta description updated.', 'smart-seo-fixer')); ?>',
                content: '<?php echo esc_js(__('Added to the post content.', 'smart-seo-fixer')); ?>'
            };
            var doneLabel = labels[r.data.target] || '<?php echo esc_js(__('Done.', 'smart-seo-fixer')); ?>';
            var note = r.data.note ? ' <em>' + esc(r.data.note) + '</em>' : '';

            var liveLinkOk = currentPostPermalink
                ? ' · <a href="' + currentPostPermalink + '" target="_blank">' +
                  '<?php echo esc_js(__('View Live', 'smart-seo-fixer')); ?></a>'
                : '';

            $btn.hide();
            $status.css('color', '#16a34a').html(
                '✓ ' + esc(doneLabel) + note + ' <a href="' + editUrl + '" target="_blank">' +
                '<?php echo esc_js(__('Review in editor', 'smart-seo-fixer')); ?></a>' + liveLinkOk
            );
        }).fail(function(xhr) {
            var detail = (xhr && xhr.status) ? ' (HTTP ' + xhr.status + ')' : '';
            $btn.prop('disabled', false).text(label);
            $status.css('color', '#dc2626').text('<?php echo esc_js(__('Request failed', 'smart-seo-fixer')); ?>' + detail + '.');
        });
    });
});
</script>
