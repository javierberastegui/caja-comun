<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Plugin
{
    private FPI_Admin $admin;

    public function __construct()
    {
        $this->admin = new FPI_Admin();
    }

    public function run(): void
    {
        $this->maybe_upgrade();
        $this->admin->hooks();
        add_action('wp_login', [$this, 'log_login'], 10, 2);
        add_action('clear_auth_cookie', [$this, 'log_logout']);
        add_action('user_register', [$this, 'sync_new_user']);
    }

    private function maybe_upgrade(): void
    {
        $installedVersion = (string) get_option('fpi_version', '0.0.0');

        if (version_compare($installedVersion, FPI_VERSION, '>=')) {
            return;
        }

        FPI_DB::create_tables();
        FPI_Roles::seed_defaults();
        FPI_Permissions::seed_defaults();
        FPI_Permissions::seed_default_role_permissions();
        FPI_Storage::seed_options();
        update_option('fpi_version', FPI_VERSION);
    }

    public function log_login(string $userLogin, WP_User $user): void
    {
        FPI_Roles::ensure_employee_record((int) $user->ID);
        FPI_Audit::log('login', 'auth', sprintf('Inicio de sesión: %s', $userLogin), 'user', (int) $user->ID);
    }

    public function log_logout(): void
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            FPI_Audit::log('logout', 'auth', 'Cierre de sesión', 'user', $userId);
        }
    }

    public function sync_new_user(int $userId): void
    {
        FPI_Roles::ensure_employee_record($userId);
        FPI_Audit::log('user_register', 'users', sprintf('Usuario sincronizado: %d', $userId), 'user', $userId);
    }
}
