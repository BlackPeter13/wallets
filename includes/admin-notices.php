<?php

/**
 * Provides an easy way to enqueue and display dismissible admin notices in the WordPress admin interface.
 * @link https://www.alexgeorgiou.gr/persistently-dismissible-notices-wordpress/ Read more about the implementation at my blog.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Notices' ) ) {

	class Dashed_Slug_Wallets_Admin_Notices {

		private static $_instance;
		private $admin_notices;
		const TYPES = 'error,warning,info,success';

		private function __construct() {
			$this->admin_notices = new stdClass();
			foreach ( explode( ',', self::TYPES ) as $type ) {
				$this->admin_notices->{$type} = array();
			}
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_notices' : 'admin_notices', array( &$this, 'action_admin_notices' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );
		}

		public static function get_instance() {
			if ( ! ( self::$_instance instanceof self ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function action_admin_init() {
			$dismiss_option = filter_input( INPUT_GET, 'wallets_dismiss', FILTER_SANITIZE_STRING );
			if ( is_string( $dismiss_option ) ) {
				Dashed_Slug_Wallets::update_option( "wallets_dismissed_$dismiss_option", true );
				wp_die();
			}

			if ( current_user_can( 'manage_wallets' ) ) {
				$this->info(
					__( '<strong>IMPORTANT</strong>: Read the <a href="https://www.dashed-slug.net/wallets-3-0-0">release notes for version 3.0.0</a>.', 'wallets' ),
					'release-notes-3-0-0'
				);
			}
		}

		public function action_admin_enqueue_scripts() {
			wp_enqueue_script(
				'wallets-notify',
				plugins_url( 'assets/scripts/wallets-notify.min.js', DSWALLETS_PATH . '/wallets.php' ),
				array( 'jquery' ),
				'3.0.3'
			);

		}

		public function action_admin_notices() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			foreach ( explode( ',', self::TYPES ) as $type ) {
				foreach ( $this->admin_notices->{$type} as $admin_notice ) {

					$dismiss_url = add_query_arg( array(
						'wallets_dismiss' => $admin_notice->dismiss_option
					), call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url' ) );

					if ( ! Dashed_Slug_Wallets::get_option( "wallets_dismissed_$admin_notice->dismiss_option" ) ) {
						?><div
							class="notice wallets-notice notice-<?php echo $type;

							if ( $admin_notice->dismiss_option ) {
								echo ' is-dismissible" data-dismiss-url="' . esc_url( $dismiss_url );
							} ?>">

							<h2><?php echo "Bitcoin and Altcoin Wallets $type"; ?></h2>
							<p><?php echo $admin_notice->message; ?></p>

						</div><?php
					}
				}
			}
		}

		public function error( $message, $dismiss_option = false ) {
			$this->notice( 'error', $message, $dismiss_option );
		}

		public function warning( $message, $dismiss_option = false ) {
			$this->notice( 'warning', $message, $dismiss_option );
		}

		public function success( $message, $dismiss_option = false ) {
			$this->notice( 'success', $message, $dismiss_option );
		}

		public function info( $message, $dismiss_option = false ) {
			$this->notice( 'info', $message, $dismiss_option );
		}

		private function notice( $type, $message, $dismiss_option ) {
			$notice = new stdClass();
			$notice->message = $message;
			$notice->dismiss_option = $dismiss_option;

			$this->admin_notices->{$type}[] = $notice;
		}

		public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {
			if ( ! ( error_reporting() & $errno ) ) {
				// This error code is not included in error_reporting
				return;
			}

			$message = "errstr: $errstr, errfile: $errfile, errline: $errline, PHP: " . PHP_VERSION . " OS: " . PHP_OS;

			$self = self::get_instance();
			switch ($errno) {
				case E_USER_ERROR:
					$self->error( $message );
					break;

				case E_USER_WARNING:
					$self->warning( $message );
					break;

				case E_USER_NOTICE:
				default:
					$self->notice( $message );
					break;
			}

			// write to wp-content/debug.log if logging enabled
			error_log( $message );

			// Don't execute PHP internal error handler
			return true;
		}
	}

}
