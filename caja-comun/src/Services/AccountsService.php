<?php

namespace CCF\Services;

use CCF\Repositories\AccountsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccountsService {
	public function __construct( private AccountsRepository $accounts_repository, private AuditLogService $audit_log_service ) {}

	public function create( array $data ) {
		if ( empty( $data['name'] ) || empty( $data['type'] ) ) {
			return new WP_Error( 'ccf_invalid_account', 'name y type son obligatorios.', array( 'status' => 400 ) );
		}
		$id = $this->accounts_repository->save( $data );
		if ( $id <= 0 ) {
			return new WP_Error( 'ccf_account_not_saved', 'No se pudo guardar la cuenta. Revisa si ya existe una cuenta con ese nombre.', array( 'status' => 400 ) );
		}
		$this->audit_log_service->log( 'account_created', 'account', $id, $data );
		return $id;
	}

	public function update( int $id, array $data ) {
		if ( ! $this->accounts_repository->find( $id ) ) {
			return new WP_Error( 'ccf_not_found', 'Cuenta no encontrada.', array( 'status' => 404 ) );
		}
		$this->accounts_repository->save( array_merge( $data, array( 'id' => $id ) ) );
		$this->audit_log_service->log( 'account_updated', 'account', $id, $data );
		return true;
	}

	public function delete( int $id ): bool {
		$deleted = $this->accounts_repository->delete( $id );
		if ( $deleted ) {
			$this->audit_log_service->log( 'account_deleted', 'account', $id );
		}
		return $deleted;
	}
}
