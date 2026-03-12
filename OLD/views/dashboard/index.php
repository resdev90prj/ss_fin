<?php
$contextLabel = $timelineContext === 'future' ? 'Projecao' : ($timelineContext === 'past' ? 'Historico' : 'Atual');
$expensesWithWithdrawals = (float)$summary['expenses'] + (float)$summary['withdrawals'];
$netClass = $projectedNet >= 0 ? 'text-emerald-600' : 'text-red-600';
$planningData = $planningData ?? [];
$activeTargetPlan = $planningData['active_target'] ?? null;
$activeObjectivePlan = $planningData['active_objective'] ?? null;
$nextActionsPlan = $planningData['next_actions'] ?? [];
$planningProgress = (float)($planningData['progress_percent'] ?? 0);
$planningPending = (int)($planningData['pending_actions'] ?? 0);
$planningDone = (int)($planningData['done_actions'] ?? 0);
$planningTotal = (int)($planningData['total_actions'] ?? 0);
$objectiveOverdue = !empty($planningData['objective_overdue']);
$objectiveRemainingDays = isset($planningData['objective_remaining_days']) ? (int)$planningData['objective_remaining_days'] : null;
?>

<div class="flex flex-col gap-3 mb-6">
  <h2 class="text-2xl font-bold">Dashboard Financeiro - <?= e($monthLabel) ?></h2>
  <div class="flex flex-wrap items-center gap-2">
    <a href="index.php?route=dashboard&month=<?= e($prevMonth) ?>" class="bg-slate-200 text-slate-700 px-3 py-2 rounded hover:bg-slate-300">&larr; Mes anterior</a>
    <form method="GET" class="flex items-center gap-2">
      <input type="hidden" name="route" value="dashboard">
      <input type="month" name="month" value="<?= e($month) ?>" class="border rounded p-2">
      <button class="bg-slate-900 text-white px-3 py-2 rounded">Aplicar competencia</button>
    </form>
    <a href="index.php?route=dashboard&month=<?= e($nextMonth) ?>" class="bg-slate-200 text-slate-700 px-3 py-2 rounded hover:bg-slate-300">Proximo mes &rarr;</a>
    <a href="index.php?route=dashboard" class="text-sm text-slate-600 hover:text-slate-900">Voltar para mes atual</a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mb-6">
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Saldo acumulado</p>
    <p class="text-xl font-bold">R$ <?= number_format((float)$balance, 2, ',', '.') ?></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de receitas</p>
    <p class="text-xl font-bold text-emerald-600">R$ <?= number_format((float)$summary['incomes'], 2, ',', '.') ?></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de despesas</p>
    <p class="text-xl font-bold text-red-600">R$ <?= number_format((float)$summary['expenses'], 2, ',', '.') ?></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm"><?= e($contextLabel) ?> de retiradas</p>
    <p class="text-xl font-bold text-amber-600">R$ <?= number_format((float)$summary['withdrawals'], 2, ',', '.') ?></p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Parcelas da competencia</p>
    <p class="text-xl font-bold text-orange-700">R$ <?= number_format((float)$installmentProjection['total_scheduled'], 2, ',', '.') ?></p>
    <p class="text-xs text-slate-500 mt-1">Em aberto hoje: R$ <?= number_format((float)$installmentProjection['total_due'], 2, ',', '.') ?> (<?= (int)$installmentProjection['installments_open_count'] ?> de <?= (int)$installmentProjection['installments_count'] ?>)</p>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <p class="text-sm">Resultado da competencia</p>
    <p class="text-xl font-bold <?= e($netClass) ?>">R$ <?= number_format((float)$projectedNet, 2, ',', '.') ?></p>
    <p class="text-xs text-slate-500 mt-1">Receber: R$ <?= number_format((float)$projectedReceivable, 2, ',', '.') ?> | Pagar: R$ <?= number_format((float)$projectedPayable, 2, ',', '.') ?></p>
  </div>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
    <h3 class="font-semibold">Alvos, Objetivos e Execucao</h3>
    <a href="index.php?route=targets" class="text-blue-700 text-sm">Abrir modulo</a>
  </div>

  <?php if ($activeTargetPlan): ?>
    <div class="grid lg:grid-cols-2 gap-4">
      <div>
        <p class="text-sm text-slate-500">Alvo ativo</p>
        <p class="text-lg font-semibold"><?= e((string)$activeTargetPlan['title']) ?></p>
        <p class="text-sm mt-1">Progresso: <strong><?= number_format($planningProgress, 2, ',', '.') ?>%</strong></p>
        <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($planningProgress, 2, '.', '') ?>%"></div></div>
        <p class="text-xs text-slate-500 mt-2">Acoes concluidas: <?= $planningDone ?> / <?= $planningTotal ?> | Pendentes: <?= $planningPending ?></p>

        <?php if ($activeObjectivePlan): ?>
          <div class="mt-3 p-3 rounded border border-slate-200">
            <p class="text-sm text-slate-500">Objetivo atual</p>
            <p class="font-semibold"><?= e((string)$activeObjectivePlan['title']) ?></p>
            <p class="text-xs text-slate-500">Prazo estimado: <?= e((string)($activeObjectivePlan['deadline_date'] ?? '-')) ?></p>
            <?php if ($objectiveOverdue): ?>
              <p class="text-xs text-red-700 mt-1">Alerta: objetivo atrasado.</p>
            <?php elseif ($objectiveRemainingDays !== null): ?>
              <p class="text-xs text-slate-600 mt-1">Dias restantes: <?= $objectiveRemainingDays ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <p class="text-sm text-slate-500 mb-2">Proximas acoes prioritarias</p>
        <?php if (empty($nextActionsPlan)): ?>
          <p class="text-sm text-slate-500">Sem acoes pendentes no alvo ativo.</p>
        <?php else: ?>
          <ul class="space-y-2 text-sm">
            <?php foreach ($nextActionsPlan as $action): ?>
              <li class="border rounded p-2">
                <p class="font-semibold"><?= e((string)$action['title']) ?></p>
                <p class="text-xs text-slate-500">Objetivo: <?= e((string)$action['objective_title']) ?> | Decisao: <?= e((string)$action['decision_title']) ?></p>
                <p class="text-xs text-slate-500">Data prevista: <?= e((string)($action['planned_date'] ?? '-')) ?> | Status: <?= e((string)$action['status']) ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <p class="text-sm text-slate-600">Nenhum alvo ativo no momento. Cadastre e ative um alvo para acompanhar execucao no dashboard.</p>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Receber x Pagar (<?= e($monthLabel) ?>)</h3>
    <canvas id="chartIncomeExpense"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Despesas por Categoria</h3>
    <canvas id="chartCategories"></canvas>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h3 class="font-semibold mb-2">Evolucao (ultimos 6 meses ate a competencia)</h3>
    <canvas id="chartEvolution"></canvas>
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
          <td class="p-2 text-center">R$ <?= number_format((float)$item['amount'], 2, ',', '.') ?></td>
          <td class="p-2 text-center">R$ <?= number_format((float)$item['paid_amount'], 2, ',', '.') ?></td>
          <td class="p-2 text-center font-semibold <?= $remaining > 0 ? 'text-orange-700' : 'text-emerald-700' ?>">R$ <?= number_format($remaining, 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="mt-4 text-right text-sm text-slate-600">Dividas totais em aberto: <strong>R$ <?= number_format((float)$debtsOpen, 2, ',', '.') ?></strong></div>

<script>
const expensesByCategory = <?= json_encode($expensesByCategory, JSON_UNESCAPED_UNICODE) ?>;
const evolution = <?= json_encode($evolution, JSON_UNESCAPED_UNICODE) ?>;

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
</script>
