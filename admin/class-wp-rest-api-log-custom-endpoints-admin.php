<?php
if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

class WP_REST_API_Log_Custom_Endpoints_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_endpoint'), 10, 2);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            __('Custom API Endpoints', 'wp-rest-api-log'),
            __('Custom API Endpoints', 'wp-rest-api-log'),
            'manage_options',
            'wp-rest-api-log-endpoints',
            array(__CLASS__, 'render_endpoints_page')
        );
    }

    public static function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php', 'tools_page_wp-rest-api-log-endpoints'])) {
            return;
        }

        // CodeMirror for PHP editing
        wp_enqueue_code_editor(['type' => 'text/x-php']);
        
        wp_enqueue_style(
            'wp-rest-api-log-custom-endpoints',
            WP_REST_API_LOG_URL . 'admin/css/custom-endpoints.css',
            array(),
            WP_REST_API_LOG_VERSION
        );

        wp_enqueue_script(
            'wp-rest-api-log-custom-endpoints',
            WP_REST_API_LOG_URL . 'admin/js/custom-endpoints.js',
            array('jquery', 'code-editor'),
            WP_REST_API_LOG_VERSION,
            true
        );
    }

    public static function render_endpoints_page() {
        require_once WP_REST_API_LOG_PATH . 'admin/class-wp-rest-api-log-custom-endpoints-list-table.php';
        $list_table = new WP_REST_API_Log_Custom_Endpoints_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Custom API Endpoints', 'wp-rest-api-log'); ?>
            </h1>
            <a href="<?php echo admin_url('post-new.php?post_type=' . WP_REST_API_Log_Custom_Endpoints::POST_TYPE); ?>" 
               class="page-title-action">
                <?php _e('Add New', 'wp-rest-api-log'); ?>
            </a>
            <hr class="wp-header-end">
            
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'endpoint-config',
            __('Endpoint Configuration', 'wp-rest-api-log'),
            array(__CLASS__, 'render_endpoint_config'),
            WP_REST_API_Log_Custom_Endpoints::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'endpoint-callback',
            __('Endpoint Callback', 'wp-rest-api-log'),
            array(__CLASS__, 'render_endpoint_callback'),
            WP_REST_API_Log_Custom_Endpoints::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'endpoint-triggers',
            __('Endpoint Triggers', 'wp-rest-api-log'),
            array(__CLASS__, 'render_endpoint_triggers'),
            WP_REST_API_Log_Custom_Endpoints::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'endpoint-logs',
            __('Recent Logs', 'wp-rest-api-log'),
            array(__CLASS__, 'render_endpoint_logs'),
            WP_REST_API_Log_Custom_Endpoints::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function render_endpoint_config($post) {
        wp_nonce_field('save_endpoint', 'endpoint_nonce');
        
        $route = get_post_meta($post->ID, '_wp_rest_api_route', true);
        $method = get_post_meta($post->ID, '_wp_rest_api_method', true);
        $status = get_post_meta($post->ID, '_wp_rest_api_status', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="route"><?php _e('Route', 'wp-rest-api-log'); ?></label></th>
                <td>
                    <input type="text" id="route" name="route" value="<?php echo esc_attr($route); ?>" 
                           class="regular-text" required pattern="^/[\w-/]+$"
                           placeholder="/my-custom-endpoint" />
                    <p class="description">
                        <?php _e('Must start with / and contain only letters, numbers, hyphens and forward slashes', 'wp-rest-api-log'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="method"><?php _e('HTTP Method', 'wp-rest-api-log'); ?></label></th>
                <td>
                    <select id="method" name="method" required>
                        <?php foreach (WP_REST_API_Log_Common::valid_methods() as $valid_method): ?>
                            <option value="<?php echo esc_attr($valid_method); ?>" 
                                    <?php selected($method, $valid_method); ?>>
                                <?php echo esc_html($valid_method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="status"><?php _e('Status', 'wp-rest-api-log'); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <option value="active" <?php selected($status, 'active'); ?>>
                            <?php _e('Active', 'wp-rest-api-log'); ?>
                        </option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>
                            <?php _e('Inactive', 'wp-rest-api-log'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_endpoint_callback($post) {
        $callback = get_post_meta($post->ID, '_wp_rest_api_callback', true);
        ?>
        <div class="endpoint-callback-editor">
            <textarea id="callback" name="callback" class="code-editor"><?php echo esc_textarea($callback); ?></textarea>
            <p class="description">
                <?php _e('Enter PHP code for the endpoint callback. Must return WP_REST_Response or WP_Error.', 'wp-rest-api-log'); ?>
            </p>
        </div>
        <?php
    }

    public static function render_endpoint_triggers($post) {
        $triggers = get_post_meta($post->ID, '_wp_rest_api_triggers', true) ?: array();
        ?>
        <div class="endpoint-triggers">
            <div class="triggers-list">
                <?php foreach ($triggers as $index => $trigger): ?>
                    <?php self::render_trigger_row($index, $trigger); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button add-trigger">
                <?php _e('Add Trigger', 'wp-rest-api-log'); ?>
            </button>
        </div>
        
        <script type="text/template" id="trigger-row-template">
            <?php self::render_trigger_row('{{index}}', array()); ?>
        </script>
        <?php
    }

    protected static function render_trigger_row($index, $trigger) {
        ?>
        <div class="trigger-row">
            <select name="triggers[<?php echo $index; ?>][type]">
                <option value="body" <?php selected($trigger['type'] ?? '', 'body'); ?>>
                    <?php _e('Body Parameter', 'wp-rest-api-log'); ?>
                </option>
                <option value="query" <?php selected($trigger['type'] ?? '', 'query'); ?>>
                    <?php _e('Query Parameter', 'wp-rest-api-log'); ?>
                </option>
                <option value="header" <?php selected($trigger['type'] ?? '', 'header'); ?>>
                    <?php _e('Header', 'wp-rest-api-log'); ?>
                </option>
            </select>
            
            <input type="text" name="triggers[<?php echo $index; ?>][key]" 
                   value="<?php echo esc_attr($trigger['key'] ?? ''); ?>" 
                   placeholder="<?php _e('Key', 'wp-rest-api-log'); ?>" />
                   
            <input type="text" name="triggers[<?php echo $index; ?>][value]" 
                   value="<?php echo esc_attr($trigger['value'] ?? ''); ?>" 
                   placeholder="<?php _e('Value', 'wp-rest-api-log'); ?>" />
                   
            <button type="button" class="button remove-trigger">
                <?php _e('Remove', 'wp-rest-api-log'); ?>
            </button>
        </div>
        <?php
    }

    public static function render_endpoint_logs($post) {
        $route = get_post_meta($post->ID, '_wp_rest_api_route', true);
        if (!$route) {
            echo '<p>' . __('Save the endpoint first to view logs.', 'wp-rest-api-log') . '</p>';
            return;
        }

        $logs = self::get_recent_logs($route);
        if (empty($logs)) {
            echo '<p>' . __('No logs found for this endpoint.', 'wp-rest-api-log') . '</p>';
            return;
        }

        echo '<ul class="endpoint-logs-list">';
        foreach ($logs as $log) {
            printf(
                '<li><a href="%s">%s - %s</a></li>',
                get_edit_post_link($log->ID),
                get_the_date('Y-m-d H:i:s', $log),
                get_post_meta($log->ID, '_response_status', true)
            );
        }
        echo '</ul>';
    }

    protected static function get_recent_logs($route, $limit = 5) {
        return get_posts(array(
            'post_type' => WP_REST_API_Log_DB::POST_TYPE,
            'posts_per_page' => $limit,
            'post_title' => $route,
        ));
    }

    public static function save_endpoint($post_id, $post) {
        if (!isset($_POST['endpoint_nonce']) || 
            !wp_verify_nonce($_POST['endpoint_nonce'], 'save_endpoint')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== WP_REST_API_Log_Custom_Endpoints::POST_TYPE) {
            return;
        }

        // Save basic endpoint config
        update_post_meta($post_id, '_wp_rest_api_route', sanitize_text_field($_POST['route']));
        update_post_meta($post_id, '_wp_rest_api_method', sanitize_text_field($_POST['method']));
        update_post_meta($post_id, '_wp_rest_api_status', sanitize_text_field($_POST['status']));
        
        // Save callback code
        update_post_meta($post_id, '_wp_rest_api_callback', wp_kses_post($_POST['callback']));
        
        // Save triggers
        $triggers = array();
        if (isset($_POST['triggers']) && is_array($_POST['triggers'])) {
            foreach ($_POST['triggers'] as $trigger) {
                if (!empty($trigger['key'])) {
                    $triggers[] = array(
                        'type' => sanitize_text_field($trigger['type']),
                        'key' => sanitize_text_field($trigger['key']),
                        'value' => sanitize_text_field($trigger['value']),
                    );
                }
            }
        }
        update_post_meta($post_id, '_wp_rest_api_triggers', $triggers);
    }
}

// Initialize the admin interface
WP_REST_API_Log_Custom_Endpoints_Admin::init();