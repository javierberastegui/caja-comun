<?php

namespace CCF\Services;

use CCF\Repositories\TransactionsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewService {
	private TransactionsRepository $transactions_repository;

	public function __construct( TransactionsRepository $transactions_repository ) {
		$this->transactions_repository = $transactions_repository;
	}

	public function queue( string $month_key ): array {
		return $this->transactions_repository->review_queue( $month_key );
	}
}
