<?php
/**
 * Plugin Name: Publishing Arcade
 * Description: Publishing Arcade foundation and manual PDF migration manager.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Publishing Arcade
 * Text Domain: publishing-arcade
 */

if (!defined('ABSPATH')) { exit; }

define('PA_VERSION', '0.1.0');
define('PA_PLUGIN_FILE', __FILE__);
define('PA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PA_DB_VERSION', '1.0.0');

require_once PA_PLUGIN_DIR . 'includes/class-pa-database.php';
require_once PA_PLUGIN_DIR . 'includes/class-pa-storage.php';
require_once PA_PLUGIN_DIR . 'includes/class-pa-migration-manager.php';
require_once PA_PLUGIN_DIR . 'includes/class-pa-admin.php';
require_once PA_PLUGIN_DIR . 'includes/class-pa-rest.php';

register_activation_hook(__FILE__, array('PA_Database', 'activate'));

final class Publishing_Arcade {
    private static $instance = null;
    public static function instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }
    private function __construct() {
        add_action('plugins_loaded', array('PA_Database', 'maybe_upgrade'));
        add_action('plugins_loaded', array('PA_Admin', 'init'));
        add_action('rest_api_init', array('PA_REST', 'register_routes'));
        add_shortcode('publishing_arcade_reader', array($this, 'reader_shortcode'));
    }
    public function reader_shortcode($atts) {
        $atts = shortcode_atts(array('publication' => 0), $atts, 'publishing_arcade_reader');
        $publication_id = absint($atts['publication']);
        if (!$publication_id) { return current_user_can('manage_options') ? '<p>Publishing Arcade: publication ID is required.</p>' : ''; }
        // Deliberately not a PDF renderer. This placeholder prevents accidental exposure of source PDFs.
        return '<div class="pa-reader-placeholder" data-publication="' . esc_attr($publication_id) . '"><p>' . esc_html__('This publication has not yet been activated in the Publishing Arcade reader.', 'publishing-arcade') . '</p></div>';
    }
}

Publishing_Arcade::instance();
