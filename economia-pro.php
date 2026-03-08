<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 2.8
 * Author: Loki
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('EconomiaPro')) {
final class EconomiaPro {
    private const OPTION_PASSWORD = 'ecopro_front_password';
    private const OPTION_PAGE_ID  = 'ecopro_front_page_id';
    private const CRON_HOOK       = 'ecopro_daily_check';

    private string $table_transactions;
    private string $table_categories;
    private string $table_budgets;
    private string $table_notifications;

    public function __construct() {
        global $wpdb;
        $this->table_transactions  = $wpdb->prefix . 'eco_transactions';
        $this->table_categories    = $wpdb->prefix . 'eco_categories';
        $this->table_budgets       = $wpdb->prefix . 'eco_budgets';
        $this->table_notifications = $wpdb->prefix . 'eco_notifications';

        register_activation_hook(__FILE__, [$this,'install']);
        register_deactivation_hook(__FILE__, [$this,'deactivate']);
        add_action('init', [$this,'maybe_install']);
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_post_ecopro_add_tx', [$this,'add_tx']);
        add_action('admin_post_ecopro_update_tx', [$this,'update_tx']);
        add_action('admin_post_ecopro_save_settings', [$this,'save_settings']);
        add_action('admin_post_ecopro_add_category', [$this,'add_category']);
        add_action('admin_post_ecopro_save_budget', [$this,'save_budget']);
        add_action('admin_post_ecopro_mark_notice_read', [$this,'mark_notice_read']);
        add_action('admin_post_ecopro_mark_all_notices_read', [$this,'mark_all_notices_read']);
        add_action('admin_post_ecopro_export_csv', [$this,'export_csv']);
        add_action(self::CRON_HOOK, [$this,'run_daily_checks']);
        add_shortcode('economia_dashboard', [$this,'dashboard']);
    }

    public function menu(): void {
        add_menu_page('Economía Pro', 'Economía', 'manage_options', 'eco-pro', [$this, 'admin_page'], 'dashicons-chart-line', 26);
    }

    public function install(): void {
        $this->create_or_update_tables();
        $this->seed_default_categories();
        $this->schedule_cron();
    }

    public function deactivate(): void {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, self::CRON_HOOK);
    }

    private function schedule_cron(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public function maybe_install(): void {
        $this->create_or_update_tables();
        $this->seed_default_categories();
        $this->schedule_cron();
    }

    private function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
    }

    private function column_exists(string $table, string $column): bool {
        global $wpdb;
        return (bool) $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private function create_or_update_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        if (!$this->table_exists($this->table_categories)) {
            $wpdb->query("CREATE TABLE {$this->table_categories} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(191) NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'expense',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY eco_slug (slug),
                KEY eco_type (type)
            ) {$charset}");
        } else {
            if (!$this->column_exists($this->table_categories, 'slug')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN slug VARCHAR(191) NULL AFTER name");
            }
            if (!$this->column_exists($this->table_categories, 'type')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'expense' AFTER slug");
            }
            if (!$this->column_exists($this->table_categories, 'created_at')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER type");
            }
        }

        if (!$this->table_exists($this->table_transactions)) {
            $wpdb->query("CREATE TABLE {$this->table_transactions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(20) NOT NULL,
                category_id BIGINT UNSIGNED NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY eco_type (type),
                KEY eco_category_id (category_id),
                KEY eco_created_at (created_at)
            ) {$charset}");
        }

        if (!$this->table_exists($this->table_budgets)) {
            $wpdb->query("CREATE TABLE {$this->table_budgets} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id BIGINT UNSIGNED NOT NULL,
                period_month CHAR(7) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY eco_budget_month_category (category_id, period_month),
                KEY eco_budget_period (period_month)
            ) {$charset}");
        } else {
            if (!$this->column_exists($this->table_budgets, 'category_id')) {
                $wpdb->query("ALTER TABLE {$this->table_budgets} ADD COLUMN category_id BIGINT UNSIGNED NOT NULL AFTER id");
            }
            if (!$this->column_exists($this->table_budgets, 'period_month')) {
                $wpdb->query("ALTER TABLE {$this->table_budgets} ADD COLUMN period_month CHAR(7) NOT NULL AFTER category_id");
            }
            if (!$this->column_exists($this->table_budgets, 'amount')) {
                $wpdb->query("ALTER TABLE {$this->table_budgets} ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER period_month");
            }
            if (!$this->column_exists($this->table_budgets, 'created_at')) {
                $wpdb->query("ALTER TABLE {$this->table_budgets} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER amount");
            }
            if (!$this->column_exists($this->table_budgets, 'updated_at')) {
                $wpdb->query("ALTER TABLE {$this->table_budgets} ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at");
            }
        }

        if (!$this->table_exists($this->table_notifications)) {
            $wpdb->query("CREATE TABLE {$this->table_notifications} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                category_id BIGINT UNSIGNED NULL,
                period_month CHAR(7) NULL,
                message VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY eco_notice_unique (type, category_id, period_month),
                KEY eco_notice_status (status),
                KEY eco_notice_created (created_at)
            ) {$charset}");
        }
    }

    private function unique_slug(string $name, int $exclude_id = 0): string {
        global $wpdb;
        $base = sanitize_title($name);
        if ($base === '') $base = 'categoria';
        $slug = $base;
        $i = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_categories} WHERE slug = %s AND id != %d", $slug, $exclude_id)) > 0) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function backfill_missing_slugs(): void {
        global $wpdb;
        if (!$this->column_exists($this->table_categories, 'slug')) return;
        $rows = $wpdb->get_results("SELECT id, name, slug FROM {$this->table_categories} ORDER BY id ASC");
        if (!$rows) return;
        foreach ($rows as $row) {
            if (!empty($row->slug)) continue;
            $slug = $this->unique_slug((string)$row->name, (int)$row->id);
            $wpdb->update($this->table_categories, ['slug' => $slug], ['id' => (int)$row->id], ['%s'], ['%d']);
        }
    }

    private function seed_default_categories(): void {
        global $wpdb;
        if (!$this->table_exists($this->table_categories)) return;
        $this->backfill_missing_slugs();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");
        if ($count > 0) return;
        foreach ([['Salario','income'],['Extra','income'],['Comida','expense'],['Transporte','expense'],['Hogar','expense']] as $cat) {
            $wpdb->insert($this->table_categories, ['name'=>$cat[0],'slug'=>$this->unique_slug($cat[0]),'type'=>$cat[1]], ['%s','%s','%s']);
        }
    }

    public function run_daily_checks(): void {
        global $wpdb;
        $period = $this->get_current_period();
        $rows = $this->get_budget_rows($period);
        foreach ($rows as $row) {
            $spent = (float) $row->spent_amount;
            $budget = (float) $row->budget_amount;
            if ($budget <= 0) continue;
            if ($spent > $budget) {
                $message = sprintf('Atención: %s supera su presupuesto mensual (%s € de %s €).', $row->name, number_format($spent, 2, ',', '.'), number_format($budget, 2, ',', '.'));
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$this->table_notifications} (type, category_id, period_month, message, status) VALUES (%s, %d, %s, %s, %s)
                     ON DUPLICATE KEY UPDATE message = VALUES(message), status = 'open'",
                    'budget_exceeded', (int)$row->category_id, $period, $message, 'open'
                ));
            }
        }
    }

    private function get_notifications(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT n.*, c.name AS category_name FROM {$this->table_notifications} n LEFT JOIN {$this->table_categories} c ON c.id = n.category_id ORDER BY n.created_at DESC LIMIT 20");
    }


    public function mark_notice_read(): void {
        check_admin_referer('ecopro_mark_notice_read');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $notice_id = isset($_POST['notice_id']) ? absint($_POST['notice_id']) : 0;
        if ($notice_id <= 0) $this->safe_back_redirect('invalid');
        $result = $wpdb->update($this->table_notifications, ['status' => 'read'], ['id' => $notice_id], ['%s'], ['%d']);
        if ($result === false) { error_log('economia-pro mark_notice_read DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('notice_read');
    }

    public function mark_all_notices_read(): void {
        check_admin_referer('ecopro_mark_all_notices_read');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $result = $wpdb->query("UPDATE {$this->table_notifications} SET status = 'read' WHERE status = 'open'");
        if ($result === false) { error_log('economia-pro mark_all_notices_read DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('all_notices_read');
    }

    private function get_current_period(): string { return current_time('Y-m'); }

    private function get_totals(): array {
        global $wpdb;
        $income = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='income'");
        $expense = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='expense'");
        return ['income'=>$income,'expense'=>$expense,'balance'=>$income-$expense];
    }

    private function get_categories(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_categories} ORDER BY type ASC, name ASC");
    }


    private function get_filter_values(): array {
        $month = isset($_GET['eco_month']) ? sanitize_text_field(wp_unslash($_GET['eco_month'])) : '';
        $type = isset($_GET['eco_type']) ? sanitize_text_field(wp_unslash($_GET['eco_type'])) : '';
        $category_id = isset($_GET['eco_category']) ? absint($_GET['eco_category']) : 0;
        if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) { $month = ''; }
        if (!in_array($type, ['', 'income', 'expense'], true)) { $type = ''; }
        return ['month' => $month, 'type' => $type, 'category_id' => $category_id];
    }

    private function get_filtered_transactions(array $filters, int $limit = 50): array {
        global $wpdb;
        $where = ["1=1"];
        $params = [];
        if ($filters['type'] !== '') {
            $where[] = "t.type = %s";
            $params[] = $filters['type'];
        }
        if ($filters['category_id'] > 0) {
            $where[] = "t.category_id = %d";
            $params[] = $filters['category_id'];
        }
        if ($filters['month'] !== '') {
            $start = $filters['month'] . '-01 00:00:00';
            $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
            $where[] = "t.created_at >= %s";
            $where[] = "t.created_at < %s";
            $params[] = $start;
            $params[] = $end;
        }
        $sql = "SELECT t.*, c.name AS category_name
                FROM {$this->table_transactions} t
                LEFT JOIN {$this->table_categories} c ON c.id=t.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.id DESC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
        }
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        return $wpdb->get_results($sql);
    }

    public function export_csv(): void {
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        $filters = $this->get_filter_values();
        $rows = $this->get_filtered_transactions($filters, 0);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=economia-pro-movimientos.csv');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Tipo', 'Categoria', 'Cantidad', 'Descripcion', 'Fecha'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                (int) $r->id,
                $r->type === 'income' ? 'Ingreso' : 'Gasto',
                $r->category_name ?: '—',
                number_format((float)$r->amount, 2, ',', '.'),
                $r->description,
                $r->created_at,
            ], ';');
        }
        fclose($out);
        exit;
    }

    private function get_transactions(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT t.*, c.name AS category_name FROM {$this->table_transactions} t LEFT JOIN {$this->table_categories} c ON c.id=t.category_id ORDER BY t.id DESC LIMIT 50");
    }

    private function get_transaction(int $id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_transactions} WHERE id = %d", $id));
    }

    private function get_front_category_summary(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT c.name, t.type, SUM(t.amount) AS total FROM {$this->table_transactions} t INNER JOIN {$this->table_categories} c ON c.id=t.category_id GROUP BY t.category_id,t.type ORDER BY total DESC LIMIT 8");
    }

    private function get_budget_rows(string $period): array {
        global $wpdb;
        if (!$this->column_exists($this->table_budgets, 'amount')) return [];
        $start = $period . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
        return $wpdb->get_results($wpdb->prepare("
            SELECT c.id AS category_id, c.name, c.type, b.amount AS budget_amount,
                   COALESCE(SUM(t.amount), 0) AS spent_amount
            FROM {$this->table_budgets} b
            INNER JOIN {$this->table_categories} c ON c.id = b.category_id
            LEFT JOIN {$this->table_transactions} t
                ON t.category_id = c.id
               AND t.type = 'expense'
               AND t.created_at >= %s
               AND t.created_at < %s
            WHERE b.period_month = %s AND c.type = 'expense'
            GROUP BY c.id, c.name, c.type, b.amount
            ORDER BY c.name ASC
        ", $start, $end, $period));
    }

    private function get_budget_overview_cards(string $period): array {
        $rows = $this->get_budget_rows($period);
        $budget_total = 0.0; $spent_total = 0.0; $over_count = 0;
        foreach ($rows as $row) {
            $budget_total += (float)$row->budget_amount;
            $spent_total += (float)$row->spent_amount;
            if ((float)$row->spent_amount > (float)$row->budget_amount) $over_count++;
        }
        return ['budget_total'=>$budget_total,'spent_total'=>$spent_total,'remaining'=>$budget_total-$spent_total,'over_count'=>$over_count];
    }

    private function get_edit_transaction_id(): int { return isset($_GET['edit_tx']) ? absint($_GET['edit_tx']) : 0; }

    private function current_url_with(array $args = []): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $url = $scheme . '://' . $host . $uri;
        foreach ($args as $k => $v) {
            if ($v === null) $url = remove_query_arg($k, $url); else $url = add_query_arg($k, $v, $url);
        }
        return esc_url($url);
    }

    private function get_notice_html(): string {
        $key = isset($_GET['eco_notice']) ? sanitize_text_field(wp_unslash($_GET['eco_notice'])) : '';
        if ($key === '') return '';
        $messages = [
            'category_added'=>['ok','Categoría creada correctamente.'],
            'tx_added'=>['ok','Movimiento guardado correctamente.'],
            'tx_updated'=>['ok','Movimiento actualizado correctamente.'],
            'budget_saved'=>['ok','Presupuesto guardado correctamente.'],
            'notice_read'=>['ok','Alerta marcada como leída.'],
            'all_notices_read'=>['ok','Todas las alertas abiertas se marcaron como leídas.'],
            'invalid'=>['err','Faltan datos o no son válidos.'],
            'type_mismatch'=>['err','La categoría no coincide con el tipo de movimiento.'],
            'db_error'=>['err','No se pudo guardar en la base de datos.'],
        ];
        if (!isset($messages[$key])) return '';
        [$kind,$text] = $messages[$key];
        $bg = $kind==='ok' ? '#ecf7ed' : '#fcf0f1';
        $bd = $kind==='ok' ? '#46b450' : '#b32d2e';
        $cl = $kind==='ok' ? '#1e4620' : '#8a2424';
        return '<div style="margin:0 0 16px 0;padding:12px 14px;border-left:4px solid '.$bd.';background:'.$bg.';color:'.$cl.';border-radius:8px;">'.esc_html($text).'</div>';
    }

    private function render_category_options(array $categories, ?string $selected_type = null, int $selected_id = 0): string {
        $options = '<option value="">Categoría</option>';
        foreach ($categories as $cat) {
            if ($selected_type !== null && $cat->type !== $selected_type) continue;
            $selected = $selected_id === (int)$cat->id ? ' selected' : '';
            $options .= '<option value="'.(int)$cat->id.'"'.$selected.'>'.esc_html($cat->name).'</option>';
        }
        return $options;
    }

    private function render_notifications_box(array $notifications): string {
        ob_start(); ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <h2 style="margin:0;">Alertas</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('ecopro_mark_all_notices_read'); ?>
                    <input type="hidden" name="action" value="ecopro_mark_all_notices_read">
                    <button class="button">Marcar todas como leídas</button>
                </form>
            </div>
            <table class="widefat striped" style="margin-top:16px;">
                <thead><tr><th>Estado</th><th>Tipo</th><th>Mensaje</th><th>Fecha</th><th>Acción</th></tr></thead>
                <tbody>
                <?php if (!empty($notifications)): foreach ($notifications as $notice): ?>
                    <tr>
                        <td><?php echo $notice->status === 'open' ? 'Abierta' : 'Leída'; ?></td>
                        <td><?php echo esc_html($notice->type); ?></td>
                        <td><?php echo esc_html($notice->message); ?></td>
                        <td><?php echo esc_html($notice->created_at); ?></td>
                        <td>
                            <?php if ($notice->status === 'open'): ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('ecopro_mark_notice_read'); ?>
                                    <input type="hidden" name="action" value="ecopro_mark_notice_read">
                                    <input type="hidden" name="notice_id" value="<?php echo (int)$notice->id; ?>">
                                    <button class="button button-small">Marcar leída</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5">No hay alertas todavía.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php return ob_get_clean();
    }

    private function render_budget_box_admin(array $categories, string $period, array $overview, array $rows): string {
        ob_start(); ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
            <h2 style="margin-top:0;">Presupuestos</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php wp_nonce_field('ecopro_save_budget'); ?>
                <input type="hidden" name="action" value="ecopro_save_budget">
                <input type="month" name="period_month" value="<?php echo esc_attr($period); ?>" required>
                <select name="category_id" required>
                    <option value="">Categoría gasto</option>
                    <?php foreach ($categories as $cat): if ($cat->type !== 'expense') continue; ?>
                        <option value="<?php echo (int)$cat->id; ?>"><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" step="0.01" min="0" name="amount" placeholder="Presupuesto" required>
                <button class="button">Guardar presupuesto</button>
            </form>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:12px;margin:16px 0;">
                <div style="border:1px solid #ddd;border-radius:8px;padding:12px;"><strong>Presupuesto</strong><div><?php echo esc_html(number_format($overview['budget_total'],2,',','.')); ?> €</div></div>
                <div style="border:1px solid #ddd;border-radius:8px;padding:12px;"><strong>Gastado</strong><div><?php echo esc_html(number_format($overview['spent_total'],2,',','.')); ?> €</div></div>
                <div style="border:1px solid #ddd;border-radius:8px;padding:12px;"><strong>Restante</strong><div><?php echo esc_html(number_format($overview['remaining'],2,',','.')); ?> €</div></div>
                <div style="border:1px solid #ddd;border-radius:8px;padding:12px;"><strong>Alertas</strong><div><?php echo (int)$overview['over_count']; ?></div></div>
            </div>
            <table class="widefat striped">
                <thead><tr><th>Categoría</th><th>Presupuesto</th><th>Gastado</th><th>Estado</th></tr></thead>
                <tbody>
                <?php if (!empty($rows)): foreach ($rows as $row): $over = (float)$row->spent_amount > (float)$row->budget_amount; ?>
                    <tr>
                        <td><?php echo esc_html($row->name); ?></td>
                        <td><?php echo esc_html(number_format((float)$row->budget_amount,2,',','.')); ?> €</td>
                        <td><?php echo esc_html(number_format((float)$row->spent_amount,2,',','.')); ?> €</td>
                        <td style="<?php echo esc_attr($over ? 'color:#b32d2e;font-weight:700;' : 'color:#1e4620;font-weight:700;'); ?>"><?php echo $over ? 'Superado' : 'OK'; ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">No hay presupuestos este mes.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php return ob_get_clean();
    }

    public function admin_page(): void {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
        $totals = $this->get_totals();
        $filters = $this->get_filter_values();
        $rows = $this->get_filtered_transactions($filters);
        $categories = $this->get_categories();
        $notifications = $this->get_notifications();
        $page_id=(int)get_option(self::OPTION_PAGE_ID,0);
        $pages=get_pages(['sort_column'=>'post_title','sort_order'=>'asc']);
        $edit_id = $this->get_edit_transaction_id();
        $edit_tx = $edit_id ? $this->get_transaction($edit_id) : null;
        $admin_selected_type = $edit_tx ? $edit_tx->type : 'expense';
        $admin_selected_cat  = $edit_tx ? (int)$edit_tx->category_id : 0;
        $period = $this->get_current_period();
        $budget_rows = $this->get_budget_rows($period);
        $budget_overview = $this->get_budget_overview_cards($period);
        ?>
        <div class="wrap"><h1>Economía Pro</h1><?php echo $this->get_notice_html(); ?>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px;max-width:960px;margin:18px 0 24px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Ingresos</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['income'],2,',','.')); ?> €</div></div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Gastos</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['expense'],2,',','.')); ?> €</div></div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Balance</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['balance'],2,',','.')); ?> €</div></div>
            </div>
            <div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;max-width:1280px;">
                <div style="display:grid;gap:24px;">
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                        <h2 style="margin-top:0;">Ajustes</h2><p><code>[economia_dashboard]</code></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ecopro_save_settings'); ?><input type="hidden" name="action" value="ecopro_save_settings">
                            <p><label><strong>Página frontend</strong></label><br><select name="ecopro_front_page" style="width:100%;max-width:360px;"><option value="0">— Sin sincronizar página —</option><?php foreach ($pages as $page): ?><option value="<?php echo esc_attr((string)$page->ID); ?>" <?php selected($page_id,(int)$page->ID); ?>><?php echo esc_html($page->post_title.' (#'.$page->ID.')'); ?></option><?php endforeach; ?></select></p>
                            <p><label><strong>Contraseña del frontend</strong></label><br><input type="password" name="eco_pass" placeholder="Escribe una nueva contraseña" style="width:100%;max-width:360px;"></p>
                            <p><button type="submit" class="button button-primary">Guardar ajustes</button></p>
                        </form>
                    </div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                        <h2 style="margin-top:0;">Categorías</h2>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;"><?php wp_nonce_field('ecopro_add_category'); ?><input type="hidden" name="action" value="ecopro_add_category"><select name="type"><option value="income">Ingreso</option><option value="expense">Gasto</option></select><input type="text" name="name" placeholder="Nueva categoría" required><button class="button">Añadir</button></form>
                        <table class="widefat striped" style="margin-top:16px;"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th></tr></thead><tbody><?php foreach($categories as $cat): ?><tr><td><?php echo (int)$cat->id; ?></td><td><?php echo esc_html($cat->name); ?></td><td><?php echo $cat->type==='income'?'Ingreso':'Gasto'; ?></td></tr><?php endforeach; ?></tbody></table>
                    </div>
                </div>
                <div style="display:grid;gap:24px;">
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                        <h2 style="margin-top:0;"><?php echo $edit_tx ? 'Editar movimiento' : 'Añadir movimiento'; ?></h2>
                        <?php if ($edit_tx): ?><p><a href="<?php echo esc_url(admin_url('admin.php?page=eco-pro')); ?>">Cancelar edición</a></p><?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                            <?php if ($edit_tx): wp_nonce_field('ecopro_update_tx'); ?><input type="hidden" name="action" value="ecopro_update_tx"><input type="hidden" name="tx_id" value="<?php echo (int)$edit_tx->id; ?>">
                            <?php else: wp_nonce_field('ecopro_add_tx'); ?><input type="hidden" name="action" value="ecopro_add_tx"><?php endif; ?>
                            <select name="type" id="ecopro-admin-type">
                                <option value="income" <?php selected($admin_selected_type === 'income'); ?>>Ingreso</option>
                                <option value="expense" <?php selected($admin_selected_type === 'expense'); ?>>Gasto</option>
                            </select>
                            <select name="category_id" id="ecopro-admin-category" required><?php echo $this->render_category_options($categories, $admin_selected_type, $admin_selected_cat); ?></select>
                            <input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" value="<?php echo $edit_tx ? esc_attr((string)$edit_tx->amount) : ''; ?>" required>
                            <input type="text" name="description" placeholder="Descripción" value="<?php echo $edit_tx ? esc_attr($edit_tx->description) : ''; ?>" style="min-width:220px;" required>
                            <button class="button button-primary"><?php echo $edit_tx ? 'Actualizar' : 'Guardar'; ?></button>
                        </form>
                        <h2 style="margin:24px 0 12px;">Movimientos</h2>
                        <form method="get" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                            <input type="hidden" name="page" value="eco-pro">
                            <input type="month" name="eco_month" value="<?php echo esc_attr($filters['month']); ?>">
                            <select name="eco_type">
                                <option value="" <?php selected($filters['type'], ''); ?>>Todos</option>
                                <option value="income" <?php selected($filters['type'], 'income'); ?>>Ingreso</option>
                                <option value="expense" <?php selected($filters['type'], 'expense'); ?>>Gasto</option>
                            </select>
                            <select name="eco_category">
                                <option value="0">Todas las categorías</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat->id; ?>" <?php selected((int)$filters['category_id'], (int)$cat->id); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button">Filtrar</button>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=eco-pro')); ?>">Reset</a>
                            <button class="button button-secondary" type="submit" formaction="<?php echo esc_url(admin_url('admin-post.php?action=ecopro_export_csv')); ?>" formmethod="get">Exportar CSV</button>
                        </form>
                        <table class="widefat striped"><thead><tr><th>ID</th><th>Tipo</th><th>Categoría</th><th>Cantidad</th><th>Descripción</th><th>Fecha</th><th>Acción</th></tr></thead><tbody><?php if(!empty($rows)): foreach($rows as $r): ?><tr><td><?php echo (int)$r->id; ?></td><td><?php echo $r->type==='income'?'Ingreso':'Gasto'; ?></td><td><?php echo esc_html($r->category_name ?: '—'); ?></td><td><?php echo esc_html(number_format((float)$r->amount,2,',','.')); ?> €</td><td><?php echo esc_html($r->description); ?></td><td><?php echo esc_html($r->created_at); ?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=eco-pro&edit_tx='.(int)$r->id)); ?>">Editar</a></td></tr><?php endforeach; else: ?><tr><td colspan="7">No hay movimientos todavía.</td></tr><?php endif; ?></tbody></table>
                    </div>
                    <?php echo $this->render_budget_box_admin($categories, $period, $budget_overview, $budget_rows); ?>
                    <?php echo $this->render_notifications_box($notifications); ?>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const categories = <?php echo wp_json_encode(array_map(function($cat){ return ['id'=>(int)$cat->id,'name'=>$cat->name,'type'=>$cat->type]; }, $categories)); ?>;
            function bindFilter(typeId, categoryId){
                const typeEl = document.getElementById(typeId);
                const catEl = document.getElementById(categoryId);
                if(!typeEl || !catEl) return;
                const initialSelected = catEl.value;
                function render(){
                    const selectedType = typeEl.value;
                    const previous = catEl.value || initialSelected;
                    catEl.innerHTML = "";
                    const placeholder = document.createElement("option");
                    placeholder.value = "";
                    placeholder.textContent = "Categoría";
                    catEl.appendChild(placeholder);
                    categories.forEach(cat => {
                        if(cat.type !== selectedType) return;
                        const opt = document.createElement("option");
                        opt.value = String(cat.id);
                        opt.textContent = cat.name;
                        if(String(cat.id) === String(previous)) opt.selected = true;
                        catEl.appendChild(opt);
                    });
                }
                typeEl.addEventListener("change", render);
                render();
            }
            bindFilter("ecopro-admin-type", "ecopro-admin-category");
        })();
        </script>
        <?php
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
        check_admin_referer('ecopro_save_settings');
        $password = isset($_POST['eco_pass']) ? sanitize_text_field(wp_unslash($_POST['eco_pass'])) : '';
        $page_id  = isset($_POST['ecopro_front_page']) ? absint($_POST['ecopro_front_page']) : 0;
        update_option(self::OPTION_PAGE_ID, $page_id, false);
        if ($password !== '') {
            update_option(self::OPTION_PASSWORD, password_hash($password, PASSWORD_DEFAULT), false);
            if ($page_id > 0 && get_post($page_id) instanceof WP_Post) {
                wp_update_post(['ID'=>$page_id,'post_password'=>$password]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=eco-pro')); exit;
    }

    public function add_category(): void {
        check_admin_referer('ecopro_add_category');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (!in_array($type,['income','expense'],true) || $name==='') $this->safe_back_redirect('invalid');
        $this->create_or_update_tables();
        $this->backfill_missing_slugs();
        $result = $wpdb->insert($this->table_categories, ['name'=>$name,'slug'=>$this->unique_slug($name),'type'=>$type], ['%s','%s','%s']);
        if ($result === false) { error_log('economia-pro add_category DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('category_added');
    }

    public function save_budget(): void {
        check_admin_referer('ecopro_save_budget');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $period_month = isset($_POST['period_month']) ? sanitize_text_field(wp_unslash($_POST['period_month'])) : '';
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        if ($category_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $period_month) || $amount < 0) $this->safe_back_redirect('invalid');
        $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
        if ($cat_type !== 'expense') $this->safe_back_redirect('invalid');

        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_budgets} WHERE category_id = %d AND period_month = %s", $category_id, $period_month));
        if ($existing_id > 0) {
            $result = $wpdb->update($this->table_budgets, ['amount'=>$amount, 'updated_at'=>current_time('mysql')], ['id'=>$existing_id], ['%f','%s'], ['%d']);
        } else {
            $result = $wpdb->insert($this->table_budgets, ['category_id'=>$category_id, 'period_month'=>$period_month, 'amount'=>$amount, 'updated_at'=>current_time('mysql')], ['%d','%s','%f','%s']);
        }
        if ($result === false) { error_log('economia-pro save_budget DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->run_daily_checks();
        $this->safe_back_redirect('budget_saved');
    }

    public function add_tx(): void {
        check_admin_referer('ecopro_add_tx');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
        if (!in_array($type,['income','expense'],true) || $amount<=0 || $description==='' || $category_id<=0) $this->safe_back_redirect('invalid');
        $this->create_or_update_tables();
        $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
        if ($cat_type !== $type) $this->safe_back_redirect('type_mismatch');
        $result = $wpdb->insert($this->table_transactions, ['type'=>$type,'category_id'=>$category_id,'amount'=>$amount,'description'=>$description], ['%s','%d','%f','%s']);
        if ($result === false) { error_log('economia-pro add_tx DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->run_daily_checks();
        $this->safe_back_redirect('tx_added');
    }

    public function update_tx(): void {
        check_admin_referer('ecopro_update_tx');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $tx_id = isset($_POST['tx_id']) ? absint($_POST['tx_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
        if ($tx_id<=0 || !in_array($type,['income','expense'],true) || $amount<=0 || $description==='' || $category_id<=0) $this->safe_back_redirect('invalid');
        $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
        if ($cat_type !== $type) $this->safe_back_redirect('type_mismatch');
        $result = $wpdb->update($this->table_transactions, ['type'=>$type,'category_id'=>$category_id,'amount'=>$amount,'description'=>$description], ['id'=>$tx_id], ['%s','%d','%f','%s'], ['%d']);
        if ($result === false) { error_log('economia-pro update_tx DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->run_daily_checks();
        $this->safe_back_redirect('tx_updated');
    }

    private function frontend_or_admin_can_manage(): bool {
        if (current_user_can('manage_options')) return true;
        return $this->frontend_access_granted();
    }

    private function frontend_access_granted(): bool {
        if (session_status()===PHP_SESSION_NONE && !headers_sent()) session_start();
        return !empty($_SESSION['ecopro_front_ok']);
    }

    private function mark_frontend_access_granted(): void {
        if (session_status()===PHP_SESSION_NONE && !headers_sent()) session_start();
        $_SESSION['ecopro_front_ok']=1;
    }

    private function safe_back_redirect(string $notice=''): void {
        $referer = wp_get_referer();
        if (!$referer) $referer = admin_url('admin.php?page=eco-pro');
        $referer = remove_query_arg('edit_tx', $referer);
        if ($notice !== '') $referer = add_query_arg('eco_notice', $notice, $referer);
        wp_safe_redirect($referer); exit;
    }

    private function front_css(): string {
        return '<style>.ecopro-wrap{max-width:980px;margin:24px auto;padding:24px;background:#fff;border:1px solid #dcdcde;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);color:#1d2327;box-sizing:border-box;width:calc(100% - 24px);}.ecopro-title{margin:0 0 18px 0;color:#1d2327;font-size:36px;line-height:1.1;word-break:break-word;}.ecopro-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px;}.ecopro-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:16px;}.ecopro-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0;}.ecopro-card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:18px;box-sizing:border-box;min-width:0;}.ecopro-form{display:flex;gap:10px;flex-wrap:wrap;}.ecopro-input,.ecopro-select{width:100%;padding:12px 14px;border:1px solid #c3c4c7;border-radius:10px;background:#fff;color:#1d2327;font-size:16px;box-sizing:border-box;min-width:0;}.ecopro-btn{display:inline-block;padding:12px 18px;border:0;border-radius:10px;background:#2271b1;color:#fff;font-weight:600;cursor:pointer;}.ecopro-table-wrap{overflow:auto;-webkit-overflow-scrolling:touch;}.ecopro-table{width:100%;border-collapse:collapse;}.ecopro-table th,.ecopro-table td{text-align:left;padding:10px;border-bottom:1px solid #eee;vertical-align:top;}.ecopro-table th{border-bottom-color:#ddd;}.ecopro-muted{margin:0;color:#50575e;}.ecopro-link{color:#2271b1;text-decoration:none;font-weight:600;}.ecopro-danger{color:#b32d2e;font-weight:700;}.ecopro-ok{color:#1e4620;font-weight:700;}@media (max-width:900px){.ecopro-grid-3,.ecopro-grid-2,.ecopro-grid-4{grid-template-columns:1fr;}}@media (max-width:640px){.ecopro-wrap{padding:16px;width:calc(100% - 16px);margin:16px auto;}.ecopro-title{font-size:28px;}.ecopro-card{padding:14px;}.ecopro-form{display:grid;grid-template-columns:1fr;gap:10px;}.ecopro-btn{width:100%;}}</style>';
    }

    private function render_front_stats(array $totals): string {
        return '<div class="ecopro-grid-3"><div class="ecopro-card"><strong>Ingresos</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['income'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Gastos</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['expense'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Balance</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['balance'],2,',','.')).' €</div></div></div>';
    }

    private function render_front_category_form(): string {
        return '<div class="ecopro-card"><h3 style="margin:0 0 14px 0;color:#1d2327;">Crear categoría</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ecopro-form">'.wp_nonce_field('ecopro_add_category','_wpnonce',true,false).'<input type="hidden" name="action" value="ecopro_add_category"><select name="type" class="ecopro-select"><option value="income">Ingreso</option><option value="expense">Gasto</option></select><input type="text" name="name" placeholder="Nueva categoría" class="ecopro-input" required><button type="submit" class="ecopro-btn">Añadir</button></form></div>';
    }

    private function render_front_category_list(array $categories): string {
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 12px 0;color:#1d2327;">Categorías disponibles</h3>';
        if (empty($categories)) return $html.'<p class="ecopro-muted">No hay categorías todavía.</p></div>';
        $html .= '<div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th></tr></thead><tbody>';
        foreach ($categories as $cat) $html .= '<tr><td>'.(int)$cat->id.'</td><td>'.esc_html($cat->name).'</td><td>'.($cat->type==='income'?'Ingreso':'Gasto').'</td></tr>';
        return $html.'</tbody></table></div></div>';
    }

    private function render_front_tx_form(array $categories, $edit_tx = null): string {
        $is_edit = !empty($edit_tx);
        $selected_type = $is_edit ? $edit_tx->type : 'expense';
        $selected_cat = $is_edit ? (int)$edit_tx->category_id : 0;
        $nonce = $is_edit ? wp_nonce_field('ecopro_update_tx','_wpnonce',true,false) : wp_nonce_field('ecopro_add_tx','_wpnonce',true,false);
        $action = $is_edit ? 'ecopro_update_tx' : 'ecopro_add_tx';
        $button = $is_edit ? 'Actualizar' : 'Guardar';
        $title = $is_edit ? 'Editar movimiento' : 'Añadir movimiento';
        $cancel = $is_edit ? '<p style="margin:0 0 12px 0;"><a class="ecopro-link" href="'.$this->current_url_with(['edit_tx'=>null]).'">Cancelar edición</a></p>' : '';
        $amount = $is_edit ? esc_attr((string)$edit_tx->amount) : '';
        $description = $is_edit ? esc_attr($edit_tx->description) : '';
        $hiddenId = $is_edit ? '<input type="hidden" name="tx_id" value="'.(int)$edit_tx->id.'">' : '';
        return '<div class="ecopro-card"><h3 style="margin:0 0 14px 0;color:#1d2327;">'.$title.'</h3>'.$cancel.'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ecopro-form">'.$nonce.'<input type="hidden" name="action" value="'.$action.'">'.$hiddenId.'<select name="type" id="ecopro-front-type" class="ecopro-select"><option value="income"'.selected($selected_type,'income',false).'>Ingreso</option><option value="expense"'.selected($selected_type,'expense',false).'>Gasto</option></select><select name="category_id" id="ecopro-front-category" class="ecopro-select" required>'.$this->render_category_options($categories, $selected_type, $selected_cat).'</select><input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" value="'.$amount.'" class="ecopro-input" required><input type="text" name="description" placeholder="Descripción" value="'.$description.'" class="ecopro-input" required><button type="submit" class="ecopro-btn">'.$button.'</button></form></div>';
    }

    private function render_front_budget_box(array $categories, string $period, array $overview, array $rows): string {
        $options = '';
        foreach ($categories as $cat) { if ($cat->type !== 'expense') continue; $options .= '<option value="'.(int)$cat->id.'">'.esc_html($cat->name).'</option>'; }
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 14px 0;color:#1d2327;">Presupuestos</h3>';
        $html .= '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ecopro-form">'.wp_nonce_field('ecopro_save_budget','_wpnonce',true,false).'<input type="hidden" name="action" value="ecopro_save_budget"><input type="month" name="period_month" value="'.esc_attr($period).'" class="ecopro-input" required><select name="category_id" class="ecopro-select" required><option value="">Categoría gasto</option>'.$options.'</select><input type="number" step="0.01" min="0" name="amount" placeholder="Presupuesto" class="ecopro-input" required><button type="submit" class="ecopro-btn">Guardar presupuesto</button></form>';
        $html .= '<div class="ecopro-grid-4"><div class="ecopro-card"><strong>Presupuesto</strong><div style="font-size:22px;margin-top:6px;">'.esc_html(number_format($overview['budget_total'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Gastado</strong><div style="font-size:22px;margin-top:6px;">'.esc_html(number_format($overview['spent_total'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Restante</strong><div style="font-size:22px;margin-top:6px;">'.esc_html(number_format($overview['remaining'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Alertas</strong><div style="font-size:22px;margin-top:6px;">'.(int)$overview['over_count'].'</div></div></div>';
        $html .= '<div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>Categoría</th><th>Presupuesto</th><th>Gastado</th><th>Estado</th></tr></thead><tbody>';
        if (!empty($rows)) foreach ($rows as $row) { $over = (float)$row->spent_amount > (float)$row->budget_amount; $html .= '<tr><td>'.esc_html($row->name).'</td><td>'.esc_html(number_format((float)$row->budget_amount,2,',','.')).' €</td><td>'.esc_html(number_format((float)$row->spent_amount,2,',','.')).' €</td><td>'.($over ? '<span class="ecopro-danger">Superado</span>' : '<span class="ecopro-ok">OK</span>').'</td></tr>'; }
        else $html .= '<tr><td colspan="4">No hay presupuestos este mes.</td></tr>';
        return $html.'</tbody></table></div></div>';
    }

    private function render_front_notifications_box(array $notifications): string {
        $html = '<div class="ecopro-card"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;"><h3 style="margin:0;color:#1d2327;">Alertas</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('ecopro_mark_all_notices_read','_wpnonce',true,false).'<input type="hidden" name="action" value="ecopro_mark_all_notices_read"><button type="submit" class="ecopro-btn" style="padding:8px 12px;">Marcar todas</button></form></div><div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>Estado</th><th>Tipo</th><th>Mensaje</th><th>Acción</th></tr></thead><tbody>';
        if (!empty($notifications)) {
            foreach ($notifications as $notice) {
                $action = $notice->status === 'open'
                    ? '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('ecopro_mark_notice_read','_wpnonce',true,false).'<input type="hidden" name="action" value="ecopro_mark_notice_read"><input type="hidden" name="notice_id" value="'.(int)$notice->id.'"><button type="submit" class="ecopro-btn" style="padding:8px 12px;">Leer</button></form>'
                    : '—';
                $html .= '<tr><td>'.($notice->status === 'open' ? 'Abierta' : 'Leída').'</td><td>'.esc_html($notice->type).'</td><td>'.esc_html($notice->message).'</td><td>'.$action.'</td></tr>';
            }
        } else {
            $html .= '<tr><td colspan="4">No hay alertas todavía.</td></tr>';
        }
        return $html.'</tbody></table></div></div>';
    }

    private function render_front_category_summary(array $items): string {
        if (empty($items)) return '';
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 12px 0;color:#1d2327;">Estadísticas por categoría</h3><ul style="margin:0;padding-left:18px;color:#50575e;font-size:16px;">';
        foreach ($items as $item) $html .= '<li><strong>'.esc_html($item->name).'</strong> ('.($item->type==='income'?'Ingreso':'Gasto').'): '.esc_html(number_format((float)$item->total,2,',','.')).' €</li>';
        return $html.'</ul></div>';
    }

    private function render_front_recent_transactions(array $rows, array $categories, array $filters): string {
        $export_url = esc_url(add_query_arg(array_filter([
            'action' => 'ecopro_export_csv',
            'eco_month' => $filters['month'],
            'eco_type' => $filters['type'],
            'eco_category' => $filters['category_id'] > 0 ? (string)$filters['category_id'] : '',
        ], function($v){ return $v !== '' && $v !== null; }), admin_url('admin-post.php')));
        $html = '<div class="ecopro-card"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;"><h3 style="margin:0;color:#1d2327;">Últimos movimientos</h3><a class="ecopro-btn" href="'.$export_url.'" style="text-decoration:none;">Exportar CSV</a></div>';
        $html .= '<form method="get" action="" class="ecopro-form" style="margin-top:12px;margin-bottom:12px;"><input type="month" name="eco_month" value="'.esc_attr($filters['month']).'" class="ecopro-input"><select name="eco_type" class="ecopro-select"><option value="">Todos</option><option value="income"'.selected($filters['type'],'income',false).'>Ingreso</option><option value="expense"'.selected($filters['type'],'expense',false).'>Gasto</option></select><select name="eco_category" class="ecopro-select"><option value="0">Todas las categorías</option>';
        foreach ($categories as $cat) {
            $html .= '<option value="'.(int)$cat->id.'"'.selected((int)$filters['category_id'], (int)$cat->id, false).'>'.esc_html($cat->name).'</option>';
        }
        $html .= '</select><button type="submit" class="ecopro-btn">Filtrar</button><a class="ecopro-link" href="'.$this->current_url_with(['eco_month'=>null,'eco_type'=>null,'eco_category'=>null]).'">Reset</a></form>';
        if (empty($rows)) return $html.'<p class="ecopro-muted">No hay movimientos todavía.</p></div>';
        $html .= '<div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>Tipo</th><th>Categoría</th><th>Cantidad</th><th>Descripción</th><th>Acción</th></tr></thead><tbody>';
        foreach ($rows as $r) { $edit_url = $this->current_url_with(['edit_tx' => (int)$r->id]); $html .= '<tr><td>'.($r->type==='income'?'Ingreso':'Gasto').'</td><td>'.esc_html($r->category_name ?: '—').'</td><td>'.esc_html(number_format((float)$r->amount,2,',','.')).' €</td><td>'.esc_html($r->description).'</td><td><a class="ecopro-link" href="'.$edit_url.'">Editar</a></td></tr>'; }
        return $html.'</tbody></table></div></div>';
    }

    public function dashboard(): string {
        $stored_hash = (string)get_option(self::OPTION_PASSWORD,'');
        if ($stored_hash==='') return $this->front_css().'<div class="ecopro-wrap"><p style="margin:0;color:#1d2327;">El administrador todavía no ha configurado la contraseña.</p></div>';
        if (isset($_POST['eco_login'])) {
            $password = isset($_POST['eco_pass']) ? (string)wp_unslash($_POST['eco_pass']) : '';
            if (password_verify($password, $stored_hash)) $this->mark_frontend_access_granted();
            else return $this->front_css().'<div class="ecopro-wrap"><p style="margin:0 0 12px 0;color:#b32d2e;font-weight:600;">Contraseña incorrecta.</p><form method="post"><p style="margin:0 0 16px 0;"><input type="password" name="eco_pass" placeholder="Contraseña" class="ecopro-input" required></p><p style="margin:0;"><button type="submit" class="ecopro-btn">Reintentar</button></p><input type="hidden" name="eco_login" value="1"></form></div>';
        }
        if (!$this->frontend_access_granted()) return $this->front_css().'<form method="post" class="ecopro-wrap"><h2 class="ecopro-title">Acceso Economía</h2><p style="margin:0 0 18px 0;color:#50575e;">Introduce tu contraseña para acceder al panel financiero.</p><p style="margin:0 0 16px 0;"><input type="password" name="eco_pass" placeholder="Contraseña" class="ecopro-input" required></p><p style="margin:0;"><button type="submit" class="ecopro-btn">Entrar</button></p><input type="hidden" name="eco_login" value="1"></form>';
        $totals = $this->get_totals();
        $categories = $this->get_categories();
        $rows = $this->get_transactions();
        $summary = $this->get_front_category_summary();
        $notifications = $this->get_notifications();
        $edit_id = $this->get_edit_transaction_id();
        $edit_tx = $edit_id ? $this->get_transaction($edit_id) : null;
        $period = $this->get_current_period();
        $budget_rows = $this->get_budget_rows($period);
        $budget_overview = $this->get_budget_overview_cards($period);
        $categories_json = wp_json_encode(array_map(function($cat){ return ['id'=>(int)$cat->id,'name'=>$cat->name,'type'=>$cat->type]; }, $categories));

        return $this->front_css().'<div class="ecopro-wrap"><h2 class="ecopro-title">Dashboard Economía</h2>'.$this->get_notice_html().$this->render_front_stats($totals).'<div class="ecopro-grid-2">'.$this->render_front_category_form().$this->render_front_tx_form($categories, $edit_tx).'</div><div style="margin-bottom:16px;">'.$this->render_front_budget_box($categories, $period, $budget_overview, $budget_rows).'</div><div class="ecopro-grid-2">'.$this->render_front_notifications_box($notifications).$this->render_front_category_summary($summary).'</div><div style="margin-top:16px;">'.$this->render_front_recent_transactions($rows, $categories, $filters).'</div></div><script>(function(){const categories='.$categories_json.';function bindFilter(typeId,categoryId){const typeEl=document.getElementById(typeId);const catEl=document.getElementById(categoryId);if(!typeEl||!catEl)return;const initial=catEl.value;function render(){const selectedType=typeEl.value;const previous=catEl.value||initial;catEl.innerHTML="";const placeholder=document.createElement("option");placeholder.value="";placeholder.textContent="Categoría";catEl.appendChild(placeholder);categories.forEach(cat=>{if(cat.type!==selectedType)return;const opt=document.createElement("option");opt.value=String(cat.id);opt.textContent=cat.name;if(String(cat.id)===String(previous))opt.selected=true;catEl.appendChild(opt);});}typeEl.addEventListener("change",render);render();}bindFilter("ecopro-front-type","ecopro-front-category");})();</script>';
    }
}
new EconomiaPro();
}
