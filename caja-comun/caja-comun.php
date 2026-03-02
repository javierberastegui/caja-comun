<?php
/**
 * Plugin Name: Caja Común
 * Description: Control financiero doméstico para parejas y familias con presupuesto común y parte separada fuera de presupuesto.
 * Version: 0.1.0
 * Author: Econom-a
 * Requires PHP: 8.0
 * Text Domain: caja-comun
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCF_PLUGIN_VERSION', '0.1.0' );
define( 'CCF_PLUGIN_FILE', __FILE__ );
define( 'CCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CCF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CCF_PLUGIN_DIR . 'src/Support/Autoloader.php';

\CCF\Support\Autoloader::register();

register_activation_hook( CCF_PLUGIN_FILE, array( \CCF\Core\Activator::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new \CCF\Core\Plugin();
		$plugin->run();
	}
);
