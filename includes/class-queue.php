<?php
/**
 * Upload queue storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores queued and active uploads.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Queue {
	use Alynt_Drime_Backups_Uploader_Option_Storage;

	const QUEUE_OPTION  = 'alynt_drime_backups_upload_queue';
	const ACTIVE_OPTION = 'alynt_drime_backups_active_upload';

	/**
	 * Whether the most recent batch queue write failed.
	 *
	 * @var bool
	 */
	private $last_persistence_failed = false;

	/**
	 * Adds an item if it is not already queued.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function add( array $item ) {
		return 1 === $this->add_many( array( $item ) );
	}

	/**
	 * Adds an item to the front of the queue.
	 *
	 * @param array<string,mixed>               $item Item.
	 * @param array<string,array<string,mixed>> $uploaded Uploaded records keyed by signature.
	 * @return bool
	 *
	 * @since 0.5.0
	 */
	public function prepend( array $item, array $uploaded = array() ) {
		$queue = $this->all();
		$index = $this->duplicate_index( $queue );

		if ( empty( $item['signature'] ) ) {
			return false;
		}

		$signature = (string) $item['signature'];
		if ( isset( $uploaded[ $signature ] ) || isset( $queue[ $signature ] ) || $this->has_indexed_duplicate_item( $index, $item ) ) {
			return false;
		}

		$item['queued_at'] = time();
		$item['attempts']  = isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0;
		$queue             = array( $signature => $item ) + $queue;

		return $this->persist_array_option( self::QUEUE_OPTION, $queue );
	}

	/**
	 * Adds multiple items using one option write.
	 *
	 * @param array<int,array<string,mixed>>    $items Items.
	 * @param array<string,array<string,mixed>> $uploaded Uploaded records keyed by signature.
	 * @return int Number of queued items, or 0 if persistence fails.
	 *
	 * @since 0.1.0
	 */
	public function add_many( array $items, array $uploaded = array() ) {
		$this->last_persistence_failed = false;
		$queue                         = $this->all();
		$index                         = $this->duplicate_index( $queue );
		$added                         = 0;

		foreach ( $items as $item ) {
			if ( ! $this->queue_item( $queue, $index, $uploaded, $item ) ) {
				continue;
			}

			++$added;
		}

		if ( 0 === $added ) {
			return 0;
		}

		if ( ! $this->persist_array_option( self::QUEUE_OPTION, $queue ) ) {
			$this->last_persistence_failed = true;
			return 0;
		}

		return $added;
	}

	/**
	 * Returns whether the most recent batch queue write failed.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function last_persistence_failed() {
		return $this->last_persistence_failed;
	}

	/**
	 * Adds one item to an in-memory queue if it is eligible.
	 *
	 * @param array<string,array<string,mixed>> $queue Queue.
	 * @param array<string,array<string,bool>>  $index Duplicate index.
	 * @param array<string,array<string,mixed>> $uploaded Uploaded records keyed by signature.
	 * @param array<string,mixed>               $item Item.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	private function queue_item( array &$queue, array &$index, array $uploaded, array $item ) {
		if ( empty( $item['signature'] ) ) {
			return false;
		}

		$signature = (string) $item['signature'];
		if ( isset( $uploaded[ $signature ] ) || isset( $queue[ $signature ] ) ) {
			return false;
		}

		if ( $this->has_indexed_duplicate_item( $index, $item ) ) {
			return false;
		}

		$item['queued_at']   = time();
		$item['attempts']    = isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0;
		$queue[ $signature ] = $item;
		$this->index_duplicate_item( $index, $item );

		return true;
	}

	/**
	 * Returns all queued items.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function all() {
		return $this->get_array_option( self::QUEUE_OPTION );
	}

	/**
	 * Returns the next queued item.
	 *
	 * @return array<string,mixed>|null
	 *
	 * @since 0.1.0
	 */
	public function next() {
		$queue = $this->all();

		if ( empty( $queue ) ) {
			return null;
		}

		$item = reset( $queue );

		return is_array( $item ) ? $item : null;
	}

	/**
	 * Removes a queued item.
	 *
	 * @param string $signature Signature.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function remove( $signature ) {
		$queue = $this->all();
		unset( $queue[ $signature ] );

		return $this->persist_array_option( self::QUEUE_OPTION, $queue );
	}

	/**
	 * Increments attempts.
	 *
	 * @param string $signature Signature.
	 * @return int
	 *
	 * @since 0.1.0
	 */
	public function increment_attempts( $signature ) {
		$queue = $this->all();
		if ( isset( $queue[ $signature ] ) ) {
			$queue[ $signature ]['attempts'] = isset( $queue[ $signature ]['attempts'] ) ? absint( $queue[ $signature ]['attempts'] ) + 1 : 1;
			if ( ! $this->persist_array_option( self::QUEUE_OPTION, $queue ) ) {
				return 0;
			}

			return (int) $queue[ $signature ]['attempts'];
		}

		return 0;
	}

	/**
	 * Sets active upload state.
	 *
	 * @param array<string,mixed>|null $state State.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function set_active( $state ) {
		if ( null === $state ) {
			return $this->delete_array_option( self::ACTIVE_OPTION );
		}

		return $this->persist_array_option( self::ACTIVE_OPTION, $state );
	}

	/**
	 * Returns active upload state.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function get_active() {
		return $this->get_array_option( self::ACTIVE_OPTION );
	}

	/**
	 * Clears active upload state.
	 *
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function clear_active() {
		return $this->delete_array_option( self::ACTIVE_OPTION );
	}

	/**
	 * Builds duplicate lookup maps for queued paths and producer-specific files.
	 *
	 * @param array<string,array<string,mixed>> $queue Queue.
	 * @return array<string,array<string,bool>>
	 */
	private function duplicate_index( array $queue ) {
		$index = array(
			'paths'   => array(),
			'wpvivid' => array(),
		);

		foreach ( $queue as $existing ) {
			if ( ! is_array( $existing ) ) {
				continue;
			}

			$this->index_duplicate_item( $index, $existing );
		}

		return $index;
	}

	/**
	 * Adds one queue item to duplicate lookup maps.
	 *
	 * @param array<string,array<string,bool>> $index Duplicate index.
	 * @param array<string,mixed>              $item Item.
	 * @return void
	 */
	private function index_duplicate_item( array &$index, array $item ) {
		$path = $this->local_path_key( $item );
		if ( '' !== $path ) {
			$index['paths'][ $path ] = true;
		}

		$wpvivid = $this->wpvivid_file_key( $item );
		if ( '' !== $wpvivid ) {
			$index['wpvivid'][ $wpvivid ] = true;
		}
	}

	/**
	 * Checks for duplicate queue entries beyond the signature key.
	 *
	 * @param array<string,array<string,bool>> $index Duplicate index.
	 * @param array<string,mixed>              $item Item.
	 * @return bool
	 */
	private function has_indexed_duplicate_item( array $index, array $item ) {
		$path    = $this->local_path_key( $item );
		$wpvivid = $this->wpvivid_file_key( $item );

		return ( '' !== $path && isset( $index['paths'][ $path ] ) ) || ( '' !== $wpvivid && isset( $index['wpvivid'][ $wpvivid ] ) );
	}

	/**
	 * Returns a normalized local path lookup key.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function local_path_key( array $item ) {
		return isset( $item['path'] ) ? wp_normalize_path( (string) $item['path'] ) : '';
	}

	/**
	 * Returns a WPvivid backup file lookup key.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function wpvivid_file_key( array $item ) {
		$id   = $this->wpvivid_backup_id( $item );
		$name = isset( $item['name'] ) ? (string) $item['name'] : '';

		return '' !== $id && '' !== $name ? $id . '|' . $name : '';
	}

	/**
	 * Returns a queue item's WPvivid backup id.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function wpvivid_backup_id( array $item ) {
		if ( empty( $item['wpvivid'] ) || ! is_array( $item['wpvivid'] ) || empty( $item['wpvivid']['backup_id'] ) ) {
			return '';
		}

		return (string) $item['wpvivid']['backup_id'];
	}
}
