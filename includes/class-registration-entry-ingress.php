<?php
/**
 * Dormant closed-entry wire grammar. Parsing is never authorization.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicitly test-loaded; no superglobals, body IO, hooks or downstream calls. */
final class Alynt_Drime_Backups_Uploader_Registration_Entry_Ingress {
	/** Fixed future transport action, not a registered hook. */
	const ACTION = 'alynt_drime_backups_register_closed';

	/**
	 * Inspect complete synthetic bytes and original WordPress-slashed form.
	 *
	 * Completeness is a transport fact a future approved bounded reader must earn;
	 * this pure function cannot establish HTTP framing or Content-Length truth.
	 *
	 * @param mixed $method Exact method.
	 * @param mixed $content_type Single content type value.
	 * @param mixed $query Raw query string.
	 * @param mixed $get Parsed query map.
	 * @param mixed $files Parsed files map.
	 * @param mixed $raw Original encoded bytes.
	 * @param mixed $post Original slashed strings.
	 * @param mixed $complete Complete-read marker, never authorization.
	 * @param mixed $overrides Method-override sources; all must be absent.
	 * @return array|false Original three domain strings, not unslashed twice.
	 */
	public static function parse( $method, $content_type, $query, $get, $files, $raw, $post, $complete, $overrides ) {
		if ( 'POST' !== $method || ! is_string( $content_type ) || strlen( $content_type ) > 128 || 1 !== preg_match( '/\Aapplication\/x-www-form-urlencoded(?:[ \t]*;[ \t]*charset=(?:UTF-8|"UTF-8"))?[ \t]*\z/iD', $content_type ) || '' !== $query || array() !== $get || array() !== $files || true !== $complete || array() !== $overrides ) {
			return false;
		}
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 16384 || ! is_array( $post ) || 4 !== count( $post ) ) {
			return false;
		}
		$fields = self::decode( $raw );
		if ( false === $fields ) {
			return false;
		}
		$inspect = array();
		foreach ( $post as $key => $value ) {
			if ( null !== ReflectionReference::fromArrayElement( $post, $key ) || ! is_string( $key ) || ! is_string( $value ) || strlen( $value ) > 2048 || ! array_key_exists( $key, $fields ) ) {
				return false;
			}
			$inspect[ $key ] = stripslashes( $value );
			if ( $inspect[ $key ] !== $fields[ $key ] ) {
				return false;
			}
		}
		if ( ! self::bounded( $post ) || self::ACTION !== $fields['action'] || 'register_closed' !== $fields['purpose'] || 'register' !== $fields['confirm'] || '' === $fields['nonce'] ) {
			return false;
		}
		unset( $post['action'] );
		return class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Request_Contract', false ) && false !== Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $post ) ? $post : false;
	}

	/**
	 * Decode exactly once; never use PHP's duplicate-collapsing parse_str.
	 *
	 * @param string $raw Bounded bytes.
	 * @return array|false
	 */
	private static function decode( $raw ) {
		$pairs = explode( '&', $raw );
		if ( 4 !== count( $pairs ) ) {
			return false;
		}
		$fields = array();
		$keys   = array( 'action', 'purpose', 'confirm', 'nonce' );
		foreach ( $pairs as $pair ) {
			if ( false === strpos( $pair, '=' ) || preg_match( '/%(?![a-f0-9]{2})/i', $pair ) ) {
				return false;
			}
			list( $key, $value ) = array_map( 'urldecode', explode( '=', $pair, 2 ) );
			if ( ! in_array( $key, $keys, true ) || array_key_exists( $key, $fields ) || strlen( $value ) > 2048 || 1 !== preg_match( '//u', $value ) || preg_match( '/\p{Cc}/u', $value ) ) {
				return false;
			}
			$fields[ $key ] = $value;
		}
		return self::bounded( $fields ) ? $fields : false;
	}

	/**
	 * Both decoded and original slashed forms include transport overhead.
	 *
	 * @param array $fields Flat strings only.
	 * @return bool
	 */
	private static function bounded( $fields ) {
		$total = 0;
		foreach ( $fields as $key => $value ) {
			$total += strlen( $key ) + strlen( $value );
		}
		$json = json_encode( $fields );
		return $total <= 4096 && is_string( $json ) && strlen( $json ) <= 4096;
	}
}
