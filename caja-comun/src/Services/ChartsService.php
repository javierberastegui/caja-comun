<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChartsService {
	private MonthlyIncomesRepository $incomes_repository;
	private TransactionsRepository $transactions_repository;
	private DatabaseManager $database_manager;

	public function __construct( MonthlyIncomesRepository $incomes_repository, TransactionsRepository $transactions_repository, DatabaseManager $database_manager ) {
		$this->incomes_repository = $incomes_repository;
		$this->transactions_repository = $transactions_repository;
		$this->database_manager = $database_manager;
	}

	public function dashboard_charts( string $month_key ): array {
		global $wpdb;
		$allocation_table = $this->database_manager->table( 'monthly_allocations' );
		$transactions_table = $this->database_manager->table( 'transactions' );
		$allocation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$allocation_table} WHERE month_key = %s", $month_key ), ARRAY_A );
		$income_totals = $this->incomes_repository->totals_for_month( $month_key );
		$category_rows = $wpdb->get_results( $wpdb->prepare( "SELECT category_id, SUM(amount) total FROM {$transactions_table} WHERE month_key = %s AND type = 'expense' GROUP BY category_id", $month_key ), ARRAY_A );
		$expense = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$transactions_table} WHERE month_key = %s AND type = 'expense'", $month_key ) );

		$evolution = $wpdb->get_results( "SELECT month_key, common_budget FROM {$allocation_table} ORDER BY month_key ASC LIMIT 12", ARRAY_A );

		return array(
			'income_vs_split_vs_common' => array(
				'labels' => array( 'Ingresos', 'Separado', 'Presupuesto común' ),
				'data' => array( (float) $income_totals['total'], (float) ( $allocation['separated_total'] ?? 0 ), (float) ( $allocation['common_budget'] ?? $income_totals['total'] ) ),
			),
			'common_expense_by_category' => $category_rows,
			'budget_vs_expense' => array(
				'budget' => (float) ( $allocation['common_budget'] ?? $income_totals['total'] ),
				'expense' => $expense,
			),
			'common_budget_evolution' => $evolution,
		);
	}
}
