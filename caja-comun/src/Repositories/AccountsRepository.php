<?php

namespace CCF\Repositories;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccountsRepository {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function get_all( array $filters = array() ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );
		$where = array( '1=1' );

		if ( isset( $filters['status'] ) && '' !== $filters['status'] ) {
			$where[] = $wpdb->prepare( 'status = %s', $filters['status'] );
		}

		if ( isset( $filters['type'] ) && '' !== $filters['type'] ) {
			$where[] = $wpdb->prepare( 'type = %s', sanitize_key( (string) $filters['type'] ) );
		}

		if ( isset( $filters['is_visible'] ) && '' !== (string) $filters['is_visible'] ) {
			$where[] = $wpdb->prepare( 'is_visible = %d', ! empty( $filters['is_visible'] ) ? 1 : 0 );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY display_order ASC, id ASC', ARRAY_A );
	}

	public function find( int $id ): ?array {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ?: null;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );

		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	public function find_by_slug( string $slug ): ?array {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );

		return $row ?: null;
	}

	public function find_first_active_common(): ?array {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE type = 'common' AND status = 'active' ORDER BY display_order ASC, id ASC LIMIT 1", ARRAY_A );

		return $row ?: null;
	}

	public function save( array $data ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'accounts' );
		$now   = $this->database_manager->now();

		$payload = array(
			'slug'            => sanitize_title( (string) $data['slug'] ),
			'name'            => sanitize_text_field( (string) $data['name'] ),
			'type'            => sanitize_key( (string) $data['type'] ),
			'description'     => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'status'          => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'display_order'   => (int) ( $data['display_order'] ?? 0 ),
			'is_visible'      => ! empty( $data['is_visible'] ) ? 1 : 0,
			'allow_manual'    => ! empty( $data['allow_manual'] ) ? 1 : 0,
			'monthly_process' => ! empty( $data['monthly_process'] ) ? 1 : 0,
			'updated_at'      => $now,
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
		$table = $this->database_manager->table( 'accounts' );

		return false !== $wpdb->update(
			$table,
			array(
				'status'     => $active ? 'active' : 'inactive',
				'updated_at' => $this->database_manager->now(),
			),
			array( 'id' => $id )
		);
	}
}
