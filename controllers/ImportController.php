<?php
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../includes/OfxQueueProcessor.php';
require_once __DIR__ . '/../includes/CategoryAutoClassifier.php';

class ImportController
{
    public function index(): void
    {
        $accounts = (new Account())->activeByUser(current_user_id());
        $queueData = [];

        try {
            $queueData = (new OfxQueueProcessor(current_user_id()))->getDashboardData();
        } catch (Throwable $e) {
            flash('error', 'Não foi possível carregar painel da fila OFX: ' . $e->getMessage());
        }

        view('imports/index', [
            'title' => 'Importação de Extratos',
            'accounts' => $accounts,
            'queueData' => $queueData,
        ]);
    }

    public function upload(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=imports');
        }

        if (empty($_FILES['statement']['tmp_name']) || !is_uploaded_file($_FILES['statement']['tmp_name'])) {
            flash('error', 'Arquivo inválido.');
            redirect('index.php?route=imports');
        }

        $userId = current_user_id();
        $accountId = (int)($_POST['account_id'] ?? 0);
        if ($accountId <= 0) {
            flash('error', 'Selecione uma conta.');
            redirect('index.php?route=imports');
        }
        if (!(new Account())->find($accountId, $userId)) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=imports');
        }

        $name = $_FILES['statement']['name'];
        $tmp = $_FILES['statement']['tmp_name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $destDir = __DIR__ . '/../public_html/uploads/statements';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . uniqid('stmt_', true) . '.' . $ext;
        move_uploaded_file($tmp, $dest);

        $rows = [];
        if ($ext === 'csv') {
            $rows = $this->parseCsv($dest);
            $source = 'import_csv';
        } elseif ($ext === 'ofx') {
            $rows = $this->parseOfx($dest);
            $source = 'import_ofx';
        } elseif ($ext === 'xlsx') {
            $rows = $this->parseXlsx($dest);
            $source = 'import_xlsx';
        } else {
            flash('error', 'Formato não suportado. Use CSV, OFX ou XLSX.');
            redirect('index.php?route=imports');
        }

        $transactionModel = new Transaction();
        $classifier = new CategoryAutoClassifier();

        $activeCategories = (new Category())->activeByUser($userId);
        if (empty($activeCategories)) {
            flash('error', 'Nenhuma categoria ativa encontrada para classificar os lançamentos importados.');
            redirect('index.php?route=imports');
        }

        $inserted = 0;
        $classifiedHigh = 0;
        $classifiedMedium = 0;
        $fallbackUsed = 0;
        $skippedInvalid = 0;

        foreach ($rows as $r) {
            $description = trim($r['description'] ?? 'Importado');
            $amount = (float)($r['amount'] ?? 0);
            $date = $r['date'] ?? date('Y-m-d');
            if ($amount == 0) {
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

            $notes = 'Importação automática | Classificação: ' . $classificationLabel;
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

        flash(
            'success',
            "Importação concluída. {$inserted} transações registradas. " .
            "Classificação automática: alta={$classifiedHigh}, média={$classifiedMedium}, fallback={$fallbackUsed}, ignoradas={$skippedInvalid}."
        );
        redirect('index.php?route=imports');
    }

    public function processOfxQueue(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && !verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=imports');
        }

        try {
            $summary = (new OfxQueueProcessor(current_user_id()))->processQueue('web');

            $message = sprintf(
                'Fila OFX processada: arquivos processados=%d, lançamentos criados=%d, classificação alta=%d, classificação média=%d, fallback=%d, duplicidades ignoradas=%d, arquivos duplicados=%d, falhas=%d.',
                (int)$summary['files_processed'],
                (int)$summary['transactions_created'],
                (int)($summary['transactions_classified_high'] ?? 0),
                (int)($summary['transactions_classified_medium'] ?? 0),
                (int)($summary['transactions_fallback_used'] ?? 0),
                (int)$summary['transactions_ignored_duplicate'],
                (int)$summary['files_skipped_duplicate_file'],
                (int)$summary['files_failed']
            );

            if ((int)$summary['files_failed'] > 0) {
                flash('error', $message);
            } else {
                flash('success', $message);
            }
        } catch (Throwable $e) {
            flash('error', 'Erro ao processar fila OFX: ' . $e->getMessage());
        }

        redirect('index.php?route=imports');
    }

    private function allowAutoFillByConfidence(string $confidence): bool
    {
        return in_array($confidence, ['high', 'medium'], true);
    }

    private function parseCsv(string $file): array
    {
        $rows = [];
        if (($h = fopen($file, 'r')) === false) {
            return $rows;
        }

        while (($data = fgetcsv($h, 0, ';')) !== false) {
            if (count($data) < 3) {
                continue;
            }
            $date = $this->normalizeDate($data[0]);
            $description = trim($data[1]);
            $amount = (float)str_replace(',', '.', preg_replace('/[^0-9,\.-]/', '', $data[2]));
            $rows[] = ['date' => $date, 'description' => $description, 'amount' => $amount];
        }
        fclose($h);
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
        foreach ($matches[1] as $trx) {
            preg_match('/<TRNAMT>([^\r\n<]+)/i', $trx, $am);
            preg_match('/<DTPOSTED>(\d{8})/i', $trx, $dt);
            preg_match('/<MEMO>([^\r\n<]+)/i', $trx, $mm);

            $amount = isset($am[1]) ? (float)$am[1] : 0;
            $date = isset($dt[1]) ? substr($dt[1], 0, 4) . '-' . substr($dt[1], 4, 2) . '-' . substr($dt[1], 6, 2) : date('Y-m-d');
            $description = trim($mm[1] ?? 'Lançamento OFX');

            $rows[] = ['date' => $date, 'description' => $description, 'amount' => $amount];
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
            $sx = simplexml_load_string($sharedStrings);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $strings[] = (string)$si->t;
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
            foreach ($row->c as $c) {
                $type = (string)$c['t'];
                $value = (string)$c->v;
                if ($type === 's') {
                    $cells[] = $strings[(int)$value] ?? '';
                } else {
                    $cells[] = $value;
                }
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
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (is_numeric($value)) {
            $unix = ((int)$value - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }

        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : date('Y-m-d');
    }
}
