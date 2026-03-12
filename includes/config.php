<?php

$config = [
    'app_name' => 'SaaS IA Finan',
    'base_path' => dirname(__DIR__),
    'base_url' => rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'),
    'debug' => [
        // Debug geral desligado por padrao. Pode ser habilitado por variavel de ambiente ou config.custom.php.
        'enabled' => getenv('APP_DEBUG') === '1',
        // Exibe erros na tela apenas quando explicitamente habilitado.
        'display_errors' => getenv('APP_DEBUG_DISPLAY') === '1',
        // Caminho opcional para arquivo de log de erros do app.
        'log_file' => getenv('APP_DEBUG_LOG') ?: null,
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u477028682_rbthmy',
        'user' => getenv('DB_USER') ?: 'u477028682_rbthmy',
        'pass' => getenv('DB_PASS') ?: 'ji=_Q2ojgq',
        'charset' => 'utf8mb4'
    ],
    'notifications' => [
        'email' => [
            'enabled' => getenv('NOTIF_EMAIL_ENABLED') !== '0',
            'from_email' => getenv('NOTIF_EMAIL_FROM') ?: 'no-reply@localhost',
            'from_name' => getenv('NOTIF_EMAIL_FROM_NAME') ?: 'SaaS IA Finan',
            'subject_prefix' => getenv('NOTIF_EMAIL_SUBJECT_PREFIX') ?: '[SaaS IA Finan]',
        ],
        'whatsapp' => [
            // Estrutura pronta para evolucao futura (Z-API, Evolution, Meta Cloud API).
            'enabled' => getenv('NOTIF_WHATSAPP_ENABLED') === '1',
            'provider' => getenv('NOTIF_WHATSAPP_PROVIDER') ?: 'not_configured',
        ],
        'rules' => [
            'many_pending_threshold' => (int)(getenv('NOTIF_RULE_PENDING_THRESHOLD') ?: 12),
            'low_execution_score_threshold' => (int)(getenv('NOTIF_RULE_LOW_SCORE_THRESHOLD') ?: 50),
        ],
        'dispatch' => [
            'max_users_per_run' => (int)(getenv('NOTIF_MAX_USERS_PER_RUN') ?: 100),
            'default_frequency' => getenv('NOTIF_DEFAULT_FREQUENCY') ?: 'daily',
            'default_hour' => getenv('NOTIF_DEFAULT_HOUR') ?: '08:00',
            'log_file' => getenv('NOTIF_LOG_FILE') ?: (dirname(__DIR__) . '/logs/alerts_dispatch.log'),
        ],
    ],
];

// Override opcional para separar ambiente local/producao sem mudar a arquitetura atual.
$customConfigPath = __DIR__ . '/config.custom.php';
if (is_file($customConfigPath)) {
    $customConfig = require $customConfigPath;
    if (is_array($customConfig)) {
        $config = array_replace_recursive($config, $customConfig);
    }
}

return $config;
