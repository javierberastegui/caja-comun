<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttachmentsService {
	private DatabaseManager $database_manager;
	private array $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function create_from_upload( int $transaction_id, array $file, string $document_type ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return array( 'error' => $attachment_id->get_error_message() );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, $this->allowed_mimes, true ) ) {
			wp_delete_attachment( $attachment_id, true );
			return array( 'error' => 'Tipo MIME no permitido.' );
		}

		global $wpdb;
		$wpdb->insert(
			$this->database_manager->table( 'transaction_attachments' ),
			array(
				'transaction_id' => $transaction_id,
				'attachment_id'  => $attachment_id,
				'document_type'  => sanitize_key( $document_type ),
				'mime_type'      => $mime,
				'created_by'     => get_current_user_id(),
				'created_at'     => $this->database_manager->now(),
			)
		);

		return array( 'id' => (int) $wpdb->insert_id, 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) );
	}

	public function list_by_transaction( int $transaction_id ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'transaction_attachments' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE transaction_id = %d ORDER BY id DESC", $transaction_id ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['url'] = wp_get_attachment_url( (int) $row['attachment_id'] );
		}
		return $rows;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'transaction_attachments' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		wp_delete_attachment( (int) $row['attachment_id'], true );
		$wpdb->delete( $table, array( 'id' => $id ) );
		return true;
	}
}
