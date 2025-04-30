<?php

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

if ( ! class_exists( 'WP_REST_API_Log_Admin' ) ) {

	class WP_REST_API_Log_Admin {

		/**
		 * Wire up WordPress hooks and filters.
		 *
		 * @return void
		 */
		static public function plugins_loaded() {
			add_filter( 'post_type_link',     array( __CLASS__, 'entry_permalink' ), 10, 2 );
			add_filter( 'get_edit_post_link', array( __CLASS__, 'entry_permalink' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'register_scripts' ), 10 );
			add_action( 'admin_init', array( __CLASS__, 'localize_script_data' ), 11 );
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_filter( 'wp_link_query_args', array( __CLASS__, 'wp_link_query_args' ) );
			add_filter( 'admin_title', array( __CLASS__, 'admin_title' ), 10, 2 );
			add_filter( 'user_has_cap', array( __CLASS__, 'add_admin_caps' ), 10, 3 );
			add_filter( 'plugin_action_links_' . WP_REST_API_LOG_BASENAME, array( __CLASS__, 'plugin_action_links' ), 10, 4 );
			add_action( 'current_screen', array( __CLASS__, 'maybe_enqueue_scripts' ) );

			// Custom actions for out plugin.
			add_action( 'wp-rest-api-log-entry-property-links', array( __CLASS__, 'display_entry_property_links' ), 10, 3 );
		}

		/**
		 * Adds the REST API Log menu to the tools page.
		 *
		 * @return void
		 */
		static public function admin_menu() {

			add_submenu_page(
				null,
				__( 'REST API Log Entries', 'wp-rest-api-log' ),
				'',
				'read_' . WP_REST_API_Log_DB::POST_TYPE,
				WP_REST_API_Log_Common::PLUGIN_NAME . '-view-entry',
				array( __CLASS__, 'display_log_entry')
			);

			global $submenu;
			if ( ! empty( $submenu['tools.php'] ) ) {
				foreach ( $submenu['tools.php'] as &$item ) {
					if ( 'edit.php?post_type=wp-rest-api-log' === $item[2] ) {
						$item[0] = __( 'REST API Log', 'wp-rest-api-log' );
						if ( ! empty( $item[3] ) ) {
							$item[3] = $item[0];
						}
					}
				}
			}

		}


		/**
		 * Registers admin scripts and styles.
		 *
		 * @return void
		 */
		static public function register_scripts() {

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			// https://highlightjs.org/
			$highlight_version = apply_filters( 'wp-rest-api-log-admin-highlight-js-version', '11.7.0' );
			$highlight_style   = apply_filters( 'wp-rest-api-log-admin-highlight-js-style',   'default' );

			// https://github.com/zenorocha/clipboard.js
			$clipboard_version = apply_filters( 'wp-rest-api-log-admin-clipboard-js-version', '2.0.11' );

			wp_register_script( 'wp-rest-api-log-admin-highlight-js',  'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/' . $highlight_version . '/highlight.min.js' );
			wp_register_style( 'wp-rest-api-log-admin-highlight-js',  'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/' . $highlight_version . '/styles/' . $highlight_style . '.min.css' );
			wp_register_script( 'wp-rest-api-log-admin-clipboard-js',  'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/' . $clipboard_version . '/clipboard.min.js' );

			wp_register_script( 'wp-rest-api-log-admin', WP_REST_API_LOG_URL . 'dist/js/admin.js', 'jquery', WP_REST_API_Log_Common::VERSION );
			wp_register_style( 'wp-rest-api-log-admin', WP_REST_API_LOG_URL . 'dist/css/admin.css', '', WP_REST_API_Log_Common::VERSION );

			// Error handling for failed script registrations
			if ( ! wp_script_is( 'wp-rest-api-log-admin-highlight-js', 'registered' ) ) {
				error_log( 'Failed to register wp-rest-api-log-admin-highlight-js script.' );
			}
			if ( ! wp_style_is( 'wp-rest-api-log-admin-highlight-js', 'registered' ) ) {
				error_log( 'Failed to register wp-rest-api-log-admin-highlight-js style.' );
			}
			if ( ! wp_script_is( 'wp-rest-api-log-admin-clipboard-js', 'registered' ) ) {
				error_log( 'Failed to register wp-rest-api-log-admin-clipboard-js script.' );
			}
			if ( ! wp_script_is( 'wp-rest-api-log-admin', 'registered' ) ) {
				error_log( 'Failed to register wp-rest-api-log-admin script.' );
			}
			if ( ! wp_style_is( 'wp-rest-api-log-admin', 'registered' ) ) {
				error_log( 'Failed to register wp-rest-api-log-admin style.' );
			}
		}

		/**
		 * Localizes script data for admin scripts.
		 *
		 * @return void
		 */
		static public function localize_script_data() {

			$data = array(
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'endpoints' => array(
					'purge_entries' => rest_url( WP_REST_API_Log_Common::PLUGIN_NAME . '/batch-purge-all' ),
					),
				);

			// Ensure admin URLs in SSL get pointed to SSL on the frontend.
			if ( is_ssl() ) {
				foreach ( $data['endpoints'] as &$endpoint ) {
					$endpoint = set_url_scheme( $endpoint, 'https' );
				}
			}

			wp_localize_script( 'wp-rest-api-log-admin', 'WP_REST_API_Log_Admin_Data', $data );
		}

		/**
		 * Displays the log entry template
		 *
		 * @return void
		 */
		static public function display_log_entry() {

			include_once apply_filters( 'wp-rest-api-log-admin-view-entry-template', WP_REST_API_LOG_PATH . 'admin/partials/wp-rest-api-log-view-entry.php' );

			self::enqueue_scripts();
		}

		/**
		 * Creates a permalink for a log enty.
		 *
		 * @param  string      $permalink Default permalink.
		 * @param  int|WP_Post $post      Post ID or object.
		 * @return string
		 */
		static public function entry_permalink( $permalink, $post ) {
			$post = get_post( $post );
			if ( WP_REST_API_Log_DB::POST_TYPE === $post->post_type ) {
				$permalink = add_query_arg( array(
					'page'  => WP_REST_API_Log_Common::PLUGIN_NAME . '-view-entry',
					'id'    => urlencode( $post->ID ),
					), admin_url( 'tools.php' ) );
			}
			return $permalink;
		}


		private function plugin_name() {
			return WP_REST_API_Log_Common::PLUGIN_NAME . '-admin';
		}


		/**
		 * Removes the wp-rest-api-log post type from the link query args
		 *
		 * @param  array $query Query args.
		 * @return array
		 */
		static public function wp_link_query_args( $query ) {

			if ( isset( $query['post_type'] ) && is_array( $query['post_type'] ) ) {
				for ( $i = count( $query['post_type'] ) - 1; $i >= 0; $i-- ) {
					if ( WP_REST_API_Log_DB::POST_TYPE === $query['post_type'][ $i ] ) {
						unset( $query['post_type'][ $i ] );
						break;
					}
				}
			}

			return $query;
		}


		/**
		 * Adjusts the title tag when viewing a log entry
		 *
		 * @param  string $admin_title
		 * @param  string $title
		 * @return string
		 */
		static public function admin_title( $admin_title, $title ) {
			$screen = get_current_screen();
			if ( ! empty( $screen ) && 'tools_page_wp-rest-api-log-view-entry' === $screen->id ) {
				$admin_title = __( 'REST API Log Entry', 'wp-rest-api-log' ) . $admin_title;
			}
			return $admin_title;
		}


		/**
		 * Adds capabilities for the custom post type to the administrator role
		 *
		 * @param array $allcaps All of the user's capabilities.
		 * @param array $caps    The requested capabilities.
		 * @param array $args    Requested cap, user ID, and object ID.
		 */
		static public function add_admin_caps( $allcaps, $caps, $args ) {

			// Get the user
			$user = get_userdata( $args[1] );

			// Give the administrator role access to the custom post type
			if ( ! empty( $user ) && ! empty( $user->roles ) && in_array( 'administrator', $user->roles ) ) {

				$post_type = get_post_type_object( WP_REST_API_Log_DB::POST_TYPE );

				if ( ! empty( $post_type ) ) {
					$allcaps[ $post_type->cap->edit_posts ]        = true;
					$allcaps[ $post_type->cap->edit_others_posts ] = true;
					$allcaps[ $post_type->cap->delete_posts ]      = true;
					$allcaps[ $post_type->cap->read_post ]         = true;
					$allcaps[ $post_type->cap->edit_post ]         = true;
					$allcaps[ $post_type->cap->delete_post ]       = true;
				}

			}

			return $allcaps;
		}

		/**
		 * Adds additional links to the row on the plugins page.
		 *
		 * @param array  $actions     An array of plugin action links.
		 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param array  $plugin_data An array of plugin data.
		 * @param string $context     The plugin context. Defaults are 'All', 'Active',
		 *                            'Inactive', 'Recently Activated', 'Upgrade',
		 *                            'Must-Use', 'Drop-ins', 'Search'.
		 */
		static public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {

			if ( is_plugin_active( $plugin_file ) && current_user_can( 'manage_options' ) ) {

				// Build the URL for the settings page.
				$url = add_query_arg(
					'page',
					rawurlencode( WP_REST_API_Log_Settings::$settings_page ),
					admin_url( 'admin.php' )
					);

				// Add the anchor tag to the list of plugin links.
				$new_actions = array(
					'settings' => sprintf( '<a href="%1$s">%2$s</a>',
						esc_url( $url ),
						esc_html__( 'Settings' )
						),
					'log' => sprintf( '<a href="%1$s">%2$s</a>',
						esc_url( admin_url( 'edit.php?post_type=wp-rest-api-log' ) ),
						esc_html__( 'Log' )
						)
					);

				$actions = array_merge( $actions, $new_actions );
			}

			return $actions;
		}

		/**
		 * Enqueues scripts based on current screent.
		 *
		 * @return void
		 */
		static public function maybe_enqueue_scripts() {
			$screen = get_current_screen();

			$screen_ids = array(
				'settings_page_wp-rest-api-log-settings',
				'edit-wp-rest-api-log',
				);

			if ( in_array( $screen->id, $screen_ids ) ) {
				self::enqueue_scripts();
			}
		}

		/**
		 * Enqueues admin scripts and styles.
		 *
		 * @return void
		 */
		static public function enqueue_scripts() {
			wp_enqueue_script( 'wp-rest-api-log-admin-highlight-js' );
			wp_enqueue_style(  'wp-rest-api-log-admin-highlight-js' );

			wp_enqueue_script( 'wp-rest-api-log-admin-clipboard-js' );

			wp_enqueue_script( 'wp-rest-api-log-admin' );
			wp_enqueue_style(  'wp-rest-api-log-admin' );

			// Error handling for failed script enqueues
			if ( ! wp_script_is( 'wp-rest-api-log-admin-highlight-js', 'enqueued' ) ) {
				error_log( 'Failed to enqueue wp-rest-api-log-admin-highlight-js script.' );
			}
			if ( ! wp_style_is( 'wp-rest-api-log-admin-highlight-js', 'enqueued' ) ) {
				error_log( 'Failed to enqueue wp-rest-api-log-admin-highlight-js style.' );
			}
			if ( ! wp_script_is( 'wp-rest-api-log-admin-clipboard-js', 'enqueued' ) ) {
				error_log( 'Failed to enqueue wp-rest-api-log-admin-clipboard-js script.' );
			}
			if ( ! wp_script_is( 'wp-rest-api-log-admin', 'enqueued' ) ) {
				error_log( 'Failed to enqueue wp-rest-api-log-admin script.' );
			}
			if ( ! wp_style_is( 'wp-rest-api-log-admin', 'enqueued' ) ) {
				error_log( 'Failed to enqueue wp-rest-api-log-admin style.' );
			}
		}

		/**
		 * Displays the property links (Download, Copy) for a log entry.
		 *
		 * @param  array $args
		 * @return void
		 */
		static public function display_entry_property_links( $args ) {

			$args = wp_parse_args( $args, array(
				'rr'               => '',
				'property'         => '',
				'download_urls'    => array(),
				'entry'            => null,
				)
			);

			include apply_filters( 'wp-rest-api-log-admin-view-entry-links-template', WP_REST_API_LOG_PATH . 'admin/partials/entry-property-links.php' );
			}

		/**
		 * Handles debugging information for a log entry.
		 *
		 * @param int $post_id Post ID.
		 * @return string Debugging information.
		 */
		static public function handle_debugging_info( $post_id ) {
			return get_post_meta( $post_id, '_wp_rest_api_debugging_info', true );
		}

		/**
		 * Saves debugging information for a log entry.
		 *
		 * @param int $post_id Post ID.
		 * @param string $debugging_info Debugging information.
		 * @return void
		 */
		static public function save_debugging_info( $post_id, $debugging_info ) {
			update_post_meta( $post_id, '_wp_rest_api_debugging_info', sanitize_textarea_field( $debugging_info ) );
			}

		/**
		 * Handles custom admin list tables.
		 *
		 * @return void
		 */
		static public function handle_custom_admin_list_tables() {
			// Implementation for handling custom admin list tables
			// This method will handle the custom admin list tables for the custom endpoints
			// It will be responsible for rendering and managing the custom admin list tables
			// Fetch custom admin list tables data
			$custom_admin_list_tables = apply_filters( 'wp_rest_api_log_custom_admin_list_tables', array() );

			// Render custom admin list tables
			foreach ( $custom_admin_list_tables as $table ) {
				echo '<div class="custom-admin-list-table">' . esc_html( $table ) . '</div>';
			}
		}

		/**
		 * Handles custom edit forms.
		 *
		 * @return void
		 */
		static public function handle_custom_edit_forms() {
			// Implementation for handling custom edit forms
			// This method will handle the custom edit forms for the custom endpoints
			// It will be responsible for rendering and managing the custom edit forms
			// Fetch custom edit forms data
			$custom_edit_forms = apply_filters( 'wp_rest_api_log_custom_edit_forms', array() );

			// Render custom edit forms
			foreach ( $custom_edit_forms as $form ) {
				echo '<div class="custom-edit-form">' . esc_html( $form ) . '</div>';
			}
		}

		/**
		 * Handles custom views.
		 *
		 * @return void
		 */
		static public function handle_custom_views() {
			// Implementation for handling custom views
			// This method will handle the custom views for the admin list table
			// It will be responsible for rendering and managing the custom views
			// Fetch custom views data
			$custom_views = apply_filters( 'wp_rest_api_log_custom_views', array() );

			// Render custom views
			foreach ( $custom_views as $view ) {
				echo '<div class="custom-view">' . esc_html( $view ) . '</div>';
			}
		}

		/**
		 * Handles custom modals.
		 *
		 * @return void
		 */
		static public function handle_custom_modals() {
			// Implementation for handling custom modals
			// This method will handle the custom modals for the admin list table
			// It will be responsible for rendering and managing the custom modals
			// Fetch custom modals data
			$custom_modals = apply_filters( 'wp_rest_api_log_custom_modals', array() );

			// Render custom modals
			foreach ( $custom_modals as $modal ) {
				echo '<div class="custom-modal">' . esc_html( $modal ) . '</div>';
			}
		}
	}
}
