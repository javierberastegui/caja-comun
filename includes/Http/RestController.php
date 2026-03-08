<?php

declare(strict_types=1);

namespace EcoPro\Http;

use EcoPro\Domain\DTO\TransactionDTO;
use EcoPro\Domain\Service\FinanceService;
use EcoPro\Infrastructure\Repository\WpdbTransactionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RestController
{
    use Permissions;

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('economia/v1', '/transactions', [
                [
                    'methods'  => 'GET',
                    'callback' => [$this, 'listTransactions'],
                    'permission_callback' => fn(): bool => $this->canManage(),
                ],
                [
                    'methods'  => 'POST',
                    'callback' => [$this, 'createTransaction'],
                    'permission_callback' => fn(): bool => $this->canManage(),
                ],
            ]);
        });
    }

    public function listTransactions(): WP_REST_Response
    {
        $repo = new WpdbTransactionRepository();
        return new WP_REST_Response($repo->list());
    }

    public function createTransaction(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $dto = new TransactionDTO(
                categoryId: (int) $request->get_param('category_id'),
                type: sanitize_text_field((string) $request->get_param('type')),
                amount: (float) $request->get_param('amount'),
                txnDate: sanitize_text_field((string) $request->get_param('txn_date')),
                description: sanitize_text_field((string) $request->get_param('description')),
                createdBy: get_current_user_id(),
                budgetId: $request->get_param('budget_id') !== null ? (int) $request->get_param('budget_id') : null,
                status: sanitize_text_field((string) ($request->get_param('status') ?: 'confirmed')),
                reference: $request->get_param('reference') ? sanitize_text_field((string) $request->get_param('reference')) : null,
                meta: $request->get_param('meta') ? wp_json_encode($request->get_param('meta')) : null,
            );

            $service = new FinanceService(new WpdbTransactionRepository());
            $id = $service->addTransaction($dto);

            return new WP_REST_Response(['id' => $id], 201);
        } catch (\Throwable $e) {
            return new WP_Error('eco_pro_create_failed', $e->getMessage(), ['status' => 400]);
        }
    }
}
