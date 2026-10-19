<?php
require_once __DIR__ . '/RealClosedWriterFixture.php';
require_once __DIR__ . '/ClosedRegistrationFixture.php';
/** Pure in-memory graphs; no core shims, auth, UUID generation or storage. */
trait Alynt_Test_Registration_Admission_Fixture {
    use Alynt_Test_Real_Closed_Writer_Fixture { setUp as protected setupAdmissionSafety; }
    use Alynt_Test_Closed_Registration_Fixture;
    protected function setUp(): void {
        $this->setupAdmissionSafety();
        if ( ! defined( 'DB_NAME' ) ) define( 'DB_NAME', 'synthetic' );
        foreach ( array( 'registration-admission-plan', 'registration-admission-handoff' ) as $part ) {
            $path = dirname( __DIR__, 2 ) . '/includes/class-' . $part . '.php';
            if ( file_exists( $path ) ) require_once $path;
        }
        $this->assertTrue( class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Admission_Plan', false ), 'Approved candidate plan must exist.' );
    }
    private function admissionFixture() {
        $s = $this->real_upgraded(); $scope = array( 'site_uuid' => $this->site, 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => $s['lifecycle']['permission_generation'], 'ledger_id' => $this->rid( 2 ), 'origin_created_at' => 50 );
        $root = $this->rroot(); $root['site_uuid'] = $scope['site_uuid']; $root['enrollment_binding'] = $scope['enrollment_binding']; $root['authority_generation'] = $scope['authority_generation'];
        $rows = array(); $n = 0;
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) $rows[$role] = array( 'option_id' => (string) ++$n, 'option_name' => $def[0], 'autoload' => 'no', 'octets' => '6', 'value' => 'a:0:{}' );
        $values = array( 'safety' => json_encode( $s ), 'settings' => serialize( array( 'site_uuid' => $this->site, 'auto_scan_enabled' => false ) ), 'ledger' => json_encode( $root ) );
        foreach ( $values as $role => $raw ) { $rows[$role]['value'] = $raw; $rows[$role]['octets'] = (string) strlen( $raw ); }
        $frame = array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => DB_NAME, 'rows' => $rows );
        $p = $this->rproposal(); $p['command']['site_uuid'] = $this->site; $p['command']['enrollment_binding'] = $scope['enrollment_binding']; $p['command']['expected_generation'] = $scope['authority_generation'];
        $p['dependency_digest'] = hash( 'sha256', 'registration_full_frame_v2:' . json_encode( $frame ) );
        $p['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $p );
        return array( $frame, $p, array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => DB_NAME, 'actor_id' => 7 ), $scope );
    }
    private function prepareAdmission( $f, $p, $c, $now = 110 ) { return Alynt_Drime_Backups_Uploader_Registration_Admission_Plan::prepare( $f, $p, $c, $now ); }
    private function admissionProperty( $owner, $key ) { $r = new ReflectionProperty( $owner, $key ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->getValue( $owner ); }
    private function admissionInvoke( $owner, $method, ...$args ) { $r = new ReflectionMethod( $owner, $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( $owner, $args ); }
    private function admissionClock( $ns = 200, $utc = 110, $start = 100 ) { return array( 'started_ns' => $start, 'now_ns' => $ns, 'utc' => $utc ); }
}
