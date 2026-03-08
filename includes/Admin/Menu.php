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
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>Economía Pro</h1><div id="eco-pro-admin-app"></div></div>';
    }
}
