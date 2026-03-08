<?php

declare(strict_types=1);

namespace EcoPro\Domain\Service;

use EcoPro\Domain\DTO\TransactionDTO;
use EcoPro\Domain\Repository\TransactionRepositoryInterface;

final class FinanceService
{
    public function __construct(private TransactionRepositoryInterface $transactions)
    {
    }

    public function addTransaction(TransactionDTO $dto): int
    {
        Sanitizer::assertValidAmount($dto->amount);
        Sanitizer::assertValidType($dto->type);

        return $this->transactions->create($dto);
    }

    public function monthlySavingsCapacity(float $income, float $fixed, float $variable): float
    {
        return round($income - $fixed - $variable, 2);
    }
}
