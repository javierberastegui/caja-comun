<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 2.1
 * Author: Loki
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('EconomiaPro')) {
    final class EconomiaPro {
        private const OPTION_PASSWORD = 'ecopro_front_password';
        private const OPTION_PAGE_ID  = 'ecopro_front_page_id';

        private string $table;

        public function __construct() {
            global $wpdb;
            $this->table = $wpdb->prefix . 'eco_transactions';

            register_activation_hook(__FILE__, [$this, 'install']);
            add_action('admin_menu', [$this, 'menu']);
            add_action('admin_post_ecopro_add_tx', [$this, 'add_tx']);
            add_action('admin_post_ecopro_save_settings', [$this, 'save_settings']);
            add_shortcode('economia_dashboard', [$this, 'dashboard']);
        }

        public function install(): void {
            global $wpdb;
            $charset = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(20) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY type (type),
                KEY created_at (created_at)
            ) {$charset};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
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

        public function admin_page(): void {
            if (!current_user_can('manage_options')) {
                wp_die('No autorizado.');
            }

            global $wpdb;
            $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 50");
            $income = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table} WHERE type='income'");
            $expense = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table} WHERE type='expense'");
            $balance = $income - $expense;
            $page_id = (int) get_option(self::OPTION_PAGE_ID, 0);
            $pages = get_pages([
                'sort_column' => 'post_title',
                'sort_order'  => 'asc',
            ]);
            ?>
            <div class="wrap">
                <h1>Economía Pro</h1>

                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                    <div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px;max-width:960px;margin:18px 0 24px 0;">
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Ingresos</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($income, 2, ',', '.')); ?> €</div>
                    </div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Gastos</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($expense, 2, ',', '.')); ?> €</div>
                    </div>
                    <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
                        <strong>Balance</strong>
                        <div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($balance, 2, ',', '.')); ?> €</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;max-width:1200px;">
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
                        <h2 style="margin-top:0;">Añadir movimiento</h2>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                            <?php wp_nonce_field('ecopro_add_tx'); ?>
                            <input type="hidden" name="action" value="ecopro_add_tx">

                            <select name="type">
                                <option value="income">Ingreso</option>
                                <option value="expense">Gasto</option>
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
                                            <td><?php echo esc_html(number_format((float) $r->amount, 2, ',', '.')); ?> €</td>
                                            <td><?php echo esc_html($r->description); ?></td>
                                            <td><?php echo esc_html($r->created_at); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5">No hay movimientos todavía.</td>
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

        public function add_tx(): void {
            if (!current_user_can('manage_options')) {
                wp_die('No autorizado.');
            }

            check_admin_referer('ecopro_add_tx');

            global $wpdb;

            $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
            $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
            $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';

            if (!in_array($type, ['income', 'expense'], true) || $amount <= 0 || $description === '') {
                wp_safe_redirect(admin_url('admin.php?page=eco-pro'));
                exit;
            }

            $wpdb->insert(
                $this->table,
                [
                    'type' => $type,
                    'amount' => $amount,
                    'description' => $description,
                ],
                ['%s', '%f', '%s']
            );

            wp_safe_redirect(admin_url('admin.php?page=eco-pro'));
            exit;
        }

        private function panel_styles(): string {
            return 'max-width:720px;margin:48px auto;padding:32px;background:#ffffff;border:1px solid #dcdcde;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);color:#1d2327;';
        }

        private function input_styles(): string {
            return 'width:100%;padding:12px 14px;border:1px solid #c3c4c7;border-radius:10px;background:#fff;color:#1d2327;font-size:16px;box-sizing:border-box;';
        }

        private function button_styles(): string {
            return 'display:inline-block;padding:12px 18px;border:0;border-radius:10px;background:#2271b1;color:#fff;font-weight:600;cursor:pointer;';
        }

        public function dashboard(): string {
            global $wpdb;

            $income = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table} WHERE type='income'");
            $expense = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table} WHERE type='expense'");
            $balance = $income - $expense;

            $stored_hash = (string) get_option(self::OPTION_PASSWORD, '');

            if ($stored_hash === '') {
                return '<div style="' . esc_attr($this->panel_styles()) . '"><p style="margin:0;color:#1d2327;">El administrador todavía no ha configurado la contraseña.</p></div>';
            }

            if (!isset($_POST['eco_login'])) {
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

            $password = isset($_POST['eco_pass']) ? (string) wp_unslash($_POST['eco_pass']) : '';

            if (!password_verify($password, $stored_hash)) {
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

            return '<div style="' . esc_attr($this->panel_styles()) . '">
                <h2 style="margin:0 0 14px 0;color:#1d2327;font-size:36px;line-height:1.1;">Dashboard Economía</h2>
                <p style="margin:0 0 8px 0;color:#50575e;font-size:18px;"><strong>Ingresos:</strong> ' . esc_html(number_format($income, 2, ',', '.')) . ' €</p>
                <p style="margin:0 0 8px 0;color:#50575e;font-size:18px;"><strong>Gastos:</strong> ' . esc_html(number_format($expense, 2, ',', '.')) . ' €</p>
                <p style="margin:0;color:#50575e;font-size:18px;"><strong>Balance:</strong> ' . esc_html(number_format($balance, 2, ',', '.')) . ' €</p>
            </div>';
        }
    }

    new EconomiaPro();
}
