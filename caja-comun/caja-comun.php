<?php
/**
 * Plugin Name: Caja Común
 * Description: Gestión financiera doméstica con presupuesto común, ingresos mensuales, separación fuera de presupuesto y dashboard visual.
 * Version: 0.1.0
 * Author: Econom-a
 * Requires PHP: 8.0
 * Text Domain: caja-comun
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCF_VERSION', '0.1.0' );
define( 'CCF_FILE', __FILE__ );
define( 'CCF_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCF_URL', plugin_dir_url( __FILE__ ) );
define( 'CCF_BASENAME', plugin_basename( __FILE__ ) );

require_once CCF_PATH . 'src/Support/Autoloader.php';

\CCF\Support\Autoloader::register();

register_activation_hook( CCF_FILE, array( \CCF\Core\Activator::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new \CCF\Core\Plugin();
		$plugin->run();
	}
);
