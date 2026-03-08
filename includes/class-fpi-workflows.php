<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Workflows
{
    public static function create_incident(array $data): int
    {
        global $wpdb;

        $payload = [
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'type' => sanitize_key((string) ($data['type'] ?? 'general')),
            'priority' => sanitize_key((string) ($data['priority'] ?? 'media')),
            'shift_label' => sanitize_key((string) ($data['shift_label'] ?? 'manana')),
            'incident_date' => ! empty($data['incident_date']) ? sanitize_text_field((string) $data['incident_date']) : current_time('Y-m-d'),
            'incident_time' => ! empty($data['incident_time']) ? sanitize_text_field((string) $data['incident_time']) : current_time('H:i:s'),
            'status' => 'abierta',
            'created_by' => get_current_user_id() ?: null,
            'assigned_to' => ! empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'resolved_by' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'resolved_at' => null,
        ];

        $wpdb->insert(
            FPI_DB::table('incidents'),
            $payload,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function get_incidents(bool $all = false, ?int $userId = null): array
    {
        global $wpdb;
        $userId = $userId ?? get_current_user_id();
        $table = FPI_DB::table('incidents');

        if ($all) {
            return $wpdb->get_results("SELECT * FROM {$table} ORDER BY CASE WHEN status='abierta' THEN 0 WHEN status='en_revision' THEN 1 ELSE 2 END, created_at DESC, id DESC", ARRAY_A) ?: [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE created_by = %d OR assigned_to = %d ORDER BY CASE WHEN status='abierta' THEN 0 WHEN status='en_revision' THEN 1 ELSE 2 END, created_at DESC, id DESC",
            $userId,
            $userId
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function update_incident_status(int $incidentId, string $status, ?int $assignedTo = null): bool
    {
        global $wpdb;
        $allowedStatuses = ['abierta', 'en_revision', 'cerrada'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'abierta';
        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($assignedTo !== null) {
            $data['assigned_to'] = $assignedTo > 0 ? $assignedTo : null;
        }

        if ($status === 'cerrada') {
            $data['resolved_by'] = get_current_user_id() ?: null;
            $data['resolved_at'] = current_time('mysql');
        }

        $updated = $wpdb->update(FPI_DB::table('incidents'), $data, ['id' => $incidentId]);
        return $updated !== false;
    }

    public static function create_request(array $data): int
    {
        global $wpdb;

        $meta = $data['meta_json'] ?? null;
        $payload = [
            'request_type' => sanitize_key((string) ($data['request_type'] ?? 'vacaciones')),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'status' => sanitize_key((string) ($data['status'] ?? 'pendiente')),
            'requested_by' => array_key_exists('requested_by', $data) ? (! empty($data['requested_by']) ? (int) $data['requested_by'] : null) : (get_current_user_id() ?: null),
            'reviewed_by' => null,
            'start_date' => ! empty($data['start_date']) ? sanitize_text_field((string) $data['start_date']) : null,
            'end_date' => ! empty($data['end_date']) ? sanitize_text_field((string) $data['end_date']) : null,
            'meta_json' => is_array($meta) ? wp_json_encode($meta) : (! empty($meta) ? sanitize_textarea_field((string) $meta) : null),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'reviewed_at' => null,
        ];

        $wpdb->insert(
            FPI_DB::table('requests'),
            $payload,
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function create_vacation_slot(array $data): int
    {
        return self::create_request([
            'request_type' => 'vacaciones_slot',
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'status' => 'publicada',
            'start_date' => (string) ($data['start_date'] ?? ''),
            'end_date' => (string) ($data['end_date'] ?? ''),
            'meta_json' => [
                'slot_capacity' => max(1, (int) ($data['slot_capacity'] ?? 99)),
            ],
        ]);
    }

    public static function reserve_vacation_slot(int $slotId, string $notes = ''): int
    {
        $slot = self::get_request_by_id($slotId);
        if (! $slot || ($slot['request_type'] ?? '') !== 'vacaciones_slot') {
            return 0;
        }

        $slotMeta = self::get_request_meta($slot);
        $capacity = max(1, (int) ($slotMeta['slot_capacity'] ?? 99));
        if (self::count_slot_reservations($slotId) >= $capacity) {
            return 0;
        }

        return self::create_request([
            'request_type' => 'vacaciones',
            'title' => (string) ($slot['title'] ?? 'Vacaciones'),
            'description' => $notes !== '' ? $notes : (string) ($slot['description'] ?? ''),
            'status' => 'pendiente',
            'start_date' => (string) ($slot['start_date'] ?? ''),
            'end_date' => (string) ($slot['end_date'] ?? ''),
            'meta_json' => [
                'slot_request_id' => $slotId,
            ],
        ]);
    }

    public static function get_requests(string $requestType, bool $all = false, ?int $userId = null): array
    {
        global $wpdb;
        $userId = $userId ?? get_current_user_id();
        $table = FPI_DB::table('requests');
        $requestType = sanitize_key($requestType);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE request_type = %s ORDER BY CASE WHEN status='pendiente' THEN 0 WHEN status IN ('publicada','aprobada') THEN 1 ELSE 2 END, start_date ASC, created_at DESC, id DESC",
            $requestType
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        if ($all) {
            return $rows;
        }

        if ($requestType !== 'cambio_turno') {
            return array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['requested_by'] ?? 0) === $userId));
        }

        return array_values(array_filter($rows, static function (array $row) use ($userId): bool {
            if ((int) ($row['requested_by'] ?? 0) === $userId) {
                return true;
            }

            $meta = ! empty($row['meta_json']) ? json_decode((string) $row['meta_json'], true) : [];
            return (int) ($meta['target_user_id'] ?? 0) === $userId;
        }));
    }

    public static function get_request_by_id(int $requestId): ?array
    {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM " . FPI_DB::table('requests') . " WHERE id = %d LIMIT 1", $requestId);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function get_vacation_slots(bool $onlyPublished = true): array
    {
        $rows = self::get_requests('vacaciones_slot', true);
        if (! $onlyPublished) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn(array $row): bool => in_array((string) ($row['status'] ?? ''), ['publicada', 'aprobada'], true)));
    }

    public static function get_request_meta(array $request): array
    {
        return ! empty($request['meta_json']) ? (json_decode((string) $request['meta_json'], true) ?: []) : [];
    }

    public static function get_vacation_reservations(bool $all = false, ?int $userId = null): array
    {
        $userId = $userId ?? get_current_user_id();
        $rows = self::get_requests('vacaciones', true);
        if ($all) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['requested_by'] ?? 0) === $userId));
    }

    public static function get_approved_vacations(?int $userId = null, bool $all = false): array
    {
        $userId = $userId ?? get_current_user_id();
        $rows = array_values(array_filter(self::get_requests('vacaciones', true), static fn(array $row): bool => (string) ($row['status'] ?? '') === 'aprobada'));

        if ($all) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['requested_by'] ?? 0) === $userId));
    }

    public static function get_approved_shift_changes(?int $userId = null, bool $all = false): array
    {
        $userId = $userId ?? get_current_user_id();
        $rows = array_values(array_filter(self::get_requests('cambio_turno', true), static fn(array $row): bool => (string) ($row['status'] ?? '') === 'aprobada'));

        if ($all) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($userId): bool {
            if ((int) ($row['requested_by'] ?? 0) === $userId) {
                return true;
            }

            $meta = self::get_request_meta($row);
            return (int) ($meta['target_user_id'] ?? 0) === $userId;
        }));
    }

    public static function count_slot_reservations(int $slotId, bool $approvedOnly = false): int
    {
        $count = 0;
        foreach (self::get_vacation_reservations(true) as $reservation) {
            $meta = self::get_request_meta($reservation);
            if ((int) ($meta['slot_request_id'] ?? 0) !== $slotId) {
                continue;
            }
            if ($approvedOnly && (string) ($reservation['status'] ?? '') !== 'aprobada') {
                continue;
            }
            $count++;
        }
        return $count;
    }

    public static function delete_request(int $requestId): bool
    {
        global $wpdb;
        $deleted = $wpdb->delete(FPI_DB::table('requests'), ['id' => $requestId], ['%d']);
        return $deleted !== false;
    }

    public static function update_request_status(int $requestId, string $status): bool
    {
        global $wpdb;
        $allowedStatuses = ['pendiente', 'aprobada', 'rechazada', 'cancelada', 'publicada'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'pendiente';

        $updated = $wpdb->update(
            FPI_DB::table('requests'),
            [
                'status' => $status,
                'reviewed_by' => get_current_user_id() ?: null,
                'reviewed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $requestId],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }
}
