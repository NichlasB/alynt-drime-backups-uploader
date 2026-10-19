<?php
require_once __DIR__ . '/ClosedRegistrationFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-closed-registration-contract.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-request-contract.php';
/** Synthetic data only; no core/authentication/UUID/clock or storage shims. */
trait Alynt_Test_Registration_Observation_Fixture {
    use Alynt_Test_Closed_Registration_Fixture;
    protected function setUp(): void {
        if ( ! defined( 'DB_NAME' ) ) define( 'DB_NAME', 'synthetic' );
        foreach ( array( 'origin', 'request', 'lifetime' ) as $part ) {
            $path = dirname( __DIR__, 2 ) . '/includes/class-registration-observation-' . $part . '.php';
            if ( file_exists( $path ) ) require_once $path;
        }
        $this->assertTrue( class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Observation_Origin', false ), 'Approved pure observation origin must exist.' );
    }
    private function observationData() {
        $p = $this->rproposal();
        return array( 'domain' => 'registration_observation_origin_v1', 'installation' => array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => DB_NAME ), 'scope' => $this->rscope(), 'manifest_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'mutation' => $p['command'], 'actor_id' => $p['actor_id'], 'operation_id' => $p['operation_id'], 'context_digest' => $p['command']['context_digest'] );
    }
    private function observationOrigin( $data = null ) { return Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $data ?? $this->observationData() ); }
    private function observationIntent() { return array( 'purpose' => 'observe_registration', 'confirm' => 'observe', 'observed_command_id' => $this->rid( 3 ), 'nonce' => 'synthetic-issuance' ); }
    private function observationForm() { return array_map( 'strval', $this->observe_command( $this->rid( 3 ) ) ) + array( 'nonce' => 'synthetic-receipt' ); }
    private function observationResult( $raw = null, $data = null, $form = null, $now = 510 ) {
        $data = $data ?? $this->observationData();
        return $this->observationOrigin( $data )->observe( $raw ?? $this->registered(), $this->observationIntent(), $form ?? $this->observationForm(), $data['installation'], $data['scope'], $now );
    }
    private function observationClock( $ns = 200, $utc = 510, $start = 100 ) { return array( 'started_ns' => $start, 'now_ns' => $ns, 'utc' => $utc ); }
    private function observationInvoke( $o, $method, ...$args ) { $r = new ReflectionMethod( $o, $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( $o, $args ); }
    private function observationProperty( $o, $key ) { $r = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->getValue( $o ); }
}
