<?php
$targetProgress = (float)($target['progress_percent'] ?? 0);
$targetDoneActions = (int)($target['done_actions'] ?? 0);
$targetTotalActions = (int)($target['total_actions'] ?? 0);
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <h2 class="text-2xl font-bold">Alvo: <?= e((string)$target['title']) ?></h2>
  <a href="index.php?route=targets" class="text-blue-700">&larr; Voltar para lista de alvos</a>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <div class="grid md:grid-cols-2 gap-4">
    <div>
      <p class="text-sm text-slate-500">Status</p>
      <p class="font-semibold"><?= e((string)$target['status']) ?></p>
      <p class="text-sm text-slate-500 mt-2">Valor alvo</p>
      <p class="font-semibold"><?= $target['target_amount'] !== null ? 'R$ ' . number_format((float)$target['target_amount'], 2, ',', '.') : '-' ?></p>
      <p class="text-sm text-slate-500 mt-2">Periodo</p>
      <p class="text-sm">Inicio: <?= e((string)($target['start_date'] ?? '-')) ?> | Previsao final: <?= e((string)($target['expected_end_date'] ?? '-')) ?></p>
      <?php if (!empty($target['active_objective_title'])): ?>
        <p class="text-sm text-indigo-700 mt-2">Objetivo atual: <?= e((string)$target['active_objective_title']) ?></p>
      <?php endif; ?>
    </div>
    <div>
      <p class="text-sm text-slate-500">Progresso geral do alvo</p>
      <p class="text-xl font-bold text-emerald-700"><?= number_format($targetProgress, 2, ',', '.') ?>%</p>
      <div class="w-full bg-slate-200 rounded h-3 mt-2"><div class="bg-emerald-600 h-3 rounded" style="width: <?= number_format($targetProgress, 2, '.', '') ?>%"></div></div>
      <p class="text-xs text-slate-500 mt-2">Acoes realizadas: <?= $targetDoneActions ?> / <?= $targetTotalActions ?></p>

      <div class="mt-3 flex flex-wrap gap-2">
        <form method="POST" action="index.php?route=targets_set_status">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
          <input type="hidden" name="status" value="active">
          <button class="text-emerald-700 text-sm">Marcar alvo ativo</button>
        </form>
        <form method="POST" action="index.php?route=targets_set_status">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
          <input type="hidden" name="status" value="achieved">
          <button class="text-indigo-700 text-sm">Marcar alvo atingido</button>
        </form>
        <form method="POST" action="index.php?route=targets_set_status">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
          <input type="hidden" name="status" value="paused">
          <button class="text-amber-700 text-sm">Pausar</button>
        </form>
      </div>
    </div>
  </div>

  <?php if (!empty($target['description'])): ?>
    <div class="mt-4 text-sm text-slate-700">
      <p class="text-slate-500">Descricao</p>
      <p><?= nl2br(e((string)$target['description'])) ?></p>
    </div>
  <?php endif; ?>

  <?php if (!empty($target['notes'])): ?>
    <div class="mt-3 text-sm text-slate-700">
      <p class="text-slate-500">Observacoes</p>
      <p><?= nl2br(e((string)$target['notes'])) ?></p>
    </div>
  <?php endif; ?>
</div>

<form method="POST" action="index.php?route=objectives_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="target_id" value="<?= (int)$target['id'] ?>">
  <input name="title" placeholder="Novo objetivo" required class="border rounded p-2 md:col-span-2">
  <input type="date" name="start_date" class="border rounded p-2">
  <input type="number" min="1" name="term_months" value="3" class="border rounded p-2" placeholder="Prazo em meses">
  <select name="status" class="border rounded p-2">
    <option value="active">Ativo</option>
    <option value="adjusted" selected>Ajustado</option>
    <option value="finished">Finalizado</option>
    <option value="achieved">Atingido</option>
  </select>
  <input name="description" placeholder="Descricao do objetivo" class="border rounded p-2 md:col-span-3">
  <input name="notes" placeholder="Observacoes" class="border rounded p-2 md:col-span-2">
  <button class="bg-slate-900 text-white rounded p-2">Adicionar objetivo</button>
</form>

