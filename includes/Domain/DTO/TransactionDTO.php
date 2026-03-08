<?php

declare(strict_types=1);

namespace EcoPro\Domain\DTO;

final class TransactionDTO
{
    public function __construct(
        public readonly int $categoryId,
        public readonly string $type,
        public readonly float $amount,
        public readonly string $txnDate,
        public readonly string $description,
        public readonly int $createdBy,
        public readonly ?int $budgetId = null,
        public readonly string $status = 'confirmed',
        public readonly ?string $reference = null,
        public readonly ?string $meta = null,
    ) {}
}
