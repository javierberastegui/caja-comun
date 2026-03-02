<?php

namespace CCF\Core;

use CCF\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		$installer = new Installer();
		$installer->install();
	}
}
