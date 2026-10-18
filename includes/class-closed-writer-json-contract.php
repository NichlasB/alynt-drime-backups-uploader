<?php
/**
 * Bounded plain JSON with duplicate-member refusal; no storage reader.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-real-closed-writer-data.php';

/** Test-loaded only; decoded data never authenticates its source. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract {
	const MAX_BYTES = 131072;
	/**
	 * Bounded input.
	 *
	 * @var string
	 */
	private $raw;
	/**
	 * Cursor.
	 *
	 * @var int
	 */
	private $position = 0;
	/**
	 * Shared traversal budget, including object keys.
	 *
	 * @var int
	 */
	private $nodes = 65536;

	/**
	 * Retain already bounded input.
	 *
	 * @param string $raw Bounded bytes.
	 */
	private function __construct( $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Decode a root object without last-member-wins ambiguity or coercion.
	 *
	 * @param mixed $raw Synthetic raw bytes, never a row provider.
	 * @return array|false Plain data only.
	 */
	public static function decode( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_BYTES ) {
			return false;
		}
		$value = json_decode( $raw, true, 16 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $value ) || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::plain( $value ) ) {
			return false;
		}
		$self = new self( $raw );
		$self->space();
		if ( '{' !== ( $raw[ $self->position ] ?? '' ) || ! $self->value( 0 ) ) {
			return false;
		}
		$self->space();
		return strlen( $raw ) === $self->position ? $value : false;
	}

	/**
	 * Consume JSON whitespace only.
	 *
	 * @return void
	 */
	private function space() {
		$this->position += strspn( $this->raw, " \t\r\n", $this->position );
	}

	/**
	 * JSON grammar/UTF-8 was checked before this bounded uniqueness traversal.
	 *
	 * @param int $depth Current nesting.
	 * @return bool
	 */
	private function value( $depth ) {
		if ( $depth > 15 || --$this->nodes < 0 ) {
			return false;
		}
		$this->space();
		$token = $this->raw[ $this->position ] ?? '';
		if ( '"' === $token ) {
			return is_string( $this->string_value() );
		}
		if ( '{' === $token || '[' === $token ) {
			return $this->collection( $depth, '{' === $token );
		}
		$length          = strcspn( $this->raw, ",]} \t\r\n", $this->position );
		$this->position += $length;
		return $length > 0;
	}

	/**
	 * Compare decoded keys, catching escaped aliases and nested duplicates.
	 *
	 * @param int  $depth Nesting.
	 * @param bool $is_object Object versus array.
	 * @return bool
	 */
	private function collection( $depth, $is_object ) {
		++$this->position;
		$this->space();
		$end  = $is_object ? '}' : ']';
		$seen = array();
		if ( ( $this->raw[ $this->position ] ?? '' ) === $end ) {
			++$this->position;
			return true;
		}
		do {
			$this->space();
			if ( $is_object ) {
				if ( --$this->nodes < 0 ) {
					return false;
				}
				$key = $this->string_value();
				if ( ! is_string( $key ) || isset( $seen[ strlen( $key ) . ':' . $key ] ) ) {
					return false;
				}
				$seen[ strlen( $key ) . ':' . $key ] = true;
				$this->space();
				if ( ':' !== ( $this->raw[ $this->position++ ] ?? '' ) ) {
					return false;
				}
			}
			if ( ! $this->value( $depth + 1 ) ) {
				return false;
			}
			$this->space();
			$next = $this->raw[ $this->position++ ] ?? '';
		} while ( ',' === $next );
		return $end === $next;
	}

	/**
	 * Decode one already syntax-checked bounded string.
	 *
	 * @return string|false
	 */
	private function string_value() {
		$start = $this->position;
		if ( '"' !== ( $this->raw[ $this->position++ ] ?? '' ) ) {
			return false;
		}
		$length = strlen( $this->raw );
		while ( $this->position < $length ) {
			$char = $this->raw[ $this->position++ ];
			if ( '\\' === $char ) {
				++$this->position;
			} elseif ( '"' === $char ) {
				return json_decode( substr( $this->raw, $start, $this->position - $start ), true );
			}
		}
		return false;
	}
}
