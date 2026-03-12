<?php
// Copie este arquivo para includes/config.custom.php e ajuste os valores do ambiente de producao.
return [
    'app_name' => 'SaaS IA Finan',
    // Exemplo: https://seudominio.com ou https://seudominio.com/subdiretorio
    'base_url' => 'https://SEU_DOMINIO_AQUI',
    'debug' => [
        // Habilite temporariamente para diagnostico em producao.
        'enabled' => false,
        // Mantenha false em producao para nao expor erros em tela.
        'display_errors' => false,
        // Exemplo: __DIR__ . '/../logs/app_debug.log'
        'log_file' => null,
    ],
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'NOME_BANCO_HOSTINGER',
        'user' => 'USUARIO_BANCO_HOSTINGER',
        'pass' => 'SENHA_BANCO_HOSTINGER',
        'charset' => 'utf8mb4',
    ],
];
