<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardService {
	private MonthlyIncomesRepository $incomes_repository;
	private TransactionsRepository $transactions_repository;
	private DatabaseManager $database_manager;

	public function __construct(
		MonthlyIncomesRepository $incomes_repository,
		TransactionsRepository $transactions_repository,
		DatabaseManager $database_manager
	) {
		$this->incomes_repository      = $incomes_repository;
		$this->transactions_repository = $transactions_repository;
		$this->database_manager        = $database_manager;
	}

	public function month_summary( string $month_key ): array {
		global $wpdb;

		$allocation_table = $this->database_manager->table( 'monthly_allocations' );
		$allocation       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$allocation_table} WHERE month_key = %s", $month_key ), ARRAY_A );
		$totals           = $this->incomes_repository->totals_for_month( $month_key );

		return array(
			'month_key'            => $month_key,
			'income_total'         => (float) $totals['total'],
			'separated_total'      => $allocation ? (float) $allocation['separated_total'] : 0.0,
			'common_budget'        => $allocation ? (float) $allocation['common_budget'] : (float) $totals['total'],
			'allocation_status'    => $allocation ? $allocation['status'] : 'pending',
			'latest_incomes'       => $this->incomes_repository->list( $month_key, 5 ),
			'latest_transactions'  => $this->transactions_repository->latest( 5 ),
			'chart_placeholders'   => array(
				'income_trend'                 => true,
				'common_budget_trend'          => true,
				'expense_by_category'          => true,
				'budget_vs_real_expense'       => true,
				'separated_percentage_compare' => true,
			),
		);
	}
}
