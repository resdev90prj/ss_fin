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
}

