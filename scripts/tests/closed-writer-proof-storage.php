<?php
/** Native owned-fixture connection: no wpdb reconnect, option hooks or cache publication. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
final class Alynt_Closed_Writer_Proof_DB {
	private $db; public $table; public $control; public $queries = 0;
	public function __construct( $table ) {
		alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_writer_probe_[a-f0-9]{12}$/D', $table ), 'Invalid writer fixture identifier.' );
		$this->table = $table; $this->control = $table . '_control';
		list( $host, $port ) = explode( ':', DB_HOST );
		$this->db = mysqli_init();
		alynt_lifecycle_check( $this->db && $this->db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ) && $this->db->options( MYSQLI_OPT_READ_TIMEOUT, 5 ) && @$this->db->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, (int) $port ), 'Native fixture connection failed.' );
		alynt_lifecycle_check( $this->db->set_charset( 'utf8mb4' ), 'Fixture character set failed.' );
		$this->exec( 'SET SESSION innodb_lock_wait_timeout=2' );
		$this->exec( 'SET SESSION lock_wait_timeout=3' );
	}
	public function query( $sql, array $args = array() ) {
		$names = preg_quote( $this->table, '/' ) . '|' . preg_quote( $this->control, '/' );
		$read = 1 === preg_match( '/^SELECT\b/D', $sql );
		$tx = in_array( $sql, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK', 'SET SESSION innodb_lock_wait_timeout=2', 'SET SESSION lock_wait_timeout=3' ), true );
		$owned = 1 === preg_match( '/^(?:INSERT INTO|UPDATE|DELETE FROM|CREATE TABLE|DROP TABLE) (?:' . $names . ')(?= |\(|$)/D', $sql );
		alynt_lifecycle_check( $read || $tx || $owned, 'SQL outside owned writer fixture denied.' );
		$this->queries++;
		$stmt = $this->db->prepare( $sql ); alynt_lifecycle_check( false !== $stmt, 'Fixture SQL preparation failed.' );
		try {
			if ( $args ) alynt_lifecycle_check( $stmt->bind_param( str_repeat( 's', count( $args ) ), ...$args ), 'Fixture SQL parameter binding failed.' );
			alynt_lifecycle_check( $stmt->execute(), 'Fixture SQL execution failed.' );
			$result = $stmt->get_result(); return false === $result ? $stmt->affected_rows : $result->fetch_all( MYSQLI_ASSOC );
		} finally { $stmt->close(); }
	}
	public function exec( $sql, array $args = array() ) { $r = $this->query( $sql, $args ); alynt_lifecycle_check( is_int( $r ), 'Expected fixture write result.' ); return $r; }
	public function one( $sql, array $args = array() ) { $r = $this->query( $sql, $args ); alynt_lifecycle_check( is_array( $r ) && count( $r ) <= 1, 'Unexpected fixture row count.' ); return $r ? $r[0] : null; }
	public function value( $name, $lock = false ) { $r = $this->one( 'SELECT option_value FROM ' . $this->table . ' WHERE option_name=?' . ( $lock ? ' FOR UPDATE' : '' ), array( $name ) ); return null === $r ? null : $r['option_value']; }
	public function replace( $name, $raw ) { return $this->exec( 'UPDATE ' . $this->table . ' SET option_value=? WHERE option_name=?', array( $raw, $name ) ); }
	public function marker( $name ) { $this->exec( 'INSERT INTO ' . $this->control . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( $name, 'ready', 'no' ) ); }
	public function wait( $name ) {
		$until = microtime( true ) + 15;
		do { if ( null !== $this->one( 'SELECT option_value FROM ' . $this->control . ' WHERE option_name=?', array( $name ) ) ) return; usleep( 20000 ); } while ( microtime( true ) < $until );
		throw new RuntimeException( 'Writer process barrier timeout.' );
	}
	public function owned( $token ) {
		alynt_lifecycle_check( 1 === preg_match( '/^[a-f0-9]{24}$/D', $token ) && $token === $this->value( 'probe_owner' ), 'Writer fixture ownership missing.' );
		foreach ( array( $this->table, $this->control ) as $table ) {
			$r = $this->one( 'SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', array( $table ) );
			alynt_lifecycle_check( is_array( $r ) && 'InnoDB' === $r['ENGINE'], 'Transactional fixture engine required.' );
		}
	}
	public function close() { $this->db->close(); }
}
/** Fixed state-only native CAS on the same connection as the target transaction. */
final class Alynt_Closed_Writer_Proof_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $db;
	public function __construct( $db ) { $this->db = $db; }
	public function read( $name ) {
		alynt_lifecycle_check( self::STATE_OPTION === $name, 'Fixture CAS name denied.' );
		return $this->db->one( 'SELECT option_value,autoload FROM ' . $this->db->table . ' WHERE option_name=?', array( $name ) );
	}
	public function create( $value ) { alynt_lifecycle_check( is_string( $value ) && strlen( $value ) <= 131072, 'Fixture creation bytes denied.' ); return 1 === $this->db->exec( 'INSERT INTO ' . $this->db->table . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( self::STATE_OPTION, $value, 'no' ) ); }
	public function swap( $name, $expected, $replacement ) {
		alynt_lifecycle_check( self::STATE_OPTION === $name && is_string( $expected ) && is_string( $replacement ) && $expected !== $replacement && strlen( $replacement ) <= 131072, 'Fixture CAS scope/bytes denied.' );
		return 1 === $this->db->exec( 'UPDATE ' . $this->db->table . ' SET option_value=? WHERE option_name=? AND BINARY option_value=BINARY ?', array( $replacement, $name, $expected ) );
	}
}
