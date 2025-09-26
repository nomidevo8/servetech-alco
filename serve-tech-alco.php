<?php
/**
 * Plugin Name: Serve Tech Alco
 * Description: Limit email submissions with Forminator and reset from admin.
 * Version: 1.0.1
 * Author: Serve Tech
 * Author URI: https://servetech.com
 * Text Domain: serve-tech-alco
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ALCO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALCO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALCO_VERSION', '1.0' );

// Include class
require_once ALCO_PLUGIN_DIR . 'includes/class-alco-main.php';

// ✅ Activation hook must point to this main file
register_activation_hook( __FILE__, ['Alco_Main', 'activate_plugin'] );

// Boot the plugin
add_action( 'plugins_loaded', ['Alco_Main', 'instance'] );

add_action( 'all', function( $hook ) {
    if ( strpos( $hook, 'forminator' ) !== false ) {
        error_log( 'Hook Fired: ' . $hook );
    }
});
