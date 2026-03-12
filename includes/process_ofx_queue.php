<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/OfxQueueProcessor.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script deve ser executado via CLI.\n";
    exit(1);
}

$userId = 0;

if (!empty($argv[1]) && ctype_digit((string)$argv[1])) {
    $userId = (int)$argv[1];
}

if ($userId <= 0) {
    $envUserId = getenv('OFX_QUEUE_USER_ID');
    if (is_string($envUserId) && ctype_digit($envUserId)) {
        $userId = (int)$envUserId;
    }
}

if ($userId <= 0) {
    $admin = (new User())->findByEmail('admin@local.test');
    $userId = isset($admin['id']) ? (int)$admin['id'] : 0;
}

if ($userId <= 0) {
    fwrite(STDERR, "ERRO: não foi possível definir o usuário da fila OFX.\n");
    exit(1);
}

try {
    $processor = new OfxQueueProcessor($userId, dirname(__DIR__));
    $summary = $processor->processQueue('cli');

    echo "OFX Queue Summary\n";
    echo "User ID: " . $summary['user_id'] . "\n";
    echo "Started: " . $summary['started_at'] . "\n";
    echo "Finished: " . $summary['finished_at'] . "\n";
    echo "Files scanned: " . $summary['files_scanned'] . "\n";
    echo "Files processed: " . $summary['files_processed'] . "\n";
    echo "Files duplicate: " . $summary['files_skipped_duplicate_file'] . "\n";
    echo "Files failed: " . $summary['files_failed'] . "\n";
    echo "Transactions found: " . $summary['transactions_found'] . "\n";
    echo "Transactions created: " . $summary['transactions_created'] . "\n";
    echo "Classified high: " . ($summary['transactions_classified_high'] ?? 0) . "\n";
    echo "Classified medium: " . ($summary['transactions_classified_medium'] ?? 0) . "\n";
    echo "Fallback used: " . ($summary['transactions_fallback_used'] ?? 0) . "\n";
    echo "Transactions duplicate ignored: " . $summary['transactions_ignored_duplicate'] . "\n";

    if (!empty($summary['failed_files'])) {
        echo "Failed files:\n";
        foreach ($summary['failed_files'] as $failed) {
            $file = (string)($failed['file'] ?? '(unknown)');
            $message = (string)($failed['message'] ?? 'Erro não informado');
            echo "- {$file}: {$message}\n";
        }
    }

    exit($summary['files_failed'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO ao executar fila OFX: " . $e->getMessage() . "\n");
    exit(1);
}
