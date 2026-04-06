<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Account.php';
require_once api_project_root() . '/models/Transaction.php';
require_once api_project_root() . '/models/Target.php';

class ApiOnboardingController
{
    public function summary(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $planningEnabled = has_current_module_access('planning');

        $accountsCount = (new Account())->countByUser($userId);
        $transactionsCount = (new Transaction())->countByUser($userId);
        $targetModel = new Target();
        $targetsCount = $planningEnabled ? $targetModel->countByUser($userId) : 0;
        $actionSummary = $planningEnabled
            ? $targetModel->actionSummaryByUser($userId)
            : ['total_actions' => 0, 'completed_actions' => 0];

        api_json_response(true, 'Resumo de onboarding carregado com sucesso.', [
            'stats' => [
                'accounts_count' => $accountsCount,
                'transactions_count' => $transactionsCount,
                'targets_count' => $targetsCount,
                'actions_count' => (int)($actionSummary['total_actions'] ?? 0),
                'completed_actions_count' => (int)($actionSummary['completed_actions'] ?? 0),
                'planning_enabled' => $planningEnabled,
            ],
        ]);
    }
}
