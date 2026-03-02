<?php

namespace CCF\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminAssets {
	public function enqueue( string $hook ): void {
		$allowed_prefixes = array( 'caja-comun', 'ccf-' );

		$should_load = false;
		foreach ( $allowed_prefixes as $prefix ) {
			if ( false !== strpos( $hook, $prefix ) ) {
				$should_load = true;
				break;
			}
		}

		if ( ! $should_load ) {
			return;
		}

		wp_enqueue_style( 'ccf-admin', CCF_URL . 'assets/css/admin.css', array(), CCF_VERSION );
		wp_enqueue_script( 'ccf-admin', CCF_URL . 'assets/js/admin.js', array(), CCF_VERSION, true );
	}
}
