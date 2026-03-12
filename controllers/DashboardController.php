<?php
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Debt.php';
require_once __DIR__ . '/../models/DebtInstallment.php';
require_once __DIR__ . '/../models/Target.php';

class DashboardController
{
    public function index(): void
    {
        $userId = current_user_id();
        if ($userId === null || $userId <= 0) {
            flash('error', 'Sessao invalida. Faca login novamente.');
            redirect('index.php?route=login');
        }

        $month = $this->resolveMonth($_GET['month'] ?? null);
        $monthDate = $month . '-01';

        $prevMonth = date('Y-m', strtotime($monthDate . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($monthDate . ' +1 month'));
        $startMonth = date('Y-m', strtotime($monthDate . ' -5 months'));
        $endMonth = $month;

        $transactionModel = new Transaction();
        $debtModel = new Debt();
        $installmentModel = new DebtInstallment();
        $targetModel = new Target();

        $summary = $this->safeDashboardCall(
            static fn(): array => $transactionModel->summaryMonth($userId, $month),
            ['incomes' => 0, 'expenses' => 0, 'withdrawals' => 0],
            'Transaction::summaryMonth',
            $userId
        );
        $balance = (float)$this->safeDashboardCall(
            static fn(): float => $transactionModel->balanceTotal($userId),
            0.0,
            'Transaction::balanceTotal',
            $userId
        );
        $debtsOpen = (float)$this->safeDashboardCall(
            static fn(): float => $debtModel->openTotal($userId),
            0.0,
            'Debt::openTotal',
            $userId
        );
        $expensesByCategory = $this->safeDashboardCall(
            static fn(): array => $transactionModel->expensesByCategoryMonth($userId, $month),
            [],
            'Transaction::expensesByCategoryMonth',
            $userId
        );

        $installmentProjection = $this->safeDashboardCall(
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
        $installmentDetails = $this->safeDashboardCall(
            static fn(): array => $installmentModel->projectionDetailsByMonth($userId, $month),
            [],
            'DebtInstallment::projectionDetailsByMonth',
            $userId
        );
        $planningData = $this->safeDashboardCall(
            static fn(): array => $targetModel->dashboardData($userId),
            $this->defaultPlanningData(),
            'Target::dashboardData',
            $userId
        );
        $agendaData = $this->safeDashboardCall(
            static fn(): array => $targetModel->executionAgendaData($userId, 120),
            $this->defaultAgendaData(),
            'Target::executionAgendaData',
            $userId
        );
        $weeklyScoreData = $this->safeDashboardCall(
            static fn(): array => $targetModel->executionWeeklyScoreData($userId, 8),
            $this->defaultWeeklyScoreData(),
            'Target::executionWeeklyScoreData',
            $userId
        );
        $transactionsEvolution = $this->safeDashboardCall(
            static fn(): array => $transactionModel->monthlyEvolutionRange($userId, $startMonth, $endMonth),
            [],
            'Transaction::monthlyEvolutionRange',
            $userId
        );
        $installmentsEvolution = $this->safeDashboardCall(
            static fn(): array => $installmentModel->projectionByRange($userId, $startMonth, $endMonth),
            [],
            'DebtInstallment::projectionByRange',
            $userId
        );
        $evolution = $this->buildEvolution(
            $transactionsEvolution,
            $installmentsEvolution,
            $startMonth,
            $endMonth
        );

        $projectedReceivable = (float)$summary['incomes'];
        $projectedPayable = (float)$summary['expenses'] + (float)$summary['withdrawals'] + (float)$installmentProjection['total_scheduled'];
        $projectedNet = $projectedReceivable - $projectedPayable;

        $todayMonth = date('Y-m');
        $timelineContext = $month > $todayMonth ? 'future' : ($month < $todayMonth ? 'past' : 'current');

        view('dashboard/index', [
            'title' => 'Dashboard',
            'summary' => $summary,
            'balance' => $balance,
            'debtsOpen' => $debtsOpen,
            'expensesByCategory' => $expensesByCategory,
            'evolution' => $evolution,
            'month' => $month,
            'monthLabel' => $this->formatMonthLabel($month),
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'installmentProjection' => $installmentProjection,
            'installmentDetails' => $installmentDetails,
            'projectedReceivable' => $projectedReceivable,
            'projectedPayable' => $projectedPayable,
            'projectedNet' => $projectedNet,
            'timelineContext' => $timelineContext,
            'planningData' => $planningData,
            'agendaData' => $agendaData,
            'weeklyScoreData' => $weeklyScoreData,
        ]);
    }

    public function agendaExecution(): void
    {
        $userId = current_user_id();
        if ($userId === null || $userId <= 0) {
            flash('error', 'Sessao invalida. Faca login novamente.');
            redirect('index.php?route=login');
        }

        $limit = (int)($_GET['limit'] ?? 200);
        $limit = max(50, min($limit, 300));

        $targetModel = new Target();
        $agendaData = $this->safeDashboardCall(
            static fn(): array => $targetModel->executionAgendaData($userId, $limit),
            $this->defaultAgendaData(),
            'Target::executionAgendaData',
            $userId
        );

        view('dashboard/agenda_execution', [
            'title' => 'Agenda de Execucao',
            'agendaData' => $agendaData,
            'limit' => $limit,
        ]);
    }

    private function resolveMonth(?string $input): string
    {
        $month = trim((string)$input);
        if ($month !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }
        return date('Y-m');
    }

    private function formatMonthLabel(string $month): string
    {
        static $months = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Marco',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];

        $year = substr($month, 0, 4);
        $monthNumber = substr($month, 5, 2);
        return ($months[$monthNumber] ?? $monthNumber) . '/' . $year;
    }

    private function buildEvolution(array $transactions, array $installments, string $startMonth, string $endMonth): array
    {
        $txMap = [];
        foreach ($transactions as $row) {
            $txMap[$row['period']] = [
                'incomes' => (float)$row['incomes'],
                'expenses' => (float)$row['expenses'],
            ];
        }

        $installmentsMap = [];
        foreach ($installments as $row) {
            $installmentsMap[$row['period']] = (float)$row['installments_due'];
        }

        $evolution = [];
        $cursor = strtotime($startMonth . '-01');
        $end = strtotime($endMonth . '-01');
        while ($cursor <= $end) {
            $period = date('Y-m', $cursor);
            $evolution[] = [
                'period' => $period,
                'incomes' => $txMap[$period]['incomes'] ?? 0.0,
                'expenses' => $txMap[$period]['expenses'] ?? 0.0,
                'installments_due' => $installmentsMap[$period] ?? 0.0,
            ];
            $cursor = strtotime('+1 month', $cursor);
        }

        return $evolution;
    }

    private function defaultPlanningData(): array
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

    private function defaultAgendaData(): array
    {
        return [
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

    /**
     * Executa blocos do dashboard com fallback para evitar HTTP 500 em producao
     * quando houver diferenca de schema/dados.
     */
    private function safeDashboardCall(callable $callback, $fallback, string $operation, int $userId)
    {
        try {
            $result = $callback();
            return $result ?? $fallback;
        } catch (Throwable $e) {
            error_log(sprintf(
                '[dashboard] %s failed for user_id=%d: %s',
                $operation,
                $userId,
                $e->getMessage()
            ));
            return $fallback;
        }
    }
}
