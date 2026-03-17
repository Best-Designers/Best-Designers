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
        $industry = get_post_meta($client->ID, '_bd_client_industry', true);
        $project_manager_name = get_post_meta($client->ID, '_bd_project_manager_name', true);
        $project_manager_email = get_post_meta($client->ID, '_bd_project_manager_email', true);
        $keyword_report_url = get_post_meta($client->ID, '_bd_keyword_report_url', true);
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
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px}
.brand{display:flex;align-items:center;gap:12px}
.logo{width:46px;height:46px;background:#000;color:#fff;display:inline-flex;align-items:center;justify-content:center;font:900 34px/1 'Arial Black',Arial,sans-serif;border-radius:0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 8px 22px rgba(15,23,42,.08)}
.card h3{margin:0 0 8px;font-size:15px;color:#334155;text-transform:uppercase;letter-spacing:.04em}
.metric{font-size:30px;font-weight:700;margin:6px 0 0}
.up{color:#16a34a}.down{color:#dc2626}
table{width:100%;border-collapse:collapse}.table-card th,.table-card td{padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px}
.table-card th{color:#475569;font-weight:600}
canvas{width:100%!important;max-height:260px}
.notice{background:#fff7ed;color:#9a3412;padding:12px 14px;border-radius:10px;margin:10px 0}
.url{word-break:break-all}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
.button{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 14px;background:#0f172a;color:#fff;text-decoration:none;font-weight:600}
.button.secondary{background:#e2e8f0;color:#0f172a}
.button.active{background:#2563eb;color:#fff}
.subgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:16px}
.stat-list{margin:0;padding-left:18px;color:#334155}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="brand">
            <span class="logo" aria-hidden="true">B</span>
            <h1><?php echo esc_html(get_the_title($client)); ?> SEO Growth Dashboard</h1>
        </div>
        <small>Updated <?php echo esc_html(gmdate('M j, Y g:i a')); ?> UTC</small>
    </div>

    <?php foreach ($data['errors'] as $error) : ?>
        <div class="notice"><?php echo esc_html($error); ?></div>
    <?php endforeach; ?>

    <?php if (! empty($industry)) : ?>
        <div class="card" style="margin-bottom:16px;">
            <h3>Industry</h3>
            <div class="metric" style="font-size:22px;"><?php echo esc_html($industry); ?></div>
        </div>
    <?php endif; ?>

    <div class="subgrid">
        <div class="card">
            <h3>Your Project Manager</h3>
            <div class="metric" style="font-size:24px;"><?php echo esc_html(! empty($project_manager_name) ? $project_manager_name : 'Best Designers SEO Team'); ?></div>
            <?php if (! empty($project_manager_email)) : ?>
                <p style="margin-top:10px;"><a class="button" href="mailto:<?php echo esc_attr($project_manager_email); ?>">Email Project Manager</a></p>
            <?php else : ?>
                <p>Project manager email will appear here once set in the backend.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>SEO Momentum Snapshot</h3>
            <ul class="stat-list">
                <li>Organic traffic trend is benchmarked across 30 and 90 day windows.</li>
                <li>Search visibility charts track clicks and impressions over time.</li>
                <li>Keyword opportunities and newly discovered pages are monitored weekly.</li>
            </ul>
            <?php if (! empty($keyword_report_url)) : ?>
                <p style="margin-top:12px;"><a class="button secondary" href="<?php echo esc_url($keyword_report_url); ?>" target="_blank" rel="noopener">Open Live SERanking Keyword Report</a></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid">
        <?php self::comparison_card('Organic Traffic (30 Days)', $data['organic_30']); ?>
        <?php self::comparison_card('Organic Traffic (90 Days)', $data['organic_90']); ?>
        <?php self::comparison_card('Overall CTR % (30 Days)', $data['overall_ctr_30'], '%'); ?>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="toolbar">
            <h3 style="margin:0;">Search Console Performance</h3>
            <div>
                <button class="button secondary active" type="button" data-range="30d">30 Days</button>
                <button class="button secondary" type="button" data-range="3m">3 Months</button>
                <button class="button secondary" type="button" data-range="6m">6 Months</button>
            </div>
        </div>
        <canvas id="scChart"></canvas>
    </div>

    <div class="subgrid">
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
            <h3>Top Queries (Last 30 Days)</h3>
            <table>
                <thead><tr><th>Query</th><th>Clicks</th></tr></thead>
                <tbody>
                <?php foreach ($data['search_console_queries'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['query'] ?? ''); ?></td>
                        <td><?php echo esc_html(number_format_i18n((float) ($row['clicks'] ?? 0))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card table-card" style="margin-top:16px;">
        <h3>New Content / New Pages (Last 30 Days)</h3>
        <table>
            <thead><tr><th>URL</th><th>Last Modified / Discovery</th></tr></thead>
            <tbody>
            <?php foreach ($data['sitemap_new_pages'] as $row) : ?>
                <tr>
                    <td class="url"><?php echo esc_html($row['url'] ?? ''); ?></td>
                    <td><?php echo esc_html($row['lastmod'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const scRangeData = <?php echo wp_json_encode($data['search_console_timeseries_ranges']); ?>;

function buildChartData(rangeKey) {
    const rows = scRangeData[rangeKey] || [];
    return {
        labels: rows.map(r => r.keys[0]),
        clicks: rows.map(r => Number(r.clicks || 0)),
        impressions: rows.map(r => Number(r.impressions || 0))
    };
}

const initialData = buildChartData('30d');

const scChart = new Chart(document.getElementById('scChart'), {
    type: 'line',
    data: {
        labels: initialData.labels,
        datasets: [
            {label:'Clicks', data:initialData.clicks, yAxisID:'y', borderColor:'#2563eb', tension:.25, pointRadius:1},
            {label:'Impressions', data:initialData.impressions, yAxisID:'y1', borderColor:'#7c3aed', tension:.25, pointRadius:1}
        ]
    },
    options: {
        responsive:true,
        maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        scales:{
            y:{type:'linear',position:'left',title:{display:true,text:'Clicks'}},
            y1:{type:'linear',position:'right',title:{display:true,text:'Impressions'},grid:{drawOnChartArea:false}}
        }
    }
});

document.querySelectorAll('[data-range]').forEach(button => {
    button.addEventListener('click', () => {
        const rangeKey = button.getAttribute('data-range');
        const nextData = buildChartData(rangeKey);

        document.querySelectorAll('[data-range]').forEach(item => item.classList.remove('active'));
        button.classList.add('active');

        scChart.data.labels = nextData.labels;
        scChart.data.datasets[0].data = nextData.clicks;
        scChart.data.datasets[1].data = nextData.impressions;
        scChart.update();
    });
});
</script>
</body>
</html>
        <?php
        exit;
    }

    private static function comparison_card(string $title, array $data, string $suffix = ''): void
    {
        $current = (float) ($data['current'] ?? 0);
        $previous = (float) ($data['previous'] ?? 0);
        $change = (float) ($data['change_percent'] ?? 0);
        $trend_class = $change >= 0 ? 'up' : 'down';
        ?>
        <div class="card">
            <h3><?php echo esc_html($title); ?></h3>
            <div class="metric"><?php echo esc_html(number_format_i18n($current, '%' === $suffix ? 2 : 0) . $suffix); ?></div>
            <p>Previous: <?php echo esc_html(number_format_i18n($previous, '%' === $suffix ? 2 : 0) . $suffix); ?></p>
            <p class="<?php echo esc_attr($trend_class); ?>"><strong><?php echo esc_html(number_format_i18n($change, 1)); ?>%</strong> vs previous period</p>
        </div>
        <?php
    }
}
