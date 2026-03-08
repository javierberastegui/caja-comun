<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Items
{
    public static function create(array $data): int
    {
        global $wpdb;

        $payload = [
            'module_slug' => sanitize_key((string) ($data['module_slug'] ?? '')),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'folder_name' => sanitize_text_field((string) ($data['folder_name'] ?? '')),
            'employee_user_id' => ! empty($data['employee_user_id']) ? (int) $data['employee_user_id'] : null,
            'provider' => sanitize_key((string) ($data['provider'] ?? 'synology')),
            'external_url' => ! empty($data['external_url']) ? esc_url_raw((string) $data['external_url']) : null,
            'storage_path' => ! empty($data['storage_path']) ? sanitize_text_field((string) $data['storage_path']) : null,
            'file_name' => ! empty($data['file_name']) ? sanitize_file_name((string) $data['file_name']) : null,
            'notes' => ! empty($data['notes']) ? sanitize_textarea_field((string) $data['notes']) : null,
            'uploaded_by' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $wpdb->insert(
            FPI_DB::table('storage_items'),
            $payload,
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function get(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $args = [];

        if (! empty($filters['module_slug'])) {
            $where[] = 'module_slug = %s';
            $args[] = sanitize_key((string) $filters['module_slug']);
        }

        if (array_key_exists('employee_user_id', $filters)) {
            if ($filters['employee_user_id'] === null) {
                $where[] = 'employee_user_id IS NULL';
            } else {
                $where[] = '(employee_user_id = %d OR employee_user_id IS NULL)';
                $args[] = (int) $filters['employee_user_id'];
            }
        }

        if (! empty($filters['month_key']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['month_key'])) {
            $where[] = "DATE_FORMAT(created_at, '%%Y-%%m') = %s";
            $args[] = (string) $filters['month_key'];
        }

        $sql = "SELECT * FROM " . FPI_DB::table('storage_items') . " WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC, id DESC";

        if (! empty($args)) {
            $sql = $wpdb->prepare($sql, $args);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function get_by_id(int $id): ?array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM " . FPI_DB::table('storage_items') . " WHERE id = %d LIMIT 1", $id);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function get_available_months(string $moduleSlug): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT DISTINCT DATE_FORMAT(created_at, '%%Y-%%m') AS month_key FROM " . FPI_DB::table('storage_items') . " WHERE module_slug = %s ORDER BY month_key DESC", sanitize_key($moduleSlug));
        $rows = $wpdb->get_col($sql);
        $months = array_values(array_filter(array_map('strval', is_array($rows) ? $rows : [])));
        $current = current_time('Y-m');
        if (! in_array($current, $months, true)) {
            array_unshift($months, $current);
        }
        return array_values(array_unique($months));
    }

    public static function create_estupe_movement(array $data): int
    {
        global $wpdb;
        $payload = [
            'movement_date' => ! empty($data['movement_date']) ? sanitize_text_field((string) $data['movement_date']) : current_time('mysql'),
            'cn' => sanitize_text_field((string) ($data['cn'] ?? '')),
            'medicine_name' => sanitize_text_field((string) ($data['medicine_name'] ?? '')),
            'movement_type' => sanitize_key((string) ($data['movement_type'] ?? 'recepcion')),
            'initial_stock' => (int) ($data['initial_stock'] ?? 0),
            'final_stock' => (int) ($data['final_stock'] ?? 0),
            'pharmacist_name' => sanitize_text_field((string) ($data['pharmacist_name'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'created_by' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql'),
        ];
        $wpdb->insert(
            FPI_DB::table('estupe_movements'),
            $payload,
            ['%s','%s','%s','%s','%d','%d','%s','%s','%d','%s']
        );
        return (int) $wpdb->insert_id;
    }

    public static function get_estupe_movements(string $monthKey): array
    {
        global $wpdb;
        $monthKey = preg_match('/^\d{4}-\d{2}$/', $monthKey) ? $monthKey : current_time('Y-m');
        $sql = $wpdb->prepare("SELECT * FROM " . FPI_DB::table('estupe_movements') . " WHERE DATE_FORMAT(movement_date, '%%Y-%%m') = %s ORDER BY movement_date DESC, id DESC", $monthKey);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function get_estupe_available_months(): array
    {
        global $wpdb;
        $rows = $wpdb->get_col("SELECT DISTINCT DATE_FORMAT(movement_date, '%Y-%m') AS month_key FROM " . FPI_DB::table('estupe_movements') . " ORDER BY month_key DESC");
        $months = array_values(array_filter(array_map('strval', is_array($rows) ? $rows : [])));
        $current = current_time('Y-m');
        if (! in_array($current, $months, true)) {
            array_unshift($months, $current);
        }
        return array_values(array_unique($months));
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        $deleted = $wpdb->delete(FPI_DB::table('storage_items'), ['id' => $id], ['%d']);
        return $deleted !== false;
    }
}

