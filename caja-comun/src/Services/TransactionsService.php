<?php

namespace CCF\Services;

use CCF\Repositories\AccountsRepository;
use CCF\Repositories\CategoriesRepository;
use CCF\Repositories\TransactionsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransactionsService {
	private array $allowed_types = array( 'income', 'expense', 'transfer', 'adjustment', 'allocation' );

	public function __construct(
		private TransactionsRepository $transactions_repository,
		private AuditLogService $audit_log_service,
		private AccountsRepository $accounts_repository,
		private CategoriesRepository $categories_repository
	) {}

	public function create( array $data ) {
		$validation = $this->validate_payload( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$data = $validation;
		$id   = $this->transactions_repository->insert( $data );
		if ( $id <= 0 ) {
			return new WP_Error( 'ccf_transaction_not_saved', 'No se pudo guardar el movimiento.', array( 'status' => 400 ) );
		}
		$this->audit_log_service->log( 'transaction_created', 'transaction', $id, $data );
		return $id;
	}

	public function update( int $id, array $data ) {
		$existing = $this->transactions_repository->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
		}
		$normalized = $this->validate_payload( array_merge( $existing, $data ) );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$ok = $this->transactions_repository->update( $id, $normalized );
		if ( $ok ) {
			$this->audit_log_service->log( 'transaction_updated', 'transaction', $id, $normalized );
		}
		return $ok;
	}

	private function validate_payload( array $data ) {
		$type = sanitize_key( (string) ( $data['type'] ?? 'expense' ) );
		if ( ! in_array( $type, $this->allowed_types, true ) ) {
			return new WP_Error( 'ccf_invalid_transaction_type', 'Tipo de movimiento no permitido.', array( 'status' => 400 ) );
		}
		if ( empty( $data['transaction_date'] ) ) {
			return new WP_Error( 'ccf_invalid_transaction_date', 'Debes seleccionar una fecha.', array( 'status' => 400 ) );
		}
		if ( empty( $data['description'] ) ) {
			return new WP_Error( 'ccf_invalid_description', 'Debes indicar un concepto.', array( 'status' => 400 ) );
		}
		if ( empty( $data['amount'] ) || (float) $data['amount'] <= 0 ) {
			return new WP_Error( 'ccf_invalid_amount', 'El importe no es válido.', array( 'status' => 400 ) );
		}

		$category_required = in_array( $type, array( 'income', 'expense' ), true );
		$category_id       = ! empty( $data['category_id'] ) ? (int) $data['category_id'] : 0;
		$account_id        = ! empty( $data['source_account_id'] ) ? (int) $data['source_account_id'] : 0;

		if ( $account_id <= 0 ) {
			$common_account = $this->accounts_repository->find_first_active_common();
			if ( ! $common_account ) {
				return new WP_Error( 'ccf_missing_common_account', 'No hay una cuenta común activa para registrar movimientos.', array( 'status' => 400 ) );
			}
			$account_id = (int) $common_account['id'];
		}

		$account = $this->accounts_repository->find( $account_id );
		if ( ! $account ) {
			return new WP_Error( 'ccf_account_not_found', 'La cuenta seleccionada no existe.', array( 'status' => 400 ) );
		}
		if ( 'common' !== (string) ( $account['type'] ?? '' ) ) {
			return new WP_Error( 'ccf_only_common_accounts', 'Solo se permiten movimientos en cuentas comunes.', array( 'status' => 400 ) );
		}
		if ( 'active' !== (string) ( $account['status'] ?? '' ) ) {
			return new WP_Error( 'ccf_inactive_account', 'La cuenta común seleccionada está inactiva.', array( 'status' => 400 ) );
		}

		if ( $category_required && $category_id <= 0 ) {
			return new WP_Error( 'ccf_missing_category', 'Debes seleccionar una categoría.', array( 'status' => 400 ) );
		}
		if ( $category_id > 0 && ! $this->categories_repository->find( $category_id ) ) {
			return new WP_Error( 'ccf_category_not_found', 'La categoría seleccionada no existe.', array( 'status' => 400 ) );
		}

		$data['type']              = $type;
		$data['source_account_id'] = $account_id;
		$data['category_id']       = $category_id > 0 ? $category_id : null;

		return $data;
	}

	public function delete( int $id ): bool {
		$ok = $this->transactions_repository->delete( $id );
		if ( $ok ) {
			$this->audit_log_service->log( 'transaction_deleted', 'transaction', $id );
		}
		return $ok;
	}
}
