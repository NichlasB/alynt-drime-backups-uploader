<?php
/**
 * One-shot private DATA handoff; never a genuine construction continuation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-candidate.php';
require_once __DIR__ . '/trait-registration-observation-handoff-data.php';

/** Test-loaded only. No public operation, callbacks, actual clock or native IO. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Fixed zero-argument factory, never accepts a provider or native owner.
	 *
	 * @return self|false Dormant DATA model only.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}
}
