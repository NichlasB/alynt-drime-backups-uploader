<?php
/**
 * Dormant fixed native lifecycle adapter; its entry remains closed.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-lifecycle-integration.php';
require_once __DIR__ . '/class-registration-native-admission.php';
require_once __DIR__ . '/class-registration-observation-construction.php';
require_once __DIR__ . '/trait-registration-native-lifecycle-methods.php';

/** No public operation, provider, activation flag or production registration. */
final class Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle {
	use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods;

	/** Existing sealed vocabulary, not another event domain.
	 *
	 * @var array
	 */
	const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Construct only an unavailable private owner.
	 *
	 * @return self|false No handle or continuation import.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Fixed closed entry, not a setting or injected enable switch.
	 *
	 * @return false Actual authentication/storage/native execution disabled.
	 */
	private function native_entry_available() {
		return false;
	}
}
