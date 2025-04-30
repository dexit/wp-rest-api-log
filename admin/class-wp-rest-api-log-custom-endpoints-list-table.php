<?php
if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WP_REST_API_Log_Custom_Endpoints_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Custom API Endpoint',
            'plural'   => 'Custom API Endpoints',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'title'       => __('Endpoint Name', 'wp-rest-api-log'),
            'route'       => __('Route', 'wp-rest-api-log'),
            'method'      => __('Method', 'wp-rest-api-log'),
            'triggers'    => __('Triggers', 'wp-rest-api-log'),
            'logs'        => __('Recent Logs', 'wp-rest-api-log'),
            'status'      => __('Status', 'wp-rest-api-log'),
            'debugging'   => __('Debugging Information', 'wp-rest-api-log')
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'route':
                return get_post_meta($item->ID, '_wp_rest_api_route', true);
            case 'method':
                return get_post_meta($item->ID, '_wp_rest_api_method', true);
            case 'triggers':
                return $this->get_trigger_count($item->ID);
            case 'logs':
                return $this->get_log_link($item->ID);
            case 'status':
                return $this->get_endpoint_status($item->ID);
            case 'debugging':
                return $this->get_debugging_info($item->ID);
            default:
                return print_r($item, true);
        }
    }

    protected function get_trigger_count($post_id) {
        $triggers = get_post_meta($post_id, '_wp_rest_api_triggers', true);
        return is_array($triggers) ? count($triggers) : 0;
    }

    protected function get_log_link($post_id) {
        $route = get_post_meta($post_id, '_wp_rest_api_route', true);
        $count = $this->get_log_count($route);
        
        return sprintf(
            '<a href="%s">%s %s</a>',
            admin_url('admin.php?page=wp-rest-api-log&route=' . urlencode($route)),
            $count,
            _n('Log', 'Logs', $count, 'wp-rest-api-log')
        );
    }

    protected function get_log_count($route) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_title = %s",
            WP_REST_API_Log_DB::POST_TYPE,
            $route
        ));
    }

    protected function get_endpoint_status($post_id) {
        $status = get_post_meta($post_id, '_wp_rest_api_status', true);
        return $status === 'active' ? 
            '<span class="status-active">Active</span>' : 
            '<span class="status-inactive">Inactive</span>';
    }

    protected function get_debugging_info($post_id) {
        $debugging_info = get_post_meta($post_id, '_wp_rest_api_debugging_info', true);
        return !empty($debugging_info) ? esc_html($debugging_info) : __('No debugging information available', 'wp-rest-api-log');
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $args = [
            'post_type'      => WP_REST_API_Log_Custom_Endpoints::POST_TYPE,
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
        ];

        $query = new WP_Query($args);
        $this->items = $query->posts;
        
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => ceil($query->found_posts / $per_page)
        ]);
    }

    // Add a new function to handle custom views
    public function handle_custom_views() {
        // Implementation for handling custom views
        // This method will handle the custom views for the custom endpoints list table
        // It will be responsible for rendering and managing the custom views
        // Fetch custom views data
        $custom_views = apply_filters( 'wp_rest_api_log_custom_views', array() );

        // Render custom views
        foreach ( $custom_views as $view ) {
            echo '<div class="custom-view">' . esc_html( $view ) . '</div>';
        }
    }

    // Add a new function to handle custom modals
    public function handle_custom_modals() {
        // Implementation for handling custom modals
        // This method will handle the custom modals for the custom endpoints list table
        // It will be responsible for rendering and managing the custom modals
        // Fetch custom modals data
        $custom_modals = apply_filters( 'wp_rest_api_log_custom_modals', array() );

        // Render custom modals
        foreach ( $custom_modals as $modal ) {
            echo '<div class="custom-modal">' . esc_html( $modal ) . '</div>';
        }
    }
}
