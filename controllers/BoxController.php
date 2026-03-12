<?php
require_once __DIR__ . '/../models/Box.php';
require_once __DIR__ . '/../models/Account.php';

class BoxController
{
    public function index(): void
    {
        $userId = current_user_id();
        $boxModel = new Box();
        $accountModel = new Account();

        view('boxes/index', [
            'title' => 'Caixas Virtuais',
            'boxes' => $boxModel->allByUser($userId),
            'accounts' => $accountModel->activeByUser($userId)
        ]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=boxes');
        }

        $userId = current_user_id();
        $data = [
            'user_id' => $userId,
            'account_id' => !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null,
            'name' => trim($_POST['name'] ?? ''),
            'objective' => trim($_POST['objective'] ?? ''),
            'balance' => (float)($_POST['balance'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ];

        if ($data['name'] === '') {
            flash('error', 'Nome do caixa é obrigatório.');
            redirect('index.php?route=boxes');
        }

        if ($data['account_id'] !== null && !(new Account())->find((int)$data['account_id'], $userId)) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=boxes');
        }

        (new Box())->create($data);
        flash('success', 'Caixa criado com sucesso.');
        redirect('index.php?route=boxes');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=boxes');
        }

        $userId = current_user_id();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'account_id' => !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null,
            'name' => trim($_POST['name'] ?? ''),
            'objective' => trim($_POST['objective'] ?? ''),
            'balance' => (float)($_POST['balance'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ];

        if ($data['account_id'] !== null && !(new Account())->find((int)$data['account_id'], $userId)) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=boxes');
        }

        (new Box())->update($id, $userId, $data);
        flash('success', 'Caixa atualizado.');
        redirect('index.php?route=boxes');
    }
}
