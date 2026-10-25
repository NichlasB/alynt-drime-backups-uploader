<?php
/** Synthetic v2 catalog only; no WordPress, storage, source attestation or native caller. */
require_once __DIR__ . '/fixed-projection-catalog.php';

final class Alynt_Sidebar_Projection_Catalog {
    // SHA256 of alynt-fixed-projection.wp7.1.2.theme.v2.sidebar-absent, not source bytes.
    const PROFILE = 'a066782b53b105be3386bc2f186b90a59c262cfe47b9bccdd07a180cf62828e0';

    public static function build( $seed, $marker, $wall, $start, $tick, $now ) {
        if ( 6 !== func_num_args() ) return false;
        $catalog = Alynt_Fixed_Projection_Catalog::build( $seed, $marker, $wall, $start, $tick, $now );
        if ( false === $catalog ) return false;
        $catalog['profile'] = self::PROFILE;
        $catalog['absent'][] = 'sidebars_widgets';
        return $catalog;
    }

    public static function matches( $candidate, $seed, $marker, $wall, $start, $tick, $now ) {
        if ( 7 !== func_num_args() || ! is_array( $candidate ) ||
            array( 'profile', 'present', 'absent', 'hidden' ) !== array_keys( $candidate ) ||
            self::PROFILE !== $candidate['profile'] || ! is_array( $candidate['absent'] ) ||
            array_keys( $candidate['absent'] ) !== range( 0, 7 ) || 'sidebars_widgets' !== $candidate['absent'][7] ) return false;
        // Reuse every v1 primitive/value/clock guard, without widening the v1 domain.
        $candidate['profile'] = Alynt_Fixed_Projection_Catalog::PROFILE;
        array_pop( $candidate['absent'] );
        return Alynt_Fixed_Projection_Catalog::matches( $candidate, $seed, $marker, $wall, $start, $tick, $now );
    }
}
