<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Category.php';
require_once api_project_root() . '/models/Account.php';

class ApiReportController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $filters = [
            'from' => trim((string)($_GET['from'] ?? date('Y-m-01'))),
            'to' => trim((string)($_GET['to'] ?? date('Y-m-t'))),
            'type' => trim((string)($_GET['type'] ?? '')),
            'category_id' => trim((string)($_GET['category_id'] ?? '')),
            'account_id' => trim((string)($_GET['account_id'] ?? '')),
        ];

        $items = (new Transaction())->listByUser($userId, $filters);
        $summary = [
            'total' => count($items),
            'income_total' => 0.0,
            'expense_total' => 0.0,
            'withdrawal_total' => 0.0,
            'transfer_total' => 0.0,
            'net_total' => 0.0,
        ];

        foreach ($items as &$item) {
            $amount = (float)($item['amount'] ?? 0);
            $type = (string)($item['type'] ?? 'expense');
            $item['amount'] = $amount;

            if ($type === 'income') {
                $summary['income_total'] += $amount;
                $summary['net_total'] += $amount;
            } elseif ($type === 'partner_withdrawal') {
                $summary['withdrawal_total'] += $amount;
                $summary['net_total'] -= $amount;
            } elseif ($type === 'transfer') {
                $summary['transfer_total'] += $amount;
            } else {
                $summary['expense_total'] += $amount;
                $summary['net_total'] -= $amount;
            }
        }
        unset($item);

        api_json_response(true, 'Relatorio carregado com sucesso.', [
            'items' => $items,
            'filters' => $filters,
            'summary' => $summary,
            'lookups' => [
                'accounts' => (new Account())->activeByUser($userId),
                'categories' => (new Category())->activeByUser($userId),
            ],
        ]);
    }
}
