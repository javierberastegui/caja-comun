<?php

namespace CCF\Core;

use CCF\Admin\AdminAssets;
use CCF\Admin\AdminMenu;
use CCF\Database\DatabaseManager;
use CCF\Frontend\Shortcodes;
use CCF\REST\Routes;
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public function run(): void {
		$database_manager = new DatabaseManager();
		$accounts_repository = new AccountsRepository( $database_manager );
		$categories_repository = new CategoriesRepository( $database_manager );
		$incomes_repository = new MonthlyIncomesRepository( $database_manager );
		$transactions_repository = new TransactionsRepository( $database_manager );
		$attachments_service = new AttachmentsService( $database_manager );
		$notes_service = new NotesService( $database_manager );
		$review_service = new ReviewService( $transactions_repository );
		$charts_service = new ChartsService( $incomes_repository, $transactions_repository, $database_manager );
		$allocation_service = new MonthlyAllocationService( $accounts_repository, $incomes_repository, $transactions_repository, $database_manager );
		$dashboard_service = new DashboardService( $incomes_repository, $transactions_repository, $database_manager, $charts_service );
		$admin_menu = new AdminMenu( $dashboard_service, $incomes_repository, $allocation_service, $accounts_repository, $categories_repository, $transactions_repository, $attachments_service, $notes_service, $review_service );
		$admin_assets = new AdminAssets();
		$shortcodes = new Shortcodes( $dashboard_service );
		$routes = new Routes( $accounts_repository, $categories_repository, $incomes_repository, $transactions_repository, $allocation_service, $dashboard_service, $attachments_service, $notes_service, $review_service, $charts_service );

		add_action( 'admin_menu', array( $admin_menu, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_assets, 'enqueue' ) );
		add_action( 'init', array( $shortcodes, 'register' ) );
		add_action( 'rest_api_init', array( $routes, 'register' ) );
	}
}
