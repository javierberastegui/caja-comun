<?php

namespace CCF\Repositories;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonthlyIncomesRepository {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function list( ?string $month_key = null, int $limit = 20 ): array {
		global $wpdb;

		$table = $this->database_manager->table( 'monthly_incomes' );
		$sql   = "SELECT * FROM {$table}";

		if ( $month_key ) {
			$sql .= $wpdb->prepare( ' WHERE month_key = %s', $month_key );
		}

		$sql .= $wpdb->prepare( ' ORDER BY created_at DESC LIMIT %d', $limit );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function upsert( string $month_key, int $user_id, float $amount, string $notes = '' ): int {
		global $wpdb;

		$table = $this->database_manager->table( 'monthly_incomes' );
		$now   = $this->database_manager->now();
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE month_key = %s AND user_id = %d",
				$month_key,
				$user_id
			)
		);

		$data = array(
			'month_key'  => $month_key,
			'user_id'    => $user_id,
			'amount'     => round( $amount, 2 ),
			'notes'      => $notes,
			'created_by' => get_current_user_id(),
			'updated_at' => $now,
		);

		if ( $found ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $found ) );
			return (int) $found;
		}

		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );

		return (int) $wpdb->insert_id;
	}

	public function totals_for_month( string $month_key ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'monthly_incomes' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, amount FROM {$table} WHERE month_key = %s ORDER BY user_id ASC",
				$month_key
			),
			ARRAY_A
		);

		$total = 0.0;
		foreach ( $rows as $row ) {
			$total += (float) $row['amount'];
		}

		return array(
			'rows'  => $rows,
			'total' => round( $total, 2 ),
		);
	}
}
