<?php

namespace CCF\Admin;

use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
	private const CAPABILITY = 'manage_options';
	private const MENU_SLUG  = 'caja-comun';

	private DashboardService $dashboard_service;
	private MonthlyIncomesRepository $incomes_repository;
	private MonthlyAllocationService $allocation_service;

	public function __construct(
		DashboardService $dashboard_service,
		MonthlyIncomesRepository $incomes_repository,
		MonthlyAllocationService $allocation_service
	) {
		$this->dashboard_service  = $dashboard_service;
		$this->incomes_repository = $incomes_repository;
		$this->allocation_service = $allocation_service;
	}

	public function register(): void {
		add_menu_page(
			'Caja Común',
			'Caja Común',
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-pie',
			26
		);

		add_submenu_page( self::MENU_SLUG, 'Dashboard', 'Dashboard', self::CAPABILITY, self::MENU_SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::MENU_SLUG, 'Ingresos Mensuales', 'Ingresos Mensuales', self::CAPABILITY, 'ccf-incomes', array( $this, 'render_monthly_incomes' ) );
		add_submenu_page( self::MENU_SLUG, 'Asignación Mensual', 'Asignación Mensual', self::CAPABILITY, 'ccf-allocations', array( $this, 'render_monthly_allocation' ) );
		add_submenu_page( self::MENU_SLUG, 'Ajustes', 'Ajustes', self::CAPABILITY, 'ccf-settings', array( $this, 'render_settings' ) );

		// Punto de entrada alternativo para ubicar el plugin bajo "Ajustes".
		add_options_page( 'Caja Común', 'Caja Común', self::CAPABILITY, 'ccf-settings', array( $this, 'render_settings' ) );
		add_management_page( 'Caja Común', 'Caja Común', self::CAPABILITY, self::MENU_SLUG, array( $this, 'render_dashboard' ) );
	}

	public function render_dashboard(): void {
		$month_key = isset( $_GET['month_key'] ) ? sanitize_text_field( wp_unslash( $_GET['month_key'] ) ) : gmdate( 'Y-m' );
		$summary   = $this->dashboard_service->month_summary( $month_key );
		require CCF_PATH . 'templates/admin/dashboard.php';
	}

	public function render_monthly_incomes(): void {
		$month_key = isset( $_GET['month_key'] ) ? sanitize_text_field( wp_unslash( $_GET['month_key'] ) ) : gmdate( 'Y-m' );
		if ( isset( $_POST['ccf_save_income'] ) ) {
			check_admin_referer( 'ccf_save_income_action' );
			$this->incomes_repository->upsert(
				sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) ),
				(int) ( $_POST['user_id'] ?? 0 ),
				(float) ( $_POST['amount'] ?? 0 ),
				sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) )
			);
			echo '<div class="notice notice-success"><p>Ingreso guardado.</p></div>';
		}

		$incomes = $this->incomes_repository->list( $month_key, 20 );
		require CCF_PATH . 'templates/admin/monthly-incomes.php';
	}

	public function render_monthly_allocation(): void {
		$month_key = isset( $_GET['month_key'] ) ? sanitize_text_field( wp_unslash( $_GET['month_key'] ) ) : gmdate( 'Y-m' );
		$preview   = $this->allocation_service->preview( $month_key );
		$result    = null;

		if ( isset( $_POST['ccf_run_allocation'] ) ) {
			check_admin_referer( 'ccf_run_allocation_action' );
			$result = $this->allocation_service->run( sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Asignación mensual ejecutada.</p></div>';
		}

		require CCF_PATH . 'templates/admin/monthly-allocation.php';
	}

	public function render_settings(): void {
		require CCF_PATH . 'templates/admin/settings.php';
	}
}
