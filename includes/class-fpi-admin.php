<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class FPI_Admin
{
    private string $noticeKey = 'fpi_admin_notice_';

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
        add_action('admin_footer', [$this, 'print_admin_js']);
    }

    public function register_menu(): void
    {
        $pages = $this->get_menu_pages();
        $visiblePages = array_values(array_filter($pages, static fn(array $page): bool => $page['visible'] === true));

        if (empty($visiblePages)) {
            return;
        }

        $firstPage = $visiblePages[0];

        add_menu_page(
            'Portal Interno',
            'Portal Interno',
            'read',
            (string) $firstPage['slug'],
            $firstPage['callback'],
            'dashicons-building',
            3
        );

        foreach ($visiblePages as $page) {
            add_submenu_page(
                (string) $firstPage['slug'],
                (string) $page['title'],
                (string) $page['menu_title'],
                'read',
                (string) $page['slug'],
                $page['callback']
            );
        }
    }

    public function enqueue_assets(string $hook): void
    {
        if (strpos($hook, 'fpi-') === false) {
            return;
        }

        wp_enqueue_style('fpi-admin', FPI_PLUGIN_URL . 'assets/css/admin.css', [], FPI_VERSION);
    }

    public function handle_actions(): void
    {
        if (empty($_POST['fpi_action'])) {
            return;
        }

        check_admin_referer('fpi_admin_action', 'fpi_nonce');
        $action = sanitize_key(wp_unslash((string) $_POST['fpi_action']));

        switch ($action) {
            case 'assign_role':
                FPI_Access::require_permission(['gestionar_usuarios']);
                $this->assign_role();
                break;
            case 'assign_permission':
                FPI_Access::require_permission(['gestionar_permisos']);
                $this->assign_permission();
                break;
            case 'assign_role_permission':
                FPI_Access::require_permission(['gestionar_permisos']);
                $this->assign_role_permission();
                break;
            case 'create_role':
                FPI_Access::require_permission(['gestionar_roles']);
                $this->create_role();
                break;
            case 'create_permission':
                FPI_Access::require_permission(['gestionar_permisos']);
                $this->create_permission();
                break;
            case 'save_storage_settings':
                FPI_Access::require_permission(['gestionar_almacenamiento']);
                $this->save_storage_settings();
                break;
            case 'add_storage_item':
                $this->add_storage_item();
                break;
            case 'delete_storage_item':
                $this->delete_storage_item();
                break;
            case 'download_storage_item':
                $this->download_storage_item();
                break;
            case 'create_estupe_movement':
                FPI_Access::require_permission(['gestionar_estupes']);
                $this->create_estupe_movement();
                break;
            case 'export_estupes':
                FPI_Access::require_permission(['gestionar_estupes']);
                $this->export_estupes();
                break;
            case 'mark_notifications_read':
                FPI_Access::require_permission(['gestionar_almacenamiento', 'gestionar_nominas', 'gestionar_documentos', 'gestionar_contabilidad', 'gestionar_estupes', 'ver_logs']);
                $this->mark_notifications_read();
                break;
            case 'create_incident':
                FPI_Access::require_permission(['crear_incidencias']);
                $this->create_incident();
                break;
            case 'update_incident_status':
                FPI_Access::require_permission(['cerrar_incidencias']);
                $this->update_incident_status();
                break;
            case 'create_request':
                $this->create_request();
                break;
            case 'create_vacation_slot':
                FPI_Access::require_permission(['aprobar_vacaciones']);
                $this->create_vacation_slot();
                break;
            case 'reserve_vacation_slot':
                FPI_Access::require_permission(['solicitar_vacaciones']);
                $this->reserve_vacation_slot();
                break;
            case 'update_request_status':
                $this->update_request_status();
                break;
        }
    }

    public function render_admin_notice(): void
    {
        if (! isset($_GET['page']) || strpos((string) $_GET['page'], 'fpi-') !== 0) {
            return;
        }

        $notice = get_transient($this->noticeKey . get_current_user_id());
        if (! is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient($this->noticeKey . get_current_user_id());
        $class = $notice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
    }

    public function print_admin_js(): void
    {
        // Intencionadamente vacío en v4.2:
        // Horarios ya no reescribe el enlace del menú ni redirige fuera del panel.
    }

    private function set_notice(string $message, string $type = 'success'): void
    {
        set_transient($this->noticeKey . get_current_user_id(), ['message' => $message, 'type' => $type], 60);
    }

    private function redirect_back(string $page): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }

    private function get_menu_pages(): array
    {
        return [
            [
                'slug' => 'fpi-dashboard',
                'title' => 'Dashboard',
                'menu_title' => 'Dashboard',
                'callback' => [$this, 'render_dashboard'],
                'visible' => FPI_Access::can('ver_dashboard') || FPI_Access::can('ver_documentos') || FPI_Access::can('ver_almacenamiento'),
            ],
            [
                'slug' => 'fpi-users',
                'title' => 'Usuarios',
                'menu_title' => 'Usuarios',
                'callback' => [$this, 'render_users'],
                'visible' => FPI_Access::can('gestionar_usuarios'),
            ],
            [
                'slug' => 'fpi-roles',
                'title' => 'Roles',
                'menu_title' => 'Roles',
                'callback' => [$this, 'render_roles'],
                'visible' => FPI_Access::can('gestionar_roles'),
            ],
            [
                'slug' => 'fpi-permissions',
                'title' => 'Permisos',
                'menu_title' => 'Permisos',
                'callback' => [$this, 'render_permissions'],
                'visible' => FPI_Access::can('gestionar_permisos'),
            ],
            [
                'slug' => 'fpi-horarios',
                'title' => 'Horarios',
                'menu_title' => 'Horarios',
                'callback' => [$this, 'render_horarios'],
                'visible' => FPI_Storage::user_can_access_module('horarios'),
            ],
            [
                'slug' => 'fpi-nominas',
                'title' => 'Nóminas',
                'menu_title' => 'Nóminas',
                'callback' => [$this, 'render_nominas'],
                'visible' => FPI_Storage::user_can_access_module('nominas'),
            ],
            [
                'slug' => 'fpi-contabilidad',
                'title' => 'Contabilidad',
                'menu_title' => 'Contabilidad',
                'callback' => [$this, 'render_contabilidad'],
                'visible' => FPI_Storage::user_can_access_module('contabilidad'),
            ],
            [
                'slug' => 'fpi-documentos',
                'title' => 'Documentos',
                'menu_title' => 'Documentos',
                'callback' => [$this, 'render_documentos'],
                'visible' => FPI_Storage::user_can_access_module('documentos'),
            ],
            [
                'slug' => 'fpi-estupes',
                'title' => 'Estupefacientes',
                'menu_title' => 'Estupefacientes',
                'callback' => [$this, 'render_estupes'],
                'visible' => FPI_Storage::user_can_access_module('estupes'),
            ],
            [
                'slug' => 'fpi-incidencias',
                'title' => 'Incidencias',
                'menu_title' => 'Incidencias',
                'callback' => [$this, 'render_incidencias'],
                'visible' => FPI_Access::any(['crear_incidencias', 'ver_incidencias', 'cerrar_incidencias']),
            ],
            [
                'slug' => 'fpi-cambios-turno',
                'title' => 'Cambios de turno',
                'menu_title' => 'Cambios de turno',
                'callback' => [$this, 'render_cambios_turno'],
                'visible' => FPI_Access::any(['solicitar_cambio_turno', 'aprobar_cambio_turno']),
            ],
            [
                'slug' => 'fpi-vacaciones',
                'title' => 'Vacaciones',
                'menu_title' => 'Vacaciones',
                'callback' => [$this, 'render_vacaciones'],
                'visible' => FPI_Access::any(['solicitar_vacaciones', 'aprobar_vacaciones']),
            ],
            [
                'slug' => 'fpi-storage',
                'title' => 'Almacenamiento',
                'menu_title' => 'Almacenamiento',
                'callback' => [$this, 'render_storage_settings'],
                'visible' => FPI_Access::can('gestionar_almacenamiento'),
            ],
            [
                'slug' => 'fpi-audit',
                'title' => 'Auditoría',
                'menu_title' => 'Auditoría',
                'callback' => [$this, 'render_audit'],
                'visible' => FPI_Access::can('ver_logs'),
            ],
        ];
    }

    private function assign_role(): void
    {
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $roleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;

        if ($userId > 0 && $roleId > 0 && FPI_Roles::assign_role($userId, $roleId, get_current_user_id())) {
            FPI_Audit::log('assign_role', 'users', sprintf('Rol %d asignado al usuario %d', $roleId, $userId), 'user', $userId);
            $this->set_notice('Rol asignado.');
        }

        $this->redirect_back('fpi-users');
    }

    private function assign_permission(): void
    {
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $permissionSlug = isset($_POST['permission_slug']) ? sanitize_key(wp_unslash((string) $_POST['permission_slug'])) : '';
        $grantType = isset($_POST['grant_type']) ? sanitize_key(wp_unslash((string) $_POST['grant_type'])) : 'allow';

        if ($userId > 0 && $permissionSlug !== '' && FPI_Permissions::assign_to_user($userId, $permissionSlug, $grantType, get_current_user_id())) {
            FPI_Audit::log('assign_permission', 'permissions', sprintf('Permiso %s (%s) al usuario %d', $permissionSlug, $grantType, $userId), 'user', $userId);
            $this->set_notice('Permiso asignado.');
        }

        $this->redirect_back('fpi-users');
    }

    private function assign_role_permission(): void
    {
        $roleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
        $permissionSlug = isset($_POST['permission_slug']) ? sanitize_key(wp_unslash((string) $_POST['permission_slug'])) : '';

        if ($roleId > 0 && $permissionSlug !== '' && FPI_Permissions::assign_to_role($roleId, $permissionSlug)) {
            FPI_Audit::log('assign_role_permission', 'permissions', sprintf('Permiso %s asignado al rol %d', $permissionSlug, $roleId), 'role', $roleId);
            $this->set_notice('Permiso añadido al rol.');
        }

        $this->redirect_back('fpi-roles');
    }

    private function create_role(): void
    {
        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash((string) $_POST['slug'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
        $isBaseRole = ! empty($_POST['is_base_role']);

        if ($slug !== '' && $name !== '' && FPI_Roles::create_role($slug, $name, $isBaseRole)) {
            FPI_Audit::log('create_role', 'roles', sprintf('Rol creado: %s', $slug));
            $this->set_notice('Rol creado.');
        }

        $this->redirect_back('fpi-roles');
    }

    private function create_permission(): void
    {
        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash((string) $_POST['slug'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
        $groupName = isset($_POST['group_name']) ? sanitize_key(wp_unslash((string) $_POST['group_name'])) : 'general';

        if ($slug !== '' && $name !== '' && FPI_Permissions::create_permission($slug, $name, $groupName)) {
            FPI_Audit::log('create_permission', 'permissions', sprintf('Permiso creado: %s', $slug));
            $this->set_notice('Permiso creado.');
        }

        $this->redirect_back('fpi-permissions');
    }

    private function save_storage_settings(): void
    {
        foreach (FPI_Storage::PROVIDERS as $provider => $label) {
            update_option('fpi_storage_provider_' . $provider . '_base_url', esc_url_raw(wp_unslash((string) ($_POST['fpi_storage_provider_' . $provider . '_base_url'] ?? ''))));
        }


        foreach (FPI_Storage::MODULES as $module => $label) {
            $provider = sanitize_key(wp_unslash((string) ($_POST['fpi_storage_module_provider_' . $module] ?? 'synology')));
            update_option('fpi_storage_module_provider_' . $module, isset(FPI_Storage::PROVIDERS[$provider]) ? $provider : 'synology');
            update_option('fpi_storage_module_url_' . $module, esc_url_raw(wp_unslash((string) ($_POST['fpi_storage_module_url_' . $module] ?? ''))));
            update_option('fpi_storage_module_path_' . $module, sanitize_text_field(wp_unslash((string) ($_POST['fpi_storage_module_path_' . $module] ?? ''))));
        }

        FPI_Storage::save_nominas_user_urls(is_array($_POST['fpi_storage_nominas_user_urls'] ?? null) ? wp_unslash($_POST['fpi_storage_nominas_user_urls']) : []);

        $matrix = [];
        foreach (get_users(['fields' => ['ID']]) as $user) {
            $userId = (int) $user->ID;
            foreach (FPI_Storage::MODULES as $module => $label) {
                $matrix[$userId][$module] = ! empty($_POST['fpi_user_module_access'][$userId][$module]) ? 1 : 0;
            }
        }
        FPI_Storage::save_visibility_matrix($matrix);

        FPI_Audit::log('save_storage_settings', 'storage', 'Configuración de almacenamiento actualizada.');

        $this->set_notice('Configuración guardada.', 'success');
        $this->redirect_back('fpi-storage');
    }


    private function add_storage_item(): void
    {
        $module = sanitize_key(wp_unslash((string) ($_POST['module_slug'] ?? '')));
        if (! isset(FPI_Storage::MODULES[$module])) {
            $this->set_notice('Módulo inválido.', 'error');
            $this->redirect_back('fpi-dashboard');
        }

        $managePermission = match ($module) {
            'nominas' => ['gestionar_nominas', 'ver_nominas_todas'],
            'contabilidad' => ['gestionar_contabilidad', 'gestionar_documentos', 'subir_facturas'],
            'documentos' => ['gestionar_documentos', 'subir_documentos'],
            'estupes' => ['gestionar_estupes'],
            default => ['gestionar_almacenamiento'],
        };
        FPI_Access::require_permission($managePermission);

        $title = sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? '')));
        if ($title === '' && ! empty($_FILES['uploaded_file']['name'])) {
            $title = sanitize_file_name((string) $_FILES['uploaded_file']['name']);
        }
        if ($title === '') {
            $title = 'Documento ' . current_time('d/m/Y H:i');
        }

        $item = [
            'module_slug' => $module,
            'title' => $title,
            'folder_name' => sanitize_text_field(wp_unslash((string) ($_POST['folder_name'] ?? ''))),
            'employee_user_id' => ! empty($_POST['employee_user_id']) ? (int) $_POST['employee_user_id'] : null,
            'provider' => FPI_Storage::get_module_provider($module),
            'external_url' => esc_url_raw(wp_unslash((string) ($_POST['external_url'] ?? ''))),
            'notes' => sanitize_textarea_field(wp_unslash((string) ($_POST['notes'] ?? ''))),
        ];

        if (! empty($_FILES['uploaded_file']['name'])) {
            $upload = FPI_Storage::upload_file_to_module($module, $_FILES['uploaded_file']);
            if (! ($upload['success'] ?? false)) {
                $this->set_notice((string) ($upload['message'] ?? 'Error al subir el archivo.'), 'error');
                $this->redirect_back('fpi-' . $module);
            }
            $item['storage_path'] = (string) ($upload['storage_path'] ?? '');
            $item['file_name'] = (string) ($upload['file_name'] ?? '');
            if (! empty($upload['external_url']) && $item['external_url'] === '') {
                $item['external_url'] = (string) $upload['external_url'];
            }
        }

        $id = FPI_Items::create($item);
        if ($id > 0) {
            $employeeLabel = ! empty($item['employee_user_id']) ? $this->get_user_label((int) $item['employee_user_id']) : 'general';
            $message = sprintf('%s subido en %s el %s', $title, FPI_Storage::MODULES[$module] ?? $module, current_time('d/m/Y H:i'));
            if (! empty($item['file_name'])) {
                $message .= ' · Archivo: ' . (string) $item['file_name'];
            }
            if ($module === 'nominas') {
                $message .= ' · Empleado: ' . $employeeLabel;
            }
            FPI_Notifications::create($module, 'Nuevo documento', $message, $id);
            FPI_Audit::log('add_storage_item', $module, sprintf('Elemento creado en %s: %s', $module, $title), 'storage_item', $id);
            $notice = 'Elemento guardado y notificado en administración.';
            if (! empty($upload['notice'])) { $notice .= ' ' . (string) $upload['notice']; }
            $this->set_notice($notice);
        } else {
            $this->set_notice('No se pudo guardar el elemento.', 'error');
        }

        $this->redirect_back('fpi-' . $module);
    }

    private function delete_storage_item(): void
    {
        $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $module = sanitize_key(wp_unslash((string) ($_POST['module_slug'] ?? 'dashboard')));
        $item = $itemId > 0 ? FPI_Items::get_by_id($itemId) : null;

        if ($itemId > 0) {
            if (is_array($item) && ! empty($item['storage_path'])) {
                FPI_Storage::delete_file_from_storage_path((string) $item['storage_path']);
            }

            if (FPI_Items::delete($itemId)) {
                FPI_Audit::log('delete_storage_item', $module, sprintf('Elemento borrado: %d', $itemId), 'storage_item', $itemId);
                $this->set_notice('Elemento eliminado.');
            }
        }

        $this->redirect_back('fpi-' . $module);
    }


    private function download_storage_item(): void
    {
        $item = $this->get_downloadable_storage_item_from_request();
        $module = sanitize_key((string) ($item['module_slug'] ?? ''));

        if (! empty($item['external_url'])) {
            wp_redirect((string) $item['external_url']);
            exit;
        }

        if (empty($item['storage_path'])) {
            wp_die('El archivo no tiene ruta de almacenamiento.');
        }

        $fileName = ! empty($item['file_name']) ? (string) $item['file_name'] : basename((string) ($item['storage_path'] ?? 'archivo'));
        $download = FPI_Storage::fetch_file_from_storage_path((string) $item['storage_path']);
        if (! ($download['success'] ?? false)) {
            wp_die(esc_html((string) ($download['message'] ?? 'No se pudo descargar el archivo.')));
        }

        FPI_Audit::log('download_storage_item', $module, 'Descarga de archivo', 'storage_item', (int) ($item['id'] ?? 0));
        $this->send_binary_download((string) ($download['body'] ?? ''), (string) ($download['content_type'] ?? 'application/octet-stream'), $fileName);
    }

    private function download_storage_item_pdf(): void
    {
        $item = $this->get_downloadable_storage_item_from_request();
        $module = sanitize_key((string) ($item['module_slug'] ?? ''));
        $fileName = ! empty($item['file_name']) ? (string) $item['file_name'] : basename((string) ($item['storage_path'] ?? 'documento'));

        $pdf = $this->build_pdf_download_from_item($item, $fileName);
        if (! ($pdf['success'] ?? false)) {
            wp_die(esc_html((string) ($pdf['message'] ?? 'No se pudo generar el PDF.')));
        }

        FPI_Audit::log('download_storage_item_pdf', $module, 'Descarga PDF de imagen', 'storage_item', (int) ($item['id'] ?? 0));
        $pdfFileName = (string) ($pdf['file_name'] ?? 'documento.pdf');
        $this->send_binary_download((string) ($pdf['body'] ?? ''), 'application/pdf', $pdfFileName);
    }

    private function get_downloadable_storage_item_from_request(): array
    {
        $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        if ($itemId < 1) {
            wp_die('Elemento no válido.');
        }

        $item = FPI_Items::get_by_id($itemId);
        if (! is_array($item)) {
            wp_die('No se encontró el archivo.');
        }

        $module = sanitize_key((string) ($item['module_slug'] ?? ''));
        $allowed = match ($module) {
            'nominas' => FPI_Access::any(['gestionar_nominas', 'ver_nominas_todas']) || (int) ($item['employee_user_id'] ?? 0) === get_current_user_id(),
            'contabilidad' => FPI_Access::any(['gestionar_contabilidad', 'gestionar_documentos']),
            'documentos' => FPI_Access::can('gestionar_documentos'),
            'estupes' => FPI_Access::can('gestionar_estupes'),
            default => FPI_Access::is_internal_superadmin(),
        };

        if (! $allowed) {
            wp_die('No tienes permisos para descargar este archivo.');
        }

        return $item;
    }

    private function create_estupe_movement(): void
    {
        $movementId = FPI_Items::create_estupe_movement([
            'movement_date' => current_time('mysql'),
            'cn' => (string) ($_POST['cn'] ?? ''),
            'medicine_name' => (string) ($_POST['medicine_name'] ?? ''),
            'movement_type' => (string) ($_POST['movement_type'] ?? 'recepcion'),
            'initial_stock' => (int) ($_POST['initial_stock'] ?? 0),
            'final_stock' => (int) ($_POST['final_stock'] ?? 0),
            'pharmacist_name' => (string) ($_POST['pharmacist_name'] ?? ''),
            'notes' => (string) ($_POST['notes'] ?? ''),
        ]);

        if ($movementId > 0) {
            FPI_Notifications::create('estupes', 'Movimiento de estupefaciente', 'Se registró un movimiento de estupefacientes.', $movementId);
            FPI_Audit::log('create_estupe_movement', 'estupes', 'Movimiento de estupefaciente creado', 'estupe_movement', $movementId);
            $this->set_notice('Movimiento guardado.');
        } else {
            $this->set_notice('No se pudo guardar el movimiento.', 'error');
        }

        $this->redirect_back('fpi-estupes');
    }

    private function export_estupes(): void
    {
        $monthKey = sanitize_text_field(wp_unslash((string) ($_POST['month_key'] ?? current_time('Y-m'))));
        $rows = FPI_Items::get_estupe_movements($monthKey);
        $filename = 'estupefacientes-' . $monthKey . '.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }
        fputcsv($output, ['Fecha', 'CN', 'Medicamento', 'Movimiento', 'Stock inicial', 'Stock final', 'Farmacéutico']);
        foreach ($rows as $row) {
            fputcsv($output, [
                (string) ($row['movement_date'] ?? ''),
                (string) ($row['cn'] ?? ''),
                (string) ($row['medicine_name'] ?? ''),
                (string) ($row['movement_type'] ?? ''),
                (string) ($row['initial_stock'] ?? ''),
                (string) ($row['final_stock'] ?? ''),
                (string) ($row['pharmacist_name'] ?? ''),
            ]);
        }
        fclose($output);
        exit;
    }
    private function mark_notifications_read(): void
    {
        FPI_Notifications::mark_all_read();
        FPI_Audit::log('mark_notifications_read', 'dashboard', 'Avisos administrativos marcados como leídos.');
        $this->set_notice('Avisos marcados como leídos.');
        $this->redirect_back('fpi-dashboard');
    }

    private function create_incident(): void
    {
        $incidentId = FPI_Workflows::create_incident([
            'title' => (string) ($_POST['title'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'type' => (string) ($_POST['incident_type'] ?? 'general'),
            'priority' => (string) ($_POST['priority'] ?? 'media'),
            'shift_label' => (string) ($_POST['shift_label'] ?? 'manana'),
            'incident_date' => (string) ($_POST['incident_date'] ?? current_time('Y-m-d')),
            'incident_time' => (string) ($_POST['incident_time'] ?? current_time('H:i:s')),
            'assigned_to' => ! empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null,
        ]);

        if ($incidentId > 0) {
            FPI_Notifications::create('incidencias', 'Nueva incidencia', 'Se registró una incidencia: ' . sanitize_text_field((string) ($_POST['title'] ?? '')), $incidentId);
            FPI_Audit::log('create_incident', 'incidencias', 'Incidencia creada', 'incident', $incidentId);
            $this->set_notice('Incidencia creada.');
        } else {
            $this->set_notice('No se pudo crear la incidencia.', 'error');
        }

        $this->redirect_back('fpi-incidencias');
    }

    private function update_incident_status(): void
    {
        $incidentId = isset($_POST['incident_id']) ? (int) $_POST['incident_id'] : 0;
        $status = sanitize_key(wp_unslash((string) ($_POST['status'] ?? 'abierta')));
        $assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;

        if ($incidentId > 0 && FPI_Workflows::update_incident_status($incidentId, $status, $assignedTo)) {
            FPI_Audit::log('update_incident_status', 'incidencias', 'Incidencia actualizada', 'incident', $incidentId);
            $this->set_notice('Incidencia actualizada.');
        } else {
            $this->set_notice('No se pudo actualizar la incidencia.', 'error');
        }

        $this->redirect_back('fpi-incidencias');
    }

    private function create_request(): void
    {
        $requestType = sanitize_key(wp_unslash((string) ($_POST['request_type'] ?? '')));
        if ($requestType !== 'cambio_turno') {
            wp_die('Tipo de solicitud no válido.');
        }

        FPI_Access::require_permission(['solicitar_cambio_turno']);

        $requestId = FPI_Workflows::create_request([
            'request_type' => 'cambio_turno',
            'title' => (string) ($_POST['title'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'start_date' => (string) ($_POST['start_date'] ?? ''),
            'end_date' => (string) ($_POST['end_date'] ?? ''),
            'meta_json' => [
                'target_user_id' => ! empty($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : null,
            ],
        ]);

        if ($requestId > 0) {
            FPI_Notifications::create('cambio_turno', 'Nueva solicitud', 'Solicitud creada: ' . sanitize_text_field((string) ($_POST['title'] ?? '')), $requestId);
            FPI_Audit::log('create_request', 'cambio_turno', 'Solicitud creada', 'request', $requestId);
            $this->set_notice('Solicitud creada.');
        } else {
            $this->set_notice('No se pudo crear la solicitud.', 'error');
        }

        $this->redirect_back('fpi-cambios-turno');
    }

    private function create_vacation_slot(): void
    {
        $slotId = FPI_Workflows::create_vacation_slot([
            'title' => (string) ($_POST['title'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'start_date' => (string) ($_POST['start_date'] ?? ''),
            'end_date' => (string) ($_POST['end_date'] ?? ''),
            'slot_capacity' => ! empty($_POST['slot_capacity']) ? (int) $_POST['slot_capacity'] : 1,
        ]);

        if ($slotId > 0) {
            FPI_Notifications::create('vacaciones', 'Nueva franja de vacaciones', 'Dirección publicó una nueva franja disponible.', $slotId);
            FPI_Audit::log('create_vacation_slot', 'vacaciones', 'Franja de vacaciones creada', 'request', $slotId);
            $this->set_notice('Franja de vacaciones creada.');
        } else {
            $this->set_notice('No se pudo crear la franja de vacaciones.', 'error');
        }

        $this->redirect_back('fpi-vacaciones');
    }

    private function reserve_vacation_slot(): void
    {
        $slotId = isset($_POST['slot_request_id']) ? (int) $_POST['slot_request_id'] : 0;
        $notes = sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? '')));

        $requestId = $slotId > 0 ? FPI_Workflows::reserve_vacation_slot($slotId, $notes) : 0;
        if ($requestId > 0) {
            FPI_Notifications::create('vacaciones', 'Reserva de vacaciones', 'Se reservó una franja pendiente de aprobación.', $requestId);
            FPI_Audit::log('reserve_vacation_slot', 'vacaciones', 'Reserva de vacaciones creada', 'request', $requestId);
            $this->set_notice('Reserva enviada. Queda pendiente de aprobación.');
        } else {
            $this->set_notice('No se pudo reservar la franja.', 'error');
        }

        $this->redirect_back('fpi-vacaciones');
    }

    private function update_request_status(): void
    {
        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $requestType = sanitize_key(wp_unslash((string) ($_POST['request_type'] ?? 'vacaciones')));
        $status = sanitize_key(wp_unslash((string) ($_POST['status'] ?? 'pendiente')));

        if ($requestType === 'cambio_turno') {
            FPI_Access::require_permission(['aprobar_cambio_turno']);
        } else {
            FPI_Access::require_permission(['aprobar_vacaciones']);
        }

        $ok = false;
        if ($requestType === 'cambio_turno' && $status === 'cancelada' && $requestId > 0) {
            $ok = FPI_Workflows::delete_request($requestId);
            if ($ok) {
                FPI_Audit::log('delete_request', 'cambio_turno', 'Cambio de turno cancelado y eliminado', 'request', $requestId);
                $this->set_notice('Cambio de turno cancelado y eliminado.');
            }
        } elseif ($requestId > 0 && FPI_Workflows::update_request_status($requestId, $status)) {
            $ok = true;
            $request = FPI_Workflows::get_request_by_id($requestId);
            if ($request && $status === 'aprobada') {
                $label = $requestType === 'cambio_turno' ? 'Cambio de turno aprobado' : 'Vacaciones aprobadas';
                FPI_Notifications::create($requestType, $label, sanitize_text_field((string) ($request['title'] ?? 'Solicitud aprobada')), $requestId);
            }
            FPI_Audit::log('update_request_status', $requestType, 'Solicitud actualizada', 'request', $requestId);
            $this->set_notice('Solicitud actualizada.');
        }

        if (! $ok) {
            $this->set_notice('No se pudo actualizar la solicitud.', 'error');
        }

        $this->redirect_back($requestType === 'cambio_turno' ? 'fpi-cambios-turno' : 'fpi-vacaciones');
    }

    public function render_dashboard(): void
    {
        FPI_Access::require_permission(['ver_dashboard', 'ver_documentos', 'ver_almacenamiento']);

        $roles = FPI_Roles::get_all();
        $permissions = FPI_Permissions::get_all();
        $logs = FPI_Audit::latest(10);
        $notifications = FPI_Notifications::latest(8);
        $users = get_users(['fields' => ['ID']]);
        $pages = array_filter($this->get_menu_pages(), static fn(array $page): bool => $page['visible'] === true);
        $canSeeNotifications = FPI_Access::any(['gestionar_almacenamiento', 'gestionar_nominas', 'gestionar_documentos', 'gestionar_contabilidad', 'gestionar_estupes', 'ver_logs']);
        ?>
        <div class="wrap fpi-wrap">
            <h1>Portal Interno</h1>
            <div class="fpi-grid">
                <div class="fpi-card"><strong>Usuarios WP</strong><span><?php echo esc_html((string) count($users)); ?></span></div>
                <div class="fpi-card"><strong>Roles internos</strong><span><?php echo esc_html((string) count($roles)); ?></span></div>
                <div class="fpi-card"><strong>Permisos</strong><span><?php echo esc_html((string) count($permissions)); ?></span></div>
                <div class="fpi-card"><strong>Módulos visibles</strong><span><?php echo esc_html((string) count($pages)); ?></span></div>
                <?php if ($canSeeNotifications) : ?>
                    <div class="fpi-card"><strong>Avisos pendientes</strong><span><?php echo esc_html((string) FPI_Notifications::unread_count()); ?></span></div>
                <?php endif; ?>
            </div>
            <?php if ($canSeeNotifications) : ?>
                <div class="fpi-panel">
                    <div class="fpi-panel-header">
                        <h2>Avisos administrativos</h2>
                        <form method="post">
                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                            <input type="hidden" name="fpi_action" value="mark_notifications_read">
                            <button class="button">Marcar todo como leído</button>
                        </form>
                    </div>
                    <?php $this->render_notifications_table($notifications); ?>
                </div>
            <?php endif; ?>
            <div class="fpi-panel">
                <h2>Última actividad</h2>
                <?php $this->render_logs_table($logs); ?>
            </div>
        </div>
        <?php
    }

    public function render_users(): void
    {
        FPI_Access::require_permission(['gestionar_usuarios']);
        $users = get_users();
        $roles = FPI_Roles::get_all();
        $permissions = FPI_Permissions::get_all();
        ?>
        <div class="wrap fpi-wrap">
            <h1>Usuarios internos</h1>
            <div class="fpi-two-cols">
                <div class="fpi-panel">
                    <h2>Asignar rol</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="assign_role">
                        <select name="user_id" required><option value="">Usuario</option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option><?php endforeach; ?></select>
                        <select name="role_id" required><option value="">Rol</option><?php foreach ($roles as $role) : ?><option value="<?php echo esc_attr((string) $role['id']); ?>"><?php echo esc_html($role['name']); ?></option><?php endforeach; ?></select>
                        <button class="button button-primary">Asignar</button>
                    </form>
                </div>
                <div class="fpi-panel">
                    <h2>Asignar permiso directo</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="assign_permission">
                        <select name="user_id" required><option value="">Usuario</option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option><?php endforeach; ?></select>
                        <select name="permission_slug" required><option value="">Permiso</option><?php foreach ($permissions as $permission) : ?><option value="<?php echo esc_attr($permission['slug']); ?>"><?php echo esc_html($permission['name']); ?></option><?php endforeach; ?></select>
                        <select name="grant_type"><option value="allow">Allow</option><option value="deny">Deny</option></select>
                        <button class="button button-primary">Asignar</button>
                    </form>
                </div>
            </div>
            <div class="fpi-panel">
                <h2>Listado</h2>
                <table class="widefat striped">
                    <thead><tr><th>Usuario</th><th>Email</th><th>Roles internos</th><th>Permisos directos</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user) : ?>
                        <?php $userRoles = FPI_Roles::get_user_roles((int) $user->ID); ?>
                        <?php $userPermissions = FPI_Permissions::get_user_permissions((int) $user->ID); ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(implode(', ', array_map(static fn(array $r): string => $r['name'], $userRoles)) ?: '—'); ?></td>
                            <td><?php echo esc_html(implode(', ', array_map(static fn(array $p): string => $p['permission_slug'] . ':' . $p['grant_type'], $userPermissions)) ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_roles(): void
    {
        FPI_Access::require_permission(['gestionar_roles']);
        $roles = FPI_Roles::get_all();
        $permissions = FPI_Permissions::get_all();
        ?>
        <div class="wrap fpi-wrap">
            <h1>Roles internos</h1>
            <div class="fpi-two-cols">
                <div class="fpi-panel">
                    <h2>Crear rol</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="create_role">
                        <input type="text" name="name" placeholder="Nombre" required>
                        <input type="text" name="slug" placeholder="slug_rol" required>
                        <label><input type="checkbox" name="is_base_role" value="1"> Rol base</label>
                        <button class="button button-primary">Crear</button>
                    </form>
                </div>
                <div class="fpi-panel">
                    <h2>Permisos por rol</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="assign_role_permission">
                        <select name="role_id" required><option value="">Rol</option><?php foreach ($roles as $role) : ?><option value="<?php echo esc_attr((string) $role['id']); ?>"><?php echo esc_html($role['name']); ?></option><?php endforeach; ?></select>
                        <select name="permission_slug" required><option value="">Permiso</option><?php foreach ($permissions as $permission) : ?><option value="<?php echo esc_attr($permission['slug']); ?>"><?php echo esc_html($permission['name']); ?></option><?php endforeach; ?></select>
                        <button class="button button-primary">Asignar</button>
                    </form>
                </div>
            </div>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Slug</th><th>Nombre</th><th>Tipo</th><th>Permisos</th></tr></thead>
                    <tbody>
                    <?php foreach ($roles as $role) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $role['id']); ?></td>
                            <td><?php echo esc_html($role['slug']); ?></td>
                            <td><?php echo esc_html($role['name']); ?></td>
                            <td><?php echo (int) $role['is_base_role'] === 1 ? 'Base' : 'Secundario'; ?></td>
                            <td><?php echo esc_html(implode(', ', FPI_Permissions::get_role_permissions((int) $role['id'])) ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_permissions(): void
    {
        FPI_Access::require_permission(['gestionar_permisos']);
        $permissions = FPI_Permissions::get_all();
        ?>
        <div class="wrap fpi-wrap">
            <h1>Permisos</h1>
            <div class="fpi-panel">
                <h2>Crear permiso</h2>
                <form method="post">
                    <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                    <input type="hidden" name="fpi_action" value="create_permission">
                    <input type="text" name="name" placeholder="Nombre" required>
                    <input type="text" name="slug" placeholder="slug_permiso" required>
                    <input type="text" name="group_name" placeholder="grupo" value="general" required>
                    <button class="button button-primary">Crear</button>
                </form>
            </div>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Slug</th><th>Nombre</th><th>Grupo</th></tr></thead>
                    <tbody>
                    <?php foreach ($permissions as $permission) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $permission['id']); ?></td>
                            <td><?php echo esc_html($permission['slug']); ?></td>
                            <td><?php echo esc_html($permission['name']); ?></td>
                            <td><?php echo esc_html($permission['group_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_horarios(): void
    {
        if (! FPI_Storage::user_can_access_module('horarios')) { wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno')); }
        $url = FPI_Storage::get_module_url('horarios');
        $showAllVacations = FPI_Access::can('aprobar_vacaciones');
        $showAllShiftChanges = FPI_Access::can('aprobar_cambio_turno') || FPI_Roles::user_has_role_slug(get_current_user_id(), 'farmaceutico');
        $approvedVacations = FPI_Workflows::get_approved_vacations(get_current_user_id(), $showAllVacations);
        $approvedShiftChanges = FPI_Workflows::get_approved_shift_changes(get_current_user_id(), $showAllShiftChanges);
        FPI_Audit::log('view_module', 'horarios', 'Acceso a horarios');
        ?>
        <div class="wrap fpi-wrap">
            <h1>Horarios</h1>
            <div class="fpi-panel">
                <h2>Consulta tu horario aquí</h2>
                <p>El botón abre en una pestaña nueva el enlace configurado en Almacenamiento.</p>
                <?php if ($url !== '') : ?>
                    <p><a class="button button-primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Abrir horario</a></p>
                <?php else : ?>
                    <p>No hay horario configurado todavía.</p>
                <?php endif; ?>
            </div>
            <div class="fpi-panel">
                <h2>Vacaciones aprobadas por la dirección</h2>
                <?php $this->render_horarios_requests_table($approvedVacations, 'vacaciones'); ?>
            </div>
            <div class="fpi-panel">
                <h2>Cambios en el turno</h2>
                <p>Lo ven la persona implicada, administración y farmacéuticos.</p>
                <?php $this->render_horarios_requests_table($approvedShiftChanges, 'cambio_turno'); ?>
            </div>
        </div>
        <?php
    }

    public function render_nominas(): void
    {
        if (! FPI_Storage::user_can_access_module('nominas')) { wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno')); }

        $canManage = FPI_Access::any(['gestionar_nominas', 'ver_nominas_todas']);
        $selectedEmployeeId = isset($_GET['employee_user_id']) ? (int) wp_unslash((string) $_GET['employee_user_id']) : 0;
        $filterEmployeeId = $canManage ? ($selectedEmployeeId > 0 ? $selectedEmployeeId : null) : get_current_user_id();
        $items = FPI_Items::get(['module_slug' => 'nominas', 'employee_user_id' => $filterEmployeeId]);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $myFolderUrl = FPI_Storage::get_nominas_user_url(get_current_user_id());

        FPI_Audit::log('view_module', 'nominas', 'Acceso a nóminas');
        ?>
        <div class="wrap fpi-wrap">
            <h1><?php echo esc_html($canManage ? 'Nóminas' : 'Mis nóminas'); ?></h1>
            <div class="fpi-panel">
                <p><?php echo esc_html($canManage ? 'La subida y gestión administrativa siguen en Almacenamiento. Aquí puedes revisar descargas y configurar URLs por empleado desde Almacenamiento.' : 'Aquí tienes tu panel de descargas y, si está configurada, el acceso directo a tu carpeta de nóminas.'); ?></p>
                <?php if ($myFolderUrl !== '') : ?>
                    <p><a class="button button-primary" href="<?php echo esc_url($myFolderUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir mi carpeta de nóminas</a></p>
                <?php endif; ?>
                <?php if ($canManage) : ?>
                    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=fpi-storage')); ?>">Ir a Almacenamiento</a></p>
                    <form method="get" class="fpi-inline-form">
                        <input type="hidden" name="page" value="fpi-nominas">
                        <select name="employee_user_id">
                            <option value="0">Todos los empleados</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected($selectedEmployeeId, (int) $user->ID); ?>><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button">Filtrar</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><?php if ($canManage) : ?><th>Empleado</th><?php endif; ?><th>Responsable</th><th>Descarga</th></tr></thead>
                    <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="<?php echo esc_attr((string) ($canManage ? 4 : 3)); ?>">Sin nóminas todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($item['created_at'] ?? '—')); ?></td>
                                <?php if ($canManage) : ?><td><?php echo esc_html($this->get_user_label((int) ($item['employee_user_id'] ?? 0))); ?></td><?php endif; ?>
                                <td><?php echo esc_html($this->get_user_label((int) ($item['uploaded_by'] ?? 0))); ?></td>
                                <td><?php $this->render_download_form($item, $canManage); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_contabilidad(): void
    {
        if (! FPI_Storage::user_can_access_module('contabilidad')) { wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno')); }
        $this->render_monthly_upload_module('contabilidad', 'Contabilidad', FPI_Access::any(['gestionar_contabilidad', 'gestionar_documentos', 'subir_facturas']));
    }

    public function render_documentos(): void
    {
        if (! FPI_Storage::user_can_access_module('documentos')) { wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno')); }
        $this->render_monthly_upload_module('documentos', 'Documentos', FPI_Access::any(['gestionar_documentos', 'subir_documentos']));
    }

    public function render_estupes(): void
    {
        if (! FPI_Storage::user_can_access_module('estupes')) { wp_die(esc_html__('No tienes permisos para acceder a este apartado.', 'farmacia-portal-interno')); }
        $monthKey = $this->get_selected_month();
        $months = FPI_Items::get_estupe_available_months();
        $rows = FPI_Items::get_estupe_movements($monthKey);
        FPI_Audit::log('view_module', 'estupes', 'Acceso a estupefacientes');
        ?>
        <div class="wrap fpi-wrap">
            <h1>Estupefacientes</h1>
            <div class="fpi-panel">
                <form method="get" class="fpi-inline-form">
                    <input type="hidden" name="page" value="fpi-estupes">
                    <select name="month_key">
                        <?php foreach ($months as $month) : ?><option value="<?php echo esc_attr($month); ?>" <?php selected($monthKey, $month); ?>><?php echo esc_html($this->format_month_label($month)); ?></option><?php endforeach; ?>
                    </select>
                    <button class="button">Ver mes</button>
                </form>
                <form method="post" class="fpi-inline-form" style="margin-left:10px; display:inline-flex;">
                    <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                    <input type="hidden" name="fpi_action" value="export_estupes">
                    <input type="hidden" name="month_key" value="<?php echo esc_attr($monthKey); ?>">
                    <button class="button">Exportar Excel (CSV)</button>
                </form>
            </div>
            <div class="fpi-panel">
                <h2>Registrar movimiento</h2>
                <form method="post">
                    <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                    <input type="hidden" name="fpi_action" value="create_estupe_movement">
                    <div class="fpi-grid-form">
                        <input type="text" name="cn" placeholder="CN (Código Nacional)" required>
                        <input type="text" name="medicine_name" placeholder="Nombre del medicamento" required>
                        <select name="movement_type" required>
                            <option value="recepcion">Recepción</option>
                            <option value="venta">Venta</option>
                            <option value="devolucion">Devolución</option>
                        </select>
                        <input type="number" name="initial_stock" placeholder="Stock inicial" required>
                        <input type="number" name="final_stock" placeholder="Stock final" required>
                        <input type="text" name="pharmacist_name" placeholder="Farmacéutico que lo recepciona" required>
                    </div>
                    <p><button class="button button-primary">Guardar movimiento</button></p>
                </form>
            </div>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><th>CN</th><th>Medicamento</th><th>Movimiento</th><th>Stock inicial</th><th>Stock final</th><th>Farmacéutico</th></tr></thead>
                    <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="7">Sin movimientos este mes.</td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['movement_date'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['cn'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['medicine_name'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ucfirst((string) ($row['movement_type'] ?? ''))); ?></td>
                                <td><?php echo esc_html((string) ($row['initial_stock'] ?? '0')); ?></td>
                                <td><?php echo esc_html((string) ($row['final_stock'] ?? '0')); ?></td>
                                <td><?php echo esc_html((string) ($row['pharmacist_name'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_incidencias(): void
    {
        $all = FPI_Access::can('cerrar_incidencias');
        $incidents = FPI_Workflows::get_incidents($all);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        FPI_Audit::log('view_module', 'incidencias', 'Acceso a incidencias');
        ?>
        <div class="wrap fpi-wrap">
            <h1>Incidencias</h1>
            <div class="fpi-panel">
                <h2>Nueva incidencia</h2>
                <form method="post">
                    <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                    <input type="hidden" name="fpi_action" value="create_incident">
                    <div class="fpi-grid-form">
                        <input type="text" name="title" placeholder="Título" required>
                        <select name="incident_type"><option value="general">General</option><option value="precio">Precio</option><option value="stock">Stock</option><option value="pedido">Pedido</option></select>
                        <select name="priority"><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option></select>
                        <select name="shift_label"><option value="manana">Turno de mañana</option><option value="tarde">Turno de tarde</option></select>
                        <input type="date" name="incident_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                        <input type="time" name="incident_time" value="<?php echo esc_attr(current_time('H:i')); ?>" required>
                        <select name="assigned_to"><option value="">Asignar a</option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name); ?></option><?php endforeach; ?></select>
                        <textarea name="description" rows="4" placeholder="Detalle"></textarea>
                    </div>
                    <p><button class="button button-primary">Crear incidencia</button></p>
                </form>
            </div>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><th>Hora</th><th>Turno</th><th>Título</th><th>Tipo</th><th>Prioridad</th><th>Estado</th><th>Asignado</th><th>Detalle</th><?php if ($all) : ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (empty($incidents)) : ?>
                        <tr><td colspan="<?php echo esc_attr($all ? '10' : '9'); ?>">Sin incidencias todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($incidents as $incident) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($incident['incident_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($incident['incident_time'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ucfirst(str_replace('_', ' ', (string) ($incident['shift_label'] ?: '—')))); ?></td>
                                <td><?php echo esc_html((string) $incident['title']); ?></td>
                                <td><?php echo esc_html((string) $incident['type']); ?></td>
                                <td><?php echo esc_html((string) $incident['priority']); ?></td>
                                <td><?php echo esc_html((string) $incident['status']); ?></td>
                                <td><?php echo esc_html($this->get_user_label((int) ($incident['assigned_to'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) ($incident['description'] ?: '—')); ?></td>
                                <?php if ($all) : ?>
                                    <td>
                                        <form method="post" class="fpi-inline-form">
                                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                                            <input type="hidden" name="fpi_action" value="update_incident_status">
                                            <input type="hidden" name="incident_id" value="<?php echo esc_attr((string) $incident['id']); ?>">
                                            <select name="status"><option value="abierta" <?php selected((string) $incident['status'], 'abierta'); ?>>Abierta</option><option value="en_revision" <?php selected((string) $incident['status'], 'en_revision'); ?>>En revisión</option><option value="cerrada" <?php selected((string) $incident['status'], 'cerrada'); ?>>Cerrada</option></select>
                                            <button class="button button-small">Guardar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_cambios_turno(): void
    {
        FPI_Access::require_permission(['solicitar_cambio_turno', 'aprobar_cambio_turno']);
        $canApprove = FPI_Access::can('aprobar_cambio_turno');
        $canViewAll = $canApprove || FPI_Roles::user_has_role_slug(get_current_user_id(), 'farmaceutico');
        $requests = FPI_Workflows::get_requests('cambio_turno', $canViewAll, get_current_user_id());
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        FPI_Audit::log('view_module', 'cambio_turno', 'Acceso a cambios de turno');
        $this->render_requests_page('cambio_turno', 'Cambios de turno', 'Solicita cambios de turno. La persona implicada, administración y farmacéuticos pueden verlos.', $requests, $canApprove, $users);
    }

    public function render_vacaciones(): void
    {
        $canApprove = FPI_Access::can('aprobar_vacaciones');
        $slots = FPI_Workflows::get_vacation_slots(false);
        $reservations = FPI_Workflows::get_vacation_reservations($canApprove);
        FPI_Audit::log('view_module', 'vacaciones', 'Acceso a vacaciones');
        ?>
        <div class="wrap fpi-wrap">
            <h1>Vacaciones</h1>
            <?php if ($canApprove) : ?>
                <div class="fpi-panel">
                    <h2>Crear franja disponible</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="create_vacation_slot">
                        <div class="fpi-grid-form">
                            <input type="text" name="title" placeholder="Ej: Julio 2026 · Bloque 1" required>
                            <input type="date" name="start_date" required>
                            <input type="date" name="end_date" required>
                            <input type="number" name="slot_capacity" min="1" value="1" placeholder="Plazas" required>
                            <textarea name="description" rows="3" placeholder="Notas internas"></textarea>
                        </div>
                        <p><button class="button button-primary">Crear franja</button></p>
                    </form>
                </div>
            <?php endif; ?>
            <div class="fpi-panel">
                <h2>Franjas disponibles</h2>
                <table class="widefat striped">
                    <thead><tr><th>Franja</th><th>Inicio</th><th>Fin</th><th>Plazas</th><th>Reservas</th><th>Detalle</th><?php if (! $canApprove && FPI_Access::can('solicitar_vacaciones')) : ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (empty($slots)) : ?>
                        <tr><td colspan="<?php echo esc_attr($canApprove ? '6' : '7'); ?>">Sin franjas todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($slots as $slot) : $meta = FPI_Workflows::get_request_meta($slot); ?>
                            <tr>
                                <td><?php echo esc_html((string) $slot['title']); ?></td>
                                <td><?php echo esc_html((string) ($slot['start_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($slot['end_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($meta['slot_capacity'] ?? 1)); ?></td>
                                <td><?php echo esc_html((string) FPI_Workflows::count_slot_reservations((int) $slot['id'])); ?></td>
                                <td><?php echo esc_html((string) ($slot['description'] ?: '—')); ?></td>
                                <?php if (! $canApprove && FPI_Access::can('solicitar_vacaciones')) : ?>
                                    <td>
                                        <form method="post" class="fpi-inline-form">
                                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                                            <input type="hidden" name="fpi_action" value="reserve_vacation_slot">
                                            <input type="hidden" name="slot_request_id" value="<?php echo esc_attr((string) $slot['id']); ?>">
                                            <button class="button button-small">Reservar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="fpi-panel">
                <h2>Reservas pendientes y aprobadas</h2>
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><th>Inicio</th><th>Fin</th><th>Solicitante</th><th>Estado</th><th>Detalle</th><?php if ($canApprove) : ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (empty($reservations)) : ?>
                        <tr><td colspan="<?php echo esc_attr($canApprove ? '7' : '6'); ?>">Sin solicitudes todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($reservations as $request) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $request['created_at']); ?></td>
                                <td><?php echo esc_html((string) ($request['start_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($request['end_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html($this->get_user_label((int) ($request['requested_by'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) $request['status']); ?></td>
                                <td><?php echo esc_html((string) ($request['description'] ?: '—')); ?></td>
                                <?php if ($canApprove) : ?>
                                    <td>
                                        <form method="post" class="fpi-inline-form">
                                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                                            <input type="hidden" name="fpi_action" value="update_request_status">
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request['id']); ?>">
                                            <input type="hidden" name="request_type" value="vacaciones">
                                            <select name="status"><option value="pendiente" <?php selected((string) $request['status'], 'pendiente'); ?>>Pendiente</option><option value="aprobada" <?php selected((string) $request['status'], 'aprobada'); ?>>Aprobada</option><option value="rechazada" <?php selected((string) $request['status'], 'rechazada'); ?>>Rechazada</option></select>
                                            <button class="button button-small">Guardar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_storage_settings(): void
    {
        FPI_Access::require_permission(['gestionar_almacenamiento']);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        ?>
        <div class="wrap fpi-wrap">
            <h1>Almacenamiento</h1>
            <div class="fpi-panel">
                <p>La subida se hace directamente en la web. Synology se usa solo para <strong>visualizar el horario</strong> y para las <strong>URLs de nóminas por empleado</strong>.</p>
                <form method="post">
                    <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                    <input type="hidden" name="fpi_action" value="save_storage_settings">

                    <h2>Visualización externa</h2>
                    <table class="widefat striped">
                        <thead><tr><th>Módulo</th><th>URL compartida</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Horarios</td>
                                <td><input type="url" class="regular-text" name="fpi_storage_module_url_horarios" value="<?php echo esc_attr(FPI_Storage::get_module_url('horarios')); ?>" placeholder="https://... horario compartido"></td>
                            </tr>
                        </tbody>
                    </table>

                    <input type="hidden" name="fpi_storage_provider_synology_base_url" value="<?php echo esc_attr(FPI_Storage::get_provider_base_url('synology')); ?>">
                    <input type="hidden" name="fpi_storage_provider_gdrive_base_url" value="<?php echo esc_attr(FPI_Storage::get_provider_base_url('gdrive')); ?>">
                    <input type="hidden" name="fpi_storage_provider_onedrive_base_url" value="<?php echo esc_attr(FPI_Storage::get_provider_base_url('onedrive')); ?>">
                    <input type="hidden" name="fpi_storage_provider_mega_base_url" value="<?php echo esc_attr(FPI_Storage::get_provider_base_url('mega')); ?>">
                    <?php foreach (FPI_Storage::MODULES as $module => $label) : ?>
                        <input type="hidden" name="fpi_storage_module_provider_<?php echo esc_attr($module); ?>" value="synology">
                        <?php if ($module !== 'horarios') : ?>
                            <input type="hidden" name="fpi_storage_module_url_<?php echo esc_attr($module); ?>" value="<?php echo esc_attr(FPI_Storage::get_module_url($module)); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="fpi_storage_module_path_<?php echo esc_attr($module); ?>" value="">
                    <?php endforeach; ?>

                    <h2>URLs de nóminas por empleado</h2>
                    <table class="widefat striped">
                        <thead><tr><th>Usuario</th><th>URL carpeta / vista de nóminas</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></td>
                                <td><input type="url" class="regular-text" name="fpi_storage_nominas_user_urls[<?php echo esc_attr((string) $user->ID); ?>]" value="<?php echo esc_attr(FPI_Storage::get_nominas_user_url((int) $user->ID)); ?>" placeholder="https://... carpeta compartida de nóminas"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h2>Qué ve cada usuario</h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <?php foreach (FPI_Storage::MODULES as $module => $label) : ?>
                                    <th><?php echo esc_html($label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></td>
                                <?php foreach (FPI_Storage::MODULES as $module => $label) : ?>
                                    <td><label><input type="checkbox" name="fpi_user_module_access[<?php echo esc_attr((string) $user->ID); ?>][<?php echo esc_attr($module); ?>]" value="1" <?php checked(FPI_Storage::user_can_access_module($module, (int) $user->ID)); ?>></label></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><button class="button button-primary">Guardar</button></p>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_audit(): void
    {
        FPI_Access::require_permission(['ver_logs']);
        $logs = FPI_Audit::latest(100);
        ?>
        <div class="wrap fpi-wrap"><h1>Auditoría</h1><div class="fpi-panel"><?php $this->render_logs_table($logs); ?></div></div>
        <?php
    }

    private function render_link_module(string $module, string $title, string $description): void
    {
        $url = FPI_Storage::get_module_url($module);
        FPI_Audit::log('view_module', $module, sprintf('Acceso a %s', $title));
        ?>
        <div class="wrap fpi-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="fpi-panel">
                <p><?php echo esc_html($description); ?></p>
                <?php if ($url === '') : ?>
                    <p><strong>No hay URL configurada todavía.</strong></p>
                    <?php if (FPI_Access::can('gestionar_almacenamiento')) : ?><p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=fpi-storage')); ?>">Configurar almacenamiento</a></p><?php endif; ?>
                <?php else : ?>
                    <p><a class="button button-primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Abrir carpeta</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_items_module(string $module, string $title, string $description, bool $canManage, bool $perEmployee = false, ?int $employeeUserId = null): void
    {
        $items = FPI_Items::get(['module_slug' => $module, 'employee_user_id' => $employeeUserId]);
        $folderUrl = FPI_Storage::get_module_url($module);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $canDelete = $canManage;
        $canUpload = $canManage;

        if ($module === 'contabilidad' && FPI_Access::can('subir_facturas')) {
            $canUpload = true;
        }

        if ($module === 'documentos' && FPI_Access::can('subir_documentos')) {
            $canUpload = true;
        }

        FPI_Audit::log('view_module', $module, sprintf('Acceso a %s', $title));
        ?>
        <div class="wrap fpi-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="fpi-panel">
                <p><?php echo esc_html($description); ?></p>
                <?php if ($folderUrl !== '') : ?><p><a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($folderUrl); ?>">Abrir carpeta del módulo</a></p><?php endif; ?>
            </div>
            <?php if ($canUpload) : ?>
                <div class="fpi-panel">
                    <h2>Añadir elemento</h2>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="add_storage_item">
                        <input type="hidden" name="module_slug" value="<?php echo esc_attr($module); ?>">
                        <div class="fpi-grid-form">
                            <input type="text" name="title" placeholder="Título" required>
                            <input type="text" name="folder_name" placeholder="Carpeta / etiqueta manual">
                            <?php if ($perEmployee) : ?>
                                <select name="employee_user_id" required>
                                    <option value="">Empleado</option>
                                    <?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option><?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <input type="url" name="external_url" placeholder="Enlace externo opcional">
                            <input type="file" name="uploaded_file">
                            <textarea name="notes" rows="3" placeholder="Notas"></textarea>
                        </div>
                        <p><button class="button button-primary">Guardar</button></p>
                    </form>
                </div>
            <?php endif; ?>
            <div class="fpi-panel">
                <h2>Listado</h2>
                <table class="widefat striped">
                    <thead><tr><th>Título</th><?php if ($perEmployee) : ?><th>Empleado</th><?php endif; ?><th>Carpeta</th><th>Origen</th><th>Enlace / Ruta</th><th>Fecha subida</th><th>Subido por</th><th>Notas</th><?php if ($canDelete) : ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="<?php echo esc_attr((string) ($perEmployee ? ($canDelete ? 9 : 8) : ($canDelete ? 8 : 7))); ?>">Sin elementos todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $item['title']); ?></td>
                                <?php if ($perEmployee) : ?><td><?php echo esc_html($this->get_user_label((int) ($item['employee_user_id'] ?? 0))); ?></td><?php endif; ?>
                                <td><?php echo esc_html((string) ($item['folder_name'] ?: '—')); ?></td>
                                <td><?php echo esc_html(FPI_Storage::PROVIDERS[(string) ($item['provider'] ?? 'synology')] ?? (string) ($item['provider'] ?? '')); ?></td>
                                <td>
                                    <?php if (! empty($item['external_url'])) : ?>
                                        <a class="button button-small" href="<?php echo esc_url((string) $item['external_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                                    <?php elseif (! empty($item['storage_path'])) : ?>
                                        <code><?php echo esc_html((string) $item['storage_path']); ?></code>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) ($item['created_at'] ?? '—')); ?></td>
                                <td><?php echo esc_html($this->get_user_label((int) ($item['uploaded_by'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) ($item['notes'] ?: '—')); ?></td>
                                <?php if ($canDelete) : ?>
                                    <td>
                                        <form method="post" onsubmit="return confirm('¿Eliminar elemento?');">
                                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                                            <input type="hidden" name="fpi_action" value="delete_storage_item">
                                            <input type="hidden" name="module_slug" value="<?php echo esc_attr($module); ?>">
                                            <input type="hidden" name="item_id" value="<?php echo esc_attr((string) $item['id']); ?>">
                                            <button class="button button-small">Borrar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_requests_page(string $requestType, string $title, string $description, array $requests, bool $canApprove, array $users): void
    {
        ?>
        <div class="wrap fpi-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <?php if (($requestType === 'cambio_turno' && FPI_Access::can('solicitar_cambio_turno')) || ($requestType === 'vacaciones' && FPI_Access::can('solicitar_vacaciones'))) : ?>
                <div class="fpi-panel">
                    <h2>Nueva solicitud</h2>
                    <form method="post">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="create_request">
                        <input type="hidden" name="request_type" value="<?php echo esc_attr($requestType); ?>">
                        <div class="fpi-grid-form">
                            <input type="text" name="title" placeholder="Título" required>
                            <input type="date" name="start_date" required>
                            <input type="date" name="end_date">
                            <?php if ($requestType === 'cambio_turno') : ?>
                                <select name="target_user_id"><option value="">Intercambio con...</option><?php foreach ($users as $user) : if ((int) $user->ID === get_current_user_id()) { continue; } ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name); ?></option><?php endforeach; ?></select>
                            <?php endif; ?>
                            <textarea name="description" rows="4" placeholder="Detalle o motivo"></textarea>
                        </div>
                        <p><button class="button button-primary">Enviar solicitud</button></p>
                    </form>
                </div>
            <?php endif; ?>
            <div class="fpi-panel">
                <p><?php echo esc_html($description); ?></p>
                <table class="widefat striped">
                    <thead><tr><th>Fecha</th><th>Título</th><th>Inicio</th><th>Fin</th><th>Solicitante</th><th>Estado</th><th>Detalle</th><?php if ($canApprove) : ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (empty($requests)) : ?>
                        <tr><td colspan="<?php echo esc_attr($canApprove ? '8' : '7'); ?>">Sin solicitudes todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($requests as $request) : ?>
                            <?php $meta = ! empty($request['meta_json']) ? json_decode((string) $request['meta_json'], true) : []; ?>
                            <tr>
                                <td><?php echo esc_html((string) $request['created_at']); ?></td>
                                <td><?php echo esc_html((string) $request['title']); ?></td>
                                <td><?php echo esc_html((string) ($request['start_date'] ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($request['end_date'] ?: '—')); ?></td>
                                <td>
                                    <?php echo esc_html($this->get_user_label((int) ($request['requested_by'] ?? 0))); ?>
                                    <?php if ($requestType === 'cambio_turno' && ! empty($meta['target_user_id'])) : ?>
                                        <br><small>Con: <?php echo esc_html($this->get_user_label((int) $meta['target_user_id'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) $request['status']); ?></td>
                                <td><?php echo esc_html((string) ($request['description'] ?: '—')); ?></td>
                                <?php if ($canApprove) : ?>
                                    <td>
                                        <form method="post" class="fpi-inline-form">
                                            <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                                            <input type="hidden" name="fpi_action" value="update_request_status">
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $request['id']); ?>">
                                            <input type="hidden" name="request_type" value="<?php echo esc_attr($requestType); ?>">
                                            <select name="status">
                                                <option value="pendiente" <?php selected((string) $request['status'], 'pendiente'); ?>>Pendiente</option>
                                                <option value="aprobada" <?php selected((string) $request['status'], 'aprobada'); ?>>Aprobada</option>
                                                <option value="rechazada" <?php selected((string) $request['status'], 'rechazada'); ?>>Rechazada</option>
                                                <option value="cancelada" <?php selected((string) $request['status'], 'cancelada'); ?>>Cancelada</option>
                                            </select>
                                            <button class="button button-small">Guardar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }


    private function render_horarios_requests_table(array $requests, string $requestType): void
    {
        ?>
        <table class="widefat striped">
            <thead>
                <?php if ($requestType === 'vacaciones') : ?>
                    <tr><th>Título</th><th>Inicio</th><th>Fin</th><th>Empleado</th><th>Detalle</th></tr>
                <?php else : ?>
                    <tr><th>Título</th><th>Inicio</th><th>Fin</th><th>Solicitante</th><th>Detalle</th></tr>
                <?php endif; ?>
            </thead>
            <tbody>
            <?php if (empty($requests)) : ?>
                <tr><td colspan="5">Sin registros aprobados.</td></tr>
            <?php else : ?>
                <?php foreach ($requests as $request) : $meta = FPI_Workflows::get_request_meta($request); ?>
                    <tr>
                        <td><?php echo esc_html((string) $request['title']); ?></td>
                        <td><?php echo esc_html((string) ($request['start_date'] ?: '—')); ?></td>
                        <td><?php echo esc_html((string) ($request['end_date'] ?: '—')); ?></td>
                        <td>
                            <?php echo esc_html($this->get_user_label((int) ($request['requested_by'] ?? 0))); ?>
                            <?php if ($requestType === 'cambio_turno' && ! empty($meta['target_user_id'])) : ?>
                                <br><small>Con: <?php echo esc_html($this->get_user_label((int) $meta['target_user_id'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) ($request['description'] ?: '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function format_time_range(array $meta): string
    {
        $start = sanitize_text_field((string) ($meta['start_time'] ?? ''));
        $end = sanitize_text_field((string) ($meta['end_time'] ?? ''));

        if ($start !== '' && $end !== '') {
            return $start . ' - ' . $end;
        }

        if ($start !== '') {
            return $start;
        }

        if ($end !== '') {
            return $end;
        }

        return '—';
    }

    private function get_user_label(int $userId): string
    {
        if ($userId < 1) {
            return '—';
        }

        $user = get_userdata($userId);
        if (! $user instanceof WP_User) {
            return '—';
        }

        return $user->display_name . ' (' . $user->user_login . ')';
    }

    private function get_selected_month(): string
    {
        $monthKey = isset($_GET['month_key']) ? sanitize_text_field(wp_unslash((string) $_GET['month_key'])) : current_time('Y-m');
        return preg_match('/^\d{4}-\d{2}$/', $monthKey) ? $monthKey : current_time('Y-m');
    }

    private function format_month_label(string $monthKey): string
    {
        $dt = DateTime::createFromFormat('Y-m', $monthKey);
        return $dt instanceof DateTime ? $dt->format('m/Y') : $monthKey;
    }


    private function build_pdf_download_from_item(array $item, string $fileName): array
    {
        $storagePath = (string) ($item['storage_path'] ?? '');
        $absolute = FPI_Storage::get_absolute_local_path_from_storage_path($storagePath);
        if ($absolute === '' || ! file_exists($absolute)) {
            return ['success' => false, 'message' => 'Solo se puede generar PDF desde archivos locales existentes.'];
        }

        return $this->build_pdf_download_from_image_file($absolute, $fileName);
    }

    private function build_pdf_download_from_image_file(string $absolutePath, string $fileName): array
    {
        $mime = (string) (wp_check_filetype(basename($absolutePath))['type'] ?? '');
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['success' => false, 'message' => 'El archivo no es una imagen compatible para PDF.'];
        }

        $prepared = $this->prepare_jpeg_bytes_for_pdf($absolutePath, $mime);
        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $jpeg = (string) ($prepared['jpeg'] ?? '');
        $width = (int) ($prepared['width'] ?? 0);
        $height = (int) ($prepared['height'] ?? 0);

        if ($jpeg === '' || $width < 1 || $height < 1) {
            return ['success' => false, 'message' => 'No se pudo preparar la imagen para PDF.'];
        }

        $pdfBody = $this->build_single_image_pdf($jpeg, $width, $height);
        $pdfName = sanitize_file_name(pathinfo($fileName, PATHINFO_FILENAME) . '.pdf');

        return [
            'success' => true,
            'body' => $pdfBody,
            'file_name' => $pdfName,
        ];
    }

    private function prepare_jpeg_bytes_for_pdf(string $absolutePath, string $mime): array
    {
        if ($mime === 'image/jpeg') {
            $info = @getimagesize($absolutePath);
            $jpeg = @file_get_contents($absolutePath);
            if (! is_array($info) || $jpeg === false) {
                return ['success' => false, 'message' => 'No se pudo leer la imagen JPEG.'];
            }

            return [
                'success' => true,
                'jpeg' => $jpeg,
                'width' => (int) ($info[0] ?? 0),
                'height' => (int) ($info[1] ?? 0),
            ];
        }

        $viaWpEditor = $this->prepare_jpeg_bytes_via_wp_editor($absolutePath);
        if ($viaWpEditor['success'] ?? false) {
            return $viaWpEditor;
        }

        $viaImagick = $this->prepare_jpeg_bytes_via_imagick($absolutePath);
        if ($viaImagick['success'] ?? false) {
            return $viaImagick;
        }

        $viaGd = $this->prepare_jpeg_bytes_via_gd($absolutePath);
        if ($viaGd['success'] ?? false) {
            return $viaGd;
        }

        return ['success' => false, 'message' => 'El servidor no pudo convertir la imagen a PDF. Activa GD o Imagick en PHP.'];
    }

    private function prepare_jpeg_bytes_via_wp_editor(string $absolutePath): array
    {
        if (! function_exists('wp_get_image_editor')) {
            $wpImage = ABSPATH . 'wp-admin/includes/image.php';
            if (file_exists($wpImage)) {
                require_once $wpImage;
            }
        }

        if (! function_exists('wp_get_image_editor')) {
            return ['success' => false, 'message' => 'wp_get_image_editor no está disponible.'];
        }

        $editor = wp_get_image_editor($absolutePath);
        if (is_wp_error($editor)) {
            return ['success' => false, 'message' => $editor->get_error_message()];
        }

        $tempFile = wp_tempnam('fpi-pdf-jpeg');
        if (! is_string($tempFile) || $tempFile === '') {
            return ['success' => false, 'message' => 'No se pudo crear un temporal para PDF.'];
        }

        $saved = $editor->save($tempFile, 'image/jpeg');
        if (is_wp_error($saved) || empty($saved['path'])) {
            @unlink($tempFile);
            return ['success' => false, 'message' => is_wp_error($saved) ? $saved->get_error_message() : 'No se pudo guardar el JPEG temporal.'];
        }

        $path = (string) $saved['path'];
        $info = @getimagesize($path);
        $jpeg = @file_get_contents($path);
        @unlink($path);

        if (! is_array($info) || $jpeg === false || $jpeg === '') {
            return ['success' => false, 'message' => 'No se pudo leer el JPEG temporal.'];
        }

        return [
            'success' => true,
            'jpeg' => $jpeg,
            'width' => (int) ($info[0] ?? 0),
            'height' => (int) ($info[1] ?? 0),
        ];
    }

    private function prepare_jpeg_bytes_via_imagick(string $absolutePath): array
    {
        if (! class_exists('Imagick')) {
            return ['success' => false, 'message' => 'Imagick no disponible.'];
        }

        try {
            $image = new Imagick($absolutePath);
            $image->setImageBackgroundColor('white');
            if (method_exists($image, 'mergeImageLayers')) {
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(92);
            $jpeg = (string) $image->getImagesBlob();
            $width = (int) $image->getImageWidth();
            $height = (int) $image->getImageHeight();
            $image->clear();
            $image->destroy();

            if ($jpeg === '' || $width < 1 || $height < 1) {
                return ['success' => false, 'message' => 'Imagick no devolvió una imagen válida.'];
            }

            return [
                'success' => true,
                'jpeg' => $jpeg,
                'width' => $width,
                'height' => $height,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function prepare_jpeg_bytes_via_gd(string $absolutePath): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return ['success' => false, 'message' => 'GD no disponible.'];
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false) {
            return ['success' => false, 'message' => 'No se pudo leer la imagen.'];
        }

        $source = @imagecreatefromstring($raw);
        if (! $source) {
            return ['success' => false, 'message' => 'No se pudo procesar la imagen.'];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) {
            imagedestroy($source);
            return ['success' => false, 'message' => 'No se pudo preparar el lienzo de conversión.'];
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 92);
        $jpeg = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if ($jpeg === '') {
            return ['success' => false, 'message' => 'No se pudo convertir la imagen a JPEG para el PDF.'];
        }

        return [
            'success' => true,
            'jpeg' => $jpeg,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function build_single_image_pdf(string $jpegData, int $imageWidthPx, int $imageHeightPx): string
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $margin = 36.0;
        $maxWidth = $pageWidth - (2 * $margin);
        $maxHeight = $pageHeight - (2 * $margin);
        $scale = min($maxWidth / max($imageWidthPx, 1), $maxHeight / max($imageHeightPx, 1));
        $drawWidth = $imageWidthPx * $scale;
        $drawHeight = $imageHeightPx * $scale;
        $x = ($pageWidth - $drawWidth) / 2;
        $y = ($pageHeight - $drawHeight) / 2;

        $content = "q
" .
            sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $drawWidth, $drawHeight, $x, $y) . "
" .
            "/Im0 Do
Q
";

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf('%.2F %.2F', $pageWidth, $pageHeight) . "] /Resources << /ProcSet [/PDF /ImageC] /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /XObject /Subtype /Image /Width {$imageWidthPx} /Height {$imageHeightPx} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>
stream
{$jpegData}
endstream";
        $objects[] = "<< /Length " . strlen($content) . " >>
stream
{$content}endstream";

        $pdf = "%PDF-1.4
";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj
" . $object . "
endobj
";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref
0 " . (count($objects) + 1) . "
";
        $pdf .= "0000000000 65535 f 
";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "
";
        }
        $pdf .= "trailer
<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>
startxref
{$xrefPosition}
%%EOF";

        return $pdf;
    }

    private function storage_item_supports_pdf(array $item): bool
    {
        $fileName = strtolower((string) ($item['file_name'] ?? basename((string) ($item['storage_path'] ?? ''))));
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            if (str_ends_with($fileName, '.' . $ext)) {
                return true;
            }
        }

        return false;
    }

    private function send_binary_download(string $body, string $contentType, string $fileName): void
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', 'Off');

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $this->send_download_headers($contentType, $fileName, strlen($body));
        echo $body;
        exit;
    }

    private function send_download_headers(string $contentType, string $fileName, ?int $contentLength = null): void
    {
        $safeFileName = $this->build_content_disposition_filename($fileName);
        nocache_headers();
        header('Content-Type: ' . $contentType);
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: attachment; filename=\"{$safeFileName}\"; filename*=UTF-8''" . rawurlencode($fileName));
    }

    private function build_content_disposition_filename(string $fileName): string
    {
        $fileName = trim($fileName);
        if ($fileName === '') {
            return 'archivo';
        }

        $fileName = str_replace(["
", "
", '"', ';'], '', $fileName);
        $fileName = basename($fileName);
        $sanitized = sanitize_file_name($fileName);

        return $sanitized !== '' ? $sanitized : 'archivo';
    }

    private function render_download_form(array $item, bool $allowDelete = false): void
    {
        $supportsPdf = $this->storage_item_supports_pdf($item);
        ?>
        <?php if ($supportsPdf) : ?>
            <form method="post" class="fpi-inline-form">
                <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                <input type="hidden" name="fpi_action" value="download_storage_item_pdf">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) ($item['id'] ?? 0)); ?>">
                <button class="button button-small">Descargar PDF</button>
            </form>
            <form method="post" class="fpi-inline-form" style="margin-left:6px;">
                <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                <input type="hidden" name="fpi_action" value="download_storage_item">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) ($item['id'] ?? 0)); ?>">
                <button class="button button-small">Original</button>
            </form>
        <?php else : ?>
            <form method="post" class="fpi-inline-form">
                <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                <input type="hidden" name="fpi_action" value="download_storage_item">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) ($item['id'] ?? 0)); ?>">
                <button class="button button-small">Descargar</button>
            </form>
        <?php endif; ?>
        <?php if ($allowDelete) : ?>
            <form method="post" class="fpi-inline-form" onsubmit="return confirm('¿Eliminar elemento?');" style="margin-left:6px;">
                <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                <input type="hidden" name="fpi_action" value="delete_storage_item">
                <input type="hidden" name="module_slug" value="<?php echo esc_attr((string) ($item['module_slug'] ?? '')); ?>">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) ($item['id'] ?? 0)); ?>">
                <button class="button button-small">Eliminar</button>
            </form>
        <?php endif;
    }

    private function render_monthly_upload_module(string $module, string $title, bool $canUpload): void
    {
        $monthKey = $this->get_selected_month();
        $months = FPI_Items::get_available_months($module);
        $items = FPI_Items::get(['module_slug' => $module, 'month_key' => $monthKey]);
        $canDelete = FPI_Access::any(['gestionar_documentos', 'gestionar_contabilidad']);
        FPI_Audit::log('view_module', $module, 'Acceso a ' . $title);
        ?>
        <div class="wrap fpi-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="fpi-panel">
                <form method="get" class="fpi-inline-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr('fpi-' . $module); ?>">
                    <select name="month_key">
                        <?php foreach ($months as $month) : ?><option value="<?php echo esc_attr($month); ?>" <?php selected($monthKey, $month); ?>><?php echo esc_html($this->format_month_label($month)); ?></option><?php endforeach; ?>
                    </select>
                    <button class="button">Ver mes</button>
                </form>
                <p style="margin-top:10px; margin-bottom:0;">Los archivos se guardan en la web y quedan descargables desde aquí.</p>
            </div>
            <?php if ($canUpload) : ?>
                <div class="fpi-panel">
                    <form method="post" enctype="multipart/form-data" class="fpi-inline-form">
                        <?php wp_nonce_field('fpi_admin_action', 'fpi_nonce'); ?>
                        <input type="hidden" name="fpi_action" value="add_storage_item">
                        <input type="hidden" name="module_slug" value="<?php echo esc_attr($module); ?>">
                        <input type="file" name="uploaded_file" required>
                        <button class="button button-primary">Subir</button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="fpi-panel">
                <table class="widefat striped">
                    <thead><tr><th>Fecha y hora</th><th>Responsable</th><th>Archivo</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="4">Sin archivos este mes.</td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($item['created_at'] ?? '—')); ?></td>
                                <td><?php echo esc_html($this->get_user_label((int) ($item['uploaded_by'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) (! empty($item['file_name']) ? $item['file_name'] : $item['title'])); ?></td>
                                <td><?php $this->render_download_form($item, $canDelete); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_logs_table(array $logs): void
    {
        ?>
        <table class="widefat striped">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="6">Sin registros todavía.</td></tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $log['created_at']); ?></td>
                        <td><?php echo esc_html($log['user_id'] ? $this->get_user_label((int) $log['user_id']) : 'Sistema'); ?></td>
                        <td><?php echo esc_html((string) $log['module']); ?></td>
                        <td><?php echo esc_html((string) $log['action']); ?></td>
                        <td><?php echo esc_html((string) $log['description']); ?></td>
                        <td><?php echo esc_html((string) $log['ip_address']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_notifications_table(array $notifications): void
    {
        ?>
        <table class="widefat striped">
            <thead><tr><th>Fecha</th><th>Módulo</th><th>Título</th><th>Detalle</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if (empty($notifications)) : ?>
                <tr><td colspan="5">Sin avisos todavía.</td></tr>
            <?php else : ?>
                <?php foreach ($notifications as $notification) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $notification['created_at']); ?></td>
                        <td><?php echo esc_html((string) $notification['module']); ?></td>
                        <td><?php echo esc_html((string) $notification['title']); ?></td>
                        <td><?php echo esc_html((string) $notification['message']); ?></td>
                        <td><?php echo (int) ($notification['is_read'] ?? 0) === 1 ? 'Leído' : 'Pendiente'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
}
