<?php
require_once __DIR__ . '/../models/Account.php';

class AccountController
{
    public function index(): void
    {
        $model = new Account();
        $accounts = $model->allByUser(current_user_id());
        view('accounts/index', ['title' => 'Contas Financeiras', 'accounts' => $accounts]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=accounts');
        }

        $data = [
            'user_id' => current_user_id(),
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'PF',
            'institution' => trim($_POST['institution'] ?? ''),
            'initial_balance' => (float)($_POST['initial_balance'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ];

        if ($data['name'] === '' || !in_array($data['type'], ['PF', 'PJ'], true)) {
            flash('error', 'Dados de conta inválidos.');
            redirect('index.php?route=accounts');
        }

        (new Account())->create($data);
        flash('success', 'Conta criada com sucesso.');
        redirect('index.php?route=accounts');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=accounts');
        }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'PF',
            'institution' => trim($_POST['institution'] ?? ''),
            'initial_balance' => (float)($_POST['initial_balance'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ];

        (new Account())->update($id, current_user_id(), $data);
        flash('success', 'Conta atualizada.');
        redirect('index.php?route=accounts');
    }

    public function toggle(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=accounts');
        }

        $id = (int)($_POST['id'] ?? 0);
        (new Account())->toggleStatus($id, current_user_id());
        flash('success', 'Status da conta atualizado.');
        redirect('index.php?route=accounts');
    }
}
