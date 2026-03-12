<?php
require_once __DIR__ . '/Model.php';

class DebtInstallment extends Model
{
    public function byDebt(int $debtId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM debt_installments WHERE debt_id = :debt_id ORDER BY installment_number ASC');
        $stmt->execute(['debt_id' => $debtId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO debt_installments (debt_id, installment_number, due_date, amount, paid_amount, paid_at, status)
                VALUES (:debt_id, :installment_number, :due_date, :amount, 0, NULL, :status)';
        return $this->db->prepare($sql)->execute($data);
    }

    public function pay(int $id, float $amount): bool
    {
        $sql = "UPDATE debt_installments
                SET paid_amount = paid_amount + :amount_add,
                    paid_at = CURDATE(),
                    status = IF((paid_amount + :amount_check) >= amount, 'paid', 'pending')
                WHERE id = :id";
        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'amount_add' => $amount,
            'amount_check' => $amount,
        ]);
    }

    public function refund(int $id, float $amount): bool
    {
        $sql = "UPDATE debt_installments
                SET paid_amount = GREATEST(paid_amount - :amount_sub, 0),
                    paid_at = IF(GREATEST(paid_amount - :amount_date, 0) > 0, paid_at, NULL),
                    status = IF(GREATEST(paid_amount - :amount_status, 0) >= amount, 'paid', 'pending')
                WHERE id = :id";
        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'amount_sub' => $amount,
            'amount_date' => $amount,
            'amount_status' => $amount,
        ]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM debt_installments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $sql = 'SELECT di.*, d.user_id, d.status AS debt_status
                FROM debt_installments di
                INNER JOIN debts d ON d.id = di.debt_id
                WHERE di.id = :id AND d.user_id = :user_id
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function appendChargeToMonth(int $debtId, int $userId, string $monthRef, int $dueDay, float $chargeAmount): void
    {
        if ($chargeAmount <= 0) {
            return;
        }

        $monthStart = $monthRef . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $sqlCurrent = "SELECT di.id
                       FROM debt_installments di
                       INNER JOIN debts d ON d.id = di.debt_id
                       WHERE di.debt_id = :debt_id
                         AND d.user_id = :user_id
                         AND di.status <> 'paid'
                         AND di.due_date BETWEEN :month_start AND :month_end
                       ORDER BY di.due_date ASC, di.installment_number ASC
                       LIMIT 1";
        $stmtCurrent = $this->db->prepare($sqlCurrent);
        $stmtCurrent->execute([
            'debt_id' => $debtId,
            'user_id' => $userId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);
        $current = $stmtCurrent->fetch();
        if ($current) {
            $this->db->prepare('UPDATE debt_installments SET amount = amount + :charge WHERE id = :id')
                ->execute(['charge' => $chargeAmount, 'id' => (int)$current['id']]);
            return;
        }

        $sqlOpen = "SELECT di.id
                    FROM debt_installments di
                    INNER JOIN debts d ON d.id = di.debt_id
                    WHERE di.debt_id = :debt_id
                      AND d.user_id = :user_id
                      AND di.status <> 'paid'
                    ORDER BY di.due_date DESC, di.installment_number DESC
                    LIMIT 1";
        $stmtOpen = $this->db->prepare($sqlOpen);
        $stmtOpen->execute(['debt_id' => $debtId, 'user_id' => $userId]);
        $open = $stmtOpen->fetch();
        if ($open) {
            $this->db->prepare('UPDATE debt_installments SET amount = amount + :charge WHERE id = :id')
                ->execute(['charge' => $chargeAmount, 'id' => (int)$open['id']]);
            return;
        }

        $stmtNumber = $this->db->prepare('SELECT COALESCE(MAX(installment_number), 0) + 1 AS next_number FROM debt_installments WHERE debt_id = :debt_id');
        $stmtNumber->execute(['debt_id' => $debtId]);
        $nextNumber = (int)(($stmtNumber->fetch())['next_number'] ?? 1);

        $day = max(1, min($dueDay, (int)date('t', strtotime($monthStart))));
        $dueDate = date('Y-m-d', strtotime(sprintf('%s-%02d', $monthRef, $day)));

        $this->create([
            'debt_id' => $debtId,
            'installment_number' => $nextNumber,
            'due_date' => $dueDate,
            'amount' => $chargeAmount,
            'status' => 'pending',
        ]);
    }

    public function deletePendingInstallment(int $id, int $userId): array
    {
        $installment = $this->findForUser($id, $userId);
        if (!$installment) {
            return ['ok' => false, 'message' => 'Parcela não encontrada.'];
        }

        $isPaidInstallment = ($installment['status'] === 'paid') || ((float)$installment['paid_amount'] > 0);
        if ($isPaidInstallment) {
            return ['ok' => false, 'message' => 'Somente parcelas pendentes podem ser excluídas.'];
        }

        $stmtPaidAny = $this->db->prepare("SELECT COUNT(*) AS qty
                                           FROM debt_installments di
                                           INNER JOIN debts d ON d.id = di.debt_id
                                           WHERE di.debt_id = :debt_id
                                             AND d.user_id = :user_id
                                             AND (di.status = 'paid' OR di.paid_amount > 0)");
        $stmtPaidAny->execute([
            'debt_id' => (int)$installment['debt_id'],
            'user_id' => $userId
        ]);
        $paidAny = (int)(($stmtPaidAny->fetch())['qty'] ?? 0);
        if ($paidAny > 0) {
            return ['ok' => false, 'message' => 'Não é permitido excluir parcela quando existe parcela paga nesta dívida.'];
        }

        $debtId = (int)$installment['debt_id'];
        $amount = (float)$installment['amount'];

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM debt_installments WHERE id = :id')->execute(['id' => $id]);

            $this->db->prepare('UPDATE debts
                                SET total_amount = GREATEST(total_amount - :amount, 0)
                                WHERE id = :debt_id AND user_id = :user_id')
                ->execute([
                    'amount' => $amount,
                    'debt_id' => $debtId,
                    'user_id' => $userId
                ]);

            $this->db->commit();
            return ['ok' => true, 'debt_id' => $debtId];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'message' => 'Falha ao excluir parcela: ' . $e->getMessage()];
        }
    }

    public function projectionSummaryByMonth(int $userId, string $month): array
    {
        $sql = "SELECT
                COUNT(*) AS installments_count,
                SUM(CASE WHEN (di.amount - di.paid_amount) > 0 THEN 1 ELSE 0 END) AS installments_open_count,
                COALESCE(SUM(di.amount), 0) AS total_scheduled,
                COALESCE(SUM(GREATEST(di.amount - di.paid_amount, 0)), 0) AS total_due
                FROM debt_installments di
                INNER JOIN debts d ON d.id = di.debt_id
                WHERE d.user_id = :user_id
                  AND DATE_FORMAT(di.due_date, '%Y-%m') = :month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'month' => $month]);
        $row = $stmt->fetch();

