<?php
/**
 * Shared Drime listing and streamed download client behavior.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Drime_Client {
	/**
	 * Lists candidate Drime entries for a package.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $folder_hash Folder hash.
	 * @param string $query Query.
	 * @param string $token Bearer token.
	 * @return array<int,array<string,mixed>>
	 */
	private function list_drime_entries( $workspace_id, $folder_hash, $query, $token ) {
		$args = array(
			'workspaceId' => max( 0, (int) $workspace_id ),
			'folderId'    => $folder_hash,
			'query'       => $query,
			'perPage'     => 100,
			'page'        => 1,
		);

		$url      = $this->drime_api_url( '/drive/file-entries?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
		$response = $this->drime_json_request( $url, $token );
		if ( ! is_array( $response ) ) {
			return array();
		}

		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			$this->error( 'No matching remote package files were found.' );
			return array();
		}

		return $response['data'];
	}

	/**
	 * Finds a Drime entry by exact filename.
	 *
	 * @param array<int,array<string,mixed>> $entries Entries.
	 * @param string                         $name Name.
	 * @return array<string,mixed>
	 */
	private function find_drime_entry_by_name( array $entries, $name ) {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['name'] ) || (string) $entry['name'] !== $name ) {
				continue;
			}

			if ( empty( $entry['hash'] ) || ! is_scalar( $entry['hash'] ) ) {
				continue;
			}

			return $entry;
		}

		return array();
	}

	/**
	 * Downloads one Drime file entry to a local path.
	 *
	 * @param string $hash Entry hash.
	 * @param string $destination Destination.
	 * @param string $token Bearer token.
	 * @return bool
	 */
	private function download_drime_entry( $hash, $destination, $token ) {
		$temp_path = $destination . '.tmp';
		if ( file_exists( $temp_path ) && ! unlink( $temp_path ) ) {
			$this->error( 'Could not remove stale temporary download file.' );
			return false;
		}

		$result = $this->download_url_to_temp_path(
			$this->drime_api_url( '/file-entries/download/' . rawurlencode( $hash ) ),
			$temp_path,
			$token
		);

		if ( '' !== $result['redirect'] ) {
			$redirect_url = $this->validate_download_redirect_url( $result['redirect'] );
			if ( '' === $redirect_url ) {
				$this->error( 'Drime download redirect target is not a safe HTTPS URL.' );
				unlink( $temp_path );
				return false;
			}

			$result = $this->download_url_to_temp_path( $redirect_url, $temp_path, '' );
		}

		if ( ! $result['ok'] || $result['status'] < 200 || $result['status'] >= 300 ) {
			$this->error( 'Drime download failed with HTTP status ' . $result['status'] . '.' );
			if ( '' !== $result['error'] ) {
				$this->error( $result['error'] );
			}

			unlink( $temp_path );
			return false;
		}

		if ( ! is_file( $temp_path ) || filesize( $temp_path ) <= 0 ) {
			$this->error( 'Downloaded file is empty.' );
			unlink( $temp_path );
			return false;
		}

		if ( file_exists( $destination ) && ! unlink( $destination ) ) {
			$this->error( 'Could not replace existing destination file.' );
			unlink( $temp_path );
			return false;
		}

		if ( ! rename( $temp_path, $destination ) ) {
			$this->error( 'Could not promote downloaded file into place.' );
			unlink( $temp_path );
			return false;
		}

		return true;
	}

	/**
	 * Downloads a URL to a temporary path without automatically following redirects.
	 *
	 * @param string $url URL.
	 * @param string $temp_path Temporary path.
	 * @param string $token Optional bearer token for first-party Drime API requests.
	 * @return array{ok:bool,status:int,error:string,redirect:string}
	 */
	private function download_url_to_temp_path( $url, $temp_path, $token ) {
		if ( file_exists( $temp_path ) && ! unlink( $temp_path ) ) {
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not remove stale temporary download file.',
				'redirect' => '',
			);
		}

		$handle = fopen( $temp_path, 'wb' );
		if ( false === $handle ) {
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not create temporary download file.',
				'redirect' => '',
			);
		}

		$headers = array();
		$curl    = curl_init( $url );
		if ( false === $curl ) {
			fclose( $handle );
			unlink( $temp_path );
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not initialize Drime download request.',
				'redirect' => '',
			);
		}

		$http_headers = array();
		if ( '' !== $token ) {
			$http_headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt( $curl, CURLOPT_HTTPHEADER, $http_headers );
		curl_setopt( $curl, CURLOPT_FILE, $handle );
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, $this->drime_download_timeout_seconds() );
		curl_setopt(
			$curl,
			CURLOPT_HEADERFUNCTION,
			function ( $curl_handle, $header ) use ( &$headers ) {
				unset( $curl_handle );

				$length = strlen( $header );
				$parts  = explode( ':', $header, 2 );
				if ( 2 === count( $parts ) ) {
					$headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}

				return $length;
			}
		);

		$ok     = curl_exec( $curl );
		$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error  = curl_error( $curl );

		$this->close_curl( $curl );
		fclose( $handle );

		return array(
			'ok'       => true === $ok,
			'status'   => $status,
			'error'    => $error,
			'redirect' => $status >= 300 && $status < 400 && isset( $headers['location'] ) ? $headers['location'] : '',
		);
	}

	/**
	 * Validates a Drime download redirect target.
	 *
	 * @param string $url Redirect URL.
	 * @return string Safe URL or empty string.
	 */
	private function validate_download_redirect_url( $url ) {
		$url    = trim( $url );
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		$host   = parse_url( $url, PHP_URL_HOST );
		$user   = parse_url( $url, PHP_URL_USER );
		$pass   = parse_url( $url, PHP_URL_PASS );

		if ( 'https' !== strtolower( (string) $scheme ) || '' === (string) $host || null !== $user || null !== $pass ) {
			return '';
		}

		return false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}

	/**
	 * Performs a Drime JSON request.
	 *
	 * @param string $url URL.
	 * @param string $token Bearer token.
	 * @return array<string,mixed>
	 */
	private function drime_json_request( $url, $token ) {
		$curl = curl_init( $url );
		if ( false === $curl ) {
			$this->error( 'Could not initialize Drime API request.' );
			return array();
		}

		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $token ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 60 );

		$body  = curl_exec( $curl );
		$code  = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $curl );

		$this->close_curl( $curl );

		if ( ! is_string( $body ) || $code < 200 || $code >= 300 ) {
			$this->error( 'Drime API request failed with HTTP status ' . $code . '.' );
			if ( '' !== $error ) {
				$this->error( $error );
			}

			return array();
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			$this->error( 'Drime API returned invalid JSON.' );
			return array();
		}

		return $decoded;
	}

	/**
	 * Returns the bounded timeout for one Drime package-file download.
	 *
	 * @return int
	 */
	private function drime_download_timeout_seconds() {
		$timeout = isset( $this->config['drime_download_timeout_seconds'] ) ? (int) $this->config['drime_download_timeout_seconds'] : 21600;

		return max( 300, min( 86400, $timeout ) );
	}

	/**
	 * Builds a Drime API URL.
	 *
	 * @param string $path API path.
	 * @return string
	 */
	private function drime_api_url( $path ) {
		$base = $this->config_string( 'drime_api_base_url' );
		if ( '' === $base ) {
			$base = 'https://app.drime.cloud/api/v1';
		}

		return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Closes cURL handles on PHP versions where explicit close is still needed.
	 *
	 * @param resource|CurlHandle $curl cURL handle.
	 * @return void
	 */
	private function close_curl( $curl ) {
		if ( PHP_VERSION_ID < 80500 ) {
			curl_close( $curl );
		}
	}

}
