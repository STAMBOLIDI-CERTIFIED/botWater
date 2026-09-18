<?php
/**
 * Admin menu registration
 */
class WaterPrize_Admin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', ['WaterPrize_Pages', 'handle_actions']);
    }

    public function register_menus() {
        $parent = add_menu_page(
            'WaterPrize',
            'WaterPrize',
            'manage_options',
            'waterprize',
            [$this, 'page_dashboard'],
            'dashicons-portfolio',
            3
        );

        add_submenu_page('waterprize', 'Дашборд', 'Дашборд', 'manage_options', 'waterprize', [$this, 'page_dashboard']);
        add_submenu_page('waterprize', 'Пользователи', 'Пользователи', 'manage_options', 'wpz-users', [$this, 'page_users']);
        add_submenu_page('waterprize', 'QR-коды', 'QR-коды', 'manage_options', 'wpz-codes', [$this, 'page_codes']);
        add_submenu_page('waterprize', 'Бутылки', 'Бутылки', 'manage_options', 'wpz-bottles', [$this, 'page_bottles']);
        add_submenu_page('waterprize', 'Розыгрыши', 'Розыгрыши', 'manage_options', 'wpz-raffles', [$this, 'page_raffles']);
        add_submenu_page('waterprize', 'Партнёры', 'Партнёры', 'manage_options', 'wpz-partners', [$this, 'page_partners']);
        add_submenu_page('waterprize', 'Аналитика', 'Аналитика', 'manage_options', 'wpz-analytics', [$this, 'page_analytics']);
        add_submenu_page('waterprize', 'Заказы', 'Заказы', 'manage_options', 'wpz-orders', [$this, 'page_orders']);
        add_submenu_page('waterprize', 'Админы', 'Админы', 'manage_options', 'wpz-admins', [$this, 'page_admins']);
        add_submenu_page('waterprize', 'Сканирования', 'Сканирования', 'manage_options', 'wpz-scans', [$this, 'page_scans']);
        add_submenu_page('waterprize', 'Уведомления', 'Уведомления', 'manage_options', 'wpz-notifications', [$this, 'page_notifications']);
        add_submenu_page('waterprize', 'Админ-коды', 'Админ-коды', 'manage_options', 'wpz-admin-codes', [$this, 'page_admin_codes']);
        add_submenu_page('waterprize', 'Активации QR', 'Активации QR', 'manage_options', 'wpz-qr-activations', [$this, 'page_user_qr_activations']);
        add_submenu_page('waterprize', 'Настройки БД', 'Настройки БД', 'manage_options', 'wpz-settings', [$this, 'page_settings']);
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'waterprize') === false) return;
        wp_enqueue_style('wpz-admin', plugins_url('/assets/admin.css', dirname(__FILE__)), [], '1.0.0');
        wp_enqueue_script('wpz-admin', plugins_url('/assets/admin.js', dirname(__FILE__)), ['jquery'], '1.0.0', true);

        // Chart.js on dashboard and analytics
        if (strpos($hook, 'waterprize_page_waterprize') !== false || $hook === 'toplevel_page_waterprize') {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
        }
        if (strpos($hook, 'wpz-analytics') !== false) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
            wp_enqueue_script('wpz-analytics', plugins_url('/assets/analytics.js', dirname(__FILE__)), ['jquery', 'chartjs'], '1.0.0', true);
        }
        if (strpos($hook, 'wpz-partners') !== false) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
            wp_enqueue_media();
        }
    }

    // Pages — delegates to WaterPrize_Pages
    public function page_dashboard() { WaterPrize_Pages::dashboard(); }
    public function page_users()     { WaterPrize_Pages::users(); }
    public function page_codes()     { WaterPrize_Pages::codes(); }
    public function page_bottles()   { WaterPrize_Pages::bottles(); }
    public function page_raffles()   { WaterPrize_Pages::raffles(); }
    public function page_partners()  { WaterPrize_Pages::partners(); }
    public function page_analytics()  { WaterPrize_Pages::analytics(); }
    public function page_orders()    { WaterPrize_Pages::orders(); }
    public function page_admins()    { WaterPrize_Pages::admins(); }
    public function page_scans()     { WaterPrize_Pages::scans(); }
    public function page_notifications() { WaterPrize_Pages::notifications(); }
    public function page_admin_codes()   { WaterPrize_Pages::admin_codes(); }
    public function page_user_qr_activations() { WaterPrize_Pages::user_qr_activations(); }
    public function page_settings()  { WaterPrize_Pages::settings(); }

    public static function activate() {}
    public static function deactivate() {}
}
