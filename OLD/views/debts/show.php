<?php
$interestMode = ($debt['interest_mode'] ?? 'percent') === 'fixed' ? 'R$' : '%';
$penaltyMode = ($debt['penalty_mode'] ?? 'percent') === 'fixed' ? 'R$' : '%';
$chargesEnabled = isset($chargesEnabled) ? (bool)$chargesEnabled : false;
$hasAnyPaidInstallment = false;
foreach ($installments as $scanInstallment) {
  if ($scanInstallment['status'] === 'paid' || (float)$scanInstallment['paid_amount'] > 0) {
    $hasAnyPaidInstallment = true;
    break;
  }
}
?>

<?php if (!$chargesEnabled): ?>
<div class="mb-3 rounded bg-amber-100 text-amber-800 p-3 text-sm">
  Juros/multa nao estao ativos no banco atual. A composicao mensal so funciona apos adicionar as colunas de configuracao na tabela <code>debts</code>.
</div>
<?php endif; ?>

<h2 class="text-2xl font-bold mb-4">Parcelas da Dívida: <?= e($debt['description']) ?></h2>
<p class="mb-2">
  Total: <strong>R$ <?= number_format((float)$debt['total_amount'],2,',','.') ?></strong> |
  Pago: <strong>R$ <?= number_format((float)$debt['paid_amount'],2,',','.') ?></strong> |
  Saldo: <strong>R$ <?= number_format((float)$debt['total_amount'] - (float)$debt['paid_amount'],2,',','.') ?></strong>
</p>
<p class="mb-4 text-sm text-slate-600">
  Juros: <strong><?= e($interestMode) ?> <?= number_format((float)($debt['interest_value'] ?? 0),2,',','.') ?></strong> |
  Multa: <strong><?= e($penaltyMode) ?> <?= number_format((float)($debt['penalty_value'] ?? 0),2,',','.') ?></strong>
</p>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
<thead class="bg-slate-200"><tr><th class="p-2">#</th><th class="p-2">Vencimento</th><th class="p-2">Valor</th><th class="p-2">Pago</th><th class="p-2">Status</th><th class="p-2">Ações</th></tr></thead>
<tbody>
<?php foreach($installments as $i): ?>
<?php
  $isPaidInstallment = $i['status'] === 'paid' || (float)$i['paid_amount'] > 0;
  $canRefundInstallment = (float)$i['paid_amount'] > 0;
  $canDeleteInstallment = !$isPaidInstallment && !$hasAnyPaidInstallment;
?>
<tr class="border-t">
  <td class="p-2 text-center"><?= (int)$i['installment_number'] ?></td>
  <td class="p-2 text-center"><?= e($i['due_date']) ?></td>
  <td class="p-2 text-center">R$ <?= number_format((float)$i['amount'],2,',','.') ?></td>
  <td class="p-2 text-center">R$ <?= number_format((float)$i['paid_amount'],2,',','.') ?></td>
  <td class="p-2 text-center"><?= e($i['status']) ?></td>
  <td class="p-2">
    <div class="space-y-2">
      <form method="POST" action="index.php?route=debts_pay_installment" class="flex gap-2 items-center">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="installment_id" value="<?= (int)$i['id'] ?>">
        <input type="number" step="0.01" min="0.01" name="amount" placeholder="Valor" class="border rounded p-1 w-24">
        <button class="bg-emerald-600 text-white rounded px-2 py-1">Pagar</button>
      </form>

      <?php if ($canRefundInstallment): ?>
      <form method="POST" action="index.php?route=debts_refund_installment" class="flex gap-2 items-center">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="installment_id" value="<?= (int)$i['id'] ?>">
        <input type="number" step="0.01" min="0.01" max="<?= number_format((float)$i['paid_amount'], 2, '.', '') ?>" name="amount" placeholder="Estorno" class="border rounded p-1 w-24">
        <button class="bg-amber-600 text-white rounded px-2 py-1">Estornar</button>
      </form>
      <?php endif; ?>

      <?php if ($canDeleteInstallment): ?>
        <form method="POST" action="index.php?route=debts_delete_installment" onsubmit="return confirm('Excluir parcela pendente?');">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="installment_id" value="<?= (int)$i['id'] ?>">
          <input type="hidden" name="debt_id" value="<?= (int)$debt['id'] ?>">
          <button class="text-red-600 text-sm">Excluir parcela</button>
        </form>
      <?php else: ?>
        <span class="text-xs text-slate-400">Bloqueado: estorne pagamentos para excluir</span>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
