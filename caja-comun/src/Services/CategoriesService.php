<?php

namespace CCF\Services;

use CCF\Repositories\CategoriesRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CategoriesService {
	public function __construct( private CategoriesRepository $categories_repository, private AuditLogService $audit_log_service ) {}

	public function create( array $data ) {
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'ccf_invalid_category', 'name es obligatorio.', array( 'status' => 400 ) );
		}

		$existing = $this->categories_repository->find_by_name( (string) $data['name'] );
		if ( $existing ) {
			$existing_id = (int) $existing['id'];
			if ( ! empty( $existing['active'] ) ) {
				return $existing_id;
			}

			$this->categories_repository->set_active( $existing_id, true );
			$this->audit_log_service->log( 'category_reactivated', 'category', $existing_id, $data );

			return $existing_id;
		}

		$id = $this->categories_repository->save( $data );
		if ( $id <= 0 ) {
			return new WP_Error( 'ccf_category_not_saved', 'No se pudo guardar la categoría. Revisa si ya existe una categoría con ese nombre.', array( 'status' => 400 ) );
		}
		$this->audit_log_service->log( 'category_created', 'category', $id, $data );
		return $id;
	}

	public function update( int $id, array $data ) {
		if ( ! $this->categories_repository->find( $id ) ) {
			return new WP_Error( 'ccf_not_found', 'Categoría no encontrada.', array( 'status' => 404 ) );
		}
		$this->categories_repository->save( array_merge( $data, array( 'id' => $id ) ) );
		$this->audit_log_service->log( 'category_updated', 'category', $id, $data );
		return true;
	}

	public function delete( int $id ): bool {
		$deleted = $this->categories_repository->delete( $id );
		if ( $deleted ) {
			$this->audit_log_service->log( 'category_deleted', 'category', $id );
		}
		return $deleted;
	}
}
