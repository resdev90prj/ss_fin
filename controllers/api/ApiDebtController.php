<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Debt.php';
require_once api_project_root() . '/models/DebtInstallment.php';
require_once api_project_root() . '/models/Account.php';

class ApiDebtController
{
    public function index(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);
        $items = $debtModel->allByUser($userId);

        $summary = [
            'total' => count($items),
            'open_count' => 0,
            'paid_count' => 0,
            'total_amount' => 0.0,
            'paid_amount' => 0.0,
            'remaining_amount' => 0.0,
        ];

        foreach ($items as &$item) {
            $totalAmount = (float)($item['total_amount'] ?? 0);
            $paidAmount = (float)($item['paid_amount'] ?? 0);
            $remaining = (float)($item['remaining'] ?? ($totalAmount - $paidAmount));
            $item['total_amount'] = $totalAmount;
            $item['paid_amount'] = $paidAmount;
            $item['remaining'] = $remaining;
            $item['has_paid_installments'] = (int)($item['paid_installments_count'] ?? 0) > 0 || $paidAmount > 0;

            $summary['total_amount'] += $totalAmount;
            $summary['paid_amount'] += $paidAmount;
            $summary['remaining_amount'] += $remaining;
            $summary[(string)($item['status'] ?? 'open') === 'paid' ? 'paid_count' : 'open_count']++;
        }
        unset($item);

