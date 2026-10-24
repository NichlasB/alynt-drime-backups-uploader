<?php
/** Isolated upload API fixtures only; no WordPress, network or native session.
 * Fresh processes measure the explicit pressure fixture, not retained mocks from
 * unrelated suite cases. Keep the same 256M ceiling and all real guard assertions.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UploaderMemoryBudgetTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
    private function memoryGuard() {
        $options = $this->base_options();
        $client = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
        $uploader = $this->uploader_with_options( $options, $client );
        $method = new ReflectionMethod( $uploader, 'validate_multipart_chunk_memory' );
        if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
        return array( $uploader, $method );
    }
    public function test_representative_suite_memory_retains_the_original_chunk_and_reserve() {
        list( $uploader, $method ) = $this->memoryGuard();
        $pressure = str_repeat( 'x', 48 * 1048576 ); // Bounded representative test-suite overhead, not an exhaustion test.
        $result = $method->invoke( $uploader, 32 * 1048576 );
        $this->assertTrue( $result, 'The test-only PHP budget must accommodate suite overhead plus the unchanged 32MiB chunk and 32MiB reserve.' );
        $this->assertSame( 48 * 1048576, strlen( $pressure ) );
        $this->assertSame( '256M', ini_get( 'memory_limit' ) );
    }
    public function test_real_guard_rejects_insufficient_headroom_and_restores_the_process_limit() {
        list( $uploader, $method ) = $this->memoryGuard();
        $previous = ini_get( 'memory_limit' );
        try {
            // Keep more than 63MiB free: no actual chunk allocation and no limit below current usage.
            $limited = (string) ( memory_get_usage( true ) + 64 * 1048576 - 1 );
            $this->assertNotFalse( ini_set( 'memory_limit', $limited ) );
            $denied = $method->invoke( $uploader, 32 * 1048576 );
            $this->assertInstanceOf( WP_Error::class, $denied );
            $this->assertSame( 'alynt_drime_chunk_exceeds_memory', $denied->get_error_code() );
            $this->assertTrue( $method->invoke( $uploader, 1048576 ) );
        } finally {
            ini_set( 'memory_limit', $previous );
        }
        $this->assertSame( $previous, ini_get( 'memory_limit' ) );
    }
}
