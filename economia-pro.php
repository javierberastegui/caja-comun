<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico con tablas propias, shortcode y cron diario.
 * Version: 1.0.0
 * Author: Loki
 * Text Domain: economia-pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ECO_PRO_VERSION', '1.0.0');
define('ECO_PRO_FILE', __FILE__);
define('ECO_PRO_PATH', plugin_dir_path(__FILE__));
define('ECO_PRO_URL', plugin_dir_url(__FILE__));

require_once ECO_PRO_PATH . 'includes/Core/Plugin.php';
require_once ECO_PRO_PATH . 'includes/Core/Installer.php';
require_once ECO_PRO_PATH . 'includes/Core/Page_Provisioner.php';
require_once ECO_PRO_PATH . 'includes/Core/Cron.php';
require_once ECO_PRO_PATH . 'includes/Domain/DTO/TransactionDTO.php';
require_once ECO_PRO_PATH . 'includes/Domain/Repository/TransactionRepositoryInterface.php';
require_once ECO_PRO_PATH . 'includes/Domain/Service/Sanitizer.php';
require_once ECO_PRO_PATH . 'includes/Domain/Service/FinanceService.php';
require_once ECO_PRO_PATH . 'includes/Domain/Service/NotificationService.php';
require_once ECO_PRO_PATH . 'includes/Infrastructure/Repository/WpdbTransactionRepository.php';
require_once ECO_PRO_PATH . 'includes/Http/Permissions.php';
require_once ECO_PRO_PATH . 'includes/Http/RestController.php';
require_once ECO_PRO_PATH . 'includes/Admin/Menu.php';
require_once ECO_PRO_PATH . 'includes/Admin/Assets.php';

register_activation_hook(__FILE__, [EcoPro\Core\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [EcoPro\Core\Cron::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new EcoPro\Core\Plugin())->boot();
});
