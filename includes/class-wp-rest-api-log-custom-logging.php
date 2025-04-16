<?php
/**
 * Custom REST API Logging Handler
 * 
 * Handles selective logging of custom endpoints while bypassing others
 */

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

class WP_REST_API_Log_Custom_Logging {

    /**
     * Initialize logging filters
     */
    public static function plugins_loaded() {
        add_filter( 'wp-rest-api-log-bypass-insert', array( __CLASS__, 'filter_logging' ), 10, 4 );
        add_filter( 'wp-rest-api-log-response-headers', array( __CLASS__, 'filter_response_headers' ), 10, 2 );
    }

    /**
     * Filter which requests get logged
     * 
     * @param bool $bypass_insert Whether to bypass logging
     * @param WP_REST_Response $result Response object
     * @param WP_REST_Request $request Request object
     * @param WP_REST_Server $rest_server Server instance
     * @return bool Whether to bypass logging
     */
    public static function filter_logging( $bypass_insert, $result, $request, $rest_server ) {
        // Only log custom endpoints
        $route = $request->get_route();
        if ( strpos( $route, '/wp-rest-api-log/v1/' ) === 0 ) {
            // Add custom logging data
            add_filter( 'wp-rest-api-log-entry-data', function( $data ) use ( $request ) {
                $data['custom_endpoint'] = true;
                $data['trigger_data'] = self::get_trigger_data( $request );
                return $data;
            });
            return false; // Don't bypass, log this request
        }
        return true; // Bypass all other logging
    }

    /**
     * Filter response headers for logging
     * 
     * @param array $headers Response headers
     * @param WP_REST_Request $request Request object
     * @return array Modified headers
     */
    public static function filter_response_headers( $headers, $request ) {
        if ( strpos( $request->get_route(), '/wp-rest-api-log/v1/' ) === 0 ) {
            $headers['X-WP-REST-API-Log-Custom-Endpoint'] = 'true';
        }
        return $headers;
    }

    /**
     * Get trigger data for logging
     * 
     * @param WP_REST_Request $request Request object
     * @return array Trigger data
     */
    protected static function get_trigger_data( $request ) {
        return array(
            'type' => get_post_meta( $request->get_param('id'), WP_REST_API_Log_Custom_Endpoints::META_PREFIX . 'trigger_type', true ),
            'key' => get_post_meta( $request->get_param('id'), WP_REST_API_Log_Custom_Endpoints::META_PREFIX . 'trigger_key', true ),
            'value' => get_post_meta( $request->get_param('id'), WP_REST_API_Log_Custom_Endpoints::META_PREFIX . 'trigger_value', true ),
        );
    }
}
