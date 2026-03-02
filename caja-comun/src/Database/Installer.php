<?php

namespace CCF\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {
	private DatabaseManager $database_manager;

	public function __construct() {
		$this->database_manager = new DatabaseManager();
	}

	public function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = array(
			"CREATE TABLE {$this->database_manager->table( 'accounts' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slug VARCHAR(80) NOT NULL,
				name VARCHAR(120) NOT NULL,
				type VARCHAR(60) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'monthly_incomes' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month_key CHAR(7) NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				amount DECIMAL(14,2) NOT NULL,
				notes TEXT NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY unique_month_user (month_key, user_id),
				KEY idx_month_key (month_key)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'transactions' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month_key CHAR(7) NOT NULL,
				type VARCHAR(40) NOT NULL,
				account_id BIGINT UNSIGNED NOT NULL,
				counterparty_account_id BIGINT UNSIGNED NULL,
				category_id BIGINT UNSIGNED NULL,
				amount DECIMAL(14,2) NOT NULL,
				direction VARCHAR(20) NOT NULL,
				description VARCHAR(190) NULL,
				auto_generated TINYINT(1) NOT NULL DEFAULT 0,
				reference VARCHAR(120) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY idx_month_key (month_key),
				KEY idx_account_id (account_id)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'monthly_allocations' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month_key CHAR(7) NOT NULL,
				status VARCHAR(30) NOT NULL,
				income_total DECIMAL(14,2) NOT NULL,
				separated_total DECIMAL(14,2) NOT NULL,
				separated_user_1 DECIMAL(14,2) NOT NULL,
				separated_user_2 DECIMAL(14,2) NOT NULL,
				common_budget DECIMAL(14,2) NOT NULL,
				separation_percent DECIMAL(5,2) NOT NULL,
				residue_strategy VARCHAR(40) NOT NULL DEFAULT 'to_common_budget',
				run_by BIGINT UNSIGNED NULL,
				run_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY unique_month_key (month_key)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'categories' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slug VARCHAR(80) NOT NULL,
				name VARCHAR(120) NOT NULL,
				type VARCHAR(30) NOT NULL DEFAULT 'expense',
				active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'settings' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				setting_key VARCHAR(120) NOT NULL,
				setting_value LONGTEXT NULL,
				autoload TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'audit_log' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				event_type VARCHAR(120) NOT NULL,
				entity_type VARCHAR(80) NOT NULL,
				entity_id BIGINT UNSIGNED NULL,
				payload LONGTEXT NULL,
				performed_by BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY idx_entity (entity_type, entity_id)
			) $charset_collate;",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		$this->seed_defaults();
		update_option( 'ccf_plugin_version', CCF_PLUGIN_VERSION );
	}

	private function seed_defaults(): void {
		global $wpdb;
		$now           = $this->database_manager->now();
		$accounts_table = $this->database_manager->table( 'accounts' );
		$settings_table = $this->database_manager->table( 'settings' );

		$default_accounts = array(
			array('slug' => 'common_budget', 'name' => 'Presupuesto Común', 'type' => 'common_budget'),
			array('slug' => 'separated_pool', 'name' => 'Fondo Separado', 'type' => 'separated_pool'),
			array('slug' => 'user_1_separated', 'name' => 'Separado Usuario 1', 'type' => 'separated_user'),
			array('slug' => 'user_2_separated', 'name' => 'Separado Usuario 2', 'type' => 'separated_user'),
		);

		foreach ( $default_accounts as $account ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$accounts_table} WHERE slug = %s", $account['slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$accounts_table,
					array_merge(
						$account,
						array(
							'status'     => 'active',
							'created_at' => $now,
							'updated_at' => $now,
						)
					)
				);
			}
		}

		$defaults = array(
			'ccf_separation_percent' => '10.00',
			'ccf_residue_strategy'   => 'to_common_budget',
		);

		foreach ( $defaults as $setting_key => $setting_value ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$settings_table} WHERE setting_key = %s", $setting_key ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$settings_table,
					array(
						'setting_key'   => $setting_key,
						'setting_value' => $setting_value,
						'autoload'      => 1,
						'created_at'    => $now,
						'updated_at'    => $now,
					)
				);
			}
		}
	}
}
