<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChartsService {
	public function __construct( private MonthlyIncomesRepository $incomes_repository, private TransactionsRepository $transactions_repository, private DatabaseManager $database_manager ) {}

	public function dashboard_charts( string $month_key ): array {
		return array(
			'income_vs_split_vs_common' => $this->income_vs_common( $month_key, $month_key ),
			'common_expense_by_category' => $this->common_expense_by_category( $month_key ),
			'budget_vs_expense' => $this->common_budget_vs_actual( $month_key ),
			'common_budget_evolution' => $this->common_budget_trend( gmdate( 'Y-m', strtotime( '-11 months' ) ), $month_key ),
		);
	}

	public function income_vs_common( string $from, string $to ): array {
		global $wpdb;
		$alloc_table = $this->database_manager->table( 'monthly_allocations' );
		$rows        = $wpdb->get_results( $wpdb->prepare( "SELECT month_key, income_total, separated_total, common_budget FROM {$alloc_table} WHERE month_key BETWEEN %s AND %s ORDER BY month_key ASC", $from, $to ), ARRAY_A );
		return array( 'series' => $rows );
	}

	public function common_expense_by_category( string $month ): array {
		global $wpdb;
		$transactions_table = $this->database_manager->table( 'transactions' );
		$categories_table   = $this->database_manager->table( 'categories' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT c.id AS category_id, c.name AS category_name, COALESCE(SUM(t.amount),0) AS total FROM {$categories_table} c LEFT JOIN {$transactions_table} t ON t.category_id = c.id AND t.month_key = %s AND t.type = 'expense' GROUP BY c.id, c.name ORDER BY total DESC", $month ), ARRAY_A );
	}

	public function common_budget_vs_actual( string $month ): array {
		global $wpdb;
		$alloc_table = $this->database_manager->table( 'monthly_allocations' );
		$tx_table    = $this->database_manager->table( 'transactions' );
		$allocation  = $wpdb->get_row( $wpdb->prepare( "SELECT common_budget, separated_total FROM {$alloc_table} WHERE month_key = %s", $month ), ARRAY_A );
		$expense     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$tx_table} WHERE month_key = %s AND type = 'expense'", $month ) );
		return array(
			'month_key' => $month,
			'common_budget' => (float) ( $allocation['common_budget'] ?? 0 ),
			'separated_total' => (float) ( $allocation['separated_total'] ?? 0 ),
			'actual_expense' => $expense,
		);
	}

	public function common_budget_trend( string $from, string $to ): array {
		global $wpdb;
		$alloc_table = $this->database_manager->table( 'monthly_allocations' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT month_key, common_budget, separated_total FROM {$alloc_table} WHERE month_key BETWEEN %s AND %s ORDER BY month_key ASC", $from, $to ), ARRAY_A );
	}
}
