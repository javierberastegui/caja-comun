<?php

namespace CCF\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DatabaseManager {
	public function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'ccf_' . $name;
	}

	public function now(): string {
		return current_time( 'mysql', true );
	}
}
