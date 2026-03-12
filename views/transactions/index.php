<?php
$othersPendingCount = (int)($othersPendingCount ?? 0);
$prioritizeOthers = !empty($prioritizeOthers);
$baseParams = ['route' => 'transactions'];
foreach (['from', 'to', 'type', 'category_id', 'account_id'] as $field) {
    $value = trim((string)($filters[$field] ?? ''));
    if ($value !== '') {
        $baseParams[$field] = $value;
    }
}
$clearPrioritizeUrl = 'index.php?' . http_build_query($baseParams);
?>

<h2 class="text-2xl font-bold mb-4">Receitas e Despesas</h2>

<form method="GET" class="bg-white p-4 rounded shadow mb-4 grid md:grid-cols-6 gap-2">
  <input type="hidden" name="route" value="transactions">
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="border rounded p-2">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="border rounded p-2">
  <select name="type" class="border rounded p-2">
    <option value="">Tipo</option><option value="income" <?= $filters['type']==='income'?'selected':'' ?>>Receita</option><option value="expense" <?= $filters['type']==='expense'?'selected':'' ?>>Despesa</option><option value="partner_withdrawal" <?= $filters['type']==='partner_withdrawal'?'selected':'' ?>>Retirada</option>
  </select>
  <select name="category_id" class="border rounded p-2"><option value="">Categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$filters['category_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select name="account_id" class="border rounded p-2"><option value="">Conta</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (string)$filters['account_id']===(string)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <button class="bg-slate-700 text-white rounded p-2">Filtrar</button>
</form>

<div class="bg-white p-4 rounded shadow mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
  <div>
    <p class="text-sm text-slate-700">
      Pendentes em <strong>Outros gastos</strong> (filtro atual): <strong><?= $othersPendingCount ?></strong>
    </p>
    <?php if ($prioritizeOthers): ?>
      <p class="text-xs text-amber-700 mt-1">Ordenação ativa: lançamentos em "Outros gastos" aparecem primeiro para revisão manual.</p>
    <?php endif; ?>
  </div>
  <div class="flex gap-2">
    <form method="POST" action="index.php?route=transactions_auto_classify_others">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="from" value="<?= e((string)($filters['from'] ?? '')) ?>">
      <input type="hidden" name="to" value="<?= e((string)($filters['to'] ?? '')) ?>">
      <input type="hidden" name="type" value="<?= e((string)($filters['type'] ?? '')) ?>">
      <input type="hidden" name="category_id" value="<?= e((string)($filters['category_id'] ?? '')) ?>">
      <input type="hidden" name="account_id" value="<?= e((string)($filters['account_id'] ?? '')) ?>">
      <button type="submit" class="bg-emerald-700 hover:bg-emerald-600 text-white rounded px-3 py-2 text-sm">
        Classificar automático (Outros gastos)
      </button>
    </form>
    <?php if ($prioritizeOthers): ?>
      <a href="<?= e($clearPrioritizeUrl) ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-800 rounded px-3 py-2 text-sm">Limpar priorização</a>
    <?php endif; ?>
  </div>
</div>

<form method="POST" action="index.php?route=transactions_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-4 gap-3" id="transaction-create-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input id="txn-description" name="description" placeholder="Descrição" required class="border rounded p-2 md:col-span-2">
  <input name="amount" type="number" step="0.01" placeholder="Valor" required class="border rounded p-2">
  <input name="transaction_date" type="date" value="<?= date('Y-m-d') ?>" class="border rounded p-2">
  <select id="txn-type" name="type" class="border rounded p-2"><option value="income">Receita</option><option value="expense" selected>Despesa</option><option value="partner_withdrawal">Retirada</option><option value="transfer">Transferência</option></select>
  <select name="mode" class="border rounded p-2"><option value="transitorio">Transitório</option><option value="transicao" selected>Transição</option><option value="ideal">Ideal</option></select>
  <select id="txn-category" name="category_id" required class="border rounded p-2"><option value="">Categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <p id="txn-category-hint" class="text-xs md:col-span-4 text-slate-500 hidden"></p>
  <select name="account_id" required class="border rounded p-2"><option value="">Conta</option><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
  <select name="box_id" class="border rounded p-2"><option value="">Caixa</option><?php foreach($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
  <input name="payment_method" placeholder="Forma de pagamento" class="border rounded p-2">
  <input name="notes" placeholder="Observação" class="border rounded p-2 md:col-span-2">
  <button class="bg-slate-900 text-white rounded p-2">Cadastrar transação</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
<thead class="bg-slate-200"><tr><th class="p-2 text-left">Descrição</th><th class="p-2">Tipo</th><th class="p-2">Valor</th><th class="p-2">Categoria</th><th class="p-2">Conta</th><th class="p-2">Data</th><th class="p-2">Ações</th></tr></thead>
<tbody>
<?php foreach ($transactions as $t): ?>
<?php $isOthers = mb_strtolower((string)($t['category_name'] ?? ''), 'UTF-8') === mb_strtolower('Outros gastos', 'UTF-8'); ?>
<tr class="border-t align-top <?= $isOthers ? 'bg-amber-50' : '' ?>">
<td class="p-2"><?= e($t['description']) ?><div class="text-xs text-slate-500"><?= e($t['payment_method'] ?? '') ?></div></td>
<td class="p-2 text-center"><?= e($t['type']) ?></td>
<td class="p-2 text-center">R$ <?= number_format((float)$t['amount'],2,',','.') ?></td>
<td class="p-2 text-center"><?= e($t['category_name']) ?></td>
<td class="p-2 text-center"><?= e($t['account_name']) ?></td>
<td class="p-2 text-center"><?= e($t['transaction_date']) ?></td>
<td class="p-2 text-center space-y-2">
<form method="POST" action="index.php?route=transactions_delete" onsubmit="return confirm('Excluir transação?');">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
  <button class="text-red-600">Excluir</button>
</form>
<details>
  <summary class="cursor-pointer text-blue-600">Editar</summary>
  <form method="POST" action="index.php?route=transactions_update" class="mt-2 space-y-1">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <input name="description" value="<?= e($t['description']) ?>" class="border rounded p-1 w-full">
    <input type="number" step="0.01" name="amount" value="<?= e((string)$t['amount']) ?>" class="border rounded p-1 w-full">
    <input type="date" name="transaction_date" value="<?= e($t['transaction_date']) ?>" class="border rounded p-1 w-full">
    <select name="type" class="border rounded p-1 w-full"><option value="income" <?= $t['type']==='income'?'selected':'' ?>>income</option><option value="expense" <?= $t['type']==='expense'?'selected':'' ?>>expense</option><option value="partner_withdrawal" <?= $t['type']==='partner_withdrawal'?'selected':'' ?>>partner_withdrawal</option><option value="transfer" <?= $t['type']==='transfer'?'selected':'' ?>>transfer</option></select>
    <select name="mode" class="border rounded p-1 w-full"><option value="transitorio" <?= $t['mode']==='transitorio'?'selected':'' ?>>transitorio</option><option value="transicao" <?= $t['mode']==='transicao'?'selected':'' ?>>transicao</option><option value="ideal" <?= $t['mode']==='ideal'?'selected':'' ?>>ideal</option></select>
    <select name="category_id" class="border rounded p-1 w-full"><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$t['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="account_id" class="border rounded p-1 w-full"><?php foreach($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$t['account_id']===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
    <select name="box_id" class="border rounded p-1 w-full"><option value="">Sem caixa</option><?php foreach($boxes as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$t['box_id']===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
    <input name="payment_method" value="<?= e($t['payment_method'] ?? '') ?>" placeholder="Forma de pagamento" class="border rounded p-1 w-full">
    <input name="notes" value="<?= e($t['notes'] ?? '') ?>" placeholder="Obs" class="border rounded p-1 w-full">
    <button class="bg-blue-600 text-white rounded px-2 py-1 w-full">Salvar</button>
  </form>
</details>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<script>
(function () {
  const form = document.getElementById('transaction-create-form');
  if (!form) {
    return;
  }

  const descriptionInput = document.getElementById('txn-description');
  const typeSelect = document.getElementById('txn-type');
  const categorySelect = document.getElementById('txn-category');
  const hint = document.getElementById('txn-category-hint');

  if (!descriptionInput || !typeSelect || !categorySelect || !hint) {
    return;
  }

  let debounceTimer = null;
  let categoryTouched = false;

  categorySelect.addEventListener('change', () => {
    categoryTouched = true;
  });

  const hideHint = () => {
    hint.textContent = '';
    hint.classList.add('hidden');
    hint.classList.remove('text-emerald-700', 'text-amber-700', 'text-slate-500');
  };

  const showHint = (text, colorClass) => {
    hint.textContent = text;
    hint.classList.remove('hidden', 'text-emerald-700', 'text-amber-700', 'text-slate-500');
    hint.classList.add(colorClass);
  };

  const requestSuggestion = () => {
    const description = (descriptionInput.value || '').trim();
    const type = typeSelect.value || 'expense';

    if (description.length < 3) {
      hideHint();
      return;
    }

    const params = new URLSearchParams({
      route: 'transactions_suggest_category',
      description,
      type,
    });

    fetch('index.php?' + params.toString(), {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data || !data.ok || !data.suggestion) {
          hideHint();
          return;
        }

        const suggestion = data.suggestion;
        const categoryName = suggestion.category_name || 'categoria sugerida';

        if (suggestion.confidence === 'high') {
          if (!categoryTouched || !categorySelect.value) {
            categorySelect.value = String(suggestion.category_id || '');
          }
          showHint('Categoria sugerida com base no seu histórico (alta confiança): ' + categoryName + '.', 'text-emerald-700');
          return;
        }

        if (suggestion.confidence === 'medium') {
          showHint('Sugestão de categoria (média confiança): ' + categoryName + '. Confirme antes de salvar.', 'text-amber-700');
          return;
        }

        showHint('Sem confiança suficiente para sugerir categoria automaticamente.', 'text-slate-500');
      })
      .catch(() => {
        hideHint();
      });
  };

  const scheduleSuggestion = () => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(requestSuggestion, 350);
  };

  descriptionInput.addEventListener('input', scheduleSuggestion);
  typeSelect.addEventListener('change', scheduleSuggestion);
})();
</script>
