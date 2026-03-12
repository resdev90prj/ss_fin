<?php
require_once __DIR__ . '/Model.php';

class Objective extends Model
{
    public function byTarget(int $targetId, int $userId): array
    {
        $sql = "SELECT o.*,
                DATE_ADD(COALESCE(o.start_date, CURDATE()), INTERVAL o.term_months MONTH) AS deadline_date,
                COALESCE((SELECT COUNT(*) FROM decisions d WHERE d.objective_id = o.id), 0) AS decisions_count,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' THEN 1 ELSE 0 END)
                    FROM decisions d
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE d.objective_id = o.id
                ), 0) AS total_actions,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' AND (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END)
                    FROM decisions d
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE d.objective_id = o.id
                ), 0) AS done_actions
                FROM objectives o
                INNER JOIN targets t ON t.id = o.target_id
                WHERE o.target_id = :target_id
                  AND t.user_id = :user_id
                ORDER BY (o.status = 'active') DESC, o.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'target_id' => $targetId,
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
        $sql = "SELECT o.*, t.user_id
                FROM objectives o
                INNER JOIN targets t ON t.id = o.target_id
                WHERE o.id = :id AND t.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        if (!$this->targetBelongsToUser((int)$data['target_id'], $userId)) {
            return 0;
        }

        $status = $this->normalizeStatus($data['status'] ?? 'adjusted');

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveObjectives((int)$data['target_id'], null);
            }

            $sql = 'INSERT INTO objectives (target_id, title, description, status, start_date, term_months, notes)
                    VALUES (:target_id, :title, :description, :status, :start_date, :term_months, :notes)';
            $this->db->prepare($sql)->execute([
                'target_id' => $data['target_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $status,
                'start_date' => $data['start_date'],
                'term_months' => $data['term_months'],
                'notes' => $data['notes'],
            ]);

            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $current = $this->findForUser($id, $userId);
        if (!$current) {
            return false;
        }

        $status = $this->normalizeStatus($data['status'] ?? (string)$current['status']);

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveObjectives((int)$current['target_id'], $id);
            }

            $sql = "UPDATE objectives o
                    INNER JOIN targets t ON t.id = o.target_id
                    SET o.title = :title,
                        o.description = :description,
                        o.status = :status,
                        o.start_date = :start_date,
                        o.term_months = :term_months,
                        o.notes = :notes
                    WHERE o.id = :id AND t.user_id = :user_id";

            $ok = $this->db->prepare($sql)->execute([
                'id' => $id,
                'user_id' => $userId,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $status,
                'start_date' => $data['start_date'],
                'term_months' => $data['term_months'],
                'notes' => $data['notes'],
            ]);

            $this->db->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setStatus(int $id, int $userId, string $status): bool
    {
        $current = $this->findForUser($id, $userId);
        if (!$current) {
            return false;
        }

        $status = $this->normalizeStatus($status);

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveObjectives((int)$current['target_id'], $id);
            }

            $ok = $this->db->prepare('UPDATE objectives SET status = :status WHERE id = :id')
                ->execute([
                    'status' => $status,
                    'id' => $id,
                ]);

            $this->db->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id, int $userId): bool
    {
        $sql = "DELETE o
                FROM objectives o
                INNER JOIN targets t ON t.id = o.target_id
                WHERE o.id = :id AND t.user_id = :user_id";

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    private function clearOtherActiveObjectives(int $targetId, ?int $exceptId): void
    {
        $sql = 'UPDATE objectives SET status = "adjusted" WHERE target_id = :target_id AND status = "active"';
        $params = ['target_id' => $targetId];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $this->db->prepare($sql)->execute($params);
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['active', 'finished', 'adjusted', 'achieved'];
        return in_array($status, $allowed, true) ? $status : 'adjusted';
    }

    private function targetBelongsToUser(int $targetId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM targets WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'id' => $targetId,
            'user_id' => $userId,
        ]);
        return (bool)$stmt->fetch();
    }
}
