<?php
/**
 * Plugin Name: Farmacia Portal Interno
 * Plugin URI: https://example.com/
 * Description: Portal interno de gestión para farmacia con multirol, permisos, auditoría y almacenamiento conectado.
 * Version: 4.8.4
 * Author: Loki
 * Author URI: https://example.com/
 * Text Domain: farmacia-portal-interno
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (defined('FPI_VERSION') || function_exists('fpi_boot_plugin') || class_exists('FPI_Plugin', false)) {
    return;
}

define('FPI_VERSION', '4.8.4');
define('FPI_PLUGIN_FILE', __FILE__);
define('FPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FPI_TABLE_PREFIX', 'fpi_');

require_once FPI_PLUGIN_DIR . 'includes/class-fpi-db.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-activator.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-audit.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-notifications.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-roles.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-permissions.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-access.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-storage.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-items.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-workflows.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-admin.php';
require_once FPI_PLUGIN_DIR . 'includes/class-fpi-plugin.php';

register_activation_hook(__FILE__, ['FPI_Activator', 'activate']);

if (! function_exists('fpi_boot_plugin')) {
    function fpi_boot_plugin(): FPI_Plugin
    {
        static $plugin = null;

        if ($plugin === null) {
            $plugin = new FPI_Plugin();
            $plugin->run();
        }

        return $plugin;
    }
}

if (! has_action('plugins_loaded', 'fpi_boot_plugin')) {
    add_action('plugins_loaded', 'fpi_boot_plugin');
}
