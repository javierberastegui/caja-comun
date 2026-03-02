<?php

namespace CCF\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilities {
	public const MANAGE_SETTINGS = 'ccf_manage_settings';
	public const MANAGE_INCOMES = 'ccf_manage_incomes';
	public const MANAGE_ALLOCATIONS = 'ccf_manage_allocations';
	public const MANAGE_ACCOUNTS = 'ccf_manage_accounts';
	public const MANAGE_CATEGORIES = 'ccf_manage_categories';
	public const MANAGE_TRANSACTIONS = 'ccf_manage_transactions';
	public const MANAGE_ATTACHMENTS = 'ccf_manage_attachments';
	public const MANAGE_NOTES = 'ccf_manage_notes';
	public const VIEW_DASHBOARD = 'ccf_view_dashboard';

	public static function all(): array {
		return array(
			self::MANAGE_SETTINGS,
			self::MANAGE_INCOMES,
			self::MANAGE_ALLOCATIONS,
			self::MANAGE_ACCOUNTS,
			self::MANAGE_CATEGORIES,
			self::MANAGE_TRANSACTIONS,
			self::MANAGE_ATTACHMENTS,
			self::MANAGE_NOTES,
			self::VIEW_DASHBOARD,
		);
	}
}
