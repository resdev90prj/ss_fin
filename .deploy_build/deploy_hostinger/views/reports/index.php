<h2 class="text-2xl font-bold mb-4">Relatórios Financeiros</h2>
<form method="GET" class="bg-white p-4 rounded shadow mb-4 grid md:grid-cols-6 gap-2">
  <input type="hidden" name="route" value="reports">
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="border rounded p-2">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="border rounded p-2">
  <select name="type" class="border rounded p-2"><option value="">Tipo</option><option value="income" <?= $filters['type']==='income'?'selected':'' ?>>Receita</option><option value="expense" <?= $filters['type']==='expense'?'selected':'' ?>>Despesa</option><option value="partner_withdrawal" <?= $filters['type']==='partner_withdrawal'?'selected':'' ?>>Retirada</option></select>
  <select name="category_id" class="border rounded p-2"><option value="">Categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$filters['category_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="account_id" class="border rounded p-2"><option value="">Conta</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (string)$filters['account_id']===(string)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <button class="bg-slate-700 text-white rounded p-2">Filtrar relatório</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm"><thead class="bg-slate-200"><tr><th class="p-2 text-left">Data</th><th class="p-2">Tipo</th><th class="p-2">Descrição</th><th class="p-2">Categoria</th><th class="p-2">Conta</th><th class="p-2">Valor</th></tr></thead><tbody>
<?php $total = 0; foreach($transactions as $t): $total += ($t['type']==='income' ? (float)$t['amount'] : -(float)$t['amount']); ?>
<tr class="border-t"><td class="p-2"><?= e($t['transaction_date']) ?></td><td class="p-2 text-center"><?= e($t['type']) ?></td><td class="p-2"><?= e($t['description']) ?></td><td class="p-2 text-center"><?= e($t['category_name']) ?></td><td class="p-2 text-center"><?= e($t['account_name']) ?></td><td class="p-2 text-center">R$ <?= number_format((float)$t['amount'],2,',','.') ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
<div class="mt-3 text-right font-semibold">Resultado líquido do período: R$ <?= number_format($total,2,',','.') ?></div>
