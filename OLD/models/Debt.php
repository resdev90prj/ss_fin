<?php
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/DebtInstallment.php';

class Debt extends Model
{
    private ?bool $supportsChargeColumnsCache = null;

    public function chargeColumnsAvailable(): bool
    {
        return $this->supportsChargeColumns();
    }

    public function allByUser(int $userId): array
    {
        $this->applyMonthlyCharges($userId);

        $sql = 'SELECT d.*, a.name AS account_name,
                (d.total_amount - d.paid_amount) AS remaining,
                (
                    SELECT COUNT(*)
                    FROM debt_installments di
                    WHERE di.debt_id = d.id
                      AND (di.status = \'paid\' OR di.paid_amount > 0)
                ) AS paid_installments_count
                FROM debts d
                LEFT JOIN accounts a ON a.id = d.account_id AND a.user_id = d.user_id
                WHERE d.user_id = :user_id
                ORDER BY d.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM debts WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        if ($this->supportsChargeColumns()) {
            $sql = 'INSERT INTO debts (user_id, account_id, description, creditor, total_amount, paid_amount, start_date, due_day,
                    interest_mode, interest_value, penalty_mode, penalty_value, last_charge_month, status, notes)
                    VALUES (:user_id, :account_id, :description, :creditor, :total_amount, 0, :start_date, :due_day,
                    :interest_mode, :interest_value, :penalty_mode, :penalty_value, :last_charge_month, :status, :notes)';
            $payload = [
                'user_id' => $data['user_id'],
                'account_id' => $data['account_id'],
                'description' => $data['description'],
                'creditor' => $data['creditor'],
                'total_amount' => $data['total_amount'],
                'start_date' => $data['start_date'],
                'due_day' => $data['due_day'],
                'interest_mode' => $this->normalizeChargeMode((string)($data['interest_mode'] ?? 'percent')),
                'interest_value' => max(0, (float)($data['interest_value'] ?? 0)),
                'penalty_mode' => $this->normalizeChargeMode((string)($data['penalty_mode'] ?? 'percent')),
                'penalty_value' => max(0, (float)($data['penalty_value'] ?? 0)),
                'last_charge_month' => null,
                'status' => $data['status'],
                'notes' => $data['notes'],
            ];
            $this->db->prepare($sql)->execute($payload);
        } else {
            $sql = 'INSERT INTO debts (user_id, account_id, description, creditor, total_amount, paid_amount, start_date, due_day, status, notes)
                    VALUES (:user_id, :account_id, :description, :creditor, :total_amount, 0, :start_date, :due_day, :status, :notes)';
            $payload = [
                'user_id' => $data['user_id'],
                'account_id' => $data['account_id'],
                'description' => $data['description'],
                'creditor' => $data['creditor'],
                'total_amount' => $data['total_amount'],
                'start_date' => $data['start_date'],
                'due_day' => $data['due_day'],
                'status' => $data['status'],
                'notes' => $data['notes'],
            ];
            $this->db->prepare($sql)->execute($payload);
        }

        return (int)$this->db->lastInsertId();
    }

    public function updatePaid(int $debtId): void
    {
        $sql = "UPDATE debts d
                SET d.paid_amount = (
                    SELECT COALESCE(SUM(di.paid_amount),0) FROM debt_installments di WHERE di.debt_id = d.id
                ),
                d.status = IF((
                    SELECT COALESCE(SUM(di.paid_amount),0) FROM debt_installments di WHERE di.debt_id = d.id
                ) >= d.total_amount, 'paid', 'open')
                WHERE d.id = :id";
        $this->db->prepare($sql)->execute(['id' => $debtId]);
    }

