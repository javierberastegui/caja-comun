<?php

declare(strict_types=1);

namespace EcoPro\Core;

final class Page_Provisioner
{
    private const COOKIE_NAME = 'eco_pro_front_access';

    public static function register(): void
    {
        add_shortcode('economia_dashboard', [self::class, 'renderShortcode']);
        add_action('init', [self::class, 'handleFrontPasswordSubmit']);
    }

    public static function handleFrontPasswordSubmit(): void
    {
        if (!isset($_POST['eco_pro_front_submit'])) {
            return;
        }

        $password = isset($_POST['eco_pro_front_password']) ? sanitize_text_field((string) $_POST['eco_pro_front_password']) : '';
        $storedHash = (string) get_option('eco_pro_front_password', '');

        if ($storedHash !== '' && wp_check_password($password, $storedHash)) {
            $token = wp_hash($storedHash . '|' . wp_salt('auth'));
            setcookie(self::COOKIE_NAME, $token, time() + DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE[self::COOKIE_NAME] = $token;
        }
    }

    public static function renderShortcode(): string
    {
        $storedHash = (string) get_option('eco_pro_front_password', '');

        if ($storedHash === '') {
            return '<div class="eco-card"><p>Primero configura la contraseña del frontend en Economía &gt; Configurar acceso.</p></div>';
        }

        $expectedToken = wp_hash($storedHash . '|' . wp_salt('auth'));
        $currentToken = isset($_COOKIE[self::COOKIE_NAME]) ? (string) $_COOKIE[self::COOKIE_NAME] : '';

        if (!hash_equals($expectedToken, $currentToken)) {
            return self::renderPasswordForm();
        }

        ob_start();
        include ECO_PRO_PATH . 'templates/shortcode-dashboard.php';
        return (string) ob_get_clean();
    }

    private static function renderPasswordForm(): string
    {
        $action = esc_url(get_permalink() ?: '');

        return '<div class="eco-card">'
            . '<h2>Acceso protegido</h2>'
            . '<p>Introduce la contraseña del panel financiero.</p>'
            . '<form method="post" action="' . $action . '">'
            . wp_nonce_field('eco_pro_front_access', '_wpnonce', true, false)
            . '<p><input type="password" name="eco_pro_front_password" class="regular-text" placeholder="Contraseña"></p>'
            . '<p><button type="submit" name="eco_pro_front_submit" class="button button-primary">Entrar</button></p>'
            . '</form>'
            . '</div>';
    }
}
