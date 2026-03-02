<?php

namespace CCF\Services;

use CCF\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeatureFlagsService {
	private const FLAGS = array(
		'enable_transactions_ui' => '0',
		'enable_accounts_ui' => '0',
		'enable_categories_ui' => '0',
		'enable_attachments_ui' => '0',
		'enable_review_ui' => '0',
	);

	public function __construct( private SettingsRepository $settings_repository ) {}

	public function is_enabled( string $flag ): bool {
		$default = self::FLAGS[ $flag ] ?? '0';
		return '1' === $this->settings_repository->get( $flag, $default );
	}

	public function defaults(): array {
		return self::FLAGS;
	}
}
