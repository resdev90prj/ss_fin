<?php
declare(strict_types=1);

require_once api_project_root() . '/models/Target.php';

class ApiTargetController
{
    public function summary(): void
    {
        api_require_login();

        $userId = api_current_effective_user_id();
        $targetModel = new Target();

        api_json_response(true, 'Resumo de execucao carregado com sucesso.', [
            'planning' => $targetModel->dashboardData($userId),
            'agenda' => $targetModel->executionAgendaData($userId, 160),
            'weekly_score' => $targetModel->executionWeeklyScoreData($userId, 8),
        ]);
    }
}

