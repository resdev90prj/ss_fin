<?php

$config = [
    'app_name' => 'SaaS IA Finan',
    'base_path' => dirname(__DIR__),
    'base_url' => rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'),
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u477028682_db_ePLopOwd',
        'user' => getenv('DB_USER') ?: 'u477028682_usr_ePLopOwd',
        'pass' => getenv('DB_PASS') ?: 'TCFDDl2p9u>S',
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
