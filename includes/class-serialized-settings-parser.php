<?php
/**
 * Bounded primitive parser, never PHP unserialization or a request handler.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded byte parser; returned trees stay private to the codec. */
final class Alynt_Drime_Backups_Uploader_Serialized_Settings_Parser {
	/**
	 * Bounded private input.
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
	 * Combined key/value count.
	 *
	 * @var int
	 */
	private $nodes = 0;
	/**
	 * Root scan Boolean token offset.
	 *
	 * @var int|null
	 */
	private $scan;

	/**
	 * No IO or callbacks.
	 *
	 * @param string $raw Bounded bytes.
	 */
	private function __construct( $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Parse plain supported data with no autoloading, hooks or magic methods.
	 *
	 * @param mixed $raw Private serialized bytes.
	 * @return array|false Internal tree/span, never a public response.
	 */
	public static function parse( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > 1048576 ) {
			return false;
		}
		$self = new self( $raw );
		try {
			$value = $self->value( 0 );
			if ( ! is_array( $value ) || strlen( $raw ) !== $self->position ) {
				return false;
			}
			return array(
				'value'  => $value,
				'offset' => $self->scan,
			);
		} catch ( UnexpectedValueException $error ) {
			return false;
		}
	}

	/**
	 * Consume exact bounded syntax; no input fragment in errors.
	 *
	 * @param string $literal Fixed syntax.
	 * @return void
	 * @throws UnexpectedValueException Unsupported bytes.
	 */
	private function take( $literal ) {
		if ( substr( $this->raw, $this->position, strlen( $literal ) ) !== $literal ) {
			throw new UnexpectedValueException( 'Serialized settings unavailable.' );
		}
		$this->position += strlen( $literal );
	}

	/**
	 * Check decimal length/range before casting or allocation.
	 *
	 * @param string $delimiter Fixed delimiter.
	 * @param string $limit Maximum decimal magnitude.
	 * @return string Canonical digits.
	 * @throws UnexpectedValueException Unsupported number.
	 */
	private function digits( $delimiter, $limit ) {
		$start  = $this->position;
		$length = strlen( $this->raw );
		while ( $this->position < $length && false !== strpos( '0123456789', $this->raw[ $this->position ] ) ) {
			++$this->position;
			if ( $this->position - $start > strlen( $limit ) ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
		}
		$n = substr( $this->raw, $start, $this->position - $start );
		if ( '' === $n || ( strlen( $n ) > 1 && '0' === $n[0] ) || ( strlen( $n ) === strlen( $limit ) && strcmp( $n, $limit ) > 0 ) ) {
			throw new UnexpectedValueException( 'Serialized settings unavailable.' );
		}
		$this->take( $delimiter );
		return $n;
	}

	/**
	 * Root depth is zero; keys and values each consume a node and depth.
	 *
	 * @param int $depth Current depth (0..15).
	 * @return mixed Plain bounded value.
	 * @throws UnexpectedValueException Unsupported value.
	 */
	private function value( $depth ) {
		if ( $depth >= 16 || ++$this->nodes > 4096 || $this->position >= strlen( $this->raw ) ) {
			throw new UnexpectedValueException( 'Serialized settings unavailable.' );
		}
		$type = $this->raw[ $this->position ];
		if ( 'N' === $type ) {
			$this->take( 'N;' );
			return null;
		}
		$this->take( $type . ':' );
		if ( 'b' === $type ) {
			return '1' === $this->digits( ';', '1' );
		}
		if ( 'i' === $type ) {
			$negative = '-' === substr( $this->raw, $this->position, 1 );
			if ( $negative ) {
				$this->take( '-' );
			}
			$n = $this->digits( ';', $negative ? substr( (string) PHP_INT_MIN, 1 ) : (string) PHP_INT_MAX );
			if ( $negative && '0' === $n ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
			return (int) ( $negative ? '-' . $n : $n );
		}
		if ( 's' === $type ) {
			$length = (int) $this->digits( ':', '1048576' );
			$this->take( '"' );
			if ( $length > strlen( $this->raw ) - $this->position - 2 ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
			$text            = substr( $this->raw, $this->position, $length );
			$this->position += $length;
			$this->take( '";' );
			if ( 1 !== preg_match( '//u', $text ) ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
			return $text;
		}
		if ( 'a' !== $type ) {
			throw new UnexpectedValueException( 'Serialized settings unavailable.' );
		}
		$count = (int) $this->digits( ':', '2048' );
		if ( $count > intdiv( 4096 - $this->nodes, 2 ) ) {
			throw new UnexpectedValueException( 'Serialized settings unavailable.' );
		}
		$this->take( '{' );
		$out = array();
		for ( $i = 0; $i < $count; ++$i ) {
			$key = $this->value( $depth + 1 );
			if ( ! is_int( $key ) && ! is_string( $key ) ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
			$probe = array( $key => true );
			if ( key( $probe ) !== $key || array_key_exists( $key, $out ) ) {
				throw new UnexpectedValueException( 'Serialized settings unavailable.' );
			}
			$offset      = $this->position;
			$out[ $key ] = $this->value( $depth + 1 );
			if ( 0 === $depth && 'auto_scan_enabled' === $key ) {
				$this->scan = $offset;
			}
		}
		$this->take( '}' );
		return $out;
	}
}
