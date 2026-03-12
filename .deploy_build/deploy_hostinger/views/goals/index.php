<h2 class="text-2xl font-bold mb-4">Metas Financeiras</h2>
<form method="POST" action="index.php?route=goals_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-5 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="title" placeholder="Meta" required class="border rounded p-2">
  <input type="number" step="0.01" name="target_amount" placeholder="Valor alvo" required class="border rounded p-2">
  <input type="number" step="0.01" name="current_amount" placeholder="Valor atual" class="border rounded p-2" value="0">
  <input type="date" name="target_date" class="border rounded p-2">
  <button class="bg-slate-900 text-white rounded p-2">Criar meta</button>
</form>

<div class="grid md:grid-cols-2 gap-4">
<?php foreach($goals as $g): ?>
<div class="bg-white p-4 rounded shadow">
  <p class="font-semibold"><?= e($g['title']) ?></p>
  <p class="text-sm">Alvo: R$ <?= number_format((float)$g['target_amount'],2,',','.') ?></p>
  <p class="text-sm">Atual: R$ <?= number_format((float)$g['current_amount'],2,',','.') ?></p>
  <?php $progress = (float)$g['target_amount'] > 0 ? min(100, ((float)$g['current_amount'] / (float)$g['target_amount']) * 100) : 0; ?>
  <div class="w-full bg-slate-200 rounded h-2 mt-2"><div class="bg-emerald-600 h-2 rounded" style="width: <?= number_format($progress,2,'.','') ?>%"></div></div>
  <p class="text-xs mt-1"><?= number_format($progress,2,',','.') ?>%</p>
  <details class="mt-2"><summary class="text-blue-600 cursor-pointer">Editar</summary>
    <form method="POST" action="index.php?route=goals_update" class="grid grid-cols-2 gap-2 mt-2">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
      <input name="title" value="<?= e($g['title']) ?>" class="border rounded p-1 col-span-2">
      <input type="number" step="0.01" name="target_amount" value="<?= e((string)$g['target_amount']) ?>" class="border rounded p-1">
      <input type="number" step="0.01" name="current_amount" value="<?= e((string)$g['current_amount']) ?>" class="border rounded p-1">
      <input type="date" name="target_date" value="<?= e($g['target_date'] ?? '') ?>" class="border rounded p-1">
      <select name="status" class="border rounded p-1"><option value="active" <?= $g['status']==='active'?'selected':'' ?>>active</option><option value="completed" <?= $g['status']==='completed'?'selected':'' ?>>completed</option><option value="paused" <?= $g['status']==='paused'?'selected':'' ?>>paused</option></select>
      <button class="bg-blue-600 text-white rounded p-1">Salvar</button>
    </form>
  </details>
  <form method="POST" action="index.php?route=goals_delete" class="mt-2" onsubmit="return confirm('Excluir meta?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="text-red-600 text-sm">Excluir</button></form>
</div>
<?php endforeach; ?>
</div>
