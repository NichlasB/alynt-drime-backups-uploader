<?php
/**
 * Test Drime folder client.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

class Alynt_Drime_Backups_Uploader_Test_Folder_Client extends Alynt_Drime_Backups_Uploader_Drime_Client {
	private $user_folders;
	private $children;
	private $paths;

	public function __construct( array $user_folders = array(), array $children = array(), array $paths = array() ) {
		parent::__construct( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->user_folders = $user_folders;
		$this->children     = $children;
		$this->paths        = $paths;
	}

	public function list_user_folders( $workspace_id = 0 ) {
		unset( $workspace_id );

		return $this->user_folders;
	}

	public function list_folder_entries( $workspace_id, $folder_hash, $page = 1, $query = '' ) {
		unset( $workspace_id, $page, $query );

		return array(
			'data' => isset( $this->children[ $folder_hash ] ) ? $this->children[ $folder_hash ] : array(),
		);
	}

	public function get_folder_path( $folder_hash ) {
		return isset( $this->paths[ $folder_hash ] ) ? $this->paths[ $folder_hash ] : array( 'path' => '' );
	}
}
