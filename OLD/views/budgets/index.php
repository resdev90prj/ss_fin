<h2 class="text-2xl font-bold mb-4">Orçamento Mensal</h2>
<form method="POST" action="index.php?route=budgets_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-4 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <select name="category_id" required class="border rounded p-2"><option value="">Categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <input type="month" name="month_ref" value="<?= date('Y-m') ?>" required class="border rounded p-2">
  <input type="number" step="0.01" name="amount_limit" placeholder="Limite" required class="border rounded p-2">
  <button class="bg-slate-900 text-white rounded p-2">Salvar orçamento</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm"><thead class="bg-slate-200"><tr><th class="p-2 text-left">Categoria</th><th class="p-2">Mês</th><th class="p-2">Limite</th><th class="p-2">Ações</th></tr></thead><tbody>
<?php foreach($budgets as $b): ?>
<tr class="border-t"><td class="p-2"><?= e($b['category_name']) ?></td><td class="p-2 text-center"><?= e($b['month_ref']) ?></td><td class="p-2 text-center">R$ <?= number_format((float)$b['amount_limit'],2,',','.') ?></td><td class="p-2 text-center"><form method="POST" action="index.php?route=budgets_delete" onsubmit="return confirm('Remover orçamento?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="text-red-600">Excluir</button></form></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
