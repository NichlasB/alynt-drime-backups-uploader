<?php
/**
 * Bounded original superglobal snapshots; no REQUEST or reconstructed raw body.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixed private acquisition, not caller-supplied HTTP facts. */
trait Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Transport {
	/**
	 * Reject every supported override/framing alias, even an empty value.
	 *
	 * @return bool
	 */
	private function plain_headers() {
		foreach ( array( 'HTTP_X_HTTP_METHOD_OVERRIDE', 'HTTP_X_METHOD_OVERRIDE', 'HTTP_X_HTTP_METHOD', 'HTTP_METHOD_OVERRIDE', 'CONTENT_ENCODING', 'HTTP_CONTENT_ENCODING', 'TRANSFER_ENCODING', 'HTTP_TRANSFER_ENCODING', 'HTTP_CONTENT_LENGTH', 'HTTP_CONTENT_TYPE' ) as $key ) {
			if ( array_key_exists( $key, $_SERVER ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Bound and copy flat original strings; no magic objects or references.
	 *
	 * @return array|false
	 */
	private function post_transport() {
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Exact bounded raw/slashed transport; authorization follows, normalization would destroy comparison evidence.
		$type   = $_SERVER['CONTENT_TYPE'] ?? null;
		$length = $_SERVER['CONTENT_LENGTH'] ?? null;
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || '' !== ( $_SERVER['QUERY_STRING'] ?? null ) || array() !== $_GET || array() !== $_FILES || ! $this->plain_headers() || ! is_string( $type ) || strlen( $type ) > 128 || 1 !== preg_match( '/\Aapplication\/x-www-form-urlencoded(?:[ \t]*;[ \t]*charset=(?:UTF-8|"UTF-8"))?[ \t]*\z/iD', $type ) || ! is_string( $length ) || strlen( $length ) > 5 || 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $length ) || (int) $length > 16384 || ! is_array( $_POST ) || 4 !== count( $_POST ) ) {
			return false;
		}
		$post = array();
		foreach ( $_POST as $key => $value ) {
			if ( ! is_string( $key ) || ! in_array( $key, array( 'action', 'purpose', 'confirm', 'nonce' ), true ) || null !== ReflectionReference::fromArrayElement( $_POST, $key ) || ! is_string( $value ) || strlen( $value ) > 2048 ) {
				return false;
			}
			$post[ $key ] = $value;
		}
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return array(
			'type'   => $type,
			'length' => $length,
			'post'   => $post,
		);
	}

	/**
	 * Fixed Tools GET only; query cannot import actor, nonce, redirect or command.
	 *
	 * @return bool
	 */
	private function get_transport() {
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Exact fixed GET context, not mutation authorization.
		return 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? null ) && 'page=alynt-drime-backups-uploader' === ( $_SERVER['QUERY_STRING'] ?? null ) && array( 'page' => 'alynt-drime-backups-uploader' ) === $_GET && null === ReflectionReference::fromArrayElement( $_GET, 'page' ) && array() === $_POST && array() === $_FILES && ! array_key_exists( 'CONTENT_TYPE', $_SERVER ) && ! array_key_exists( 'CONTENT_LENGTH', $_SERVER ) && $this->plain_headers();
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Screen comes from the current genuine admin context, not a posted screen ID.
	 *
	 * @return bool
	 */
	private function tools_context() {
		if ( ! $this->get_transport() || ! function_exists( 'is_admin' ) || ! function_exists( 'get_current_screen' ) || true !== is_admin() ) {
			return false;
		}
		$screen = get_current_screen();
		return is_object( $screen ) && 'WP_Screen' === get_class( $screen ) && 'tools_page_alynt-drime-backups-uploader' === $screen->id && $this->get_transport();
	}
}
