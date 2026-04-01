<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Account.php';

class ApiAccountController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $items = (new Account())->allByUser($userId);

        $summary = [
            'total' => count($items),
            'active_count' => 0,
            'inactive_count' => 0,
            'pf_count' => 0,
            'pj_count' => 0,
            'total_initial_balance' => 0.0,
        ];

        foreach ($items as &$item) {
            $status = (string)($item['status'] ?? 'inactive');
            $type = strtoupper((string)($item['type'] ?? 'PF'));
            $initialBalance = (float)($item['initial_balance'] ?? 0);

            $item['initial_balance'] = $initialBalance;
            $item['is_active'] = $status === 'active';

            $summary['total_initial_balance'] += $initialBalance;
            $summary[$status === 'active' ? 'active_count' : 'inactive_count']++;

            if ($type === 'PJ') {
                $summary['pj_count']++;
            } else {
                $summary['pf_count']++;
            }
        }
        unset($item);

        api_json_response(true, 'Contas carregadas com sucesso.', [
            'items' => $items,
            'summary' => $summary,
        ]);
    }

    public function store(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $data = $this->validatedPayload($payload);
        $data['user_id'] = $userId;

        (new Account())->create($data);

        api_json_response(true, 'Conta criada com sucesso.');
    }

    public function update(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Account())->find($id, $userId)) {
            api_json_response(false, 'Conta nao encontrada.', [], ['Conta invalida para o usuario atual.'], 404);
        }

        $data = $this->validatedPayload($payload);
        (new Account())->update($id, $userId, $data);

        api_json_response(true, 'Conta atualizada com sucesso.');
    }

    public function toggle(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Account())->find($id, $userId)) {
            api_json_response(false, 'Conta nao encontrada.', [], ['Conta invalida para o usuario atual.'], 404);
        }

        (new Account())->toggleStatus($id, $userId);

        api_json_response(true, 'Status da conta atualizado com sucesso.');
    }

    private function validatedPayload(array $payload): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $type = strtoupper(trim((string)($payload['type'] ?? 'PF')));
        $institution = trim((string)($payload['institution'] ?? ''));
        $status = $this->normalizeStatus((string)($payload['status'] ?? 'active'));
        $initialBalance = (float)($payload['initial_balance'] ?? 0);

        if ($name === '') {
            api_json_response(false, 'Nome da conta obrigatorio.', [], ['Informe o nome da conta.'], 422);
        }

        if (!in_array($type, ['PF', 'PJ'], true)) {
            api_json_response(false, 'Tipo de conta invalido.', [], ['Use PF ou PJ.'], 422);
        }

        return [
            'name' => $name,
            'type' => $type,
            'institution' => $institution,
            'initial_balance' => $initialBalance,
            'status' => $status,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'inactive' ? 'inactive' : 'active';
    }
}
