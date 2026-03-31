<?php
declare(strict_types=1);

require_once api_project_root() . '/models/User.php';
require_once api_project_root() . '/models/Category.php';

class ApiAuthController
{
    public function me(): void
    {
        $session = api_session_payload();
        $message = !empty($session['authenticated']) ? 'Sessao autenticada.' : 'Sessao nao autenticada.';

        api_json_response(true, $message, $session);
    }

    public function login(): void
    {
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            api_json_response(false, 'Credenciais invalidas.', [], ['Informe e-mail valido e senha.'], 422);
        }

        $user = (new User())->findByEmail($email, true);
        if (!$user || !password_verify($password, (string)$user['password'])) {
            api_json_response(false, 'E-mail ou senha incorretos.', [], ['Falha de autenticacao.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];
        clear_scope_user_id();

        (new Category())->ensureDefaultsForUser((int)$user['id']);

        api_json_response(true, 'Login realizado com sucesso.', api_session_payload());
    }

    public function logout(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        clear_scope_user_id();
        api_destroy_session();

        api_json_response(true, 'Sessao encerrada com sucesso.', api_session_payload());
    }
}

