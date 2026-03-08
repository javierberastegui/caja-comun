<?php

declare(strict_types=1);

namespace EcoPro\Admin;

final class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', function (string $hook): void {
            if ($hook !== 'toplevel_page_eco-pro') {
                return;
            }

            wp_enqueue_style('eco-pro-admin', ECO_PRO_URL . 'assets/css/economia.css', [], ECO_PRO_VERSION);
            wp_enqueue_script('eco-pro-admin', ECO_PRO_URL . 'assets/js/admin-app.js', ['wp-api-fetch'], ECO_PRO_VERSION, true);
            wp_localize_script('eco-pro-admin', 'ecoProData', [
                'restUrl' => esc_url_raw(rest_url('economia/v1/')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]);
        });

        add_action('wp_enqueue_scripts', function (): void {
            if (!is_page()) {
                return;
            }

            wp_enqueue_style('eco-pro-front', ECO_PRO_URL . 'assets/css/economia.css', [], ECO_PRO_VERSION);
            wp_enqueue_script('eco-pro-front', ECO_PRO_URL . 'assets/js/front-app.js', [], ECO_PRO_VERSION, true);
        });
    }
}
