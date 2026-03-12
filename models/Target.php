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
            return $this->emptyDashboardData();
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

        $activeObjectiveId = $activeObjective ? (int)($activeObjective['id'] ?? 0) : 0;
        $activeObjectiveProgress = $activeObjective ? (float)($activeObjective['progress_percent'] ?? 0.0) : 0.0;
        $today = date('Y-m-d');

        $activeTargetActions = $this->enrichActions(
            $this->fetchOpenActionsByTarget($userId, $targetId, 120),
            $activeObjectiveId > 0 ? $activeObjectiveId : null,
            $today
        );
        $secondaryActions = $this->enrichActions(
            $this->fetchOpenActionsOutsideTarget($userId, $targetId, 5),
            null,
            $today
        );
        $recentOpenActions = $this->enrichActions(
            $this->fetchRecentOpenActionsByTarget($userId, $targetId, 3),
            $activeObjectiveId > 0 ? $activeObjectiveId : null,
            $today
        );
        $completedRecently = $this->countCompletedRecentlyByTarget($userId, $targetId, 7);

        $priorityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'no_deadline' => 0,
        ];

        foreach ($activeTargetActions as $action) {
            $priority = (string)($action['priority'] ?? '');
            if (isset($priorityCounts[$priority])) {
                $priorityCounts[$priority]++;
            }
        }

        $nextActions = $this->sortActionsByPriority($activeTargetActions);
        $nextActions = array_slice($nextActions, 0, 10);

        $attentionActions = [];
        foreach ($nextActions as $action) {
            $priority = (string)($action['priority'] ?? '');
            $isActiveObjectiveAction = !empty($action['is_active_objective']);
            if (
                $priority === 'critical'
                || $priority === 'high'
                || ($isActiveObjectiveAction && $priority === 'medium')
            ) {
                $attentionActions[] = $action;
            }
            if (count($attentionActions) >= 8) {
                break;
            }
        }

        $executionSidebar = [];
        foreach ($nextActions as $action) {
            $priority = (string)($action['priority'] ?? '');
            $isImportant = in_array($priority, ['critical', 'high'], true)
                || (!empty($action['is_active_objective']) && $priority === 'medium')
                || ($priority === 'no_deadline' && (string)($action['status'] ?? '') === 'in_progress');
            if ($isImportant) {
                $executionSidebar[] = $action;
            }
            if (count($executionSidebar) >= 8) {
                break;
            }
        }

        $notifications = [];
        $notificationKeys = [];

        foreach ($nextActions as $action) {
            $priority = (string)($action['priority'] ?? '');
            if (!in_array($priority, ['critical', 'high', 'medium', 'low', 'no_deadline'], true)) {
                continue;
            }

            $kind = $this->notificationKindForPriority($priority);
            $this->appendNotification(
                $notifications,
                $notificationKeys,
                $this->makeNotification($action, $kind),
                14
            );
        }

        foreach ($recentOpenActions as $action) {
            $this->appendNotification(
                $notifications,
                $notificationKeys,
                $this->makeNotification($action, 'new_action'),
                14
            );
        }

        foreach ($nextActions as $action) {
            $notes = trim((string)($action['notes'] ?? ''));
            if ((string)($action['status'] ?? '') === 'pending' && $notes === '') {
                $this->appendNotification(
                    $notifications,
                    $notificationKeys,
                    $this->makeNotification($action, 'no_progress'),
                    14
                );
            }
            if (count($notifications) >= 14) {
                break;
            }
        }

        usort($notifications, function (array $left, array $right): int {
            $leftWeight = $this->priorityWeight((string)($left['priority'] ?? 'scheduled'));
            $rightWeight = $this->priorityWeight((string)($right['priority'] ?? 'scheduled'));
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            $leftDays = $left['days_to_deadline'];
            $rightDays = $right['days_to_deadline'];
            if ($leftDays === null && $rightDays === null) {
                return ((int)($left['action_id'] ?? 0)) <=> ((int)($right['action_id'] ?? 0));
            }
            if ($leftDays === null) {
                return 1;
            }
            if ($rightDays === null) {
                return -1;
            }
            if ((int)$leftDays !== (int)$rightDays) {
                return (int)$leftDays <=> (int)$rightDays;
            }

            return ((int)($left['action_id'] ?? 0)) <=> ((int)($right['action_id'] ?? 0));
        });

        $notifications = array_slice($notifications, 0, 12);
        $alertBadge = $priorityCounts['critical'] + $priorityCounts['high'] + $priorityCounts['medium'];

        $executionCenter = [
            'alert_badge' => $alertBadge,
            'priority_counts' => $priorityCounts,
            'notifications' => $notifications,
            'immediate_attention' => $attentionActions,
            'next_actions' => $nextActions,
            'sidebar_actions' => $executionSidebar,
            'secondary_actions' => array_slice($this->sortActionsByPriority($secondaryActions), 0, 5),
            'indicators' => [
                'pending' => $pendingActions,
                'overdue' => $priorityCounts['critical'],
                'due_3_days' => $priorityCounts['high'] + $priorityCounts['medium'],
                'completed_recently' => $completedRecently,
                'objective_progress' => $activeObjectiveProgress,
                'target_progress' => $progressPercent,
            ],
            'progress_summary' => [
                'progress_percent' => $progressPercent,
                'total_actions' => $totalActions,
                'done_actions' => $doneActions,
                'pending_actions' => $pendingActions,
                'overdue_actions' => $priorityCounts['critical'],
            ],
        ];

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
            'next_actions' => array_slice($nextActions, 0, 5),
            'objective_overdue' => $objectiveOverdue,
            'objective_remaining_days' => $objectiveRemainingDays,
            'execution_center' => $executionCenter,
        ];
    }

    public function executionAgendaData(int $userId, int $limit = 120): array
    {
        $limit = max(20, min($limit, 300));
        $today = date('Y-m-d');

        $activeTarget = $this->activeByUser($userId);
        $activeTargetId = $activeTarget ? (int)($activeTarget['id'] ?? 0) : 0;

        $activeObjective = null;
        if ($activeTargetId > 0) {
            $sqlObjective = "SELECT o.*,
                            DATE_ADD(COALESCE(o.start_date, CURDATE()), INTERVAL o.term_months MONTH) AS deadline_date
                            FROM objectives o
                            WHERE o.target_id = :target_id AND o.status = 'active'
                            ORDER BY o.id DESC
                            LIMIT 1";
            $stmtObjective = $this->db->prepare($sqlObjective);
            $stmtObjective->execute(['target_id' => $activeTargetId]);
            $activeObjective = $stmtObjective->fetch() ?: null;
        }

        $activeObjectiveId = $activeObjective ? (int)($activeObjective['id'] ?? 0) : 0;
        $rows = $this->fetchOpenActionsForAgenda($userId, $limit);

        $items = [];
        $summary = [
            'total' => 0,
            'overdue_count' => 0,
            'due_today_count' => 0,
            'due_3_days_count' => 0,
            'in_progress_count' => 0,
            'active_objective_count' => 0,
            'active_target_count' => 0,
            'pending_count' => 0,
        ];

        foreach ($rows as $row) {
            $plannedDate = isset($row['planned_date']) ? trim((string)$row['planned_date']) : '';
            $daysToDeadline = null;
            if ($plannedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedDate)) {
                $seconds = strtotime($plannedDate . ' 00:00:00') - strtotime($today . ' 00:00:00');
                $daysToDeadline = (int)floor($seconds / 86400);
            }

            $objectiveId = (int)($row['objective_id'] ?? 0);
            $targetId = (int)($row['target_id'] ?? 0);
            $priorityRank = $this->agendaPriorityRank(
                $plannedDate,
                $daysToDeadline,
                $objectiveId,
                $activeObjectiveId,
                $targetId,
                $activeTargetId
            );
            $priorityMeta = $this->agendaPriorityMeta($priorityRank);
            $status = (string)($row['status'] ?? 'pending');

            $item = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'decision_title' => (string)($row['decision_title'] ?? ''),
                'objective_title' => (string)($row['objective_title'] ?? ''),
                'target_title' => (string)($row['target_title'] ?? ''),
                'objective_id' => $objectiveId,
                'target_id' => $targetId,
                'planned_date' => $plannedDate !== '' ? $plannedDate : null,
                'status' => $status,
                'status_label' => $this->agendaStatusLabel($status),
                'days_to_deadline' => $daysToDeadline,
                'priority_rank' => $priorityRank,
                'priority_label' => $priorityMeta['label'],
                'priority_badge_class' => $priorityMeta['badge_class'],
                'urgency_level' => $this->agendaUrgencyLevel($priorityRank, $daysToDeadline),
                'urgency_text' => $this->agendaUrgencyText($priorityRank, $daysToDeadline, $plannedDate),
                'quick_url' => 'index.php?route=targets_show&id=' . $targetId,
                'is_active_objective' => $activeObjectiveId > 0 && $objectiveId === $activeObjectiveId,
                'is_active_target' => $activeTargetId > 0 && $targetId === $activeTargetId,
            ];

            $items[] = $item;
            $summary['total']++;

            if ($priorityRank === 1) {
                $summary['overdue_count']++;
            } elseif ($priorityRank === 2) {
                $summary['due_today_count']++;
            } elseif ($priorityRank === 3) {
                $summary['due_3_days_count']++;
            }

            if ($status === 'in_progress') {
                $summary['in_progress_count']++;
            } else {
                $summary['pending_count']++;
            }
            if ($item['is_active_objective']) {
                $summary['active_objective_count']++;
            }
            if ($item['is_active_target']) {
                $summary['active_target_count']++;
            }
        }

        $items = $this->sortAgendaItems($items);
        $focusItems = array_values(array_filter($items, static function (array $item): bool {
            return (int)($item['priority_rank'] ?? 6) <= 3 || (string)($item['status'] ?? '') === 'in_progress';
        }));

        return [
            'active_target' => $activeTarget,
            'active_objective' => $activeObjective,
            'summary' => $summary,
            'focus_items' => array_slice($focusItems, 0, 8),
            'items' => $items,
        ];
    }

    public function executionWeeklyScoreData(int $userId, int $weeks = 8): array
    {
        $weeks = max(4, min($weeks, 20));
        $today = date('Y-m-d');

        $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
        $currentWeekEnd = date('Y-m-d', strtotime($currentWeekStart . ' +6 days'));
        $historyStart = date('Y-m-d', strtotime($currentWeekStart . ' -' . (7 * ($weeks - 1)) . ' days'));

        $activeTarget = $this->activeByUser($userId);
        $activeTargetId = $activeTarget ? (int)($activeTarget['id'] ?? 0) : 0;

        $activeObjective = null;
        if ($activeTargetId > 0) {
            $sqlObjective = "SELECT o.*
                            FROM objectives o
                            WHERE o.target_id = :target_id AND o.status = 'active'
                            ORDER BY o.id DESC
                            LIMIT 1";
            $stmtObjective = $this->db->prepare($sqlObjective);
            $stmtObjective->execute(['target_id' => $activeTargetId]);
            $activeObjective = $stmtObjective->fetch() ?: null;
        }
        $activeObjectiveId = $activeObjective ? (int)($activeObjective['id'] ?? 0) : 0;

        $rows = $this->fetchActionsForWeeklyScore($userId, $historyStart, $currentWeekEnd);

        $history = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = date('Y-m-d', strtotime($currentWeekStart . ' -' . (7 * $i) . ' days'));
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            $referenceDate = $i === 0 ? $today : $weekEnd;

            $weekData = $this->buildWeeklyScoreForRange(
                $rows,
                $weekStart,
                $weekEnd,
                $referenceDate,
                $activeTargetId,
                $activeObjectiveId
            );
            $history[] = $weekData;
        }

        $currentWeek = !empty($history) ? $history[count($history) - 1] : $this->emptyWeeklyScoreWeek($currentWeekStart, $currentWeekEnd);
        $previousWeek = count($history) > 1
            ? $history[count($history) - 2]
            : $this->emptyWeeklyScoreWeek(
                date('Y-m-d', strtotime($currentWeekStart . ' -7 days')),
                date('Y-m-d', strtotime($currentWeekStart . ' -1 days'))
            );

        $delta = (int)$currentWeek['score'] - (int)$previousWeek['score'];
        $trend = $this->weeklyScoreTrend($delta);
        $message = $this->weeklyScoreMessage($currentWeek, $previousWeek, $activeObjectiveId);

        return [
            'active_target' => $activeTarget,
            'active_objective' => $activeObjective,
            'current_week' => $currentWeek,
            'previous_week' => $previousWeek,
            'comparison' => [
                'delta' => $delta,
                'trend' => $trend['id'],
                'trend_label' => $trend['label'],
                'trend_class' => $trend['class'],
                'message' => $message,
            ],
            'history' => $history,
        ];
    }

    public function activeObjectiveProgressSnapshot(int $userId, int $recentDays = 7): array
    {
        $recentDays = max(1, min($recentDays, 60));

        $activeTarget = $this->activeByUser($userId);
        if (!$activeTarget) {
            return [
                'has_active_objective' => false,
                'objective_id' => 0,
                'objective_title' => '',
                'open_actions' => 0,
                'recent_completed' => 0,
                'recent_days' => $recentDays,
                'is_stalled' => false,
            ];
        }

        $targetId = (int)($activeTarget['id'] ?? 0);
        $sqlObjective = "SELECT o.id, o.title
                         FROM objectives o
                         WHERE o.target_id = :target_id
                           AND o.status = 'active'
                         ORDER BY o.id DESC
                         LIMIT 1";
        $stmtObjective = $this->db->prepare($sqlObjective);
        $stmtObjective->execute(['target_id' => $targetId]);
        $activeObjective = $stmtObjective->fetch();
        if (!$activeObjective) {
            return [
                'has_active_objective' => false,
                'objective_id' => 0,
                'objective_title' => '',
                'open_actions' => 0,
                'recent_completed' => 0,
                'recent_days' => $recentDays,
                'is_stalled' => false,
            ];
        }

        $objectiveId = (int)($activeObjective['id'] ?? 0);
        $cutoff = date('Y-m-d', strtotime('-' . $recentDays . ' days'));

        $sqlOpen = "SELECT COUNT(*) AS qty
                    FROM actions a
                    INNER JOIN decisions d ON d.id = a.decision_id
                    INNER JOIN objectives o ON o.id = d.objective_id
                    INNER JOIN targets t ON t.id = o.target_id
                    WHERE t.user_id = :user_id
                      AND o.id = :objective_id
                      AND a.status IN ('pending', 'in_progress')
                      AND a.is_done = 0";
        $stmtOpen = $this->db->prepare($sqlOpen);
        $stmtOpen->execute([
            'user_id' => $userId,
            'objective_id' => $objectiveId,
        ]);
        $openRow = $stmtOpen->fetch();
        $openActions = (int)($openRow['qty'] ?? 0);

        $sqlCompleted = "SELECT COUNT(*) AS qty
                         FROM actions a
                         INNER JOIN decisions d ON d.id = a.decision_id
                         INNER JOIN objectives o ON o.id = d.objective_id
                         INNER JOIN targets t ON t.id = o.target_id
                         WHERE t.user_id = :user_id
                           AND o.id = :objective_id
                           AND (a.status = 'completed' OR a.is_done = 1)
                           AND a.completed_at IS NOT NULL
                           AND a.completed_at >= :cutoff";
        $stmtCompleted = $this->db->prepare($sqlCompleted);
        $stmtCompleted->execute([
            'user_id' => $userId,
            'objective_id' => $objectiveId,
            'cutoff' => $cutoff,
        ]);
        $completedRow = $stmtCompleted->fetch();
        $recentCompleted = (int)($completedRow['qty'] ?? 0);

        return [
            'has_active_objective' => true,
            'objective_id' => $objectiveId,
            'objective_title' => (string)($activeObjective['title'] ?? ''),
            'open_actions' => $openActions,
            'recent_completed' => $recentCompleted,
            'recent_days' => $recentDays,
            'is_stalled' => $openActions > 0 && $recentCompleted === 0,
        ];
    }

    private function emptyDashboardData(): array
    {
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
            'execution_center' => [
                'alert_badge' => 0,
                'priority_counts' => [
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'no_deadline' => 0,
                ],
                'notifications' => [],
                'immediate_attention' => [],
                'next_actions' => [],
                'sidebar_actions' => [],
                'secondary_actions' => [],
                'indicators' => [
                    'pending' => 0,
                    'overdue' => 0,
                    'due_3_days' => 0,
                    'completed_recently' => 0,
                    'objective_progress' => 0.0,
                    'target_progress' => 0.0,
                ],
                'progress_summary' => [
                    'progress_percent' => 0.0,
                    'total_actions' => 0,
                    'done_actions' => 0,
                    'pending_actions' => 0,
                    'overdue_actions' => 0,
                ],
            ],
        ];
    }

    private function fetchOpenActionsForAgenda(int $userId, int $limit): array
    {
        $limit = max(20, min($limit, 300));
        $sql = "SELECT a.id, a.title, a.planned_date, a.status,
                       d.id AS decision_id,
                       d.title AS decision_title,
                       o.id AS objective_id,
                       o.title AS objective_title,
                       t.id AS target_id,
                       t.title AS target_title
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND a.status IN ('pending', 'in_progress')
                  AND a.is_done = 0
                ORDER BY
                    CASE
                        WHEN a.planned_date IS NOT NULL AND a.planned_date < CURDATE() THEN 1
                        WHEN a.planned_date = CURDATE() THEN 2
                        WHEN a.planned_date > CURDATE() AND a.planned_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 3
                        ELSE 4
                    END ASC,
                    (a.status = 'in_progress') DESC,
                    (a.planned_date IS NULL) ASC,
                    a.planned_date ASC,
                    a.id ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function fetchActionsForWeeklyScore(int $userId, string $historyStart, string $historyEnd): array
    {
        $sql = "SELECT a.id, a.planned_date, a.completed_at, a.status, a.is_done,
                       o.id AS objective_id,
                       t.id AS target_id
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND a.status <> 'cancelled'
                  AND (
                        (a.planned_date IS NOT NULL AND a.planned_date <= :history_end)
                     OR (a.completed_at IS NOT NULL AND a.completed_at >= :history_start AND a.completed_at <= :history_end)
                  )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'history_start' => $historyStart,
            'history_end' => $historyEnd,
        ]);

        return $stmt->fetchAll();
    }

    private function buildWeeklyScoreForRange(
        array $rows,
        string $weekStart,
        string $weekEnd,
        string $referenceDate,
        int $activeTargetId,
        int $activeObjectiveId
    ): array {
        $plannedCount = 0;
        $completedCount = 0;
        $overdueOpenCount = 0;
        $completedActiveTargetCount = 0;
        $completedActiveObjectiveCount = 0;

        foreach ($rows as $row) {
            $plannedDate = $this->normalizeDateValue($row['planned_date'] ?? null);
            $completedDate = $this->normalizeDateValue($row['completed_at'] ?? null);
            $done = $this->isActionDone($row);
            $objectiveId = (int)($row['objective_id'] ?? 0);
            $targetId = (int)($row['target_id'] ?? 0);

            if ($plannedDate !== null && $plannedDate >= $weekStart && $plannedDate <= $weekEnd) {
                $plannedCount++;
            }

            $completedInWeek = $done && $completedDate !== null && $completedDate >= $weekStart && $completedDate <= $weekEnd;
            if ($completedInWeek) {
                $completedCount++;

                if ($activeTargetId > 0 && $targetId === $activeTargetId) {
                    $completedActiveTargetCount++;
                }
                if ($activeObjectiveId > 0 && $objectiveId === $activeObjectiveId) {
                    $completedActiveObjectiveCount++;
                }
            }

            if ($plannedDate !== null && $plannedDate < $referenceDate) {
                $doneByReference = $this->isActionDoneByReference($row, $referenceDate);
                if (!$doneByReference) {
                    $overdueOpenCount++;
                }
            }
        }

        $completionRate = $plannedCount > 0
            ? min(100.0, round(($completedCount / $plannedCount) * 100, 2))
            : ($completedCount > 0 ? 75.0 : 60.0);

        $targetBonus = min(12.0, $completedActiveTargetCount * 3.0);
        $objectiveBonus = min(18.0, $completedActiveObjectiveCount * 6.0);
        $overduePenalty = min(45.0, $overdueOpenCount * 5.0);
        $inactivityPenalty = ($completedCount === 0 && $plannedCount > 0) ? 10.0 : 0.0;

        $rawScore = $completionRate + $targetBonus + $objectiveBonus - $overduePenalty - $inactivityPenalty;
        $score = (int)round(max(0.0, min(100.0, $rawScore)));
        $classification = $this->weeklyScoreClassification($score);

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $this->weeklyLabel($weekStart, $weekEnd),
            'score' => $score,
            'classification_id' => $classification['id'],
            'classification_label' => $classification['label'],
            'classification_badge_class' => $classification['badge_class'],
            'planned_count' => $plannedCount,
            'completed_count' => $completedCount,
            'overdue_open_count' => $overdueOpenCount,
            'completed_active_target_count' => $completedActiveTargetCount,
            'completed_active_objective_count' => $completedActiveObjectiveCount,
            'completion_rate' => $completionRate,
            'target_bonus' => $targetBonus,
            'objective_bonus' => $objectiveBonus,
            'overdue_penalty' => $overduePenalty,
            'inactivity_penalty' => $inactivityPenalty,
        ];
    }

    private function emptyWeeklyScoreWeek(string $weekStart, string $weekEnd): array
    {
        $classification = $this->weeklyScoreClassification(0);

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $this->weeklyLabel($weekStart, $weekEnd),
            'score' => 0,
            'classification_id' => $classification['id'],
            'classification_label' => $classification['label'],
            'classification_badge_class' => $classification['badge_class'],
            'planned_count' => 0,
            'completed_count' => 0,
            'overdue_open_count' => 0,
            'completed_active_target_count' => 0,
            'completed_active_objective_count' => 0,
            'completion_rate' => 0.0,
            'target_bonus' => 0.0,
            'objective_bonus' => 0.0,
            'overdue_penalty' => 0.0,
            'inactivity_penalty' => 0.0,
        ];
    }

    private function weeklyLabel(string $weekStart, string $weekEnd): string
    {
        $startTs = strtotime($weekStart . ' 00:00:00');
        $endTs = strtotime($weekEnd . ' 00:00:00');
        if ($startTs === false || $endTs === false) {
            return $weekStart . ' - ' . $weekEnd;
        }
        return date('d/m', $startTs) . ' - ' . date('d/m', $endTs);
    }

    private function weeklyScoreClassification(int $score): array
    {
        if ($score >= 85) {
            return [
                'id' => 'excellent',
                'label' => 'Excelente',
                'badge_class' => 'bg-emerald-100 text-emerald-700',
            ];
        }
        if ($score >= 70) {
            return [
                'id' => 'good',
                'label' => 'Bom',
                'badge_class' => 'bg-blue-100 text-blue-700',
            ];
        }
        if ($score >= 50) {
            return [
                'id' => 'attention',
                'label' => 'Atencao',
                'badge_class' => 'bg-amber-100 text-amber-700',
            ];
        }
        return [
            'id' => 'critical',
            'label' => 'Critico',
            'badge_class' => 'bg-red-100 text-red-700',
        ];
    }

    private function weeklyScoreTrend(int $delta): array
    {
        if ($delta >= 5) {
            return [
                'id' => 'up',
                'label' => 'Evolucao positiva',
                'class' => 'text-emerald-700',
            ];
        }
        if ($delta <= -5) {
            return [
                'id' => 'down',
                'label' => 'Queda de consistencia',
                'class' => 'text-red-700',
            ];
        }
        return [
            'id' => 'stable',
            'label' => 'Estavel',
            'class' => 'text-slate-700',
        ];
    }

    private function weeklyScoreMessage(array $currentWeek, array $previousWeek, int $activeObjectiveId): string
    {
        $delta = (int)($currentWeek['score'] ?? 0) - (int)($previousWeek['score'] ?? 0);
        $overdueCount = (int)($currentWeek['overdue_open_count'] ?? 0);
        $completedCount = (int)($currentWeek['completed_count'] ?? 0);
        $completedObjectiveCount = (int)($currentWeek['completed_active_objective_count'] ?? 0);

        if ($overdueCount >= max(3, $completedCount + 1)) {
            return 'Atencao: muitas acoes atrasadas.';
        }
        if ($delta >= 5) {
            return 'Voce evoluiu em relacao a semana passada.';
        }
        if ($delta <= -5 && $activeObjectiveId > 0 && $completedObjectiveCount === 0) {
            return 'Seu foco no objetivo ativo caiu esta semana.';
        }

        $score = (int)($currentWeek['score'] ?? 0);
        if ($score >= 75) {
            return 'Boa semana de execucao.';
        }
        if ($score >= 55) {
            return 'Consistencia moderada: ajuste prioridades.';
        }
        return 'Semana critica de execucao: priorize reduzir atrasos.';
    }

    private function normalizeDateValue($value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) >= 10) {
            $candidate = substr($raw, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function isActionDone(array $row): bool
    {
        $status = (string)($row['status'] ?? '');
        if ($status === 'completed') {
            return true;
        }
        return (int)($row['is_done'] ?? 0) === 1;
    }

    private function isActionDoneByReference(array $row, string $referenceDate): bool
    {
        if (!$this->isActionDone($row)) {
            return false;
        }

        $completedDate = $this->normalizeDateValue($row['completed_at'] ?? null);
        if ($completedDate === null) {
            return true;
        }

        return $completedDate <= $referenceDate;
    }

    private function fetchOpenActionsByTarget(int $userId, int $targetId, int $limit): array
    {
        $limit = max(1, min($limit, 300));
        $sql = "SELECT a.id, a.title, a.planned_date, a.status, a.notes,
                       d.id AS decision_id,
                       d.title AS decision_title,
                       o.id AS objective_id,
                       o.title AS objective_title,
                       o.status AS objective_status,
                       t.id AS target_id,
                       t.title AS target_title
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND t.id = :target_id
                  AND a.status IN ('pending', 'in_progress')
                  AND a.is_done = 0
                ORDER BY (a.planned_date IS NULL) ASC, a.planned_date ASC, a.id ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $targetId,
        ]);

        return $stmt->fetchAll();
    }

    private function agendaPriorityRank(string $plannedDate, ?int $daysToDeadline, int $objectiveId, int $activeObjectiveId, int $targetId, int $activeTargetId): int
    {
        if ($plannedDate !== '' && $daysToDeadline !== null && $daysToDeadline < 0) {
            return 1;
        }
        if ($plannedDate !== '' && $daysToDeadline === 0) {
            return 2;
        }
        if ($plannedDate !== '' && $daysToDeadline !== null && $daysToDeadline > 0 && $daysToDeadline <= 3) {
            return 3;
        }
        if ($activeObjectiveId > 0 && $objectiveId === $activeObjectiveId) {
            return 4;
        }
        if ($activeTargetId > 0 && $targetId === $activeTargetId) {
            return 5;
        }
        return 6;
    }

    private function agendaPriorityMeta(int $priorityRank): array
    {
        $map = [
            1 => ['label' => 'Atrasada', 'badge_class' => 'bg-red-100 text-red-700'],
            2 => ['label' => 'Vence hoje', 'badge_class' => 'bg-orange-100 text-orange-700'],
            3 => ['label' => 'Ate 3 dias', 'badge_class' => 'bg-amber-100 text-amber-700'],
            4 => ['label' => 'Objetivo ativo', 'badge_class' => 'bg-indigo-100 text-indigo-700'],
            5 => ['label' => 'Alvo ativo', 'badge_class' => 'bg-blue-100 text-blue-700'],
            6 => ['label' => 'Demais pendentes', 'badge_class' => 'bg-slate-200 text-slate-700'],
        ];
        return $map[$priorityRank] ?? $map[6];
    }

    private function agendaUrgencyLevel(int $priorityRank, ?int $daysToDeadline): string
    {
        if ($priorityRank === 1) {
            return 'Critica';
        }
        if ($priorityRank === 2) {
            return 'Alta';
        }
        if ($priorityRank === 3) {
            return 'Media';
        }
        if ($priorityRank === 4 || $priorityRank === 5) {
            return 'Foco estrategico';
        }
        if ($daysToDeadline === null) {
            return 'Sem prazo';
        }
        return 'Baixa';
    }

    private function agendaUrgencyText(int $priorityRank, ?int $daysToDeadline, string $plannedDate): string
    {
        if ($priorityRank === 1) {
            $days = abs((int)$daysToDeadline);
            return $days <= 1 ? 'Atrasada desde ontem' : 'Atrasada ha ' . $days . ' dias';
        }
        if ($priorityRank === 2) {
            return 'Vence hoje';
        }
        if ($priorityRank === 3) {
            $days = (int)$daysToDeadline;
            return $days === 1 ? 'Vence amanha' : 'Vence em ' . $days . ' dias';
        }
        if ($priorityRank === 4) {
            return 'Contribui diretamente para o objetivo ativo';
        }
        if ($priorityRank === 5) {
            return 'Contribui para o alvo ativo';
        }
        if ($plannedDate === '') {
            return 'Sem prazo definido';
        }
        return 'Pendente sem urgencia imediata';
    }

    private function agendaStatusLabel(string $status): string
    {
        if ($status === 'in_progress') {
            return 'Em andamento';
        }
        if ($status === 'pending') {
            return 'Pendente';
        }
        return $status;
    }

    private function sortAgendaItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            $leftPriority = (int)($left['priority_rank'] ?? 6);
            $rightPriority = (int)($right['priority_rank'] ?? 6);
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftStatus = (string)($left['status'] ?? '');
            $rightStatus = (string)($right['status'] ?? '');
            if ($leftStatus !== $rightStatus) {
                if ($leftStatus === 'in_progress') {
                    return -1;
                }
                if ($rightStatus === 'in_progress') {
                    return 1;
                }
            }

            $leftDays = $left['days_to_deadline'];
            $rightDays = $right['days_to_deadline'];
            if ($leftDays === null && $rightDays !== null) {
                return 1;
            }
            if ($leftDays !== null && $rightDays === null) {
                return -1;
            }
            if ($leftDays !== null && $rightDays !== null && (int)$leftDays !== (int)$rightDays) {
                return (int)$leftDays <=> (int)$rightDays;
            }

            $leftDate = (string)($left['planned_date'] ?? '');
            $rightDate = (string)($right['planned_date'] ?? '');
            if ($leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            return ((int)($left['id'] ?? 0)) <=> ((int)($right['id'] ?? 0));
        });

        return $items;
    }

    private function fetchOpenActionsOutsideTarget(int $userId, int $targetId, int $limit): array
    {
        $limit = max(1, min($limit, 100));
        $sql = "SELECT a.id, a.title, a.planned_date, a.status, a.notes,
                       d.id AS decision_id,
                       d.title AS decision_title,
                       o.id AS objective_id,
                       o.title AS objective_title,
                       o.status AS objective_status,
                       t.id AS target_id,
                       t.title AS target_title
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND t.id <> :target_id
                  AND a.status IN ('pending', 'in_progress')
                  AND a.is_done = 0
                ORDER BY (a.planned_date IS NULL) ASC, a.planned_date ASC, a.id ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $targetId,
        ]);

        return $stmt->fetchAll();
    }

    private function fetchRecentOpenActionsByTarget(int $userId, int $targetId, int $limit): array
    {
        $limit = max(1, min($limit, 50));
        $sql = "SELECT a.id, a.title, a.planned_date, a.status, a.notes,
                       d.id AS decision_id,
                       d.title AS decision_title,
                       o.id AS objective_id,
                       o.title AS objective_title,
                       o.status AS objective_status,
                       t.id AS target_id,
                       t.title AS target_title
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND t.id = :target_id
                  AND a.status IN ('pending', 'in_progress')
                  AND a.is_done = 0
                ORDER BY a.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $targetId,
        ]);

        return $stmt->fetchAll();
    }

    private function countCompletedRecentlyByTarget(int $userId, int $targetId, int $days): int
    {
        $days = max(1, min($days, 60));
        $cutoffDate = date('Y-m-d', strtotime('-' . $days . ' days'));
        $sql = "SELECT COUNT(*) AS qty
                FROM actions a
                INNER JOIN decisions d ON d.id = a.decision_id
                INNER JOIN objectives o ON o.id = d.objective_id
                INNER JOIN targets t ON t.id = o.target_id
                WHERE t.user_id = :user_id
                  AND t.id = :target_id
                  AND (a.status = 'completed' OR a.is_done = 1)
                  AND a.completed_at IS NOT NULL
                  AND a.completed_at >= :cutoff_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'target_id' => $targetId,
            'cutoff_date' => $cutoffDate,
        ]);
        $row = $stmt->fetch();
        return (int)($row['qty'] ?? 0);
    }

    private function enrichActions(array $rows, ?int $activeObjectiveId, string $today): array
    {
        $enriched = [];
        foreach ($rows as $row) {
            $plannedDate = isset($row['planned_date']) ? trim((string)$row['planned_date']) : '';
            $daysToDeadline = null;
            if ($plannedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedDate)) {
                $seconds = strtotime($plannedDate . ' 00:00:00') - strtotime($today . ' 00:00:00');
                $daysToDeadline = (int)floor($seconds / 86400);
            }

            $priority = $this->resolvePriority($daysToDeadline, $plannedDate);
            $meta = $this->priorityMeta($priority);
            $objectiveId = (int)($row['objective_id'] ?? 0);
            $isActiveObjective = $activeObjectiveId !== null && $objectiveId > 0 && $objectiveId === $activeObjectiveId;

            $row['priority'] = $priority;
            $row['priority_label'] = $meta['label'];
            $row['priority_badge_class'] = $meta['badge_class'];
            $row['priority_border_class'] = $meta['border_class'];
            $row['days_to_deadline'] = $daysToDeadline;
            $row['urgency_text'] = $this->buildUrgencyText($priority, $daysToDeadline);
            $row['is_active_objective'] = $isActiveObjective;
            $row['action_url'] = 'index.php?route=targets_show&id=' . (int)($row['target_id'] ?? 0);
            $enriched[] = $row;
        }

        return $enriched;
    }

    private function resolvePriority($daysToDeadline, string $plannedDate): string
    {
        if ($plannedDate === '') {
            return 'no_deadline';
        }
        if ($daysToDeadline === null) {
            return 'scheduled';
        }
        if ($daysToDeadline < 0) {
            return 'critical';
        }
        if ($daysToDeadline === 0) {
            return 'high';
        }
        if ($daysToDeadline <= 3) {
            return 'medium';
        }
        if ($daysToDeadline <= 7) {
            return 'low';
        }

        return 'scheduled';
    }

    private function priorityMeta(string $priority): array
    {
        $map = [
            'critical' => [
                'label' => 'Critico',
                'badge_class' => 'bg-red-100 text-red-700',
                'border_class' => 'border-red-200',
            ],
            'high' => [
                'label' => 'Alta prioridade',
                'badge_class' => 'bg-orange-100 text-orange-700',
                'border_class' => 'border-orange-200',
            ],
            'medium' => [
                'label' => 'Media prioridade',
                'badge_class' => 'bg-amber-100 text-amber-700',
                'border_class' => 'border-amber-200',
            ],
            'low' => [
                'label' => 'Baixa prioridade',
                'badge_class' => 'bg-blue-100 text-blue-700',
                'border_class' => 'border-blue-200',
            ],
            'no_deadline' => [
                'label' => 'Sem prazo',
                'badge_class' => 'bg-slate-200 text-slate-700',
                'border_class' => 'border-slate-200',
            ],
            'scheduled' => [
                'label' => 'Planejada',
                'badge_class' => 'bg-emerald-100 text-emerald-700',
                'border_class' => 'border-emerald-200',
            ],
        ];

        return $map[$priority] ?? $map['scheduled'];
    }

    private function buildUrgencyText(string $priority, $daysToDeadline): string
    {
        if ($priority === 'critical') {
            $days = abs((int)$daysToDeadline);
            return $days <= 1 ? 'Atrasada desde ontem' : 'Atrasada ha ' . $days . ' dias';
        }
        if ($priority === 'high') {
            return 'Vence hoje';
        }
        if ($priority === 'medium' || $priority === 'low') {
            $days = max(0, (int)$daysToDeadline);
            return $days === 1 ? 'Vence amanha' : 'Vence em ' . $days . ' dias';
        }
        if ($priority === 'no_deadline') {
            return 'Sem prazo definido';
        }

        return 'Prazo planejado';
    }

    private function sortActionsByPriority(array $actions): array
    {
        usort($actions, function (array $left, array $right): int {
            $leftWeight = $this->priorityWeight((string)($left['priority'] ?? 'scheduled'));
            $rightWeight = $this->priorityWeight((string)($right['priority'] ?? 'scheduled'));
            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            $leftDays = $left['days_to_deadline'];
            $rightDays = $right['days_to_deadline'];
            if ($leftDays === null && $rightDays !== null) {
                return 1;
            }
            if ($leftDays !== null && $rightDays === null) {
                return -1;
            }
            if ($leftDays !== null && $rightDays !== null && (int)$leftDays !== (int)$rightDays) {
                return (int)$leftDays <=> (int)$rightDays;
            }

            $leftPlanned = (string)($left['planned_date'] ?? '');
            $rightPlanned = (string)($right['planned_date'] ?? '');
            if ($leftPlanned !== $rightPlanned) {
                return strcmp($leftPlanned, $rightPlanned);
            }

            return ((int)($left['id'] ?? 0)) <=> ((int)($right['id'] ?? 0));
        });

        return $actions;
    }

    private function priorityWeight(string $priority): int
    {
        $weights = [
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            'no_deadline' => 5,
            'scheduled' => 6,
        ];

        return $weights[$priority] ?? 6;
    }

    private function notificationKindForPriority(string $priority): string
    {
        $map = [
            'critical' => 'overdue',
            'high' => 'due_today',
            'medium' => 'due_soon',
            'low' => 'upcoming',
            'no_deadline' => 'no_deadline',
        ];

        return $map[$priority] ?? 'upcoming';
    }

    private function makeNotification(array $action, string $kind): array
    {
        $messages = [
            'overdue' => 'Acao atrasada',
            'due_today' => 'Acao vence hoje',
            'due_soon' => 'Acao vence em breve',
            'upcoming' => 'Acao com prazo proximo',
            'no_deadline' => 'Acao pendente sem prazo',
            'new_action' => 'Nova acao criada recentemente',
            'no_progress' => 'Acao sem progresso',
        ];

        return [
            'kind' => $kind,
            'message' => $messages[$kind] ?? 'Notificacao de execucao',
            'action_id' => (int)($action['id'] ?? 0),
            'action_title' => (string)($action['title'] ?? ''),
            'decision_title' => (string)($action['decision_title'] ?? ''),
            'objective_title' => (string)($action['objective_title'] ?? ''),
            'target_title' => (string)($action['target_title'] ?? ''),
            'planned_date' => $action['planned_date'] ?? null,
            'priority' => (string)($action['priority'] ?? 'scheduled'),
            'priority_label' => (string)($action['priority_label'] ?? 'Planejada'),
            'priority_badge_class' => (string)($action['priority_badge_class'] ?? 'bg-slate-200 text-slate-700'),
            'urgency_text' => (string)($action['urgency_text'] ?? ''),
            'action_url' => (string)($action['action_url'] ?? 'index.php?route=targets'),
            'days_to_deadline' => $action['days_to_deadline'] ?? null,
        ];
    }

    private function appendNotification(array &$notifications, array &$notificationKeys, array $notification, int $limit): void
    {
        if (count($notifications) >= $limit) {
            return;
        }

        $key = (string)($notification['kind'] ?? '') . ':' . (int)($notification['action_id'] ?? 0);
        if (isset($notificationKeys[$key])) {
            return;
        }

        $notificationKeys[$key] = true;
        $notifications[] = $notification;
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