    public function openTotal(int $userId): float
    {
        $this->applyMonthlyCharges($userId);

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount - paid_amount),0) AS total FROM debts WHERE user_id=:user_id AND status <> 'paid'");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return (float)($row['total'] ?? 0);
    }

    public function deletePendingOnly(int $debtId, int $userId): array
    {
        $debt = $this->find($debtId, $userId);
        if (!$debt) {
            return ['ok' => false, 'message' => 'Dívida não encontrada.'];
        }

        if ((float)$debt['paid_amount'] > 0) {
            return ['ok' => false, 'message' => 'Não é permitido excluir dívida com parcelas pagas.'];
        }

        $stmtPaid = $this->db->prepare("SELECT COUNT(*) AS qty
                                        FROM debt_installments
                                        WHERE debt_id = :debt_id
                                          AND (status = 'paid' OR paid_amount > 0)");
        $stmtPaid->execute(['debt_id' => $debtId]);
        $paidQty = (int)(($stmtPaid->fetch())['qty'] ?? 0);
        if ($paidQty > 0) {
            return ['ok' => false, 'message' => 'Não é permitido excluir dívida com uma ou mais parcelas pagas.'];
        }

        $this->db->prepare('DELETE FROM debts WHERE id = :id AND user_id = :user_id')
            ->execute(['id' => $debtId, 'user_id' => $userId]);

        return ['ok' => true];
    }

    public function applyMonthlyCharges(int $userId): void
    {
        if (!$this->supportsChargeColumns()) {
            return;
        }

        $sql = "SELECT id, user_id, total_amount, paid_amount, start_date, due_day,
                COALESCE(interest_mode, 'percent') AS interest_mode,
                COALESCE(interest_value, 0) AS interest_value,
                COALESCE(penalty_mode, 'percent') AS penalty_mode,
                COALESCE(penalty_value, 0) AS penalty_value,
                last_charge_month
                FROM debts
                WHERE user_id = :user_id
                  AND status <> 'paid'
                  AND (COALESCE(interest_value, 0) > 0 OR COALESCE(penalty_value, 0) > 0)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $debts = $stmt->fetchAll();
        if (empty($debts)) {
            return;
        }

        $installmentModel = new DebtInstallment();
        $today = date('Y-m-d');
        $currentMonth = date('Y-m');

        foreach ($debts as $debt) {
            try {
                $dueDay = (int)($debt['due_day'] ?? 1);
                $dueDay = max(1, min($dueDay, 31));

                $startMonth = date('Y-m', strtotime((string)$debt['start_date']));
                $lastChargeMonth = trim((string)($debt['last_charge_month'] ?? ''));
                $cursorMonth = $lastChargeMonth !== '' ? $this->nextMonthRef($lastChargeMonth) : $startMonth;

                while ($cursorMonth <= $currentMonth) {
                    $chargeDate = $this->buildChargeDate($cursorMonth, $dueDay);
                    if ($chargeDate > $today) {
                        break;
                    }

                    $remaining = max(0, round((float)$debt['total_amount'] - (float)$debt['paid_amount'], 2));
                    if ($remaining <= 0) {
                        $this->setLastChargeMonth((int)$debt['id'], $userId, $cursorMonth);
                        break;
                    }

                    $interestCharge = $this->calculateCharge(
                        $remaining,
                        $this->normalizeChargeMode((string)$debt['interest_mode']),
                        (float)$debt['interest_value']
                    );
                    $penaltyCharge = $this->calculateCharge(
                        $remaining,
                        $this->normalizeChargeMode((string)$debt['penalty_mode']),
                        (float)$debt['penalty_value']
                    );
                    $totalCharge = round($interestCharge + $penaltyCharge, 2);

                    $this->db->beginTransaction();
                    try {
                        if ($totalCharge > 0) {
                            $this->db->prepare('UPDATE debts SET total_amount = total_amount + :charge WHERE id = :id AND user_id = :user_id')
                                ->execute([
                                    'charge' => $totalCharge,
                                    'id' => (int)$debt['id'],
                                    'user_id' => $userId,
                                ]);

                            $installmentModel->appendChargeToMonth((int)$debt['id'], $userId, $cursorMonth, $dueDay, $totalCharge);
                            $debt['total_amount'] = (float)$debt['total_amount'] + $totalCharge;
                        }

                        $this->setLastChargeMonth((int)$debt['id'], $userId, $cursorMonth);
                        $this->db->commit();
                    } catch (Throwable $e) {
                        $this->db->rollBack();
                        throw $e;
                    }

                    $cursorMonth = $this->nextMonthRef($cursorMonth);
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    private function calculateCharge(float $baseAmount, string $mode, float $value): float
    {
        if ($value <= 0 || $baseAmount <= 0) {
            return 0.0;
        }

        if ($mode === 'fixed') {
            return round($value, 2);
        }

        return round(($baseAmount * $value) / 100, 2);
    }

    private function buildChargeDate(string $monthRef, int $dueDay): string
    {
        $firstDay = $monthRef . '-01';
        $maxDay = (int)date('t', strtotime($firstDay));
        $day = max(1, min($dueDay, $maxDay));
        return sprintf('%s-%02d', $monthRef, $day);
    }

    private function nextMonthRef(string $monthRef): string
    {
        return date('Y-m', strtotime($monthRef . '-01 +1 month'));
    }

    private function setLastChargeMonth(int $debtId, int $userId, string $monthRef): void
    {
        $this->db->prepare('UPDATE debts SET last_charge_month = :month_ref WHERE id = :id AND user_id = :user_id')
            ->execute([
                'month_ref' => $monthRef,
                'id' => $debtId,
                'user_id' => $userId,
            ]);
    }

    private function normalizeChargeMode(string $mode): string
    {
        return $mode === 'fixed' ? 'fixed' : 'percent';
    }

    private function supportsChargeColumns(): bool
    {
        if ($this->supportsChargeColumnsCache !== null) {
            return $this->supportsChargeColumnsCache;
        }

        try {
            $sql = "SELECT COUNT(*) AS qty
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'debts'
                      AND COLUMN_NAME IN (
                        'interest_mode',
                        'interest_value',
                        'penalty_mode',
                        'penalty_value',
                        'last_charge_month'
                      )";
            $row = $this->db->query($sql)->fetch();
            $this->supportsChargeColumnsCache = ((int)($row['qty'] ?? 0) === 5);
            return $this->supportsChargeColumnsCache;
        } catch (Throwable $e) {
            $this->supportsChargeColumnsCache = false;
            return false;
        }
    }
}
