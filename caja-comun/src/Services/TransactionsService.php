<?php

namespace CCF\Services;

use CCF\Repositories\TransactionsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransactionsService {
	private array $allowed_types = array( 'income', 'expense', 'transfer', 'adjustment', 'allocation' );

	public function __construct( private TransactionsRepository $transactions_repository, private AuditLogService $audit_log_service ) {}

	public function create( array $data ) {
		if ( empty( $data['amount'] ) || (float) $data['amount'] <= 0 ) {
			return new WP_Error( 'ccf_invalid_transaction', 'amount debe ser mayor a 0.', array( 'status' => 400 ) );
		}
		$type = sanitize_key( (string) ( $data['type'] ?? 'expense' ) );
		if ( ! in_array( $type, $this->allowed_types, true ) ) {
			return new WP_Error( 'ccf_invalid_transaction_type', 'type no permitido.', array( 'status' => 400 ) );
		}
		$data['type'] = $type;
		$id           = $this->transactions_repository->insert( $data );
		$this->audit_log_service->log( 'transaction_created', 'transaction', $id, $data );
		return $id;
	}

	public function update( int $id, array $data ) {
		if ( ! $this->transactions_repository->find( $id ) ) {
			return new WP_Error( 'ccf_not_found', 'Movimiento no encontrado.', array( 'status' => 404 ) );
		}
		if ( isset( $data['type'] ) && ! in_array( sanitize_key( $data['type'] ), $this->allowed_types, true ) ) {
			return new WP_Error( 'ccf_invalid_transaction_type', 'type no permitido.', array( 'status' => 400 ) );
		}
		$ok = $this->transactions_repository->update( $id, $data );
		if ( $ok ) {
			$this->audit_log_service->log( 'transaction_updated', 'transaction', $id, $data );
		}
		return $ok;
	}

	public function delete( int $id ): bool {
		$ok = $this->transactions_repository->delete( $id );
		if ( $ok ) {
			$this->audit_log_service->log( 'transaction_deleted', 'transaction', $id );
		}
		return $ok;
	}
}
