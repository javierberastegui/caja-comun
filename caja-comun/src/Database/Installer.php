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
				description TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				display_order INT NOT NULL DEFAULT 0,
				is_visible TINYINT(1) NOT NULL DEFAULT 1,
				allow_manual TINYINT(1) NOT NULL DEFAULT 1,
				monthly_process TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug),
				KEY idx_type_status (type, status)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'categories' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slug VARCHAR(80) NOT NULL,
				name VARCHAR(120) NOT NULL,
				description TEXT NULL,
				color VARCHAR(20) NULL,
				icon VARCHAR(80) NULL,
				parent_id BIGINT UNSIGNED NULL,
				display_order INT NOT NULL DEFAULT 0,
				active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug),
				KEY idx_parent_id (parent_id)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'monthly_incomes' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month_key CHAR(7) NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				amount DECIMAL(14,2) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
				notes TEXT NULL,
				allocation_id BIGINT UNSIGNED NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY unique_month_user (month_key, user_id),
				KEY idx_month_key (month_key),
				KEY idx_status (status)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'transactions' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				month_key CHAR(7) NOT NULL,
				type VARCHAR(40) NOT NULL,
				source_account_id BIGINT UNSIGNED NULL,
				destination_account_id BIGINT UNSIGNED NULL,
				category_id BIGINT UNSIGNED NULL,
				subcategory_id BIGINT UNSIGNED NULL,
				amount DECIMAL(14,2) NOT NULL,
				currency CHAR(3) NOT NULL DEFAULT 'EUR',
				transaction_date DATE NOT NULL,
				accounting_date DATE NULL,
				description VARCHAR(190) NULL,
				quick_note VARCHAR(190) NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'posted',
				reviewed TINYINT(1) NOT NULL DEFAULT 0,
				reconciled TINYINT(1) NOT NULL DEFAULT 0,
				flagged TINYINT(1) NOT NULL DEFAULT 0,
				auto_generated TINYINT(1) NOT NULL DEFAULT 0,
				reference VARCHAR(120) NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY idx_month_key (month_key),
				KEY idx_type (type),
				KEY idx_reviewed (reviewed),
				KEY idx_status (status),
				KEY idx_transaction_date (transaction_date),
				KEY idx_source_account (source_account_id),
				KEY idx_destination_account (destination_account_id),
				KEY idx_category (category_id)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'notes' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				transaction_id BIGINT UNSIGNED NOT NULL,
				note_type VARCHAR(20) NOT NULL DEFAULT 'internal',
				content LONGTEXT NOT NULL,
				pending_review TINYINT(1) NOT NULL DEFAULT 0,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY idx_transaction_id (transaction_id),
				KEY idx_pending_review (pending_review)
			) $charset_collate;",
			"CREATE TABLE {$this->database_manager->table( 'transaction_attachments' )} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				transaction_id BIGINT UNSIGNED NOT NULL,
				attachment_id BIGINT UNSIGNED NOT NULL,
				document_type VARCHAR(20) NOT NULL,
				mime_type VARCHAR(120) NOT NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY idx_transaction_id (transaction_id),
				KEY idx_attachment_id (attachment_id)
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
		update_option( 'ccf_version', CCF_VERSION );
	}

	private function seed_defaults(): void {
		global $wpdb;
		$now            = $this->database_manager->now();
		$accounts_table = $this->database_manager->table( 'accounts' );
		$settings_table = $this->database_manager->table( 'settings' );
		$categories_table = $this->database_manager->table( 'categories' );

		$default_accounts = array(
			array( 'slug' => 'cuenta-comun', 'name' => 'Cuenta común', 'type' => 'common', 'display_order' => 1 ),
			array( 'slug' => 'cuenta-personal-usuario-1', 'name' => 'Cuenta personal Usuario 1', 'type' => 'personal', 'display_order' => 2 ),
			array( 'slug' => 'cuenta-personal-usuario-2', 'name' => 'Cuenta personal Usuario 2', 'type' => 'personal', 'display_order' => 3 ),
			array( 'slug' => 'cuenta-ahorro', 'name' => 'Cuenta ahorro opcional', 'type' => 'savings', 'display_order' => 4 ),
			array( 'slug' => 'cuenta-ajuste', 'name' => 'Cuenta ajuste opcional', 'type' => 'adjustment', 'display_order' => 5 ),
		);

		foreach ( $default_accounts as $account ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$accounts_table} WHERE slug = %s", $account['slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$accounts_table,
					array_merge(
						$account,
						array(
							'status'          => 'active',
							'is_visible'      => 1,
							'allow_manual'    => 1,
							'monthly_process' => 1,
							'created_at'      => $now,
							'updated_at'      => $now,
						)
					)
				);
			}
		}

		$default_categories = array( 'Supermercado', 'Hogar', 'Transporte', 'Gasolina', 'Hijos', 'Salud', 'Ocio', 'Suscripciones', 'Formación', 'Deuda', 'Ahorro', 'Ajuste', 'Imprevistos', 'Ropa', 'Tecnología' );
		foreach ( $default_categories as $index => $name ) {
			$slug = sanitize_title( $name );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$categories_table} WHERE slug = %s", $slug ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$categories_table,
					array(
						'slug'          => $slug,
						'name'          => $name,
						'color'         => '#2271b1',
						'icon'          => 'money-alt',
						'display_order' => $index + 1,
						'active'        => 1,
						'created_at'    => $now,
						'updated_at'    => $now,
					)
				);
			}
		}

		$defaults = array(
			'ccf_separation_percent'   => '10.00',
			'ccf_residue_strategy'     => 'to_common_budget',
			'enable_transactions_ui'   => '1',
			'enable_accounts_ui'       => '1',
			'enable_categories_ui'     => '1',
			'enable_attachments_ui'    => '1',
			'enable_review_ui'         => '1',
			'ccf_enable_frontend_app'    => '1',
			'ccf_frontend_session_hours' => '12',
			'ccf_frontend_max_attempts'  => '5',
			'ccf_frontend_block_minutes' => '15',
			'ccf_frontend_profile_pin_enabled' => '0',
			'ccf_frontend_profile_a_name' => 'Perfil A',
			'ccf_frontend_profile_b_name' => 'Perfil B',
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
