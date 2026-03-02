<?php

namespace CCF\Services;

use CCF\Database\DatabaseManager;
use CCF\Repositories\AccountsRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonthlyAllocationService {
	private AccountsRepository $accounts_repository;
	private MonthlyIncomesRepository $incomes_repository;
	private TransactionsRepository $transactions_repository;
	private DatabaseManager $database_manager;

	public function __construct( AccountsRepository $accounts_repository, MonthlyIncomesRepository $incomes_repository, TransactionsRepository $transactions_repository, DatabaseManager $database_manager ) {
		$this->accounts_repository = $accounts_repository;
		$this->incomes_repository = $incomes_repository;
		$this->transactions_repository = $transactions_repository;
		$this->database_manager = $database_manager;
	}

	public function preview( string $month_key, ?float $percent = null ): array {
		$totals = $this->incomes_repository->totals_for_month( $month_key );
		$total_ingresos = (float) $totals['total'];
		$separation_percent = null !== $percent ? $percent : $this->get_default_percentage();
		$total_separado = round( $total_ingresos * ( $separation_percent / 100 ), 2 );
		$separated_user_1 = round( $total_separado / 2, 2 );
		$separated_user_2 = round( $total_separado - $separated_user_1, 2 );
		$common_budget = round( $total_ingresos - $total_separado, 2 );

		return array(
			'month_key' => $month_key,
			'income_total' => round( $total_ingresos, 2 ),
			'separated_total' => $total_separado,
			'separated_user_1' => $separated_user_1,
			'separated_user_2' => $separated_user_2,
			'common_budget' => round( $common_budget, 2 ),
			'separation_percent' => round( $separation_percent, 2 ),
			'residue_strategy' => $this->get_residue_strategy(),
		);
	}

	public function run( string $month_key, ?float $percent = null ): array {
		global $wpdb;
		$preview = $this->preview( $month_key, $percent );
		$table = $this->database_manager->table( 'monthly_allocations' );
		$now = $this->database_manager->now();
		$user_id = get_current_user_id();
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE month_key = %s", $month_key ) );
		$data = array_merge( $preview, array( 'status' => 'completed', 'run_by' => $user_id, 'run_at' => $now, 'updated_at' => $now ) );

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$allocation_id = (int) $existing;
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$allocation_id = (int) $wpdb->insert_id;
		}

		$this->register_automatic_transactions( $preview, $user_id );
		return array_merge( $preview, array( 'allocation_id' => $allocation_id, 'status' => 'completed' ) );
	}

	private function register_automatic_transactions( array $preview, int $user_id ): void {
		$common_account = $this->accounts_repository->find_by_slug( 'cuenta-comun' );
		$user_1_account = $this->accounts_repository->find_by_slug( 'cuenta-personal-usuario-1' );
		$user_2_account = $this->accounts_repository->find_by_slug( 'cuenta-personal-usuario-2' );
		$savings_account = $this->accounts_repository->find_by_slug( 'cuenta-ahorro' );
		if ( ! $common_account || ! $savings_account ) {
			return;
		}

		$this->transactions_repository->insert(
			array(
				'month_key' => $preview['month_key'],
				'type' => 'allocation',
				'destination_account_id' => (int) $common_account['id'],
				'amount' => $preview['common_budget'],
				'transaction_date' => $preview['month_key'] . '-01',
				'description' => 'Asignación automática a presupuesto común',
				'auto_generated' => 1,
				'reference' => 'monthly-allocation',
				'created_by' => $user_id,
			)
		);

		$this->transactions_repository->insert(
			array(
				'month_key' => $preview['month_key'],
				'type' => 'transfer',
				'source_account_id' => (int) $common_account['id'],
				'destination_account_id' => (int) $savings_account['id'],
				'amount' => $preview['separated_total'],
				'transaction_date' => $preview['month_key'] . '-01',
				'description' => 'Separación automática fuera de presupuesto',
				'auto_generated' => 1,
				'created_by' => $user_id,
			)
		);

		if ( $user_1_account && $user_2_account ) {
			foreach ( array( 1 => $user_1_account, 2 => $user_2_account ) as $index => $account ) {
				$this->transactions_repository->insert(
					array(
						'month_key' => $preview['month_key'],
						'type' => 'transfer',
						'source_account_id' => (int) $savings_account['id'],
						'destination_account_id' => (int) $account['id'],
						'amount' => $preview[ 'separated_user_' . $index ],
						'transaction_date' => $preview['month_key'] . '-01',
						'description' => sprintf( 'Asignación separada usuario %d', $index ),
						'auto_generated' => 1,
						'created_by' => $user_id,
					)
				);
			}
		}
	}

	private function get_default_percentage(): float {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM ' . $this->database_manager->table( 'settings' ) . ' WHERE setting_key = %s', 'ccf_separation_percent' ) );
		return $value ? (float) $value : 10.0;
	}

	private function get_residue_strategy(): string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM ' . $this->database_manager->table( 'settings' ) . ' WHERE setting_key = %s', 'ccf_residue_strategy' ) );
		return $value ?: 'to_common_budget';
	}
}
