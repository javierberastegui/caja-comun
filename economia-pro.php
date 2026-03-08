<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 2.3.1
 * Author: Loki
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('EconomiaPro')) {
    final class EconomiaPro {
        private const OPTION_PASSWORD = 'ecopro_front_password';
        private const OPTION_PAGE_ID  = 'ecopro_front_page_id';

        private string $table_transactions;
        private string $table_categories;

        public function __construct() {
            global $wpdb;
            $this->table_transactions = $wpdb->prefix . 'eco_transactions';
            $this->table_categories   = $wpdb->prefix . 'eco_categories';

            register_activation_hook(__FILE__, [$this, 'install']);
            add_action('admin_menu', [$this, 'menu']);
            add_action('admin_post_ecopro_add_tx', [$this, 'add_tx']);
            add_action('admin_post_ecopro_save_settings', [$this, 'save_settings']);
            add_action('admin_post_ecopro_add_category', [$this, 'add_category']);
            add_shortcode('economia_dashboard', [$this, 'dashboard']);
        }

        public function install(): void {
            global $wpdb;
            $charset = $wpdb->get_charset_collate();

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $sql_categories = "CREATE TABLE {$this->table_categories} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'expense',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY type (type)
            ) {$charset};";

            $sql_transactions = "CREATE TABLE {$this->table_transactions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(20) NOT NULL,
                category_id BIGINT UNSIGNED NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY type (type),
                KEY category_id (category_id),
                KEY created_at (created_at)
            ) {$charset};";

            dbDelta($sql_categories);
            dbDelta($sql_transactions);

            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");
            if ($count === 0) {
                $defaults = [
                    ['name' => 'Salario', 'type' => 'income'],
                    ['name' => 'Extra', 'type' => 'income'],
                    ['name' => 'Comida', 'type' => 'expense'],
                    ['name' => 'Transporte', 'type' => 'expense'],
                    ['name' => 'Hogar', 'type' => 'expense'],
                ];
                foreach ($defaults as $cat) {
                    $wpdb->insert($this->table_categories, $cat, ['%s', '%s']);
                }
            }
        }

        public function menu(): void {
            add_menu_page(
                'Economía Pro',
                'Economía',
                'manage_options',
                'eco-pro',
                [$this, 'admin_page'],
                'dashicons-chart-line',
                26
            );
        }

        private function get_totals(): array {
            global $wpdb;
            $income = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='income'");
            $expense = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='expense'");
            return [
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }

        private function get_categories(): array {
            global $wpdb;
            return $wpdb->get_results("SELECT * FROM {$this->table_categories} ORDER BY type ASC, name ASC");
        }

        private function get_transactions(): array {
            global $wpdb;
            return $wpdb->get_results(
                "SELECT t.*, c.name AS category_name
                 FROM {$this->table_transactions} t
                 LEFT JOIN {$this->table_categories} c ON c.id = t.category_id
                 ORDER BY t.id DESC
                 LIMIT 50"
            );
        }

        private function get_front_category_summary(): array {
            global $wpdb;
            return $wpdb->get_results(
                "SELECT c.name, t.type, SUM(t.amount) AS total
                 FROM {$this->table_transactions} t
                 INNER JOIN {$this->table_categories} c ON c.id = t.category_id
                 GROUP BY t.category_id, t.type
                 ORDER BY total DESC
                 LIMIT 8"
            );
        }

        private function get_notice_html(): string {
            $key = isset($_GET['eco_notice']) ? sanitize_text_field(wp_unslash($_GET['eco_notice'])) : '';
            if ($key === '') {
                return '';
            }

            $messages = [
                'category_added' => ['ok', 'Categoría creada correctamente.'],
                'tx_added'       => ['ok', 'Movimiento guardado correctamente.'],
                'invalid'        => ['err', 'Faltan datos o no son válidos.'],
                'type_mismatch'  => ['err', 'La categoría no coincide con el tipo de movimiento.'],
                'db_error'       => ['err', 'No se pudo guardar en la base de datos.'],
            ];

            if (!isset($messages[$key])) {
                return '';
            }

            [$kind, $text] = $messages[$key];
            $bg = $kind === 'ok' ? '#ecf7ed' : '#fcf0f1';
            $bd = $kind === 'ok' ? '#46b450' : '#b32d2e';
            $cl = $kind === 'ok' ? '#1e4620' : '#8a2424';

            return '<div style="margin:0 0 16px 0;padding:12px 14px;border-left:4px solid ' . esc_attr($bd) . ';background:' . esc_attr($bg) . ';color:' . esc_attr($cl) . ';border-radius:8px;">' . esc_html($text) . '</div>';
        }

        public function admin_page(): void {
            if (!current_user_can('manage_options')) {
                wp_die('No autorizado.');
            }

            $totals = $this->get_totals();
            $rows = $this->get_transactions();
            $categories = $this->get_categories();
            $page_id = (int) get_option(self::OPTION_PAGE_ID, 0);
            $pages = get_pages([
                'sort_column' => 'post_title',
                'sort_order'  => 'asc',
            ]);
            ?>
            <div class="wrap">
                <h1>Economía Pro</h1>
                <?php echo $this->get_notice_html(); ?>

                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                    <div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px;max-width:960px;margin:18px 0 24px 0;">
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Ingresos</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['income'], 2, ',', '.')); ?> €</div>
                    </div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Gastos</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['expense'], 2, ',', '.')); ?> €</div>
                    </div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Balance</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['balance'], 2, ',', '.')); ?> €</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;max-width:1280px;">
                    <div style="display:grid;gap:24px;">
                        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                            <h2 style="margin-top:0;">Ajustes</h2>
                            <p>Pega este shortcode en la página donde quieras mostrar el panel:</p>
                            <p><code>[economia_dashboard]</code></p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('ecopro_save_settings'); ?>
                                <input type="hidden" name="action" value="ecopro_save_settings">

                                <p>
                                    <label for="ecopro_front_page"><strong>Página frontend</strong></label><br>
                                    <select id="ecopro_front_page" name="ecopro_front_page" style="width:100%;max-width:360px;">
                                        <option value="0">— Sin sincronizar página —</option>
                                        <?php foreach ($pages as $page) : ?>
                                            <option value="<?php echo esc_attr((string) $page->ID); ?>" <?php selected($page_id, (int) $page->ID); ?>>
                                                <?php echo esc_html($page->post_title . ' (#' . $page->ID . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p>
                                    <label for="eco_pass"><strong>Contraseña del frontend</strong></label><br>
                                    <input id="eco_pass" type="password" name="eco_pass" placeholder="Escribe una nueva contraseña" style="width:100%;max-width:360px;">
                                </p>

                                <p style="color:#50575e;">Si eliges una página, el plugin sincroniza la contraseña con la protección nativa de WordPress de esa página.</p>

                                <p>
                                    <button type="submit" class="button button-primary">Guardar ajustes</button>
                                </p>
                            </form>
                        </div>

                        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                            <h2 style="margin-top:0;">Categorías</h2>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                                <?php wp_nonce_field('ecopro_add_category'); ?>
                                <input type="hidden" name="action" value="ecopro_add_category">
                                <select name="type">
                                    <option value="income">Ingreso</option>
                                    <option value="expense">Gasto</option>
                                </select>
                                <input type="text" name="name" placeholder="Nueva categoría" required>
                                <button class="button">Añadir</button>
                            </form>

                            <table class="widefat striped" style="margin-top:16px;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat) : ?>
                                        <tr>
                                            <td><?php echo (int) $cat->id; ?></td>
                                            <td><?php echo esc_html($cat->name); ?></td>
                                            <td><?php echo $cat->type === 'income' ? 'Ingreso' : 'Gasto'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                        <h2 style="margin-top:0;">Añadir movimiento</h2>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                            <?php wp_nonce_field('ecopro_add_tx'); ?>
                            <input type="hidden" name="action" value="ecopro_add_tx">

                            <select name="type">
                                <option value="income">Ingreso</option>
                                <option value="expense">Gasto</option>
                            </select>

                            <select name="category_id" required>
                                <option value="">Categoría</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo (int) $cat->id; ?>">
                                        <?php echo esc_html(($cat->type === 'income' ? '[Ingreso] ' : '[Gasto] ') . $cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" required>
                            <input type="text" name="description" placeholder="Descripción" style="min-width:220px;" required>

                            <button class="button button-primary">Guardar</button>
                        </form>

                        <h2 style="margin:24px 0 12px;">Movimientos</h2>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th>Descripción</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)) : ?>
                                    <?php foreach ($rows as $r) : ?>
                                        <tr>
                                            <td><?php echo (int) $r->id; ?></td>
                                            <td><?php echo $r->type === 'income' ? 'Ingreso' : 'Gasto'; ?></td>
                                            <td><?php echo esc_html($r->category_name ?: '—'); ?></td>
                                            <td><?php echo esc_html(number_format((float) $r->amount, 2, ',', '.')); ?> €</td>
                                            <td><?php echo esc_html($r->description); ?></td>
                                            <td><?php echo esc_html($r->created_at); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6">No hay movimientos todavía.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        }

        public function save_settings(): void {
            if (!current_user_can('manage_options')) {
                wp_die('No autorizado.');
            }

            check_admin_referer('ecopro_save_settings');

            $password = isset($_POST['eco_pass']) ? sanitize_text_field(wp_unslash($_POST['eco_pass'])) : '';
            $page_id  = isset($_POST['ecopro_front_page']) ? absint($_POST['ecopro_front_page']) : 0;

            update_option(self::OPTION_PAGE_ID, $page_id, false);

            if ($password !== '') {
                update_option(self::OPTION_PASSWORD, password_hash($password, PASSWORD_DEFAULT), false);

                if ($page_id > 0 && get_post($page_id) instanceof WP_Post) {
                    wp_update_post([
                        'ID'            => $page_id,
                        'post_password' => $password,
                    ]);
                }
            }

            wp_safe_redirect(admin_url('admin.php?page=eco-pro&updated=1'));
            exit;
        }

        public function add_category(): void {
            check_admin_referer('ecopro_add_category');

            if (!$this->frontend_or_admin_can_manage()) {
                wp_die('No autorizado.');
            }

            global $wpdb;

            $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

            if (!in_array($type, ['income', 'expense'], true) || $name === '') {
                $this->safe_back_redirect('invalid');
            }

            $result = $wpdb->insert(
                $this->table_categories,
                [
                    'name' => $name,
                    'type' => $type,
                ],
                ['%s', '%s']
            );

            if ($result === false) {
                $this->safe_back_redirect('db_error');
            }

            $this->safe_back_redirect('category_added');
        }

        public function add_tx(): void {
            check_admin_referer('ecopro_add_tx');

            if (!$this->frontend_or_admin_can_manage()) {
                wp_die('No autorizado.');
            }

            global $wpdb;

            $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
            $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
            $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
            $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';

            if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '' || $category_id <= 0) {
                $this->safe_back_redirect('invalid');
            }

            $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
            if ($cat_type !== $type) {
                $this->safe_back_redirect('type_mismatch');
            }

            $result = $wpdb->insert(
                $this->table_transactions,
                [
                    'type' => $type,
                    'category_id' => $category_id,
                    'amount' => $amount,
                    'description' => $description,
                ],
                ['%s', '%d', '%f', '%s']
            );

            if ($result === false) {
                $this->safe_back_redirect('db_error');
            }

            $this->safe_back_redirect('tx_added');
        }

        private function frontend_or_admin_can_manage(): bool {
            if (current_user_can('manage_options')) {
                return true;
            }
            return $this->frontend_access_granted();
        }

        private function frontend_access_granted(): bool {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            return !empty($_SESSION['ecopro_front_ok']);
        }

        private function mark_frontend_access_granted(): void {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['ecopro_front_ok'] = 1;
        }

        private function safe_back_redirect(string $notice = ''): void {
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=eco-pro');
            }

            if ($notice !== '') {
                $referer = add_query_arg('eco_notice', $notice, $referer);
            }

            wp_safe_redirect($referer);
            exit;
        }

        private function panel_styles(): string {
            return 'max-width:900px;margin:48px auto;padding:32px;background:#ffffff;border:1px solid #dcdcde;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);color:#1d2327;';
        }

        private function card_styles(): string {
            return 'background:#fff;border:1px solid #ddd;border-radius:12px;padding:18px;';
        }

        private function input_styles(): string {
            return 'width:100%;padding:12px 14px;border:1px solid #c3c4c7;border-radius:10px;background:#fff;color:#1d2327;font-size:16px;box-sizing:border-box;';
        }

        private function button_styles(): string {
            return 'display:inline-block;padding:12px 18px;border:0;border-radius:10px;background:#2271b1;color:#fff;font-weight:600;cursor:pointer;';
        }

        private function render_front_stats(array $totals): string {
            return '<div style="display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:14px;margin-bottom:18px;">
                <div style="' . esc_attr($this->card_styles()) . '"><strong>Ingresos</strong><div style="font-size:26px;margin-top:8px;">' . esc_html(number_format($totals['income'], 2, ',', '.')) . ' €</div></div>
                <div style="' . esc_attr($this->card_styles()) . '"><strong>Gastos</strong><div style="font-size:26px;margin-top:8px;">' . esc_html(number_format($totals['expense'], 2, ',', '.')) . ' €</div></div>
                <div style="' . esc_attr($this->card_styles()) . '"><strong>Balance</strong><div style="font-size:26px;margin-top:8px;">' . esc_html(number_format($totals['balance'], 2, ',', '.')) . ' €</div></div>
            </div>';
        }

        private function render_front_category_form(): string {
            return '<div style="' . esc_attr($this->card_styles()) . '">
                <h3 style="margin:0 0 14px 0;color:#1d2327;">Crear categoría</h3>
                <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:10px;flex-wrap:wrap;">
                    ' . wp_nonce_field('ecopro_add_category', '_wpnonce', true, false) . '
                    <input type="hidden" name="action" value="ecopro_add_category">
                    <select name="type" style="padding:12px;border:1px solid #c3c4c7;border-radius:10px;">
                        <option value="income">Ingreso</option>
                        <option value="expense">Gasto</option>
                    </select>
                    <input type="text" name="name" placeholder="Nueva categoría" style="min-width:220px;padding:12px;border:1px solid #c3c4c7;border-radius:10px;" required>
                    <button type="submit" style="' . esc_attr($this->button_styles()) . '">Añadir</button>
                </form>
            </div>';
        }

        private function render_front_category_list(array $categories): string {
            $html = '<div style="' . esc_attr($this->card_styles()) . '">
                <h3 style="margin:0 0 12px 0;color:#1d2327;">Categorías disponibles</h3>';

            if (empty($categories)) {
                return $html . '<p style="margin:0;color:#50575e;">No hay categorías todavía.</p></div>';
            }

            $html .= '<div style="overflow:auto;"><table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">ID</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Nombre</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Tipo</th>
                </tr></thead><tbody>';

            foreach ($categories as $cat) {
                $html .= '<tr>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . (int) $cat->id . '</td>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . esc_html($cat->name) . '</td>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . ($cat->type === 'income' ? 'Ingreso' : 'Gasto') . '</td>
                </tr>';
            }

            return $html . '</tbody></table></div></div>';
        }

        private function render_front_tx_form(array $categories): string {
            $options = '<option value="">Categoría</option>';
            foreach ($categories as $cat) {
                $label = ($cat->type === 'income' ? '[Ingreso] ' : '[Gasto] ') . $cat->name;
                $options .= '<option value="' . (int) $cat->id . '">' . esc_html($label) . '</option>';
            }

            return '<div style="' . esc_attr($this->card_styles()) . '">
                <h3 style="margin:0 0 14px 0;color:#1d2327;">Añadir movimiento</h3>
                <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:10px;flex-wrap:wrap;">
                    ' . wp_nonce_field('ecopro_add_tx', '_wpnonce', true, false) . '
                    <input type="hidden" name="action" value="ecopro_add_tx">
                    <select name="type" style="padding:12px;border:1px solid #c3c4c7;border-radius:10px;">
                        <option value="income">Ingreso</option>
                        <option value="expense">Gasto</option>
                    </select>
                    <select name="category_id" style="padding:12px;border:1px solid #c3c4c7;border-radius:10px;" required>' . $options . '</select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" style="min-width:140px;padding:12px;border:1px solid #c3c4c7;border-radius:10px;" required>
                    <input type="text" name="description" placeholder="Descripción" style="min-width:220px;padding:12px;border:1px solid #c3c4c7;border-radius:10px;" required>
                    <button type="submit" style="' . esc_attr($this->button_styles()) . '">Guardar</button>
                </form>
            </div>';
        }

        private function render_front_category_summary(array $items): string {
            if (empty($items)) {
                return '';
            }
            $html = '<div style="' . esc_attr($this->card_styles()) . '">
                <h3 style="margin:0 0 12px 0;color:#1d2327;">Estadísticas por categoría</h3>
                <ul style="margin:0;padding-left:18px;color:#50575e;font-size:16px;">';
            foreach ($items as $item) {
                $html .= '<li><strong>' . esc_html($item->name) . '</strong> (' . ($item->type === 'income' ? 'Ingreso' : 'Gasto') . '): ' . esc_html(number_format((float) $item->total, 2, ',', '.')) . ' €</li>';
            }
            return $html . '</ul></div>';
        }

        private function render_front_recent_transactions(array $rows): string {
            $html = '<div style="' . esc_attr($this->card_styles()) . '">
                <h3 style="margin:0 0 12px 0;color:#1d2327;">Últimos movimientos</h3>';

            if (empty($rows)) {
                return $html . '<p style="margin:0;color:#50575e;">No hay movimientos todavía.</p></div>';
            }

            $html .= '<div style="overflow:auto;"><table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Tipo</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Categoría</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Cantidad</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid #ddd;">Descripción</th>
                </tr></thead><tbody>';

            foreach ($rows as $r) {
                $html .= '<tr>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . ($r->type === 'income' ? 'Ingreso' : 'Gasto') . '</td>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . esc_html($r->category_name ?: '—') . '</td>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . esc_html(number_format((float) $r->amount, 2, ',', '.')) . ' €</td>
                    <td style="padding:10px;border-bottom:1px solid #eee;">' . esc_html($r->description) . '</td>
                </tr>';
            }

            return $html . '</tbody></table></div></div>';
        }

        public function dashboard(): string {
            $stored_hash = (string) get_option(self::OPTION_PASSWORD, '');

            if ($stored_hash === '') {
                return '<div style="' . esc_attr($this->panel_styles()) . '"><p style="margin:0;color:#1d2327;">El administrador todavía no ha configurado la contraseña.</p></div>';
            }

            if (isset($_POST['eco_login'])) {
                $password = isset($_POST['eco_pass']) ? (string) wp_unslash($_POST['eco_pass']) : '';
                if (password_verify($password, $stored_hash)) {
                    $this->mark_frontend_access_granted();
                } else {
                    return '<div style="' . esc_attr($this->panel_styles()) . '">
                        <p style="margin:0 0 12px 0;color:#b32d2e;font-weight:600;">Contraseña incorrecta.</p>
                        <form method="post">
                            <p style="margin:0 0 16px 0;">
                                <input type="password" name="eco_pass" placeholder="Contraseña" style="' . esc_attr($this->input_styles()) . '" required>
                            </p>
                            <p style="margin:0;">
                                <button type="submit" style="' . esc_attr($this->button_styles()) . '">Reintentar</button>
                            </p>
                            <input type="hidden" name="eco_login" value="1">
                        </form>
                    </div>';
                }
            }

            if (!$this->frontend_access_granted()) {
                return '<form method="post" style="' . esc_attr($this->panel_styles()) . '">
                    <h2 style="margin:0 0 18px 0;color:#1d2327;font-size:36px;line-height:1.1;">Acceso Economía</h2>
                    <p style="margin:0 0 18px 0;color:#50575e;">Introduce tu contraseña para acceder al panel financiero.</p>
                    <p style="margin:0 0 16px 0;">
                        <input type="password" name="eco_pass" placeholder="Contraseña" style="' . esc_attr($this->input_styles()) . '" required>
                    </p>
                    <p style="margin:0;">
                        <button type="submit" style="' . esc_attr($this->button_styles()) . '">Entrar</button>
                    </p>
                    <input type="hidden" name="eco_login" value="1">
                </form>';
            }

            $totals = $this->get_totals();
            $categories = $this->get_categories();
            $rows = $this->get_transactions();
            $summary = $this->get_front_category_summary();

            return '<div style="' . esc_attr($this->panel_styles()) . '">
                <h2 style="margin:0 0 18px 0;color:#1d2327;font-size:36px;line-height:1.1;">Dashboard Economía</h2>'
                . $this->get_notice_html()
                . $this->render_front_stats($totals)
                . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                . $this->render_front_category_form()
                . $this->render_front_tx_form($categories)
                . '</div>'
                . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'
                . $this->render_front_category_list($categories)
                . $this->render_front_category_summary($summary)
                . '</div>'
                . '<div style="margin-top:16px;">' . $this->render_front_recent_transactions($rows) . '</div>
            </div>';
        }
    }

    new EconomiaPro();
}
