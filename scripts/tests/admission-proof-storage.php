<?php
/** Native SQL limited to this parent's exact auth fixture options/control tables. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
final class Alynt_Admission_Proof_DB {
	private $db; public $table; public $control; public $queries = 0;
	public function __construct( $base ) {
		alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}$/D', $base ), 'Invalid admission fixture identifier.' );
		$this->table = $base . '_options'; $this->control = $base . '_control';
		list( $host, $port ) = explode( ':', DB_HOST ); $this->db = mysqli_init();
		alynt_lifecycle_check( $this->db && $this->db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ) && $this->db->options( MYSQLI_OPT_READ_TIMEOUT, 5 ) && @$this->db->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, (int) $port ), 'Native admission connection failed.' );
		alynt_lifecycle_check( $this->db->set_charset( 'utf8mb4' ), 'Admission charset failed.' );
		$this->exec( 'SET SESSION innodb_lock_wait_timeout=2' ); $this->exec( 'SET SESSION lock_wait_timeout=3' );
	}
	public function query( $sql, array $args = array() ) {
		$names = preg_quote( $this->table, '/' ) . '|' . preg_quote( $this->control, '/' );
		$allowed = 1 === preg_match( '/^SELECT\b/D', $sql ) || in_array( $sql, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK', 'SET SESSION innodb_lock_wait_timeout=2', 'SET SESSION lock_wait_timeout=3' ), true ) || 1 === preg_match( '/^(?:INSERT INTO|UPDATE|DELETE FROM) (?:' . $names . ')(?= |\(|$)/D', $sql );
		alynt_lifecycle_check( $allowed, 'SQL outside owned admission fixtures denied.' ); $this->queries++;
		$stmt = $this->db->prepare( $sql ); alynt_lifecycle_check( false !== $stmt, 'Admission SQL preparation failed.' );
		try {
			if ( $args ) alynt_lifecycle_check( $stmt->bind_param( str_repeat( 's', count( $args ) ), ...$args ), 'Admission SQL binding failed.' );
			alynt_lifecycle_check( $stmt->execute(), 'Admission SQL execution failed.' ); $result = $stmt->get_result();
			return false === $result ? $stmt->affected_rows : $result->fetch_all( MYSQLI_ASSOC );
		} finally { $stmt->close(); }
	}
	public function exec( $sql, array $args = array() ) { $r = $this->query( $sql, $args ); alynt_lifecycle_check( is_int( $r ), 'Expected admission write result.' ); return $r; }
	public function one( $sql, array $args = array() ) { $r = $this->query( $sql, $args ); alynt_lifecycle_check( is_array( $r ) && count( $r ) <= 1, 'Unexpected admission row count.' ); return $r ? $r[0] : null; }
	public function value( $name, $lock = false ) { $r = $this->one( 'SELECT option_value FROM ' . $this->table . ' WHERE option_name=?' . ( $lock ? ' FOR UPDATE' : '' ), array( $name ) ); return null === $r ? null : $r['option_value']; }
	public function replace( $name, $raw ) { return $this->exec( 'UPDATE ' . $this->table . ' SET option_value=? WHERE option_name=?', array( $raw, $name ) ); }
	public function marker( $name ) { $this->exec( 'INSERT INTO ' . $this->control . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( $name, 'ready', 'no' ) ); }
	public function wait( $name ) {
		$until = microtime( true ) + 15;
		do { if ( null !== $this->one( 'SELECT option_value FROM ' . $this->control . ' WHERE option_name=?', array( $name ) ) ) return; usleep( 20000 ); } while ( microtime( true ) < $until );
		throw new RuntimeException( 'Admission process barrier timeout.' );
	}
	public function owned( $token ) {
		alynt_lifecycle_check( 1 === preg_match( '/^[a-f0-9]{24}$/D', $token ) && $token === $this->value( 'probe_owner' ), 'Admission ownership missing.' );
		$base = substr( $this->table, 0, -8 );
		foreach ( array( 'options', 'users', 'usermeta', 'control' ) as $suffix ) {
			$r = $this->one( 'SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', array( $base . '_' . $suffix ) );
			alynt_lifecycle_check( is_array( $r ) && 'InnoDB' === $r['ENGINE'], 'Admission requires owned InnoDB tables.' );
		}
	}
	public function close() { $this->db->close(); }
}
