<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';

class AuthController
{
    public function loginForm(): void
    {
        view('auth/login', ['title' => 'Login']);
    }

    public function login(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        remember_old(['email' => $email]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            flash('error', 'Credenciais inválidas.');
            redirect('index.php?route=login');
        }

        $model = new User();
        $user = $model->findByEmail($email, true);

        if (!$user || !password_verify($password, (string)$user['password'])) {
            flash('error', 'E-mail ou senha incorretos.');
            redirect('index.php?route=login');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        clear_scope_user_id();

        // Garante categorias base por usuário para manter fluxo multiusuário consistente.
        (new Category())->ensureDefaultsForUser((int)$user['id']);

        clear_old();
        redirect('index.php?route=dashboard');
    }

    public function logout(): void
    {
        clear_scope_user_id();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);

        flash('success', 'Sessão encerrada com sucesso.');
        redirect('index.php?route=login');
    }
}