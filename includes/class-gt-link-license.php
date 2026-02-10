<?php
/**
 * License manager for GT Link Manager.
 *
 * Handles license activation, deactivation, verification, and auto-updates
 * via FluentCart Pro licensing API.
 *
 * @package GTLinkManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GT_Link_License {

	private const LICENSE_SERVER = 'https://gauravtiwari.org/';
	private const ITEM_ID       = 1150898;
	private const OPTION_KEY    = 'gt_link_license';
	private const LAST_CHECK    = 'gt_link_license_last_check';
	private const UPDATE_CACHE  = 'gt_link_update_info';
	private const CHECK_RESULT  = 'gt_link_update_check_result';
	private const CRON_HOOK     = 'gt_link_verify_license';

	private string $plugin_file;
	private string $plugin_basename;

	public static function init(): self {
		$instance = new self( GT_LINK_MANAGER_FILE );
		$instance->hooks();
		return $instance;
	}

	private function __construct( string $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
	}

	private function hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_update_transient' ) );
		add_action( 'admin_init', array( $this, 'maybe_check_for_updates' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'plugin_action_links' ) );
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}

		add_action( self::CRON_HOOK, array( $this, 'verify_remote_license' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules
	 * @return array<string, array<string, mixed>>
	 */
	public function register_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'gt-link-manager' ),
			);
		}

		return $schedules;
	}

	public static function clear_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public function register_menu(): void {
		add_submenu_page(
			'gt-links',
			esc_html__( 'License', 'gt-link-manager' ),
			esc_html__( 'License', 'gt-link-manager' ),
			'manage_options',
			'gt-links-license',
			array( $this, 'render_license_page' )
		);
	}

	public function handle_license_actions(): void {
		if ( ! isset( $_POST['gt_license_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'gt-links-license' !== $page ) {
			return;
		}

		check_admin_referer( 'gt_link_license_nonce' );

		$action = sanitize_text_field( (string) wp_unslash( $_POST['gt_license_action'] ) );

		if ( 'activate' === $action ) {
			$key = sanitize_text_field( trim( (string) wp_unslash( $_POST['license_key'] ?? '' ) ) );
			if ( '' === $key ) {
				add_settings_error( 'gt_link_license', 'empty_key', __( 'Please enter a license key.', 'gt-link-manager' ), 'error' );
				return;
			}
			$result = $this->activate_license( $key );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'gt_link_license', 'activation_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'gt_link_license', 'activated', __( 'License activated successfully. Checking for updates...', 'gt-link-manager' ), 'success' );
				$this->force_update_check();
			}
		} elseif ( 'deactivate' === $action ) {
			$result = $this->deactivate_license();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'gt_link_license', 'deactivation_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'gt_link_license', 'deactivated', __( 'License deactivated successfully.', 'gt-link-manager' ), 'success' );
			}
		}
	}

	/**
	 * @return array<string, string>|WP_Error
	 */
	public function activate_license( string $key ) {
		$response = $this->api_request( 'activate_license', array(
			'license_key' => $key,
			'item_id'     => self::ITEM_ID,
			'site_url'    => home_url(),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['success'] ) || empty( $response['status'] ) || 'valid' !== $response['status'] ) {
			$message = $response['message'] ?? __( 'License activation failed. Please check your key and try again.', 'gt-link-manager' );
			return new WP_Error( 'activation_failed', $message );
		}

		$license_data = array(
			'license_key'     => $key,
			'status'          => 'valid',
			'activation_hash' => $response['activation_hash'] ?? '',
			'expiration_date' => $response['expiration_date'] ?? 'lifetime',
			'product_title'   => $response['product_title'] ?? 'GT Link Manager',
			'activated_at'    => current_time( 'mysql' ),
		);

		update_option( self::OPTION_KEY, $license_data );
		update_option( self::LAST_CHECK, time() );
		delete_transient( self::UPDATE_CACHE );

		return $license_data;
	}

	/**
	 * @return array<string, string>|WP_Error
	 */
	public function deactivate_license() {
		$license = $this->get_license_data();

		if ( '' === $license['license_key'] ) {
			return new WP_Error( 'no_license', __( 'No license key found.', 'gt-link-manager' ) );
		}

		$this->api_request( 'deactivate_license', array(
			'license_key' => $license['license_key'],
			'item_id'     => self::ITEM_ID,
			'site_url'    => home_url(),
		) );

		$default_data = array(
			'license_key'     => '',
			'status'          => 'inactive',
			'activation_hash' => '',
			'expiration_date' => '',
			'product_title'   => '',
			'activated_at'    => '',
		);

		update_option( self::OPTION_KEY, $default_data );
		delete_option( self::LAST_CHECK );
		delete_transient( self::UPDATE_CACHE );

		return $default_data;
	}

	public function verify_remote_license(): void {
		$license = $this->get_license_data();

		if ( '' === $license['license_key'] || 'valid' !== $license['status'] ) {
			return;
		}

		$params = array(
			'item_id'  => self::ITEM_ID,
			'site_url' => home_url(),
		);

		if ( '' !== $license['activation_hash'] ) {
			$params['activation_hash'] = $license['activation_hash'];
		} else {
			$params['license_key'] = $license['license_key'];
		}

		$response = $this->api_request( 'check_license', $params );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$remote_status = $response['status'] ?? 'invalid';

		if ( 'valid' !== $remote_status ) {
			$license['status'] = $remote_status;
			update_option( self::OPTION_KEY, $license );
		}

		update_option( self::LAST_CHECK, time() );
	}

	/**
	 * @param object $transient_data
	 * @return object
	 */
	public function check_for_update( $transient_data ) {
		if ( empty( $transient_data->checked ) ) {
			return $transient_data;
		}

		$license = $this->get_license_data();
		if ( '' === $license['license_key'] || 'valid' !== $license['status'] ) {
			return $transient_data;
		}

		$update_info = get_transient( self::UPDATE_CACHE );

		if ( false === $update_info ) {
			$params = array(
				'item_id'  => self::ITEM_ID,
				'site_url' => home_url(),
			);

			if ( '' !== $license['activation_hash'] ) {
				$params['activation_hash'] = $license['activation_hash'];
			} else {
				$params['license_key'] = $license['license_key'];
			}

			$update_info = $this->api_request( 'get_license_version', $params );

			if ( ! is_wp_error( $update_info ) ) {
				set_transient( self::UPDATE_CACHE, $update_info, 12 * HOUR_IN_SECONDS );
			}
		}

		if ( is_wp_error( $update_info ) || empty( $update_info['new_version'] ) ) {
			return $transient_data;
		}

		if ( version_compare( $update_info['new_version'], GT_LINK_MANAGER_VERSION, '>' ) ) {
			$plugin_data = (object) array(
				'id'            => $this->plugin_basename,
				'slug'          => 'gt-link-manager',
				'plugin'        => $this->plugin_basename,
				'new_version'   => $update_info['new_version'],
				'url'           => $update_info['url'] ?? 'https://gauravtiwari.org/product/gt-link-manager/',
				'package'       => $update_info['package'] ?? '',
				'icons'         => $update_info['icons'] ?? array(),
				'banners'       => $update_info['banners'] ?? array(),
				'tested'        => $update_info['tested'] ?? '',
				'requires_php'  => $update_info['requires_php'] ?? '8.2',
				'compatibility' => new stdClass(),
			);

			$transient_data->response[ $this->plugin_basename ] = $plugin_data;
		}

		return $transient_data;
	}

	/**
	 * @param false|object|array<string, mixed> $result
	 * @param string                            $action
	 * @param object                            $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'gt-link-manager' !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$update_info = get_transient( self::UPDATE_CACHE );
		if ( empty( $update_info ) || is_wp_error( $update_info ) ) {
			return $result;
		}

		return (object) array(
			'name'          => $update_info['name'] ?? 'GT Link Manager',
			'slug'          => 'gt-link-manager',
			'version'       => $update_info['new_version'] ?? '',
			'author'        => '<a href="https://gauravtiwari.org">Gaurav Tiwari</a>',
			'homepage'      => $update_info['homepage'] ?? 'https://gauravtiwari.org/product/gt-link-manager/',
			'download_link' => $update_info['package'] ?? '',
			'trunk'         => $update_info['trunk'] ?? '',
			'last_updated'  => $update_info['last_updated'] ?? '',
			'sections'      => $update_info['sections'] ?? array(),
			'banners'       => $update_info['banners'] ?? array(),
			'icons'         => $update_info['icons'] ?? array(),
			'requires'      => $update_info['requires'] ?? '6.4',
			'requires_php'  => $update_info['requires_php'] ?? '8.2',
			'tested'        => $update_info['tested'] ?? '',
		);
	}

	public function clear_update_transient(): void {
		delete_transient( self::UPDATE_CACHE );
	}

	private function force_update_check(): void {
		delete_transient( self::UPDATE_CACHE );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
	}

	public function maybe_check_for_updates(): void {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( empty( $_GET['gt_link_check_update'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gt_link_check_update' ) ) {
			return;
		}

		delete_transient( self::UPDATE_CACHE );
		delete_site_transient( 'update_plugins' );

		wp_update_plugins();

		$update_data = get_site_transient( 'update_plugins' );
		$result      = array(
			'checked_at' => time(),
			'available'  => false,
			'version'    => '',
		);

		if ( isset( $update_data->response[ $this->plugin_basename ] ) ) {
			$plugin_update       = $update_data->response[ $this->plugin_basename ];
			$result['available'] = true;
			$result['version']   = $plugin_update->new_version ?? '';
		}

		set_transient( self::CHECK_RESULT, $result, 2 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=gt-links-license&gt_link_update_checked=1' ) );
		exit;
	}

	/**
	 * @param array<int, string> $links
	 * @return array<int, string>
	 */
	public function plugin_action_links( array $links ): array {
		$license_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=gt-links-license' ),
			__( 'License', 'gt-link-manager' )
		);

		array_unshift( $links, $license_link );

		return $links;
	}

	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( strpos( $screen->id, 'gt-links' ) === false ) {
			return;
		}

		if ( ! empty( $_GET['page'] ) && 'gt-links-license' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$license = $this->get_license_data();
		$status  = $license['status'];

		if ( 'valid' === $status ) {
			return;
		}

		$license_url = admin_url( 'admin.php?page=gt-links-license' );

		if ( 'expired' === $status ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Your GT Link Manager license has expired. Renew to continue receiving updates.', 'gt-link-manager' ),
				esc_url( $license_url ),
				esc_html__( 'Manage License', 'gt-link-manager' )
			);
		} else {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Get a free license key to enable direct updates for GT Link Manager.', 'gt-link-manager' ),
				esc_url( $license_url ),
				esc_html__( 'Activate License', 'gt-link-manager' )
			);
		}
	}

	public function render_license_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'gt-link-manager' ) );
		}

		$license       = $this->get_license_data();
		$status        = $license['status'];
		$key           = $license['license_key'];
		$expires       = $license['expiration_date'];
		$update_result = get_transient( self::CHECK_RESULT );

		settings_errors( 'gt_link_license' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'GT Link Manager - License', 'gt-link-manager' ) . '</h1>';

		// Updates card.
		echo '<div class="card" style="max-width: 600px; margin-top: 20px;">';
		echo '<h2 style="margin-top: 0;">' . esc_html__( 'Updates', 'gt-link-manager' ) . '</h2>';

		if ( 'valid' === $status ) {
			echo '<p>' . esc_html__( 'Check for plugin updates from the license server.', 'gt-link-manager' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=gt-links-license&gt_link_check_update=1' ), 'gt_link_check_update' ) ) . '">' . esc_html__( 'Check for Updates', 'gt-link-manager' ) . '</a></p>';

			if ( ! empty( $update_result ) && is_array( $update_result ) ) {
				if ( ! empty( $update_result['available'] ) ) {
					echo '<div style="background: #e7f3ff; border: 1px solid #b6d9ff; padding: 10px 12px; border-radius: 4px;">';
					printf( esc_html__( 'Update found. Latest version: %s', 'gt-link-manager' ), esc_html( $update_result['version'] ?: __( 'Unknown', 'gt-link-manager' ) ) );
					echo '</div>';
				} else {
					echo '<div style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 10px 12px; border-radius: 4px;">';
					esc_html_e( 'No updates found. You are on the latest version.', 'gt-link-manager' );
					echo '</div>';
				}
			}
		} else {
			echo '<p>' . esc_html__( 'Activate your license key below to enable update checks.', 'gt-link-manager' ) . '</p>';
		}

		echo '</div>';

		// License status card.
		echo '<div class="card" style="max-width: 600px; margin-top: 20px;">';
		echo '<h2 style="margin-top: 0;">' . esc_html__( 'License Status', 'gt-link-manager' ) . '</h2>';

		if ( 'valid' === $status ) {
			echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">';
			echo '<strong style="color: #155724;">&#10003; ' . esc_html__( 'License Active', 'gt-link-manager' ) . '</strong>';
			if ( $expires && 'lifetime' !== $expires ) {
				echo '<br><small>' . sprintf( esc_html__( 'Expires: %s', 'gt-link-manager' ), esc_html( $expires ) ) . '</small>';
			} elseif ( 'lifetime' === $expires ) {
				echo '<br><small>' . esc_html__( 'Lifetime license', 'gt-link-manager' ) . '</small>';
			}
			echo '</div>';

			echo '<form method="post">';
			wp_nonce_field( 'gt_link_license_nonce' );
			echo '<input type="hidden" name="gt_license_action" value="deactivate">';
			echo '<p><code style="font-size: 14px; padding: 4px 8px;">' . esc_html( $this->mask_key( $key ) ) . '</code></p>';
			echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Deactivate License', 'gt-link-manager' ) . '"></p>';
			echo '</form>';

		} elseif ( 'expired' === $status ) {
			echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">';
			echo '<strong style="color: #856404;">&#9888; ' . esc_html__( 'License Expired', 'gt-link-manager' ) . '</strong>';
			if ( $expires ) {
				echo '<br><small>' . sprintf( esc_html__( 'Expired: %s', 'gt-link-manager' ), esc_html( $expires ) ) . '</small>';
			}
			echo '</div>';

			echo '<p>' . esc_html__( 'Your license has expired. Renew it to continue receiving updates.', 'gt-link-manager' ) . '</p>';
			echo '<p><a href="https://gauravtiwari.org/product/gt-link-manager/" class="button button-primary" target="_blank">' . esc_html__( 'Renew License', 'gt-link-manager' ) . '</a></p>';

			echo '<hr>';
			echo '<form method="post">';
			wp_nonce_field( 'gt_link_license_nonce' );
			echo '<input type="hidden" name="gt_license_action" value="activate">';
			echo '<p><label for="license_key"><strong>' . esc_html__( 'Or enter a new license key:', 'gt-link-manager' ) . '</strong></label><br>';
			echo '<input type="text" id="license_key" name="license_key" class="regular-text" placeholder="' . esc_attr__( 'Enter license key...', 'gt-link-manager' ) . '" style="margin-top: 4px;"></p>';
			echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Activate License', 'gt-link-manager' ) . '"></p>';
			echo '</form>';

		} else {
			echo '<p>' . esc_html__( 'Enter your license key to enable direct updates from your WordPress dashboard.', 'gt-link-manager' ) . '</p>';

			echo '<form method="post">';
			wp_nonce_field( 'gt_link_license_nonce' );
			echo '<input type="hidden" name="gt_license_action" value="activate">';
			echo '<p><label for="license_key"><strong>' . esc_html__( 'License Key', 'gt-link-manager' ) . '</strong></label><br>';
			echo '<input type="text" id="license_key" name="license_key" class="regular-text" placeholder="' . esc_attr__( 'Enter license key...', 'gt-link-manager' ) . '" style="margin-top: 4px;"></p>';
			echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Activate License', 'gt-link-manager' ) . '"></p>';
			echo '</form>';

			echo '<hr>';
			echo '<p>';
			printf(
				/* translators: 1: opening link tag, 2: closing link tag */
				esc_html__( 'Don\'t have a license key? %1$sGet one for free%2$s from gauravtiwari.org. It enables direct plugin updates right from your dashboard.', 'gt-link-manager' ),
				'<a href="https://gauravtiwari.org/product/gt-link-manager/" target="_blank"><strong>',
				'</strong></a>'
			);
			echo '</p>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * @return array<string, string>
	 */
	public function get_license_data(): array {
		$defaults = array(
			'license_key'     => '',
			'status'          => 'inactive',
			'activation_hash' => '',
			'expiration_date' => '',
			'product_title'   => '',
			'activated_at'    => '',
		);

		$data = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $data ) ) {
			return $defaults;
		}

		return wp_parse_args( $data, $defaults );
	}

	public function is_valid(): bool {
		$license = $this->get_license_data();
		return 'valid' === $license['status'];
	}

	/**
	 * @param string               $action
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	private function api_request( string $action, array $params = array() ) {
		$url = add_query_arg(
			array_merge(
				array( 'fluent-cart' => $action ),
				$params
			),
			self::LICENSE_SERVER
		);

		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_error',
				__( 'Could not connect to the license server. Please try again later.', 'gt-link-manager' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || empty( $body ) ) {
			$message = $body['message'] ?? __( 'License server returned an error.', 'gt-link-manager' );
			return new WP_Error( 'api_error', $message );
		}

		return $body;
	}

	private function mask_key( string $key ): string {
		if ( strlen( $key ) <= 8 ) {
			return $key;
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
	}
}
