<?php
/**
 * Uninstall Economia Pro.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('eco_pro_page_id');
delete_option('eco_pro_last_daily_check');
