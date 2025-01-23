<?php
/**
 * Plugin Name: REST API Log
 * Description: Logs requests and responses for the REST API
 * Author: Pete Nelson
 * Author URI: https://petenelson.io
 * Version: 1.7.0
 * Plugin URI: https://github.com/petenelson/wp-rest-api-log
 * Text Domain: wp-rest-api-log
 * Domain Path: /languages
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    die( 'restricted access' );
}

// Plugin version and constants
if ( ! defined( 'WP_REST_API_LOG_VERSION' ) ) {
    define( 'WP_REST_API_LOG_VERSION', '1.7.0' );
}

// ... (keep existing constants)

$plugin_class_file = 'wp-rest-api-log';

// Core includes
$includes = array(
    'includes/class-' . $plugin_class_file . '-common.php',
    'includes/class-' . $plugin_class_file . '-db.php',
    'includes/class-' . $plugin_class_file . '-post-type.php',
    // ... (keep existing includes)
);

// Add custom endpoint functionality
$includes[] = 'includes/class-' . $plugin_class_file . '-custom-endpoints.php';
$includes[] = 'includes/class-' . $plugin_class_file . '-custom-logging.php';

$class_base = 'WP_REST_API_Log';

// Core classes
$classes = array(
    $class_base . '_Common',
    $class_base . '_DB',
    // ... (keep existing classes)
);

// Add custom endpoint classes
$classes[] = $class_base . '_Custom_Endpoints';
$classes[] = $class_base . '_Custom_Logging';

/* Include all required files */
foreach ( $includes as $include ) {
    require_once WP_REST_API_LOG_PATH . $include;
}

// Initialize WP-CLI if available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once WP_REST_API_LOG_PATH . 'includes/wp-cli/setup.php';
}

/* Record the start time for logging */
if ( class_exists( 'WP_REST_API_Log_Common' ) ) {
    global $wp_rest_api_log_start;
    $wp_rest_api_log_start = WP_REST_API_Log_Common::current_milliseconds();
}

/* Initialize core functionality */
foreach ( $classes as $class ) {
    $plugin = new $class();
    if ( method_exists( $class, 'plugins_loaded' ) ) {
        add_action( 'plugins_loaded', array( $plugin, 'plugins_loaded' ), 1 );
    }
}

// Initialize core static classes
WP_REST_API_Log_i18n::plugins_loaded();
WP_REST_API_Log::plugins_loaded();
// ... (keep existing static initializations)

// Initialize custom endpoint functionality
WP_REST_API_Log_Custom_Endpoints::plugins_loaded();
WP_REST_API_Log_Custom_Logging::plugins_loaded();