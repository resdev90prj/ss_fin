<?php
$activeTarget = $activeTarget ?? null;
?>

<h2 class="text-2xl font-bold mb-4">Alvos, Objetivos e Execucao</h2>
<p class="text-sm text-slate-600 mb-4">Planejamento hierarquico: <strong>Alvo -> Objetivos -> Decisoes -> Acoes</strong>.</p>

<?php if ($activeTarget): ?>
<div class="bg-emerald-50 border border-emerald-200 rounded p-4 mb-6">
  <p class="text-sm text-emerald-700">Alvo ativo</p>
  <p class="font-semibold text-lg"><?= e($activeTarget['title']) ?></p>
  <p class="text-sm mt-1">Progresso: <?= number_format((float)$activeTarget['progress_percent'], 2, ',', '.') ?>%</p>
  <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format((float)$activeTarget['progress_percent'], 2, '.', '') ?>%"></div></div>
  <a href="index.php?route=targets_show&id=<?= (int)$activeTarget['id'] ?>" class="text-blue-700 text-sm inline-block mt-2">Abrir alvo ativo</a>
</div>
<?php endif; ?>

<form method="POST" action="index.php?route=targets_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="title" placeholder="Titulo do alvo" required class="border rounded p-2 md:col-span-2">
  <input type="text" name="target_amount" placeholder="Valor alvo (opcional)" class="border rounded p-2">
  <input type="date" name="start_date" class="border rounded p-2">
  <input type="date" name="expected_end_date" class="border rounded p-2">
  <select name="status" class="border rounded p-2">
    <option value="paused">Pausado</option>
    <option value="active">Ativo</option>
    <option value="achieved">Atingido</option>
    <option value="cancelled">Cancelado</option>
  </select>
  <input name="description" placeholder="Descricao livre" class="border rounded p-2 md:col-span-3">
  <input name="notes" placeholder="Observacoes" class="border rounded p-2 md:col-span-2">
  <button class="bg-slate-900 text-white rounded p-2">Criar alvo</button>
</form>

<div class="grid md:grid-cols-2 gap-4">
<?php foreach ($targets as $t): ?>
  <?php
    $progress = (float)($t['progress_percent'] ?? 0);
    $isActive = ($t['status'] ?? '') === 'active';
  ?>
  <div class="bg-white p-4 rounded shadow">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="font-semibold text-lg"><?= e($t['title']) ?></p>
        <p class="text-xs text-slate-500">Status: <?= e($t['status']) ?><?= $isActive ? ' (alvo ativo)' : '' ?></p>
      </div>
      <a class="text-blue-700 text-sm" href="index.php?route=targets_show&id=<?= (int)$t['id'] ?>">Ver detalhes</a>
    </div>

    <?php if (!empty($t['description'])): ?>
      <p class="text-sm text-slate-600 mt-2"><?= nl2br(e((string)$t['description'])) ?></p>
    <?php endif; ?>

    <div class="mt-3">
      <p class="text-sm">Progresso geral: <?= number_format($progress, 2, ',', '.') ?>%</p>
      <div class="w-full bg-slate-200 rounded h-2 mt-1"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($progress, 2, '.', '') ?>%"></div></div>
      <p class="text-xs text-slate-500 mt-1">Acoes realizadas: <?= (int)($t['done_actions'] ?? 0) ?> / <?= (int)($t['total_actions'] ?? 0) ?></p>
      <?php if (!empty($t['active_objective_title'])): ?>
        <p class="text-xs text-indigo-700 mt-1">Objetivo atual: <?= e((string)$t['active_objective_title']) ?></p>
      <?php endif; ?>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      <form method="POST" action="index.php?route=targets_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="status" value="active">
        <button class="text-emerald-700 text-sm">Marcar ativo</button>
      </form>
      <form method="POST" action="index.php?route=targets_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <input type="hidden" name="status" value="achieved">
        <button class="text-indigo-700 text-sm">Marcar atingido</button>
      </form>
      <form method="POST" action="index.php?route=targets_delete" onsubmit="return confirm('Excluir alvo e toda sua estrutura?');">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <button class="text-red-700 text-sm">Excluir</button>
      </form>
    </div>

    <details class="mt-2">
      <summary class="cursor-pointer text-blue-600 text-sm">Editar alvo</summary>
      <form method="POST" action="index.php?route=targets_update" class="grid grid-cols-2 gap-2 mt-2">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <input name="title" value="<?= e((string)$t['title']) ?>" class="border rounded p-1 col-span-2" required>
        <input name="target_amount" value="<?= e(isset($t['target_amount']) ? (string)$t['target_amount'] : '') ?>" class="border rounded p-1" placeholder="Valor alvo">
        <select name="status" class="border rounded p-1">
          <option value="active" <?= ($t['status'] === 'active') ? 'selected' : '' ?>>Ativo</option>
          <option value="achieved" <?= ($t['status'] === 'achieved') ? 'selected' : '' ?>>Atingido</option>
          <option value="paused" <?= ($t['status'] === 'paused') ? 'selected' : '' ?>>Pausado</option>
          <option value="cancelled" <?= ($t['status'] === 'cancelled') ? 'selected' : '' ?>>Cancelado</option>
        </select>
        <input type="date" name="start_date" value="<?= e((string)($t['start_date'] ?? '')) ?>" class="border rounded p-1">
        <input type="date" name="expected_end_date" value="<?= e((string)($t['expected_end_date'] ?? '')) ?>" class="border rounded p-1">
        <input name="description" value="<?= e((string)($t['description'] ?? '')) ?>" class="border rounded p-1 col-span-2" placeholder="Descricao">
        <input name="notes" value="<?= e((string)($t['notes'] ?? '')) ?>" class="border rounded p-1 col-span-2" placeholder="Observacoes">
        <button class="bg-blue-600 text-white rounded p-1 col-span-2">Salvar</button>
      </form>
    </details>
  </div>
<?php endforeach; ?>
</div>