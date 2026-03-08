<?php

declare(strict_types=1);

namespace EcoPro\Domain\Service;

final class Sanitizer
{
    public static function assertValidAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El importe debe ser mayor que cero.');
        }
    }

    public static function assertValidType(string $type): void
    {
        $valid = ['income', 'expense', 'transfer', 'debt_payment'];
        if (!in_array($type, $valid, true)) {
            throw new \InvalidArgumentException('Tipo de transacción no válido.');
        }
    }
}
