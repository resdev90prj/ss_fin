<?php
declare(strict_types=1);

function api_bootstrap(): void
{
    $config = api_config();
    $debug = is_array($config['debug'] ?? null) ? $config['debug'] : [];

    ini_set('display_errors', !empty($debug['display_errors']) ? '1' : '0');
    ini_set('log_errors', '1');

    $logFile = trim((string)($debug['log_file'] ?? ''));
    if ($logFile !== '') {
        ini_set('error_log', $logFile);
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }

    set_exception_handler(static function (Throwable $throwable): void {
        api_handle_exception($throwable);
    });
}

function api_project_root(): string
{
    static $root = null;
    if ($root === null) {
        $root = dirname(__DIR__, 2);
    }

    return $root;
}

function api_config(): array
{
    static $config = null;
    if ($config === null) {
        $loaded = require api_project_root() . '/includes/config.php';
        $config = is_array($loaded) ? $loaded : [];
    }

    return $config;
}

function api_request_path(): string
{
    $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/api'), PHP_URL_PATH);
    $uriPath = is_string($uriPath) && $uriPath !== '' ? $uriPath : '/';

    $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')));
    if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uriPath, $scriptDir)) {
        $uriPath = substr($uriPath, strlen($scriptDir));
    }

    $normalized = '/' . trim($uriPath, '/');
    if ($normalized === '/')
    {
        return '/';
    }

    return rtrim($normalized, '/');
}

function api_request_data(): array
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        return $_GET;
    }

    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            api_json_response(false, 'JSON invalido.', [], ['Corpo JSON invalido.'], 400);
        }

        return $decoded;
    }

    return $_POST;
}

function api_json_response(bool $success, string $message, array $data = [], array $errors = [], int $status = 200): void
{
    http_response_code($status);

    $payload = [
        'success' => $success,
        'message' => $message,
        'data' => $data === [] ? (object)[] : $data,
        'errors' => array_values($errors),
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_not_found(): void
{
    api_json_response(false, 'Endpoint nao encontrado.', [], ['Rota da API nao mapeada.'], 404);
}

function api_handle_exception(Throwable $throwable): void
{
    error_log(sprintf(
        '[api] %s in %s:%d',
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine()
    ));

    $errors = [];
    $config = api_config();
    $debug = is_array($config['debug'] ?? null) ? $config['debug'] : [];
    if (!empty($debug['enabled'])) {
        $errors[] = $throwable->getMessage();
    }

    api_json_response(false, 'Falha interna ao processar a API.', [], $errors, 500);
}

function api_destroy_session(): void
{
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
}

function api_sync_authenticated_session(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    require_once api_project_root() . '/models/User.php';

    $loggedUserId = logged_user_id();
    if ($loggedUserId === null || $loggedUserId <= 0) {
        api_destroy_session();
        return null;
    }

    $user = (new User())->findById((int)$loggedUserId);
    if (!$user || (int)($user['status'] ?? 0) !== 1) {
        api_destroy_session();
        return null;
    }

    $_SESSION['user']['name'] = (string)$user['name'];
    $_SESSION['user']['email'] = (string)$user['email'];
    $_SESSION['user']['role'] = (string)$user['role'];

    return $user;
}

function api_require_login(): array
{
    $user = api_sync_authenticated_session();
    if (!$user) {
        api_json_response(false, 'Autenticacao obrigatoria.', [], ['Sessao ausente ou invalida.'], 401);
    }

    return $user;
}

function api_verify_csrf_or_fail(?string $token): void
{
    if (!verify_csrf($token)) {
        api_json_response(false, 'Token CSRF invalido.', [], ['Requisicao rejeitada por CSRF.'], 419);
    }
}

function api_session_payload(): array
{
    $user = api_sync_authenticated_session();
    $config = api_config();

    return [
        'authenticated' => $user !== null,
        'csrf_token' => csrf_token(),
        'user' => $user ? [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ] : null,
        'scope' => [
            'logged_user_id' => logged_user_id(),
            'current_user_id' => current_user_id(),
            'scoped_user_id' => scoped_user_id(),
            'is_admin' => is_admin(),
        ],
        'release' => [
            'app_name' => (string)($config['app_name'] ?? 'SaaS IA Finan'),
            'legacy_base' => '/',
            'react_base' => '/newrelease',
            'api_base' => '/api',
        ],
    ];
}

function api_current_effective_user_id(): int
{
    $currentUserId = current_user_id();
    if ($currentUserId === null || $currentUserId <= 0) {
        api_json_response(false, 'Usuario efetivo invalido.', [], ['Nao foi possivel resolver o user_id atual.'], 422);
    }

    return (int)$currentUserId;
}

function api_normalize_month(?string $input): string
{
    $month = trim((string)$input);
    if ($month !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        return $month;
    }

    return date('Y-m');
}

function api_format_month_label(string $month): string
{
    $labels = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Marco',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro',
    ];

    $year = substr($month, 0, 4);
    $monthNumber = substr($month, 5, 2);

    return ($labels[$monthNumber] ?? $monthNumber) . '/' . $year;
}

