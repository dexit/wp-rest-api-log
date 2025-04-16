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
                $result = register_rest_route('wp-rest-api-log/v1', $route, array(
                    'methods'             => $method,
                    'callback'            => function($request) use ($endpoint, $callback) {
                        return self::execute_endpoint_callback($endpoint, $callback, $request);
                    },
                    'permission_callback' => function($request) use ($endpoint) {
                        return self::check_trigger_conditions($endpoint, $request);
                    }
                ));

                if (is_wp_error($result)) {
                    error_log('Failed to register dynamic endpoint: ' . $route);
                }
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

    /**
     * Execute the endpoint callback
     */
    protected static function execute_endpoint_callback($endpoint, $callback, $request) {
        $callback_function = create_function('', $callback);
        if (is_callable($callback_function)) {
            return call_user_func($callback_function, $request);
        } else {
            return new WP_Error(
                'invalid_callback',
                __('Invalid callback function', 'wp-rest-api-log'),
                array('status' => 500)
            );
        }
    }

    /**
     * Add meta boxes for endpoint management
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'wp-rest-api-log-endpoint-meta',
            __('Endpoint Details', 'wp-rest-api-log'),
            array( __CLASS__, 'render_endpoint_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the endpoint meta box
     */
    public static function render_endpoint_meta_box($post) {
        wp_nonce_field('wp_rest_api_log_save_endpoint_meta', 'wp_rest_api_log_endpoint_meta_nonce');

        $route = get_post_meta($post->ID, self::META_PREFIX . 'route', true);
        $method = get_post_meta($post->ID, self::META_PREFIX . 'method', true);
        $callback = get_post_meta($post->ID, self::META_PREFIX . 'callback', true);
        $status = get_post_meta($post->ID, self::META_PREFIX . 'status', true);
        $triggers = get_post_meta($post->ID, self::META_PREFIX . 'triggers', true);

        ?>
        <p>
            <label for="wp_rest_api_log_route"><?php _e('Route', 'wp-rest-api-log'); ?></label>
            <input type="text" id="wp_rest_api_log_route" name="wp_rest_api_log_route" value="<?php echo esc_attr($route); ?>" class="widefat">
        </p>
        <p>
            <label for="wp_rest_api_log_method"><?php _e('Method', 'wp-rest-api-log'); ?></label>
            <input type="text" id="wp_rest_api_log_method" name="wp_rest_api_log_method" value="<?php echo esc_attr($method); ?>" class="widefat">
        </p>
        <p>
            <label for="wp_rest_api_log_callback"><?php _e('Callback', 'wp-rest-api-log'); ?></label>
            <textarea id="wp_rest_api_log_callback" name="wp_rest_api_log_callback" class="widefat"><?php echo esc_textarea($callback); ?></textarea>
        </p>
        <p>
            <label for="wp_rest_api_log_status"><?php _e('Status', 'wp-rest-api-log'); ?></label>
            <select id="wp_rest_api_log_status" name="wp_rest_api_log_status" class="widefat">
                <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'wp-rest-api-log'); ?></option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', 'wp-rest-api-log'); ?></option>
            </select>
        </p>
        <p>
            <label for="wp_rest_api_log_triggers"><?php _e('Triggers', 'wp-rest-api-log'); ?></label>
            <textarea id="wp_rest_api_log_triggers" name="wp_rest_api_log_triggers" class="widefat"><?php echo esc_textarea(json_encode($triggers, JSON_PRETTY_PRINT)); ?></textarea>
        </p>
        <?php
    }

    /**
     * Save endpoint meta data
     */
    public static function save_endpoint_meta($post_id) {
        if (!isset($_POST['wp_rest_api_log_endpoint_meta_nonce']) || !wp_verify_nonce($_POST['wp_rest_api_log_endpoint_meta_nonce'], 'wp_rest_api_log_save_endpoint_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['wp_rest_api_log_route'])) {
            update_post_meta($post_id, self::META_PREFIX . 'route', sanitize_text_field($_POST['wp_rest_api_log_route']));
        }
        if (isset($_POST['wp_rest_api_log_method'])) {
            update_post_meta($post_id, self::META_PREFIX . 'method', sanitize_text_field($_POST['wp_rest_api_log_method']));
        }
        if (isset($_POST['wp_rest_api_log_callback'])) {
            update_post_meta($post_id, self::META_PREFIX . 'callback', wp_kses_post($_POST['wp_rest_api_log_callback']));
        }
        if (isset($_POST['wp_rest_api_log_status'])) {
            update_post_meta($post_id, self::META_PREFIX . 'status', sanitize_text_field($_POST['wp_rest_api_log_status']));
        }
        if (isset($_POST['wp_rest_api_log_triggers'])) {
            update_post_meta($post_id, self::META_PREFIX . 'triggers', json_decode(stripslashes($_POST['wp_rest_api_log_triggers']), true));
        }
    }

    /**
     * Validate endpoint data before saving
     */
    public static function validate_endpoint_data($data, $postarr) {
        if ($data['post_type'] === self::POST_TYPE) {
            if (empty($data['post_title'])) {
                return new WP_Error(
                    'empty_title',
                    __('Title cannot be empty', 'wp-rest-api-log')
                );
            }
            if (empty($postarr[self::META_PREFIX . 'route'])) {
                return new WP_Error(
                    'empty_route',
                    __('Route cannot be empty', 'wp-rest-api-log')
                );
            }
            if (empty($postarr[self::META_PREFIX . 'method'])) {
                return new WP_Error(
                    'empty_method',
                    __('Method cannot be empty', 'wp-rest-api-log')
                );
            }
            if (empty($postarr[self::META_PREFIX . 'callback'])) {
                return new WP_Error(
                    'empty_callback',
                    __('Callback cannot be empty', 'wp-rest-api-log')
                );
            }
        }

        return $data;
    }
}
