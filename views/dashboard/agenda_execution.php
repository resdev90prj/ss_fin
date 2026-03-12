<?php
$agendaData = $agendaData ?? [
  'active_target' => null,
  'active_objective' => null,
  'summary' => [],
  'items' => [],
];
$activeTarget = $agendaData['active_target'] ?? null;
$activeObjective = $agendaData['active_objective'] ?? null;
$summary = $agendaData['summary'] ?? [];
$items = $agendaData['items'] ?? [];
$limit = isset($limit) ? (int)$limit : 200;

$formatDate = static function ($value): string {
  $raw = trim((string)$value);
  if ($raw === '') {
    return '-';
  }
  $ts = strtotime($raw);
  return $ts !== false ? date('d/m/Y', $ts) : $raw;
};
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-4">
  <div>
    <h2 class="text-2xl font-bold">Agenda de Execucao</h2>
    <p class="text-sm text-slate-500">Lista automatica de acoes pendentes/em andamento priorizadas para execucao pratica.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a href="index.php?route=dashboard" class="bg-slate-200 text-slate-700 px-3 py-2 rounded hover:bg-slate-300">&larr; Voltar ao dashboard</a>
    <a href="index.php?route=targets" class="bg-slate-900 text-white px-3 py-2 rounded">Abrir modulo de alvos</a>
  </div>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
    <div class="rounded border border-slate-200 p-2 bg-slate-50">
      <p class="text-[11px] text-slate-500">Total</p>
      <p class="text-lg font-bold"><?= (int)($summary['total'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-red-200 p-2 bg-red-50">
      <p class="text-[11px] text-red-700">Atrasadas</p>
      <p class="text-lg font-bold text-red-700"><?= (int)($summary['overdue_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-orange-200 p-2 bg-orange-50">
      <p class="text-[11px] text-orange-700">Vencem hoje</p>
      <p class="text-lg font-bold text-orange-700"><?= (int)($summary['due_today_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-amber-200 p-2 bg-amber-50">
      <p class="text-[11px] text-amber-700">Ate 3 dias</p>
      <p class="text-lg font-bold text-amber-700"><?= (int)($summary['due_3_days_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-indigo-200 p-2 bg-indigo-50">
      <p class="text-[11px] text-indigo-700">Em andamento</p>
      <p class="text-lg font-bold text-indigo-700"><?= (int)($summary['in_progress_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-violet-200 p-2 bg-violet-50">
      <p class="text-[11px] text-violet-700">Obj. ativo</p>
      <p class="text-lg font-bold text-violet-700"><?= (int)($summary['active_objective_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-blue-200 p-2 bg-blue-50">
      <p class="text-[11px] text-blue-700">Alvo ativo</p>
      <p class="text-lg font-bold text-blue-700"><?= (int)($summary['active_target_count'] ?? 0) ?></p>
    </div>
    <div class="rounded border border-slate-200 p-2 bg-slate-50">
      <p class="text-[11px] text-slate-500">Pendente</p>
      <p class="text-lg font-bold text-slate-700"><?= (int)($summary['pending_count'] ?? 0) ?></p>
    </div>
  </div>

  <div class="mt-3 text-xs text-slate-600">
    <p>Regra de ordenacao: 1) atrasadas, 2) hoje, 3) ate 3 dias, 4) objetivo ativo, 5) alvo ativo, 6) demais pendentes.</p>
    <?php if ($activeTarget): ?>
      <p class="mt-1">Alvo ativo: <strong><?= e((string)($activeTarget['title'] ?? '-')) ?></strong><?php if ($activeObjective): ?> | Objetivo ativo: <strong><?= e((string)($activeObjective['title'] ?? '-')) ?></strong><?php endif; ?></p>
    <?php endif; ?>
    <p class="mt-1">Limite atual da agenda: <?= $limit ?> acoes.</p>
  </div>
</div>

<div class="bg-white p-4 rounded shadow overflow-auto">
  <h3 class="font-semibold mb-3">Itens da agenda</h3>
  <?php if (empty($items)): ?>
    <p class="text-sm text-slate-500">Nenhuma acao pendente ou em andamento encontrada.</p>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-200">
        <tr>
          <th class="p-2 text-left">Acao</th>
          <th class="p-2 text-left">Decisao</th>
          <th class="p-2 text-left">Objetivo</th>
          <th class="p-2 text-left">Alvo</th>
          <th class="p-2">Prazo</th>
          <th class="p-2">Status</th>
          <th class="p-2">Urgencia</th>
          <th class="p-2 text-right">Acesso</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <tr class="border-t">
          <td class="p-2">
            <p class="font-semibold"><?= e((string)($item['title'] ?? 'Acao')) ?></p>
            <p class="text-xs text-slate-500"><?= e((string)($item['urgency_text'] ?? '-')) ?></p>
          </td>
          <td class="p-2"><?= e((string)($item['decision_title'] ?? '-')) ?></td>
          <td class="p-2"><?= e((string)($item['objective_title'] ?? '-')) ?></td>
          <td class="p-2"><?= e((string)($item['target_title'] ?? '-')) ?></td>
          <td class="p-2 text-center"><?= e($formatDate($item['planned_date'] ?? '')) ?></td>
          <td class="p-2 text-center"><?= e((string)($item['status_label'] ?? '-')) ?></td>
          <td class="p-2 text-center">
            <span class="text-[11px] px-2 py-0.5 rounded <?= e((string)($item['priority_badge_class'] ?? 'bg-slate-200 text-slate-700')) ?>">
              <?= e((string)($item['urgency_level'] ?? 'Pendente')) ?>
            </span>
          </td>
          <td class="p-2 text-right">
            <a href="<?= e((string)($item['quick_url'] ?? 'index.php?route=targets')) ?>" class="text-blue-700 hover:text-blue-900">Abrir acao</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
