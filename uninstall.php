<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('eco_pro_needs_setup');
delete_option('eco_pro_front_password');
