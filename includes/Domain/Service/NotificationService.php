<?php

declare(strict_types=1);

namespace EcoPro\Domain\Service;

final class NotificationService
{
    public function runDailyChecks(): void
    {
        $log = [
            'ran_at' => current_time('mysql'),
            'message' => 'Chequeo diario ejecutado.',
        ];

        update_option('eco_pro_last_daily_check', $log, false);
    }
}
