<?php

declare(strict_types=1);

namespace EcoPro\Core;

final class Installer
{
    public static function activate(): void
    {
        self::createTables();
        self::addCapabilities();
        Cron::schedule();
        Page_Provisioner::ensurePage();
        flush_rewrite_rules();
    }

    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t_tx = $wpdb->prefix . 'eco_transactions';
        $t_cat = $wpdb->prefix . 'eco_categories';
        $t_bud = $wpdb->prefix . 'eco_budgets';

        $sql_categories = "CREATE TABLE {$t_cat} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            kind ENUM('income','expense','mixed') NOT NULL DEFAULT 'expense',
            color VARCHAR(20) NOT NULL DEFAULT '#2271b1',
            icon VARCHAR(60) NULL,
            parent_id BIGINT UNSIGNED NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY kind (kind),
            KEY parent_id (parent_id)
        ) {$charset};";

        $sql_budgets = "CREATE TABLE {$t_bud} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            period ENUM('monthly','quarterly','yearly','custom') NOT NULL DEFAULT 'monthly',
            starts_on DATE NOT NULL,
            ends_on DATE NULL,
            amount_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            alert_threshold DECIMAL(5,2) NOT NULL DEFAULT 80.00,
            category_id BIGINT UNSIGNED NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY period (period),
            KEY category_id (category_id),
            KEY status (status)
        ) {$charset};";

        $sql_transactions = "CREATE TABLE {$t_tx} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            budget_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            type ENUM('income','expense','transfer','debt_payment') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            txn_date DATE NOT NULL,
            description VARCHAR(255) NULL,
            reference VARCHAR(100) NULL,
            status ENUM('planned','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
            meta LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY budget_id (budget_id),
            KEY category_id (category_id),
            KEY type (type),
            KEY txn_date (txn_date),
            KEY status (status),
            KEY created_by (created_by)
        ) {$charset};";

        dbDelta($sql_categories);
        dbDelta($sql_budgets);
        dbDelta($sql_transactions);
    }

    private static function addCapabilities(): void
    {
        $role = get_role('administrator');
        if ($role && !$role->has_cap('manage_finance')) {
            $role->add_cap('manage_finance');
        }
    }
}
