<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Account.php';
require_once api_project_root() . '/models/Box.php';
require_once api_project_root() . '/models/Category.php';
require_once api_project_root() . '/includes/CategoryAutoClassifier.php';

class ApiTransactionController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $prioritizeOthers = $this->toBoolean($_GET['prioritize_others'] ?? false);
        $filters = [
            'from' => trim((string)($_GET['from'] ?? '')),
            'to' => trim((string)($_GET['to'] ?? '')),
            'type' => trim((string)($_GET['type'] ?? '')),
            'category_id' => trim((string)($_GET['category_id'] ?? '')),
            'account_id' => trim((string)($_GET['account_id'] ?? '')),
        ];

        $transactionModel = new Transaction();
        $items = $transactionModel->listByUser($userId, $filters, $prioritizeOthers);
        $othersPendingCount = $transactionModel->countOthersPending($userId, $filters);

        $summary = [
            'total' => count($items),
            'income_total' => 0.0,
            'expense_total' => 0.0,
            'withdrawal_total' => 0.0,
            'transfer_total' => 0.0,
            'others_pending_count' => $othersPendingCount,
        ];

        foreach ($items as &$item) {
            $amount = (float)($item['amount'] ?? 0);
            $type = (string)($item['type'] ?? 'expense');
            $categoryName = mb_strtolower((string)($item['category_name'] ?? ''), 'UTF-8');

            if ($type === 'income') {
                $summary['income_total'] += $amount;
            } elseif ($type === 'partner_withdrawal') {
                $summary['withdrawal_total'] += $amount;
            } elseif ($type === 'transfer') {
                $summary['transfer_total'] += $amount;
            } else {
                $summary['expense_total'] += $amount;
            }

            $item['amount'] = $amount;
            $item['is_others_category'] = $categoryName === mb_strtolower('Outros gastos', 'UTF-8');
        }
        unset($item);

        api_json_response(true, 'Transacoes carregadas com sucesso.', [
            'items' => $items,
            'filters' => $filters,
            'prioritize_others' => $prioritizeOthers,
            'summary' => $summary,
            'lookups' => [
                'accounts' => (new Account())->activeByUser($userId),
                'boxes' => (new Box())->activeByUser($userId),
                'categories' => (new Category())->activeByUser($userId),
            ],
        ]);
    }

    public function suggestCategory(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $description = trim((string)($_GET['description'] ?? ''));
        $type = $this->normalizeType((string)($_GET['type'] ?? 'expense'));

        if ($description === '') {
            api_json_response(true, 'Nenhuma sugestao disponivel.', [
                'suggestion' => null,
            ]);
        }

        $suggestion = (new CategoryAutoClassifier())->suggest($userId, $description, $type);
        if (empty($suggestion['category_id'])) {
            api_json_response(true, 'Nenhuma sugestao disponivel.', [
                'suggestion' => null,
            ]);
        }

        api_json_response(true, 'Sugestao de categoria carregada.', [
            'suggestion' => $suggestion,
        ]);
    }

    public function autoClassifyOthers(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $result = (new Transaction())->autoClassifyOthers($userId);

        api_json_response(true, 'Classificacao em lote concluida com sucesso.', [
            'result' => $result,
        ]);
    }

    public function store(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $payload['user_id'] = $userId;
        $data = $this->validatedPayload($payload, true);
        $data['user_id'] = $userId;
        $data['source'] = 'manual';

        (new Transaction())->create($data);

        api_json_response(true, $this->autoCategoryMessage !== null
            ? 'Transacao cadastrada com sucesso. ' . $this->autoCategoryMessage
            : 'Transacao cadastrada com sucesso.'
        );
    }

    public function update(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Transaction())->find($id, $userId)) {
            api_json_response(false, 'Transacao nao encontrada.', [], ['Transacao invalida para o usuario atual.'], 404);
        }

        $data = $this->validatedPayload($payload, false);
        (new Transaction())->update($id, $userId, $data);

        api_json_response(true, 'Transacao atualizada com sucesso.');
    }

    public function delete(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0 || !(new Transaction())->find($id, $userId)) {
            api_json_response(false, 'Transacao nao encontrada.', [], ['Transacao invalida para o usuario atual.'], 404);
        }

        (new Transaction())->delete($id, $userId);

        api_json_response(true, 'Transacao excluida com sucesso.');
    }

    private ?string $autoCategoryMessage = null;

    private function validatedPayload(array $input, bool $allowAutoCategory): array
    {
        $userId = api_current_effective_user_id();
        $description = trim((string)($input['description'] ?? ''));
        $amount = (float)($input['amount'] ?? 0);

        if ($description === '' || $amount <= 0) {
            api_json_response(false, 'Descricao e valor sao obrigatorios.', [], ['Informe descricao e um valor maior que zero.'], 422);
        }

        $type = $this->normalizeType((string)($input['type'] ?? 'expense'));
        $mode = $this->normalizeMode((string)($input['mode'] ?? 'transicao'));

        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId <= 0 || !(new Account())->find($accountId, $userId)) {
            api_json_response(false, 'Conta invalida.', [], ['Selecione uma conta valida para o usuario atual.'], 422);
        }

        $categoryId = (int)($input['category_id'] ?? 0);
        if ($categoryId <= 0 && $allowAutoCategory) {
            $suggestion = (new CategoryAutoClassifier())->suggest($userId, $description, $type);
            $isHigh = ($suggestion['confidence'] ?? 'low') === 'high';
            $suggestedCategoryId = (int)($suggestion['category_id'] ?? 0);

            if ($isHigh && $suggestedCategoryId > 0) {
                $categoryId = $suggestedCategoryId;
                $this->autoCategoryMessage = 'Categoria preenchida automaticamente com base no seu historico (' . (string)($suggestion['category_name'] ?? '') . ').';
            }
        }

        if ($categoryId <= 0 || !(new Category())->find($categoryId, $userId)) {
            api_json_response(false, 'Categoria invalida.', [], ['Selecione uma categoria valida para o usuario atual.'], 422);
        }

        $boxId = !empty($input['box_id']) ? (int)$input['box_id'] : null;
        if ($boxId !== null && !(new Box())->find($boxId, $userId)) {
            api_json_response(false, 'Caixa invalido.', [], ['Selecione um caixa valido para o usuario atual.'], 422);
        }

        $transactionDate = trim((string)($input['transaction_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
            $transactionDate = date('Y-m-d');
        }

        return [
            'account_id' => $accountId,
            'box_id' => $boxId,
            'category_id' => $categoryId,
            'type' => $type,
            'mode' => $mode,
            'description' => $description,
            'amount' => $amount,
            'transaction_date' => $transactionDate,
            'payment_method' => trim((string)($input['payment_method'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['income', 'expense', 'partner_withdrawal', 'transfer'], true)
            ? $type
            : 'expense';
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, ['transitorio', 'transicao', 'ideal'], true)
            ? $mode
            : 'transicao';
    }

    private function toBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}
