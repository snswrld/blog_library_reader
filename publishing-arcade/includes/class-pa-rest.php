<?php
if (!defined('ABSPATH')) { exit; }

class PA_REST {
    public static function register_routes() {
        register_rest_route('arcade/v1', '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'status'),
            'permission_callback' => '__return_true',
        ));
    }
    public static function status() {
        return rest_ensure_response(array(
            'plugin' => 'Publishing Arcade', 'version' => PA_VERSION,
            'database_version' => get_option('pa_db_version', 'not_installed'),
            'stage' => 'foundation_pdf_migration_scaffold',
            'reader_renderer_ready' => false,
            'solana_integration_ready' => false,
        ));
    }
}
