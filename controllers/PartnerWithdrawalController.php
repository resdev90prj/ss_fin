<?php
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Box.php';
require_once __DIR__ . '/../models/Category.php';

class PartnerWithdrawalController
{
    public function index(): void
    {
        $userId = current_user_id();
        $filters = [
            'type' => 'partner_withdrawal',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
            'category_id' => '',
            'account_id' => '',
        ];

        view('withdrawals/index', [
            'title' => 'Retiradas do Sócio',
            'transactions' => (new Transaction())->listByUser($userId, $filters),
            'accounts' => (new Account())->activeByUser($userId),
            'boxes' => (new Box())->activeByUser($userId),
            'categories' => (new Category())->activeByUser($userId)
        ]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=withdrawals');
        }

        $userId = current_user_id();
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        if ($description === '' || $amount <= 0) {
            flash('error', 'Descrição e valor são obrigatórios.');
            redirect('index.php?route=withdrawals');
        }

        $mode = $_POST['mode'] ?? 'transicao';
        if (!in_array($mode, ['transitorio', 'transicao', 'ideal'], true)) {
            $mode = 'transicao';
        }

        $accountId = (int)($_POST['account_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $boxId = !empty($_POST['box_id']) ? (int)$_POST['box_id'] : null;

        if ($accountId <= 0 || !(new Account())->find($accountId, $userId)) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=withdrawals');
        }

        if ($categoryId <= 0 || !(new Category())->find($categoryId, $userId)) {
            flash('error', 'Categoria inválida para o usuário logado.');
            redirect('index.php?route=withdrawals');
        }

        if ($boxId !== null && !(new Box())->find($boxId, $userId)) {
            flash('error', 'Caixa inválido para o usuário logado.');
            redirect('index.php?route=withdrawals');
        }

        (new Transaction())->create([
            'user_id' => $userId,
            'account_id' => $accountId,
            'box_id' => $boxId,
            'category_id' => $categoryId,
            'type' => 'partner_withdrawal',
            'mode' => $mode,
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
            'payment_method' => trim($_POST['payment_method'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'source' => 'manual'
        ]);

        flash('success', 'Retirada registrada.');
        redirect('index.php?route=withdrawals');
    }
}
