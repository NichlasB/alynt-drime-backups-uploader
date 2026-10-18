<?php
use PHPUnit\Framework\TestCase;
use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data as Data;

/** Bounded, in-memory resource boundaries; no timing or machine-exhaustion claims. */
class RealClosedWriterResourceTest extends TestCase {
	protected function setUp(): void { require_once dirname( __DIR__ ) . '/includes/class-real-closed-writer-data.php'; }
	public function test_combined_node_budget_has_an_accept_control_and_counts_keys() {
		$this->assertTrue( Data::plain( array_fill( 0, 32767, null ) ) ); // 65535 nodes: root plus keys and values.
		$this->assertFalse( Data::plain( array_fill( 0, 32768, null ) ) );
	}
	public function test_budget_is_shared_across_siblings_not_reset_for_each_subtree() {
		$branch = array_fill( 0, 16382, null ); // 65533 total, including outer keys and both branch roots.
		$this->assertTrue( Data::plain( array( $branch, $branch ) ) );
		$branch[] = null;
		$this->assertFalse( Data::plain( array( $branch, $branch ) ) );
	}
	public function test_depth_limit_and_recursive_references_stop_without_callbacks() {
		$v = null; for ( $i = 0; $i < 15; ++$i ) $v = array( $v );
		$this->assertTrue( Data::plain( $v ) ); $this->assertFalse( Data::plain( array( $v ) ) );
		$cycle = array(); $cycle['self'] =& $cycle;
		$this->assertFalse( Data::plain( $cycle ) ); $this->assertFalse( Data::hash( $cycle ) );
	}
	public function test_string_budget_counts_keys_and_values_in_one_allowance() {
		$this->assertTrue( Data::plain( array( 'key' => str_repeat( 'x', 131069 ) ) ) );
		$this->assertFalse( Data::plain( array( 'key' => str_repeat( 'x', 131070 ) ) ) );
		$this->assertFalse( Data::plain( array( str_repeat( 'k', 65537 ) => str_repeat( 'v', 65536 ) ) ) );
	}
}
