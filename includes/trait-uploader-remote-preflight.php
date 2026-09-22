<?php
/**
 * Uploader remote preflight helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs Drime connection, duplicate, simple-upload, and remote-parent bookkeeping helpers.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Remote_Preflight {
	/**
	 * Runs connection and duplicate preflight checks.
	 *
	 * @param string              $remote_name Remote name.
	 * @param int                 $size Size.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return string|false|WP_Error
	 */
	private function preflight_remote_name( $remote_name, $size, array $settings, $parent_id = null ) {
		$connection = $this->client->test_connection();
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return $this->resolve_duplicate_mode( $remote_name, $size, $settings, $parent_id );
	}

	/**
	 * Uploads a small queued item.
	 *
	 * @param string                   $path File path.
	 * @param string                   $remote_name Remote name.
	 * @param int                      $size Size.
	 * @param int|null                 $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null $settings Effective upload settings.
	 * @return array<string,mixed>|WP_Error
	 */
	private function simple_upload_item( $path, $remote_name, $size, $parent_id = null, ?array $settings = null ) {
		$response = $this->client->simple_upload( $path, $remote_name, $parent_id, $settings );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'path'        => $path,
			'remote_name' => $remote_name,
			'size'        => $size,
			'drime'       => $response,
		);
	}

	/**
	 * Handles duplicate mode.
	 *
	 * @param string              $remote_name Remote name.
	 * @param int                 $size Size.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return string|false|WP_Error
	 */
	private function resolve_duplicate_mode( $remote_name, $size, array $settings, $parent_id = null ) {
		$has_parent_override = null !== $parent_id;
		$parent_id           = $has_parent_override ? absint( $parent_id ) : $this->resolved_drime_parent_id( $settings );
		$file                = array(
			'name' => $remote_name,
			'size' => $size,
		);

		if ( ! $has_parent_override && '' !== $settings['relative_path'] && ! empty( $settings['parent_folder_id'] ) ) {
			$file['relativePath'] = $settings['relative_path'];
		} elseif ( $parent_id <= 0 ) {
			$file['relativePath'] = '/';
		}

		$validation = $this->client->validate_upload( array( $file ), $parent_id > 0 ? $parent_id : null );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$duplicates = isset( $validation['duplicates'] ) && is_array( $validation['duplicates'] ) ? $validation['duplicates'] : array();
		if ( empty( $duplicates ) ) {
			return $remote_name;
		}

		if ( 'skip' === $settings['duplicate_mode'] ) {
			return false;
		}

		return $this->client->get_available_name( $remote_name, $parent_id > 0 ? $parent_id : null );
	}

	/**
	 * Resolves the Drime parent ID available for duplicate checks.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return int
	 */
	private function resolved_drime_parent_id( array $settings ) {
		if ( ! empty( $settings['parent_folder_id'] ) ) {
			return absint( $settings['parent_folder_id'] );
		}

		if ( empty( $settings['relative_path'] ) ) {
			return 0;
		}

		return $this->registry->get_drime_parent_id( absint( $settings['workspace_id'] ), (string) $settings['relative_path'] );
	}

	/**
	 * Remembers the Drime parent ID returned after a relative-path upload.
	 *
	 * @param array<string,mixed> $result Upload result.
	 * @param array<string,mixed> $item Queue item.
	 * @return void
	 */
	private function remember_remote_parent_from_result( array $result, array $item ) {
		$settings = $this->effective_upload_settings( $this->settings->get(), $item );

		if ( empty( $settings['relative_path'] ) ) {
			return;
		}

		if ( empty( $result['drime']['fileEntry'] ) || ! is_array( $result['drime']['fileEntry'] ) || empty( $result['drime']['fileEntry']['parent_id'] ) ) {
			return;
		}

		$this->registry->remember_drime_location( absint( $settings['workspace_id'] ), (string) $settings['relative_path'], absint( $result['drime']['fileEntry']['parent_id'] ), absint( $settings['parent_folder_id'] ) );
	}
}
