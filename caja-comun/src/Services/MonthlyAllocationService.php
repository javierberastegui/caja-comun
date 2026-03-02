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

	public function __construct(
		AccountsRepository $accounts_repository,
		MonthlyIncomesRepository $incomes_repository,
		TransactionsRepository $transactions_repository,
		DatabaseManager $database_manager
	) {
		$this->accounts_repository     = $accounts_repository;
		$this->incomes_repository      = $incomes_repository;
		$this->transactions_repository = $transactions_repository;
		$this->database_manager        = $database_manager;
	}

	public function preview( string $month_key, ?float $separation_percent = null ): array {
		$totals             = $this->incomes_repository->totals_for_month( $month_key );
		$total_ingresos     = (float) $totals['total'];
		$separation_percent = null === $separation_percent ? $this->get_default_percentage() : $separation_percent;
		$separation_percent = round( $separation_percent, 2 );

		$total_separado      = round( $total_ingresos * ( $separation_percent / 100 ), 2 );
		$mitad_base          = round( $total_separado / 2, 2 );
		$residuo             = round( $total_separado - ( $mitad_base * 2 ), 2 );
		$separado_usuario_1  = $mitad_base;
		$separado_usuario_2  = $mitad_base;
		$common_budget       = round( $total_ingresos - $total_separado, 2 );
		$residue_strategy    = $this->get_residue_strategy();

		if ( abs( $residuo ) > 0.0 ) {
			if ( 'to_user_1' === $residue_strategy ) {
				$separado_usuario_1 = round( $separado_usuario_1 + $residuo, 2 );
			} elseif ( 'to_user_2' === $residue_strategy ) {
				$separado_usuario_2 = round( $separado_usuario_2 + $residuo, 2 );
			} else {
				$common_budget = round( $common_budget + $residuo, 2 );
			}
		}

		return array(
			'month_key'          => $month_key,
			'income_total'       => round( $total_ingresos, 2 ),
			'separation_percent' => $separation_percent,
			'separated_total'    => round( $total_separado, 2 ),
			'separated_user_1'   => round( $separado_usuario_1, 2 ),
			'separated_user_2'   => round( $separado_usuario_2, 2 ),
			'common_budget'      => round( $common_budget, 2 ),
			'residue'            => round( $residuo, 2 ),
			'residue_strategy'   => $residue_strategy,
			'incomes'            => $totals['rows'],
		);
	}

	public function run( string $month_key, ?float $separation_percent = null ): array {
		global $wpdb;

		$preview = $this->preview( $month_key, $separation_percent );
		$table   = $this->database_manager->table( 'monthly_allocations' );
		$now     = $this->database_manager->now();
		$user_id = get_current_user_id();

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE month_key = %s", $month_key ) );

		$data = array(
			'month_key'           => $month_key,
			'status'              => 'completed',
			'income_total'        => $preview['income_total'],
			'separated_total'     => $preview['separated_total'],
			'separated_user_1'    => $preview['separated_user_1'],
			'separated_user_2'    => $preview['separated_user_2'],
			'common_budget'       => $preview['common_budget'],
			'separation_percent'  => $preview['separation_percent'],
			'residue_strategy'    => $preview['residue_strategy'],
			'run_by'              => $user_id,
			'run_at'              => $now,
			'updated_at'          => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			$allocation_id = (int) $existing;
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$allocation_id = (int) $wpdb->insert_id;
		}

		$this->register_automatic_transactions( $preview, $user_id );
		$this->audit( 'monthly_allocation.run', 'monthly_allocation', $allocation_id, $preview, $user_id );

		return array_merge( $preview, array( 'allocation_id' => $allocation_id, 'status' => 'completed' ) );
	}

	private function register_automatic_transactions( array $preview, int $user_id ): void {
		$common_account  = $this->accounts_repository->find_by_slug( 'common_budget' );
		$pool_account    = $this->accounts_repository->find_by_slug( 'separated_pool' );
		$user_1_account  = $this->accounts_repository->find_by_slug( 'user_1_separated' );
		$user_2_account  = $this->accounts_repository->find_by_slug( 'user_2_separated' );

		if ( ! $common_account || ! $pool_account || ! $user_1_account || ! $user_2_account ) {
			return;
		}

		$this->transactions_repository->insert(
			array(
				'month_key'       => $preview['month_key'],
				'type'            => 'allocation_common_budget',
				'account_id'      => (int) $common_account['id'],
				'amount'          => $preview['common_budget'],
				'direction'       => 'in',
				'description'     => 'Asignación automática a presupuesto común',
				'auto_generated'  => 1,
				'reference'       => 'monthly-allocation',
				'created_by'      => $user_id,
			)
		);

		$this->transactions_repository->insert(
			array(
				'month_key'      => $preview['month_key'],
				'type'           => 'allocation_separated_pool',
				'account_id'     => (int) $pool_account['id'],
				'amount'         => $preview['separated_total'],
				'direction'      => 'in',
				'description'    => 'Separación automática fuera de presupuesto',
				'auto_generated' => 1,
				'reference'      => 'monthly-allocation',
				'created_by'     => $user_id,
			)
		);

		foreach ( array( 1 => $user_1_account, 2 => $user_2_account ) as $index => $account ) {
			$this->transactions_repository->insert(
				array(
					'month_key'               => $preview['month_key'],
					'type'                    => 'allocation_separated_user',
					'account_id'              => (int) $account['id'],
					'counterparty_account_id' => (int) $pool_account['id'],
					'amount'                  => $preview[ 'separated_user_' . $index ],
					'direction'               => 'in',
					'description'             => sprintf( 'Asignación separada usuario %d', $index ),
					'auto_generated'          => 1,
					'reference'               => 'monthly-allocation',
					'created_by'              => $user_id,
				)
			);
		}
	}

	private function audit( string $event_type, string $entity_type, int $entity_id, array $payload, int $user_id ): void {
		global $wpdb;

		$wpdb->insert(
			$this->database_manager->table( 'audit_log' ),
			array(
				'event_type'   => $event_type,
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'payload'      => wp_json_encode( $payload ),
				'performed_by' => $user_id,
				'created_at'   => $this->database_manager->now(),
			)
		);
	}

	private function get_default_percentage(): float {
		global $wpdb;
		$table = $this->database_manager->table( 'settings' );
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", 'ccf_separation_percent' ) );

		return $value ? (float) $value : 10.0;
	}

	private function get_residue_strategy(): string {
		global $wpdb;
		$table = $this->database_manager->table( 'settings' );
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", 'ccf_residue_strategy' ) );

		return $value ?: 'to_common_budget';
	}
}
