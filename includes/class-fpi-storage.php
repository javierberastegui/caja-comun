<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Storage
{
    private static string $activeUploadModule = '';

    public const MODULES = [
        'horarios' => 'Horarios',
        'nominas' => 'Nóminas',
        'contabilidad' => 'Contabilidad',
        'documentos' => 'Documentos',
        'estupes' => 'Estupefacientes',
    ];

    public const PROVIDERS = [
        'synology' => 'Synology',
        'gdrive' => 'Google Drive',
        'onedrive' => 'OneDrive',
        'mega' => 'MEGA',
    ];

    public const MODULE_VIEW_PERMISSIONS = [
        'horarios' => 'ver_horarios',
        'nominas' => 'ver_nominas_propias',
        'contabilidad' => 'ver_contabilidad',
        'documentos' => 'ver_documentos',
        'estupes' => 'ver_estupes',
    ];

    public static function seed_options(): void
    {
        $defaults = [
            'fpi_storage_provider_synology_base_url' => '',
            'fpi_storage_provider_gdrive_base_url' => '',
            'fpi_storage_provider_onedrive_base_url' => '',
            'fpi_storage_provider_mega_base_url' => '',
            'fpi_storage_visibility_matrix' => [],
            'fpi_storage_nominas_user_urls' => [],
        ];

        foreach (self::MODULES as $module => $label) {
            $defaults['fpi_storage_module_provider_' . $module] = 'synology';
            $defaults['fpi_storage_module_url_' . $module] = '';
            $defaults['fpi_storage_module_path_' . $module] = '';
        }

        foreach ($defaults as $key => $value) {
            if (get_option($key, null) === null) {
                add_option($key, $value);
            }
        }

        self::migrate_legacy_options();
    }

    public static function migrate_legacy_options(): void
    {
        $legacy = [
            'horarios' => 'fpi_synology_horarios_url',
            'nominas' => 'fpi_synology_nominas_url',
            'contabilidad' => 'fpi_synology_contabilidad_url',
            'documentos' => 'fpi_synology_documentos_url',
            'estupes' => 'fpi_synology_estupes_url',
        ];

        foreach ($legacy as $module => $oldKey) {
            $newKey = 'fpi_storage_module_url_' . $module;
            $current = (string) get_option($newKey, '');
            $old = (string) get_option($oldKey, '');

            if ($current === '' && $old !== '') {
                update_option($newKey, $old);
            }
        }

        $oldBase = (string) get_option('fpi_synology_base_url', '');
        if ((string) get_option('fpi_storage_provider_synology_base_url', '') === '' && $oldBase !== '') {
            update_option('fpi_storage_provider_synology_base_url', $oldBase);
        }

        if (get_option('fpi_storage_iframe_mode', null) === null && get_option('fpi_synology_iframe_mode', null) !== null) {
            update_option('fpi_storage_iframe_mode', (string) get_option('fpi_synology_iframe_mode', '0'));
        }
    }

    public static function get_module_provider(string $module): string
    {
        $provider = (string) get_option('fpi_storage_module_provider_' . sanitize_key($module), 'synology');
        return isset(self::PROVIDERS[$provider]) ? $provider : 'synology';
    }

    public static function get_module_url(string $module): string
    {
        return (string) get_option('fpi_storage_module_url_' . sanitize_key($module), '');
    }

    public static function get_nominas_user_urls(): array
    {
        $urls = get_option('fpi_storage_nominas_user_urls', []);
        return is_array($urls) ? $urls : [];
    }

    public static function get_nominas_user_url(int $userId): string
    {
        $urls = self::get_nominas_user_urls();
        return isset($urls[$userId]) ? (string) $urls[$userId] : '';
    }

    public static function save_nominas_user_urls(array $urls): void
    {
        $clean = [];
        foreach ($urls as $userId => $url) {
            $userId = (int) $userId;
            if ($userId < 1) { continue; }
            $url = esc_url_raw((string) $url);
            if ($url !== '') {
                $clean[$userId] = $url;
            }
        }
        update_option('fpi_storage_nominas_user_urls', $clean);
    }


    public static function get_module_path(string $module): string
    {
        return trim((string) get_option('fpi_storage_module_path_' . sanitize_key($module), ''), '/');
    }

    public static function get_provider_base_url(string $provider): string
    {
        return (string) get_option('fpi_storage_provider_' . sanitize_key($provider) . '_base_url', '');
    }

    public static function get_module_view_permission(string $module): string
    {
        return self::MODULE_VIEW_PERMISSIONS[sanitize_key($module)] ?? '';
    }


    public static function get_visibility_matrix(): array
    {
        $matrix = get_option('fpi_storage_visibility_matrix', []);
        return is_array($matrix) ? $matrix : [];
    }

    public static function save_visibility_matrix(array $matrix): void
    {
        update_option('fpi_storage_visibility_matrix', $matrix);
    }

    public static function user_can_access_module(string $module, ?int $userId = null): bool
    {
        $module = sanitize_key($module);
        $userId = $userId ?? get_current_user_id();

        if ($userId < 1) {
            return false;
        }

        if (FPI_Access::is_wp_admin_user($userId) || FPI_Access::is_internal_superadmin($userId)) {
            return true;
        }

        $matrix = self::get_visibility_matrix();
        if (isset($matrix[$userId]) && is_array($matrix[$userId]) && array_key_exists($module, $matrix[$userId])) {
            return (bool) $matrix[$userId][$module];
        }

        $permission = self::get_module_view_permission($module);
        return $permission !== '' ? FPI_Access::can($permission, $userId) : false;
    }

    public static function iframe_enabled(): bool
    {
        return false;
    }

    public static function synology_webdav_ready(): bool
    {
        return false;
    }

    public static function test_synology_webdav_connection(): array
    {
        return ['success' => false, 'message' => 'WebDAV desactivado en esta versión.'];
    }

    public static function upload_file_to_module(string $module, array $file): array
    {
        $module = sanitize_key($module);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No se recibió un archivo válido.'];
        }

        return self::upload_file_to_local_library($module, $file);
    }

    private static function upload_file_to_synology(string $module, array $file): array
    {
        $originalName = sanitize_file_name((string) ($file['name'] ?? 'archivo'));
        $timestamp = current_time('timestamp');
        $datedName = wp_date('Y-m-d_H-i-s', $timestamp) . '_' . $originalName;

        $path = self::get_module_path($module);
        if ($path !== '') {
            $mkcol = self::ensure_webdav_directory($path);
            if (! $mkcol['success']) {
                return $mkcol;
            }
        }

        $targetPath = ($path !== '' ? $path . '/' : '') . $datedName;
        $body = file_get_contents((string) $file['tmp_name']);
        if ($body === false) {
            return ['success' => false, 'message' => 'No se pudo leer el archivo temporal.'];
        }

        $response = wp_remote_request(
            self::build_webdav_url($targetPath),
            [
                'method' => 'PUT',
                'timeout' => 60,
                'headers' => [
                    'Authorization' => self::get_webdav_auth_header(),
                    'Content-Type' => (string) ($file['type'] ?? 'application/octet-stream'),
                ],
                'body' => $body,
            ]
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (! in_array($code, [200, 201, 204], true)) {
            return ['success' => false, 'message' => 'Synology devolvió HTTP ' . $code . '.'];
        }

        return [
            'success' => true,
            'storage_path' => '/' . $targetPath,
            'file_name' => $datedName,
            'uploaded_at' => current_time('mysql'),
            'storage_origin' => 'synology',
        ];
    }

    private static function upload_file_to_local_library(string $module, array $file): array
    {
        if (! function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $overrides = [
            'test_form' => false,
            'test_type' => false,
            'mimes' => null,
            'unique_filename_callback' => static function (string $dir, string $name, string $ext) use ($module): string {
                $base = sanitize_file_name(pathinfo($name, PATHINFO_FILENAME));
                $stamp = wp_date('Y-m-d_H-i-s', current_time('timestamp'));
                return $stamp . '_' . $module . '_' . $base . $ext;
            },
        ];

        add_filter('upload_dir', [__CLASS__, 'filter_upload_dir']);
        self::$activeUploadModule = $module;
        $uploaded = wp_handle_upload($file, $overrides);
        self::$activeUploadModule = '';
        remove_filter('upload_dir', [__CLASS__, 'filter_upload_dir']);

        if (! is_array($uploaded) || ! empty($uploaded['error'])) {
            return ['success' => false, 'message' => (string) ($uploaded['error'] ?? 'No se pudo subir el archivo a la biblioteca local.')];
        }

        $relative = self::relative_upload_path((string) ($uploaded['file'] ?? ''));
        if ($relative === '') {
            return ['success' => false, 'message' => 'No se pudo calcular la ruta local del archivo.'];
        }

        return [
            'success' => true,
            'storage_path' => 'local:' . $relative,
            'file_name' => basename((string) ($uploaded['file'] ?? 'archivo')),
            'uploaded_at' => current_time('mysql'),
            'storage_origin' => 'local',
            'external_url' => (string) ($uploaded['url'] ?? ''),
        ];
    }

    private static function ensure_webdav_directory(string $path): array
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== '');
        $current = '';

        foreach ($segments as $segment) {
            $current .= ($current === '' ? '' : '/') . $segment;
            $response = wp_remote_request(
                self::build_webdav_url($current),
                [
                    'method' => 'MKCOL',
                    'timeout' => 20,
                    'headers' => [
                        'Authorization' => self::get_webdav_auth_header(),
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return ['success' => false, 'message' => $response->get_error_message()];
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if (! in_array($code, [201, 301, 405], true)) {
                return ['success' => false, 'message' => 'No se pudo preparar la carpeta en Synology (HTTP ' . $code . ').'];
            }
        }

        return ['success' => true];
    }

    public static function fetch_file_from_storage_path(string $storagePath): array
    {
        if (str_starts_with($storagePath, 'local:')) {
            return self::fetch_local_file($storagePath);
        }

        return ['success' => false, 'message' => 'Solo hay archivos locales disponibles en esta versión.'];
    }

    public static function get_absolute_local_path_from_storage_path(string $storagePath): string
    {
        if (! str_starts_with($storagePath, 'local:')) {
            return '';
        }

        return self::absolute_upload_path(substr($storagePath, 6));
    }

    public static function delete_file_from_storage_path(string $storagePath): bool
    {
        if ($storagePath === '') {
            return true;
        }

        if (str_starts_with($storagePath, 'local:')) {
            $absolute = self::absolute_upload_path(substr($storagePath, 6));
            return $absolute === '' || ! file_exists($absolute) ? true : (bool) @unlink($absolute);
        }

        return true;
    }

    public static function filter_upload_dir(array $dirs): array
    {
        $module = self::$activeUploadModule !== '' ? self::$activeUploadModule : 'general';
        $moduleDir = 'farmacia-portal-interno/' . sanitize_key($module) . '/' . current_time('Y') . '/' . current_time('m');
        $dirs['subdir'] = '/' . $moduleDir;
        $dirs['path'] = $dirs['basedir'] . '/' . $moduleDir;
        $dirs['url'] = $dirs['baseurl'] . '/' . $moduleDir;
        return $dirs;
    }

    private static function fetch_local_file(string $storagePath): array
    {
        $relative = substr($storagePath, 6);
        $absolute = self::absolute_upload_path($relative);
        if ($absolute === '' || ! file_exists($absolute)) {
            return ['success' => false, 'message' => 'El archivo local no existe.'];
        }

        $body = file_get_contents($absolute);
        if ($body === false) {
            return ['success' => false, 'message' => 'No se pudo leer el archivo local.'];
        }

        return [
            'success' => true,
            'body' => $body,
            'content_type' => (string) wp_check_filetype(basename($absolute))['type'] ?: 'application/octet-stream',
        ];
    }

    private static function relative_upload_path(string $absolute): string
    {
        $uploads = wp_get_upload_dir();
        $base = wp_normalize_path((string) ($uploads['basedir'] ?? ''));
        $absolute = wp_normalize_path($absolute);
        if ($base === '' || ! str_starts_with($absolute, $base)) {
            return '';
        }
        return ltrim(substr($absolute, strlen($base)), '/');
    }

    private static function absolute_upload_path(string $relative): string
    {
        $uploads = wp_get_upload_dir();
        $base = wp_normalize_path((string) ($uploads['basedir'] ?? ''));
        $relative = ltrim(wp_normalize_path($relative), '/');
        return $base !== '' && $relative !== '' ? $base . '/' . $relative : '';
    }

    private static function get_webdav_auth_header(): string
    {
        return 'Basic ' . base64_encode((string) get_option('fpi_storage_synology_webdav_user', '') . ':' . (string) get_option('fpi_storage_synology_webdav_pass', ''));
    }

    private static function build_webdav_url(string $targetPath): string
    {
        $base = rtrim((string) get_option('fpi_storage_synology_webdav_url', ''), '/');
        $segments = array_filter(explode('/', trim($targetPath, '/')), static fn(string $segment): bool => $segment !== '');
        $encoded = implode('/', array_map('rawurlencode', $segments));

        return $base . '/' . $encoded;
    }
}
