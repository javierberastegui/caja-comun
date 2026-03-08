<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Activator
{
    public static function activate(): void
    {
        FPI_DB::create_tables();
        FPI_Roles::seed_defaults();
        FPI_Permissions::seed_defaults();
        FPI_Permissions::seed_default_role_permissions();
        self::sync_wp_users_as_employees();
        FPI_Storage::seed_options();
        update_option('fpi_version', FPI_VERSION);
    }

    private static function sync_wp_users_as_employees(): void
    {
        $users = get_users(['fields' => 'ids']);

        foreach ($users as $userId) {
            FPI_Roles::ensure_employee_record((int) $userId);
        }
    }
}
