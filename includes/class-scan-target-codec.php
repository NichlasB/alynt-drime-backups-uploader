<?php
/**
 * Dormant snapshot/queue typed byte deltas; no storage or file operations.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-scan-effect-manifest.php';
require_once __DIR__ . '/class-scan-target-bytes.php';

/** Internal after bytes remain data, never a native capability or public status. */
final class Alynt_Drime_Backups_Uploader_Scan_Target_Codec {
	/**
	 * Replace one fixed producer snapshot, retaining unchanged entry spans.
	 *
	 * @param mixed $role Fixed producer role.
	 * @param mixed $before Existing private bytes.
	 * @param mixed $replacement Proposed snapshot bytes, not persisted.
	 * @return array|false Private non-authorizing candidate.
	 */
	public static function snapshot( $role, $before, $replacement ) {
		if ( ! in_array( $role, array( 'wpvivid_snapshot', 'outbox_snapshot' ), true ) ) {
			return false;
		}
		$a = self::read( $role, $before );
		$b = self::read( $role, $replacement );
		if ( false === $a || false === $b || ! self::snapshots( $a['value'] ) || ! self::snapshots( $b['value'] ) ) {
			return false;
		}
		$entries = array();
		foreach ( $b['value'] as $key => $value ) {
			$same      = isset( $a['value'][ $key ] ) && $a['value'][ $key ]['size'] === $value['size'] && $a['value'][ $key ]['mtime'] === $value['mtime'];
			$span      = $same ? $a['spans'][ $key ] : $b['spans'][ $key ];
			$entries[] = substr( $same ? $before : $replacement, $span[0], $span[2] - $span[0] );
		}
		return self::candidate( $role, $before, Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::root( $entries ) );
	}

