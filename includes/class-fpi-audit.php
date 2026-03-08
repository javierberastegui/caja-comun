<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Audit
{
    public static function log(string $action, string $module, ?string $description = null, ?string $objectType = null, ?int $objectId = null): void
    {
        global $wpdb;

        $wpdb->insert(
            FPI_DB::table('audit_logs'),
            [
                'user_id' => get_current_user_id() ?: null,
                'action' => sanitize_key($action),
                'module' => sanitize_key($module),
                'object_type' => $objectType ? sanitize_text_field($objectType) : null,
                'object_id' => $objectId,
                'description' => $description,
                'ip_address' => self::get_ip_address(),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    public static function latest(int $limit = 50): array
    {
        global $wpdb;
        $table = FPI_DB::table('audit_logs');
        $sql = $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function get_ip_address(): ?string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (! empty($_SERVER[$key])) {
                $value = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
                return explode(',', $value)[0];
            }
        }

        return null;
    }
}
