<?php $chargesEnabled = isset($chargesEnabled) ? (bool)$chargesEnabled : false; ?>

<h2 class="text-2xl font-bold mb-4">Dividas</h2>
<p class="text-sm text-slate-600 mb-3">Exclusao so e permitida para pendentes. Se houver parcela paga, use o estorno em "Ver parcelas" antes de excluir.</p>

<?php if (!$chargesEnabled): ?>
<div class="mb-4 rounded bg-amber-100 text-amber-800 p-3 text-sm">
  Juros e multa nao estao habilitados no banco atual. Adicione as colunas
  <code>interest_mode</code>, <code>interest_value</code>, <code>penalty_mode</code>,
  <code>penalty_value</code> e <code>last_charge_month</code> na tabela <code>debts</code>.
</div>
<?php endif; ?>

<form method="POST" action="index.php?route=debts_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="description" placeholder="Descricao da divida" required class="border rounded p-2 md:col-span-2">
  <input name="creditor" placeholder="Credor" class="border rounded p-2">
  <input type="number" step="0.01" name="total_amount" placeholder="Valor total" required class="border rounded p-2">
  <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="border rounded p-2">
  <input type="number" min="1" max="31" name="due_day" placeholder="Dia vencimento" class="border rounded p-2">

  <input type="number" min="1" name="installments_count" value="1" class="border rounded p-2" placeholder="Qtd parcelas">
  <select name="account_id" class="border rounded p-2">
    <option value="">Conta</option>
    <?php foreach ($accounts as $a): ?>
      <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="interest_mode" class="border rounded p-2">
    <option value="percent">Juros (%)</option>
    <option value="fixed">Juros (R$)</option>
  </select>
  <input type="text" name="interest_value" value="0" class="border rounded p-2" placeholder="Valor juros">

  <select name="penalty_mode" class="border rounded p-2">
    <option value="percent">Multa (%)</option>
    <option value="fixed">Multa (R$)</option>
  </select>
  <input type="text" name="penalty_value" value="0" class="border rounded p-2" placeholder="Valor multa">

  <input name="notes" placeholder="Observacao" class="border rounded p-2 md:col-span-5">
  <button class="bg-slate-900 text-white rounded p-2">Cadastrar divida</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
<thead class="bg-slate-200"><tr><th class="p-2 text-left">Descricao</th><th class="p-2">Credor</th><th class="p-2">Total</th><th class="p-2">Pago</th><th class="p-2">Saldo</th><th class="p-2">Juros</th><th class="p-2">Multa</th><th class="p-2">Status</th><th class="p-2">Acoes</th></tr></thead>
<tbody>
<?php foreach ($debts as $d): ?>
<?php
  $interestMode = ($d['interest_mode'] ?? 'percent') === 'fixed' ? 'R$' : '%';
  $penaltyMode = ($d['penalty_mode'] ?? 'percent') === 'fixed' ? 'R$' : '%';
  $hasPaidInstallments = (int)($d['paid_installments_count'] ?? 0) > 0 || (float)($d['paid_amount'] ?? 0) > 0;
  $canDeleteDebt = !$hasPaidInstallments;
?>
<tr class="border-t">
  <td class="p-2"><?= e($d['description']) ?></td>
  <td class="p-2 text-center"><?= e($d['creditor'] ?? '-') ?></td>
  <td class="p-2 text-center">R$ <?= number_format((float)$d['total_amount'], 2, ',', '.') ?></td>
  <td class="p-2 text-center">R$ <?= number_format((float)$d['paid_amount'], 2, ',', '.') ?></td>
  <td class="p-2 text-center">R$ <?= number_format((float)$d['remaining'], 2, ',', '.') ?></td>
  <td class="p-2 text-center"><?= e($interestMode) ?> <?= number_format((float)($d['interest_value'] ?? 0), 2, ',', '.') ?></td>
  <td class="p-2 text-center"><?= e($penaltyMode) ?> <?= number_format((float)($d['penalty_value'] ?? 0), 2, ',', '.') ?></td>
  <td class="p-2 text-center"><?= e($d['status']) ?></td>
  <td class="p-2 text-center space-y-2">
    <a class="text-blue-600 block" href="index.php?route=debts_show&id=<?= (int)$d['id'] ?>">Ver parcelas</a>
    <?php if ($canDeleteDebt): ?>
      <form method="POST" action="index.php?route=debts_delete" onsubmit="return confirm('Excluir divida pendente?');">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <button class="text-red-600">Excluir divida</button>
      </form>
    <?php else: ?>
      <span class="text-xs text-slate-400">Bloqueado: ha parcela paga</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
