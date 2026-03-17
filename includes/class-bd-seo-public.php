<?php

if (! defined('ABSPATH')) {
    exit;
}

class BD_SEO_Public
{
    public static function init(): void
    {
        add_action('template_redirect', [__CLASS__, 'render_dashboard']);
    }

    public static function render_dashboard(): void
    {
        $token = sanitize_text_field((string) get_query_var('bd_dashboard_token'));
        if (empty($token)) {
            return;
        }

        $clients = get_posts([
            'post_type' => 'bd_seo_client',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_key' => '_bd_dashboard_token',
            'meta_value' => $token,
        ]);

        if (empty($clients)) {
            status_header(404);
            wp_die('Dashboard not found.', 'Not Found', ['response' => 404]);
        }

        $client = $clients[0];
        $data = BD_SEO_Google::load_client_data($client->ID);

        status_header(200);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?php echo esc_html(get_the_title($client)); ?> SEO Dashboard</title>
<style>
body{font-family:Inter,Arial,sans-serif;background:#f4f7fb;color:#0f172a;margin:0;padding:0}
.container{max-width:1100px;margin:0 auto;padding:28px 20px 48px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 8px 22px rgba(15,23,42,.08)}
.card h3{margin:0 0 8px;font-size:15px;color:#334155;text-transform:uppercase;letter-spacing:.04em}
.metric{font-size:30px;font-weight:700;margin:6px 0 0}
.up{color:#16a34a}.down{color:#dc2626}
table{width:100%;border-collapse:collapse}.table-card th,.table-card td{padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px}
.table-card th{color:#475569;font-weight:600}
canvas{width:100%!important;max-height:260px}
.notice{background:#fff7ed;color:#9a3412;padding:12px 14px;border-radius:10px;margin:10px 0}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><?php echo esc_html(get_the_title($client)); ?> SEO Growth Dashboard</h1>
        <small>Updated <?php echo esc_html(gmdate('M j, Y g:i a')); ?> UTC</small>
    </div>

    <?php foreach ($data['errors'] as $error) : ?>
        <div class="notice"><?php echo esc_html($error); ?></div>
    <?php endforeach; ?>

    <div class="grid">
        <?php self::comparison_card('Organic Traffic (30 Days)', $data['organic_30']); ?>
        <?php self::comparison_card('Organic Traffic (90 Days)', $data['organic_90']); ?>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Search Console Performance (30 Days)</h3>
        <canvas id="scChart"></canvas>
    </div>

    <div class="card table-card" style="margin-top:16px;">
        <h3>Top Views by Page Title (Last 30 Days)</h3>
        <table>
            <thead><tr><th>Page Title</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($data['top_pages'] as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['dimension']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['metric'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card table-card" style="margin-top:16px;">
        <h3>Search Console Top Queries</h3>
        <table>
            <thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg Position</th></tr></thead>
            <tbody>
            <?php foreach ($data['search_console_queries'] as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['keys'][0] ?? ''); ?></td>
                    <td><?php echo esc_html(number_format_i18n((float) ($row['clicks'] ?? 0))); ?></td>
                    <td><?php echo esc_html(number_format_i18n((float) ($row['impressions'] ?? 0))); ?></td>
                    <td><?php echo esc_html(number_format_i18n(((float) ($row['ctr'] ?? 0)) * 100, 2)); ?>%</td>
                    <td><?php echo esc_html(number_format_i18n((float) ($row['position'] ?? 0), 1)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const scRows = <?php echo wp_json_encode($data['search_console_timeseries']); ?>;
const labels = scRows.map(r => r.keys[0]);
const clicks = scRows.map(r => Number(r.clicks || 0));
const impressions = scRows.map(r => Number(r.impressions || 0));
const ctr = scRows.map(r => Number((r.ctr || 0) * 100));
const position = scRows.map(r => Number(r.position || 0));

new Chart(document.getElementById('scChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {label:'Clicks', data:clicks, borderColor:'#2563eb', tension:.25},
            {label:'Impressions', data:impressions, borderColor:'#7c3aed', tension:.25},
            {label:'CTR %', data:ctr, borderColor:'#16a34a', tension:.25},
            {label:'Avg Position', data:position, borderColor:'#f97316', tension:.25}
        ]
    },
    options: {responsive:true, maintainAspectRatio:false}
});
</script>
</body>
</html>
        <?php
        exit;
    }

    private static function comparison_card(string $title, array $data): void
    {
        $current = (float) ($data['current'] ?? 0);
        $previous = (float) ($data['previous'] ?? 0);
        $change = (float) ($data['change_percent'] ?? 0);
        $trend_class = $change >= 0 ? 'up' : 'down';
        ?>
        <div class="card">
            <h3><?php echo esc_html($title); ?></h3>
            <div class="metric"><?php echo esc_html(number_format_i18n($current)); ?></div>
            <p>Previous: <?php echo esc_html(number_format_i18n($previous)); ?></p>
            <p class="<?php echo esc_attr($trend_class); ?>"><strong><?php echo esc_html(number_format_i18n($change, 1)); ?>%</strong> vs previous period</p>
        </div>
        <?php
    }
}
