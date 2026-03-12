<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';

class UserController
{
    public function index(): void
    {
        require_admin();

        view('users/index', [
            'title' => 'Usuários',
            'users' => (new User())->all(),
            'scopedUserId' => scoped_user_id(),
            'loggedUserId' => logged_user_id(),
        ]);
    }

    public function store(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'user');
        $status = $this->normalizeStatus($_POST['status'] ?? '1');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nome e e-mail válidos são obrigatórios.');
            redirect('index.php?route=users');
        }

        if (strlen($password) < 6) {
            flash('error', 'A senha deve ter ao menos 6 caracteres.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        if ($userModel->emailExists($email)) {
            flash('error', 'Já existe usuário com este e-mail.');
            redirect('index.php?route=users');
        }

        $newUserId = $userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
        ]);

        (new Category())->ensureDefaultsForUser($newUserId);

        flash('success', 'Usuário criado com sucesso.');
        redirect('index.php?route=users');
    }

    public function update(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Usuário inválido.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            flash('error', 'Usuário não encontrado.');
            redirect('index.php?route=users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'user');
        $status = $this->normalizeStatus($_POST['status'] ?? '1');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nome e e-mail válidos são obrigatórios.');
            redirect('index.php?route=users');
        }

        if ($userModel->emailExists($email, $id)) {
            flash('error', 'Já existe usuário com este e-mail.');
            redirect('index.php?route=users');
        }

        $isSelf = $id === (int)(logged_user_id() ?? 0);
        if ($isSelf && $status === 0) {
            flash('error', 'Não é permitido desativar o próprio usuário.');
            redirect('index.php?route=users');
        }

        $userModel->updateByAdmin($id, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
        ]);

        if ($isSelf) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;
            if ($role !== 'admin') {
                clear_scope_user_id();
            }
        }

        flash('success', 'Usuário atualizado com sucesso.');
        redirect('index.php?route=users');
    }

    public function toggleStatus(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Usuário inválido.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            flash('error', 'Usuário não encontrado.');
            redirect('index.php?route=users');
        }

        if ($id === (int)(logged_user_id() ?? 0) && (int)$target['status'] === 1) {
            flash('error', 'Não é permitido desativar o próprio usuário.');
            redirect('index.php?route=users');
        }

        $nextStatus = (int)$target['status'] === 1 ? 0 : 1;
        $userModel->setStatus($id, $nextStatus);

        if ($nextStatus === 0 && (int)(scoped_user_id() ?? 0) === $id) {
            clear_scope_user_id();
        }

        flash('success', $nextStatus === 1 ? 'Usuário ativado.' : 'Usuário desativado.');
        redirect('index.php?route=users');
    }

    public function resetPassword(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($id <= 0 || strlen($newPassword) < 6) {
            flash('error', 'Senha inválida. Use ao menos 6 caracteres.');
            redirect('index.php?route=users');
        }

        $target = (new User())->findById($id);
        if (!$target) {
            flash('error', 'Usuário não encontrado.');
            redirect('index.php?route=users');
        }

        (new User())->resetPassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        flash('success', 'Senha redefinida com sucesso.');
        redirect('index.php?route=users');
    }

    public function scope(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=users');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            flash('error', 'Usuário inválido para escopo.');
            redirect('index.php?route=users');
        }

        $target = (new User())->findById($userId);
        if (!$target) {
            flash('error', 'Usuário não encontrado para escopo.');
            redirect('index.php?route=users');
        }

        set_scope_user_id($userId);
        flash('success', 'Escopo de visualização alterado para: ' . (string)$target['name']);
        redirect('index.php?route=dashboard');
    }

    public function clearScope(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=dashboard');
        }

        clear_scope_user_id();
        flash('success', 'Escopo de visualização retornou para seu usuário administrador.');
        redirect('index.php?route=dashboard');
    }

    private function normalizeRole(string $role): string
    {
        return $role === 'admin' ? 'admin' : 'user';
    }

    private function normalizeStatus(string $status): int
    {
        return $status === '0' ? 0 : 1;
    }
}