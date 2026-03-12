<?php
require_once __DIR__ . '/../models/Budget.php';
require_once __DIR__ . '/../models/Category.php';

class BudgetController
{
    public function index(): void
    {
        $userId = current_user_id();
        view('budgets/index', [
            'title' => 'Orçamento Mensal',
            'budgets' => (new Budget())->allByUser($userId),
            'categories' => (new Category())->activeByUser($userId)
        ]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=budgets');
        }

        $userId = current_user_id();
        $data = [
            'user_id' => $userId,
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'month_ref' => $_POST['month_ref'] ?? date('Y-m'),
            'amount_limit' => (float)($_POST['amount_limit'] ?? 0),
        ];

        if (!(new Category())->find((int)$data['category_id'], $userId)) {
            flash('error', 'Categoria inválida para o usuário logado.');
            redirect('index.php?route=budgets');
        }

        (new Budget())->upsert($data);
        flash('success', 'Orçamento salvo.');
        redirect('index.php?route=budgets');
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=budgets');
        }

        $id = (int)($_POST['id'] ?? 0);
        (new Budget())->delete($id, current_user_id());
        flash('success', 'Orçamento removido.');
        redirect('index.php?route=budgets');
    }
}
