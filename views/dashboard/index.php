<?php
$contextLabel = $timelineContext === 'future' ? 'Projecao' : ($timelineContext === 'past' ? 'Historico' : 'Atual');
$expensesWithWithdrawals = (float)$summary['expenses'] + (float)$summary['withdrawals'];
$netClass = $projectedNet >= 0 ? 'text-emerald-600' : 'text-red-600';
$planningData = $planningData ?? [];
$activeTargetPlan = $planningData['active_target'] ?? null;
$activeObjectivePlan = $planningData['active_objective'] ?? null;
$planningProgress = (float)($planningData['progress_percent'] ?? 0);
$planningPending = (int)($planningData['pending_actions'] ?? 0);
$planningDone = (int)($planningData['done_actions'] ?? 0);
$planningTotal = (int)($planningData['total_actions'] ?? 0);
$objectiveOverdue = !empty($planningData['objective_overdue']);
$objectiveRemainingDays = isset($planningData['objective_remaining_days']) ? (int)$planningData['objective_remaining_days'] : null;

$executionCenter = $planningData['execution_center'] ?? [];
$notificationBadge = (int)($executionCenter['alert_badge'] ?? 0);
$notificationItems = $executionCenter['notifications'] ?? [];
$attentionItems = $executionCenter['immediate_attention'] ?? [];
$nextExecutionItems = $executionCenter['next_actions'] ?? [];
$sidebarItems = $executionCenter['sidebar_actions'] ?? [];
$secondaryItems = $executionCenter['secondary_actions'] ?? [];
$priorityCounts = $executionCenter['priority_counts'] ?? ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'no_deadline' => 0];
$indicators = $executionCenter['indicators'] ?? [
  'pending' => 0,
  'overdue' => 0,
  'due_3_days' => 0,
  'completed_recently' => 0,
  'objective_progress' => 0,
  'target_progress' => 0,
];
$progressSummary = $executionCenter['progress_summary'] ?? [
  'progress_percent' => 0,
  'total_actions' => 0,
  'done_actions' => 0,
  'pending_actions' => 0,
  'overdue_actions' => 0,
];
$formatDate = static function ($value): string {
  $raw = trim((string)$value);
  if ($raw === '') {
    return '-';
  }
  $ts = strtotime($raw);
  return $ts !== false ? date('d/m/Y', $ts) : $raw;
};
$agendaData = $agendaData ?? [
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
$agendaSummary = $agendaData['summary'] ?? [];
$agendaFocusItems = $agendaData['focus_items'] ?? [];
$agendaTotalItems = (int)($agendaSummary['total'] ?? 0);

$weeklyScoreData = $weeklyScoreData ?? [
  'current_week' => [],
  'previous_week' => [],
  'comparison' => [],
  'history' => [],
];
$weeklyCurrent = $weeklyScoreData['current_week'] ?? [];
$weeklyPrevious = $weeklyScoreData['previous_week'] ?? [];
$weeklyComparison = $weeklyScoreData['comparison'] ?? [];
$weeklyHistory = $weeklyScoreData['history'] ?? [];
$weeklyScore = (int)($weeklyCurrent['score'] ?? 0);
$weeklyClassLabel = (string)($weeklyCurrent['classification_label'] ?? 'Critico');
$weeklyClassBadge = (string)($weeklyCurrent['classification_badge_class'] ?? 'bg-red-100 text-red-700');
$weeklyDelta = (int)($weeklyComparison['delta'] ?? 0);
$weeklyTrendLabel = (string)($weeklyComparison['trend_label'] ?? 'Estavel');
$weeklyTrendClass = (string)($weeklyComparison['trend_class'] ?? 'text-slate-700');
$weeklyMessage = (string)($weeklyComparison['message'] ?? 'Score semanal indisponivel.');
$weeklyRangeLabel = (string)($weeklyCurrent['week_label'] ?? '');
$weeklyPrevRangeLabel = (string)($weeklyPrevious['week_label'] ?? '');
$weeklyCompleted = (int)($weeklyCurrent['completed_count'] ?? 0);
$weeklyPlanned = (int)($weeklyCurrent['planned_count'] ?? 0);
$weeklyOverdue = (int)($weeklyCurrent['overdue_open_count'] ?? 0);
$weeklyCompletionRate = (float)($weeklyCurrent['completion_rate'] ?? 0.0);
$weeklyDeltaLabel = $weeklyDelta > 0 ? '+' . $weeklyDelta : (string)$weeklyDelta;
?>

<style>
  .financial-value {
    transition: filter 0.2s ease;
  }

  .blur-sensitive {
    filter: blur(6px);
  }
</style>

<div class="flex flex-wrap items-start justify-between gap-3 mb-4">
  <div>
    <h2 class="text-2xl font-bold">Dashboard Financeiro - <?= e($monthLabel) ?></h2>
    <p class="text-sm text-slate-500 mt-1">Central de execucao: foco nas acoes que aproximam do alvo principal.</p>
  </div>
  <div class="flex items-start gap-2">
    <button
      id="privacyModeToggle"
      type="button"
      class="bg-slate-800 hover:bg-slate-700 text-white text-sm rounded-lg px-3 py-2 shadow-sm inline-flex items-center gap-2"
      aria-pressed="false"
      title="Alternar modo privacidade dos valores financeiros"
    >
      <span id="privacyModeIcon" aria-hidden="true">&#128065;</span>
      <span id="privacyModeLabel">Ocultar valores</span>
    </button>
    <?php if (is_admin()): ?>
      <form method="POST" action="index.php?route=alerts_dispatch">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg px-3 py-2 shadow-sm">Executar alertas por e-mail</button>
      </form>
    <?php endif; ?>
    <div class="relative">
      <button id="executionNotifToggle" type="button" class="relative bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm hover:bg-slate-50">
        <span class="text-lg">&#128276;</span>
        <span class="font-semibold text-sm ml-1">Alertas</span>
        <?php if ($notificationBadge > 0): ?>
          <span class="absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1 rounded-full bg-red-600 text-white text-xs font-bold flex items-center justify-center"><?= $notificationBadge ?></span>
        <?php endif; ?>
      </button>
      <div id="executionNotifPanel" class="hidden absolute right-0 mt-2 w-[360px] max-w-[90vw] bg-white border border-slate-200 rounded-lg shadow-xl z-30">
        <div class="px-3 py-2 border-b border-slate-200 flex items-center justify-between">
          <p class="font-semibold text-sm">Notificacoes da execucao</p>
          <span class="text-xs text-slate-500"><?= count($notificationItems) ?> itens</span>
        </div>
        <div class="max-h-[380px] overflow-auto p-2 space-y-2">
          <?php if (empty($notificationItems)): ?>
            <p class="text-sm text-slate-500 p-2">Sem alertas no momento.</p>
          <?php else: ?>
            <?php foreach ($notificationItems as $notification): ?>
              <div class="border rounded p-2">
                <div class="flex items-start justify-between gap-2">
                  <p class="text-sm font-semibold"><?= e((string)($notification['message'] ?? 'Notificacao')) ?></p>
                  <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($notification['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
                    <?= e((string)($notification['priority_label'] ?? 'Planejada')) ?>
                  </span>
                </div>
                <p class="text-sm mt-1"><?= e((string)($notification['action_title'] ?? 'Acao')) ?></p>
                <p class="text-xs text-slate-500 mt-1">
                  Objetivo: <?= e((string)($notification['objective_title'] ?? '-')) ?> | Decisao: <?= e((string)($notification['decision_title'] ?? '-')) ?>
                </p>
                <p class="text-xs text-slate-500">Prazo: <?= e($formatDate($notification['planned_date'] ?? '')) ?> | <?= e((string)($notification['urgency_text'] ?? '-')) ?></p>
                <div class="mt-2 text-right">
                  <a href="<?= e((string)($notification['action_url'] ?? 'index.php?route=targets')) ?>" class="text-xs text-blue-700 hover:text-blue-900">Abrir acao</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6">
  <a href="index.php?route=dashboard&month=<?= e($prevMonth) ?>" class="bg-slate-200 text-slate-700 px-3 py-2 rounded hover:bg-slate-300">&larr; Mes anterior</a>
  <form method="GET" class="flex items-center gap-2">
    <input type="hidden" name="route" value="dashboard">
    <input type="month" name="month" value="<?= e($month) ?>" class="border rounded p-2">
    <button class="bg-slate-900 text-white px-3 py-2 rounded">Aplicar competencia</button>
  </form>
  <a href="index.php?route=dashboard&month=<?= e($nextMonth) ?>" class="bg-slate-200 text-slate-700 px-3 py-2 rounded hover:bg-slate-300">Proximo mes &rarr;</a>
  <a href="index.php?route=dashboard" class="text-sm text-slate-600 hover:text-slate-900">Voltar para mes atual</a>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
    <div>
      <h3 class="font-semibold">Agenda de Hoje</h3>
      <p class="text-xs text-slate-500">Execucao diaria priorizada para o alvo principal.</p>
    </div>
    <a href="index.php?route=agenda_execution" class="text-blue-700 text-sm">Abrir Agenda de Execucao</a>
  </div>

  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
    <div class="rounded border border-red-200 bg-red-50 p-2">
      <p class="text-[11px] text-red-700">Atrasadas</p>
      <p class="text-lg font-bold text-red-700"><?= (int)($agendaSummary['overdue_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-orange-200 bg-orange-50 p-2">
      <p class="text-[11px] text-orange-700">Vencem hoje</p>
      <p class="text-lg font-bold text-orange-700"><?= (int)($agendaSummary['due_today_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-amber-200 bg-amber-50 p-2">
      <p class="text-[11px] text-amber-700">Ate 3 dias</p>
      <p class="text-lg font-bold text-amber-700"><?= (int)($agendaSummary['due_3_days_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-indigo-200 bg-indigo-50 p-2">
      <p class="text-[11px] text-indigo-700">Em andamento</p>
      <p class="text-lg font-bold text-indigo-700"><?= (int)($agendaSummary['in_progress_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-slate-200 bg-slate-50 p-2">
      <p class="text-[11px] text-slate-600">Total na agenda</p>
      <p class="text-lg font-bold text-slate-700"><?= $agendaTotalItems ?></p>
    </div>
  </div>

  <?php if (empty($agendaFocusItems)): ?>
    <p class="text-sm text-slate-500">Sem acoes urgentes para hoje.</p>
  <?php else: ?>
    <div class="space-y-2">
      <?php foreach ($agendaFocusItems as $item): ?>
        <div class="border rounded p-2">
          <div class="flex items-start justify-between gap-2">
            <p class="font-semibold text-sm"><?= e((string)($item['title'] ?? 'Acao')) ?></p>
            <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($item['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
              <?= e((string)($item['priority_label'] ?? 'Pendente')) ?>
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">
            Alvo: <?= e((string)($item['target_title'] ?? '-')) ?> | Objetivo: <?= e((string)($item['objective_title'] ?? '-')) ?> | Decisao: <?= e((string)($item['decision_title'] ?? '-')) ?>
          </p>
          <div class="flex items-center justify-between mt-1">
            <p class="text-xs text-slate-500">
              Prazo: <?= e($formatDate($item['planned_date'] ?? '')) ?> | Status: <?= e((string)($item['status_label'] ?? '-')) ?> | Urgencia: <?= e((string)($item['urgency_level'] ?? '-')) ?>
            </p>
            <a href="<?= e((string)($item['quick_url'] ?? 'index.php?route=targets')) ?>" class="text-xs text-blue-700 hover:text-blue-900">Acesso rapido</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
    <div>
      <h3 class="font-semibold">Score de Execucao Semanal</h3>
      <p class="text-xs text-slate-500">Consistencia semanal das acoes do plano com foco em alvo e objetivo ativos.</p>
    </div>
    <?php if ($weeklyRangeLabel !== ''): ?>
      <span class="text-xs text-slate-500">Semana atual: <?= e($weeklyRangeLabel) ?></span>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 mb-3">
    <div class="rounded border border-slate-200 p-3">
      <p class="text-xs text-slate-500">Score da semana</p>
      <p class="text-3xl font-bold text-slate-900"><?= $weeklyScore ?></p>
      <span class="text-[11px] px-2 py-0.5 rounded <?= e($weeklyClassBadge) ?>"><?= e($weeklyClassLabel) ?></span>
    </div>
    <div class="rounded border border-slate-200 p-3">
      <p class="text-xs text-slate-500">Comparacao semanal</p>
      <p class="text-xl font-semibold <?= e($weeklyTrendClass) ?>"><?= e($weeklyDeltaLabel) ?> pts</p>
      <p class="text-xs <?= e($weeklyTrendClass) ?>"><?= e($weeklyTrendLabel) ?></p>
      <?php if ($weeklyPrevRangeLabel !== ''): ?>
        <p class="text-[11px] text-slate-500 mt-1">Base: <?= e($weeklyPrevRangeLabel) ?></p>
      <?php endif; ?>
    </div>
    <div class="rounded border border-slate-200 p-3">
      <p class="text-xs text-slate-500">Execucao da semana</p>
      <p class="text-sm mt-1">Concluidas: <strong><?= $weeklyCompleted ?></strong></p>
      <p class="text-sm">Previstas: <strong><?= $weeklyPlanned ?></strong></p>
      <p class="text-sm">Taxa: <strong><?= number_format($weeklyCompletionRate, 2, ',', '.') ?>%</strong></p>
    </div>
    <div class="rounded border border-slate-200 p-3">
      <p class="text-xs text-slate-500">Risco atual</p>
      <p class="text-sm mt-1">Atrasadas em aberto: <strong class="<?= $weeklyOverdue > 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= $weeklyOverdue ?></strong></p>
      <p class="text-xs text-slate-600 mt-1"><?= e($weeklyMessage) ?></p>
    </div>
  </div>

  <?php if (!empty($weeklyHistory)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
      <div class="lg:col-span-2 rounded border border-slate-200 p-3">
        <h4 class="font-semibold text-sm mb-2">Historico das ultimas semanas</h4>
        <canvas id="chartWeeklyScore" height="120"></canvas>
      </div>
      <div class="rounded border border-slate-200 p-3">
        <h4 class="font-semibold text-sm mb-2">Resumo rapido</h4>
        <div class="space-y-2">
          <?php foreach (array_slice(array_reverse($weeklyHistory), 0, 4) as $week): ?>
            <div class="border rounded p-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-slate-600"><?= e((string)($week['week_label'] ?? '-')) ?></p>
                <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($week['classification_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
                  <?= e((string)($week['classification_label'] ?? '-')) ?>
                </span>
              </div>
              <p class="text-sm font-semibold mt-1">Score <?= (int)($week['score'] ?? 0) ?></p>
              <p class="text-[11px] text-slate-500">Concluidas <?= (int)($week['completed_count'] ?? 0) ?> / Previstas <?= (int)($week['planned_count'] ?? 0) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
    <h3 class="font-semibold">Central de Execucao</h3>
    <a href="index.php?route=targets" class="text-blue-700 text-sm">Abrir modulo de alvos</a>
  </div>

  <?php if ($activeTargetPlan): ?>
    <div class="rounded border border-slate-200 p-3 mb-4">
      <p class="text-sm text-slate-500">Alvo ativo</p>
      <p class="text-lg font-semibold"><?= e((string)$activeTargetPlan['title']) ?></p>
      <p class="text-sm mt-1">Progresso: <strong><?= number_format($planningProgress, 2, ',', '.') ?>%</strong></p>
      <div class="w-full bg-slate-200 rounded h-2 mt-2">
        <div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($planningProgress, 2, '.', '') ?>%"></div>
      </div>
      <p class="text-xs text-slate-500 mt-2">Acoes concluidas: <?= $planningDone ?> / <?= $planningTotal ?> | Pendentes: <?= $planningPending ?></p>

      <?php if ($activeObjectivePlan): ?>
        <div class="mt-3 p-3 rounded border border-slate-200 bg-slate-50">
          <p class="text-sm text-slate-500">Objetivo ativo</p>
          <p class="font-semibold"><?= e((string)$activeObjectivePlan['title']) ?></p>
          <p class="text-xs text-slate-500">Prazo estimado: <?= e($formatDate($activeObjectivePlan['deadline_date'] ?? '')) ?></p>
          <?php if ($objectiveOverdue): ?>
            <p class="text-xs text-red-700 mt-1">Alerta: objetivo atrasado.</p>
          <?php elseif ($objectiveRemainingDays !== null): ?>
            <p class="text-xs text-slate-600 mt-1">Dias restantes: <?= $objectiveRemainingDays ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 space-y-4">
        <div class="rounded border border-red-200 bg-red-50 p-3">
          <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="font-semibold text-red-800">Atencao imediata</h4>
            <span class="text-xs text-red-700">Atrasadas, vencendo hoje e criticas do objetivo ativo</span>
          </div>
          <?php if (empty($attentionItems)): ?>
            <p class="text-sm text-red-700">Nenhuma acao critica agora.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($attentionItems as $action): ?>
                <div class="border rounded p-2 bg-white <?= e((string)($action['priority_border_class'] ?? 'border-slate-200')) ?>">
                  <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-sm"><?= e((string)$action['title']) ?></p>
                    <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($action['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
                      <?= e((string)($action['priority_label'] ?? 'Planejada')) ?>
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 mt-1">Objetivo: <?= e((string)($action['objective_title'] ?? '-')) ?> | Decisao: <?= e((string)($action['decision_title'] ?? '-')) ?></p>
                  <p class="text-xs text-slate-500">Prazo: <?= e($formatDate($action['planned_date'] ?? '')) ?> | <?= e((string)($action['urgency_text'] ?? '-')) ?></p>
                  <div class="mt-2 flex items-center justify-between">
                    <?php if (!empty($action['is_active_objective'])): ?>
                      <span class="text-[11px] px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">Objetivo ativo</span>
                    <?php else: ?>
                      <span class="text-[11px] px-2 py-0.5 rounded bg-slate-200 text-slate-700">Alvo ativo</span>
                    <?php endif; ?>
                    <a href="<?= e((string)($action['action_url'] ?? 'index.php?route=targets')) ?>" class="text-xs text-blue-700 hover:text-blue-900">Abrir acao</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="rounded border border-slate-200 p-3">
          <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="font-semibold">Proximas acoes</h4>
            <span class="text-xs text-slate-500">Ordenadas por prioridade e prazo</span>
          </div>
          <?php if (empty($nextExecutionItems)): ?>
            <p class="text-sm text-slate-500">Sem acoes pendentes no alvo ativo.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($nextExecutionItems as $action): ?>
                <div class="border rounded p-2 <?= e((string)($action['priority_border_class'] ?? 'border-slate-200')) ?>">
                  <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-sm"><?= e((string)$action['title']) ?></p>
                    <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($action['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
                      <?= e((string)($action['priority_label'] ?? 'Planejada')) ?>
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 mt-1">Objetivo: <?= e((string)($action['objective_title'] ?? '-')) ?> | Decisao: <?= e((string)($action['decision_title'] ?? '-')) ?></p>
                  <div class="flex items-center justify-between mt-1">
                    <p class="text-xs text-slate-500">Prazo: <?= e($formatDate($action['planned_date'] ?? '')) ?> | <?= e((string)($action['urgency_text'] ?? '-')) ?></p>
                    <a href="<?= e((string)($action['action_url'] ?? 'index.php?route=targets')) ?>" class="text-xs text-blue-700 hover:text-blue-900">Abrir acao</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($secondaryItems)): ?>
          <div class="rounded border border-slate-200 bg-slate-50 p-3">
            <h4 class="font-semibold mb-2">Outras acoes fora do alvo ativo</h4>
            <div class="space-y-2">
              <?php foreach ($secondaryItems as $action): ?>
                <div class="border border-slate-200 rounded p-2 bg-white">
                  <p class="font-semibold text-sm"><?= e((string)$action['title']) ?></p>
                  <p class="text-xs text-slate-500 mt-1">Alvo: <?= e((string)($action['target_title'] ?? '-')) ?> | Objetivo: <?= e((string)($action['objective_title'] ?? '-')) ?></p>
                  <p class="text-xs text-slate-500">Prazo: <?= e($formatDate($action['planned_date'] ?? '')) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="space-y-4">
        <div class="rounded border border-slate-200 p-3">
          <h4 class="font-semibold mb-2">Minhas acoes de hoje</h4>
          <?php if (empty($sidebarItems)): ?>
            <p class="text-sm text-slate-500">Sem pendencias imediatas.</p>
          <?php else: ?>
            <div class="space-y-2">
              <?php foreach ($sidebarItems as $action): ?>
                <div class="border rounded p-2 <?= e((string)($action['priority_border_class'] ?? 'border-slate-200')) ?>">
                  <p class="font-semibold text-sm"><?= e((string)$action['title']) ?></p>
                  <p class="text-xs text-slate-500"><?= e((string)($action['urgency_text'] ?? '-')) ?></p>
                  <div class="flex items-center justify-between mt-1">
                    <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($action['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
                      <?= e((string)($action['priority_label'] ?? 'Planejada')) ?>
                    </span>
                    <a href="<?= e((string)($action['action_url'] ?? 'index.php?route=targets')) ?>" class="text-xs text-blue-700 hover:text-blue-900">Abrir</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="rounded border border-slate-200 p-3">
          <h4 class="font-semibold mb-2">Resumo do progresso</h4>
          <p class="text-sm">Progresso do alvo ativo: <strong><?= number_format((float)($progressSummary['progress_percent'] ?? 0), 2, ',', '.') ?>%</strong></p>
          <div class="w-full bg-slate-200 rounded h-2 mt-2">
            <div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format((float)($progressSummary['progress_percent'] ?? 0), 2, '.', '') ?>%"></div>
          </div>
          <div class="mt-3 text-xs text-slate-600 space-y-1">
            <p>Total de acoes: <?= (int)($progressSummary['total_actions'] ?? 0) ?></p>
            <p>Concluidas: <?= (int)($progressSummary['done_actions'] ?? 0) ?></p>
            <p>Pendentes: <?= (int)($progressSummary['pending_actions'] ?? 0) ?></p>
            <p>Atrasadas: <?= (int)($progressSummary['overdue_actions'] ?? 0) ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-6 gap-3 mt-4">
      <div class="bg-slate-50 border border-slate-200 rounded p-3">
        <p class="text-xs text-slate-500">Acoes pendentes</p>
        <p class="text-lg font-bold"><?= (int)($indicators['pending'] ?? 0) ?></p>
      </div>
      <div class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-xs text-red-700">Acoes vencidas</p>
        <p class="text-lg font-bold text-red-700"><?= (int)($indicators['overdue'] ?? 0) ?></p>
      </div>
      <div class="bg-amber-50 border border-amber-200 rounded p-3">
        <p class="text-xs text-amber-700">Vencem em ate 3 dias</p>
        <p class="text-lg font-bold text-amber-700"><?= (int)($indicators['due_3_days'] ?? 0) ?></p>
      </div>
      <div class="bg-emerald-50 border border-emerald-200 rounded p-3">
        <p class="text-xs text-emerald-700">Concluidas recentemente</p>
        <p class="text-lg font-bold text-emerald-700"><?= (int)($indicators['completed_recently'] ?? 0) ?></p>
      </div>
      <div class="bg-indigo-50 border border-indigo-200 rounded p-3">
        <p class="text-xs text-indigo-700">Progresso objetivo ativo</p>
        <p class="text-lg font-bold text-indigo-700"><?= number_format((float)($indicators['objective_progress'] ?? 0), 2, ',', '.') ?>%</p>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded p-3">
        <p class="text-xs text-blue-700">Progresso alvo ativo</p>
        <p class="text-lg font-bold text-blue-700"><?= number_format((float)($indicators['target_progress'] ?? 0), 2, ',', '.') ?>%</p>
      </div>
    </div>
  <?php else: ?>
    <p class="text-sm text-slate-600">Nenhum alvo ativo no momento. Cadastre e ative um alvo para usar a central de execucao no dashboard.</p>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Saldo acumulado</p>
    <p class="text-xl font-bold"><span class="financial-value">R$ <?= number_format((float)$balance, 2, ',', '.') ?></span></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de receitas</p>
    <p class="text-xl font-bold text-emerald-600"><span class="financial-value">R$ <?= number_format((float)$summary['incomes'], 2, ',', '.') ?></span></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de despesas</p>
    <p class="text-xl font-bold text-red-600"><span class="financial-value">R$ <?= number_format((float)$summary['expenses'], 2, ',', '.') ?></span></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de retiradas</p>
    <p class="text-xl font-bold text-amber-600"><span class="financial-value">R$ <?= number_format((float)$summary['withdrawals'], 2, ',', '.') ?></span></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Parcelas da competencia</p>
    <p class="text-xl font-bold text-orange-700"><span class="financial-value">R$ <?= number_format((float)$installmentProjection['total_scheduled'], 2, ',', '.') ?></span></p>
    <p class="text-xs text-slate-500 mt-1">Em aberto hoje: <span class="financial-value">R$ <?= number_format((float)$installmentProjection['total_due'], 2, ',', '.') ?></span> (<?= (int)$installmentProjection['installments_open_count'] ?> de <?= (int)$installmentProjection['installments_count'] ?>)</p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Resultado da competencia</p>
    <p class="text-xl font-bold <?= e($netClass) ?>"><span class="financial-value">R$ <?= number_format((float)$projectedNet, 2, ',', '.') ?></span></p>
    <p class="text-xs text-slate-500 mt-1">Receber: <span class="financial-value">R$ <?= number_format((float)$projectedReceivable, 2, ',', '.') ?></span> | Pagar: <span class="financial-value">R$ <?= number_format((float)$projectedPayable, 2, ',', '.') ?></span></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Receber x Pagar (<?= e($monthLabel) ?>)</h3>
    <canvas id="chartIncomeExpense" class="financial-value"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Despesas por Categoria</h3>
    <canvas id="chartCategories" class="financial-value"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Evolucao (ultimos 6 meses ate a competencia)</h3>
    <canvas id="chartEvolution" class="financial-value"></canvas>
  </div>
</div>

<div class="bg-white p-4 rounded shadow mt-6 overflow-auto">
  <h3 class="font-semibold mb-3">Parcelas previstas na competencia (<?= e($monthLabel) ?>)</h3>
  <?php if (empty($installmentDetails)): ?>
    <p class="text-sm text-slate-500">Sem parcelas cadastradas para este mes.</p>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-200">
        <tr>
          <th class="p-2 text-left">Divida</th>
          <th class="p-2">Parcela</th>
          <th class="p-2">Vencimento</th>
          <th class="p-2">Valor</th>
          <th class="p-2">Pago</th>
          <th class="p-2">Saldo</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($installmentDetails as $item): ?>
        <?php $remaining = (float)$item['remaining_amount']; ?>
        <tr class="border-t">
          <td class="p-2"><?= e($item['debt_description']) ?></td>
          <td class="p-2 text-center">#<?= (int)$item['installment_number'] ?></td>
          <td class="p-2 text-center"><?= e($item['due_date']) ?></td>
          <td class="p-2 text-center"><span class="financial-value">R$ <?= number_format((float)$item['amount'], 2, ',', '.') ?></span></td>
          <td class="p-2 text-center"><span class="financial-value">R$ <?= number_format((float)$item['paid_amount'], 2, ',', '.') ?></span></td>
          <td class="p-2 text-center font-semibold <?= $remaining > 0 ? 'text-orange-700' : 'text-emerald-700' ?>"><span class="financial-value">R$ <?= number_format($remaining, 2, ',', '.') ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="mt-4 text-right text-sm text-slate-600">Dividas totais em aberto: <strong class="financial-value">R$ <?= number_format((float)$debtsOpen, 2, ',', '.') ?></strong></div>

<script>
const expensesByCategory = <?= json_encode($expensesByCategory, JSON_UNESCAPED_UNICODE) ?>;
const evolution = <?= json_encode($evolution, JSON_UNESCAPED_UNICODE) ?>;
const weeklyScoreHistory = <?= json_encode($weeklyHistory, JSON_UNESCAPED_UNICODE) ?>;

const categoryLabels = expensesByCategory.length ? expensesByCategory.map(i => i.name) : ['Sem dados'];
const categoryValues = expensesByCategory.length ? expensesByCategory.map(i => Number(i.total)) : [0];
const formatPeriod = (period) => {
  const [year, month] = period.split('-');
  return `${month}/${year}`;
};

new Chart(document.getElementById('chartIncomeExpense'), {
  type: 'bar',
  data: {
    labels: ['Receber (Receitas)', 'Pagar (Despesas + Retiradas)', 'Parcelas da competencia'],
    datasets: [{
      label: 'R$ na competencia',
      data: [<?= (float)$summary['incomes'] ?>, <?= (float)$expensesWithWithdrawals ?>, <?= (float)$installmentProjection['total_scheduled'] ?>],
      backgroundColor: ['#10b981', '#ef4444', '#f59e0b']
    }]
  }
});

new Chart(document.getElementById('chartCategories'), {
  type: 'doughnut',
  data: {
    labels: categoryLabels,
    datasets: [{
      data: categoryValues,
      backgroundColor: ['#0284c7', '#0369a1', '#0f766e', '#f59e0b', '#dc2626', '#6d28d9', '#334155']
    }]
  }
});

new Chart(document.getElementById('chartEvolution'), {
  type: 'line',
  data: {
    labels: evolution.map(i => formatPeriod(i.period)),
    datasets: [
      { label: 'Receitas', data: evolution.map(i => Number(i.incomes)), borderColor: '#10b981', tension: 0.2 },
      { label: 'Despesas', data: evolution.map(i => Number(i.expenses)), borderColor: '#ef4444', tension: 0.2 },
      { label: 'Parcelas previstas', data: evolution.map(i => Number(i.installments_due)), borderColor: '#f59e0b', tension: 0.2, borderDash: [6, 4] }
    ]
  }
});

if (weeklyScoreHistory.length && document.getElementById('chartWeeklyScore')) {
  new Chart(document.getElementById('chartWeeklyScore'), {
    type: 'line',
    data: {
      labels: weeklyScoreHistory.map((item) => item.week_label || ''),
      datasets: [{
        label: 'Score semanal',
        data: weeklyScoreHistory.map((item) => Number(item.score || 0)),
        borderColor: '#0f172a',
        backgroundColor: 'rgba(15, 23, 42, 0.10)',
        fill: true,
        tension: 0.3
      }]
    },
    options: {
      scales: {
        y: {
          min: 0,
          max: 100
        }
      }
    }
  });
}

const notifToggle = document.getElementById('executionNotifToggle');
const notifPanel = document.getElementById('executionNotifPanel');
if (notifToggle && notifPanel) {
  notifToggle.addEventListener('click', () => {
    notifPanel.classList.toggle('hidden');
  });
  document.addEventListener('click', (event) => {
    if (!notifPanel.classList.contains('hidden') && !notifPanel.contains(event.target) && !notifToggle.contains(event.target)) {
      notifPanel.classList.add('hidden');
    }
  });
}

const privacyStorageKey = 'dashboard_privacy_mode';
const privacyToggle = document.getElementById('privacyModeToggle');
const privacyIcon = document.getElementById('privacyModeIcon');
const privacyLabel = document.getElementById('privacyModeLabel');

const applyPrivacyMode = (enabled) => {
  const sensitiveNodes = document.querySelectorAll('.financial-value');
  sensitiveNodes.forEach((node) => {
    node.classList.toggle('blur-sensitive', enabled);
  });

  if (!privacyToggle || !privacyIcon || !privacyLabel) {
    return;
  }

  privacyToggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
  privacyIcon.innerHTML = enabled ? '&#128584;' : '&#128065;';
  privacyLabel.textContent = enabled ? 'Mostrar valores' : 'Ocultar valores';
};

const initialPrivacyMode = (() => {
  try {
    return localStorage.getItem(privacyStorageKey) === 'on';
  } catch (error) {
    return false;
  }
})();

applyPrivacyMode(initialPrivacyMode);

if (privacyToggle) {
  privacyToggle.addEventListener('click', () => {
    const isHidden = privacyToggle.getAttribute('aria-pressed') === 'true';
    const nextState = !isHidden;
    applyPrivacyMode(nextState);

    try {
      localStorage.setItem(privacyStorageKey, nextState ? 'on' : 'off');
    } catch (error) {
      // Sem persistencia quando localStorage estiver indisponivel.
    }
  });
}
</script>
