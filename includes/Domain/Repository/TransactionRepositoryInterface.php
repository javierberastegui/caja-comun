<?php

declare(strict_types=1);

namespace EcoPro\Domain\Repository;

use EcoPro\Domain\DTO\TransactionDTO;

interface TransactionRepositoryInterface
{
    public function create(TransactionDTO $dto): int;
    public function list(array $filters = []): array;
}
