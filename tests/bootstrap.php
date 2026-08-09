<?php
/**
 * Bootstrap file for plugin tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

define( 'ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH', dirname( __DIR__ ) );
define( 'ABSPATH', ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . DIRECTORY_SEPARATOR );
define( 'WP_PLUGIN_DIR', dirname( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH ) );
define( 'WP_CONTENT_DIR', ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . DIRECTORY_SEPARATOR . 'wp-content' );
define( 'WPINC', 'wp-includes' );

require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/vendor/autoload.php';
require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/Support/ProducerAdapterAssertions.php';
require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/Support/ServerRunnerCliTestCase.php';
require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/Support/ServerRunnerProductionRestoreFixture.php';
require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/Support/UploaderTestCase.php';

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( $value, '/\\' ) . DIRECTORY_SEPARATOR;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf( '%08x-%04x-4%03x-a%03x-%012x', mt_rand(), mt_rand( 0, 0xffff ), mt_rand( 0, 0xfff ), mt_rand( 0, 0xfff ), mt_rand() );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		unset( $referer );
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-for-' . esc_attr( (string) $action ) . '">';
		if ( $echo ) {
			echo $field;
		}

		return $field;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		unset( $domain, $deprecated, $plugin_rel_path );
		return true;
	}
}

require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/alynt-drime-backups-uploader.php';
require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/Support/TestDrimeClient.php';
