<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Account.php';
require_once api_project_root() . '/models/Category.php';

class ApiTransactionController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $prioritizeOthers = (string)($_GET['prioritize_others'] ?? '') === '1';
        $filters = [
            'from' => trim((string)($_GET['from'] ?? '')),
            'to' => trim((string)($_GET['to'] ?? '')),
            'type' => trim((string)($_GET['type'] ?? '')),
            'category_id' => trim((string)($_GET['category_id'] ?? '')),
            'account_id' => trim((string)($_GET['account_id'] ?? '')),
        ];

        $transactionModel = new Transaction();
        $items = $transactionModel->listByUser($userId, $filters, $prioritizeOthers);
        $othersPendingCount = $transactionModel->countOthersPending($userId, $filters);

        $summary = [
            'total' => count($items),
            'income_total' => 0.0,
            'expense_total' => 0.0,
            'withdrawal_total' => 0.0,
            'transfer_total' => 0.0,
            'others_pending_count' => $othersPendingCount,
        ];

        foreach ($items as &$item) {
            $amount = (float)($item['amount'] ?? 0);
            $type = (string)($item['type'] ?? 'expense');
            $categoryName = mb_strtolower((string)($item['category_name'] ?? ''), 'UTF-8');

            if ($type === 'income') {
                $summary['income_total'] += $amount;
            } elseif ($type === 'partner_withdrawal') {
                $summary['withdrawal_total'] += $amount;
            } elseif ($type === 'transfer') {
                $summary['transfer_total'] += $amount;
            } else {
                $summary['expense_total'] += $amount;
            }

            $item['amount'] = $amount;
            $item['is_others_category'] = $categoryName === mb_strtolower('Outros gastos', 'UTF-8');
        }
        unset($item);

        api_json_response(true, 'Transacoes carregadas com sucesso.', [
            'items' => $items,
            'filters' => $filters,
            'prioritize_others' => $prioritizeOthers,
            'summary' => $summary,
            'lookups' => [
                'accounts' => (new Account())->activeByUser($userId),
                'categories' => (new Category())->activeByUser($userId),
            ],
        ]);
    }
}

