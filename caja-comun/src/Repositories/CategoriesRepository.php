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

	public function find( int $id ): ?array {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ?: null;
	}

	public function find_by_name( string $name ): ?array {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s LIMIT 1", sanitize_text_field( $name ) ), ARRAY_A );

		return $row ?: null;
	}

	public function save( array $data ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		$now   = $this->database_manager->now();
		$id    = ! empty( $data['id'] ) ? (int) $data['id'] : 0;

		$name          = sanitize_text_field( (string) $data['name'] );
		$provided_slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		$base_slug     = $provided_slug ?: sanitize_title( $name );
		if ( '' === $base_slug ) {
			$base_slug = 'categoria';
		}
		$slug = $this->build_unique_slug( $base_slug, $id );

		$has_active_value = array_key_exists( 'active', $data );

		$payload = array(
			'name'          => $name,
			'slug'          => $slug,
			'description'   => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'color'         => sanitize_hex_color( (string) ( $data['color'] ?? '#2271b1' ) ) ?: '#2271b1',
			'icon'          => sanitize_key( (string) ( $data['icon'] ?? 'money-alt' ) ),
			'parent_id'     => ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null,
			'display_order' => (int) ( $data['display_order'] ?? 0 ),
			'active'        => $has_active_value ? ( ! empty( $data['active'] ) ? 1 : 0 ) : 1,
			'updated_at'    => $now,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $payload, array( 'id' => $id ) );
			return $id;
		}

		$payload['created_at'] = $now;
		$wpdb->insert( $table, $payload );

		return (int) $wpdb->insert_id;
	}

	private function build_unique_slug( string $base_slug, int $current_id = 0 ): string {
		global $wpdb;
		$table      = $this->database_manager->table( 'categories' );
		$candidate  = $base_slug;
		$suffix     = 2;

		while ( true ) {
			if ( $current_id > 0 ) {
				$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id != %d", $candidate, $current_id ) );
			} else {
				$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $candidate ) );
			}

			if ( $existing_id <= 0 ) {
				return $candidate;
			}

			$candidate = $base_slug . '-' . $suffix;
			++$suffix;
		}
	}

	public function set_active( int $id, bool $active ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );
		return false !== $wpdb->update( $table, array( 'active' => $active ? 1 : 0, 'updated_at' => $this->database_manager->now() ), array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'categories' );

		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
