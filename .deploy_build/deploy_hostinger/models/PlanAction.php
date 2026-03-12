<?php
require_once __DIR__ . '/Model.php';

class PlanAction extends Model
{
    public function byDecision(int $decisionId, int $userId): array
    {
        $sql = "SELECT a.*
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE a.decision_id = :decision_id
                  AND t.user_id = :user_id
                ORDER BY (a.status = 'completed') ASC, (a.planned_date IS NULL) ASC, a.planned_date ASC, a.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'decision_id' => $decisionId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $sql = "SELECT a.*, d.objective_id, o.target_id
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE a.id = :id
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

    public function create(int $userId, array $data): int
    {
        if (!$this->decisionBelongsToUser((int)$data['decision_id'], $userId)) {
            return 0;
        }

        $status = $this->normalizeStatus($data['status'] ?? 'pending');
        $isDone = !empty($data['is_done']) || $status === 'completed';
        if ($isDone) {
            $status = 'completed';
        }

        $completedAt = $isDone
            ? (!empty($data['completed_at']) ? $data['completed_at'] : date('Y-m-d'))
            : null;

        $sql = 'INSERT INTO actions (decision_id, title, description, planned_date, status, is_done, completed_at, notes)
                VALUES (:decision_id, :title, :description, :planned_date, :status, :is_done, :completed_at, :notes)';

        $this->db->prepare($sql)->execute([
            'decision_id' => $data['decision_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'planned_date' => $data['planned_date'],
            'status' => $status,
            'is_done' => $isDone ? 1 : 0,
            'completed_at' => $completedAt,
            'notes' => $data['notes'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $action = $this->findForUser($id, $userId);
        if (!$action) {
            return false;
        }

        $status = $this->normalizeStatus($data['status'] ?? (string)$action['status']);
        $isDone = !empty($data['is_done']) || $status === 'completed';
        if ($isDone) {
            $status = 'completed';
        }

        $completedAt = $isDone
            ? (!empty($data['completed_at']) ? $data['completed_at'] : ((string)($action['completed_at'] ?? '') !== '' ? $action['completed_at'] : date('Y-m-d')))
            : null;

        $sql = 'UPDATE actions
                SET title = :title,
                    description = :description,
                    planned_date = :planned_date,
                    status = :status,
                    is_done = :is_done,
                    completed_at = :completed_at,
                    notes = :notes
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'],
            'planned_date' => $data['planned_date'],
            'status' => $status,
            'is_done' => $isDone ? 1 : 0,
            'completed_at' => $completedAt,
            'notes' => $data['notes'],
        ]);
    }

    public function markDone(int $id, int $userId, bool $done): bool
    {
        $action = $this->findForUser($id, $userId);
        if (!$action) {
            return false;
        }

        return $this->db->prepare('UPDATE actions
                                   SET is_done = :is_done,
                                       status = :status,
                                       completed_at = :completed_at
                                   WHERE id = :id')
            ->execute([
                'id' => $id,
                'is_done' => $done ? 1 : 0,
                'status' => $done ? 'completed' : 'pending',
                'completed_at' => $done ? date('Y-m-d') : null,
            ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $action = $this->findForUser($id, $userId);
        if (!$action) {
            return false;
        }

        return $this->db->prepare('DELETE FROM actions WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function decisionBelongsToUser(int $decisionId, int $userId): bool
    {
        $sql = "SELECT d.id
                FROM decisions d
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE d.id = :decision_id
                  AND t.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'decision_id' => $decisionId,
            'user_id' => $userId,
        ]);

        return (bool)$stmt->fetch();
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['pending', 'in_progress', 'completed', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'pending';
    }
}