        api_json_response(true, 'Dividas carregadas com sucesso.', [
            'items' => $items,
            'summary' => $summary,
            'charges_enabled' => $debtModel->chargeColumnsAvailable(),
            'lookups' => [
                'accounts' => (new Account())->activeByUser($userId),
            ],
        ]);
    }

    public function details(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $id = (int)($_GET['id'] ?? 0);
        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);
        $debt = $debtModel->find($id, $userId);

        if (!$debt) {
            api_json_response(false, 'Divida nao encontrada.', [], ['Divida invalida para o usuario atual.'], 404);
        }

        $installments = (new DebtInstallment())->byDebt($id);
        foreach ($installments as &$installment) {
            $installment['amount'] = (float)($installment['amount'] ?? 0);
            $installment['paid_amount'] = (float)($installment['paid_amount'] ?? 0);
            $installment['remaining_amount'] = max(0, $installment['amount'] - $installment['paid_amount']);
            $installment['is_paid'] = (string)($installment['status'] ?? 'pending') === 'paid' || $installment['paid_amount'] > 0;
        }
        unset($installment);

        api_json_response(true, 'Detalhes da divida carregados com sucesso.', [
            'debt' => $debt,
            'installments' => $installments,
            'charges_enabled' => $debtModel->chargeColumnsAvailable(),
        ]);
    }

    public function store(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $data = $this->validatedDebtPayload($payload, $userId);
        $data['user_id'] = $userId;
        $installmentsCount = max(1, (int)($payload['installments_count'] ?? 1));

        $debtModel = new Debt();
        $chargesEnabled = $debtModel->chargeColumnsAvailable();
        $chargesRequested = $data['interest_value'] > 0 || $data['penalty_value'] > 0;
        if ($chargesRequested && !$chargesEnabled) {
            api_json_response(false, 'Juros e multa nao estao habilitados no banco atual.', [], ['Atualize as colunas interest_* e penalty_* da tabela debts.'], 422);
        }

        $installmentModel = new DebtInstallment();
        $debtId = $debtModel->create($data);
        $baseAmount = round($data['total_amount'] / $installmentsCount, 2);
        $accumulated = 0.0;

        for ($i = 1; $i <= $installmentsCount; $i++) {
            $dueDate = date('Y-m-d', strtotime($data['start_date'] . ' +' . ($i - 1) . ' month'));
            if ($i < $installmentsCount) {
                $amount = $baseAmount;
                $accumulated += $amount;
            } else {
                $amount = round($data['total_amount'] - $accumulated, 2);
            }

            $installmentModel->create([
                'debt_id' => $debtId,
                'installment_number' => $i,
                'due_date' => $dueDate,
                'amount' => $amount,
                'status' => 'pending',
            ]);
        }

        api_json_response(true, 'Divida cadastrada com sucesso.', [
            'debt_id' => $debtId,
        ]);
    }

    public function payInstallment(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $installmentId = (int)($payload['installment_id'] ?? 0);
        $amount = $this->parseDecimalInput($payload['amount'] ?? '0');

        if ($amount <= 0) {
            api_json_response(false, 'Valor de pagamento invalido.', [], ['Informe um valor maior que zero para o pagamento.'], 422);
        }

        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        $installmentModel = new DebtInstallment();
        $installment = $installmentModel->findForUser($installmentId, $userId);
        if (!$installment) {
            api_json_response(false, 'Parcela nao encontrada.', [], ['Parcela invalida para o usuario atual.'], 404);
        }

        if ((string)$installment['status'] === 'paid' && ((float)$installment['paid_amount'] + 0.0001) >= (float)$installment['amount']) {
            api_json_response(false, 'Esta parcela ja esta quitada.', [], ['Nao ha saldo pendente nesta parcela.'], 422);
        }

        $installmentModel->pay($installmentId, $amount);
        $debtModel->updatePaid((int)$installment['debt_id']);

        api_json_response(true, 'Pagamento registrado com sucesso.', [
            'debt_id' => (int)$installment['debt_id'],
        ]);
    }

    public function refundInstallment(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = api_current_effective_user_id();
        $installmentId = (int)($payload['installment_id'] ?? 0);
        $amount = $this->parseDecimalInput($payload['amount'] ?? '0');

        if ($amount <= 0) {
            api_json_response(false, 'Valor de estorno invalido.', [], ['Informe um valor maior que zero para o estorno.'], 422);
        }

        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        $installmentModel = new DebtInstallment();
        $installment = $installmentModel->findForUser($installmentId, $userId);
        if (!$installment) {
            api_json_response(false, 'Parcela nao encontrada.', [], ['Parcela invalida para o usuario atual.'], 404);
        }

        $paidAmount = (float)$installment['paid_amount'];
        if ($paidAmount <= 0) {
            api_json_response(false, 'Nao existe pagamento para estornar nesta parcela.', [], ['A parcela ainda nao possui valor pago.'], 422);
        }

        if (($amount - $paidAmount) > 0.0001) {
            api_json_response(false, 'Estorno maior que o valor pago da parcela.', [], ['Informe um estorno menor ou igual ao valor pago.'], 422);
        }

        $installmentModel->refund($installmentId, $amount);
        $debtModel->updatePaid((int)$installment['debt_id']);

        api_json_response(true, 'Estorno registrado com sucesso.', [
            'debt_id' => (int)$installment['debt_id'],
        ]);
    }

    public function delete(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $result = (new Debt())->deletePendingOnly((int)($payload['id'] ?? 0), api_current_effective_user_id());
        if (!$result['ok']) {
            api_json_response(false, (string)($result['message'] ?? 'Nao foi possivel excluir a divida.'), [], [(string)($result['message'] ?? 'Exclusao bloqueada.')], 422);
        }

        api_json_response(true, 'Divida excluida com sucesso.');
    }

    public function deleteInstallment(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $result = (new DebtInstallment())->deletePendingInstallment((int)($payload['installment_id'] ?? 0), api_current_effective_user_id());
        if (!$result['ok']) {
            api_json_response(false, (string)($result['message'] ?? 'Nao foi possivel excluir a parcela.'), [], [(string)($result['message'] ?? 'Exclusao bloqueada.')], 422);
        }

        $debtId = (int)($result['debt_id'] ?? 0);
        if ($debtId > 0) {
            (new Debt())->updatePaid($debtId);
        }

        api_json_response(true, 'Parcela excluida com sucesso.', [
            'debt_id' => $debtId,
        ]);
    }

    private function validatedDebtPayload(array $payload, int $userId): array
    {
        $dueDay = !empty($payload['due_day']) ? max(1, min((int)$payload['due_day'], 31)) : null;
        $totalAmount = $this->parseDecimalInput($payload['total_amount'] ?? '0');
        $interestValue = max(0, $this->parseDecimalInput($payload['interest_value'] ?? '0'));
        $penaltyValue = max(0, $this->parseDecimalInput($payload['penalty_value'] ?? '0'));
        $accountId = !empty($payload['account_id']) ? (int)$payload['account_id'] : null;
        $startDate = trim((string)($payload['start_date'] ?? date('Y-m-d')));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }

        $data = [
            'account_id' => $accountId,
            'description' => trim((string)($payload['description'] ?? '')),
            'creditor' => trim((string)($payload['creditor'] ?? '')),
            'total_amount' => $totalAmount,
            'start_date' => $startDate,
            'due_day' => $dueDay,
            'interest_mode' => $this->normalizeChargeMode((string)($payload['interest_mode'] ?? 'percent')),
            'interest_value' => $interestValue,
            'penalty_mode' => $this->normalizeChargeMode((string)($payload['penalty_mode'] ?? 'percent')),
            'penalty_value' => $penaltyValue,
            'status' => 'open',
            'notes' => trim((string)($payload['notes'] ?? '')),
        ];

        if ($data['description'] === '' || $data['total_amount'] <= 0) {
            api_json_response(false, 'Descricao e valor total sao obrigatorios.', [], ['Informe descricao e valor total maior que zero.'], 422);
        }

        if ($accountId !== null && !(new Account())->find($accountId, $userId)) {
            api_json_response(false, 'Conta invalida.', [], ['Selecione uma conta valida para esta divida.'], 422);
        }

        return $data;
    }

    private function normalizeChargeMode(string $mode): string
    {
        return $mode === 'fixed' ? 'fixed' : 'percent';
    }

    private function parseDecimalInput(mixed $value): float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0.0;
        }

        $raw = str_replace(['R$', ' '], '', $raw);
        $hasComma = strpos($raw, ',') !== false;
        $hasDot = strpos($raw, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        }

        $raw = preg_replace('/[^0-9\.\-]/', '', $raw);
        if (!is_string($raw) || $raw === '' || !is_numeric($raw)) {
            return 0.0;
        }

        return (float)$raw;
    }
}
