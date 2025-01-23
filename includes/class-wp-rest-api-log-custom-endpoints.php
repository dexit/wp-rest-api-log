<?php
/**
 * Custom REST API Endpoints Handler
 */
if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

class WP_REST_API_Log_Custom_Endpoints {

    const POST_TYPE = 'wp-rest-endpoint';
    const TAXONOMY_TRIGGER = 'wp-rest-trigger';
    const META_PREFIX = '_wp_rest_api_';

    /**
     * Initialize the custom endpoints functionality
     */
    public static function plugins_loaded() {
        // Register post type and taxonomy
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
        
        // Register REST endpoints
        add_action( 'rest_api_init', array( __CLASS__, 'register_dynamic_endpoints' ) );

        // Add meta boxes and save handlers
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post', array( __CLASS__, 'save_endpoint_meta' ) );

        // Add validation filters
        add_filter( 'wp_insert_post_data', array( __CLASS__, 'validate_endpoint_data' ), 10, 2 );

        // Register custom endpoint namespace
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    /**
     * Register custom REST routes for endpoint management
     */
    public static function register_rest_routes() {
        register_rest_route( 'wp-rest-api-log/v1', '/endpoints', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_endpoints' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create_endpoint' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            )
        ));

        register_rest_route( 'wp-rest-api-log/v1', '/endpoints/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_endpoint' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_endpoint' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( __CLASS__, 'delete_endpoint' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            )
        ));
    }

    /**
     * Register dynamic endpoints from the database
     */
    public static function register_dynamic_endpoints() {
        $endpoints = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => self::META_PREFIX . 'status',
                    'value'   => 'active',
                    'compare' => '='
                )
            )
        ));

        foreach ($endpoints as $endpoint) {
            $route = get_post_meta($endpoint->ID, self::META_PREFIX . 'route', true);
            $method = get_post_meta($endpoint->ID, self::META_PREFIX . 'method', true);
            $callback = get_post_meta($endpoint->ID, self::META_PREFIX . 'callback', true);

            if ($route && $method && $callback) {
                register_rest_route('wp-rest-api-log/v1', $route, array(
                    'methods'             => $method,
                    'callback'            => function($request) use ($endpoint, $callback) {
                        return self::execute_endpoint_callback($endpoint, $callback, $request);
                    },
                    'permission_callback' => function($request) use ($endpoint) {
                        return self::check_trigger_conditions($endpoint, $request);
                    }
                ));
            }
        }
    }

    /**
     * Check if trigger conditions are met
     */
    protected static function check_trigger_conditions($endpoint, $request) {
        $triggers = get_post_meta($endpoint->ID, self::META_PREFIX . 'triggers', true);
        
        if (empty($triggers)) {
            return true; // No triggers means always allow
        }

        foreach ($triggers as $trigger) {
            $value = null;
            
            switch ($trigger['type']) {
                case 'body':
                    $value = $request->get_param($trigger['key']);
                    break;
                    
                case 'query':
                    $value = $request->get_param($trigger['key']);
                    break;
                    
                case 'header':
                    $headers = $request->get_headers();
                    $value = isset($headers[$trigger['key']]) ? $headers[$trigger['key']][0] : null;
                    break;
            }

            if ($value !== $trigger['value']) {
                return new WP_Error(
                    'trigger_condition_failed',
                    sprintf(__('Trigger condition failed: %s', 'wp-rest-api-log'), $trigger['key']),
                    array('status' => 403)
                );
            }
        }

        return true;
    }

    /**
     * Check admin permissions for endpoint management
     */
    public static function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get all endpoints
     */
    public static function get_endpoints($request) {
        $endpoints = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1
        ));

        $data = array();
        foreach ($endpoints as $endpoint) {
            $data[] = self::prepare_endpoint_response($endpoint);
        }

        return rest_ensure_response($data);
    }

    /**
     * Get single endpoint
     */
    public static function get_endpoint($request) {
        $endpoint = get_post($request['id']);
        if (!$endpoint || $endpoint->post_type !== self::POST_TYPE) {
            return new WP_Error(
                'endpoint_not_found',
                __('Endpoint not found', 'wp-rest-api-log'),
                array('status' => 404)
            );
        }

        return rest_ensure_response(self::prepare_endpoint_response($endpoint));
    }

    /**
     * Create new endpoint
     */
    public static function create_endpoint($request) {
        $endpoint_data = $request->get_params();
        
        $post_data = array(
            'post_title'  => sanitize_text_field($endpoint_data['title']),
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish'
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save endpoint meta
        update_post_meta($post_id, self::META_PREFIX . 'route', sanitize_text_field($endpoint_data['route']));
        update_post_meta($post_id, self::META_PREFIX . 'method', sanitize_text_field($endpoint_data['method']));
        update_post_meta($post_id, self::META_PREFIX . 'callback', wp_kses_post($endpoint_data['callback']));
        update_post_meta($post_id, self::META_PREFIX . 'status', 'active');
        
        if (!empty($endpoint_data['triggers'])) {
            update_post_meta($post_id, self::META_PREFIX . 'triggers', $endpoint_data['triggers']);
        }

        $endpoint = get_post($post_id);
        return rest_ensure_response(self::prepare_endpoint_response($endpoint));
    }

    /**
     * Update existing endpoint
     */
    public static function update_endpoint($request) {
        $endpoint = get_post($request['id']);
        if (!$endpoint || $endpoint->post_type !== self::POST_TYPE) {
            return new WP_Error(
                'endpoint_not_found',
                __('Endpoint not found', 'wp-rest-api-log'),
                array('status' => 404)
            );
        }

        $endpoint_data = $request->get_params();
        
        $post_data = array(
            'ID'         => $endpoint->ID,
            'post_title' => sanitize_text_field($endpoint_data['title'])
        );

        wp_update_post($post_data);

        // Update endpoint meta
        if (isset($endpoint_data['route'])) {
            update_post_meta($endpoint->ID, self::META_PREFIX . 'route', sanitize_text_field($endpoint_data['route']));
        }
        if (isset($endpoint_data['method'])) {
            update_post_meta($endpoint->ID, self::META_PREFIX . 'method', sanitize_text_field($endpoint_data['method']));
        }
        if (isset($endpoint_data['callback'])) {
            update_post_meta($endpoint->ID, self::META_PREFIX . 'callback', wp_kses_post($endpoint_data['callback']));
        }
        if (isset($endpoint_data['status'])) {
            update_post_meta($endpoint->ID, self::META_PREFIX . 'status', sanitize_text_field($endpoint_data['status']));
        }
        if (isset($endpoint_data['triggers'])) {
            update_post_meta($endpoint->ID, self::META_PREFIX . 'triggers', $endpoint_data['triggers']);
        }

        return rest_ensure_response(self::prepare_endpoint_response($endpoint));
    }

    /**
     * Delete endpoint
     */
    public static function delete_endpoint($request) {
        $endpoint = get_post($request['id']);
        if (!$endpoint || $endpoint->post_type !== self::POST_TYPE) {
            return new WP_Error(
                'endpoint_not_found',
                __('Endpoint not found', 'wp-rest-api-log'),
                array('status' => 404)
            );
        }

        $result = wp_delete_post($endpoint->ID, true);
        if (!$result) {
            return new WP_Error(
                'endpoint_delete_failed',
                __('Failed to delete endpoint', 'wp-rest-api-log'),
                array('status' => 500)
            );
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * Prepare endpoint data for API response
     */
    protected static function prepare_endpoint_response($endpoint) {
        return array(
            'id'       => $endpoint->ID,
            'title'    => $endpoint->post_title,
            'route'    => get_post_meta($endpoint->ID, self::META_PREFIX . 'route', true),
            'method'   => get_post_meta($endpoint->ID, self::META_PREFIX . 'method', true),
            'callback' => get_post_meta($endpoint->ID, self::META_PREFIX . 'callback', true),
            'status'   => get_post_meta($endpoint->ID, self::META_PREFIX . 'status', true),
            'triggers' => get_post_meta($endpoint->ID, self::META_PREFIX . 'triggers', true),
            'logs'     => self::get_endpoint_logs($endpoint->ID)
        );
    }

    /**
     * Get recent logs for an endpoint
     */
    protected static function get_endpoint_logs($endpoint_id, $limit = 5) {
        $route = get_post_meta($endpoint_id, self::META_PREFIX . 'route', true);
        
        return get_posts(array(
            'post_type'      => WP_REST_API_Log_DB::POST_TYPE,
            'posts_per_page' => $limit,
            'post_title'     => $route
        ));
    }
}