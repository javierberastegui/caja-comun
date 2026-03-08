<?php

declare(strict_types=1);

namespace EcoPro\Core;

use EcoPro\Admin\Assets;
use EcoPro\Admin\Menu;
use EcoPro\Http\RestController;

final class Plugin
{
    public function boot(): void
    {
        (new Menu())->register();
        (new Assets())->register();
        (new RestController())->register();
        Cron::register();
        Page_Provisioner::register();
    }
}
