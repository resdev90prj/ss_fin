<?php

require_once __DIR__ . '/OfxParser.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/CategoryAutoClassifier.php';

class OfxQueueProcessor
{
    private int $userId;
    private string $basePath;
    private string $importsDir;
    private string $pendingDir;
    private string $processedDir;
    private string $errorDir;
    private string $logsDir;
    private string $hashRegistryFile;
    private string $lastRunFile;

    private OfxParser $parser;
    private Account $accountModel;
    private Transaction $transactionModel;
    private CategoryAutoClassifier $classifier;

    private ?array $activeAccounts = null;

    public function __construct(int $userId, ?string $basePath = null)
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para processamento de fila OFX.');
        }

        $this->userId = $userId;
        $this->basePath = rtrim($basePath ?: dirname(__DIR__), DIRECTORY_SEPARATOR);
        $this->importsDir = $this->basePath . DIRECTORY_SEPARATOR . 'imports';
        $this->pendingDir = $this->importsDir . DIRECTORY_SEPARATOR . 'pending';
        $this->processedDir = $this->importsDir . DIRECTORY_SEPARATOR . 'processed';
        $this->errorDir = $this->importsDir . DIRECTORY_SEPARATOR . 'error';
        $this->logsDir = $this->importsDir . DIRECTORY_SEPARATOR . 'logs';
        $this->hashRegistryFile = $this->logsDir . DIRECTORY_SEPARATOR . 'processed_hashes.json';
        $this->lastRunFile = $this->logsDir . DIRECTORY_SEPARATOR . 'last_run.json';

        $this->parser = new OfxParser();
        $this->accountModel = new Account();
        $this->transactionModel = new Transaction();
        $this->classifier = new CategoryAutoClassifier();

        $this->ensureDirectories();
    }

    public function processQueue(string $trigger = 'web'): array
    {
        $summary = [
            'trigger' => $trigger,
            'user_id' => $this->userId,
            'started_at' => date('c'),
            'finished_at' => null,
            'files_scanned' => 0,
            'files_processed' => 0,
            'files_failed' => 0,
            'files_skipped_duplicate_file' => 0,
            'transactions_found' => 0,
            'transactions_created' => 0,
            'transactions_ignored_duplicate' => 0,
            'transactions_classified_high' => 0,
            'transactions_classified_medium' => 0,
            'transactions_fallback_used' => 0,
            'processed_files' => [],
            'failed_files' => [],
        ];

        $registry = $this->loadHashRegistry();

        try {
            $pendingFiles = $this->getPendingOfxFiles();
            $summary['files_scanned'] = count($pendingFiles);

            foreach ($pendingFiles as $filePath) {
                $fileName = basename($filePath);
                $fileHash = hash_file('sha256', $filePath) ?: '';

                if ($fileHash !== '' && isset($registry[$fileHash])) {
                    try {
                        $summary['files_skipped_duplicate_file']++;
                        $destination = $this->moveFileTo($filePath, $this->processedDir, 'duplicate');
                        $summary['processed_files'][] = [
                            'file' => $fileName,
                            'status' => 'duplicate_file',
                            'destination' => basename($destination),
                            'transactions_found' => 0,
                            'transactions_created' => 0,
                            'transactions_ignored_duplicate' => 0,
                        ];

                        $this->writeLog('FILE_DUPLICATE', $fileName, [
                            'message' => 'Hash já processado anteriormente.',
                            'destination' => basename($destination),
                        ]);
                    } catch (Throwable $e) {
                        $summary['files_failed']++;
                        $summary['failed_files'][] = [
                            'file' => $fileName,
                            'status' => 'error',
                            'destination' => '',
                            'message' => $e->getMessage(),
                        ];
                        $this->writeLog('FILE_ERROR', $fileName, [
                            'message' => 'Falha ao mover arquivo duplicado: ' . $e->getMessage(),
                        ]);
                    }
                    continue;
                }

                try {
                    $result = $this->processSingleFile($filePath, $fileName);
                    $summary['files_processed']++;
                    $summary['transactions_found'] += $result['transactions_found'];
                    $summary['transactions_created'] += $result['transactions_created'];
                    $summary['transactions_ignored_duplicate'] += $result['transactions_ignored_duplicate'];
                    $summary['transactions_classified_high'] += $result['transactions_classified_high'];
                    $summary['transactions_classified_medium'] += $result['transactions_classified_medium'];
                    $summary['transactions_fallback_used'] += $result['transactions_fallback_used'];
                    $summary['processed_files'][] = $result;

                    if ($fileHash !== '') {
                        $registry[$fileHash] = [
                            'file_name' => $fileName,
                            'processed_at' => date('c'),
                            'user_id' => $this->userId,
                            'transactions_found' => $result['transactions_found'],
                            'transactions_created' => $result['transactions_created'],
                            'transactions_ignored_duplicate' => $result['transactions_ignored_duplicate'],
                            'transactions_classified_high' => $result['transactions_classified_high'],
                            'transactions_classified_medium' => $result['transactions_classified_medium'],
                            'transactions_fallback_used' => $result['transactions_fallback_used'],
                        ];
                    }
                } catch (Throwable $e) {
                    $summary['files_failed']++;
                    $destination = $this->moveFileTo($filePath, $this->errorDir, 'error');
                    $failure = [
                        'file' => $fileName,
                        'status' => 'error',
                        'destination' => basename($destination),
                        'message' => $e->getMessage(),
                    ];
                    $summary['failed_files'][] = $failure;

                    $this->writeLog('FILE_ERROR', $fileName, [
                        'message' => $e->getMessage(),
                        'destination' => basename($destination),
                    ]);
                }
            }
        } catch (Throwable $e) {
            $summary['files_failed']++;
            $summary['failed_files'][] = [
                'file' => '(queue)',
                'status' => 'error',
                'destination' => '',
                'message' => $e->getMessage(),
            ];

            $this->writeLog('RUN_ERROR', '(queue)', [
                'message' => $e->getMessage(),
            ]);
        }

        $summary['finished_at'] = date('c');

        $this->saveHashRegistry($registry);
        $this->saveLastRunSummary($summary);
        $this->writeLog('RUN_SUMMARY', '(queue)', [
            'files_scanned' => $summary['files_scanned'],
            'files_processed' => $summary['files_processed'],
            'files_failed' => $summary['files_failed'],
            'files_skipped_duplicate_file' => $summary['files_skipped_duplicate_file'],
            'transactions_found' => $summary['transactions_found'],
            'transactions_created' => $summary['transactions_created'],
            'transactions_ignored_duplicate' => $summary['transactions_ignored_duplicate'],
            'transactions_classified_high' => $summary['transactions_classified_high'],
            'transactions_classified_medium' => $summary['transactions_classified_medium'],
            'transactions_fallback_used' => $summary['transactions_fallback_used'],
        ]);

        return $summary;
    }

    public function getDashboardData(int $limit = 8): array
    {
        return [
            'pending_files' => $this->listFiles($this->pendingDir, $limit),
            'processed_files' => $this->listFiles($this->processedDir, $limit),
            'error_files' => $this->listFiles($this->errorDir, $limit),
            'last_run' => $this->getLastRunSummary(),
            'recent_logs' => $this->readRecentLogLines(20),
            'recent_errors' => $this->readRecentErrorLines(12),
        ];
    }

    private function processSingleFile(string $filePath, string $fileName): array
    {
        $this->assertOfxFile($filePath);

        $parsed = $this->parser->parseFile($filePath);
        $rows = $parsed['transactions'] ?? [];
        $meta = $parsed['meta'] ?? [];
        $accountId = $this->resolveAccountId((string)($meta['account_id'] ?? ''), $fileName);

        $transactionsFound = 0;
        $transactionsCreated = 0;
        $transactionsIgnoredDuplicate = 0;
        $transactionsClassifiedHigh = 0;
        $transactionsClassifiedMedium = 0;
        $transactionsFallbackUsed = 0;

        foreach ($rows as $row) {
            $transactionsFound++;

            $amount = (float)($row['amount'] ?? 0);
            if ($amount == 0.0) {
                continue;
            }

            $type = $amount > 0 ? 'income' : 'expense';
            $transactionDate = $this->normalizeDate((string)($row['date'] ?? ''));
            if ($transactionDate === null) {
                continue;
            }

            $description = $this->sanitizeDescription((string)($row['description'] ?? 'Lancamento OFX'));
            $descriptionNormalized = $this->normalizeText($description);
            $fitId = trim((string)($row['fitid'] ?? ''));
            $absAmount = abs($amount);

            if ($this->isDuplicateTransaction($accountId, $transactionDate, $absAmount, $type, $descriptionNormalized, $fitId)) {
                $transactionsIgnoredDuplicate++;
                continue;
            }

            $classification = $this->resolveCategoryClassification($description, $type);
            $categoryId = (int)($classification['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            if (($classification['confidence'] ?? '') === 'high') {
                $transactionsClassifiedHigh++;
            } elseif (($classification['confidence'] ?? '') === 'medium') {
                $transactionsClassifiedMedium++;
            } else {
                $transactionsFallbackUsed++;
            }

            $notes = $this->buildOfxNotes(
                $fileName,
                $fitId,
                (string)($row['type'] ?? ''),
                $meta,
                $classification
            );

            $this->transactionModel->create([
                'user_id' => $this->userId,
                'account_id' => $accountId,
                'box_id' => null,
                'category_id' => $categoryId,
                'type' => $type,
                'mode' => 'transicao',
                'description' => $description,
                'amount' => $absAmount,
                'transaction_date' => $transactionDate,
                'payment_method' => 'importado_ofx_fila',
                'notes' => $notes,
                'source' => 'import_ofx',
            ]);

            $transactionsCreated++;
        }

        $destination = $this->moveFileTo($filePath, $this->processedDir, 'ok');
        $result = [
            'file' => $fileName,
            'status' => 'processed',
            'destination' => basename($destination),
            'transactions_found' => $transactionsFound,
            'transactions_created' => $transactionsCreated,
            'transactions_ignored_duplicate' => $transactionsIgnoredDuplicate,
            'transactions_classified_high' => $transactionsClassifiedHigh,
            'transactions_classified_medium' => $transactionsClassifiedMedium,
            'transactions_fallback_used' => $transactionsFallbackUsed,
        ];

        $this->writeLog('FILE_PROCESSED', $fileName, [
            'transactions_found' => $transactionsFound,
            'transactions_created' => $transactionsCreated,
            'transactions_ignored_duplicate' => $transactionsIgnoredDuplicate,
            'transactions_classified_high' => $transactionsClassifiedHigh,
            'transactions_classified_medium' => $transactionsClassifiedMedium,
            'transactions_fallback_used' => $transactionsFallbackUsed,
            'destination' => basename($destination),
        ]);

        return $result;
    }

    private function isDuplicateTransaction(
        int $accountId,
        string $transactionDate,
        float $amount,
        string $type,
        string $descriptionNormalized,
        string $fitId
    ): bool {
        if ($fitId !== '' && $this->transactionModel->existsByOfxFitId($this->userId, $accountId, $fitId)) {
            return true;
        }

        $candidates = $this->transactionModel->findPotentialDuplicates(
            $this->userId,
            $accountId,
            $transactionDate,
            $amount,
            $type
        );

        foreach ($candidates as $candidate) {
            $candidateNormalized = $this->normalizeText((string)($candidate['description'] ?? ''));
            if ($candidateNormalized === $descriptionNormalized) {
                return true;
            }
        }

        return false;
    }

    private function resolveAccountId(string $ofxAccountId, string $fileName): int
    {
        $accounts = $this->getActiveAccounts();
        if (empty($accounts)) {
            throw new RuntimeException('Nenhuma conta ativa encontrada para o usuário.');
        }

        if (preg_match('/account[_-]?(\d+)/i', $fileName, $m)) {
            $idFromFile = (int)$m[1];
            foreach ($accounts as $acc) {
                if ((int)$acc['id'] === $idFromFile) {
                    return $idFromFile;
                }
            }
        }

        $digits = preg_replace('/\D+/', '', $ofxAccountId);
        if ($digits !== '') {
            foreach ($accounts as $acc) {
                $candidate = (string)($acc['institution'] ?? '') . ' ' . (string)($acc['name'] ?? '');
                $candidateDigits = preg_replace('/\D+/', '', $candidate);
                if ($candidateDigits !== '' && strpos($candidateDigits, $digits) !== false) {
                    return (int)$acc['id'];
                }
            }
        }

        return (int)$accounts[0]['id'];
    }

    private function resolveCategoryClassification(string $description, string $type): array
    {
        $suggestion = $this->classifier->suggest($this->userId, $description, $type);
        $confidence = (string)($suggestion['confidence'] ?? 'low');
        $categoryId = 0;

        if (!empty($suggestion['category_id']) && $this->allowAutoFillByConfidence($confidence)) {
            $categoryId = (int)$suggestion['category_id'];
        } else {
            $fallbackId = $this->classifier->fallbackCategoryId($this->userId, $type);
            if ($fallbackId !== null) {
                $categoryId = $fallbackId;
            }
            $confidence = 'low';
        }

        if ($categoryId <= 0) {
            throw new RuntimeException('Nenhuma categoria ativa encontrada para classificar a transação OFX.');
        }

        return [
            'category_id' => $categoryId,
            'confidence' => $confidence,
            'reason' => (string)($suggestion['reason'] ?? 'not_found'),
            'normalized_description' => (string)($suggestion['normalized_description'] ?? ''),
        ];
    }

    private function allowAutoFillByConfidence(string $confidence): bool
    {
        return in_array($confidence, ['high', 'medium'], true);
    }

    private function buildOfxNotes(string $fileName, string $fitId, string $trnType, array $meta, array $classification): string
    {
        $parts = ['Importacao automatica OFX'];
        $parts[] = 'OFX_FILE:' . $fileName;
        if ($fitId !== '') {
            $parts[] = 'OFX_FITID:' . $fitId;
        }
        if (trim($trnType) !== '') {
            $parts[] = 'OFX_TRNTYPE:' . trim($trnType);
        }
        if (!empty($meta['account_id'])) {
            $parts[] = 'OFX_ACCOUNT:' . (string)$meta['account_id'];
        }
        if (!empty($meta['bank_id'])) {
            $parts[] = 'OFX_BANK:' . (string)$meta['bank_id'];
        }
        if (!empty($classification['confidence'])) {
            $parts[] = 'CLASS_CONFIDENCE:' . (string)$classification['confidence'];
        }
        if (!empty($classification['reason'])) {
            $parts[] = 'CLASS_REASON:' . (string)$classification['reason'];
        }
        if (!empty($classification['normalized_description'])) {
            $parts[] = 'CLASS_DESC:' . (string)$classification['normalized_description'];
        }

        return implode(' | ', $parts);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        $time = strtotime($value);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function sanitizeDescription(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);
        $value = trim((string)$value);

        if ($value === '') {
            return 'Lancamento OFX';
        }

        if (mb_strlen($value, 'UTF-8') > 255) {
            return mb_substr($value, 0, 255, 'UTF-8');
        }

        return $value;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string)$value);
        return trim((string)$value);
    }

    private function assertOfxFile(string $filePath): void
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'ofx') {
            throw new RuntimeException('Arquivo ignorado: extensão não suportada.');
        }

        $realPending = realpath($this->pendingDir);
        $realFile = realpath($filePath);
        if ($realPending === false || $realFile === false) {
            throw new RuntimeException('Falha ao validar caminho real do arquivo OFX.');
        }

        $prefix = rtrim(strtolower($realPending), '\\/') . DIRECTORY_SEPARATOR;
        if (strpos(strtolower($realFile), $prefix) !== 0) {
            throw new RuntimeException('Arquivo rejeitado por proteção de diretório.');
        }
    }

    private function moveFileTo(string $filePath, string $targetDir, string $statusSuffix): string
    {
        $baseName = pathinfo($filePath, PATHINFO_FILENAME);
        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        $safeSuffix = preg_replace('/[^a-z0-9_\-]/i', '', $statusSuffix);
        $datePart = date('Ymd_His');

        $newFileName = $baseName . '_' . $datePart . '_' . $safeSuffix . '.' . ($ext !== '' ? $ext : 'ofx');
        $destination = $targetDir . DIRECTORY_SEPARATOR . $newFileName;
        $counter = 1;
        while (file_exists($destination)) {
            $destination = $targetDir . DIRECTORY_SEPARATOR . $baseName . '_' . $datePart . '_' . $safeSuffix . '_' . $counter . '.' . ($ext !== '' ? $ext : 'ofx');
            $counter++;
        }

        if (!@rename($filePath, $destination)) {
            if (!@copy($filePath, $destination)) {
                throw new RuntimeException('Falha ao mover arquivo para ' . basename($targetDir) . '.');
            }
            @unlink($filePath);
        }

        return $destination;
    }

    private function getPendingOfxFiles(): array
    {
        $items = @scandir($this->pendingDir);
        if (!is_array($items)) {
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $this->pendingDir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($path)) {
                continue;
            }
            if (strtolower(pathinfo($item, PATHINFO_EXTENSION)) !== 'ofx') {
                continue;
            }
            $files[$path] = filemtime($path) ?: 0;
        }

        asort($files);
        return array_keys($files);
    }

    private function ensureDirectories(): void
    {
        $dirs = [
            $this->importsDir,
            $this->pendingDir,
            $this->processedDir,
            $this->errorDir,
            $this->logsDir,
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar diretório: ' . $dir);
            }
        }
    }

    private function getActiveAccounts(): array
    {
        if ($this->activeAccounts === null) {
            $this->activeAccounts = $this->accountModel->activeByUser($this->userId);
        }
        return $this->activeAccounts;
    }

    private function loadHashRegistry(): array
    {
        if (!file_exists($this->hashRegistryFile)) {
            return [];
        }

        $json = file_get_contents($this->hashRegistryFile);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function saveHashRegistry(array $registry): void
    {
        file_put_contents(
            $this->hashRegistryFile,
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function saveLastRunSummary(array $summary): void
    {
        file_put_contents(
            $this->lastRunFile,
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function getLastRunSummary(): ?array
    {
        if (!file_exists($this->lastRunFile)) {
            return null;
        }

        $json = file_get_contents($this->lastRunFile);
        if ($json === false || trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function writeLog(string $status, string $fileName, array $data = []): void
    {
        $lineParts = [
            '[' . date('Y-m-d H:i:s') . ']',
            'status=' . $status,
            'user_id=' . $this->userId,
            'file=' . $this->sanitizeLogValue($fileName),
        ];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lineParts[] = $key . '=' . $this->sanitizeLogValue((string)$value);
            }
        }

        $line = implode(' | ', $lineParts) . PHP_EOL;
        file_put_contents($this->getTodayLogFile(), $line, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeLogValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], [' ', ' '], $value);
        return trim($value);
    }

    private function getTodayLogFile(): string
    {
        return $this->logsDir . DIRECTORY_SEPARATOR . 'ofx_queue_' . date('Y-m-d') . '.log';
    }

    private function listFiles(string $dir, int $limit): array
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => $item,
                'size' => (int)(filesize($path) ?: 0),
                'modified_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
            ];
        }

        usort($files, static function (array $a, array $b): int {
            return strcmp($b['modified_at'], $a['modified_at']);
        });

        return array_slice($files, 0, $limit);
    }

    private function readRecentLogLines(int $limit): array
    {
        $files = glob($this->logsDir . DIRECTORY_SEPARATOR . 'ofx_queue_*.log');
        if (!is_array($files) || empty($files)) {
            return [];
        }

        rsort($files);
        $lines = [];
        foreach ($files as $file) {
            $current = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($current)) {
                continue;
            }

            $current = array_reverse($current);
            foreach ($current as $line) {
                $lines[] = $line;
                if (count($lines) >= $limit) {
                    return $lines;
                }
            }
        }

        return $lines;
    }

    private function readRecentErrorLines(int $limit): array
    {
        $lines = $this->readRecentLogLines(max($limit * 4, 20));
        $errors = [];

        foreach ($lines as $line) {
            if (strpos($line, 'status=FILE_ERROR') !== false || strpos($line, 'status=RUN_ERROR') !== false) {
                $errors[] = $line;
                if (count($errors) >= $limit) {
                    break;
                }
            }
        }

        return $errors;
    }
}

