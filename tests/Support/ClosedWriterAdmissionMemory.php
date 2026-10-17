<?php
/** Deliberately synthetic memory/auth/nonce/transaction ports, never WP or SQL. */
final class Alynt_Test_Closed_Writer_Admission_Memory {
	public $row;
	public $now = 1800000000;
	public $authorized = true;
	public $session = 'fixture-session';
	public $capable = true;
	public $single_site = true;
	public $reads = 0;
	public $writes = 0;
	public $auth_calls = 0;
	public $nonce_calls = 0;
	public $uuid_calls = 0;
	public $transactions = 0;
	public $conflicts = 0;
	public $ack = 'normal';
	public $mode = 'commit';
	public $on_read;
	public $on_compare;
	public $on_transaction;
	public $before_commit;
	public $on_auth;
	public $retained;
	public $reused;
	public $reconfirmed;
	public $proposals = array();
	public $inside_auth_delta = null;
	public $identity;
	public $next_uuid;
	public $throw_read = false;
	public function __construct( array $state, $site ) {
		$this->row = array( 'state' => $state, 'target' => json_encode( array( 'site_uuid' => $site, 'auto_scan_enabled' => true, 'unrelated' => array( 'secret' => 'DO-NOT-EXPORT', 'timestamp' => 123 ) ) ), 'autoload' => 'no' );
		$this->identity = new stdClass();
	}
	public function nonce( $action ) { ++$this->nonce_calls; return hash( 'sha256', 'FIXTURE-ONLY:' . $this->session . ':' . $action ); }
	public function ports() {
		return array(
			'authorize' => function () { ++$this->auth_calls; if ( $this->on_auth ) ( $this->on_auth )( $this ); return $this->authorized && $this->capable && $this->single_site && '' !== $this->session; },
			'verify' => function ( $nonce, $action ) { return hash_equals( $this->nonce( $action ), $nonce ) ? 1 : false; },
			'nonce' => function ( $action ) { return $this->nonce( $action ); },
			'clock' => function () { return $this->now; },
			'uuid' => function () { ++$this->uuid_calls; return $this->next_uuid ?? sprintf( '77777777-7777-4777-8777-%012d', $this->uuid_calls ); },
			'read' => function () { ++$this->reads; if ( $this->throw_read ) throw new RuntimeException( 'SECRET-provider' ); if ( $this->on_read ) ( $this->on_read )( $this ); return $this->row; },
			'compare' => function ( $expected, $next ) {
				++$this->writes; $this->proposals[] = $next;
				if ( $this->on_compare ) ( $this->on_compare )( $this );
				if ( $this->conflicts > 0 ) { --$this->conflicts; return false; }
				if ( 'truthy' === $this->ack ) return array( 'committed' => true );
				if ( 'true_without_write' === $this->ack ) return true;
				if ( $this->row !== $expected ) return false;
				$this->row['state'] = $next;
				if ( 'throw' === $this->ack ) throw new RuntimeException( 'SECRET-committed' );
				return 'false' === $this->ack ? false : true;
			},
			'transaction' => function ( $continuation ) {
				++$this->transactions; $this->retained = $continuation; $auth = $this->auth_calls;
				if ( $this->on_transaction ) ( $this->on_transaction )( $this );
				if ( 'none' === $this->mode ) return true;
				if ( 'throw_before' === $this->mode ) throw new RuntimeException( 'SECRET-transaction' );
				$pair = $continuation->enter_fixture( 'wrong_identity' === $this->mode ? new stdClass() : $this->identity, $this->row['state'], $this->row['target'], $this->now );
				$this->reused = $continuation->enter_fixture( $this->identity, $this->row['state'], $this->row['target'], $this->now );
				if ( $this->before_commit ) ( $this->before_commit )( $this );
				if ( false !== $pair && 'abort' !== $this->mode ) {
					$pair = $continuation->confirm_fixture( $this->identity, $this->row['state'], $this->row['target'], $this->now );
					if ( false !== $pair ) $this->row = array( 'state' => $pair['state'], 'target' => $pair['target'], 'autoload' => 'no' );
					$this->reconfirmed = $continuation->confirm_fixture( $this->identity, $this->row['state'], $this->row['target'], $this->now );
				}
				$this->inside_auth_delta = $this->auth_calls - $auth;
				if ( 'throw_after' === $this->mode ) throw new RuntimeException( 'SECRET-post-commit' );
				return true;
			},
		);
	}
}
