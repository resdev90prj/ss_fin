<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Category.php';

class ApiCategoryController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $items = (new Category())->allByUser($userId);

        $summary = [
            'total' => count($items),
            'active_count' => 0,
            'inactive_count' => 0,
            'default_count' => 0,
            'custom_count' => 0,
            'income_count' => 0,
            'expense_count' => 0,
            'both_count' => 0,
        ];

        foreach ($items as &$item) {
            $status = (string)($item['status'] ?? 'inactive');
            $type = (string)($item['type'] ?? 'both');
            $isDefault = (int)($item['is_default'] ?? 0) === 1;

            $summary[$status === 'active' ? 'active_count' : 'inactive_count']++;
            $summary[$isDefault ? 'default_count' : 'custom_count']++;

            if ($type === 'income') {
                $summary['income_count']++;
            } elseif ($type === 'expense') {
                $summary['expense_count']++;
            } else {
                $summary['both_count']++;
            }

            $item['is_default'] = $isDefault;
        }
        unset($item);

        api_json_response(true, 'Categorias carregadas com sucesso.', [
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

        (new Category())->create($data);

        api_json_response(true, 'Categoria criada com sucesso.');
    }

    public function update(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        $category = (new Category())->find($id, $userId);
        if (!$category) {
            api_json_response(false, 'Categoria nao encontrada.', [], ['Categoria invalida para o usuario atual.'], 404);
        }

        $data = $this->validatedPayload($payload);
        (new Category())->update($id, $userId, $data);

        api_json_response(true, 'Categoria atualizada com sucesso.');
    }

    public function delete(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        $category = (new Category())->find($id, $userId);
        if (!$category) {
            api_json_response(false, 'Categoria nao encontrada.', [], ['Categoria invalida para o usuario atual.'], 404);
        }

        if ((int)($category['is_default'] ?? 0) === 1) {
            api_json_response(false, 'Categoria padrao protegida.', [], ['Categorias padrao nao podem ser excluidas.'], 422);
        }

        (new Category())->delete($id, $userId);

        api_json_response(true, 'Categoria removida com sucesso.');
    }

    private function validatedPayload(array $payload): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        $type = strtolower(trim((string)($payload['type'] ?? 'both')));
        $status = $this->normalizeStatus((string)($payload['status'] ?? 'active'));

        if ($name === '') {
            api_json_response(false, 'Nome da categoria obrigatorio.', [], ['Informe o nome da categoria.'], 422);
        }

        if (!in_array($type, ['income', 'expense', 'both'], true)) {
            api_json_response(false, 'Tipo de categoria invalido.', [], ['Use income, expense ou both.'], 422);
        }

        return [
            'name' => $name,
            'type' => $type,
            'status' => $status,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'inactive' ? 'inactive' : 'active';
    }
}
