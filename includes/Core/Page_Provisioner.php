<?php

declare(strict_types=1);

namespace EcoPro\Core;

final class Page_Provisioner
{
    public static function register(): void
    {
        add_shortcode('economia_dashboard', [self::class, 'renderShortcode']);
    }

    public static function ensurePage(): void
    {
        $pageId = (int) get_option('eco_pro_page_id', 0);
        if ($pageId > 0 && get_post($pageId)) {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'   => 'Economía',
            'post_name'    => 'economia',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[economia_dashboard]',
            'post_password'=> 'cambiar-esta-clave',
        ]);

        if (!is_wp_error($pageId) && $pageId > 0) {
            update_option('eco_pro_page_id', $pageId);
        }
    }

    public static function renderShortcode(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Debes iniciar sesión para ver este panel.</p>';
        }

        ob_start();
        include ECO_PRO_PATH . 'templates/shortcode-dashboard.php';
        return (string) ob_get_clean();
    }
}
