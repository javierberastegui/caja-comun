<?php

declare(strict_types=1);

namespace EcoPro\Core;

use EcoPro\Domain\Service\NotificationService;

final class Cron
{
    public static function register(): void
    {
        add_action('eco_pro_daily_check', [self::class, 'run']);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled('eco_pro_daily_check')) {
            wp_schedule_event(time(), 'daily', 'eco_pro_daily_check');
        }
    }

    public static function run(): void
    {
        (new NotificationService())->runDailyChecks();
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled('eco_pro_daily_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'eco_pro_daily_check');
        }
        flush_rewrite_rules();
    }
}
