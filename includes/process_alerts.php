<?php
require_once __DIR__ . '/AlertCenterService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script deve ser executado via CLI.\n";
    exit(1);
}

$onlyUserId = null;
if (!empty($argv[1]) && ctype_digit((string)$argv[1])) {
    $candidate = (int)$argv[1];
    $onlyUserId = $candidate > 0 ? $candidate : null;
}

try {
    $summary = (new AlertCenterService())->dispatchAll($onlyUserId, 'cli');

    echo "Alert Center Summary\n";
    echo "Trigger: " . (string)$summary['trigger'] . "\n";
    echo "Started: " . (string)$summary['started_at'] . "\n";
    echo "Finished: " . (string)$summary['finished_at'] . "\n";
    echo "Users scanned: " . (int)$summary['users_scanned'] . "\n";
    echo "Users processed: " . (int)$summary['users_processed'] . "\n";
    echo "Users with alerts: " . (int)$summary['users_with_alerts'] . "\n";
    echo "Dispatch sent: " . (int)$summary['dispatch_sent'] . "\n";
    echo "Dispatch failed: " . (int)$summary['dispatch_failed'] . "\n";
    echo "Dispatch skipped: " . (int)$summary['dispatch_skipped'] . "\n";

    if (!empty($summary['results'])) {
        echo "Per user:\n";
        foreach ($summary['results'] as $result) {
            $line = sprintf(
                "- user_id=%d alerts=%d",
                (int)($result['user_id'] ?? 0),
                (int)($result['alert_count'] ?? 0)
            );
            echo $line . "\n";
        }
    }

    exit((int)$summary['dispatch_failed'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO ao executar Central de Alertas: " . $e->getMessage() . "\n");
    exit(1);
}

