<?php

namespace CCF\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminAssets {
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'ccf' ) ) {
			return;
		}

		wp_enqueue_style( 'ccf-admin', CCF_PLUGIN_URL . 'assets/css/admin.css', array(), CCF_PLUGIN_VERSION );
		wp_enqueue_script( 'ccf-admin', CCF_PLUGIN_URL . 'assets/js/admin.js', array(), CCF_PLUGIN_VERSION, true );
	}
}
