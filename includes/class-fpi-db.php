<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_DB
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . FPI_TABLE_PREFIX . $name;
    }

    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $sql = [];

        $sql[] = "CREATE TABLE " . self::table('employees') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            employee_code VARCHAR(100) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('roles') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(190) NOT NULL,
            is_base_role TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('permissions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(190) NOT NULL,
            group_name VARCHAR(100) NOT NULL DEFAULT 'general',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('user_roles') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            assigned_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_role (user_id, role_id),
            KEY role_id (role_id)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('user_permissions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            permission_slug VARCHAR(100) NOT NULL,
            grant_type VARCHAR(20) NOT NULL DEFAULT 'allow',
            assigned_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_permission (user_id, permission_slug, grant_type)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('role_permissions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            role_id BIGINT UNSIGNED NOT NULL,
            permission_slug VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY role_permission (role_id, permission_slug),
            KEY permission_slug (permission_slug)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('audit_logs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(100) NOT NULL,
            object_type VARCHAR(100) DEFAULT NULL,
            object_id BIGINT UNSIGNED DEFAULT NULL,
            description TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY module (module),
            KEY action (action)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('storage_items') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_slug VARCHAR(50) NOT NULL,
            title VARCHAR(190) NOT NULL,
            folder_name VARCHAR(190) DEFAULT NULL,
            employee_user_id BIGINT UNSIGNED DEFAULT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'synology',
            external_url TEXT DEFAULT NULL,
            storage_path VARCHAR(255) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            uploaded_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY module_slug (module_slug),
            KEY employee_user_id (employee_user_id)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('incidents') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            description TEXT DEFAULT NULL,
            type VARCHAR(100) NOT NULL DEFAULT 'general',
            priority VARCHAR(20) NOT NULL DEFAULT 'media',
            shift_label VARCHAR(20) DEFAULT NULL,
            incident_date DATE DEFAULT NULL,
            incident_time TIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'abierta',
            created_by BIGINT UNSIGNED DEFAULT NULL,
            assigned_to BIGINT UNSIGNED DEFAULT NULL,
            resolved_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by),
            KEY assigned_to (assigned_to),
            KEY incident_date (incident_date)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('estupe_movements') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            movement_date DATETIME NOT NULL,
            cn VARCHAR(50) NOT NULL,
            medicine_name VARCHAR(190) NOT NULL,
            movement_type VARCHAR(20) NOT NULL,
            initial_stock INT NOT NULL DEFAULT 0,
            final_stock INT NOT NULL DEFAULT 0,
            pharmacist_name VARCHAR(190) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY movement_date (movement_date),
            KEY cn (cn),
            KEY movement_type (movement_type)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('requests') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_type VARCHAR(50) NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            requested_by BIGINT UNSIGNED DEFAULT NULL,
            reviewed_by BIGINT UNSIGNED DEFAULT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            meta_json LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY request_type (request_type),
            KEY status (status),
            KEY requested_by (requested_by)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE " . self::table('notifications') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module VARCHAR(100) NOT NULL,
            title VARCHAR(190) NOT NULL,
            message TEXT DEFAULT NULL,
            related_id BIGINT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY module (module),
            KEY is_read (is_read)
        ) $charsetCollate;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
