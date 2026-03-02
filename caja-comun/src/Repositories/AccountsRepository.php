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

	public function get_all(): array {
		global $wpdb;

		$table = $this->database_manager->table( 'accounts' );

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );
	}

	public function find_by_slug( string $slug ): ?array {
		global $wpdb;

		$table = $this->database_manager->table( 'accounts' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );

		return $row ?: null;
	}
}
