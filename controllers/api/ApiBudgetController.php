<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Budget.php';
require_once api_project_root() . '/models/Category.php';

class ApiBudgetController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $items = (new Budget())->allByUser($userId);

        $summary = [
            'total' => count($items),
            'current_month_count' => 0,
            'total_limit' => 0.0,
        ];
        $currentMonth = date('Y-m');

        foreach ($items as &$item) {
            $amountLimit = (float)($item['amount_limit'] ?? 0);
            $item['amount_limit'] = $amountLimit;
            $summary['total_limit'] += $amountLimit;
            if ((string)($item['month_ref'] ?? '') === $currentMonth) {
                $summary['current_month_count']++;
            }
        }
        unset($item);

        api_json_response(true, 'Orcamentos carregados com sucesso.', [
            'items' => $items,
            'summary' => $summary,
            'lookups' => [
                'categories' => (new Category())->activeByUser($userId),
            ],
        ]);
    }

    public function store(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $data = $this->validatedPayload($payload, $userId);
        $data['user_id'] = $userId;

        (new Budget())->upsert($data);

        api_json_response(true, 'Orcamento salvo com sucesso.');
    }

    public function delete(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Budget())->find($id, $userId)) {
            api_json_response(false, 'Orcamento nao encontrado.', [], ['Orcamento invalido para o usuario atual.'], 404);
        }

        (new Budget())->delete($id, $userId);

        api_json_response(true, 'Orcamento removido com sucesso.');
    }

    private function validatedPayload(array $payload, int $userId): array
    {
        $categoryId = (int)($payload['category_id'] ?? 0);
        $monthRef = trim((string)($payload['month_ref'] ?? date('Y-m')));
        $amountLimit = (float)($payload['amount_limit'] ?? 0);

        if ($categoryId <= 0 || !(new Category())->find($categoryId, $userId)) {
            api_json_response(false, 'Categoria invalida.', [], ['Selecione uma categoria valida para o orcamento.'], 422);
        }

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthRef)) {
            api_json_response(false, 'Mes de referencia invalido.', [], ['Use o formato YYYY-MM.'], 422);
        }

        if ($amountLimit <= 0) {
            api_json_response(false, 'Limite invalido.', [], ['Informe um limite maior que zero.'], 422);
        }

        return [
            'category_id' => $categoryId,
            'month_ref' => $monthRef,
            'amount_limit' => $amountLimit,
        ];
    }
}
