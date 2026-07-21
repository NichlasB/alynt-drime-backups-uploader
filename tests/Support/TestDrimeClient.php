<?php
/**
 * Drime client test double.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

class Alynt_Drime_Backups_Uploader_Test_Drime_Client extends Alynt_Drime_Backups_Uploader_Drime_Client {
	public $create_multipart_calls = 0;
	public $uploaded_part_numbers  = array();
	public $parts                  = array();
	public $completed_key          = '';
	public $completed_upload_id    = '';
	public $validate_files         = array();
	public $validate_parent_id     = null;
	public $create_multipart_parent_id = null;
	public $create_s3_parent_id    = null;
	public $create_multipart_settings = array();
	public $create_s3_settings     = array();
	public $entry_parent_id        = 0;
	public $aborted_key            = '';
	public $aborted_upload_id      = '';
	public $connection_result      = true;
	public $validate_calls         = 0;
	public $sign_response          = array();
	public $signed_part_number_batches = array();
	public $abort_result           = array( 'status' => 'success' );
	public $children               = array();
	public $created_folders        = array();
	public $simple_upload_names    = array();
	public $simple_upload_parent_ids = array();
	public $simple_upload_settings = array();
	public $duplicate_names        = array();
	public $upload_part_callback   = null;
	private $next_folder_id        = 654;

	public function test_connection() {
		return $this->connection_result;
	}

	public function validate_upload( array $files, $parent_id = null ) {
		++$this->validate_calls;
		$this->validate_files     = $files;
		$this->validate_parent_id = $parent_id;

		$duplicates = array();
		foreach ( $files as $file ) {
			if ( is_array( $file ) && isset( $file['name'] ) && in_array( (string) $file['name'], $this->duplicate_names, true ) ) {
				$duplicates[] = $file;
			}
		}

		return array( 'duplicates' => $duplicates );
	}

	public function simple_upload( $path, $remote_name, $parent_id = null, ?array $settings_override = null ) {
		unset( $path );
		$this->simple_upload_names[] = $remote_name;
		$this->simple_upload_parent_ids[] = $parent_id;
		$this->simple_upload_settings[] = $settings_override;

		return array(
			'fileEntry' => array(
				'id'   => 456,
				'name' => $remote_name,
				'parent_id' => null !== $parent_id ? $parent_id : 0,
			),
		);
	}

	public function create_multipart_upload( $filename, $size, $extension, $parent_id = null, ?array $settings_override = null ) {
		unset( $filename, $size, $extension );
		++$this->create_multipart_calls;
		$this->create_multipart_parent_id = $parent_id;
		$this->create_multipart_settings  = null === $settings_override ? array() : $settings_override;
		return array(
			'key'      => 'new-key',
			'uploadId' => 'new-upload',
		);
	}

	public function get_uploaded_parts( $key, $upload_id ) {
		unset( $key, $upload_id );
		return array( 'parts' => $this->parts );
	}

	public function abort_multipart_upload( $key, $upload_id ) {
		$this->aborted_key       = $key;
		$this->aborted_upload_id = $upload_id;

		return $this->abort_result;
	}

	public function sign_part_urls( $key, $upload_id, array $part_numbers ) {
		unset( $key, $upload_id );
		$this->signed_part_number_batches[] = $part_numbers;
		if ( ! empty( $this->sign_response ) ) {
			return $this->sign_response;
		}

		$urls = array();
		foreach ( $part_numbers as $part_number ) {
			$urls[] = array(
				'partNumber' => $part_number,
				'url'        => 'https://example.test/upload/' . $part_number,
			);
		}

		return array(
			'urls' => $urls,
		);
	}

	public function upload_part( $url, $data ) {
		unset( $url, $data );
		if ( is_callable( $this->upload_part_callback ) ) {
			call_user_func( $this->upload_part_callback );
		}
		$this->uploaded_part_numbers[] = count( $this->uploaded_part_numbers ) + ( empty( $this->parts ) ? 1 : 2 );
		return '"etag-' . end( $this->uploaded_part_numbers ) . '"';
	}

	public function complete_multipart_upload( $key, $upload_id, array $parts ) {
		$this->completed_key       = $key;
		$this->completed_upload_id = $upload_id;
		return array(
			'location' => 'https://example.test/object.zip',
			'parts'    => $parts,
		);
	}

	public function create_s3_entry( $key, $client_name, $size, $extension, $parent_id = null, ?array $settings_override = null ) {
		unset( $key, $client_name, $size, $extension );
		$this->create_s3_parent_id = $parent_id;
		$this->create_s3_settings  = null === $settings_override ? array() : $settings_override;
		$file_entry = array( 'id' => 123 );

		if ( $this->entry_parent_id > 0 ) {
			$file_entry['parent_id'] = $this->entry_parent_id;
		} elseif ( null !== $parent_id ) {
			$file_entry['parent_id'] = $parent_id;
		}

		return array( 'fileEntry' => $file_entry );
	}

	public function list_folder_entries( $workspace_id, $folder_hash, $page = 1, $query = '' ) {
		unset( $workspace_id, $page, $query );

		return array(
			'data' => isset( $this->children[ $folder_hash ] ) ? $this->children[ $folder_hash ] : array(),
		);
	}

	public function create_folder( $workspace_id, $name, $parent_id = 0 ) {
		unset( $workspace_id );
		$this->created_folders[ $name ] = $parent_id;
		$id = $this->next_folder_id++;

		return array(
			'folder' => array(
				'id'   => $id,
				'hash' => 'createdhash' . $id,
				'name' => $name,
			),
		);
	}
}
