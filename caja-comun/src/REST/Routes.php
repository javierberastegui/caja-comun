<?php

namespace CCF\REST;

use CCF\Repositories\AccountsRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routes {
	private const NAMESPACE = 'caja-comun/v1';

	private AccountsRepository $accounts_repository;
	private MonthlyIncomesRepository $incomes_repository;
	private MonthlyAllocationService $allocation_service;
	private DashboardService $dashboard_service;

	public function __construct(
		AccountsRepository $accounts_repository,
		MonthlyIncomesRepository $incomes_repository,
		MonthlyAllocationService $allocation_service,
		DashboardService $dashboard_service
	) {
		$this->accounts_repository = $accounts_repository;
		$this->incomes_repository  = $incomes_repository;
		$this->allocation_service  = $allocation_service;
		$this->dashboard_service   = $dashboard_service;
	}

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/accounts',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => fn() => new WP_REST_Response( array( 'data' => $this->accounts_repository->get_all() ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monthly-incomes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'list_incomes' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'can_manage' ),
					'callback'            => array( $this, 'save_income' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monthly-allocations/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'preview_allocation' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monthly-allocations/run',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'run_allocation' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/month-summary',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'dashboard_summary' ),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_incomes( WP_REST_Request $request ): WP_REST_Response {
		$month_key = $this->sanitize_month_key( (string) $request->get_param( 'month_key' ), true );
		if ( is_wp_error( $month_key ) ) {
			return new WP_REST_Response( array( 'error' => $month_key->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'data' => $this->incomes_repository->list( $month_key ) ) );
	}

	public function save_income( WP_REST_Request $request ) {
		$month_key = $this->sanitize_month_key( (string) $request->get_param( 'month_key' ) );
		$user_id   = (int) $request->get_param( 'user_id' );
		$amount    = (float) $request->get_param( 'amount' );
		$notes     = sanitize_text_field( (string) $request->get_param( 'notes' ) );

		if ( is_wp_error( $month_key ) ) {
			return $month_key;
		}

		if ( $user_id <= 0 || $amount <= 0 ) {
			return new WP_Error( 'ccf_invalid_payload', 'Debe enviar user_id válido y amount > 0.', array( 'status' => 400 ) );
		}

		$income_id = $this->incomes_repository->upsert( $month_key, $user_id, $amount, $notes );

		return new WP_REST_Response(
			array(
				'message'   => 'Ingreso mensual guardado.',
				'income_id' => $income_id,
			),
			201
		);
	}

	public function preview_allocation( WP_REST_Request $request ) {
		$month_key = $this->sanitize_month_key( (string) $request->get_param( 'month_key' ) );
		$percent   = $request->get_param( 'separation_percent' );
		$percent   = null !== $percent ? (float) $percent : null;

		if ( is_wp_error( $month_key ) ) {
			return $month_key;
		}

		return new WP_REST_Response( $this->allocation_service->preview( $month_key, $percent ) );
	}

	public function run_allocation( WP_REST_Request $request ) {
		$month_key = $this->sanitize_month_key( (string) $request->get_param( 'month_key' ) );
		$percent   = $request->get_param( 'separation_percent' );
		$percent   = null !== $percent ? (float) $percent : null;

		if ( is_wp_error( $month_key ) ) {
			return $month_key;
		}

		return new WP_REST_Response( $this->allocation_service->run( $month_key, $percent ) );
	}

	public function dashboard_summary( WP_REST_Request $request ): WP_REST_Response {
		$month_key = $this->sanitize_month_key( (string) $request->get_param( 'month_key' ), true );
		if ( is_wp_error( $month_key ) ) {
			return new WP_REST_Response( array( 'error' => $month_key->get_error_message() ), 400 );
		}

		if ( ! $month_key ) {
			$month_key = gmdate( 'Y-m' );
		}

		return new WP_REST_Response( $this->dashboard_service->month_summary( $month_key ) );
	}

	private function sanitize_month_key( string $month_key, bool $allow_empty = false ) {
		$month_key = trim( $month_key );

		if ( '' === $month_key && $allow_empty ) {
			return null;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_key ) ) {
			return new WP_Error( 'ccf_invalid_month_key', 'month_key debe tener formato YYYY-MM.', array( 'status' => 400 ) );
		}

		return $month_key;
	}
}
