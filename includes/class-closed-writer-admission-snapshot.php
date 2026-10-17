<?php
/**
 * Bounded immutable read adapter for the unchanged strict schema-6 validator.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Contains only already-loaded fixture bytes; cannot access a database. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Snapshot extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	/**
	 * Frozen encoded row.
	 *
	 * @var string
	 */
	private $raw;

	/**
	 * Do not construct a database adapter or invoke a provider.
	 *
	 * @param string $raw Already bounded bytes.
	 */
	public function __construct( $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Only this snapshot can be observed.
	 *
	 * @param string $name Fixed state identity.
	 * @return array|null
	 */
	public function read( $name ) {
		return self::STATE_OPTION === $name ? array(
			'option_value' => $this->raw,
			'autoload'     => 'no',
		) : null;
	}

	/**
	 * This validator cannot bootstrap storage.
	 *
	 * @param mixed $value Ignored.
	 * @return false
	 */
	public function create( $value ) {
		unset( $value );
		return false;
	}

	/**
	 * This validator cannot write storage.
	 *
	 * @param mixed $name Ignored.
	 * @param mixed $expected Ignored.
	 * @param mixed $replacement Ignored.
	 * @return false
	 */
	public function swap( $name, $expected, $replacement ) {
		unset( $name, $expected, $replacement );
		return false;
	}
}
