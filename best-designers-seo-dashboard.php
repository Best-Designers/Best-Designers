<?php
/**
 * Plugin Name: Best Designers SEO Client Dashboard
 * Description: Creates public, noindex SEO dashboards for clients using GA4 and Search Console data.
 * Version: 1.0.0
 * Author: Best Designers
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-bd-seo-plugin.php';

BD_SEO_Plugin::init();
