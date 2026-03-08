<?php

declare(strict_types=1);

namespace EcoPro\Admin;

final class Menu
{
    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_menu_page(
                'Economía',
                'Economía',
                'manage_finance',
                'eco-pro',
                [$this, 'render'],
                'dashicons-chart-pie',
                56
            );
        });

        add_action('admin_init', [$this, 'maybeRedirectAfterActivation']);
        add_action('admin_post_eco_pro_save_front_password', [$this, 'saveFrontPassword']);
    }

    public function maybeRedirectAfterActivation(): void
    {
        if (!current_user_can('manage_finance')) {
            return;
        }

        if (get_transient('eco_pro_activation_redirect') !== '1') {
            return;
        }

        delete_transient('eco_pro_activation_redirect');

        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=eco-pro'));
        exit;
    }

    public function saveFrontPassword(): void
    {
        if (!current_user_can('manage_finance')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('eco_pro_save_front_password');

        $password = isset($_POST['eco_front_password']) ? sanitize_text_field((string) $_POST['eco_front_password']) : '';

        if ($password === '') {
            update_option('eco_pro_front_password', '');
            update_option('eco_pro_needs_setup', 'yes');
        } else {
            update_option('eco_pro_front_password', wp_hash_password($password));
            update_option('eco_pro_needs_setup', 'no');
        }

        wp_safe_redirect(admin_url('admin.php?page=eco-pro&updated=1'));
        exit;
    }

    public function render(): void
    {
        $needsSetup = get_option('eco_pro_needs_setup', 'yes') === 'yes';
        $updated = isset($_GET['updated']) ? 1 : 0;

        echo '<div class="wrap"><h1>Economía Pro</h1>';

        if ($updated) {
            echo '<div class="notice notice-success is-dismissible"><p>Contraseña frontend guardada.</p></div>';
        }

        echo '<div class="eco-card" style="max-width:900px;padding:24px;margin-top:20px;">';
        echo '<h2>Configurar acceso frontend</h2>';
        echo '<p>Pega este shortcode en la página que quieras mostrar en frontend:</p>';
        echo '<code>[economia_dashboard]</code>';
        echo '<p style="margin-top:12px;">Al entrar en esa página, el visitante tendrá que introducir la contraseña que definas aquí.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:18px;">';
        wp_nonce_field('eco_pro_save_front_password');
        echo '<input type="hidden" name="action" value="eco_pro_save_front_password">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="eco_front_password">Contraseña del frontend</label></th>';
        echo '<td><input type="text" id="eco_front_password" name="eco_front_password" class="regular-text" placeholder="Elige una contraseña"></td></tr>';
        echo '</tbody></table>';
        submit_button($needsSetup ? 'Guardar contraseña y activar frontend' : 'Actualizar contraseña');
        echo '</form>';
        echo '</div>';

        echo '<div id="eco-pro-admin-app" style="margin-top:20px;"></div>';
        echo '</div>';
    }
}