        return [
            'installments_count' => (int)($row['installments_count'] ?? 0),
            'installments_open_count' => (int)($row['installments_open_count'] ?? 0),
            'total_scheduled' => (float)($row['total_scheduled'] ?? 0),
            'total_due' => (float)($row['total_due'] ?? 0),
        ];
    }

    public function projectionDetailsByMonth(int $userId, string $month): array
    {
        $sql = "SELECT
                di.id,
                d.description AS debt_description,
                di.installment_number,
                di.due_date,
                di.amount,
                di.paid_amount,
                GREATEST(di.amount - di.paid_amount, 0) AS remaining_amount
                FROM debt_installments di
                INNER JOIN debts d ON d.id = di.debt_id
                WHERE d.user_id = :user_id
                  AND DATE_FORMAT(di.due_date, '%Y-%m') = :month
                ORDER BY di.due_date ASC, di.installment_number ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'month' => $month]);
        return $stmt->fetchAll();
    }

    public function projectionByRange(int $userId, string $startMonth, string $endMonth): array
    {
        $startDate = $startMonth . '-01';
        $endDate = date('Y-m-t', strtotime($endMonth . '-01'));

        $sql = "SELECT
                DATE_FORMAT(di.due_date, '%Y-%m') AS period,
                COALESCE(SUM(di.amount), 0) AS installments_due
                FROM debt_installments di
                INNER JOIN debts d ON d.id = di.debt_id
                WHERE d.user_id = :user_id
                  AND di.due_date BETWEEN :start_date AND :end_date
                GROUP BY DATE_FORMAT(di.due_date, '%Y-%m')
                ORDER BY period ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        return $stmt->fetchAll();
    }
}
