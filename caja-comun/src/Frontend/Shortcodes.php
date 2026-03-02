<?php

namespace CCF\Frontend;

use CCF\Services\DashboardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {
	private DashboardService $dashboard_service;

	public function __construct( DashboardService $dashboard_service ) {
		$this->dashboard_service = $dashboard_service;
	}

	public function register(): void {
		add_shortcode( 'caja_comun_dashboard', array( $this, 'render_dashboard' ) );
	}

	public function render_dashboard( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'month'         => gmdate( 'Y-m' ),
				'show_incomes'  => 'yes',
				'show_movements'=> 'yes',
			),
			$atts,
			'caja_comun_dashboard'
		);

		if ( ! is_user_logged_in() ) {
			return '<p>Debes iniciar sesión para ver Caja Común.</p>';
		}

		$month_key = sanitize_text_field( $atts['month'] );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_key ) ) {
			$month_key = gmdate( 'Y-m' );
		}

		$summary = $this->dashboard_service->month_summary( $month_key );

		ob_start();
		require CCF_PLUGIN_DIR . 'templates/frontend/dashboard-shortcode.php';
		return (string) ob_get_clean();
	}
}
