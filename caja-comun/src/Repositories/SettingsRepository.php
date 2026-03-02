<?php

namespace CCF\Repositories;

use CCF\Database\DatabaseManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsRepository {
	private DatabaseManager $database_manager;

	public function __construct( DatabaseManager $database_manager ) {
		$this->database_manager = $database_manager;
	}

	public function get( string $key, ?string $default = null ): ?string {
		global $wpdb;
		$table = $this->database_manager->table( 'settings' );
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key ) );

		return null === $value ? $default : (string) $value;
	}

	public function set( string $key, string $value, int $autoload = 1 ): bool {
		global $wpdb;
		$table = $this->database_manager->table( 'settings' );
		$now   = $this->database_manager->now();
		$id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) );
		if ( $id ) {
			return false !== $wpdb->update(
				$table,
				array(
					'setting_value' => $value,
					'autoload'      => $autoload,
					'updated_at'    => $now,
				),
				array( 'id' => (int) $id )
			);
		}

		return false !== $wpdb->insert(
			$table,
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
				'autoload'      => $autoload,
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);
	}
}
