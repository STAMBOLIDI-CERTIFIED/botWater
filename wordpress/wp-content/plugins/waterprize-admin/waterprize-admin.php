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
require_once __DIR__ . '/includes/class-pdf-export.php';

register_activation_hook(__FILE__, ['WaterPrize_Admin', 'activate']);
register_deactivation_hook(__FILE__, ['WaterPrize_Admin', 'deactivate']);

add_action('init', function () {
    WaterPrize_DB::instance();
    WaterPrize_API::instance();
});

// Auto-migrate DB columns on admin load
add_action('admin_init', function () {
    if (!get_option('wpz_migration_ban_columns')) {
        $db = WaterPrize_DB::instance();
        $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned BOOLEAN DEFAULT FALSE");
        $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_reason TEXT DEFAULT ''");
        $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_at TIMESTAMPTZ");
        update_option('wpz_migration_ban_columns', true);
    }
    if (!get_option('wpz_migration_partner_settlements')) {
        $db = WaterPrize_DB::instance();
        $ok = $db->execute(
            "CREATE TABLE IF NOT EXISTS partner_settlements (
                id BIGSERIAL PRIMARY KEY,
                partner_account_id INTEGER NOT NULL REFERENCES partner_accounts(id) ON DELETE CASCADE,
                period_month DATE NOT NULL,
                amount INTEGER NOT NULL,
                comment TEXT DEFAULT '',
                created_by TEXT DEFAULT '',
                created_at TIMESTAMPTZ DEFAULT NOW()
            )"
        );
        if ($ok) {
            $db->execute(
                "CREATE INDEX IF NOT EXISTS idx_partner_settlements_account_month
                 ON partner_settlements(partner_account_id, period_month)"
            );
            update_option('wpz_migration_partner_settlements', true);
        }
    }
    if (!get_option('wpz_migration_broadcasts')) {
        $db = WaterPrize_DB::instance();
        $ok = $db->execute(
            "CREATE TABLE IF NOT EXISTS broadcasts (
                id BIGSERIAL PRIMARY KEY,
                title TEXT NOT NULL DEFAULT '',
                body TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'sending',
                recipients INTEGER NOT NULL DEFAULT 0,
                sent_count INTEGER NOT NULL DEFAULT 0,
                error TEXT NOT NULL DEFAULT '',
                created_by TEXT NOT NULL DEFAULT '',
                media TEXT NOT NULL DEFAULT '[]',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                sent_at TIMESTAMPTZ
            )"
        );
        $ok = $ok && $db->execute(
            "CREATE TABLE IF NOT EXISTS bot_messages (
                id BIGSERIAL PRIMARY KEY,
                chat_id BIGINT NOT NULL,
                message_id BIGINT NOT NULL,
                broadcast_id BIGINT REFERENCES broadcasts(id) ON DELETE CASCADE,
                delete_after TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )"
        );
        $ok = $ok && $db->execute(
            "CREATE INDEX IF NOT EXISTS idx_bot_messages_delete_after ON bot_messages (delete_after)"
        );
        $ok = $ok && $db->execute(
            "CREATE INDEX IF NOT EXISTS idx_bot_messages_chat_id ON bot_messages (chat_id)"
        );
        $ok = $ok && $db->execute(
            "CREATE INDEX IF NOT EXISTS idx_bot_messages_broadcast_id ON bot_messages (broadcast_id)"
        );
        if ($ok) {
            update_option('wpz_migration_broadcasts', true);
        }
    }
    if (!get_option('wpz_migration_broadcast_media')) {
        $db = WaterPrize_DB::instance();
        if ($db->execute("ALTER TABLE broadcasts ADD COLUMN IF NOT EXISTS media TEXT NOT NULL DEFAULT '[]'")) {
            update_option('wpz_migration_broadcast_media', true);
        }
    }
    if (!get_option('wpz_migration_media_files')) {
        $db = WaterPrize_DB::instance();
        $ok = $db->execute(
            "CREATE TABLE IF NOT EXISTS media_files (
                id BIGSERIAL PRIMARY KEY,
                path TEXT NOT NULL UNIQUE,
                mime TEXT NOT NULL DEFAULT 'image/jpeg',
                bytes BYTEA NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )"
        );
        $ok = $ok && $db->execute(
            "CREATE INDEX IF NOT EXISTS idx_media_files_path ON media_files (path)"
        );
        if ($ok) {
            update_option('wpz_migration_media_files', true);
        }
    }
});

add_action('wp_ajax_wpz_online', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('no permission');
    }
    $db = WaterPrize_DB::instance();
    $count = $db->count_online(15);
    wp_send_json_success(['count' => (int)$count]);
});

if (is_admin()) {
    add_action('plugins_loaded', function () {
        WaterPrize_Admin::instance();
    });
}
