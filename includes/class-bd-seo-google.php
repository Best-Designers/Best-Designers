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
        $measurement_id = get_post_meta($client_id, '_bd_ga_measurement_id', true);
        $stream_id = get_post_meta($client_id, '_bd_ga_stream_id', true);
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

        $resolved_property_id = self::resolve_property_id($access_token, $property_id, $measurement_id, $stream_id);
        if (is_wp_error($resolved_property_id)) {
            $result['errors'][] = $resolved_property_id->get_error_message();
        }

        if (! is_wp_error($resolved_property_id)) {
            $property_id = $resolved_property_id;
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



    private static function resolve_property_id(string $access_token, string $property_id, string $measurement_id, string $stream_id)
    {
        if (! empty($property_id)) {
            return $property_id;
        }

        if (empty($measurement_id) && empty($stream_id)) {
            return new \WP_Error('bd_missing_ga', 'Add a GA4 Property ID, or provide a Measurement ID / Stream ID that can be resolved to a property.');
        }

        $cache_key = 'bd_ga_property_lookup_' . md5($measurement_id . '|' . $stream_id);
        $cached = get_transient($cache_key);
        if (! empty($cached) && is_string($cached)) {
            return $cached;
        }

        $property = self::lookup_property_id_by_stream($access_token, $measurement_id, $stream_id);
        if (is_wp_error($property)) {
            return $property;
        }

        set_transient($cache_key, $property, DAY_IN_SECONDS);

        return $property;
    }

    private static function lookup_property_id_by_stream(string $access_token, string $measurement_id, string $stream_id)
    {
        $summaries = self::google_get_json('https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200', $access_token, 'bd_ga_admin_failed', 'Unable to list GA4 account summaries while resolving property ID.');

        if (is_wp_error($summaries)) {
            return $summaries;
        }

        $account_summaries = $summaries['accountSummaries'] ?? [];

        foreach ($account_summaries as $summary) {
            $property_summaries = $summary['propertySummaries'] ?? [];
            foreach ($property_summaries as $property_summary) {
                $property_name = $property_summary['property'] ?? '';
                if (empty($property_name)) {
                    continue;
                }

                $streams = self::google_get_json('https://analyticsadmin.googleapis.com/v1beta/' . $property_name . '/dataStreams?pageSize=200', $access_token, 'bd_ga_streams_failed', 'Unable to list GA4 data streams while resolving property ID.');

                if (is_wp_error($streams)) {
                    continue;
                }

                foreach (($streams['dataStreams'] ?? []) as $data_stream) {
                    $found_stream_id = (string) basename((string) ($data_stream['name'] ?? ''));
                    $found_measurement_id = (string) ($data_stream['webStreamData']['measurementId'] ?? '');

                    if ((! empty($stream_id) && $stream_id === $found_stream_id) || (! empty($measurement_id) && $measurement_id === $found_measurement_id)) {
                        return basename($property_name);
                    }
                }
            }
        }

        return new \WP_Error('bd_property_not_found', 'Could not resolve GA4 Property ID from the provided Measurement ID / Stream ID. If possible, add the Property ID directly.');
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



    private static function google_get_json(string $url, string $access_token, string $error_code, string $fallback_message)
    {
        $response = wp_remote_get($url, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            $error_message = $data['error']['message'] ?? $fallback_message;
            return new \WP_Error($error_code, $error_message);
        }

        return is_array($data) ? $data : [];
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
