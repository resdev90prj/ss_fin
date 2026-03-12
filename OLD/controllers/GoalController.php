<?php
require_once __DIR__ . '/../models/Goal.php';

class GoalController
{
    public function index(): void
    {
        $goals = (new Goal())->allByUser(current_user_id());
        view('goals/index', ['title' => 'Metas Financeiras', 'goals' => $goals]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=goals');
        }

        $data = [
            'user_id' => current_user_id(),
            'title' => trim($_POST['title'] ?? ''),
            'target_amount' => (float)($_POST['target_amount'] ?? 0),
            'current_amount' => (float)($_POST['current_amount'] ?? 0),
            'target_date' => $_POST['target_date'] ?: null,
            'status' => $_POST['status'] ?? 'active'
        ];

        if ($data['title'] === '' || $data['target_amount'] <= 0) {
            flash('error', 'Título e valor alvo são obrigatórios.');
            redirect('index.php?route=goals');
        }

        (new Goal())->create($data);
        flash('success', 'Meta criada.');
        redirect('index.php?route=goals');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=goals');
        }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'target_amount' => (float)($_POST['target_amount'] ?? 0),
            'current_amount' => (float)($_POST['current_amount'] ?? 0),
            'target_date' => $_POST['target_date'] ?: null,
            'status' => $_POST['status'] ?? 'active'
        ];

        (new Goal())->update($id, current_user_id(), $data);
        flash('success', 'Meta atualizada.');
        redirect('index.php?route=goals');
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=goals');
        }
        $id = (int)($_POST['id'] ?? 0);
        (new Goal())->delete($id, current_user_id());
        flash('success', 'Meta removida.');
        redirect('index.php?route=goals');
    }
}
