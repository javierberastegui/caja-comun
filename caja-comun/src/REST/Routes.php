<?php

namespace CCF\REST;

use CCF\Core\Capabilities;
use CCF\Repositories\AccountsRepository;
use CCF\Repositories\CategoriesRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;
use CCF\Services\AccountsService;
use CCF\Services\AttachmentsService;
use CCF\Services\AuditLogService;
use CCF\Services\CategoriesService;
use CCF\Services\ChartsService;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;
use CCF\Services\NotesService;
use CCF\Services\ReviewService;
use CCF\Services\TransactionsService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routes {
	private const NAMESPACE = 'caja-comun/v1';

	public function __construct(
		private AccountsRepository $accounts_repository,
		private CategoriesRepository $categories_repository,
		private MonthlyIncomesRepository $incomes_repository,
		private TransactionsRepository $transactions_repository,
		private MonthlyAllocationService $allocation_service,
		private DashboardService $dashboard_service,
		private AttachmentsService $attachments_service,
		private NotesService $notes_service,
		private ReviewService $review_service,
		private ChartsService $charts_service,
		private AccountsService $accounts_service,
		private CategoriesService $categories_service,
		private TransactionsService $transactions_service,
		private AuditLogService $audit_log_service
	) {}

	public function register(): void {
		register_rest_route( self::NAMESPACE, '/accounts', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->accounts_repository->get_all( $r->get_params() ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ACCOUNTS ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'handle_create_account' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ACCOUNTS ) ),
		) );
		register_rest_route( self::NAMESPACE, '/accounts/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'handle_get_account' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ACCOUNTS ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'handle_update_account' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ACCOUNTS ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'handle_delete_account' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ACCOUNTS ) ),
		) );

		register_rest_route( self::NAMESPACE, '/categories', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->categories_repository->list( $r->get_params() ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_CATEGORIES ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_category' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_CATEGORIES ) ),
		) );
		register_rest_route( self::NAMESPACE, '/categories/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_category' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_CATEGORIES ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_category' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_CATEGORIES ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_category' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_CATEGORIES ) ),
		) );

		register_rest_route( self::NAMESPACE, '/monthly-incomes', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->incomes_repository->list( $r->get_param( 'month_key' ), 100, $r->get_params() ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_INCOMES ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'save_income' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_INCOMES ) ),
		) );

		register_rest_route( self::NAMESPACE, '/transactions', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'list_transactions' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_transaction' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ),
		) );
		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_transaction' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_transaction' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_transaction' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ),
		) );
		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/review', array( 'methods' => 'POST', 'callback' => array( $this, 'mark_review' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ) );
		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/pending-review', array( 'methods' => 'POST', 'callback' => array( $this, 'mark_pending_review' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_TRANSACTIONS ) ) );

		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/attachments', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->attachments_service->list_by_transaction( (int) $r['id'] ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ATTACHMENTS ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'upload_attachment' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ATTACHMENTS ) ),
		) );
		register_rest_route( self::NAMESPACE, '/attachments/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'ok' => $this->attachments_service->delete( (int) $r['id'] ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ATTACHMENTS ) ) );

		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/notes', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->notes_service->list( (int) $r['id'] ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_NOTES ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_note' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_NOTES ) ),
		) );
		register_rest_route( self::NAMESPACE, '/notes/(?P<id>\d+)', array( 'methods' => 'PUT', 'callback' => array( $this, 'update_note' ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_NOTES ) ) );
		register_rest_route( self::NAMESPACE, '/notes/(?P<id>\d+)/pending', array( 'methods' => 'POST', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'ok' => $this->notes_service->set_pending_review( (int) $r['id'], (bool) $r->get_param( 'pending_review' ) ) ) ), 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_NOTES ) ) );

		register_rest_route( self::NAMESPACE, '/monthly-allocations/preview', array( 'methods' => 'POST', 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ALLOCATIONS ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->allocation_service->preview( (string) $r->get_param( 'month_key' ), $r->get_param( 'separation_percent' ) ? (float) $r->get_param( 'separation_percent' ) : null ) ) ) );
		register_rest_route( self::NAMESPACE, '/monthly-allocations/run', array( 'methods' => 'POST', 'permission_callback' => fn() => $this->can( Capabilities::MANAGE_ALLOCATIONS ), 'callback' => array( $this, 'run_allocation' ) ) );

		register_rest_route( self::NAMESPACE, '/dashboard/month-summary', array( 'methods' => 'GET', 'permission_callback' => fn() => $this->can( Capabilities::VIEW_DASHBOARD ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->dashboard_service->month_summary( (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/charts/income-vs-common', array( 'methods' => 'GET', 'permission_callback' => fn() => $this->can( Capabilities::VIEW_DASHBOARD ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->charts_service->income_vs_common( (string) $r->get_param( 'from' ), (string) $r->get_param( 'to' ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/charts/common-expense-by-category', array( 'methods' => 'GET', 'permission_callback' => fn() => $this->can( Capabilities::VIEW_DASHBOARD ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->charts_service->common_expense_by_category( (string) $r->get_param( 'month' ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/charts/common-budget-vs-actual', array( 'methods' => 'GET', 'permission_callback' => fn() => $this->can( Capabilities::VIEW_DASHBOARD ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->charts_service->common_budget_vs_actual( (string) $r->get_param( 'month' ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/charts/common-budget-trend', array( 'methods' => 'GET', 'permission_callback' => fn() => $this->can( Capabilities::VIEW_DASHBOARD ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->charts_service->common_budget_trend( (string) $r->get_param( 'from' ), (string) $r->get_param( 'to' ) ) ) ) );
	}

	private function can( string $capability ): bool {
		return current_user_can( $capability ) || current_user_can( 'manage_options' );
	}

	public function handle_create_account( WP_REST_Request $request ) {
		$result = $this->accounts_service->create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}
	public function handle_get_account( WP_REST_Request $request ) {
		$row = $this->accounts_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
	}
	public function handle_update_account( WP_REST_Request $request ) {
		$result = $this->accounts_service->update( (int) $request['id'], (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ) );
	}
	public function handle_delete_account( WP_REST_Request $request ) {
		$deleted = $this->accounts_service->delete( (int) $request['id'] );
		return $deleted ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
	}

	public function create_category( WP_REST_Request $request ) {
		$result = $this->categories_service->create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}
	public function get_category( WP_REST_Request $request ) {
		$row = $this->categories_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
	}
	public function update_category( WP_REST_Request $request ) {
		$result = $this->categories_service->update( (int) $request['id'], (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ) );
	}
	public function delete_category( WP_REST_Request $request ) {
		$deleted = $this->categories_service->delete( (int) $request['id'] );
		return $deleted ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
	}

	public function list_transactions( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		if ( ! empty( $params['month'] ) ) {
			$params['month_key'] = sanitize_text_field( $params['month'] );
		}
		return new WP_REST_Response( array( 'data' => $this->transactions_repository->list( $params, (int) ( $params['limit'] ?? 100 ) ) ) );
	}
	public function create_transaction( WP_REST_Request $request ) {
		$result = $this->transactions_service->create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}
	public function get_transaction( WP_REST_Request $request ) {
		$row = $this->transactions_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
	}
	public function update_transaction( WP_REST_Request $request ) {
		$result = $this->transactions_service->update( (int) $request['id'], (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => (bool) $result ) );
	}
	public function delete_transaction( WP_REST_Request $request ) {
		$ok = $this->transactions_service->delete( (int) $request['id'] );
		return $ok ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
	}
	public function mark_review( WP_REST_Request $request ): WP_REST_Response {
		$ok = $this->transactions_repository->update( (int) $request['id'], array( 'reviewed' => (bool) $request->get_param( 'reviewed' ), 'flagged' => (bool) $request->get_param( 'flagged' ) ) );
		return new WP_REST_Response( array( 'ok' => $ok ) );
	}
	public function mark_pending_review( WP_REST_Request $request ): WP_REST_Response {
		$ok = $this->transactions_repository->update( (int) $request['id'], array( 'reviewed' => ! (bool) $request->get_param( 'pending_review' ) ) );
		return new WP_REST_Response( array( 'ok' => $ok ) );
	}

	public function create_note( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'ccf_invalid_note', 'content es obligatorio.', array( 'status' => 400 ) );
		}
		$id = $this->notes_service->add( (int) $request['id'], $content, (string) $request->get_param( 'note_type' ), (bool) $request->get_param( 'pending_review' ) );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}
	public function update_note( WP_REST_Request $request ): WP_REST_Response {
		$ok = $this->notes_service->update( (int) $request['id'], (array) $request->get_json_params() );
		return new WP_REST_Response( array( 'ok' => $ok ) );
	}

	public function save_income( WP_REST_Request $request ) {
		$month_key = sanitize_text_field( (string) $request->get_param( 'month_key' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_key ) ) {
			return new WP_Error( 'ccf_invalid_month_key', 'month_key debe tener formato YYYY-MM.', array( 'status' => 400 ) );
		}
		$user_id = (int) $request->get_param( 'user_id' );
		$amount  = (float) $request->get_param( 'amount' );
		if ( $user_id <= 0 || $amount <= 0 ) {
			return new WP_Error( 'ccf_invalid_payload', 'Debe enviar user_id válido y amount > 0.', array( 'status' => 400 ) );
		}
		$id = $this->incomes_repository->upsert( $month_key, $user_id, $amount, sanitize_text_field( (string) $request->get_param( 'notes' ) ), sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'confirmed' ) ) );
		$this->audit_log_service->log( 'monthly_income_changed', 'monthly_income', $id, array( 'month_key' => $month_key, 'user_id' => $user_id, 'amount' => $amount ) );
		return new WP_REST_Response( array( 'income_id' => $id ), 201 );
	}

	public function run_allocation( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->allocation_service->run( (string) $request->get_param( 'month_key' ), $request->get_param( 'separation_percent' ) ? (float) $request->get_param( 'separation_percent' ) : null );
		return new WP_REST_Response( $result );
	}

	public function upload_attachment( WP_REST_Request $request ): WP_REST_Response {
		$files = $request->get_file_params();
		if ( empty( $files ) ) {
			return new WP_REST_Response( array( 'error' => 'Archivo requerido.' ), 400 );
		}
		if ( ! empty( $files['file']['name'] ) ) {
			$result = $this->attachments_service->create_from_upload( (int) $request['id'], $files['file'], (string) $request->get_param( 'document_type' ) );
			$status = isset( $result['error'] ) ? 400 : 201;
			return new WP_REST_Response( $result, $status );
		}

		$normalized = array();
		foreach ( $files['files']['name'] as $index => $name ) {
			$normalized[] = array(
				'name'     => $name,
				'type'     => $files['files']['type'][ $index ],
				'tmp_name' => $files['files']['tmp_name'][ $index ],
				'error'    => $files['files']['error'][ $index ],
				'size'     => $files['files']['size'][ $index ],
			);
		}
		return new WP_REST_Response( array( 'data' => $this->attachments_service->create_many_from_uploads( (int) $request['id'], $normalized, (string) $request->get_param( 'document_type' ) ) ), 201 );
	}
}