	/**
	 * Exact fixed queue deltas with explicit raw dependency data, not evidence.
	 *
	 * @param mixed $before Existing queue bytes.
	 * @param mixed $verb Fixed verb.
	 * @param mixed $payload Serialized additions or exact signature/attempt pair.
	 * @param mixed $dependencies Exact active/uploaded/failed raw arrays.
	 * @return array|false Private candidate, not an enqueue or upload permission.
	 */
	public static function queue( $before, $verb, $payload, $dependencies ) {
		$a = self::read( 'queue', $before );
		if ( false === $a || ! self::items( $a['value'] ) || ! is_array( $dependencies ) || array_keys( $dependencies ) !== array( 'active', 'uploaded', 'failed' ) ) {
			return false;
		}
		$deps = array();
		foreach ( $dependencies as $role => $raw ) {
			$parsed = self::read( $role, $raw );
			if ( false === $parsed || count( $parsed['value'] ) > 128 ) {
				return false;
			}
			$deps[ $role ] = $parsed['value'];
			if ( 'active' !== $role && ! self::registry( $deps[ $role ] ) ) {
				return false;
			}
		}
		$entries = array();
		foreach ( $a['spans'] as $key => $span ) {
			$entries[ $key ] = substr( $before, $span[0], $span[2] - $span[0] );
		}
		if ( in_array( $verb, array( 'append_new', 'prepend_requeue' ), true ) ) {
			$b = self::read( 'queue', $payload );
			if ( false === $b || strlen( $payload ) > 262144 || count( $b['value'] ) > 64 || ( 'prepend_requeue' === $verb && 1 !== count( $b['value'] ) ) || ! self::items( $b['value'] ) ) {
				return false;
			}
			$seen = $a['value'];
			foreach ( $b['value'] as $key => $item ) {
				if ( 'prepend_requeue' === $verb && ( ! isset( $deps['failed'][ $key ] ) || ( $deps['failed'][ $key ]['path'] ?? null ) !== $item['path'] ) ) {
					return false;
				}
				if ( isset( $seen[ $key ] ) || isset( $deps['uploaded'][ $key ] ) || ( 'append_new' === $verb && isset( $deps['failed'][ $key ] ) ) || self::duplicate( $item, $seen ) || self::duplicate( $item, $deps['uploaded'] ) || self::active_duplicate( $item, $deps['active'] ) ) {
					return false;
				}
				$seen[ $key ] = $item;
			}
			$added = array();
			foreach ( $b['spans'] as $key => $span ) {
				$added[ $key ] = substr( $payload, $span[0], $span[2] - $span[0] );
			}
			$entries = 'append_new' === $verb ? $entries + $added : $added + $entries;
		} elseif ( in_array( $verb, array( 'remove_existing', 'set_attempts_existing' ), true ) ) {
			if ( ! is_array( $payload ) || array_keys( $payload ) !== ( 'remove_existing' === $verb ? array( 'signature' ) : array( 'signature', 'attempts' ) ) || ! self::digest( $payload['signature'] ) || ! isset( $a['value'][ $payload['signature'] ] ) ) {
				return false;
			}
			$key = $payload['signature'];
			if ( 'remove_existing' === $verb ) {
				unset( $entries[ $key ] );
			} else {
				if ( ! is_int( $payload['attempts'] ) || $payload['attempts'] < 0 || $payload['attempts'] >= PHP_INT_MAX ) {
					return false;
				}
				$span  = $a['spans'][ $key ];
				$raw   = substr( $before, $span[1], $span[2] - $span[1] );
				$entry = Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $raw );
				if ( ! isset( $entry['spans']['attempts'] ) ) {
					return false;
				}
				$token           = $entry['spans']['attempts'];
				$raw             = substr_replace( $raw, 'i:' . $payload['attempts'] . ';', $token[1], $token[2] - $token[1] );
				$entries[ $key ] = substr( $before, $span[0], $span[1] - $span[0] ) . $raw;
			}
		} else {
			return false;
		}
		return count( $entries ) <= 128 ? self::candidate( 'queue', $before, Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::root( $entries ) ) : false;
	}

	/**
	 * Fixed byte bound before the shared unchanged primitive grammar.
	 *
	 * @param string $role Fixed role.
	 * @param mixed  $raw Private bytes.
	 * @return array|false
	 */
	private static function read( $role, $raw ) {
		$roles = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
		return is_string( $raw ) && strlen( $raw ) <= $roles[ $role ][1] ? Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $raw ) : false;
	}

	/**
	 * Validate fixed snapshot entries without normalizing or trimming.
	 *
	 * @param array $values Parsed map.
	 * @return bool
	 */
	private static function snapshots( $values ) {
		if ( count( $values ) > 512 ) {
			return false;
		}
		foreach ( $values as $key => $value ) {
			if ( ! self::digest( $key ) || ! is_array( $value ) || count( $value ) !== 2 || ! is_int( $value['size'] ?? null ) || $value['size'] < 0 || ! is_int( $value['mtime'] ?? null ) || $value['mtime'] < 0 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Required identity/path/attempt types; supported unknown fields stay intact.
	 *
	 * @param array $items Parsed queue.
	 * @return bool
	 */
	private static function items( $items ) {
		if ( count( $items ) > 128 ) {
			return false;
		}
		foreach ( $items as $key => $item ) {
			if ( ! self::digest( $key ) || ! is_array( $item ) || ( $item['signature'] ?? null ) !== $key || ! is_string( $item['path'] ?? null ) || '' === $item['path'] || false !== strpos( $item['path'], '://' ) || ! is_string( $item['name'] ?? null ) || ! is_int( $item['attempts'] ?? null ) || $item['attempts'] < 0 || $item['attempts'] >= PHP_INT_MAX ) {
				return false;
			}
		}
		return self::registry( $items );
	}

	/**
	 * Dependency records are bounded plain maps, not arbitrary castable values.
	 *
	 * @param array $records Current records.
	 * @return bool
	 */
	private static function registry( $records ) {
		foreach ( $records as $key => $record ) {
			if ( ! self::digest( $key ) || ! is_array( $record ) || ( isset( $record['path'] ) && ( ! is_string( $record['path'] ) || false !== strpos( $record['path'], '://' ) ) ) || ( isset( $record['wpvivid'] ) && ( ! is_array( $record['wpvivid'] ) || ( isset( $record['wpvivid']['backup_id'] ) && ! is_string( $record['wpvivid']['backup_id'] ) && ! is_int( $record['wpvivid']['backup_id'] ) ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Existing path and WPvivid file dedup, without WordPress callbacks.
	 *
	 * @param array $item Proposed item.
	 * @param array $items Current records.
	 * @return bool
	 */
	private static function duplicate( $item, $items ) {
		foreach ( $items as $other ) {
			if ( ! is_array( $other ) ) {
				return true;
			}
			if ( isset( $other['path'] ) && ( ! is_string( $other['path'] ) || self::path( $other['path'] ) === self::path( $item['path'] ) ) ) {
				return true;
			}
			$id = $item['wpvivid']['backup_id'] ?? null;
			if ( ! empty( $id ) && (string) ( $other['wpvivid']['backup_id'] ?? '' ) === (string) $id && ( $other['name'] ?? null ) === $item['name'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Active state can use path/signature rather than a signature-keyed map.
	 *
	 * @param array $item Proposal.
	 * @param array $active Parsed private active data.
	 * @return bool
	 */
	private static function active_duplicate( $item, $active ) {
		if ( ! $active ) {
			return false;
		}
		if ( ! self::digest( $active['signature'] ?? null ) || ! is_string( $active['local_file'] ?? null ) || false !== strpos( $active['local_file'], '://' ) ) {
			return true;
		}
		return $active['signature'] === $item['signature'] || self::path( $active['local_file'] ) === self::path( $item['path'] );
	}

	/**
	 * Pure non-stream core path normalization subset; no filesystem resolution.
	 *
	 * @param string $path Supported path.
	 * @return string
	 */
	private static function path( $path ) {
		$path = preg_replace( '|(?<=.)/+|', '/', str_replace( '\\', '/', $path ) );
		return ':' === substr( $path, 1, 1 ) ? ucfirst( $path ) : $path;
	}

	/**
	 * Fixed digest grammar.
	 *
	 * @param mixed $value Identifier.
	 * @return bool
	 */
	private static function digest( $value ) {
		return is_string( $value ) && 64 === strlen( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/**
	 * Reparse output and keep private bytes separate from all support flags.
	 *
	 * @param string $role Fixed target.
	 * @param string $before Original bytes.
	 * @param string $after Candidate bytes.
	 * @return array|false
	 */
	private static function candidate( $role, $before, $after ) {
		$p = self::read( $role, $after );
		return false === $p || ( 'queue' === $role && ! self::items( $p['value'] ) ) || ( 'queue' !== $role && ! self::snapshots( $p['value'] ) ) ? false : Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::disposition() + array(
			'before_hash' => hash( 'sha256', $before ),
			'after_hash'  => hash( 'sha256', $after ),
			'after'       => $after,
		);
	}
}
