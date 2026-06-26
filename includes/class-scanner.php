<?php
/**
 * Backup package scanner.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans configured backup producers and returns normalized packages.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Scanner {
	const SNAPSHOT_OPTION = Alynt_Drime_Backups_Uploader_WPvivid_Producer::SNAPSHOT_OPTION;

	/**
	 * Producers.
	 *
	 * @var array<int,Alynt_Drime_Backups_Uploader_Producer_Interface>
	 */
	private $producers;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings                      $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_WPvivid_Detector              $detector Detector.
	 * @param Alynt_Drime_Backups_Uploader_Logger|null                   $logger Logger.
	 * @param array<int,Alynt_Drime_Backups_Uploader_Producer_Interface> $producers Producers.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		Alynt_Drime_Backups_Uploader_Settings $settings,
		Alynt_Drime_Backups_Uploader_WPvivid_Detector $detector,
		?Alynt_Drime_Backups_Uploader_Logger $logger = null,
		array $producers = array()
	) {
		if ( empty( $producers ) ) {
			$producers = array(
				new Alynt_Drime_Backups_Uploader_WPvivid_Producer( $settings, $detector, $logger ),
				new Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer( $settings, $logger ),
			);
		}

		$this->producers = $this->filter_producers( $producers );
	}

	/**
	 * Scans every configured producer.
	 *
	 * @return array{directory:string,candidates:array<int,array<string,mixed>>,errors:array<int,string>,producers:array<string,array<string,mixed>>}
	 */
	public function scan() {
		$combined = array(
			'directory'  => '',
			'candidates' => array(),
			'errors'     => array(),
			'producers'  => array(),
		);

		foreach ( $this->producers as $producer ) {
			$result = $producer->scan();
			$key    = $producer->key();

			if ( '' === $combined['directory'] && ! empty( $result['directory'] ) ) {
				$combined['directory'] = (string) $result['directory'];
			}

			$combined['producers'][ $key ] = $result;

			if ( ! empty( $result['candidates'] ) && is_array( $result['candidates'] ) ) {
				foreach ( $result['candidates'] as $candidate ) {
					$normalized = is_array( $candidate ) ? $this->normalize_candidate( $candidate, $producer ) : array();
					if ( empty( $normalized ) ) {
						$combined['errors'][] = sprintf(
							/* translators: %s: backup producer key. */
							__( 'The %s backup producer returned an invalid package record.', 'alynt-drime-backups-uploader' ),
							$key
						);
						continue;
					}

					$combined['candidates'][] = $normalized;
				}
			}

			if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
				$combined['errors'] = array_merge( $combined['errors'], $result['errors'] );
			}
		}

		return $combined;
	}

	/**
	 * Builds a stable local signature.
	 *
	 * @param string $file File path.
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function signature( $file ) {
		return hash( 'sha256', wp_normalize_path( $file ) );
	}

	/**
	 * Returns valid producers only.
	 *
	 * @param array<int,mixed> $producers Producers.
	 * @return array<int,Alynt_Drime_Backups_Uploader_Producer_Interface>
	 */
	private function filter_producers( array $producers ) {
		$valid = array();

		foreach ( $producers as $producer ) {
			if ( $producer instanceof Alynt_Drime_Backups_Uploader_Producer_Interface ) {
				$valid[] = $producer;
			}
		}

		return $valid;
	}

	/**
	 * Validates and fills the normalized producer candidate shape.
	 *
	 * @param array<string,mixed>                             $candidate Candidate.
	 * @param Alynt_Drime_Backups_Uploader_Producer_Interface $producer Producer.
	 * @return array<string,mixed>
	 */
	private function normalize_candidate( array $candidate, Alynt_Drime_Backups_Uploader_Producer_Interface $producer ) {
		$signature = $this->candidate_string( $candidate, 'signature' );
		$path      = $this->candidate_string( $candidate, 'path' );
		$name      = $this->candidate_string( $candidate, 'name' );

		if ( '' === $signature || '' === $path || '' === $name ) {
			return array();
		}

		$candidate['signature']          = $signature;
		$candidate['path']               = $path;
		$candidate['name']               = $name;
		$candidate['size']               = $this->candidate_int( $candidate, 'size' );
		$candidate['mtime']              = $this->candidate_int( $candidate, 'mtime' );
		$candidate['producer_key']       = $producer->key();
		$candidate['producer_label']     = $producer->label();
		$candidate['package_id']         = $this->candidate_string( $candidate, 'package_id', $signature );
		$candidate['filename']           = $this->candidate_string( $candidate, 'filename', $name );
		$candidate['modified_time']      = $this->candidate_int( $candidate, 'modified_time', $candidate['mtime'] );
		$candidate['backup_set_id']      = $this->candidate_string( $candidate, 'backup_set_id', $candidate['package_id'] );
		$candidate['backup_set_part']    = $this->candidate_string( $candidate, 'backup_set_part' );
		$candidate['backup_set_total']   = max( 1, $this->candidate_int( $candidate, 'backup_set_total', 1 ) );
		$candidate['manifest_path']      = $this->candidate_string( $candidate, 'manifest_path' );
		$candidate['checksum_path']      = $this->candidate_string( $candidate, 'checksum_path' );
		$candidate['checksum_algorithm'] = $this->candidate_string( $candidate, 'checksum_algorithm' );
		$candidate['checksum_value']     = $this->candidate_string( $candidate, 'checksum_value' );
		$candidate['site_url']           = $this->candidate_string( $candidate, 'site_url' );
		$candidate['created_at']         = $this->candidate_int( $candidate, 'created_at' );
		$candidate['metadata']           = isset( $candidate['metadata'] ) && is_array( $candidate['metadata'] ) ? $candidate['metadata'] : array();

		return $candidate;
	}

	/**
	 * Returns a scalar candidate value as a string.
	 *
	 * @param array<string,mixed> $candidate Candidate.
	 * @param string              $key Key.
	 * @param string              $fallback Fallback.
	 * @return string
	 */
	private function candidate_string( array $candidate, $key, $fallback = '' ) {
		if ( ! isset( $candidate[ $key ] ) || ! is_scalar( $candidate[ $key ] ) ) {
			return $fallback;
		}

		$value = (string) $candidate[ $key ];

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Returns a candidate value as a non-negative integer.
	 *
	 * @param array<string,mixed> $candidate Candidate.
	 * @param string              $key Key.
	 * @param int                 $fallback Fallback.
	 * @return int
	 */
	private function candidate_int( array $candidate, $key, $fallback = 0 ) {
		if ( ! isset( $candidate[ $key ] ) ) {
			return absint( $fallback );
		}

		return absint( $candidate[ $key ] );
	}
}
