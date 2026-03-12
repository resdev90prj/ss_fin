<h2 class="text-2xl font-bold mb-4">Categorias</h2>
<form method="POST" action="index.php?route=categories_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-4 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="name" placeholder="Nome" required class="border rounded p-2">
  <select name="type" class="border rounded p-2"><option value="income">Receita</option><option value="expense">Despesa</option><option value="both">Ambos</option></select>
  <select name="status" class="border rounded p-2"><option value="active">Ativa</option><option value="inactive">Inativa</option></select>
  <button class="bg-slate-900 text-white rounded p-2">Criar categoria</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
<thead class="bg-slate-200"><tr><th class="p-2 text-left">Nome</th><th class="p-2">Tipo</th><th class="p-2">Padrão</th><th class="p-2">Status</th><th class="p-2">Ações</th></tr></thead>
<tbody>
<?php foreach ($categories as $c): ?>
<tr class="border-t">
<td class="p-2"><?= e($c['name']) ?></td><td class="p-2 text-center"><?= e($c['type']) ?></td><td class="p-2 text-center"><?= (int)$c['is_default'] ? 'Sim' : 'Não' ?></td><td class="p-2 text-center"><?= e($c['status']) ?></td>
<td class="p-2 text-center">
<?php if (!(int)$c['is_default']): ?>
<form method="POST" action="index.php?route=categories_delete" onsubmit="return confirm('Excluir categoria?');">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <button class="text-red-600">Excluir</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
