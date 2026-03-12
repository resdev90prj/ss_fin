<?php
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function logged_user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function current_user_id(): ?int
{
    $loggedUserId = logged_user_id();
    if ($loggedUserId === null) {
        return null;
    }

    if (!is_admin()) {
        return $loggedUserId;
    }

    $scopeUserId = $_SESSION['scope_user_id'] ?? null;
    if (!is_numeric($scopeUserId)) {
        return $loggedUserId;
    }

    $scopeUserId = (int)$scopeUserId;
    return $scopeUserId > 0 ? $scopeUserId : $loggedUserId;
}

function scoped_user_id(): ?int
{
    if (!is_admin()) {
        return null;
    }

    $scopeUserId = $_SESSION['scope_user_id'] ?? null;
    if (!is_numeric($scopeUserId)) {
        return null;
    }

    $scopeUserId = (int)$scopeUserId;
    return $scopeUserId > 0 ? $scopeUserId : null;
}

function set_scope_user_id(int $userId): void
{
    if ($userId > 0 && is_admin()) {
        $_SESSION['scope_user_id'] = $userId;
    }
}

function clear_scope_user_id(): void
{
    unset($_SESSION['scope_user_id']);
}

function is_logged_in(): bool
{
    return logged_user_id() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Faça login para continuar.');
        redirect('index.php?route=login');
    }

    require_once __DIR__ . '/../models/User.php';
    $user = (new User())->findById((int)logged_user_id());
    if (!$user || (int)$user['status'] !== 1) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        session_start();
        flash('error', 'Sua sessão expirou ou seu usuário está inativo.');
        redirect('index.php?route=login');
    }

    // Mantém sessão sincronizada quando admin altera nome/e-mail/perfil do usuário logado.
    $_SESSION['user']['name'] = (string)$user['name'];
    $_SESSION['user']['email'] = (string)$user['email'];
    $_SESSION['user']['role'] = (string)$user['role'];
}

function require_admin(): void
{
    if (!is_logged_in()) {
        flash('error', 'Faça login para continuar.');
        redirect('index.php?route=login');
    }

    if (!is_admin()) {
        flash('error', 'Acesso restrito ao administrador.');
        redirect('index.php?route=dashboard');
    }
}
