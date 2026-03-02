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
		$transaction_date = ! empty( $data['transaction_date'] ) ? $data['transaction_date'] : gmdate( 'Y-m-d' );
		$payload = wp_parse_args(
			$data,
			array(
				'month_key'              => substr( $transaction_date, 0, 7 ),
				'type'                   => 'expense',
				'source_account_id'      => null,
				'destination_account_id' => null,
				'category_id'            => null,
				'subcategory_id'         => null,
				'currency'               => 'EUR',
				'accounting_date'        => null,
				'description'            => '',
				'quick_note'             => '',
				'status'                 => 'posted',
				'reviewed'               => 0,
				'reconciled'             => 0,
				'flagged'                => 0,
				'auto_generated'         => 0,
				'reference'              => '',
				'created_by'             => get_current_user_id(),
				'created_at'             => $this->database_manager->now(),
				'updated_at'             => $this->database_manager->now(),
			)
		);
		$payload['amount'] = round( (float) $payload['amount'], 2 );
		$payload['transaction_date'] = $transaction_date;
		$wpdb->insert( $table, $payload );
		return (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'transactions' );
		$data['updated_at'] = $this->database_manager->now();
		if ( isset( $data['amount'] ) ) {
			$data['amount'] = round( (float) $data['amount'], 2 );
		}
		return false !== $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	public function list( array $filters = array(), int $limit = 100 ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'transactions' );
		$where = array( '1=1' );
		$attachments_table = $this->database_manager->table( 'transaction_attachments' );

		$map = array(
			'month_key' => 't.month_key', 'user_id' => 't.created_by', 'account_id' => 't.source_account_id', 'category_id' => 't.category_id', 'status' => 't.status', 'reviewed' => 't.reviewed', 'type' => 't.type',
		);
		foreach ( $map as $key => $column ) {
			if ( '' !== (string) ( $filters[ $key ] ?? '' ) ) {
				$where[] = $wpdb->prepare( "{$column} = %s", $filters[ $key ] );
			}
		}
		if ( isset( $filters['year'] ) && '' !== $filters['year'] ) {
			$where[] = $wpdb->prepare( 't.month_key LIKE %s', sanitize_text_field( $filters['year'] ) . '-%' );
		}
		if ( isset( $filters['has_attachment'] ) && '' !== $filters['has_attachment'] ) {
			$where[] = 't.id ' . ( (int) $filters['has_attachment'] ? 'IN' : 'NOT IN' ) . " (SELECT transaction_id FROM {$attachments_table})";
		}

		$sql = "SELECT t.* FROM {$table} t WHERE " . implode( ' AND ', $where ) . $wpdb->prepare( ' ORDER BY t.transaction_date DESC, t.id DESC LIMIT %d', $limit );
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function latest( int $limit = 10 ): array {
		return $this->list( array(), $limit );
	}

	public function review_queue( string $month_key ): array {
		global $wpdb;
		$table = $this->database_manager->table( 'transactions' );
		$attachments = $this->database_manager->table( 'transaction_attachments' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, (SELECT COUNT(*) FROM {$attachments} a WHERE a.transaction_id = t.id) AS attachment_count
				FROM {$table} t
				WHERE t.month_key = %s
				AND (t.reviewed = 0 OR t.category_id IS NULL OR t.flagged = 1 OR (SELECT COUNT(*) FROM {$attachments} a2 WHERE a2.transaction_id = t.id) = 0)
				ORDER BY t.transaction_date DESC",
				$month_key
			),
			ARRAY_A
		);
	}
}
