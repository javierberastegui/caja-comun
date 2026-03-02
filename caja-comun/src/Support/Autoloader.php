<?php

namespace CCF\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	private static function autoload( string $class ): void {
		$prefix = 'CCF\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = CCF_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
