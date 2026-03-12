<?php
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Account.php';

class ReportController
{
    public function index(): void
    {
        $userId = current_user_id();
        $filters = [
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
            'type' => $_GET['type'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'account_id' => $_GET['account_id'] ?? '',
        ];

        $transactions = (new Transaction())->listByUser($userId, $filters);

        view('reports/index', [
            'title' => 'Relatórios',
            'transactions' => $transactions,
            'filters' => $filters,
            'categories' => (new Category())->activeByUser($userId),
            'accounts' => (new Account())->activeByUser($userId),
        ]);
    }
}
