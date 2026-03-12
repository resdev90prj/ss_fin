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
