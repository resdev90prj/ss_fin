<?php
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Box.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../includes/CategoryAutoClassifier.php';

class TransactionController
{
    private ?string $autoCategoryMessage = null;

    public function index(): void
    {
        $userId = current_user_id();
        $prioritizeOthers = (string)($_GET['prioritize_others'] ?? '') === '1';
        $filters = [
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
            'type' => $_GET['type'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'account_id' => $_GET['account_id'] ?? '',
        ];

        $transactionModel = new Transaction();

        view('transactions/index', [
            'title' => 'Receitas e Despesas',
            'transactions' => $transactionModel->listByUser($userId, $filters, $prioritizeOthers),
            'filters' => $filters,
            'prioritizeOthers' => $prioritizeOthers,
            'othersPendingCount' => $transactionModel->countOthersPending($userId, $filters),
            'accounts' => (new Account())->activeByUser($userId),
            'boxes' => (new Box())->activeByUser($userId),
            'categories' => (new Category())->activeByUser($userId)
        ]);
    }

    public function autoClassifyOthers(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=transactions');
        }

        $userId = current_user_id();
        $result = (new Transaction())->autoClassifyOthers($userId);

        $message = sprintf(
            'Classificação em lote concluída: analisados=%d, reclassificados=%d (alta=%d, média=%d), não alterados=%d, restantes em "Outros gastos"=%d.',
            (int)$result['processed'],
            (int)$result['reclassified'],
            (int)$result['high_confidence'],
            (int)$result['medium_confidence'],
            (int)$result['unchanged'],
            (int)$result['remaining_others']
        );

        $bootstrapped = (int)($result['bootstrapped_from_transactions'] ?? 0);
        if ($bootstrapped > 0) {
            $message .= sprintf(' Base histórica carregada automaticamente a partir de transações antigas: %d registros.', $bootstrapped);
        }

        $diagnostics = is_array($result['diagnostics'] ?? null) ? $result['diagnostics'] : [];
        $message .= sprintf(
            ' Diagnóstico dos não alterados: sugeridos novamente como "Outros gastos"=%d, confiança baixa na sugestão principal=%d, sem histórico alternativo=%d, descrição vazia=%d, descrição curta/sem tokens=%d, sinal histórico fraco=%d, falha de update=%d.',
            (int)($diagnostics['primary_suggested_others'] ?? 0),
            (int)($diagnostics['primary_low_confidence'] ?? 0),
            (int)($diagnostics['history_no_match'] ?? 0),
            (int)($diagnostics['description_empty'] ?? 0),
            (int)($diagnostics['history_insufficient_tokens'] ?? 0),
            (int)($diagnostics['history_weak_signal'] ?? 0),
            (int)($diagnostics['update_failed'] ?? 0)
        );
        flash('success', $message);

        redirect($this->buildTransactionsUrl($_POST, true));
    }

    public function suggestCategory(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $userId = current_user_id();
        $description = trim((string)($_GET['description'] ?? ''));
        $type = (string)($_GET['type'] ?? 'expense');

        if ($description === '') {
            echo json_encode([
                'ok' => true,
                'suggestion' => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $suggestion = (new CategoryAutoClassifier())->suggest($userId, $description, $type);
        if (empty($suggestion['category_id'])) {
            echo json_encode([
                'ok' => true,
                'suggestion' => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'suggestion' => $suggestion,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=transactions');
        }

        $userId = current_user_id();
        $data = $this->validatedPayload($_POST, $userId, true);
        $data['user_id'] = $userId;
        $data['source'] = 'manual';

        (new Transaction())->create($data);

        if ($this->autoCategoryMessage !== null) {
            flash('success', 'Transação cadastrada. ' . $this->autoCategoryMessage);
        } else {
            flash('success', 'Transação cadastrada.');
        }

        redirect('index.php?route=transactions');
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=transactions');
        }

        $userId = current_user_id();
        $id = (int)($_POST['id'] ?? 0);
        $data = $this->validatedPayload($_POST, $userId, false);

        (new Transaction())->update($id, $userId, $data);
        flash('success', 'Transação atualizada.');
        redirect('index.php?route=transactions');
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF inválido.');
            redirect('index.php?route=transactions');
        }
        $id = (int)($_POST['id'] ?? 0);
        (new Transaction())->delete($id, current_user_id());
        flash('success', 'Transação excluída.');
        redirect('index.php?route=transactions');
    }

    private function validatedPayload(array $input, int $userId, bool $allowAutoCategory): array
    {
        $description = trim($input['description'] ?? '');
        $amount = (float)($input['amount'] ?? 0);

        if ($description === '' || $amount <= 0) {
            flash('error', 'Descrição e valor são obrigatórios.');
            redirect('index.php?route=transactions');
        }

        $type = $input['type'] ?? 'expense';
        if (!in_array($type, ['income', 'expense', 'partner_withdrawal', 'transfer'], true)) {
            $type = 'expense';
        }

        $mode = $input['mode'] ?? 'transicao';
        if (!in_array($mode, ['transitorio', 'transicao', 'ideal'], true)) {
            $mode = 'transicao';
        }

        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId <= 0 || !(new Account())->find($accountId, $userId)) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=transactions');
        }

        $categoryId = (int)($input['category_id'] ?? 0);
        if ($categoryId <= 0 && $allowAutoCategory) {
            $suggestion = (new CategoryAutoClassifier())->suggest($userId, $description, $type);
            $isHigh = ($suggestion['confidence'] ?? 'low') === 'high';
            $suggestedCategoryId = (int)($suggestion['category_id'] ?? 0);

            if ($isHigh && $suggestedCategoryId > 0) {
                $categoryId = $suggestedCategoryId;
                $this->autoCategoryMessage = 'Categoria preenchida automaticamente com base no seu histórico (' . (string)($suggestion['category_name'] ?? '') . ').';
            }
        }

        if ($categoryId <= 0 || !(new Category())->find($categoryId, $userId)) {
            flash('error', 'Categoria inválida para o usuário logado.');
            redirect('index.php?route=transactions');
        }

        $boxId = !empty($input['box_id']) ? (int)$input['box_id'] : null;
        if ($boxId !== null && !(new Box())->find($boxId, $userId)) {
            flash('error', 'Caixa inválido para o usuário logado.');
            redirect('index.php?route=transactions');
        }

        return [
            'account_id' => $accountId,
            'box_id' => $boxId,
            'category_id' => $categoryId,
            'type' => $type,
            'mode' => $mode,
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => $input['transaction_date'] ?? date('Y-m-d'),
            'payment_method' => trim($input['payment_method'] ?? ''),
            'notes' => trim($input['notes'] ?? ''),
        ];
    }

    private function buildTransactionsUrl(array $input, bool $prioritizeOthers): string
    {
        $params = ['route' => 'transactions'];

        foreach (['from', 'to', 'type', 'category_id', 'account_id'] as $field) {
            $value = trim((string)($input[$field] ?? ''));
            if ($value !== '') {
                $params[$field] = $value;
            }
        }

        if ($prioritizeOthers) {
            $params['prioritize_others'] = '1';
        }

        return 'index.php?' . http_build_query($params);
    }
}
