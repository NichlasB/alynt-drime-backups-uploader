<?php
/**
 * Uploader multipart session completion helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Completes multipart upload sessions.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Multipart_Session {
	/**
	 * Completes a multipart session.
	 *
	 * @param string                                       $path Path.
	 * @param string                                       $remote_name Remote name.
	 * @param int                                          $size Size.
	 * @param string                                       $extension Extension.
	 * @param array{key:string,upload_id:string,total:int} $session Multipart session.
	 * @param array<int,array<string,mixed>>               $parts Completed parts.
	 * @param int|null                                     $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null                     $settings Effective upload settings.
	 * @return array<string,mixed>|WP_Error
	 */
	private function complete_multipart_session( $path, $remote_name, $size, $extension, array $session, array $parts, $parent_id = null, ?array $settings = null ) {
		$completed = $this->client->complete_multipart_upload( $session['key'], $session['upload_id'], array_values( $parts ) );
		if ( is_wp_error( $completed ) ) {
			return $completed;
		}

		$this->logger->event(
			'upload',
			'info',
			'multipart_completed',
			'Multipart upload completed.',
			array(
				'file'  => $remote_name,
				'parts' => count( $parts ),
			)
		);

		$entry = $this->client->create_s3_entry( $session['key'], $remote_name, $size, $extension, $parent_id, $settings );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		return array(
			'path'        => $path,
			'remote_name' => $remote_name,
			'size'        => $size,
			'key'         => $session['key'],
			'drime'       => $entry,
		);
	}
}
