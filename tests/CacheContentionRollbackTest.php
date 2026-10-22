<?php
use PHPUnit\Framework\TestCase;

/** Executes the actual fixed helper branch with query doubles, never mysqli/WordPress. */
final class CacheContentionRollbackTest extends TestCase {
    private function run_branch( $ack, $row, &$calls ) {
        $source = str_replace( "\r\n", "\n", file_get_contents( dirname( __DIR__ ) . '/scripts/tests/cache-contention-native-helper.php' ) );
        $start = strpos( $source, "        \$stage = 'rollback';\n" );
        $end = strpos( $source, '        $owned_transaction = false;', $start );
        $this->assertNotFalse( $start ); $this->assertNotFalse( $end );
        $branch = substr( $source, $start, $end - $start );
        $db = new class( $ack, $row ) {
            public $calls = array(); private $ack; private $row;
            public function __construct( $ack, $row ) { $this->ack = $ack; $this->row = $row; }
            public function query( $sql ) {
                $this->calls[] = $sql;
                if ( 'ROLLBACK AND NO CHAIN NO RELEASE' === $sql ) return $this->ack;
                // Version-pinned read-only discovery: MariaDB casts this boolean to ON.
                if ( 'SELECT CAST(@@autocommit AS CHAR),CAST(@@in_transaction AS CHAR)' === $sql ) $row = array( 'ON','0' );
                elseif ( 'SELECT CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR)' === $sql ) $row = $this->row;
                else throw new LogicException( 'unexpected_sql' );
                return new class( $row ) {
                    private $row;
                    public function __construct( $row ) { $this->row = $row; }
                    public function fetch_row() { return $this->row; }
                    public function free() {}
                };
            }
        };
        try {
            // Only this trusted repository slice is evaluated; no caller or database code.
            $stage = null; eval( $branch ); return $stage;
        } finally { $calls = $db->calls; }
    }

    public function test_numeric_projection_preserves_strict_ack_and_closed_state() {
        $calls = array();
        $this->assertSame( 'rollback_state', $this->run_branch( true, array( '1','0' ), $calls ) );
        $this->assertSame( array( 'ROLLBACK AND NO CHAIN NO RELEASE', 'SELECT CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR)' ), $calls );
    }

    /** @dataProvider bad_acknowledgements */
    public function test_non_true_ack_refuses_before_session_read( $ack ) {
        $calls = array();
        try { $this->run_branch( $ack, array( '1','0' ), $calls ); $this->fail( 'Acknowledgement guard bypassed' ); }
        catch ( RuntimeException $error ) { $this->assertSame( 'rollback_ack', $error->getMessage() ); }
        $this->assertSame( array( 'ROLLBACK AND NO CHAIN NO RELEASE' ), $calls );
    }
    public static function bad_acknowledgements() { return array( array( false ),array( 0 ),array( 1 ),array( '1' ),array( null ) ); }

    /** @dataProvider bad_states */
    public function test_active_malformed_or_noncanonical_state_stays_refused( $row ) {
        $calls = array();
        try { $this->run_branch( true, $row, $calls ); $this->fail( 'Closed-state guard bypassed' ); }
        catch ( RuntimeException $error ) { $this->assertSame( 'rollback_state', $error->getMessage() ); }
        $this->assertCount( 2, $calls );
    }
    public static function bad_states() {
        return array( array( null ),array( array() ),array( array( 'ON','0' ) ),array( array( '1','1' ) ),array( array( '0','0' ) ),array( array( 1,0 ) ),array( array( '1','0','extra' ) ) );
    }
}
