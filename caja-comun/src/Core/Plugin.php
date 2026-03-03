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
use CCF\Repositories\SettingsRepository;
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public function run(): void {
		$database_manager         = new DatabaseManager();
		$accounts_repository      = new AccountsRepository( $database_manager );
		$categories_repository    = new CategoriesRepository( $database_manager );
		$incomes_repository       = new MonthlyIncomesRepository( $database_manager );
		$transactions_repository  = new TransactionsRepository( $database_manager );
		$settings_repository      = new SettingsRepository( $database_manager );
		$audit_log_service        = new AuditLogService( $database_manager );
		$attachments_service      = new AttachmentsService( $database_manager, $audit_log_service );
		$notes_service            = new NotesService( $database_manager, $audit_log_service );
		$accounts_service         = new AccountsService( $accounts_repository, $audit_log_service );
		$categories_service       = new CategoriesService( $categories_repository, $audit_log_service );
		$transactions_service     = new TransactionsService( $transactions_repository, $audit_log_service );
		$review_service           = new ReviewService( $transactions_repository );
		$charts_service           = new ChartsService( $incomes_repository, $transactions_repository, $database_manager );
		$allocation_service       = new MonthlyAllocationService( $accounts_repository, $incomes_repository, $transactions_repository, $database_manager, $audit_log_service );
		$dashboard_service        = new DashboardService( $incomes_repository, $transactions_repository, $database_manager, $charts_service );
		$admin_menu              = new AdminMenu( $dashboard_service, $incomes_repository, $allocation_service, $accounts_repository, $categories_repository, $transactions_repository, $attachments_service, $notes_service, $review_service, $settings_repository );
		$admin_assets            = new AdminAssets();
		$shortcodes              = new Shortcodes( $dashboard_service );
		$routes                  = new Routes( $accounts_repository, $categories_repository, $incomes_repository, $transactions_repository, $allocation_service, $dashboard_service, $attachments_service, $notes_service, $review_service, $charts_service, $accounts_service, $categories_service, $transactions_service, $audit_log_service );

		add_action( 'admin_menu', array( $admin_menu, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_assets, 'enqueue' ) );
		add_action( 'init', array( $shortcodes, 'register' ) );
		add_action( 'rest_api_init', array( $routes, 'register' ) );
	}
}
