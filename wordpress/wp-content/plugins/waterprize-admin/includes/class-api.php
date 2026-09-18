<?php
/**
 * REST API endpoints for bot communication
 */
class WaterPrize_API {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('waterprize/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('waterprize/v1', '/users', [
            'methods' => 'GET',
            'callback' => [$this, 'get_users'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('waterprize/v1', '/users/(?P<telegram_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('waterprize/v1', '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    public function get_stats($request) {
        $db = WaterPrize_DB::instance();
        return rest_ensure_response($db->get_stats());
    }

    public function get_users($request) {
        $db = WaterPrize_DB::instance();
        $search = $request->get_param('search') ?? '';
        $limit = min((int)($request->get_param('limit') ?? 100), 500);
        return rest_ensure_response($db->get_users($search, $limit));
    }

    public function get_user($request) {
        $db = WaterPrize_DB::instance();
        $user = $db->get_user((int)$request['telegram_id']);
        if (!$user) {
            return new WP_Error('not_found', 'User not found', ['status' => 404]);
        }
        return rest_ensure_response($user);
    }

    public function check_admin() {
        return current_user_can('manage_options');
    }

    public function get_analytics($request) {
        $db = WaterPrize_DB::instance();
        $period = sanitize_text_field($request->get_param('period') ?? 'day');
        $from = sanitize_text_field($request->get_param('from') ?? '');
        $to = sanitize_text_field($request->get_param('to') ?? '');
        $chart = sanitize_text_field($request->get_param('chart') ?? 'registrations');

        $data = match($chart) {
            'points'  => $db->get_points_analytics($period, $from, $to),
            'scans'   => $db->get_scans_analytics($period, $from, $to),
            default   => $db->get_registrations_analytics($period, $from, $to),
        };

        return rest_ensure_response([
            'chart'  => $chart,
            'period' => $period,
            'data'   => $data,
        ]);
    }
}
