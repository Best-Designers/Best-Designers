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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('manage_bd_seo_client_posts_columns', [__CLASS__, 'columns']);
        add_action('manage_bd_seo_client_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    }


    public static function enqueue_admin_assets(string $hook_suffix): void
    {
        if (! in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || 'bd_seo_client' !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();
        wp_register_script('bd-seo-admin-media', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('bd-seo-admin-media');
        $script = <<<'JS'
jQuery(function($){
    $('.bd-logo-upload').on('click', function(e){
        e.preventDefault();
        const frame = wp.media({title:'Select Client Logo', button:{text:'Use this logo'}, multiple:false});
        frame.on('select', function(){
            const media = frame.state().get('selection').first().toJSON();
            $('#bd_client_logo_url').val(media.url);
        });
        frame.open();
    });
});
JS;
        wp_add_inline_script('bd-seo-admin-media', $script);
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
            'google_merchant_id' => sanitize_text_field($input['google_merchant_id'] ?? ''),
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
                            <p class="description">Token must include Google Analytics Data API, Search Console, Business Profile Performance, and (if used) Merchant Center scopes.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_merchant_id">Default Google Merchant Center Account ID (optional)</label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_merchant_id]" id="google_merchant_id" class="regular-text" value="<?php echo esc_attr($settings['google_merchant_id'] ?? ''); ?>" placeholder="123456789">
                            <p class="description">Optional global fallback. You can override per client in the client profile.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
            <hr>
            <h2>Connection Checklist</h2>
            <ol>
                <li>Create a Google Cloud project and enable <strong>Analytics Data API</strong>, <strong>Search Console API</strong>, <strong>Business Profile Performance API</strong>, and (if used) <strong>Content API for Shopping</strong>.</li>
                <li>Create OAuth credentials and paste Client ID + Secret here.</li>
                <li>Generate a refresh token with scopes: <code>https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/business.manage https://www.googleapis.com/auth/content</code>.</li>
                <li>For each client profile, add GA4 Property ID (or Measurement ID / Stream ID), Search Console site URL, Google Business Profile location resource name, and optional Merchant Center Account ID.</li>
                <li>Google Business Profile location can be either a numeric Business Profile ID like <code>1234567890</code> or a full resource name like <code>locations/1234567890</code>.</li>
                <li>Merchant Center ID format: numeric account ID like <code>123456789</code>. If omitted globally and per client, merchant widgets are hidden on the dashboard.</li>
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
        $project_manager_name = get_post_meta($post->ID, '_bd_project_manager_name', true);
        $project_manager_email = get_post_meta($post->ID, '_bd_project_manager_email', true);
        $keyword_report_url = get_post_meta($post->ID, '_bd_keyword_report_url', true);
        $gbp_location_name = get_post_meta($post->ID, '_bd_gbp_location_name', true);
        $merchant_id = get_post_meta($post->ID, '_bd_merchant_id', true);
        $client_logo_url = get_post_meta($post->ID, '_bd_client_logo_url', true);

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

        echo '<hr>';
        echo '<p><label for="bd_project_manager_name"><strong>Project Manager Name</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_project_manager_name" id="bd_project_manager_name" value="' . esc_attr($project_manager_name) . '" placeholder="e.g. Alex Smith"></p>';

        echo '<p><label for="bd_project_manager_email"><strong>Project Manager Email</strong></label><br>';
        echo '<input class="regular-text" type="email" name="bd_project_manager_email" id="bd_project_manager_email" value="' . esc_attr($project_manager_email) . '" placeholder="e.g. alex@example.com"></p>';

        echo '<p><label for="bd_keyword_report_url"><strong>SERanking Public Keyword Report URL</strong></label><br>';
        echo '<input class="large-text" type="url" name="bd_keyword_report_url" id="bd_keyword_report_url" value="' . esc_attr($keyword_report_url) . '" placeholder="https://...">';
        echo '<br><em>Paste each client\'s unique public SERanking report link to surface keyword progress in their dashboard.</em></p>';

        echo '<p><label for="bd_gbp_location_name"><strong>Google Business Profile Location (ID or Resource Name)</strong></label><br>';
        echo '<input class="large-text" type="text" name="bd_gbp_location_name" id="bd_gbp_location_name" value="' . esc_attr($gbp_location_name) . '" placeholder="1234567890 or locations/1234567890">';
        echo '<br><em>Used to show the Google Business Profile Performance overview (Overview, Calls, Directions, Website Clicks). You can paste either the numeric Business Profile ID or the full <code>locations/...</code> resource name.</em></p>';

        echo '<p><label for="bd_merchant_id"><strong>Google Merchant Center Account ID (optional)</strong></label><br>';
        echo '<input class="regular-text" type="text" name="bd_merchant_id" id="bd_merchant_id" value="' . esc_attr($merchant_id) . '" placeholder="123456789">';
        echo '<br><em>If empty, we use the default Merchant ID from SEO Dashboards settings. If none exists, the merchant section is hidden on the frontend.</em></p>';

        echo '<p><label for="bd_client_logo_url"><strong>Client Logo URL</strong></label><br>';
        echo '<input class="large-text" type="url" name="bd_client_logo_url" id="bd_client_logo_url" value="' . esc_attr($client_logo_url) . '" placeholder="https://example.com/logo.png"> ';
        echo '<button type="button" class="button bd-logo-upload">Select from Media Library</button>';
        echo '<br><em>Upload/select a logo via the Media Library to replace the default logo on the dashboard.</em></p>';

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
            '_bd_project_manager_name' => 'bd_project_manager_name',
            '_bd_project_manager_email' => 'bd_project_manager_email',
            '_bd_keyword_report_url' => 'bd_keyword_report_url',
            '_bd_gbp_location_name' => 'bd_gbp_location_name',
            '_bd_merchant_id' => 'bd_merchant_id',
            '_bd_client_logo_url' => 'bd_client_logo_url',
        ];

        foreach ($map as $meta_key => $field) {
            if (! isset($_POST[$field])) {
                continue;
            }

            $raw_value = wp_unslash($_POST[$field]);
            if ('_bd_project_manager_email' === $meta_key) {
                $value = sanitize_email($raw_value);
            } elseif (in_array($meta_key, ['_bd_keyword_report_url', '_bd_client_logo_url'], true)) {
                $value = esc_url_raw($raw_value);
            } else {
                $value = sanitize_text_field($raw_value);
            }

            update_post_meta($post_id, $meta_key, $value);
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
