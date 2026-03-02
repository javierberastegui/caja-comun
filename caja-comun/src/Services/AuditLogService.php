<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLogService {
	public function __construct( private DatabaseManager $database_manager ) {}

	public function log( string $event_type, string $entity_type, ?int $entity_id = null, array $payload = array() ): void {
		global $wpdb;
		$table = $this->database_manager->table( 'audit_log' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'event_type'   => sanitize_key( $event_type ),
				'entity_type'  => sanitize_key( $entity_type ),
				'entity_id'    => $entity_id,
				'payload'      => wp_json_encode( $payload ),
				'performed_by' => get_current_user_id(),
				'created_at'   => $this->database_manager->now(),
			)
		);
	}
}
