
<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 1.2
 * Author: Loki
 */

if (!defined('ABSPATH')) exit;

class EcoPro {

    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_shortcode('economia_dashboard', [$this,'shortcode']);
        add_action('admin_post_ecopro_save_password', [$this,'save_password']);
    }

    public function menu() {
        add_menu_page(
            'Economía Pro',
            'Economía',
            'manage_options',
            'eco-pro',
            [$this,'admin_page'],
            'dashicons-chart-line',
            26
        );
    }

    public function admin_page() {
        $pass = get_option('ecopro_front_password','');
        ?>
        <div class="wrap">
            <h1>Economía Pro</h1>

            <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:6px;max-width:700px;">
                <h2>Configurar acceso frontend</h2>
                <p>Pega este shortcode en la página donde quieras mostrar el panel:</p>
                <code>[economia_dashboard]</code>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="ecopro_save_password">

                    <p>
                        <label><strong>Contraseña del frontend</strong></label><br>
                        <input type="password" name="eco_pass" style="width:300px" placeholder="Elige una contraseña">
                    </p>

                    <p>
                        <button class="button button-primary">Guardar contraseña</button>
                    </p>
                </form>
            </div>

        </div>
        <?php
    }

    public function save_password() {
        if (!current_user_can('manage_options')) wp_die();

        $pass = sanitize_text_field($_POST['eco_pass'] ?? '');

        if ($pass) {
            update_option('ecopro_front_password', password_hash($pass, PASSWORD_DEFAULT));
        }

        wp_redirect(admin_url('admin.php?page=eco-pro'));
        exit;
    }

    public function shortcode() {

        $stored = get_option('ecopro_front_password','');

        if (!$stored) {
            return "<p>El administrador todavía no ha configurado la contraseña.</p>";
        }

        if (!isset($_POST['eco_login'])) {
            return '
            <form method="post">
                <p><strong>Acceso Economía</strong></p>
                <input type="password" name="eco_pass">
                <button>Entrar</button>
                <input type="hidden" name="eco_login" value="1">
            </form>';
        }

        $pass = $_POST['eco_pass'] ?? '';

        if (!password_verify($pass, $stored)) {
            return "<p>Contraseña incorrecta.</p>";
        }

        return "<div style='padding:20px;background:#fff;border:1px solid #ddd'>
        <h2>Dashboard Economía</h2>
        <p>Sin transacciones aún.</p>
        </div>";
    }
}

new EcoPro();
