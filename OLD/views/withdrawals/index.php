<h2 class="text-2xl font-bold mb-4">Retiradas do Sócio</h2>
<p class="mb-3 text-sm text-slate-600">Tipos suportados: pró-labore, distribuição de lucro, retirada do sócio e gasto pessoal pago pela empresa.</p>
<form method="POST" action="index.php?route=withdrawals_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-4 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="description" placeholder="Descrição" required class="border rounded p-2 md:col-span-2">
  <input name="amount" type="number" step="0.01" placeholder="Valor" required class="border rounded p-2">
  <input name="transaction_date" type="date" value="<?= date('Y-m-d') ?>" class="border rounded p-2">
  <select name="payment_method" class="border rounded p-2"><option>pró-labore</option><option>distribuição de lucro</option><option>retirada do sócio</option><option>gasto pessoal pago pela empresa</option></select>
  <select name="mode" class="border rounded p-2"><option value="transitorio">Transitório</option><option value="transicao" selected>Transição</option><option value="ideal">Ideal</option></select>
  <select name="category_id" required class="border rounded p-2"><option value="">Categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="account_id" required class="border rounded p-2"><option value="">Conta</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select name="box_id" class="border rounded p-2"><option value="">Caixa</option><?php foreach($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
  <input name="notes" placeholder="Observação" class="border rounded p-2 md:col-span-2">
  <button class="bg-slate-900 text-white rounded p-2">Registrar retirada</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
<thead class="bg-slate-200"><tr><th class="p-2 text-left">Descrição</th><th class="p-2">Valor</th><th class="p-2">Data</th><th class="p-2">Tipo retirada</th></tr></thead>
<tbody>
<?php foreach ($transactions as $t): ?>
<tr class="border-t"><td class="p-2"><?= e($t['description']) ?></td><td class="p-2 text-center">R$ <?= number_format((float)$t['amount'],2,',','.') ?></td><td class="p-2 text-center"><?= e($t['transaction_date']) ?></td><td class="p-2 text-center"><?= e($t['payment_method']) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
