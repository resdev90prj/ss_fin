<h2 class="text-2xl font-bold mb-4">Caixas Virtuais</h2>
<form method="POST" action="index.php?route=boxes_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="name" placeholder="Nome do caixa" required class="border rounded p-2">
  <input name="objective" placeholder="Objetivo" class="border rounded p-2 md:col-span-2">
  <select name="account_id" class="border rounded p-2">
    <option value="">Sem conta</option>
    <?php foreach ($accounts as $acc): ?>
      <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" step="0.01" name="balance" placeholder="Saldo" class="border rounded p-2">
  <button class="bg-slate-900 text-white rounded p-2">Criar caixa</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
  <thead class="bg-slate-200"><tr><th class="p-2 text-left">Nome</th><th class="p-2">Conta</th><th class="p-2">Objetivo</th><th class="p-2">Saldo</th><th class="p-2">Status</th></tr></thead>
  <tbody>
  <?php foreach ($boxes as $b): ?>
  <tr class="border-t"><td class="p-2"><?= e($b['name']) ?></td><td class="p-2 text-center"><?= e($b['account_name'] ?? '-') ?></td><td class="p-2 text-center"><?= e($b['objective'] ?? '-') ?></td><td class="p-2 text-center">R$ <?= number_format((float)$b['balance'],2,',','.') ?></td><td class="p-2 text-center"><?= e($b['status']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
