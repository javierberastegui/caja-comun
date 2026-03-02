<?php

namespace CCF\Admin;

use CCF\Repositories\AccountsRepository;
use CCF\Repositories\CategoriesRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;
use CCF\Services\AttachmentsService;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;
use CCF\Services\NotesService;
use CCF\Services\ReviewService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
	private const CAPABILITY = 'manage_options';
	private const MENU_SLUG = 'caja-comun';

	public function __construct(private DashboardService $dashboard_service, private MonthlyIncomesRepository $incomes_repository, private MonthlyAllocationService $allocation_service, private AccountsRepository $accounts_repository, private CategoriesRepository $categories_repository, private TransactionsRepository $transactions_repository, private AttachmentsService $attachments_service, private NotesService $notes_service, private ReviewService $review_service) {}

	public function register(): void {
		add_menu_page( 'Caja Común', 'Caja Común', self::CAPABILITY, self::MENU_SLUG, array( $this, 'render_dashboard' ), 'dashicons-chart-pie', 26 );
		add_submenu_page( self::MENU_SLUG, 'Dashboard', 'Dashboard', self::CAPABILITY, self::MENU_SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::MENU_SLUG, 'Ingresos Mensuales', 'Ingresos Mensuales', self::CAPABILITY, 'ccf-incomes', array( $this, 'render_monthly_incomes' ) );
		add_submenu_page( self::MENU_SLUG, 'Transacciones', 'Transacciones', self::CAPABILITY, 'ccf-transactions', array( $this, 'render_transactions' ) );
		add_submenu_page( self::MENU_SLUG, 'Cuentas', 'Cuentas', self::CAPABILITY, 'ccf-accounts', array( $this, 'render_accounts' ) );
		add_submenu_page( self::MENU_SLUG, 'Categorías', 'Categorías', self::CAPABILITY, 'ccf-categories', array( $this, 'render_categories' ) );
		add_submenu_page( self::MENU_SLUG, 'Justificantes', 'Justificantes', self::CAPABILITY, 'ccf-attachments', array( $this, 'render_attachments' ) );
		add_submenu_page( self::MENU_SLUG, 'Revisión', 'Revisión de movimientos', self::CAPABILITY, 'ccf-review', array( $this, 'render_review' ) );
		add_submenu_page( self::MENU_SLUG, 'Asignación Mensual', 'Asignación Mensual', self::CAPABILITY, 'ccf-allocations', array( $this, 'render_monthly_allocation' ) );
	}

	public function render_dashboard(): void { $month_key = sanitize_text_field( wp_unslash( $_GET['month_key'] ?? gmdate( 'Y-m' ) ) ); $summary = $this->dashboard_service->month_summary( $month_key ); require CCF_PATH . 'templates/admin/dashboard.php'; }
	public function render_monthly_incomes(): void { $month_key = sanitize_text_field( wp_unslash( $_GET['month_key'] ?? gmdate( 'Y-m' ) ) ); if ( isset( $_POST['ccf_save_income'] ) ) { check_admin_referer( 'ccf_save_income_action' ); $this->incomes_repository->upsert( sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) ), (int) $_POST['user_id'], (float) $_POST['amount'], sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['status'] ?? 'confirmed' ) ) ); } $incomes = $this->incomes_repository->list( $month_key, 100, array( 'user_id' => (int) ( $_GET['user_id'] ?? 0 ), 'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ) ) ); require CCF_PATH . 'templates/admin/monthly-incomes.php'; }
	public function render_monthly_allocation(): void { $month_key = sanitize_text_field( wp_unslash( $_GET['month_key'] ?? gmdate( 'Y-m' ) ) ); $preview = $this->allocation_service->preview( $month_key ); $result = null; if ( isset( $_POST['ccf_run_allocation'] ) ) { check_admin_referer( 'ccf_run_allocation_action' ); $result = $this->allocation_service->run( sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) ) ); } require CCF_PATH . 'templates/admin/monthly-allocation.php'; }
	public function render_accounts(): void { if ( isset( $_POST['ccf_save_account'] ) ) { check_admin_referer( 'ccf_save_account_action' ); $this->accounts_repository->save( wp_unslash( $_POST ) ); } $accounts = $this->accounts_repository->get_all(); require CCF_PATH . 'templates/admin/accounts.php'; }
	public function render_categories(): void { if ( isset( $_POST['ccf_save_category'] ) ) { check_admin_referer( 'ccf_save_category_action' ); $this->categories_repository->save( wp_unslash( $_POST ) ); } $categories = $this->categories_repository->list(); require CCF_PATH . 'templates/admin/categories.php'; }
	public function render_transactions(): void { if ( isset( $_POST['ccf_save_transaction'] ) ) { check_admin_referer( 'ccf_save_transaction_action' ); $id = $this->transactions_repository->insert( wp_unslash( $_POST ) ); if ( ! empty( $_POST['note_content'] ) ) { $this->notes_service->add( $id, sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ), 'internal', ! empty( $_POST['note_pending'] ) ); } } $filters = array_map( 'sanitize_text_field', wp_unslash( $_GET ) ); $transactions = $this->transactions_repository->list( $filters, 200 ); $accounts = $this->accounts_repository->get_all( array( 'status' => 'active' ) ); $categories = $this->categories_repository->list( array( 'active' => 1 ) ); require CCF_PATH . 'templates/admin/transactions.php'; }
	public function render_attachments(): void { $tx_id = (int) ( $_GET['transaction_id'] ?? 0 ); $attachments = $tx_id ? $this->attachments_service->list_by_transaction( $tx_id ) : array(); require CCF_PATH . 'templates/admin/attachments.php'; }
	public function render_review(): void { $month_key = sanitize_text_field( wp_unslash( $_GET['month_key'] ?? gmdate( 'Y-m' ) ) ); if ( isset( $_POST['ccf_mark_review'] ) ) { check_admin_referer( 'ccf_mark_review_action' ); $this->transactions_repository->update( (int) $_POST['transaction_id'], array( 'reviewed' => ! empty( $_POST['reviewed'] ) ? 1 : 0, 'flagged' => ! empty( $_POST['flagged'] ) ? 1 : 0 ) ); } $queue = $this->review_service->queue( $month_key ); require CCF_PATH . 'templates/admin/review.php'; }
}
