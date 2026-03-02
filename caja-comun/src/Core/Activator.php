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
		self::register_capabilities();
	}

	private static function register_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( Capabilities::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}
}
