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
				$combined['candidates'] = array_merge( $combined['candidates'], $result['candidates'] );
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
}
