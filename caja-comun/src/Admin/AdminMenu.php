<?php

namespace CCF\Admin;

use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
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
		add_menu_page( 'Caja Común', 'Caja Común', 'manage_options', 'ccf-dashboard', array( $this, 'render_dashboard' ), 'dashicons-chart-pie', 26 );
		add_submenu_page( 'ccf-dashboard', 'Ingresos Mensuales', 'Ingresos Mensuales', 'manage_options', 'ccf-monthly-incomes', array( $this, 'render_monthly_incomes' ) );
		add_submenu_page( 'ccf-dashboard', 'Asignación Mensual', 'Asignación Mensual', 'manage_options', 'ccf-monthly-allocation', array( $this, 'render_monthly_allocation' ) );
		add_submenu_page( 'ccf-dashboard', 'Ajustes', 'Ajustes', 'manage_options', 'ccf-settings', array( $this, 'render_settings' ) );
	}

	public function render_dashboard(): void {
		$month_key = isset( $_GET['month_key'] ) ? sanitize_text_field( wp_unslash( $_GET['month_key'] ) ) : gmdate( 'Y-m' );
		$summary   = $this->dashboard_service->month_summary( $month_key );
		require CCF_PLUGIN_DIR . 'templates/admin/dashboard.php';
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
		require CCF_PLUGIN_DIR . 'templates/admin/monthly-incomes.php';
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

		require CCF_PLUGIN_DIR . 'templates/admin/monthly-allocation.php';
	}

	public function render_settings(): void {
		require CCF_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
