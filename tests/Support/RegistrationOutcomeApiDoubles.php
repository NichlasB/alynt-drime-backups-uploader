<?php
/** Loaded by php -n only. These classes cannot open any socket or database. */
function alynt_outcome_api_install_doubles() {
if ( extension_loaded( 'mysqli' ) || class_exists( 'mysqli', false ) ) throw new LogicException( 'Native extension must be absent.' );
class mysqli {
    public $calls = array(); public $returns = array(); public $throws = array(); public $ro = '1'; public $statement; public $inspect;
    public function point( $name ) {
        $this->calls[] = $name; if ( $this->inspect ) ($this->inspect)( $name );
        if ( in_array( $name, $this->throws, true ) ) throw new RuntimeException( 'Synthetic API interruption.' );
    }
    public function query( $sql ) {
        $names = array( 'START TRANSACTION READ WRITE' => 'begin', 'COMMIT AND NO CHAIN NO RELEASE' => 'commit', 'ROLLBACK AND NO CHAIN NO RELEASE' => 'rollback' );
        if ( isset( $names[$sql] ) ) { $key = $names[$sql]; $this->point( $key ); return $this->returns[$key] ?? true; }
        if ( ! in_array( $sql, array( 'SET SESSION TRANSACTION READ WRITE', 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED', 'SET SESSION innodb_lock_wait_timeout=2', 'SET SESSION lock_wait_timeout=3' ), true ) ) throw new LogicException( 'Unexpected API SQL.' );
        $this->ro = '0'; $this->point( 'configure' ); return true;
    }
    public function prepare( $sql ) {
        if ( 'UPDATE `wp_options` SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1' !== $sql ) throw new LogicException( 'Unexpected API statement.' );
        $this->point( 'prepare' ); if ( false === ($this->returns['prepare'] ?? true) ) return false;
        return $this->statement = new Alynt_Outcome_Statement_Double( $this );
    }
    public function close() { $this->point( 'close' ); return $this->returns['close'] ?? true; }
}
class Alynt_Outcome_Statement_Double {
    private $db; public $affected_rows = 1;
    public function __construct( $db ) { $this->db = $db; $this->affected_rows = $db->returns['rows'] ?? 1; }
    public function bind_param( $types, &...$values ) {
        if ( 'sssss' !== $types || 5 !== count( $values ) ) throw new LogicException( 'Bad binding.' );
        $this->db->point( 'bind' ); return $this->db->returns['bind'] ?? true;
    }
    public function execute() { $this->db->point( 'execute' ); return $this->db->returns['execute'] ?? true; }
    public function close() { $this->db->point( 'statement_close' ); }
}
}
