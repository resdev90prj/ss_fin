<?php
require_once __DIR__ . '/Model.php';

class Decision extends Model
{
    public function byObjective(int $objectiveId, int $userId): array
    {
        $sql = "SELECT d.*,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' THEN 1 ELSE 0 END)
                    FROM actions a
                    WHERE a.decision_id = d.id
                ), 0) AS total_actions,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' AND (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END)
                    FROM actions a
                    WHERE a.decision_id = d.id
                ), 0) AS done_actions
                FROM decisions d
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE d.objective_id = :objective_id
                  AND t.user_id = :user_id
                ORDER BY d.order_no ASC, d.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'objective_id' => $objectiveId,
            'user_id' => $userId,
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $total = (int)($row['total_actions'] ?? 0);
            $done = (int)($row['done_actions'] ?? 0);
            $row['progress_percent'] = $total > 0 ? round(($done / $total) * 100, 2) : 0.0;
        }

        return $rows;
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $sql = "SELECT d.*, o.target_id
                FROM decisions d
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE d.id = :id
                  AND t.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function countByObjective(int $objectiveId, int $userId): int
    {
        $sql = "SELECT COUNT(*) AS qty
                FROM decisions d
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE d.objective_id = :objective_id
                  AND t.user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'objective_id' => $objectiveId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return (int)($row['qty'] ?? 0);
    }

    public function create(int $userId, array $data): int
    {
        if (!$this->objectiveBelongsToUser((int)$data['objective_id'], $userId)) {
            return 0;
        }

        $orderNo = (int)($data['order_no'] ?? 0);
        if ($orderNo <= 0) {
            $orderNo = $this->nextOrderNo((int)$data['objective_id']);
        }

        $sql = 'INSERT INTO decisions (objective_id, title, description, order_no, status)
                VALUES (:objective_id, :title, :description, :order_no, :status)';
        $this->db->prepare($sql)->execute([
            'objective_id' => $data['objective_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'order_no' => $orderNo,
            'status' => $this->normalizeStatus($data['status'] ?? 'pending'),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $decision = $this->findForUser($id, $userId);
        if (!$decision) {
            return false;
        }

        $orderNo = (int)($data['order_no'] ?? 0);
        if ($orderNo <= 0) {
            $orderNo = (int)$decision['order_no'];
        }

        $sql = 'UPDATE decisions
                SET title = :title,
                    description = :description,
                    order_no = :order_no,
                    status = :status
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'],
            'order_no' => $orderNo,
            'status' => $this->normalizeStatus($data['status'] ?? (string)$decision['status']),
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $decision = $this->findForUser($id, $userId);
        if (!$decision) {
            return false;
        }

        return $this->db->prepare('DELETE FROM decisions WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function objectiveBelongsToUser(int $objectiveId, int $userId): bool
    {
        $sql = "SELECT o.id
                FROM objectives o
                INNER JOIN targets t ON t.id = o.target_id
                WHERE o.id = :objective_id
                  AND t.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'objective_id' => $objectiveId,
            'user_id' => $userId,
        ]);

        return (bool)$stmt->fetch();
    }

    private function nextOrderNo(int $objectiveId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(order_no), 0) + 1 AS next_order FROM decisions WHERE objective_id = :objective_id');
        $stmt->execute(['objective_id' => $objectiveId]);
        $row = $stmt->fetch();
        return (int)($row['next_order'] ?? 1);
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['pending', 'in_progress', 'done', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'pending';
    }
}