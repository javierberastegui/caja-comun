<?php

namespace CCF\Repositories;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransactionsRepository {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function insert( array $data ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'transactions' );

		$data = wp_parse_args(
			$data,
			array(
				'category_id'             => null,
				'counterparty_account_id' => null,
				'description'             => '',
				'auto_generated'          => 1,
				'reference'               => '',
				'created_by'              => get_current_user_id(),
				'created_at'              => $this->database_manager->now(),
			)
		);

		$data['amount'] = round( (float) $data['amount'], 2 );

		$wpdb->insert( $table, $data );

		return (int) $wpdb->insert_id;
	}

	public function latest( int $limit = 10 ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'transactions' );

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}
}
