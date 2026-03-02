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

	public function list( ?string $month_key = null, int $limit = 20, array $filters = array() ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'monthly_incomes' );
		$where = array( '1=1' );

		if ( $month_key ) {
			$where[] = $wpdb->prepare( 'month_key = %s', $month_key );
		}
		if ( ! empty( $filters['year'] ) ) {
			$where[] = $wpdb->prepare( 'month_key LIKE %s', sanitize_text_field( $filters['year'] ) . '-%' );
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', (int) $filters['user_id'] );
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', sanitize_key( $filters['status'] ) );
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . $wpdb->prepare( ' ORDER BY month_key DESC, user_id ASC LIMIT %d', $limit );
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function upsert( string $month_key, int $user_id, float $amount, string $notes = '', string $status = 'confirmed' ): int {
		global $wpdb;
		$table = $this->database_manager->table( 'monthly_incomes' );
		$now   = $this->database_manager->now();
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE month_key = %s AND user_id = %d", $month_key, $user_id ) );

		$data = array(
			'month_key'  => $month_key,
			'user_id'    => $user_id,
			'amount'     => round( $amount, 2 ),
			'status'     => sanitize_key( $status ),
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
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, amount FROM {$table} WHERE month_key = %s AND status != %s ORDER BY user_id ASC", $month_key, 'void' ), ARRAY_A );
		$total = array_reduce(
			$rows,
			static fn( float $carry, array $row ): float => $carry + (float) $row['amount'],
			0.0
		);
		return array( 'rows' => $rows, 'total' => round( $total, 2 ) );
	}
}
