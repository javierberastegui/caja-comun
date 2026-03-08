<?php

declare(strict_types=1);

namespace EcoPro\Http;

trait Permissions
{
    private function canManage(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_finance');
    }
}
