<?php
require_once __DIR__ . '/Model.php';

class Target extends Model
{
    public function allByUser(int $userId): array
    {
        $sql = "SELECT t.*,
                (
                    SELECT o.id
                    FROM objectives o
                    WHERE o.target_id = t.id AND o.status = 'active'
                    ORDER BY o.id DESC
                    LIMIT 1
                ) AS active_objective_id,
                (
                    SELECT o.title
                    FROM objectives o
                    WHERE o.target_id = t.id AND o.status = 'active'
                    ORDER BY o.id DESC
                    LIMIT 1
                ) AS active_objective_title,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' THEN 1 ELSE 0 END)
                    FROM objectives o
                    LEFT JOIN decisions d ON d.objective_id = o.id
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE o.target_id = t.id
                ), 0) AS total_actions,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' AND (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END)
                    FROM objectives o
                    LEFT JOIN decisions d ON d.objective_id = o.id
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE o.target_id = t.id
                ), 0) AS done_actions
                FROM targets t
                WHERE t.user_id = :user_id
                ORDER BY (t.status = 'active') DESC, t.created_at DESC, t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $this->hydrateProgress($row);
        }

        return $rows;
    }

    public function find(int $id, int $userId): ?array
    {
        $sql = "SELECT t.*,
                (
                    SELECT o.id
                    FROM objectives o
                    WHERE o.target_id = t.id AND o.status = 'active'
                    ORDER BY o.id DESC
                    LIMIT 1
                ) AS active_objective_id,
                (
                    SELECT o.title
                    FROM objectives o
                    WHERE o.target_id = t.id AND o.status = 'active'
                    ORDER BY o.id DESC
                    LIMIT 1
                ) AS active_objective_title,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' THEN 1 ELSE 0 END)
                    FROM objectives o
                    LEFT JOIN decisions d ON d.objective_id = o.id
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE o.target_id = t.id
                ), 0) AS total_actions,
                COALESCE((
                    SELECT SUM(CASE WHEN a.status <> 'cancelled' AND (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END)
                    FROM objectives o
                    LEFT JOIN decisions d ON d.objective_id = o.id
                    LEFT JOIN actions a ON a.decision_id = d.id
                    WHERE o.target_id = t.id
                ), 0) AS done_actions
                FROM targets t
                WHERE t.id = :id AND t.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $this->hydrateProgress($row);
        return $row;
    }

    public function create(array $data): int
    {
        $status = $this->normalizeStatus($data['status'] ?? 'paused');

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveTargets((int)$data['user_id'], null);
            }

            $sql = 'INSERT INTO targets (user_id, title, description, target_amount, status, start_date, expected_end_date, notes)
                    VALUES (:user_id, :title, :description, :target_amount, :status, :start_date, :expected_end_date, :notes)';

            $this->db->prepare($sql)->execute([
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'target_amount' => $data['target_amount'],
                'status' => $status,
                'start_date' => $data['start_date'],
                'expected_end_date' => $data['expected_end_date'],
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
        $status = $this->normalizeStatus($data['status'] ?? 'paused');

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveTargets($userId, $id);
            }

            $sql = 'UPDATE targets
                    SET title = :title,
                        description = :description,
                        target_amount = :target_amount,
                        status = :status,
                        start_date = :start_date,
                        expected_end_date = :expected_end_date,
                        notes = :notes
                    WHERE id = :id AND user_id = :user_id';

            $ok = $this->db->prepare($sql)->execute([
                'id' => $id,
                'user_id' => $userId,
                'title' => $data['title'],
                'description' => $data['description'],
                'target_amount' => $data['target_amount'],
                'status' => $status,
                'start_date' => $data['start_date'],
                'expected_end_date' => $data['expected_end_date'],
                'notes' => $data['notes'],
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
        return $this->db->prepare('DELETE FROM targets WHERE id = :id AND user_id = :user_id')
            ->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function setStatus(int $id, int $userId, string $status): bool
    {
        $status = $this->normalizeStatus($status);

        $this->db->beginTransaction();
        try {
            if ($status === 'active') {
                $this->clearOtherActiveTargets($userId, $id);
            }

            $ok = $this->db->prepare('UPDATE targets SET status = :status WHERE id = :id AND user_id = :user_id')
                ->execute([
                    'status' => $status,
                    'id' => $id,
                    'user_id' => $userId,
                ]);

            $this->db->commit();
            return $ok;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function activeByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM targets WHERE user_id = :user_id AND status = "active" ORDER BY id DESC LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $full = $this->find((int)$row['id'], $userId);
        return $full ?: null;
    }

    public function dashboardData(int $userId): array
    {
        $activeTarget = $this->activeByUser($userId);
        if (!$activeTarget) {
            return [
                'active_target' => null,
                'active_objective' => null,
                'pending_actions' => 0,
                'done_actions' => 0,
                'total_actions' => 0,
                'progress_percent' => 0.0,
                'next_actions' => [],
                'objective_overdue' => false,
                'objective_remaining_days' => null,
            ];
        }

        $targetId = (int)$activeTarget['id'];

        $sqlObjective = "SELECT o.*,
                        DATE_ADD(COALESCE(o.start_date, CURDATE()), INTERVAL o.term_months MONTH) AS deadline_date,
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
                        WHERE o.target_id = :target_id AND o.status = 'active'
                        ORDER BY o.id DESC
                        LIMIT 1";
        $stmtObjective = $this->db->prepare($sqlObjective);
        $stmtObjective->execute(['target_id' => $targetId]);
        $activeObjective = $stmtObjective->fetch() ?: null;

        if ($activeObjective) {
            $objectiveTotal = (int)($activeObjective['total_actions'] ?? 0);
            $objectiveDone = (int)($activeObjective['done_actions'] ?? 0);
            $activeObjective['progress_percent'] = $objectiveTotal > 0
                ? round(($objectiveDone / $objectiveTotal) * 100, 2)
                : 0.0;
        }

        $sqlCounters = "SELECT
                        COALESCE(SUM(CASE WHEN a.status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS total_actions,
                        COALESCE(SUM(CASE WHEN a.status <> 'cancelled' AND (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END), 0) AS done_actions,
                        COALESCE(SUM(CASE WHEN a.status <> 'cancelled' AND NOT (a.is_done = 1 OR a.status = 'completed') THEN 1 ELSE 0 END), 0) AS pending_actions
                        FROM objectives o
                        LEFT JOIN decisions d ON d.objective_id = o.id
                        LEFT JOIN actions a ON a.decision_id = d.id
                        WHERE o.target_id = :target_id";
        $stmtCounters = $this->db->prepare($sqlCounters);
        $stmtCounters->execute(['target_id' => $targetId]);
        $counters = $stmtCounters->fetch() ?: ['total_actions' => 0, 'done_actions' => 0, 'pending_actions' => 0];

        $totalActions = (int)($counters['total_actions'] ?? 0);
        $doneActions = (int)($counters['done_actions'] ?? 0);
        $pendingActions = (int)($counters['pending_actions'] ?? 0);
        $progressPercent = $totalActions > 0 ? round(($doneActions / $totalActions) * 100, 2) : 0.0;

        $sqlNext = "SELECT a.id, a.title, a.planned_date, a.status,
                    d.title AS decision_title,
                    o.title AS objective_title
                    FROM actions a
                    INNER JOIN decisions d ON d.id = a.decision_id
                    INNER JOIN objectives o ON o.id = d.objective_id
                    WHERE o.target_id = :target_id
                      AND a.status IN ('pending', 'in_progress')
                      AND a.is_done = 0
                    ORDER BY (a.planned_date IS NULL) ASC, a.planned_date ASC, a.id ASC
                    LIMIT 5";
        $stmtNext = $this->db->prepare($sqlNext);
        $stmtNext->execute(['target_id' => $targetId]);
        $nextActions = $stmtNext->fetchAll();

        $objectiveOverdue = false;
        $objectiveRemainingDays = null;

        if ($activeObjective && !empty($activeObjective['deadline_date'])) {
            $deadline = (string)$activeObjective['deadline_date'];
            $today = date('Y-m-d');

            $objectiveOverdue = $deadline < $today;

            $diffSeconds = strtotime($deadline . ' 00:00:00') - strtotime($today . ' 00:00:00');
            $objectiveRemainingDays = (int)floor($diffSeconds / 86400);
        }

        return [
            'active_target' => $activeTarget,
            'active_objective' => $activeObjective,
            'pending_actions' => $pendingActions,
            'done_actions' => $doneActions,
            'total_actions' => $totalActions,
            'progress_percent' => $progressPercent,
            'next_actions' => $nextActions,
            'objective_overdue' => $objectiveOverdue,
            'objective_remaining_days' => $objectiveRemainingDays,
        ];
    }

    private function clearOtherActiveTargets(int $userId, ?int $exceptId): void
    {
        $sql = 'UPDATE targets SET status = "paused" WHERE user_id = :user_id AND status = "active"';
        $params = ['user_id' => $userId];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $this->db->prepare($sql)->execute($params);
    }

    private function hydrateProgress(array &$row): void
    {
        $total = (int)($row['total_actions'] ?? 0);
        $done = (int)($row['done_actions'] ?? 0);

        $row['total_actions'] = $total;
        $row['done_actions'] = $done;
        $row['progress_percent'] = $total > 0 ? round(($done / $total) * 100, 2) : 0.0;
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['active', 'achieved', 'paused', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'paused';
    }
}