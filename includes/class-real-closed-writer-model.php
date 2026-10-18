<?php
/**
 * Dormant pure schema-8 data plans; no request factory or admission handle.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-real-closed-writer-validation.php';
require_once __DIR__ . '/trait-real-closed-writer-candidates.php';

/** Test-loaded only; real-shaped data does not prove a trusted event. */
final class Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Validation;
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Candidates;

	/**
	 * Support strict closed originals or schema 8 only, never schema 7.
	 *
	 * @param array  $s Data.
	 * @param string $site Fixed UUID.
	 * @return bool
	 */
	public static function supported( array $s, $site ) {
		return 6 === ( $s['schema_version'] ?? null ) ? Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::source( $s, $site ) : self::valid( $s, $site );
	}

	/**
	 * Context from exact bytes and explicit primitive scope metadata only.
	 * Blog/autoload arguments are simulated data, not proven WordPress facts.
	 *
	 * @param array  $s Closed data.
	 * @param string $raw Exact serialized bytes.
	 * @param string $site Fixed UUID.
	 * @param int    $blog Fixed positive blog ID.
	 * @param string $autoload Explicit row metadata.
	 * @return array|false Private data, never an admission context.
	 */
	public static function context( array $s, $raw, $site, $blog, $autoload ) {
		if ( ! is_int( $blog ) || $blog < 1 || ! self::supported( $s, $site ) ) {
			return false;
		}
		$t = Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $raw, $site, $autoload );
		if ( false === $t ) {
			return false;
		}
		$f = array(
			'version'             => 1,
			'blog_id'             => $blog,
			'site_uuid'           => $site,
			'target'              => 'settings',
			'writer'              => 'disable_auto_scan',
			'codec_version'       => $t['codec_version'],
			'autoload'            => $autoload,
			'enrollment_binding'  => $s['enrollment_binding'],
			'expected_generation' => $s['lifecycle']['permission_generation'],
			'source_digest'       => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::hash( $s ),
			'before_hash'         => $t['before_hash'],
			'after_hash'          => $t['after_hash'],
		);
		return array(
			'context_digest' => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::hash( $f ),
			'before_hash'    => $t['before_hash'],
			'after_hash'     => $t['after_hash'],
			'enabled'        => $t['enabled'],
			'after'          => $t['after'],
		);
	}

	/**
	 * Private bounded receipt status, no commands, secrets or write permissions.
	 *
	 * @param array  $s Snapshot.
	 * @param string $id Observed identity.
	 * @param string $site Fixed site.
	 * @return string
	 */
	public static function lookup( array $s, $id, $site ) {
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $id ) || ! self::supported( $s, $site ) ) {
			return 'unavailable';
		}
		if ( isset( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::legacy( $s )['ids'][ $id ] ) ) {
			return 'domain_conflict';
		}
		if ( 8 === $s['schema_version'] ) {
			foreach ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::records( $s ) as $r ) {
				if ( $id === $r['command']['command_id'] ) {
					return $r['result_generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded';
				}
			}
		}
		return isset( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::identities( $s )[ $id ] ) ? 'domain_conflict' : 'not_found';
	}

	/**
	 * Default-off simulation; no caller flag or return value proves admission.
	 *
	 * @param array       $s Existing data.
	 * @param array       $e Original typed command.
	 * @param string      $raw Exact target bytes.
	 * @param int         $now Clock.
	 * @param int         $blog Fixed blog ID.
	 * @param string      $autoload Explicit metadata.
	 * @param string|null $reserved Once-reserved completion UUID.
	 * @param bool        $enabled Enable pure simulation only.
	 * @return array|false
	 */
	public static function plan( array $s, array $e, $raw, $now, $blog, $autoload, $reserved = null, $enabled = false ) {
		$site = $e['site_uuid'] ?? null;
		if ( true !== $enabled || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::command( $e ) || ! self::supported( $s, $site ) || 'receipt_read' === $e['purpose'] || ! self::time( $s, $e, $now ) || $e['expected_generation'] !== $s['lifecycle']['permission_generation'] || $e['enrollment_binding'] !== $s['enrollment_binding'] || 'not_found' !== self::lookup( $s, $e['command_id'], $site ) ) {
			return false;
		}
		if ( 'upgrade_closed' === $e['purpose'] ) {
			return self::candidate( self::upgrade_candidate( $s, $e, $now ) );
		}
		$c = self::context( $s, $raw, $site, $blog, $autoload );
		if ( 8 !== $s['schema_version'] || false === $c || $e['context_digest'] !== $c['context_digest'] ) {
			return false;
		}
		$w = $s['writer'];
		if ( 'begin_disable' === $e['purpose'] ) {
			if ( null !== $w['pending'] || $e['before_hash'] !== $c['before_hash'] || $e['after_hash'] !== $c['after_hash'] ) {
				return false;
			}
			if ( false === $c['enabled'] ) {
				return self::candidate( $s );
			}
			if ( ! self::room( $s, 3 ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $reserved ) || $reserved === $e['command_id'] || isset( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::identities( $s )[ $reserved ] ) ) {
				return false;
			}
			foreach ( array( 'closure', 'completion' ) as $slot ) {
				if ( null !== $w[ $slot ] ) {
					$map = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::AUTH === $w[ $slot ]['domain'] ? 'authenticated_receipts' : 'internal_receipts';
					$w[ $map ][ $w[ $slot ]['command']['command_id'] ] = $w[ $slot ];
				}
			}
			$w['closure']    = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'begin_disable', $e, $now, $reserved );
			$w['completion'] = null;
			$w['pending']    = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::pending( $w['closure'] );
		} else {
			if ( null === $w['pending'] || ! self::room( $s, 2 ) || $e['observed_hash'] !== $c['before_hash'] ) {
				return false;
			}
			$w['completion'] = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'abandon_closed', $e, $now );
			if ( ! self::terminal_matches( $w['completion'], $w['closure'] ) ) {
				return false;
			}
			$w['pending'] = null;
		}
		$s['writer'] = $w;
		return self::candidate( self::advanced( $s, $now, $site ) );
	}

	/**
	 * Derive a state/target pair from original begin facts, never a storage sink.
	 *
	 * @param array  $s Expected pending state.
	 * @param array  $e Original begin.
	 * @param string $raw Original exact bytes.
	 * @param int    $now Original-window clock.
	 * @param int    $blog Fixed blog.
	 * @param string $autoload Explicit metadata.
	 * @param bool   $enabled Enable simulation only.
	 * @return array|false
	 */
	public static function completion( array $s, array $e, $raw, $now, $blog, $autoload, $enabled = false ) {
		$site = $e['site_uuid'] ?? null;
		if ( true !== $enabled || ! self::valid( $s, $site ) || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::command( $e ) || 'begin_disable' !== $e['purpose'] || ! self::time( $s, $e, $now ) || ! self::room( $s, 2 ) || null === $s['writer']['pending'] || $s['writer']['closure']['command'] !== $e || $s['lifecycle']['permission_generation'] !== $e['expected_generation'] + 1 ) {
			return false;
		}
		$c = self::context( self::before_begin( $s ), $raw, $site, $blog, $autoload );
		if ( false === $c || true !== $c['enabled'] || $c['context_digest'] !== $e['context_digest'] || $c['before_hash'] !== $e['before_hash'] || $c['after_hash'] !== $e['after_hash'] ) {
			return false;
		}
		$t                         = array(
			'command_id'          => $s['writer']['pending']['completion_id'],
			'operation_id'        => $e['command_id'],
			'site_uuid'           => $site,
			'enrollment_binding'  => $e['enrollment_binding'],
			'expected_generation' => $s['lifecycle']['permission_generation'],
			'closure_generation'  => $s['lifecycle']['permission_generation'],
			'observed_hash'       => $c['after_hash'],
			'issued_at'           => $e['issued_at'],
			'expires_at'          => $e['expires_at'],
		);
		$s['writer']['completion'] = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'complete_disable', $t, $now );
		$s['writer']['pending']    = null;
		return self::candidate( self::advanced( $s, $now, $site ), $c['after'] );
	}
}
