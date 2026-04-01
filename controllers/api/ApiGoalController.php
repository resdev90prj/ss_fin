<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Goal.php';

class ApiGoalController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $items = (new Goal())->allByUser($userId);

        $summary = [
            'total' => count($items),
            'active_count' => 0,
            'completed_count' => 0,
            'paused_count' => 0,
            'target_total' => 0.0,
            'current_total' => 0.0,
        ];

        foreach ($items as &$item) {
            $targetAmount = (float)($item['target_amount'] ?? 0);
            $currentAmount = (float)($item['current_amount'] ?? 0);
            $status = (string)($item['status'] ?? 'active');

            $item['target_amount'] = $targetAmount;
            $item['current_amount'] = $currentAmount;
            $item['progress_percent'] = $targetAmount > 0
                ? min(100, round(($currentAmount / $targetAmount) * 100, 2))
                : 0.0;

            $summary['target_total'] += $targetAmount;
            $summary['current_total'] += $currentAmount;

            if ($status === 'completed') {
                $summary['completed_count']++;
            } elseif ($status === 'paused') {
                $summary['paused_count']++;
            } else {
                $summary['active_count']++;
            }
        }
        unset($item);

        api_json_response(true, 'Metas carregadas com sucesso.', [
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

        (new Goal())->create($data);

        api_json_response(true, 'Meta criada com sucesso.');
    }

    public function update(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Goal())->find($id, $userId)) {
            api_json_response(false, 'Meta nao encontrada.', [], ['Meta invalida para o usuario atual.'], 404);
        }

        $data = $this->validatedPayload($payload);
        (new Goal())->update($id, $userId, $data);

        api_json_response(true, 'Meta atualizada com sucesso.');
    }

    public function delete(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Goal())->find($id, $userId)) {
            api_json_response(false, 'Meta nao encontrada.', [], ['Meta invalida para o usuario atual.'], 404);
        }

        (new Goal())->delete($id, $userId);

        api_json_response(true, 'Meta removida com sucesso.');
    }

    private function validatedPayload(array $payload): array
    {
        $title = trim((string)($payload['title'] ?? ''));
        $targetAmount = (float)($payload['target_amount'] ?? 0);
        $currentAmount = (float)($payload['current_amount'] ?? 0);
        $targetDate = trim((string)($payload['target_date'] ?? ''));
        $status = $this->normalizeStatus((string)($payload['status'] ?? 'active'));

        if ($title === '' || $targetAmount <= 0) {
            api_json_response(false, 'Titulo e valor alvo sao obrigatorios.', [], ['Informe titulo e um valor alvo maior que zero.'], 422);
        }

        if ($targetDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            api_json_response(false, 'Data alvo invalida.', [], ['Use o formato YYYY-MM-DD.'], 422);
        }

        return [
            'title' => $title,
            'target_amount' => $targetAmount,
            'current_amount' => max(0, $currentAmount),
            'target_date' => $targetDate !== '' ? $targetDate : null,
            'status' => $status,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'completed', 'paused'], true)
            ? $status
            : 'active';
    }
}
