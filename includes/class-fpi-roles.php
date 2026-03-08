<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Roles
{
    private const DEFAULT_ROLES = [
        ['slug' => 'superadmin_interno', 'name' => 'Superadmin interno', 'is_base_role' => 1],
        ['slug' => 'titular', 'name' => 'Titular', 'is_base_role' => 1],
        ['slug' => 'administracion', 'name' => 'Administración', 'is_base_role' => 1],
        ['slug' => 'farmaceutico', 'name' => 'Farmacéutico', 'is_base_role' => 1],
        ['slug' => 'tecnico_auxiliar', 'name' => 'Técnicos y auxiliares', 'is_base_role' => 1],
        ['slug' => 'transporte_limpieza', 'name' => 'Transporte y limpieza', 'is_base_role' => 1],
    ];

    private const LEGACY_HIDDEN_SLUGS = [
        'tecnico',
        'recepcion_pedidos',
        'gestor_documental',
        'aprobador_turnos',
        'responsable_estupes',
        'visor_contabilidad',
        'gestor_nominas',
    ];

    public static function seed_defaults(): void
    {
        global $wpdb;
        $table = FPI_DB::table('roles');

        foreach (self::DEFAULT_ROLES as $role) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $role['slug']));
            if (! $exists) {
                $wpdb->insert(
                    $table,
                    [
                        'slug' => $role['slug'],
                        'name' => $role['name'],
                        'is_base_role' => $role['is_base_role'],
                        'created_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%d', '%s']
                );
                continue;
            }

            $wpdb->update(
                $table,
                [
                    'name' => $role['name'],
                    'is_base_role' => $role['is_base_role'],
                ],
                ['slug' => $role['slug']],
                ['%s', '%d'],
                ['%s']
            );
        }
    }

    public static function get_default_role_slugs(): array
    {
        return array_map(static fn(array $role): string => $role['slug'], self::DEFAULT_ROLES);
    }

    public static function ensure_employee_record(int $userId): void
    {
        global $wpdb;
        $table = FPI_DB::table('employees');
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d", $userId));

        if (! $exists) {
            $wpdb->insert(
                $table,
                [
                    'user_id' => $userId,
                    'employee_code' => null,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
        }
    }

    public static function get_all(): array
    {
        global $wpdb;
        $table = FPI_DB::table('roles');
        $hidden = self::LEGACY_HIDDEN_SLUGS;
        $placeholders = implode(',', array_fill(0, count($hidden), '%s'));
        $sql = "SELECT * FROM {$table} WHERE slug NOT IN ({$placeholders}) ORDER BY is_base_role DESC, name ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $hidden), ARRAY_A) ?: [];
    }

    public static function get_by_slug(string $slug): ?array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM " . FPI_DB::table('roles') . " WHERE slug = %s LIMIT 1", sanitize_key($slug));
        $role = $wpdb->get_row($sql, ARRAY_A);
        return is_array($role) ? $role : null;
    }

    public static function assign_role(int $userId, int $roleId, ?int $assignedBy = null): bool
    {
        global $wpdb;
        self::ensure_employee_record($userId);

        $result = $wpdb->replace(
            FPI_DB::table('user_roles'),
            [
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_by' => $assignedBy,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s']
        );

        return $result !== false;
    }

    public static function get_user_roles(int $userId): array
    {
        global $wpdb;
        $tableUserRoles = FPI_DB::table('user_roles');
        $tableRoles = FPI_DB::table('roles');

        $sql = $wpdb->prepare(
            "SELECT r.*
             FROM {$tableUserRoles} ur
             INNER JOIN {$tableRoles} r ON r.id = ur.role_id
             WHERE ur.user_id = %d
             ORDER BY r.name ASC",
            $userId
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function user_has_role_slug(int $userId, string $slug): bool
    {
        foreach (self::get_user_roles($userId) as $role) {
            if (($role['slug'] ?? '') === $slug) {
                return true;
            }
        }

        return false;
    }

    public static function create_role(string $slug, string $name, bool $isBaseRole = false): bool
    {
        global $wpdb;

        $result = $wpdb->insert(
            FPI_DB::table('roles'),
            [
                'slug' => sanitize_key($slug),
                'name' => sanitize_text_field($name),
                'is_base_role' => $isBaseRole ? 1 : 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }
}
