<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Access
{
    public static function is_wp_admin_user(?int $userId = null): bool
    {
        $userId = $userId ?? get_current_user_id();
        if ($userId < 1) {
            return false;
        }

        $user = get_userdata($userId);
        return $user instanceof WP_User && user_can($user, 'manage_options');
    }

    public static function is_internal_superadmin(?int $userId = null): bool
    {
        $userId = $userId ?? get_current_user_id();
        if ($userId < 1) {
            return false;
        }

        return FPI_Roles::user_has_role_slug($userId, 'superadmin_interno');
    }

    public static function can(string $permissionSlug, ?int $userId = null): bool
    {
        $userId = $userId ?? get_current_user_id();
        if ($userId < 1) {
            return false;
        }

        if (self::is_wp_admin_user($userId) || self::is_internal_superadmin($userId)) {
            return true;
        }

        $permissionSlug = sanitize_key($permissionSlug);

        foreach (FPI_Permissions::get_user_permissions($userId) as $permission) {
            if (($permission['permission_slug'] ?? '') === $permissionSlug && ($permission['grant_type'] ?? 'allow') === 'deny') {
                return false;
            }
        }

        foreach (FPI_Permissions::get_user_permissions($userId) as $permission) {
            if (($permission['permission_slug'] ?? '') === $permissionSlug && ($permission['grant_type'] ?? 'allow') === 'allow') {
                return true;
            }
        }

        foreach (FPI_Roles::get_user_roles($userId) as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            if ($roleId > 0 && in_array($permissionSlug, FPI_Permissions::get_role_permissions($roleId), true)) {
                return true;
            }
        }

        return false;
    }

    public static function any(array $permissionSlugs, ?int $userId = null): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if (self::can((string) $permissionSlug, $userId)) {
                return true;
            }
        }

        return false;
    }

    public static function require_permission(array|string $permissionSlugs): void
    {
        $permissionSlugs = (array) $permissionSlugs;

        if (self::any($permissionSlugs)) {
            return;
        }

        wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno'));
    }
}
