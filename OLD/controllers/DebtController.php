<?php
require_once __DIR__ . '/../models/Debt.php';
require_once __DIR__ . '/../models/DebtInstallment.php';
require_once __DIR__ . '/../models/Account.php';

class DebtController
{
    public function index(): void
    {
        $userId = current_user_id();
        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        view('debts/index', [
            'title' => 'Dividas',
            'debts' => $debtModel->allByUser($userId),
            'accounts' => (new Account())->activeByUser($userId),
            'chargesEnabled' => $debtModel->chargeColumnsAvailable(),
        ]);
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $userId = current_user_id();

        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        $debt = $debtModel->find($id, $userId);
        if (!$debt) {
            flash('error', 'Divida nao encontrada.');
            redirect('index.php?route=debts');
        }

        $installments = (new DebtInstallment())->byDebt($id);
        view('debts/show', [
            'title' => 'Detalhes da Divida',
            'debt' => $debt,
            'installments' => $installments,
            'chargesEnabled' => $debtModel->chargeColumnsAvailable(),
        ]);
    }

    public function store(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=debts');
        }

        $dueDay = !empty($_POST['due_day']) ? (int)$_POST['due_day'] : null;
        if ($dueDay !== null) {
            $dueDay = max(1, min($dueDay, 31));
        }

        $totalAmount = $this->parseDecimalInput($_POST['total_amount'] ?? '0');
        $interestValue = max(0, $this->parseDecimalInput($_POST['interest_value'] ?? '0'));
        $penaltyValue = max(0, $this->parseDecimalInput($_POST['penalty_value'] ?? '0'));

        $data = [
            'user_id' => current_user_id(),
            'account_id' => !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'creditor' => trim($_POST['creditor'] ?? ''),
            'total_amount' => $totalAmount,
            'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
            'due_day' => $dueDay,
            'interest_mode' => $this->normalizeChargeMode($_POST['interest_mode'] ?? 'percent'),
            'interest_value' => $interestValue,
            'penalty_mode' => $this->normalizeChargeMode($_POST['penalty_mode'] ?? 'percent'),
            'penalty_value' => $penaltyValue,
            'status' => 'open',
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        $installmentsCount = max(1, (int)($_POST['installments_count'] ?? 1));
        if ($data['description'] === '' || $data['total_amount'] <= 0) {
            flash('error', 'Descricao e valor total sao obrigatorios.');
            redirect('index.php?route=debts');
        }

        if ($data['account_id'] !== null && !(new Account())->find((int)$data['account_id'], (int)$data['user_id'])) {
            flash('error', 'Conta inválida para o usuário logado.');
            redirect('index.php?route=debts');
        }

        $debtModel = new Debt();
        $chargesEnabled = $debtModel->chargeColumnsAvailable();
        $chargesRequested = $data['interest_value'] > 0 || $data['penalty_value'] > 0;

        if ($chargesRequested && !$chargesEnabled) {
            flash('error', 'Juros e multa exigem colunas no banco (interest_*/penalty_*). Atualize a tabela debts e tente novamente.');
            redirect('index.php?route=debts');
        }

        $installmentModel = new DebtInstallment();
        $debtId = $debtModel->create($data);

        $baseAmount = round($data['total_amount'] / $installmentsCount, 2);
        $accumulated = 0.0;
        for ($i = 1; $i <= $installmentsCount; $i++) {
            $dueDate = date('Y-m-d', strtotime("{$data['start_date']} +" . ($i - 1) . ' month'));
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

        flash('success', 'Divida cadastrada com parcelas.');
        redirect('index.php?route=debts');
    }

    public function payInstallment(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=debts');
        }

        $userId = current_user_id();
        $installmentId = (int)($_POST['installment_id'] ?? 0);
        $amount = $this->parseDecimalInput($_POST['amount'] ?? '0');

        if ($amount <= 0) {
            flash('error', 'Valor de pagamento invalido.');
            redirect('index.php?route=debts');
        }

        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        $installmentModel = new DebtInstallment();
        $installment = $installmentModel->findForUser($installmentId, $userId);
        if (!$installment) {
            flash('error', 'Parcela nao encontrada.');
            redirect('index.php?route=debts');
        }

        if ($installment['status'] === 'paid' && (float)$installment['paid_amount'] + 0.0001 >= (float)$installment['amount']) {
            flash('error', 'Esta parcela ja esta quitada.');
            redirect('index.php?route=debts_show&id=' . (int)$installment['debt_id']);
        }

        $installmentModel->pay($installmentId, $amount);
        $debtModel->updatePaid((int)$installment['debt_id']);

        flash('success', 'Pagamento da parcela registrado.');
        redirect('index.php?route=debts_show&id=' . (int)$installment['debt_id']);
    }

    public function refundInstallment(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=debts');
        }

        $userId = current_user_id();
        $installmentId = (int)($_POST['installment_id'] ?? 0);
        $amount = $this->parseDecimalInput($_POST['amount'] ?? '0');

        if ($amount <= 0) {
            flash('error', 'Valor de estorno invalido.');
            redirect('index.php?route=debts');
        }

        $debtModel = new Debt();
        $debtModel->applyMonthlyCharges($userId);

        $installmentModel = new DebtInstallment();
        $installment = $installmentModel->findForUser($installmentId, $userId);
        if (!$installment) {
            flash('error', 'Parcela nao encontrada.');
            redirect('index.php?route=debts');
        }

        $paidAmount = (float)$installment['paid_amount'];
        if ($paidAmount <= 0) {
            flash('error', 'Nao existe pagamento para estornar nesta parcela.');
            redirect('index.php?route=debts_show&id=' . (int)$installment['debt_id']);
        }

        if (($amount - $paidAmount) > 0.0001) {
            flash('error', 'Estorno maior que o valor pago da parcela.');
            redirect('index.php?route=debts_show&id=' . (int)$installment['debt_id']);
        }

        $installmentModel->refund($installmentId, $amount);
        $debtModel->updatePaid((int)$installment['debt_id']);

        flash('success', 'Estorno da parcela registrado.');
        redirect('index.php?route=debts_show&id=' . (int)$installment['debt_id']);
    }

    public function delete(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=debts');
        }

        $id = (int)($_POST['id'] ?? 0);
        $result = (new Debt())->deletePendingOnly($id, current_user_id());
        if (!$result['ok']) {
            flash('error', $result['message'] ?? 'Nao foi possivel excluir a divida.');
            redirect('index.php?route=debts');
        }

        flash('success', 'Divida excluida com sucesso.');
        redirect('index.php?route=debts');
    }

    public function deleteInstallment(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=debts');
        }

        $installmentId = (int)($_POST['installment_id'] ?? 0);
        $debtId = (int)($_POST['debt_id'] ?? 0);
        $installmentModel = new DebtInstallment();

        $result = $installmentModel->deletePendingInstallment($installmentId, current_user_id());
        if (!$result['ok']) {
            flash('error', $result['message'] ?? 'Nao foi possivel excluir a parcela.');
            redirect('index.php?route=debts_show&id=' . $debtId);
        }

        $realDebtId = (int)($result['debt_id'] ?? $debtId);
        (new Debt())->updatePaid($realDebtId);

        flash('success', 'Parcela excluida com sucesso.');
        redirect('index.php?route=debts_show&id=' . $realDebtId);
    }

    private function normalizeChargeMode(string $mode): string
    {
        return $mode === 'fixed' ? 'fixed' : 'percent';
    }

    private function parseDecimalInput($value): float
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
