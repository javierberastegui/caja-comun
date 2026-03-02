<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotesService {
	public function __construct( private DatabaseManager $database_manager, private AuditLogService $audit_log_service ) {}

	public function add( int $transaction_id, string $content, string $note_type = 'internal', bool $pending_review = false ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'notes' );
		$wpdb->insert(
			$table,
			array(
				'transaction_id'  => $transaction_id,
				'note_type'       => in_array( $note_type, array( 'internal', 'public' ), true ) ? $note_type : 'internal',
				'content'         => wp_kses_post( $content ),
				'pending_review'  => $pending_review ? 1 : 0,
				'created_by'      => get_current_user_id(),
				'created_at'      => $this->database_manager->now(),
				'updated_at'      => $this->database_manager->now(),
			)
		);
		$id = (int) $wpdb->insert_id;
		$this->audit_log_service->log( 'note_created', 'note', $id, array( 'transaction_id' => $transaction_id ) );
		return $id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table   = $this->database_manager->table( 'notes' );
		$payload = array( 'updated_at' => $this->database_manager->now() );
		if ( isset( $data['content'] ) ) {
			$payload['content'] = wp_kses_post( (string) $data['content'] );
		}
		if ( isset( $data['note_type'] ) ) {
			$payload['note_type'] = in_array( $data['note_type'], array( 'internal', 'public' ), true ) ? $data['note_type'] : 'internal';
		}
		if ( isset( $data['pending_review'] ) ) {
			$payload['pending_review'] = ! empty( $data['pending_review'] ) ? 1 : 0;
		}
		$ok = false !== $wpdb->update( $table, $payload, array( 'id' => $id ) );
		if ( $ok ) {
			$this->audit_log_service->log( 'note_updated', 'note', $id, $payload );
		}
		return $ok;
	}

	public function list( int $transaction_id ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'notes' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE transaction_id = %d ORDER BY id DESC", $transaction_id ), ARRAY_A );
	}

	public function set_pending_review( int $id, bool $pending_review ): bool {
		$ok = $this->update( $id, array( 'pending_review' => $pending_review ? 1 : 0 ) );
		if ( $ok ) {
			$this->audit_log_service->log( 'note_pending_review_updated', 'note', $id, array( 'pending_review' => $pending_review ) );
		}
		return $ok;
	}
}
