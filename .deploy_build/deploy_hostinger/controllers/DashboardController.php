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

        $summary = $transactionModel->summaryMonth($userId, $month);
        $balance = $transactionModel->balanceTotal($userId);
        $debtsOpen = $debtModel->openTotal($userId);
        $expensesByCategory = $transactionModel->expensesByCategoryMonth($userId, $month);

        $installmentProjection = $installmentModel->projectionSummaryByMonth($userId, $month);
        $installmentDetails = $installmentModel->projectionDetailsByMonth($userId, $month);
        $planningData = $targetModel->dashboardData($userId);
        $evolution = $this->buildEvolution(
            $transactionModel->monthlyEvolutionRange($userId, $startMonth, $endMonth),
            $installmentModel->projectionByRange($userId, $startMonth, $endMonth),
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
}
