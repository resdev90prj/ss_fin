<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Box.php';
require_once api_project_root() . '/models/Account.php';

class ApiBoxController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $items = (new Box())->allByUser($userId);

        $summary = [
            'total' => count($items),
            'active_count' => 0,
            'inactive_count' => 0,
            'linked_account_count' => 0,
            'total_balance' => 0.0,
        ];

        foreach ($items as &$item) {
            $status = (string)($item['status'] ?? 'inactive');
            $balance = (float)($item['balance'] ?? 0);
            $hasLinkedAccount = !empty($item['account_id']);

            $item['balance'] = $balance;
            $item['is_active'] = $status === 'active';

            $summary[$status === 'active' ? 'active_count' : 'inactive_count']++;
            $summary['total_balance'] += $balance;
            if ($hasLinkedAccount) {
                $summary['linked_account_count']++;
            }
        }
        unset($item);

        api_json_response(true, 'Caixas carregados com sucesso.', [
            'items' => $items,
            'summary' => $summary,
            'lookups' => [
                'accounts' => (new Account())->activeByUser($userId),
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

        (new Box())->create($data);

        api_json_response(true, 'Caixa criado com sucesso.');
    }

    public function update(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Box())->find($id, $userId)) {
            api_json_response(false, 'Caixa nao encontrado.', [], ['Caixa invalido para o usuario atual.'], 404);
        }

        $data = $this->validatedPayload($payload, $userId);
        (new Box())->update($id, $userId, $data);

        api_json_response(true, 'Caixa atualizado com sucesso.');
    }

    private function validatedPayload(array $payload, int $userId): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $objective = trim((string)($payload['objective'] ?? ''));
        $balance = (float)($payload['balance'] ?? 0);
        $status = $this->normalizeStatus((string)($payload['status'] ?? 'active'));
        $accountId = !empty($payload['account_id']) ? (int)$payload['account_id'] : null;

        if ($name === '') {
            api_json_response(false, 'Nome do caixa obrigatorio.', [], ['Informe o nome do caixa.'], 422);
        }

        if ($accountId !== null && !(new Account())->find($accountId, $userId)) {
            api_json_response(false, 'Conta invalida.', [], ['Selecione uma conta valida para vincular ao caixa.'], 422);
        }

        return [
            'account_id' => $accountId,
            'name' => $name,
            'objective' => $objective,
            'balance' => $balance,
            'status' => $status,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'inactive' ? 'inactive' : 'active';
    }
}