<div class="space-y-5">
<?php foreach ($objectives as $objective): ?>
  <?php
    $objectiveProgress = (float)($objective['progress_percent'] ?? 0);
    $isObjectiveActive = ($objective['status'] ?? '') === 'active';
    $decisions = $objective['decisions'] ?? [];
    $decisionCount = count($decisions);
  ?>
  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="font-semibold text-lg"><?= e((string)$objective['title']) ?></p>
        <p class="text-xs text-slate-500">Status: <?= e((string)$objective['status']) ?><?= $isObjectiveActive ? ' (objetivo atual)' : '' ?></p>
        <p class="text-xs text-slate-500">Inicio: <?= e((string)($objective['start_date'] ?? '-')) ?> | Prazo: <?= (int)($objective['term_months'] ?? 3) ?> mes(es) | Deadline: <?= e((string)($objective['deadline_date'] ?? '-')) ?></p>
      </div>
      <div class="text-right">
        <p class="text-sm text-emerald-700 font-semibold"><?= number_format($objectiveProgress, 2, ',', '.') ?>%</p>
        <p class="text-xs text-slate-500">Acoes: <?= (int)($objective['done_actions'] ?? 0) ?> / <?= (int)($objective['total_actions'] ?? 0) ?></p>
      </div>
    </div>

    <?php if (!empty($objective['description'])): ?>
      <p class="text-sm text-slate-700 mt-2"><?= nl2br(e((string)$objective['description'])) ?></p>
    <?php endif; ?>

    <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($objectiveProgress, 2, '.', '') ?>%"></div></div>

    <div class="mt-3 flex flex-wrap gap-2 text-sm">
      <form method="POST" action="index.php?route=objectives_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <input type="hidden" name="status" value="active">
        <button class="text-emerald-700">Ativar</button>
      </form>
      <form method="POST" action="index.php?route=objectives_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <input type="hidden" name="status" value="finished">
        <button class="text-blue-700">Finalizar</button>
      </form>
      <form method="POST" action="index.php?route=objectives_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <input type="hidden" name="status" value="achieved">
        <button class="text-indigo-700">Marcar atingido</button>
      </form>
      <form method="POST" action="index.php?route=objectives_set_status">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <input type="hidden" name="status" value="adjusted">
        <button class="text-amber-700">Ajustar</button>
      </form>
      <form method="POST" action="index.php?route=objectives_delete" onsubmit="return confirm('Excluir objetivo e toda estrutura interna?');">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <button class="text-red-700">Excluir</button>
      </form>
    </div>

    <details class="mt-2">
      <summary class="cursor-pointer text-blue-600 text-sm">Editar objetivo</summary>
      <form method="POST" action="index.php?route=objectives_update" class="grid md:grid-cols-6 gap-2 mt-2">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$objective['id'] ?>">
        <input name="title" value="<?= e((string)$objective['title']) ?>" class="border rounded p-1 md:col-span-2" required>
        <input type="date" name="start_date" value="<?= e((string)($objective['start_date'] ?? '')) ?>" class="border rounded p-1">
        <input type="number" min="1" name="term_months" value="<?= (int)($objective['term_months'] ?? 3) ?>" class="border rounded p-1">
        <select name="status" class="border rounded p-1">
          <option value="active" <?= $objective['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
          <option value="finished" <?= $objective['status'] === 'finished' ? 'selected' : '' ?>>Finalizado</option>
          <option value="adjusted" <?= $objective['status'] === 'adjusted' ? 'selected' : '' ?>>Ajustado</option>
          <option value="achieved" <?= $objective['status'] === 'achieved' ? 'selected' : '' ?>>Atingido</option>
        </select>
        <input name="description" value="<?= e((string)($objective['description'] ?? '')) ?>" class="border rounded p-1 md:col-span-3" placeholder="Descricao">
        <input name="notes" value="<?= e((string)($objective['notes'] ?? '')) ?>" class="border rounded p-1 md:col-span-2" placeholder="Observacoes">
        <button class="bg-blue-600 text-white rounded p-1">Salvar objetivo</button>
      </form>
    </details>

    <div class="mt-4 border-t pt-4">
      <div class="flex items-center justify-between gap-2 mb-2">
        <h4 class="font-semibold">Decisoes (<?= $decisionCount ?>/3)</h4>
        <?php if ($decisionCount >= 3): ?>
          <span class="text-xs text-amber-700">Limite de 3 decisoes atingido</span>
        <?php endif; ?>
      </div>

      <?php if ($decisionCount < 3): ?>
      <form method="POST" action="index.php?route=decisions_store" class="grid md:grid-cols-5 gap-2 mb-3">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="objective_id" value="<?= (int)$objective['id'] ?>">
        <input name="title" placeholder="Nova decisao" required class="border rounded p-1 md:col-span-2">
        <input name="description" placeholder="Descricao" class="border rounded p-1 md:col-span-2">
        <input type="number" min="1" max="3" name="order_no" placeholder="Ordem" class="border rounded p-1">
        <select name="status" class="border rounded p-1">
          <option value="pending">Pendente</option>
          <option value="in_progress">Em andamento</option>
          <option value="done">Concluida</option>
          <option value="cancelled">Cancelada</option>
        </select>
        <button class="bg-slate-800 text-white rounded p-1">Adicionar decisao</button>
      </form>
      <?php endif; ?>

      <div class="space-y-3">
      <?php foreach ($decisions as $decision): ?>
        <?php $decisionProgress = (float)($decision['progress_percent'] ?? 0); ?>
        <div class="border rounded p-3 bg-slate-50">
          <div class="flex flex-wrap justify-between gap-3">
            <div>
              <p class="font-semibold"><?= e((string)$decision['title']) ?></p>
              <p class="text-xs text-slate-500">Ordem <?= (int)($decision['order_no'] ?? 1) ?> | Status <?= e((string)$decision['status']) ?></p>
              <?php if (!empty($decision['description'])): ?><p class="text-sm mt-1"><?= e((string)$decision['description']) ?></p><?php endif; ?>
            </div>
            <div class="text-right">
              <p class="text-sm text-emerald-700"><?= number_format($decisionProgress, 2, ',', '.') ?>%</p>
              <p class="text-xs text-slate-500">Acoes: <?= (int)($decision['done_actions'] ?? 0) ?> / <?= (int)($decision['total_actions'] ?? 0) ?></p>
            </div>
          </div>
          <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($decisionProgress, 2, '.', '') ?>%"></div></div>

          <details class="mt-2">
            <summary class="cursor-pointer text-blue-600 text-sm">Editar decisao</summary>
            <form method="POST" action="index.php?route=decisions_update" class="grid md:grid-cols-5 gap-2 mt-2">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$decision['id'] ?>">
              <input name="title" value="<?= e((string)$decision['title']) ?>" class="border rounded p-1 md:col-span-2" required>
              <input name="description" value="<?= e((string)($decision['description'] ?? '')) ?>" class="border rounded p-1 md:col-span-2">
              <input type="number" min="1" max="3" name="order_no" value="<?= (int)($decision['order_no'] ?? 1) ?>" class="border rounded p-1">
              <select name="status" class="border rounded p-1">
                <option value="pending" <?= $decision['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                <option value="in_progress" <?= $decision['status'] === 'in_progress' ? 'selected' : '' ?>>Em andamento</option>
                <option value="done" <?= $decision['status'] === 'done' ? 'selected' : '' ?>>Concluida</option>
                <option value="cancelled" <?= $decision['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
              </select>
              <button class="bg-blue-600 text-white rounded p-1">Salvar decisao</button>
            </form>
          </details>

          <form method="POST" action="index.php?route=decisions_delete" class="mt-2" onsubmit="return confirm('Excluir decisao e acoes associadas?');">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$decision['id'] ?>">
            <button class="text-red-700 text-sm">Excluir decisao</button>
          </form>

          <div class="mt-3 border-t pt-3">
            <h5 class="font-semibold mb-2">Acoes</h5>
            <form method="POST" action="index.php?route=actions_store" class="grid md:grid-cols-6 gap-2 mb-3">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="decision_id" value="<?= (int)$decision['id'] ?>">
              <input name="title" placeholder="Nova acao" required class="border rounded p-1 md:col-span-2">
              <input name="description" placeholder="Descricao" class="border rounded p-1 md:col-span-2">
              <input type="date" name="planned_date" class="border rounded p-1">
              <select name="status" class="border rounded p-1">
                <option value="pending">Pendente</option>
                <option value="in_progress">Em andamento</option>
                <option value="completed">Realizado</option>
                <option value="cancelled">Cancelado</option>
              </select>
              <input name="notes" placeholder="Observacoes/aprendizados" class="border rounded p-1 md:col-span-4">
              <button class="bg-slate-700 text-white rounded p-1">Adicionar acao</button>
            </form>

            <?php $actions = $decision['actions'] ?? []; ?>
            <?php if (empty($actions)): ?>
              <p class="text-sm text-slate-500">Sem acoes cadastradas nesta decisao.</p>
            <?php else: ?>
              <div class="space-y-2">
              <?php foreach ($actions as $action): ?>
                <div class="bg-white border rounded p-2">
                  <div class="flex flex-wrap justify-between gap-2">
                    <div>
                      <p class="font-medium"><?= e((string)$action['title']) ?></p>
                      <p class="text-xs text-slate-500">Status <?= e((string)$action['status']) ?> | Prevista <?= e((string)($action['planned_date'] ?? '-')) ?> | Conclusao <?= e((string)($action['completed_at'] ?? '-')) ?></p>
                      <?php if (!empty($action['description'])): ?><p class="text-sm mt-1"><?= e((string)$action['description']) ?></p><?php endif; ?>
                      <?php if (!empty($action['notes'])): ?><p class="text-xs mt-1 text-slate-600">Obs: <?= e((string)$action['notes']) ?></p><?php endif; ?>
                    </div>
                    <div class="text-right space-y-1">
                      <form method="POST" action="index.php?route=actions_toggle_done">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
                        <input type="hidden" name="done" value="<?= (int)$action['is_done'] === 1 ? '0' : '1' ?>">
                        <button class="text-emerald-700 text-sm"><?= (int)$action['is_done'] === 1 ? 'Reabrir' : 'Marcar realizado' ?></button>
                      </form>
                      <form method="POST" action="index.php?route=actions_delete" onsubmit="return confirm('Excluir acao?');">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
                        <button class="text-red-700 text-sm">Excluir</button>
                      </form>
                    </div>
                  </div>

                  <details class="mt-2">
                    <summary class="cursor-pointer text-blue-600 text-sm">Editar acao</summary>
                    <form method="POST" action="index.php?route=actions_update" class="grid md:grid-cols-6 gap-2 mt-2">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
                      <input name="title" value="<?= e((string)$action['title']) ?>" class="border rounded p-1 md:col-span-2" required>
                      <input name="description" value="<?= e((string)($action['description'] ?? '')) ?>" class="border rounded p-1 md:col-span-2">
                      <input type="date" name="planned_date" value="<?= e((string)($action['planned_date'] ?? '')) ?>" class="border rounded p-1">
                      <select name="status" class="border rounded p-1">
                        <option value="pending" <?= $action['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="in_progress" <?= $action['status'] === 'in_progress' ? 'selected' : '' ?>>Em andamento</option>
                        <option value="completed" <?= $action['status'] === 'completed' ? 'selected' : '' ?>>Realizado</option>
                        <option value="cancelled" <?= $action['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                      </select>
                      <label class="text-xs flex items-center gap-1"><input type="checkbox" name="is_done" value="1" <?= (int)$action['is_done'] === 1 ? 'checked' : '' ?>> Realizado</label>
                      <input type="date" name="completed_at" value="<?= e((string)($action['completed_at'] ?? '')) ?>" class="border rounded p-1">
                      <input name="notes" value="<?= e((string)($action['notes'] ?? '')) ?>" class="border rounded p-1 md:col-span-3" placeholder="Observacoes">
                      <button class="bg-blue-600 text-white rounded p-1">Salvar acao</button>
                    </form>
                  </details>
                </div>
              <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if (empty($objectives)): ?>
  <div class="bg-white p-4 rounded shadow text-sm text-slate-500">Nenhum objetivo cadastrado para este alvo.</div>
<?php endif; ?>
</div>