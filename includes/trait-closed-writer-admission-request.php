<?php
/**
 * Fixture-only request guards and issuance, never real authentication.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** All private observations are outside simulated locks and freshly guarded. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Request {
	/**
	 * Short-lived fixture issue action, separate from command and receipt actions.
	 *
	 * @param string $purpose Purpose.
	 * @return string|false
	 */
	public function issue_action( $purpose ) {
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::fields( $purpose ) ? Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::PREFIX . 'issue_v1:' . $this->site . ':' . $purpose : false;
	}

	/**
	 * Issue all private facts from existing fixture state, never posted context.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Small issuance form.
	 * @return array|WP_Error Scalar-string command form, or redacted error.
	 */
	public function issue( $method, $body ) {
		if ( ! $this->enabled || 'POST' !== $method ) {
			return $this->error();
		}
		try {
			$f = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::strings( $body );
			if ( false === $f || ! isset( $f['purpose'], $f['confirm'], $f['nonce'] ) || ! $this->issue_action( $f['purpose'] ) ) {
				return $this->error();
			}
			$keys          = array( 'purpose', 'confirm', 'nonce' );
			$confirmations = array(
				'upgrade_closed' => 'close',
				'begin_disable'  => 'disable',
				'abandon_closed' => 'abandon',
				'receipt_read'   => 'observe',
			);
			if ( $f['confirm'] !== $confirmations[ $f['purpose'] ] ) {
				return $this->error();
			}
			if ( 'abandon_closed' === $f['purpose'] || 'receipt_read' === $f['purpose'] ) {
				$keys[] = 'abandon_closed' === $f['purpose'] ? 'operation_id' : 'observed_command_id';
				$id_key = end( $keys );
				if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $f[ $id_key ] ?? null ) ) {
					return $this->error();
				}
			}
			if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::keys( $f, $keys ) || ! $this->authorize( $f['nonce'], $this->issue_action( $f['purpose'] ) ) ) {
				return $this->error();
			}
			$now = $this->now();
			if ( false === $now || $now > PHP_INT_MAX - 3900 ) {
				return $this->error();
			}
			$row = $this->snapshot();
			if ( false === $row ) {
				return $this->error();
			}
			$s = $row['state'];
			$e = array(
				'contract'   => 'closed_writer_request_v1',
				'purpose'    => $f['purpose'],
				'command_id' => ( $this->ports['uuid'] )(),
				'site_uuid'  => $this->site,
				'issued_at'  => $now,
				'expires_at' => $now + 300,
				'confirm'    => $f['confirm'],
			);
			if ( 'receipt_read' === $e['purpose'] ) {
				$e['observed_command_id'] = $f['observed_command_id'];
			} else {
				$e['enrollment_binding']  = $s['enrollment_binding'];
				$e['expected_generation'] = $s['lifecycle']['permission_generation'];
				if ( 'upgrade_closed' === $e['purpose'] ) {
					if ( 6 !== $s['schema_version'] ) {
						return $this->error();
					}
					$e['origin_digest'] = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $s );
				} elseif ( ! $this->issue_context( $e, $f, $row ) ) {
					return $this->error();
				}
			}
			$action = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e );
			if ( false === $action || ! $this->authorize( $f['nonce'], $this->issue_action( $f['purpose'] ) ) || ! $this->window( $e ) || $now < $s['lifecycle']['changed_at'] ) {
				return $this->error();
			}
			$nonce = ( $this->ports['nonce'] )( $action );
			if ( ! is_string( $nonce ) || '' === $nonce || strlen( $nonce ) > 2048 || 1 !== preg_match( '//u', $nonce ) ) {
				return $this->error();
			}
			$e['nonce'] = $nonce;
			return array_map( 'strval', $e );
		} catch ( Throwable $error ) {
			return $this->error();
		}
	}

	/**
	 * Derive only the fixed disable or pending-abandon context.
	 *
	 * @param array $e Envelope under construction.
	 * @param array $f Exact small form.
	 * @param array $row Existing snapshot.
	 * @return bool
	 */
	private function issue_context( array &$e, array $f, array $row ) {
		$s = $row['state'];
		$c = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::context( $s, $row['target'], $this->site );
		if ( 7 !== $s['schema_version'] || false === $c ) {
			return false;
		}
		$e['context_digest'] = $c['context_digest'];
		if ( 'begin_disable' === $e['purpose'] ) {
			$e['before_hash'] = $c['before_hash'];
			$e['after_hash']  = $c['after_hash'];
			return null === $s['writer']['pending'];
		}
		$p = $s['writer']['pending'];
		if ( null === $p || $f['operation_id'] !== $p['operation_id'] ) {
			return false;
		}
		$e['operation_id']       = $f['operation_id'];
		$e['closure_generation'] = $p['closure_generation'];
		$e['observed_hash']      = $c['before_hash'];
		return true;
	}

	/**
	 * Fixture authentication + capability + session port and exact nonce.
	 *
	 * @param string $nonce Nonce.
	 * @param string $action Action.
	 * @return bool
	 */
	private function authorize( $nonce, $action ) {
		return true === ( $this->ports['authorize'] )() && in_array( ( $this->ports['verify'] )( $nonce, $action ), array( 1, 2 ), true );
	}

	/**
	 * Clock calls never renew or regress an issued window.
	 *
	 * @return int|false
	 */
	private function now() {
		$now = ( $this->ports['clock'] )();
		if ( ! is_int( $now ) || $now < 1 || $now > PHP_INT_MAX - 3600 || $now < $this->last_clock ) {
			return false;
		}
		$this->last_clock = $now;
		return $now;
	}

	/**
	 * Original independent deadline.
	 *
	 * @param array $e Original typed command.
	 * @return bool
	 */
	private function window( array $e ) {
		$now = $this->now();
		return false !== $now && $now >= $e['issued_at'] && $now < $e['expires_at'];
	}

	/**
	 * Fresh guard, invoked independently before reads and writes.
	 *
	 * @param array  $e Original command.
	 * @param string $nonce Exact nonce.
	 * @return bool
	 */
	private function guard( array $e, $nonce ) {
		return $this->authorize( $nonce, Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e ) ) && $this->window( $e );
	}

	/**
	 * Strict memory snapshot; no default installation, migration or SQL adapter.
	 *
	 * @return array|false
	 */
	private function snapshot() {
		$row = ( $this->ports['read'] )();
		if ( ! is_array( $row ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::keys( $row, array( 'state', 'target', 'autoload' ) ) || ! is_array( $row['state'] ) || ! is_string( $row['target'] ) || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::supported( $row['state'], $this->site ) ) {
			return false;
		}
		return $row;
	}
}
