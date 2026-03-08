<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Permissions
{
    public static function seed_defaults(): void
    {
        $permissions = [
            ['slug' => 'ver_dashboard', 'name' => 'Ver dashboard', 'group_name' => 'general'],
            ['slug' => 'ver_documentos', 'name' => 'Ver documentos', 'group_name' => 'documentos'],
            ['slug' => 'ver_contabilidad', 'name' => 'Ver contabilidad', 'group_name' => 'documentos'],
            ['slug' => 'ver_nominas_propias', 'name' => 'Ver nóminas propias', 'group_name' => 'nominas'],
            ['slug' => 'ver_nominas_todas', 'name' => 'Ver todas las nóminas', 'group_name' => 'nominas'],
            ['slug' => 'firmar_documentos', 'name' => 'Firmar documentos', 'group_name' => 'documentos'],
            ['slug' => 'ver_horarios', 'name' => 'Ver horarios', 'group_name' => 'horarios'],
            ['slug' => 'solicitar_cambio_turno', 'name' => 'Solicitar cambio de turno', 'group_name' => 'horarios'],
            ['slug' => 'aprobar_cambio_turno', 'name' => 'Aprobar cambio de turno', 'group_name' => 'horarios'],
            ['slug' => 'solicitar_vacaciones', 'name' => 'Solicitar vacaciones', 'group_name' => 'horarios'],
            ['slug' => 'aprobar_vacaciones', 'name' => 'Aprobar vacaciones', 'group_name' => 'horarios'],
            ['slug' => 'ver_estupes', 'name' => 'Ver estupefacientes', 'group_name' => 'estupes'],
            ['slug' => 'ver_almacenamiento', 'name' => 'Ver almacenamiento', 'group_name' => 'almacenamiento'],
            ['slug' => 'gestionar_almacenamiento', 'name' => 'Configurar almacenamiento', 'group_name' => 'almacenamiento'],
            ['slug' => 'gestionar_nominas', 'name' => 'Gestionar nóminas', 'group_name' => 'nominas'],
            ['slug' => 'gestionar_contabilidad', 'name' => 'Gestionar contabilidad', 'group_name' => 'documentos'],
            ['slug' => 'gestionar_documentos', 'name' => 'Gestionar documentos', 'group_name' => 'documentos'],
            ['slug' => 'gestionar_estupes', 'name' => 'Gestionar estupefacientes', 'group_name' => 'estupes'],
            ['slug' => 'subir_facturas', 'name' => 'Subir facturas', 'group_name' => 'documentos'],
            ['slug' => 'subir_documentos', 'name' => 'Subir documentos', 'group_name' => 'documentos'],
            ['slug' => 'crear_incidencias', 'name' => 'Crear incidencias', 'group_name' => 'incidencias'],
            ['slug' => 'ver_incidencias', 'name' => 'Ver incidencias', 'group_name' => 'incidencias'],
            ['slug' => 'cerrar_incidencias', 'name' => 'Cerrar incidencias', 'group_name' => 'incidencias'],
            ['slug' => 'ver_logs', 'name' => 'Ver logs', 'group_name' => 'auditoria'],
            ['slug' => 'gestionar_usuarios', 'name' => 'Gestionar usuarios', 'group_name' => 'usuarios'],
            ['slug' => 'gestionar_roles', 'name' => 'Gestionar roles', 'group_name' => 'usuarios'],
            ['slug' => 'gestionar_permisos', 'name' => 'Gestionar permisos', 'group_name' => 'usuarios'],
        ];

        global $wpdb;
        $table = FPI_DB::table('permissions');

        foreach ($permissions as $permission) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $permission['slug']));
            if (! $exists) {
                $wpdb->insert(
                    $table,
                    [
                        'slug' => $permission['slug'],
                        'name' => $permission['name'],
                        'group_name' => $permission['group_name'],
                        'created_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s', '%s']
                );
                continue;
            }

            $wpdb->update(
                $table,
                [
                    'name' => $permission['name'],
                    'group_name' => $permission['group_name'],
                ],
                ['slug' => $permission['slug']],
                ['%s', '%s'],
                ['%s']
            );
        }
    }

    public static function seed_default_role_permissions(): void
    {
        $all = self::get_all_slugs();
        $map = [
            'superadmin_interno' => $all,
            'titular' => $all,
            'administracion' => [
                'ver_dashboard', 'ver_documentos', 'ver_contabilidad', 'ver_nominas_todas', 'firmar_documentos', 'ver_horarios',
                'aprobar_cambio_turno', 'solicitar_cambio_turno', 'aprobar_vacaciones', 'solicitar_vacaciones', 'ver_estupes',
                'gestionar_almacenamiento', 'ver_almacenamiento', 'gestionar_nominas', 'gestionar_contabilidad', 'gestionar_documentos',
                'subir_facturas', 'subir_documentos', 'crear_incidencias', 'ver_incidencias', 'cerrar_incidencias', 'ver_logs'
            ],
            'farmaceutico' => [
                'ver_dashboard', 'ver_documentos', 'ver_contabilidad', 'ver_nominas_propias', 'firmar_documentos', 'ver_horarios',
                'solicitar_cambio_turno', 'solicitar_vacaciones', 'ver_estupes', 'gestionar_estupes', 'subir_facturas', 'subir_documentos',
                'crear_incidencias', 'ver_incidencias'
            ],
            'tecnico_auxiliar' => [
                'ver_dashboard', 'ver_contabilidad', 'ver_nominas_propias', 'firmar_documentos', 'ver_horarios',
                'solicitar_cambio_turno', 'subir_facturas'
            ],
            'transporte_limpieza' => [
                'ver_dashboard', 'ver_nominas_propias', 'firmar_documentos', 'ver_horarios', 'solicitar_vacaciones'
            ],
        ];

        global $wpdb;
        foreach ($map as $roleSlug => $permissionSlugs) {
            $role = FPI_Roles::get_by_slug($roleSlug);
            if (! $role) {
                continue;
            }

            $wpdb->delete(FPI_DB::table('role_permissions'), ['role_id' => (int) $role['id']], ['%d']);

            foreach ($permissionSlugs as $permissionSlug) {
                self::assign_to_role((int) $role['id'], $permissionSlug);
            }
        }
    }

    public static function get_all(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . FPI_DB::table('permissions') . " ORDER BY group_name ASC, name ASC", ARRAY_A) ?: [];
    }

    public static function get_all_slugs(): array
    {
        return array_map(static fn(array $permission): string => (string) $permission['slug'], self::get_all());
    }

    public static function create_permission(string $slug, string $name, string $groupName = 'general'): bool
    {
        global $wpdb;
        $result = $wpdb->insert(
            FPI_DB::table('permissions'),
            [
                'slug' => sanitize_key($slug),
                'name' => sanitize_text_field($name),
                'group_name' => sanitize_key($groupName),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    public static function assign_to_user(int $userId, string $permissionSlug, string $grantType = 'allow', ?int $assignedBy = null): bool
    {
        global $wpdb;
        $permissionSlug = sanitize_key($permissionSlug);
        $grantType = $grantType === 'deny' ? 'deny' : 'allow';

        $wpdb->delete(
            FPI_DB::table('user_permissions'),
            [
                'user_id' => $userId,
                'permission_slug' => $permissionSlug,
            ],
            ['%d', '%s']
        );

        $result = $wpdb->insert(
            FPI_DB::table('user_permissions'),
            [
                'user_id' => $userId,
                'permission_slug' => $permissionSlug,
                'grant_type' => $grantType,
                'assigned_by' => $assignedBy,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    public static function set_user_permission_state(int $userId, string $permissionSlug, bool $allowed, ?int $assignedBy = null): bool
    {
        return self::assign_to_user($userId, $permissionSlug, $allowed ? 'allow' : 'deny', $assignedBy);
    }

    public static function get_user_permissions(int $userId): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM " . FPI_DB::table('user_permissions') . " WHERE user_id = %d ORDER BY permission_slug ASC",
            $userId
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function assign_to_role(int $roleId, string $permissionSlug): bool
    {
        global $wpdb;
        $result = $wpdb->replace(
            FPI_DB::table('role_permissions'),
            [
                'role_id' => $roleId,
                'permission_slug' => sanitize_key($permissionSlug),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        return $result !== false;
    }

    public static function get_role_permissions(int $roleId): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT permission_slug FROM " . FPI_DB::table('role_permissions') . " WHERE role_id = %d ORDER BY permission_slug ASC",
            $roleId
        );

        return array_map(static fn(array $row): string => (string) $row['permission_slug'], $wpdb->get_results($sql, ARRAY_A) ?: []);
    }
}
