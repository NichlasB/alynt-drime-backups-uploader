<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-scan-effect-manifest.php';

class ScanEffectManifestTest extends TestCase {
	private function frame() {
		$f = array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => 'synthetic', 'rows' => array() ); $i = 0;
		foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $definition ) $f['rows'][$role] = array( 'option_id' => (string) ++$i, 'option_name' => $definition[0], 'autoload' => 'off', 'octets' => '6', 'value' => 'a:0:{}' );
		return $f;
	}
	public function test_fixed_order_and_positive_exact_observation_remain_non_authorizing() {
		$this->assertSame( array( 'safety', 'settings', 'connection', 'ledger', 'wpvivid_snapshot', 'outbox_snapshot', 'queue', 'active', 'uploaded', 'failed' ), array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) );
		$r = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $this->frame(), $this->frame() ); $this->assertTrue( $r['same_candidate'] );
		foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key] ); $this->assertArrayNotHasKey( 'rows', $r );
	}
	public function test_every_dependency_and_tuple_field_matters_not_just_the_queue() {
		foreach ( array_keys( $this->frame()['rows'] ) as $role ) {
			foreach ( array( 'value' => 'a:1:{}', 'option_id' => '99', 'autoload' => 'no', 'option_name' => 'arbitrary', 'octets' => 6 ) as $field => $value ) {
				$a = $this->frame(); $b = $a; $b['rows'][$role][$field] = $value; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $b )['same_candidate'], $role . '/' . $field );
			}
		}
	}
	public function test_wrong_scope_missing_duplicate_reordered_and_oversized_rows_refuse() {
		foreach ( array( 'blog_id' => 2, 'database' => 'other', 'prefix' => 'other_' ) as $key => $value ) { $a = $this->frame(); $b = $a; $b[$key] = $value; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $b )['same_candidate'] ); }
		$a = $this->frame(); $a['rows'] = array_reverse( $a['rows'], true ); $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $a )['same_candidate'] );
		foreach ( array( '0', '01', (string) PHP_INT_MAX ) as $bad ) { $a = $this->frame(); $a['rows']['safety']['option_id'] = $bad; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $a )['same_candidate'] ); }
		$a = $this->frame(); $a['rows']['queue']['option_id'] = '1'; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $a )['same_candidate'] );
		$a = $this->frame(); unset( $a['rows']['failed'] ); $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $a )['same_candidate'] );
		$a = $this->frame(); $a['rows']['ledger']['value'] = str_repeat( 'x', 131073 ); $a['rows']['ledger']['octets'] = '131073'; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $a, $a )['same_candidate'] );
	}
}
