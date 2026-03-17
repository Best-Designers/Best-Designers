<?php

if (! defined('ABSPATH')) {
    exit;
}

class BD_SEO_Admin
{
    private const OPTION_KEY = 'bd_seo_plugin_settings';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post_bd_seo_client', [__CLASS__, 'save_client_meta']);
        add_filter('manage_bd_seo_client_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_bd_seo_client_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    }

    public static function register_menu(): void
    {
        add_menu_page('SEO Dashboards', 'SEO Dashboards', 'manage_options', 'bd-seo-dashboard', [__CLASS__, 'render_settings_page'], 'dashicons-chart-line', 56);

        add_submenu_page('bd-seo-dashboard', 'Settings', 'Settings', 'manage_options', 'bd-seo-dashboard', [__CLASS__, 'render_settings_page']);
        add_submenu_page('bd-seo-dashboard', 'Clients', 'Clients', 'edit_posts', 'edit.php?post_type=bd_seo_client');
        add_submenu_page('bd-seo-dashboard', 'Add Client', 'Add Client', 'edit_posts', 'post-new.php?post_type=bd_seo_client');
    }

    public static function register_settings(): void
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default' => [],
        ]);
    }

    public static function sanitize_settings(array $input): array
    {
        return [
            'google_client_id' => sanitize_text_field($input['google_client_id'] ?? ''),
            'google_client_secret' => sanitize_text_field($input['google_client_secret'] ?? ''),
            'google_refresh_token' => sanitize_text_field($input['google_refresh_token'] ?? ''),
        ];
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1>SEO Dashboard Settings</h1>
            <p>Connect Google APIs once, then assign property/site values per client profile.</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_KEY); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="google_client_id">Google OAuth Client ID</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_client_id]" id="google_client_id" class="regular-text" value="<?php echo esc_attr($settings['google_client_id'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_client_secret">Google OAuth Client Secret</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_client_secret]" id="google_client_secret" class="regular-text" value="<?php echo esc_attr($settings['google_client_secret'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_refresh_token">Google OAuth Refresh Token</label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_refresh_token]" id="google_refresh_token" class="large-text" value="<?php echo esc_attr($settings['google_refresh_token'] ?? ''); ?>">
                            <p class="description">Token must include Google Analytics Data API and Search Console scopes.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
            <hr>
            <h2>Connection Checklist</h2>
            <ol>
                <li>Create a Google Cloud project and enable <strong>Analytics Data API</strong> and <strong>Search Console API</strong>.</li>
                <li>Create OAuth credentials and paste Client ID + Secret here.</li>
                <li>Generate a refresh token with scopes: <code>https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly</code>.</li>
                <li>For each client profile, add GA4 Property ID (or Measurement ID / Stream ID), Search Console site URL, and optional Industry.</li>
            </ol>
        </div>
        <?php
    }

    public static function register_meta_boxes(): void
    {
        add_meta_box('bd-seo-client-data', 'Client Data Sources', [__CLASS__, 'render_client_meta_box'], 'bd_seo_client', 'normal', 'high');
    }

    public static function render_client_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('bd_seo_client_meta', 'bd_seo_client_meta_nonce');

        $token = get_post_meta($post->ID, '_bd_dashboard_token', true);
        $ga_property_id = get_post_meta($post->ID, '_bd_ga_property_id', true);
        $ga_measurement_id = get_post_meta($post->ID, '_bd_ga_measurement_id', true);
        $ga_stream_id = get_post_meta($post->ID, '_bd_ga_stream_id', true);
        $sc_site_url = get_post_meta($post->ID, '_bd_sc_site_url', true);
        $industry = get_post_meta($post->ID, '_bd_client_industry', true);

        if (empty($token)) {
            $token = wp_generate_password(28, false, false);
        }

        echo '<p><label><strong>Public Dashboard URL</strong></label><br>';
        echo '<code>' . esc_html(home_url('/client-dashboard/' . $token . '/')) . '</code><br><em>Share only with client. This URL is set to noindex.</em></p>';

        echo '<p><label for="bd_ga_property_id"><strong>GA4 Property ID (optional if Stream/Measurement ID is provided)</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_ga_property_id" id="bd_ga_property_id" value="' . esc_attr($ga_property_id) . '" placeholder="123456789"></p>';

        echo '<p><label for="bd_ga_measurement_id"><strong>GA4 Measurement ID (optional reference)</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_ga_measurement_id" id="bd_ga_measurement_id" value="' . esc_attr($ga_measurement_id) . '" placeholder="G-XXXXXXXXXX"></p>';

        echo '<p><label for="bd_ga_stream_id"><strong>GA4 Stream ID (optional)</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_ga_stream_id" id="bd_ga_stream_id" value="' . esc_attr($ga_stream_id) . '" placeholder="9876543210"></p>';

        echo '<p><label for="bd_sc_site_url"><strong>Search Console Site URL</strong></label><br>';
        echo '<input class="large-text" type="text" name="bd_sc_site_url" id="bd_sc_site_url" value="' . esc_attr($sc_site_url) . '" placeholder="sc-domain:example.com or https://example.com/"></p>';

        echo '<p><label for="bd_client_industry"><strong>Client Industry</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_client_industry" id="bd_client_industry" value="' . esc_attr($industry) . '" placeholder="e.g. Home Services"></p>';

        echo '<input type="hidden" name="bd_dashboard_token" value="' . esc_attr($token) . '">';
    }

    public static function save_client_meta(int $post_id): void
    {
        if (! isset($_POST['bd_seo_client_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bd_seo_client_meta_nonce'])), 'bd_seo_client_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $map = [
            '_bd_dashboard_token' => 'bd_dashboard_token',
            '_bd_ga_property_id' => 'bd_ga_property_id',
            '_bd_ga_measurement_id' => 'bd_ga_measurement_id',
            '_bd_ga_stream_id' => 'bd_ga_stream_id',
            '_bd_sc_site_url' => 'bd_sc_site_url',
            '_bd_client_industry' => 'bd_client_industry',
        ];

        foreach ($map as $meta_key => $field) {
            if (! isset($_POST[$field])) {
                continue;
            }
            update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$field])));
        }

        delete_transient('bd_dashboard_data_' . get_post_meta($post_id, '_bd_dashboard_token', true));
    }

    public static function columns(array $columns): array
    {
        $columns['dashboard_url'] = 'Dashboard URL';
        $columns['ga_property'] = 'GA Property';
        return $columns;
    }

    public static function column_content(string $column, int $post_id): void
    {
        if ('dashboard_url' === $column) {
            $token = get_post_meta($post_id, '_bd_dashboard_token', true);
            if ($token) {
                echo '<code>' . esc_html(home_url('/client-dashboard/' . $token . '/')) . '</code>';
            }
        }

        if ('ga_property' === $column) {
            echo esc_html(get_post_meta($post_id, '_bd_ga_property_id', true));
        }
    }

    public static function get_settings(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }
}
