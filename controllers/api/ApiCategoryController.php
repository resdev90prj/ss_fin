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
}

