<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'class-bd-seo-google.php';
require_once plugin_dir_path(__FILE__) . 'class-bd-seo-admin.php';
require_once plugin_dir_path(__FILE__) . 'class-bd-seo-public.php';

class BD_SEO_Plugin
{
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);

        register_activation_hook(BD_SEO_PLUGIN_FILE(), [__CLASS__, 'activate']);
        register_deactivation_hook(BD_SEO_PLUGIN_FILE(), [__CLASS__, 'deactivate']);

        BD_SEO_Admin::init();
        BD_SEO_Public::init();
    }

    public static function register_post_type(): void
    {
        register_post_type('bd_seo_client', [
            'labels' => [
                'name' => __('SEO Clients', 'bd-seo'),
                'singular_name' => __('SEO Client', 'bd-seo'),
                'add_new_item' => __('Add New Client', 'bd-seo'),
                'edit_item' => __('Edit Client', 'bd-seo'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-chart-line',
            'supports' => ['title'],
        ]);
    }

    public static function register_rewrite(): void
    {
        add_rewrite_tag('%bd_dashboard_token%', '([^&]+)');
        add_rewrite_rule('^client-dashboard/([^/]+)/?$', 'index.php?bd_dashboard_token=$matches[1]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'bd_dashboard_token';
        return $vars;
    }

    public static function activate(): void
    {
        self::register_post_type();
        self::register_rewrite();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}

function BD_SEO_PLUGIN_FILE(): string
{
    return dirname(__DIR__) . '/best-designers-seo-dashboard.php';
}
