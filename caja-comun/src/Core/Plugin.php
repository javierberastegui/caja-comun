<?php

namespace CCF\Core;

use CCF\Admin\AdminAssets;
use CCF\Admin\AdminMenu;
use CCF\Database\DatabaseManager;
use CCF\Frontend\Shortcodes;
use CCF\REST\Routes;
use CCF\Repositories\AccountsRepository;
use CCF\Repositories\MonthlyIncomesRepository;
use CCF\Repositories\TransactionsRepository;
use CCF\Services\DashboardService;
use CCF\Services\MonthlyAllocationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public function run(): void {
		$database_manager          = new DatabaseManager();
		$accounts_repository       = new AccountsRepository( $database_manager );
		$incomes_repository        = new MonthlyIncomesRepository( $database_manager );
		$transactions_repository   = new TransactionsRepository( $database_manager );
		$allocation_service        = new MonthlyAllocationService( $accounts_repository, $incomes_repository, $transactions_repository, $database_manager );
		$dashboard_service         = new DashboardService( $incomes_repository, $transactions_repository, $database_manager );
		$admin_menu                = new AdminMenu( $dashboard_service, $incomes_repository, $allocation_service );
		$admin_assets              = new AdminAssets();
		$shortcodes                = new Shortcodes( $dashboard_service );
		$routes                    = new Routes( $accounts_repository, $incomes_repository, $allocation_service, $dashboard_service );

		add_action( 'admin_menu', array( $admin_menu, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_assets, 'enqueue' ) );
		add_action( 'init', array( $shortcodes, 'register' ) );
		add_action( 'rest_api_init', array( $routes, 'register' ) );
	}
}
