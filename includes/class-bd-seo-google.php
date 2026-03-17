<?php

if (! defined('ABSPATH')) {
    exit;
}

class BD_SEO_Google
{
    public static function load_client_data(int $client_id): array
    {
        $token = get_post_meta($client_id, '_bd_dashboard_token', true);
        $cache_key = 'bd_dashboard_data_' . $token;
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $settings = BD_SEO_Admin::get_settings();
        $property_id = get_post_meta($client_id, '_bd_ga_property_id', true);
        $site_url = get_post_meta($client_id, '_bd_sc_site_url', true);

        $result = [
            'top_pages' => [],
            'organic_30' => [],
            'organic_90' => [],
            'search_console_timeseries' => [],
            'search_console_queries' => [],
            'errors' => [],
        ];

        $access_token = self::get_access_token($settings);
        if (is_wp_error($access_token)) {
            $result['errors'][] = $access_token->get_error_message();
            return $result;
        }

        $top_pages = self::run_ga_report($access_token, $property_id, [
            'dateRanges' => [[
                'startDate' => '30daysAgo',
                'endDate' => 'today',
            ]],
            'dimensions' => [['name' => 'pageTitle']],
            'metrics' => [['name' => 'screenPageViews']],
            'limit' => 10,
            'orderBys' => [[
                'metric' => ['metricName' => 'screenPageViews'],
                'desc' => true,
            ]],
        ]);

        if (is_wp_error($top_pages)) {
            $result['errors'][] = $top_pages->get_error_message();
        } else {
            $result['top_pages'] = self::normalize_ga_rows($top_pages);
        }

        $organic_30 = self::get_organic_comparison($access_token, $property_id, 30);
        $organic_90 = self::get_organic_comparison($access_token, $property_id, 90);

        if (is_wp_error($organic_30)) {
            $result['errors'][] = $organic_30->get_error_message();
        } else {
            $result['organic_30'] = $organic_30;
        }

        if (is_wp_error($organic_90)) {
            $result['errors'][] = $organic_90->get_error_message();
        } else {
            $result['organic_90'] = $organic_90;
        }

        $sc_timeseries = self::search_console_query($access_token, $site_url, [
            'startDate' => gmdate('Y-m-d', strtotime('-30 days')),
            'endDate' => gmdate('Y-m-d'),
            'dimensions' => ['date'],
            'rowLimit' => 30,
        ]);

        if (is_wp_error($sc_timeseries)) {
            $result['errors'][] = $sc_timeseries->get_error_message();
        } else {
            $result['search_console_timeseries'] = $sc_timeseries;
        }

        $sc_queries = self::search_console_query($access_token, $site_url, [
            'startDate' => gmdate('Y-m-d', strtotime('-30 days')),
            'endDate' => gmdate('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit' => 12,
        ]);

        if (is_wp_error($sc_queries)) {
            $result['errors'][] = $sc_queries->get_error_message();
        } else {
            $result['search_console_queries'] = $sc_queries;
        }

        set_transient($cache_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    private static function get_access_token(array $settings)
    {
        foreach (['google_client_id', 'google_client_secret', 'google_refresh_token'] as $required) {
            if (empty($settings[$required])) {
                return new \WP_Error('bd_missing_oauth', 'Missing Google OAuth credentials in plugin settings.');
            }
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'client_id' => $settings['google_client_id'],
                'client_secret' => $settings['google_client_secret'],
                'refresh_token' => $settings['google_refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (200 !== $status || empty($data['access_token'])) {
            return new \WP_Error('bd_oauth_failed', 'Google OAuth refresh failed. Verify credentials and token scopes.');
        }

        return $data['access_token'];
    }

    private static function get_organic_comparison(string $access_token, string $property_id, int $days)
    {
       $current = self::get_organic_sessions_for_range($access_token, $property_id, $days . 'daysAgo', 'today');
        $previous = self::get_organic_sessions_for_range($access_token, $property_id, ($days * 2) . 'daysAgo', ($days + 1) . 'daysAgo');

        if (is_wp_error($current)) {
            return $current;
        }

        if (is_wp_error($previous)) {
            return $previous;
        }

        $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $change,
        ];
    }

    private static function get_organic_sessions_for_range(string $access_token, string $property_id, string $start_date, string $end_date)
    {
        $rows = self::run_ga_report($access_token, $property_id, [
            'dateRanges' => [[
                'startDate' => $start_date,
                'endDate' => $end_date,
            ]],
            'metrics' => [['name' => 'sessions']],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'sessionDefaultChannelGroup',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => 'Organic Search',
                    ],
                ],
            ],
            'limit' => 1,
        ]);

        if (is_wp_error($rows)) {
            return $rows;
        }

        return (float) ($rows[0]['metricValues'][0]['value'] ?? 0);
    }

    private static function run_ga_report(string $access_token, string $property_id, array $body)
    {
        if (empty($property_id)) {
            return new \WP_Error('bd_missing_property', 'Missing GA4 Property ID for this client.');
        }

        $response = wp_remote_post('https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($property_id) . ':runReport', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            $error_message = $data['error']['message'] ?? 'GA4 API error while building dashboard.';
            return new \WP_Error('bd_ga_failed', 'GA4 API error: ' . $error_message);
        }

        return $data['rows'] ?? [];
    }

    private static function normalize_ga_rows(array $rows): array
    {
        $output = [];
        foreach ($rows as $row) {
            $output[] = [
                'dimension' => $row['dimensionValues'][0]['value'] ?? '',
                'metric' => (float) ($row['metricValues'][0]['value'] ?? 0),
            ];
        }

        return $output;
    }

    private static function search_console_query(string $access_token, string $site_url, array $payload)
    {
        if (empty($site_url)) {
            return new \WP_Error('bd_missing_sc_url', 'Missing Search Console site URL for this client.');
        }

        $response = wp_remote_post('https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($site_url) . '/searchAnalytics/query', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            $error_message = $data['error']['message'] ?? 'Search Console API error while building dashboard.';
            return new \WP_Error('bd_sc_failed', 'Search Console API error: ' . $error_message);
        }

        return $data['rows'] ?? [];
    }
}
