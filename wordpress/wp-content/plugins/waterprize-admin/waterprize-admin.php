<?php
/**
 * Plugin Name: WaterPrize Admin
 * Description: Админ-панель для управления ботом WaterPrize через PostgreSQL
 * Version: 1.0.0
 * Author: WaterPrize Team
 */

if (!defined('ABSPATH')) exit;

define('WP_PLUGIN_DIR_ABS', __DIR__);

require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-pages.php';
require_once __DIR__ . '/includes/class-api.php';

register_activation_hook(__FILE__, ['WaterPrize_Admin', 'activate']);
register_deactivation_hook(__FILE__, ['WaterPrize_Admin', 'deactivate']);

add_action('init', function () {
    WaterPrize_DB::instance();
    WaterPrize_API::instance();
});

if (is_admin()) {
    add_action('plugins_loaded', function () {
        WaterPrize_Admin::instance();
    });
}
