<?php
/**
 * Drime workspace browser.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes Drime workspaces for the admin selector.
 *
 * @since 0.5.0
 */
class Alynt_Drime_Backups_Uploader_Workspace_Browser {
	/**
	 * Drime client.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Drime_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Drime_Client $client Client.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Drime_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Lists selectable workspaces.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_workspaces() {
		$response = $this->client->list_workspaces();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$workspaces = $this->extract_workspaces( $response );

		return array(
			'workspaces'        => $this->filter_allowed_workspaces( array_values( array_filter( array_map( array( $this, 'normalize_workspace' ), $workspaces ) ) ) ),
			'allowlist_enabled' => Alynt_Drime_Backups_Uploader_Settings::workspace_allowlist_enabled(),
			'allowed_ids'       => Alynt_Drime_Backups_Uploader_Settings::allowed_workspace_ids(),
		);
	}

	/**
	 * Extracts the workspace list from known response shapes.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return array<int,array<string,mixed>>
	 */
	private function extract_workspaces( array $response ) {
		if ( isset( $response['workspaces'] ) && is_array( $response['workspaces'] ) ) {
			return $response['workspaces'];
		}

		if ( isset( $response['data']['workspaces'] ) && is_array( $response['data']['workspaces'] ) ) {
			return $response['data']['workspaces'];
		}

		return array();
	}

	/**
	 * Normalizes one workspace row.
	 *
	 * @param array<string,mixed>|mixed $workspace Workspace.
	 * @return array<string,mixed>|null
	 */
	private function normalize_workspace( $workspace ) {
		if ( ! is_array( $workspace ) || empty( $workspace['id'] ) ) {
			return null;
		}

		$name = isset( $workspace['name'] ) ? sanitize_text_field( (string) $workspace['name'] ) : '';
		if ( '' === $name ) {
			$name = sprintf(
				/* translators: %d: Drime workspace ID. */
				__( 'Workspace %d', 'alynt-drime-backups-uploader' ),
				absint( $workspace['id'] )
			);
		}

		return array(
			'id'            => absint( $workspace['id'] ),
			'name'          => $name,
			'members_count' => isset( $workspace['members_count'] ) ? absint( $workspace['members_count'] ) : 0,
			'role'          => $this->workspace_role( $workspace ),
			'is_owner'      => $this->workspace_is_owner( $workspace ),
		);
	}

	/**
	 * Filters normalized workspaces to allowed backup destinations.
	 *
	 * @param array<int,array<string,mixed>> $workspaces Workspaces.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_allowed_workspaces( array $workspaces ) {
		return array_values(
			array_filter(
				$workspaces,
				function ( array $workspace ) {
					return isset( $workspace['id'] ) && Alynt_Drime_Backups_Uploader_Settings::is_workspace_id_allowed( absint( $workspace['id'] ) );
				}
			)
		);
	}

	/**
	 * Gets the current user's workspace role.
	 *
	 * @param array<string,mixed> $workspace Workspace.
	 * @return string
	 */
	private function workspace_role( array $workspace ) {
		if ( isset( $workspace['currentUser']['role_name'] ) ) {
			return sanitize_text_field( (string) $workspace['currentUser']['role_name'] );
		}

		return '';
	}

	/**
	 * Gets whether the current user owns the workspace.
	 *
	 * @param array<string,mixed> $workspace Workspace.
	 * @return bool
	 */
	private function workspace_is_owner( array $workspace ) {
		return ! empty( $workspace['currentUser']['is_owner'] ) || ! empty( $workspace['owner']['is_owner'] );
	}
}
