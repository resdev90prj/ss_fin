<h2 class="text-2xl font-bold mb-4">Contas Financeiras</h2>
<form method="POST" action="index.php?route=accounts_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="name" placeholder="Nome da conta" required class="border rounded p-2 md:col-span-2">
  <select name="type" class="border rounded p-2"><option value="PF">PF</option><option value="PJ">PJ</option></select>
  <input name="institution" placeholder="Instituição" class="border rounded p-2">
  <input type="number" step="0.01" name="initial_balance" placeholder="Saldo inicial" class="border rounded p-2">
  <button class="bg-slate-900 text-white rounded p-2">Criar conta</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
  <thead class="bg-slate-200"><tr><th class="p-2 text-left">Nome</th><th class="p-2">Tipo</th><th class="p-2">Instituição</th><th class="p-2">Saldo Inicial</th><th class="p-2">Status</th><th class="p-2">Ações</th></tr></thead>
  <tbody>
  <?php foreach ($accounts as $a): ?>
  <tr class="border-t">
    <td class="p-2"><?= e($a['name']) ?></td><td class="p-2 text-center"><?= e($a['type']) ?></td><td class="p-2 text-center"><?= e($a['institution'] ?? '-') ?></td>
    <td class="p-2 text-center">R$ <?= number_format((float)$a['initial_balance'],2,',','.') ?></td><td class="p-2 text-center"><?= e($a['status']) ?></td>
    <td class="p-2 text-center">
      <form method="POST" action="index.php?route=accounts_toggle">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="text-blue-600">Ativar/Inativar</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
