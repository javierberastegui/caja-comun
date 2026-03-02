<?php

namespace CCF\Repositories;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CategoriesRepository {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function list( array $filters = array() ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		$where = array( '1=1' );
		if ( isset( $filters['active'] ) && '' !== $filters['active'] ) {
			$where[] = $wpdb->prepare( 'active = %d', (int) $filters['active'] );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY display_order ASC, name ASC', ARRAY_A );
	}

	public function save( array $data ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		$now   = $this->database_manager->now();

		$payload = array(
			'name'          => sanitize_text_field( (string) $data['name'] ),
			'slug'          => sanitize_title( (string) ( $data['slug'] ?? $data['name'] ) ),
			'description'   => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'color'         => sanitize_hex_color( (string) ( $data['color'] ?? '#2271b1' ) ) ?: '#2271b1',
			'icon'          => sanitize_key( (string) ( $data['icon'] ?? 'money-alt' ) ),
			'parent_id'     => ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null,
			'display_order' => (int) ( $data['display_order'] ?? 0 ),
			'active'        => ! empty( $data['active'] ) ? 1 : 0,
			'updated_at'    => $now,
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $payload, array( 'id' => (int) $data['id'] ) );
			return (int) $data['id'];
		}

		$payload['created_at'] = $now;
		$wpdb->insert( $table, $payload );

		return (int) $wpdb->insert_id;
	}

	public function set_active( int $id, bool $active ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		return false !== $wpdb->update( $table, array( 'active' => $active ? 1 : 0, 'updated_at' => $this->database_manager->now() ), array( 'id' => $id ) );
	}
}
