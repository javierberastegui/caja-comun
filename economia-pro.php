<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 1.5
 * Author: Loki
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Loki_Economia_Pro')) {
    final class Loki_Economia_Pro {
        private const OPTION_PASSWORD = 'ecopro_front_password';
        private const OPTION_PAGE_ID   = 'ecopro_front_page_id';

        public function __construct() {
            register_activation_hook(__FILE__, [self::class, 'activate']);
            add_action('admin_menu', [$this, 'register_menu']);
            add_action('admin_post_ecopro_save_password', [$this, 'save_settings']);
            add_shortcode('economia_dashboard', [$this, 'render_shortcode']);
        }

        public static function activate(): void {
            if (!function_exists('deactivate_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $self = plugin_basename(__FILE__);
            $active_plugins = (array) get_option('active_plugins', []);

            foreach ($active_plugins as $plugin_file) {
                if ($plugin_file === $self) {
                    continue;
                }

                if (
                    preg_match('~(^|/)(economia-pro(?:-v[\d.]+)?)/economia-pro\.php$~i', $plugin_file)
                    || basename($plugin_file) === 'economia-pro.php'
                ) {
                    deactivate_plugins($plugin_file, true);
                }
            }
        }

        public function register_menu(): void {
            add_menu_page(
                'Economía Pro',
                'Economía',
                'manage_options',
                'eco-pro',
                [$this, 'render_admin_page'],
                'dashicons-chart-line',
                26
            );
        }

        public function render_admin_page(): void {
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

                <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;max-width:760px;">
                    <h2 style="margin-top:0;">Configurar acceso frontend</h2>
                    <p>Pega este shortcode en la página donde quieras mostrar el panel:</p>
                    <p><code>[economia_dashboard]</code></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ecopro_save_password'); ?>
                        <input type="hidden" name="action" value="ecopro_save_password">

                        <p>
                            <label for="ecopro_front_page"><strong>Página del frontend</strong></label><br>
                            <select id="ecopro_front_page" name="ecopro_front_page" style="width:320px;">
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
                            <input id="eco_pass" type="password" name="eco_pass" style="width:320px;" placeholder="Escribe la nueva contraseña" required>
                        </p>

                        <p style="max-width:680px;color:#50575e;">
                            Si eliges una página arriba, el plugin sincroniza esta contraseña con la protección nativa de WordPress de esa página.
                        </p>

                        <p>
                            <button type="submit" class="button button-primary">Guardar contraseña</button>
                        </p>
                    </form>
                </div>
            </div>
            <?php
        }

        public function save_settings(): void {
            if (!current_user_can('manage_options')) {
                wp_die('No autorizado.');
            }

            check_admin_referer('ecopro_save_password');

            $password = isset($_POST['eco_pass']) ? sanitize_text_field(wp_unslash($_POST['eco_pass'])) : '';
            $page_id  = isset($_POST['ecopro_front_page']) ? absint($_POST['ecopro_front_page']) : 0;

            if ($password === '') {
                wp_safe_redirect(admin_url('admin.php?page=eco-pro'));
                exit;
            }

            update_option(self::OPTION_PASSWORD, password_hash($password, PASSWORD_DEFAULT), false);
            update_option(self::OPTION_PAGE_ID, $page_id, false);

            if ($page_id > 0 && get_post($page_id) instanceof WP_Post) {
                wp_update_post([
                    'ID'            => $page_id,
                    'post_password' => $password,
                ]);
            }

            wp_safe_redirect(admin_url('admin.php?page=eco-pro&updated=1'));
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

        public function render_shortcode(): string {
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
                <p style="margin:0;color:#50575e;font-size:18px;">Sin transacciones aún.</p>
            </div>';
        }
    }

    new Loki_Economia_Pro();
}
