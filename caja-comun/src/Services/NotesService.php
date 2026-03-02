<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotesService {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function add( int $transaction_id, string $content, string $note_type = 'internal', bool $pending = false ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'transaction_notes' );
		$wpdb->insert(
			$table,
			array(
				'transaction_id' => $transaction_id,
				'note_type'      => sanitize_key( $note_type ),
				'content'        => wp_kses_post( $content ),
				'is_pending'     => $pending ? 1 : 0,
				'created_by'     => get_current_user_id(),
				'created_at'     => $this->database_manager->now(),
				'updated_at'     => $this->database_manager->now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function list( int $transaction_id ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'transaction_notes' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE transaction_id = %d ORDER BY id DESC", $transaction_id ), ARRAY_A );
	}

	public function set_pending( int $id, bool $pending ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'transaction_notes' );
		return false !== $wpdb->update( $table, array( 'is_pending' => $pending ? 1 : 0, 'updated_at' => $this->database_manager->now() ), array( 'id' => $id ) );
	}
}
