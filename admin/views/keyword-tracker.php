<?php
/**
 * Keyword Tracker View
 */
if (!defined('ABSPATH')) exit;

$stats = class_exists('SSF_Keyword_Tracker') ? SSF_Keyword_Tracker::get_stats() : ['total_keywords' => 0, 'avg_position' => 0, 'total_clicks' => 0, 'total_impressions' => 0, 'top_keyword' => '', 'days_tracked' => 0];
$gsc_connected = false;
if (class_exists('SSF_GSC_Client')) {
    $gsc = new SSF_GSC_Client();
    $gsc_connected = $gsc->is_connected();
}
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-chart-bar"></span>
        <?php esc_html_e('Keyword Tracker', 'smart-seo-fixer'); ?>
    </h1>
    
    <?php if (!$gsc_connected): ?>
    <div class="ssf-notice" style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <strong><?php esc_html_e('Google Search Console not connected.', 'smart-seo-fixer'); ?></strong>
        <?php esc_html_e('Connect GSC in', 'smart-seo-fixer'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=smart-seo-fixer-settings')); ?>"><?php esc_html_e('Settings', 'smart-seo-fixer'); ?></a> <?php esc_html_e('to start tracking keyword rankings automatically.', 'smart-seo-fixer'); ?>
    </div>
    <?php else: ?>
    <div style="margin-bottom: 16px;">
        <button type="button" id="ssf-fetch-keywords-now" class="button button-primary">
            <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
            <?php esc_html_e('Fetch Keywords Now', 'smart-seo-fixer'); ?>
        </button>
        <span id="ssf-fetch-status" style="margin-left: 8px; font-size: 13px; color: #64748b;"></span>
    </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="ssf-stats-row">
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #3b82f6;"><?php echo esc_html(number_format($stats['total_keywords'])); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Keywords (30d)', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #10b981;"><?php echo esc_html($stats['avg_position'] ?: '—'); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Avg Position', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #8b5cf6;"><?php echo esc_html(number_format($stats['total_clicks'])); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Total Clicks', 'smart-seo-fixer'); ?></span>
        </div>
        <div class="ssf-mini-stat">
            <span class="ssf-mini-val" style="color: #f59e0b;"><?php echo esc_html(number_format($stats['total_impressions'])); ?></span>
            <span class="ssf-mini-label"><?php esc_html_e('Impressions', 'smart-seo-fixer'); ?></span>
        </div>
    </div>
    
    <?php if (!empty($stats['top_keyword'])): ?>
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
        <strong><?php esc_html_e('Top keyword:', 'smart-seo-fixer'); ?></strong> 
        <code style="background: #dbeafe; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($stats['top_keyword']); ?></code>
        — <?php echo esc_html($stats['days_tracked']); ?> <?php esc_html_e('days of data', 'smart-seo-fixer'); ?>
    </div>
    <?php endif; ?>
    
    <!-- Controls -->
    <div class="ssf-toolbar">
        <div class="ssf-toolbar-left">
            <select id="ssf-kw-days" class="ssf-select">
                <option value="7"><?php esc_html_e('Last 7 days', 'smart-seo-fixer'); ?></option>
                <option value="30" selected><?php esc_html_e('Last 30 days', 'smart-seo-fixer'); ?></option>
                <option value="90"><?php esc_html_e('Last 90 days', 'smart-seo-fixer'); ?></option>
            </select>
            <input type="text" id="ssf-kw-search" class="ssf-input" placeholder="<?php esc_attr_e('Search keywords...', 'smart-seo-fixer'); ?>">
        </div>
    </div>
    
    <!-- Chart -->
    <div class="ssf-chart-card" id="ssf-kw-chart-card" style="display: none;">
        <h3 id="ssf-kw-chart-title" style="margin: 0 0 12px; font-size: 14px;"></h3>
        <canvas id="ssf-kw-chart" height="200"></canvas>
    </div>
    
    <!-- Table -->
    <div class="ssf-table-wrap">
        <table class="ssf-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Keyword', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Avg Position', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Clicks', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Impressions', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('CTR', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Top URL', 'smart-seo-fixer'); ?></th>
                    <th><?php esc_html_e('Actions', 'smart-seo-fixer'); ?></th>
                </tr>
            </thead>
            <tbody id="ssf-kw-body">
                <tr><td colspan="7" class="ssf-loading"><?php esc_html_e('Loading...', 'smart-seo-fixer'); ?></td></tr>
            </tbody>
        </table>
    </div>
    <div class="ssf-pagination" id="ssf-kw-pagination"></div>
</div>

<style>
.ssf-stats-row { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.ssf-mini-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; text-align: center; min-width: 120px; flex: 1; }
.ssf-mini-val { display: block; font-size: 28px; font-weight: 700; line-height: 1.2; }
.ssf-mini-label { color: #6b7280; font-size: 12px; margin-top: 4px; display: block; }
.ssf-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; }
.ssf-toolbar-left { display: flex; gap: 8px; flex-wrap: wrap; }
.ssf-select, .ssf-input { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
.ssf-input { min-width: 220px; }
.ssf-chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.ssf-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow-x: auto; }
.ssf-table { width: 100%; border-collapse: collapse; }
.ssf-table th { background: #f9fafb; padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.ssf-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #1f2937; }
.ssf-table tr:hover { background: #f9fafb; }
.ssf-loading { text-align: center; color: #94a3b8; padding: 40px !important; }
.ssf-pos-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 700; }
.ssf-pos-top3 { background: #dcfce7; color: #166534; }
.ssf-pos-top10 { background: #dbeafe; color: #1e40af; }
.ssf-pos-top20 { background: #fef9c3; color: #854d0e; }
.ssf-pos-low { background: #f3f4f6; color: #6b7280; }
.ssf-btn-sm { padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer; border: 1px solid #d1d5db; background: #fff; color: #374151; }
.ssf-btn-sm:hover { background: #f3f4f6; }
.ssf-pagination { display: flex; justify-content: center; gap: 4px; margin-top: 16px; }
.ssf-pagination button { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; cursor: pointer; font-size: 13px; }
.ssf-pagination button.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var kwChart = null;
    
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    function posBadge(pos) {
        pos = parseFloat(pos);
        if (pos <= 3) return '<span class="ssf-pos-badge ssf-pos-top3">#' + pos + '</span>';
        if (pos <= 10) return '<span class="ssf-pos-badge ssf-pos-top10">#' + pos + '</span>';
        if (pos <= 20) return '<span class="ssf-pos-badge ssf-pos-top20">#' + pos + '</span>';
        return '<span class="ssf-pos-badge ssf-pos-low">#' + pos + '</span>';
    }
    
    function loadKeywords() {
        var data = {
            action: 'ssf_get_tracked_keywords',
            nonce: ssfAdmin.nonce,
            page: currentPage,
            days: $('#ssf-kw-days').val(),
            search: $('#ssf-kw-search').val()
        };
        
        $('#ssf-kw-body').html('<tr><td colspan="7" class="ssf-loading">Loading...</td></tr>');
        
        $.post(ssfAdmin.ajax_url, data, function(response) {
            if (!response.success) {
                $('#ssf-kw-body').html('<tr><td colspan="7" class="ssf-loading">' + esc(response.data?.message || 'Error') + '</td></tr>');
                return;
            }
            
            var items = response.data.items;
            var html = '';
            
            if (!items.length) {
                html = '<tr><td colspan="7" class="ssf-loading">No keyword data yet. Connect Google Search Console and data will be collected daily.</td></tr>';
            }
            
            $.each(items, function(i, kw) {
                var urlShort = kw.primary_url ? kw.primary_url.replace(/^https?:\/\/[^\/]+/, '') : '—';
                html += '<tr>';
                html += '<td><strong>' + esc(kw.keyword) + '</strong></td>';
                html += '<td>' + posBadge(kw.avg_position) + '</td>';
                html += '<td>' + parseInt(kw.total_clicks).toLocaleString() + '</td>';
                html += '<td>' + parseInt(kw.total_impressions).toLocaleString() + '</td>';
                html += '<td>' + parseFloat(kw.avg_ctr).toFixed(1) + '%</td>';
                html += '<td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + esc(kw.primary_url) + '">' + esc(urlShort.substring(0, 40)) + '</td>';
                html += '<td><button class="ssf-btn-sm ssf-kw-trend" data-keyword="' + esc(kw.keyword) + '">Trend</button></td>';
                html += '</tr>';
            });
            
            $('#ssf-kw-body').html(html);
            
            var pages = response.data.pages;
            var ph = '';
            for (var p = 1; p <= Math.min(pages, 10); p++) {
                ph += '<button ' + (p === currentPage ? 'class="active"' : '') + ' data-page="' + p + '">' + p + '</button>';
            }
            $('#ssf-kw-pagination').html(ph);
        });
    }
    
    // Filters
    $('#ssf-kw-days').on('change', function() { currentPage = 1; loadKeywords(); });
    var searchTimer;
    $('#ssf-kw-search').on('input', function() { clearTimeout(searchTimer); searchTimer = setTimeout(function() { currentPage = 1; loadKeywords(); }, 400); });
    $(document).on('click', '#ssf-kw-pagination button', function() { currentPage = $(this).data('page'); loadKeywords(); });
    
    // Trend chart
    $(document).on('click', '.ssf-kw-trend', function() {
        var keyword = $(this).data('keyword');
        $('#ssf-kw-chart-title').text('Position trend: ' + keyword);
        $('#ssf-kw-chart-card').show();
        
        $.post(ssfAdmin.ajax_url, {
            action: 'ssf_get_keyword_history',
            nonce: ssfAdmin.nonce,
            keyword: keyword,
            days: $('#ssf-kw-days').val()
        }, function(response) {
            if (!response.success || !response.data.length) return;
            
            var labels = [], positions = [], clicks = [];
            $.each(response.data, function(i, d) {
                labels.push(d.tracked_date);
                positions.push(parseFloat(d.position));
                clicks.push(parseInt(d.clicks));
            });
            
            if (kwChart) kwChart.destroy();
            var ctx = document.getElementById('ssf-kw-chart').getContext('2d');
            kwChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Position', data: positions, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', yAxisID: 'y', tension: 0.3, fill: true },
                        { label: 'Clicks', data: clicks, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', yAxisID: 'y1', tension: 0.3 }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { type: 'linear', position: 'left', reverse: true, title: { display: true, text: 'Position' }, min: 0 },
                        y1: { type: 'linear', position: 'right', title: { display: true, text: 'Clicks' }, grid: { drawOnChartArea: false }, min: 0 }
                    }
                }
            });
            
            $('html, body').animate({ scrollTop: $('#ssf-kw-chart-card').offset().top - 50 }, 300);
        });
    });
    
    loadKeywords();
    
    // Fetch Keywords Now button
    $('#ssf-fetch-keywords-now').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $('#ssf-fetch-status').text('Fetching from Google Search Console...').css('color', '#64748b');
        $.post(ssfAdmin.ajax_url, { action: 'ssf_fetch_keywords_now', nonce: ssfAdmin.nonce }, function(r) {
            $btn.prop('disabled', false);
            if (r.success) {
                $('#ssf-fetch-status').text((r.data?.message || 'Done!') + ' Reloading...').css('color', '#10b981');
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                $('#ssf-fetch-status').html('<strong>Error:</strong> ' + (r.data?.message || 'Unknown error.')).css('color', '#ef4444');
            }
        }).fail(function(xhr) {
            $btn.prop('disabled', false);
            $('#ssf-fetch-status').text('Network request failed (status ' + xhr.status + '). Check your server logs.').css('color', '#ef4444');
        });
    });
});
</script>
