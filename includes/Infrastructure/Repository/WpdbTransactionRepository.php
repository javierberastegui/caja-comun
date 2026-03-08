<?php

declare(strict_types=1);

namespace EcoPro\Infrastructure\Repository;

use EcoPro\Domain\DTO\TransactionDTO;
use EcoPro\Domain\Repository\TransactionRepositoryInterface;

final class WpdbTransactionRepository implements TransactionRepositoryInterface
{
    public function create(TransactionDTO $dto): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'eco_transactions';
        $wpdb->insert($table, [
            'budget_id'   => $dto->budgetId,
            'category_id' => $dto->categoryId,
            'type'        => $dto->type,
            'amount'      => $dto->amount,
            'txn_date'    => $dto->txnDate,
            'description' => $dto->description,
            'reference'   => $dto->reference,
            'status'      => $dto->status,
            'meta'        => $dto->meta,
            'created_by'  => $dto->createdBy,
        ], [
            '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d'
        ]);

        return (int) $wpdb->insert_id;
    }

    public function list(array $filters = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'eco_transactions';
        $sql = "SELECT * FROM {$table} ORDER BY txn_date DESC, id DESC LIMIT 100";
        return (array) $wpdb->get_results($sql, ARRAY_A);
    }
}
