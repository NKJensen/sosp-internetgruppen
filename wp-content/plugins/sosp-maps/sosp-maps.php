<?php
/**
 * Plugin Name: SOSP Maps - Gruppe Markører
 * Plugin URI: http://sosp.internetgruppen.dk
 * Description: Administrer søspejdergrupper på et interaktivt OpenStreetMap-kort
 * Version: 2.2
 * Author: Søspejderne
 * Author URI: http://sosp.internetgruppen.dk
 * License: GPL2
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SOSP_MAPS_VERSION', '2.2' );
define( 'SOSP_MAPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOSP_MAPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// Receives requests to delete groups (can be overridden in wp-config.php)
if ( ! defined( 'SOSP_MAPS_ADMIN_EMAIL' ) ) {
    define( 'SOSP_MAPS_ADMIN_EMAIL', 'nkj@internetgruppen.dk' );
}

// Load the classes
function sosp_maps_load_classes() {
    require_once SOSP_MAPS_PLUGIN_DIR . 'includes/class-sosp-maps-db.php';
    require_once SOSP_MAPS_PLUGIN_DIR . 'includes/class-sosp-maps-admin.php';
    require_once SOSP_MAPS_PLUGIN_DIR . 'includes/class-sosp-maps-frontend.php';
    
    new SOSP_Maps_DB();
    new SOSP_Maps_Admin();
    new SOSP_Maps_Frontend();
}

// Initialize the plugin after WordPress is loaded
add_action( 'plugins_loaded', 'sosp_maps_load_classes' );

// Create database table on plugin activation
function sosp_maps_activate() {
    require_once SOSP_MAPS_PLUGIN_DIR . 'includes/class-sosp-maps-db.php';
    SOSP_Maps_DB::create_table();
}
register_activation_hook( __FILE__, 'sosp_maps_activate' );
?>
