<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Notifications
{
    public static function create(string $module, string $title, string $message, ?int $relatedId = null): int
    {
        global $wpdb;

        $wpdb->insert(
            FPI_DB::table('notifications'),
            [
                'module' => sanitize_key($module),
                'title' => sanitize_text_field($title),
                'message' => sanitize_textarea_field($message),
                'related_id' => $relatedId,
                'is_read' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function latest(int $limit = 20): array
    {
        global $wpdb;
        $table = FPI_DB::table('notifications');
        $sql = $wpdb->prepare("SELECT * FROM {$table} ORDER BY is_read ASC, created_at DESC, id DESC LIMIT %d", $limit);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function unread_count(): int
    {
        global $wpdb;
        $table = FPI_DB::table('notifications');
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_read = 0");
    }

    public static function mark_all_read(): void
    {
        global $wpdb;
        $wpdb->update(FPI_DB::table('notifications'), ['is_read' => 1], ['is_read' => 0], ['%d'], ['%d']);
    }
}
