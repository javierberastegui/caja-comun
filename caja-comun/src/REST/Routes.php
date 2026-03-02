<?php

namespace CCF\REST;

use CCF\Repositories\AccountsRepository;
use CCF\Repositories\CategoriesRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;
use CCF\Services\AttachmentsService;
use CCF\Services\ChartsService;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;
use CCF\Services\NotesService;
use CCF\Services\ReviewService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Routes {
	private const NAMESPACE = 'caja-comun/v1';

	public function __construct(private AccountsRepository $accounts_repository, private CategoriesRepository $categories_repository, private MonthlyIncomesRepository $incomes_repository, private TransactionsRepository $transactions_repository, private MonthlyAllocationService $allocation_service, private DashboardService $dashboard_service, private AttachmentsService $attachments_service, private NotesService $notes_service, private ReviewService $review_service, private ChartsService $charts_service) {}

	public function register(): void {
		register_rest_route( self::NAMESPACE, '/accounts', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->accounts_repository->get_all( $r->get_params() ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_account' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/accounts/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_account' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_account' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_account' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );

		register_rest_route( self::NAMESPACE, '/categories', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->categories_repository->list( $r->get_params() ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_category' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/categories/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_category' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_category' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_category' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );

		register_rest_route( self::NAMESPACE, '/monthly-incomes', array( array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->incomes_repository->list( $r->get_param( 'month_key' ), 100, $r->get_params() ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ), array( 'methods' => 'POST', 'callback' => array( $this, 'save_income' ), 'permission_callback' => array( $this, 'can_manage' ) ) ) );

		register_rest_route( self::NAMESPACE, '/transactions', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->transactions_repository->list( $r->get_params() ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_transaction' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_transaction' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_transaction' ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_transaction' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/review', array( 'methods' => 'POST', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'ok' => $this->transactions_repository->update( (int) $r['id'], array( 'reviewed' => (bool) $r->get_param( 'reviewed' ), 'flagged' => (bool) $r->get_param( 'flagged' ) ) ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/attachments', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->attachments_service->list_by_transaction( (int) $r['id'] ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'upload_attachment' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/attachments/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'ok' => $this->attachments_service->delete( (int) $r['id'] ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		register_rest_route( self::NAMESPACE, '/transactions/(?P<id>\d+)/notes', array(
			array( 'methods' => 'GET', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->notes_service->list( (int) $r['id'] ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_note' ), 'permission_callback' => array( $this, 'can_manage' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/notes/(?P<id>\d+)/pending', array( 'methods' => 'POST', 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'ok' => $this->notes_service->set_pending( (int) $r['id'], (bool) $r->get_param( 'is_pending' ) ) ) ), 'permission_callback' => array( $this, 'can_manage' ) ) );

		register_rest_route( self::NAMESPACE, '/monthly-allocations/preview', array( 'methods' => 'POST', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->allocation_service->preview( (string) $r->get_param( 'month_key' ), $r->get_param( 'separation_percent' ) ? (float) $r->get_param( 'separation_percent' ) : null ) ) ) );
		register_rest_route( self::NAMESPACE, '/monthly-allocations/run', array( 'methods' => 'POST', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->allocation_service->run( (string) $r->get_param( 'month_key' ), $r->get_param( 'separation_percent' ) ? (float) $r->get_param( 'separation_percent' ) : null ) ) ) );
		register_rest_route( self::NAMESPACE, '/dashboard/month-summary', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->dashboard_service->month_summary( (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/dashboard/charts', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( $this->charts_service->dashboard_charts( (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/dashboard/charts-data-ready', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'month_key' => (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ), 'charts' => $this->charts_service->dashboard_charts( (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ) ) ) ) ) );
		register_rest_route( self::NAMESPACE, '/review', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'can_manage' ), 'callback' => fn( WP_REST_Request $r ) => new WP_REST_Response( array( 'data' => $this->review_service->queue( (string) ( $r->get_param( 'month_key' ) ?: gmdate( 'Y-m' ) ) ) ) ) ) );
	}

	public function can_manage(): bool { return current_user_can( 'manage_options' ); }

	public function create_account( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params['name'] ) || empty( $params['type'] ) ) {
			return new WP_Error( 'ccf_invalid_account', 'name y type son obligatorios.', array( 'status' => 400 ) );
		}
		$id = $this->accounts_repository->save( $params );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}
	public function get_account( WP_REST_Request $request ) {
		$row = $this->accounts_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
	}
	public function update_account( WP_REST_Request $request ) {
		if ( ! $this->accounts_repository->find( (int) $request['id'] ) ) {
			return new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
		}
		$id = $this->accounts_repository->save( array_merge( $request->get_json_params(), array( 'id' => (int) $request['id'] ) ) );
		return new WP_REST_Response( array( 'id' => $id ) );
	}
	public function delete_account( WP_REST_Request $request ) {
		$deleted = $this->accounts_repository->delete( (int) $request['id'] );
		return $deleted ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
	}

	public function create_category( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params['name'] ) ) {
			return new WP_Error( 'ccf_invalid_category', 'name es obligatorio.', array( 'status' => 400 ) );
		}
		$id = $this->categories_repository->save( $params );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}
	public function get_category( WP_REST_Request $request ) {
		$row = $this->categories_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
	}
	public function update_category( WP_REST_Request $request ) {
		if ( ! $this->categories_repository->find( (int) $request['id'] ) ) {
			return new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
		}
		$id = $this->categories_repository->save( array_merge( $request->get_json_params(), array( 'id' => (int) $request['id'] ) ) );
		return new WP_REST_Response( array( 'id' => $id ) );
	}
	public function delete_category( WP_REST_Request $request ) {
		$deleted = $this->categories_repository->delete( (int) $request['id'] );
		return $deleted ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
	}

	public function create_transaction( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params['amount'] ) || (float) $params['amount'] <= 0 ) {
			return new WP_Error( 'ccf_invalid_transaction', 'amount debe ser mayor a 0.', array( 'status' => 400 ) );
		}
		$id = $this->transactions_repository->insert( $params );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}
	public function get_transaction( WP_REST_Request $request ) {
		$row = $this->transactions_repository->find( (int) $request['id'] );
		return $row ? new WP_REST_Response( $row ) : new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
	}
	public function update_transaction( WP_REST_Request $request ) {
		if ( ! $this->transactions_repository->find( (int) $request['id'] ) ) {
			return new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
		}
		$ok = $this->transactions_repository->update( (int) $request['id'], $request->get_json_params() );
		return new WP_REST_Response( array( 'ok' => $ok ) );
	}
	public function delete_transaction( WP_REST_Request $request ) {
		$ok = $this->transactions_repository->delete( (int) $request['id'] );
		return $ok ? new WP_REST_Response( array( 'ok' => true ) ) : new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
	}

	public function create_note( WP_REST_Request $request ) {
		$content = (string) $request->get_param( 'content' );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'ccf_invalid_note', 'content es obligatorio.', array( 'status' => 400 ) );
		}
		$id = $this->notes_service->add( (int) $request['id'], $content, (string) $request->get_param( 'note_type' ), (bool) $request->get_param( 'is_pending' ) );
		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	public function save_income( WP_REST_Request $request ) {
		$month_key = sanitize_text_field( (string) $request->get_param( 'month_key' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_key ) ) { return new WP_Error( 'ccf_invalid_month_key', 'month_key debe tener formato YYYY-MM.', array( 'status' => 400 ) ); }
		$user_id = (int) $request->get_param( 'user_id' );
		$amount = (float) $request->get_param( 'amount' );
		if ( $user_id <= 0 || $amount <= 0 ) { return new WP_Error( 'ccf_invalid_payload', 'Debe enviar user_id válido y amount > 0.', array( 'status' => 400 ) ); }
		$id = $this->incomes_repository->upsert( $month_key, $user_id, $amount, sanitize_text_field( (string) $request->get_param( 'notes' ) ), sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'confirmed' ) ) );
		return new WP_REST_Response( array( 'income_id' => $id ), 201 );
	}

	public function upload_attachment( WP_REST_Request $request ): WP_REST_Response {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Archivo requerido en file.' ), 400 );
		}
		$result = $this->attachments_service->create_from_upload( (int) $request['id'], $files['file'], (string) $request->get_param( 'document_type' ) );
		$status = isset( $result['error'] ) ? 400 : 201;
		return new WP_REST_Response( $result, $status );
	}
}
