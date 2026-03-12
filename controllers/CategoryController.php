<?php
require_once __DIR__ . '/../models/Category.php';

class CategoryController
{
    public function index(): void
    {
        $categories = (new Category())->allByUser(current_user_id());
        view('categories/index', ['title' => 'Categorias', 'categories' => $categories]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=categories');
        }

        $data = [
            'user_id' => current_user_id(),
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'both',
            'status' => $_POST['status'] ?? 'active',
        ];

        if ($data['name'] === '') {
            flash('error', 'Nome da categoria é obrigatório.');
            redirect('index.php?route=categories');
        }

        (new Category())->create($data);
        flash('success', 'Categoria criada.');
        redirect('index.php?route=categories');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=categories');
        }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'both',
            'status' => $_POST['status'] ?? 'active',
        ];
        (new Category())->update($id, current_user_id(), $data);
        flash('success', 'Categoria atualizada.');
        redirect('index.php?route=categories');
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=categories');
        }
        $id = (int)($_POST['id'] ?? 0);
        (new Category())->delete($id, current_user_id());
        flash('success', 'Categoria removida.');
        redirect('index.php?route=categories');
    }
}
