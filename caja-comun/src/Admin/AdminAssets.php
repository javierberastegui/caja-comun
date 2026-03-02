<?php

namespace CCF\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminAssets {
	public function enqueue( string $hook ): void {
		$should_load = false;
		foreach ( array( 'caja-comun', 'ccf-' ) as $prefix ) {
			if ( false !== strpos( $hook, $prefix ) ) {
				$should_load = true;
				break;
			}
		}
		if ( ! $should_load ) {
			return;
		}
		wp_enqueue_style( 'ccf-admin', CCF_URL . 'assets/css/admin.css', array(), CCF_VERSION );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', array(), '4.4.3', true );
		wp_enqueue_script( 'ccf-admin', CCF_URL . 'assets/js/admin.js', array( 'chart-js' ), CCF_VERSION, true );
	}
}
