<?php
/**
 * Performance Profiler View
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ssf-wrap">
    <h1 class="ssf-page-title">
        <span class="dashicons dashicons-performance"></span>
        <?php esc_html_e('Performance Profiler', 'smart-seo-fixer'); ?>
    </h1>
    
    <div class="ssf-perf-stats" id="ssf-perf-stats">
        <div class="ssf-perf-card">
            <div class="ssf-perf-icon" style="color: #3b82f6;"><span class="dashicons dashicons-clock"></span></div>
            <div class="ssf-perf-data">
                <div class="ssf-perf-num" id="ssf-perf-duration">—</div>
                <div class="ssf-perf-label"><?php esc_html_e('Load Time (ms)', 'smart-seo-fixer'); ?></div>
            </div>
        </div>
        <div class="ssf-perf-card">
            <div class="ssf-perf-icon" style="color: #8b5cf6;"><span class="dashicons dashicons-database"></span></div>
            <div class="ssf-perf-data">
                <div class="ssf-perf-num" id="ssf-perf-queries">—</div>
                <div class="ssf-perf-label"><?php esc_html_e('DB Queries', 'smart-seo-fixer'); ?></div>
            </div>
        </div>
        <div class="ssf-perf-card">
            <div class="ssf-perf-icon" style="color: #10b981;"><span class="dashicons dashicons-admin-tools"></span></div>
            <div class="ssf-perf-data">
                <div class="ssf-perf-num" id="ssf-perf-memory">—</div>
                <div class="ssf-perf-label"><?php esc_html_e('Memory (KB)', 'smart-seo-fixer'); ?></div>
            </div>
        </div>
        <div class="ssf-perf-card">
            <div class="ssf-perf-icon" style="color: #f59e0b;"><span class="dashicons dashicons-chart-area"></span></div>
            <div class="ssf-perf-data">
                <div class="ssf-perf-num" id="ssf-perf-peak">—</div>
                <div class="ssf-perf-label"><?php esc_html_e('Peak Mem (MB)', 'smart-seo-fixer'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="ssf-perf-row">
        <div class="ssf-perf-chart-wrap">
            <h3><?php esc_html_e('Performance Over Time', 'smart-seo-fixer'); ?></h3>
            <canvas id="ssf-perf-chart" height="250"></canvas>
        </div>
        <div class="ssf-perf-env">
            <h3><?php esc_html_e('Environment', 'smart-seo-fixer'); ?></h3>
            <table class="ssf-env-table" id="ssf-env-table">
                <tbody><tr><td colspan="2" style="text-align:center; color: #94a3b8;"><?php esc_html_e('Loading...', 'smart-seo-fixer'); ?></td></tr></tbody>
            </table>
        </div>
    </div>
    
    <div class="ssf-perf-db-section">
        <h3><?php esc_html_e('Plugin Database Tables', 'smart-seo-fixer'); ?></h3>
        <table class="ssf-db-table" id="ssf-db-table">
            <thead><tr><th><?php esc_html_e('Table', 'smart-seo-fixer'); ?></th><th><?php esc_html_e('Rows', 'smart-seo-fixer'); ?></th><th><?php esc_html_e('Data (KB)', 'smart-seo-fixer'); ?></th><th><?php esc_html_e('Index (KB)', 'smart-seo-fixer'); ?></th></tr></thead>
            <tbody><tr><td colspan="4" style="text-align:center; color: #94a3b8;"><?php esc_html_e('Loading...', 'smart-seo-fixer'); ?></td></tr></tbody>
        </table>
    </div>
    
    <div style="margin-top: 16px;">
        <button type="button" id="ssf-perf-clear" class="button" style="color: #dc2626;">
            <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
            <?php esc_html_e('Clear History', 'smart-seo-fixer'); ?>
        </button>
    </div>
</div>

<style>
.ssf-perf-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.ssf-perf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 14px; }
.ssf-perf-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
.ssf-perf-num { font-size: 24px; font-weight: 800; color: #1e293b; }
.ssf-perf-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 600; }

.ssf-perf-row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 960px) { .ssf-perf-row { grid-template-columns: 1fr; } }
.ssf-perf-chart-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
.ssf-perf-chart-wrap h3, .ssf-perf-env h3, .ssf-perf-db-section h3 { margin: 0 0 12px; font-size: 15px; color: #1e293b; }
.ssf-perf-env { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }

.ssf-env-table { width: 100%; border-collapse: collapse; }
.ssf-env-table td { padding: 6px 0; font-size: 12px; border-bottom: 1px solid #f3f4f6; }
.ssf-env-table td:first-child { font-weight: 600; color: #475569; width: 40%; }
.ssf-env-table td:last-child { color: #1e293b; }

.ssf-perf-db-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
.ssf-db-table { width: 100%; border-collapse: collapse; }
.ssf-db-table th { text-align: left; padding: 8px 12px; background: #f9fafb; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; }
.ssf-db-table td { padding: 8px 12px; font-size: 12px; border-bottom: 1px solid #f3f4f6; color: #334155; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
jQuery(document).ready(function($) {
    function esc(t) { return $('<span>').text(t || '').html(); }
    
    // Load data
    $.post(ssfAdmin.ajax_url, { action: 'ssf_performance_data', nonce: ssfAdmin.nonce }, function(r) {
        if (!r.success) return;
        var d = r.data;
        
        // Latest
        var l = d.latest;
        $('#ssf-perf-duration').text(l.duration || '—');
        $('#ssf-perf-queries').text(l.queries || '—');
        $('#ssf-perf-memory').text(l.memory_kb || '—');
        $('#ssf-perf-peak').text(l.peak_mb || '—');
        
        // Environment
        var env = d.environment;
        var envHtml = '';
        var envLabels = {
            php_version: 'PHP', wp_version: 'WordPress', plugin_version: 'Plugin',
            mysql_version: 'MySQL', server: 'Server', memory_limit: 'PHP Memory',
            max_exec_time: 'Max Execution', php_sapi: 'SAPI', active_plugins: 'Active Plugins',
            total_posts: 'Published Posts'
        };
        $.each(envLabels, function(k, label) {
            envHtml += '<tr><td>' + label + '</td><td>' + esc(String(env[k] || '—')) + '</td></tr>';
        });
        $('#ssf-env-table tbody').html(envHtml);
        
        // DB tables
        if (env.db_tables && env.db_tables.length) {
            var dbHtml = '';
            $.each(env.db_tables, function(i, t) {
                dbHtml += '<tr><td>' + esc(t.table_name) + '</td><td>' + (t.table_rows || 0) + '</td><td>' + (t.data_kb || 0) + '</td><td>' + (t.index_kb || 0) + '</td></tr>';
            });
            $('#ssf-db-table tbody').html(dbHtml);
        } else {
            $('#ssf-db-table tbody').html('<tr><td colspan="4" style="text-align:center; color:#94a3b8;">No plugin tables found</td></tr>');
        }
        
        // Chart
        var history = d.history;
        if (history.length) {
            var labels = history.map(function(h) { return new Date(h.time * 1000).toLocaleTimeString(); });
            var durations = history.map(function(h) { return h.duration; });
            var memories = history.map(function(h) { return h.memory; });
            var queries = history.map(function(h) { return h.queries; });
            
            new Chart(document.getElementById('ssf-perf-chart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Load Time (ms)', data: durations, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 },
                        { label: 'Memory (KB)', data: memories, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: false, tension: 0.3 },
                        { label: 'Queries', data: queries, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: false, tension: 0.3, yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { labels: { font: { size: 11 } } } },
                    scales: {
                        x: { ticks: { font: { size: 10 }, maxRotation: 45, maxTicksLimit: 15 } },
                        y: { position: 'left', title: { display: true, text: 'ms / KB', font: { size: 10 } }, beginAtZero: true },
                        y1: { position: 'right', title: { display: true, text: 'Queries', font: { size: 10 } }, beginAtZero: true, grid: { drawOnChartArea: false } }
                    }
                }
            });
        }
    });
    
    $('#ssf-perf-clear').on('click', function() {
        if (!confirm('Clear all performance history data?')) return;
        $.post(ssfAdmin.ajax_url, { action: 'ssf_performance_clear', nonce: ssfAdmin.nonce }, function(r) {
            if (r.success) location.reload();
        });
    });
});
</script>
