<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RawObservationFixture.php';

class RawObservationContractTest extends TestCase {
	use Alynt_Test_Raw_Observation_Fixture;
	public function test_coherent_rows_have_a_non_authorizing_positive_control() {
		$o = $this->observation(); $this->assertIsObject( $o ); $this->assertTrue( $o->matches( $this->observation() ) );
		$c = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $this->real_upgraded(), $this->settings, $this->site, 1, 'no' );
		$this->assertTrue( $o->matches_context( $c['context_digest'] ) ); $this->assertFalse( $o->matches_context( str_repeat( '0', 64 ) ) );
		$this->assertSame( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $o->disposition() );
	}
	public function test_each_raw_tuple_component_is_checked_across_observations() {
		$this->assertIsObject( $this->observation() );
		foreach ( array( 0, 1 ) as $index ) foreach ( array( 'option_id' => '3', 'autoload' => 'off', 'value' => 'changed', 'octets' => '1', 'option_name' => 'wrong' ) as $key => $value ) {
			$rows = $this->rows; $rows[$index][$key] = $value; $this->assertFalse( $this->observation( null, null, $rows ), $index . ':' . $key );
		}
	}
	public function test_noncanonical_metadata_and_truncation_refuse_without_coercion() {
		foreach ( array( 0, 1 ) as $index ) foreach ( array( 'option_id' => array( 1, '0', '01', '-1', (string) PHP_INT_MAX, new stdClass() ), 'autoload' => array( false, 'yes', 'NO' ), 'octets' => array( 1, '01', '0' ), 'value' => array( null, false, new stdClass() ) ) as $key => $values ) foreach ( $values as $value ) {
			$rows = $this->rows; $rows[$index][$key] = $value; $this->assertFalse( $this->observation( null, $rows ) );
		}
		$rows = $this->rows; $rows[1]['option_id'] = $rows[0]['option_id']; $this->assertFalse( $this->observation( null, $rows ) );
		$rows = $this->rows; $rows[0]['extra'] = true; $this->assertFalse( $this->observation( null, $rows ) );
	}
	public function test_private_binding_detects_semantically_equal_raw_safety_and_row_recreation() {
		$o = $this->observation();
		$rows = $this->rows; $rows[0] = $this->row( 'safety', ' ' . $rows[0]['value'], '1' );
		$other = $this->observation( null, $rows ); $this->assertIsObject( $other ); $this->assertFalse( $o->matches( $other ) );
		$c = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $this->real_upgraded(), $this->settings, $this->site, 1, 'no' );
		$this->assertTrue( $other->matches_context( $c['context_digest'] ), 'Private binding must not silently change context v1.' );
		$rows = $this->rows; $rows[0]['option_id'] = '3'; $this->assertFalse( $o->matches( $this->observation( null, $rows ) ) );
		$scope = $this->scope; $scope['session_id'] = str_repeat( 'e', 64 ); $this->assertFalse( $o->matches( $this->observation( $scope ) ) );
	}
	public function test_scope_and_real_schema_identity_are_not_caller_enablement() {
		foreach ( array( 'blog_id' => 0, 'prefix' => 'wp_;', 'database' => '', 'site_uuid' => $this->real_id( 99 ), 'session_id' => true ) as $key => $value ) {
			$scope = $this->scope; $scope[$key] = $value; $this->assertFalse( $this->observation( $scope ) );
		}
		$state = $this->real_upgraded(); $state['schema_version'] = 7;
		$rows = $this->rows; $rows[0] = $this->row( 'safety', json_encode( $state ), '1' ); $this->assertFalse( $this->observation( null, $rows ) );
		$rows[0] = $this->row( 'safety', str_repeat( ' ', 131073 ), '1' ); $this->assertFalse( $this->observation( null, $rows ) );
		$rows[1] = $this->row( 'settings', str_repeat( 'x', 1048577 ), '2' ); $this->assertFalse( $this->observation( null, $rows ) );
	}
	public function test_supported_metadata_and_schema_six_have_real_accept_controls() {
		foreach ( array( 'no', 'off', 'auto-off' ) as $autoload ) {
			$rows = $this->rows; $rows[0] = $this->row( 'safety', json_encode( $this->six ), '1' );
			$rows[0]['autoload'] = $autoload; $rows[1]['autoload'] = $autoload;
			$this->assertIsObject( $this->observation( null, $rows, array_map( function ( $r ) { return array_reverse( $r, true ); }, $rows ) ) );
		}
	}
}
