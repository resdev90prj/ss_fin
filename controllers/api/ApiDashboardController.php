<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Debt.php';
require_once api_project_root() . '/models/DebtInstallment.php';
require_once api_project_root() . '/models/Target.php';

class ApiDashboardController
{
    public function summary(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $month = api_normalize_month($_GET['month'] ?? null);
        $monthDate = $month . '-01';

        $prevMonth = date('Y-m', strtotime($monthDate . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($monthDate . ' +1 month'));
        $startMonth = date('Y-m', strtotime($monthDate . ' -5 months'));
        $todayMonth = date('Y-m');

        $transactionModel = new Transaction();
        $debtModel = new Debt();
        $installmentModel = new DebtInstallment();
        $targetModel = new Target();
        $planningEnabled = has_current_module_access('planning');

        $summary = $this->safeCall(
            static fn(): array => $transactionModel->summaryMonth($userId, $month),
            ['incomes' => 0, 'expenses' => 0, 'withdrawals' => 0],
            'Transaction::summaryMonth',
            $userId
        );
        $balance = (float)$this->safeCall(
            static fn(): float => $transactionModel->balanceTotal($userId),
            0.0,
            'Transaction::balanceTotal',
            $userId
        );
        $debtsOpen = (float)$this->safeCall(
            static fn(): float => $debtModel->openTotal($userId),
            0.0,
            'Debt::openTotal',
            $userId
        );
        $expensesByCategory = $this->safeCall(
            static fn(): array => $transactionModel->expensesByCategoryMonth($userId, $month),
            [],
            'Transaction::expensesByCategoryMonth',
            $userId
        );
        $installmentProjection = $this->safeCall(
            static fn(): array => $installmentModel->projectionSummaryByMonth($userId, $month),
            [
                'installments_count' => 0,
                'installments_open_count' => 0,
                'total_scheduled' => 0.0,
                'total_due' => 0.0,
            ],
            'DebtInstallment::projectionSummaryByMonth',
            $userId
        );
        $installmentDetails = $this->safeCall(
            static fn(): array => $installmentModel->projectionDetailsByMonth($userId, $month),
            [],
            'DebtInstallment::projectionDetailsByMonth',
            $userId
        );
        $planning = $planningEnabled
            ? $this->safeCall(
                static fn(): array => $targetModel->dashboardData($userId),
                $this->defaultPlanningData(),
                'Target::dashboardData',
                $userId
            )
            : $this->defaultPlanningData();
        $agenda = $planningEnabled
            ? $this->safeCall(
                static fn(): array => $targetModel->executionAgendaData($userId, 120),
                $this->defaultAgendaData(),
                'Target::executionAgendaData',
                $userId
            )
            : $this->defaultAgendaData();
        $weeklyScore = $planningEnabled
            ? $this->safeCall(
                static fn(): array => $targetModel->executionWeeklyScoreData($userId, 8),
                $this->defaultWeeklyScoreData(),
                'Target::executionWeeklyScoreData',
                $userId
            )
            : $this->defaultWeeklyScoreData();

        $planning['enabled'] = $planningEnabled;
        $agenda['enabled'] = $planningEnabled;
        $weeklyScore['enabled'] = $planningEnabled;
        $transactionsEvolution = $this->safeCall(
            static fn(): array => $transactionModel->monthlyEvolutionRange($userId, $startMonth, $month),
            [],
            'Transaction::monthlyEvolutionRange',
            $userId
        );
        $installmentsEvolution = $this->safeCall(
            static fn(): array => $installmentModel->projectionByRange($userId, $startMonth, $month),
            [],
            'DebtInstallment::projectionByRange',
            $userId
        );

        $projectedReceivable = (float)($summary['incomes'] ?? 0);
        $projectedPayable = (float)($summary['expenses'] ?? 0)
            + (float)($summary['withdrawals'] ?? 0)
            + (float)($installmentProjection['total_scheduled'] ?? 0);
        $projectedNet = $projectedReceivable - $projectedPayable;
        $timelineContext = $month > $todayMonth ? 'future' : ($month < $todayMonth ? 'past' : 'current');

        api_json_response(true, 'Resumo do dashboard carregado.', [
            'month' => $month,
            'month_label' => api_format_month_label($month),
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'timeline_context' => $timelineContext,
            'summary' => $summary,
            'balance' => $balance,
            'debts_open' => $debtsOpen,
            'expenses_by_category' => $expensesByCategory,
            'evolution' => $this->buildEvolution($transactionsEvolution, $installmentsEvolution, $startMonth, $month),
            'installment_projection' => $installmentProjection,
            'installment_details' => $installmentDetails,
            'projected_receivable' => $projectedReceivable,
            'projected_payable' => $projectedPayable,
            'projected_net' => $projectedNet,
            'planning' => $planning,
            'agenda' => $agenda,
            'weekly_score' => $weeklyScore,
            'feature_flags' => [
                'planning_enabled' => $planningEnabled,
            ],
            'privacy' => [
                'storage_key' => 'dashboard_privacy_mode',
                'default_enabled' => false,
            ],
        ]);
    }

    private function safeCall(callable $callback, mixed $fallback, string $operation, int $userId): mixed
    {
        try {
            $result = $callback();
            return $result ?? $fallback;
        } catch (Throwable $throwable) {
            error_log(sprintf(
                '[api-dashboard] %s failed for user_id=%d: %s',
                $operation,
                $userId,
                $throwable->getMessage()
            ));

            return $fallback;
        }
    }

    private function buildEvolution(array $transactions, array $installments, string $startMonth, string $endMonth): array
    {
        $transactionsMap = [];
        foreach ($transactions as $row) {
            $transactionsMap[(string)$row['period']] = [
                'incomes' => (float)($row['incomes'] ?? 0),
                'expenses' => (float)($row['expenses'] ?? 0),
            ];
        }

        $installmentsMap = [];
        foreach ($installments as $row) {
            $installmentsMap[(string)$row['period']] = (float)($row['installments_due'] ?? 0);
        }

        $items = [];
        $cursor = strtotime($startMonth . '-01');
        $end = strtotime($endMonth . '-01');

        while ($cursor <= $end) {
            $period = date('Y-m', $cursor);
            $items[] = [
                'period' => $period,
                'incomes' => $transactionsMap[$period]['incomes'] ?? 0.0,
                'expenses' => $transactionsMap[$period]['expenses'] ?? 0.0,
                'installments_due' => $installmentsMap[$period] ?? 0.0,
            ];

            $cursor = strtotime('+1 month', $cursor);
        }

        return $items;
    }

    private function defaultPlanningData(): array
    {
        return [
            'enabled' => false,
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

    private function defaultAgendaData(): array
    {
        return [
            'enabled' => false,
            'active_target' => null,
            'active_objective' => null,
            'summary' => [
                'total' => 0,
                'overdue_count' => 0,
                'due_today_count' => 0,
                'due_3_days_count' => 0,
                'in_progress_count' => 0,
                'active_objective_count' => 0,
                'active_target_count' => 0,
                'pending_count' => 0,
            ],
            'focus_items' => [],
            'items' => [],
        ];
    }

    private function defaultWeeklyScoreData(): array
    {
        return [
            'enabled' => false,
            'active_target' => null,
            'active_objective' => null,
            'current_week' => [
                'week_start' => date('Y-m-d', strtotime('monday this week')),
                'week_end' => date('Y-m-d', strtotime('sunday this week')),
                'week_label' => '',
                'score' => 0,
                'classification_id' => 'critical',
                'classification_label' => 'Critico',
                'classification_badge_class' => 'bg-red-100 text-red-700',
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
            ],
            'previous_week' => [
                'week_start' => date('Y-m-d', strtotime('monday this week -7 days')),
                'week_end' => date('Y-m-d', strtotime('sunday this week -7 days')),
                'week_label' => '',
                'score' => 0,
                'classification_id' => 'critical',
                'classification_label' => 'Critico',
                'classification_badge_class' => 'bg-red-100 text-red-700',
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
            ],
            'comparison' => [
                'delta' => 0,
                'trend' => 'stable',
                'trend_label' => 'Estavel',
                'trend_class' => 'text-slate-700',
                'message' => 'Score semanal indisponivel no momento.',
            ],
            'history' => [],
        ];
    }
}
