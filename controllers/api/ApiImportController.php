<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Category.php';
require_once api_project_root() . '/models/Account.php';
require_once api_project_root() . '/includes/OfxQueueProcessor.php';
require_once api_project_root() . '/includes/CategoryAutoClassifier.php';

class ApiImportController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $queueData = [];
        $queueError = '';

        try {
            $queueData = (new OfxQueueProcessor($userId))->getDashboardData();
        } catch (Throwable $throwable) {
            $queueError = $throwable->getMessage();
            error_log('[api-imports] queue dashboard failed: ' . $throwable->getMessage());
        }

        api_json_response(true, 'Painel de importacao carregado com sucesso.', [
            'accounts' => (new Account())->activeByUser($userId),
            'queue_data' => $queueData,
            'queue_error' => $queueError,
        ]);
    }

    public function upload(): void
    {
        api_require_login();
        api_verify_csrf_or_fail((string)($_POST['csrf_token'] ?? $_POST['_csrf'] ?? ''));

        if (empty($_FILES['statement']['tmp_name']) || !is_uploaded_file($_FILES['statement']['tmp_name'])) {
            api_json_response(false, 'Arquivo invalido.', [], ['Selecione um arquivo valido para importar.'], 422);
        }

        $userId = api_current_effective_user_id();
        $accountId = (int)($_POST['account_id'] ?? 0);
        if ($accountId <= 0 || !(new Account())->find($accountId, $userId)) {
            api_json_response(false, 'Conta invalida.', [], ['Selecione uma conta valida para receber o extrato.'], 422);
        }

        $name = (string)($_FILES['statement']['name'] ?? '');
        $tmp = (string)($_FILES['statement']['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $destDir = api_project_root() . '/public_html/uploads/statements';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $dest = $destDir . '/' . uniqid('stmt_', true) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dest)) {
            api_json_response(false, 'Falha ao mover arquivo.', [], ['Nao foi possivel salvar o arquivo enviado.'], 500);
        }

        [$rows, $source] = $this->parseRows($dest, $ext);
        if ($source === null) {
            api_json_response(false, 'Formato nao suportado.', [], ['Use arquivos CSV, OFX ou XLSX.'], 422);
        }

        $transactionModel = new Transaction();
        $classifier = new CategoryAutoClassifier();
        $activeCategories = (new Category())->activeByUser($userId);
        if (empty($activeCategories)) {
            api_json_response(false, 'Nenhuma categoria ativa encontrada.', [], ['Crie ou ative categorias antes de importar.'], 422);
        }

        $inserted = 0;
        $classifiedHigh = 0;
        $classifiedMedium = 0;
        $fallbackUsed = 0;
        $skippedInvalid = 0;

        foreach ($rows as $row) {
            $description = trim((string)($row['description'] ?? 'Importado'));
            $amount = (float)($row['amount'] ?? 0);
            $date = (string)($row['date'] ?? date('Y-m-d'));

            if ($amount == 0.0) {
                $skippedInvalid++;
                continue;
            }

            $type = $amount > 0 ? 'income' : 'expense';
            $suggestion = $classifier->suggest($userId, $description, $type);
            $categoryId = null;
            $classificationLabel = 'fallback';

            if (!empty($suggestion['category_id']) && $this->allowAutoFillByConfidence((string)($suggestion['confidence'] ?? 'low'))) {
                $categoryId = (int)$suggestion['category_id'];
                if (($suggestion['confidence'] ?? '') === 'high') {
                    $classifiedHigh++;
                    $classificationLabel = 'alta';
                } else {
                    $classifiedMedium++;
                    $classificationLabel = 'media';
                }
            } else {
                $fallbackId = $classifier->fallbackCategoryId($userId, $type);
                if ($fallbackId !== null) {
                    $categoryId = $fallbackId;
                    $fallbackUsed++;
                }
            }

            if ($categoryId === null || $categoryId <= 0) {
                $skippedInvalid++;
                continue;
            }

            $notes = 'Importacao automatica | Classificacao: ' . $classificationLabel;
            if (!empty($suggestion['reason'])) {
                $notes .= ' | Regra: ' . (string)$suggestion['reason'];
            }

            $transactionModel->create([
                'user_id' => $userId,
                'account_id' => $accountId,
                'box_id' => null,
                'category_id' => $categoryId,
                'type' => $type,
                'mode' => 'transicao',
                'description' => $description,
                'amount' => abs($amount),
                'transaction_date' => $date,
                'payment_method' => 'importado',
                'notes' => $notes,
                'source' => $source,
            ]);
            $inserted++;
        }

        api_json_response(true, 'Importacao concluida com sucesso.', [
            'summary' => [
                'inserted' => $inserted,
                'classified_high' => $classifiedHigh,
                'classified_medium' => $classifiedMedium,
                'fallback_used' => $fallbackUsed,
                'skipped_invalid' => $skippedInvalid,
            ],
        ]);
    }

    public function processOfxQueue(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $summary = (new OfxQueueProcessor(api_current_effective_user_id()))->processQueue('web');

        api_json_response(true, 'Fila OFX processada com sucesso.', [
            'summary' => $summary,
        ]);
    }

    private function allowAutoFillByConfidence(string $confidence): bool
    {
        return in_array($confidence, ['high', 'medium'], true);
    }

    private function parseRows(string $file, string $extension): array
    {
        if ($extension === 'csv') {
            return [$this->parseCsv($file), 'import_csv'];
        }

        if ($extension === 'ofx') {
            return [$this->parseOfx($file), 'import_ofx'];
        }

        if ($extension === 'xlsx') {
            return [$this->parseXlsx($file), 'import_xlsx'];
        }

        return [[], null];
    }

    private function parseCsv(string $file): array
    {
        $rows = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return $rows;
        }

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) < 3) {
                continue;
            }

            $rows[] = [
                'date' => $this->normalizeDate((string)$data[0]),
                'description' => trim((string)$data[1]),
                'amount' => (float)str_replace(',', '.', preg_replace('/[^0-9,\.-]/', '', (string)$data[2])),
            ];
        }

        fclose($handle);
        return $rows;
    }

    private function parseOfx(string $file): array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $matches);
        $rows = [];
        foreach ($matches[1] as $transaction) {
            preg_match('/<TRNAMT>([^\r\n<]+)/i', $transaction, $amountMatches);
            preg_match('/<DTPOSTED>(\d{8})/i', $transaction, $dateMatches);
            preg_match('/<MEMO>([^\r\n<]+)/i', $transaction, $memoMatches);

            $rows[] = [
                'date' => isset($dateMatches[1])
                    ? substr($dateMatches[1], 0, 4) . '-' . substr($dateMatches[1], 4, 2) . '-' . substr($dateMatches[1], 6, 2)
                    : date('Y-m-d'),
                'description' => trim((string)($memoMatches[1] ?? 'Lancamento OFX')),
                'amount' => isset($amountMatches[1]) ? (float)$amountMatches[1] : 0.0,
            ];
        }

        return $rows;
    }

    private function parseXlsx(string $file): array
    {
        if (!class_exists('ZipArchive')) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return [];
        }

        $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sheetData === false) {
            return [];
        }

        $strings = [];
        if ($sharedStrings) {
            $stringXml = simplexml_load_string($sharedStrings);
            if ($stringXml) {
                foreach ($stringXml->si as $stringItem) {
                    $strings[] = (string)$stringItem->t;
                }
            }
        }

        $xml = simplexml_load_string($sheetData);
        if (!$xml) {
            return [];
        }

        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xml->xpath('//a:sheetData/a:row') as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $type = (string)$cell['t'];
                $value = (string)$cell->v;
                $cells[] = $type === 's' ? ($strings[(int)$value] ?? '') : $value;
            }

            if (count($cells) < 3 || !preg_match('/\d/', (string)$cells[0])) {
                continue;
            }

            $rows[] = [
                'date' => $this->normalizeDate((string)$cells[0]),
                'description' => (string)$cells[1],
                'amount' => (float)str_replace(',', '.', preg_replace('/[^0-9,\.-]/', '', (string)$cells[2])),
            ];
        }

        return $rows;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (is_numeric($value)) {
            $unix = ((int)$value - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }

        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : date('Y-m-d');
    }
}
