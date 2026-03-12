<?php
$queueData = $queueData ?? [];
$lastRun = is_array($queueData['last_run'] ?? null) ? $queueData['last_run'] : null;
$pendingFiles = is_array($queueData['pending_files'] ?? null) ? $queueData['pending_files'] : [];
$processedFiles = is_array($queueData['processed_files'] ?? null) ? $queueData['processed_files'] : [];
$errorFiles = is_array($queueData['error_files'] ?? null) ? $queueData['error_files'] : [];
$recentLogs = is_array($queueData['recent_logs'] ?? null) ? $queueData['recent_logs'] : [];
$recentErrors = is_array($queueData['recent_errors'] ?? null) ? $queueData['recent_errors'] : [];
?>

<h2 class="text-2xl font-bold mb-4">Importacao de Extratos</h2>
<p class="mb-4 text-sm text-slate-600">Formatos suportados: CSV, OFX, XLSX. Lancamentos nao reconhecidos serao categorizados como <strong>Outros gastos</strong>.</p>

<div class="bg-white p-4 rounded shadow mb-6">
  <h3 class="text-lg font-semibold mb-2">Importacao Manual</h3>
  <form method="POST" action="index.php?route=imports_upload" enctype="multipart/form-data" class="grid md:grid-cols-4 gap-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <select name="account_id" required class="border rounded p-2">
      <option value="">Conta destino</option>
      <?php foreach ($accounts as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="statement" accept=".csv,.ofx,.xlsx" required class="border rounded p-2 md:col-span-2">
    <button class="bg-slate-900 text-white rounded p-2">Importar extrato</button>
  </form>
</div>

<div class="bg-white p-4 rounded shadow mb-6">
  <h3 class="text-lg font-semibold mb-2">Fila OFX Automatizada</h3>
  <p class="text-sm text-slate-600 mb-3">Coloque arquivos em <code>imports/pending</code> e processe pela rota protegida <code>index.php?route=imports/process_ofx_queue</code>.</p>
  <form method="POST" action="index.php?route=imports/process_ofx_queue" class="flex flex-wrap items-center gap-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button class="bg-emerald-700 hover:bg-emerald-600 text-white rounded px-4 py-2">Processar fila OFX agora</button>
    <span class="text-sm text-slate-500">Script CLI: <code>php includes/process_ofx_queue.php</code></span>
  </form>
</div>

<?php if ($lastRun): ?>
<div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-9 gap-3 mb-6">
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Arquivos lidos</p><p class="text-lg font-semibold"><?= (int)$lastRun['files_scanned'] ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Arquivos processados</p><p class="text-lg font-semibold text-emerald-700"><?= (int)$lastRun['files_processed'] ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Arquivos duplicados</p><p class="text-lg font-semibold text-amber-700"><?= (int)$lastRun['files_skipped_duplicate_file'] ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Arquivos com falha</p><p class="text-lg font-semibold text-red-700"><?= (int)$lastRun['files_failed'] ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Lancamentos criados</p><p class="text-lg font-semibold"><?= (int)$lastRun['transactions_created'] ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Classificação alta</p><p class="text-lg font-semibold text-emerald-700"><?= (int)($lastRun['transactions_classified_high'] ?? 0) ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Classificação média</p><p class="text-lg font-semibold text-amber-700"><?= (int)($lastRun['transactions_classified_medium'] ?? 0) ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Fallback usados</p><p class="text-lg font-semibold"><?= (int)($lastRun['transactions_fallback_used'] ?? 0) ?></p></div>
  <div class="bg-white rounded shadow p-3"><p class="text-xs text-slate-500">Duplicidade ignorada</p><p class="text-lg font-semibold"><?= (int)$lastRun['transactions_ignored_duplicate'] ?></p></div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
  <div class="bg-white rounded shadow p-4">
    <h4 class="font-semibold mb-2">Pendentes em imports/pending</h4>
    <?php if (empty($pendingFiles)): ?>
      <p class="text-sm text-slate-500">Sem arquivos pendentes.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-100"><tr><th class="p-2 text-left">Arquivo</th><th class="p-2">Tamanho</th><th class="p-2">Modificado em</th></tr></thead>
        <tbody>
          <?php foreach ($pendingFiles as $f): ?>
            <tr class="border-t">
              <td class="p-2"><?= e((string)$f['name']) ?></td>
              <td class="p-2 text-center"><?= number_format(((int)$f['size']) / 1024, 1, ',', '.') ?> KB</td>
              <td class="p-2 text-center"><?= e((string)$f['modified_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded shadow p-4">
    <h4 class="font-semibold mb-2">Ultimos erros da fila</h4>
    <?php if (empty($recentErrors)): ?>
      <p class="text-sm text-slate-500">Sem erros recentes.</p>
    <?php else: ?>
      <div class="space-y-2 max-h-64 overflow-auto text-xs">
        <?php foreach ($recentErrors as $line): ?>
          <div class="p-2 rounded bg-red-50 border border-red-100 text-red-800"><?= e($line) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
  <div class="bg-white rounded shadow p-4">
    <h4 class="font-semibold mb-2">Arquivos processados (ultimos)</h4>
    <?php if (empty($processedFiles)): ?>
      <p class="text-sm text-slate-500">Sem arquivos processados ainda.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-100"><tr><th class="p-2 text-left">Arquivo</th><th class="p-2">Tamanho</th><th class="p-2">Modificado em</th></tr></thead>
        <tbody>
          <?php foreach ($processedFiles as $f): ?>
            <tr class="border-t">
              <td class="p-2"><?= e((string)$f['name']) ?></td>
              <td class="p-2 text-center"><?= number_format(((int)$f['size']) / 1024, 1, ',', '.') ?> KB</td>
              <td class="p-2 text-center"><?= e((string)$f['modified_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded shadow p-4">
    <h4 class="font-semibold mb-2">Arquivos com falha (ultimos)</h4>
    <?php if (empty($errorFiles)): ?>
      <p class="text-sm text-slate-500">Sem arquivos com falha.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-100"><tr><th class="p-2 text-left">Arquivo</th><th class="p-2">Tamanho</th><th class="p-2">Modificado em</th></tr></thead>
        <tbody>
          <?php foreach ($errorFiles as $f): ?>
            <tr class="border-t">
              <td class="p-2"><?= e((string)$f['name']) ?></td>
              <td class="p-2 text-center"><?= number_format(((int)$f['size']) / 1024, 1, ',', '.') ?> KB</td>
              <td class="p-2 text-center"><?= e((string)$f['modified_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded shadow p-4">
  <h4 class="font-semibold mb-2">Logs recentes da fila</h4>
  <?php if (empty($recentLogs)): ?>
    <p class="text-sm text-slate-500">Sem logs ainda.</p>
  <?php else: ?>
    <div class="space-y-2 max-h-72 overflow-auto text-xs">
      <?php foreach ($recentLogs as $line): ?>
        <div class="p-2 rounded bg-slate-50 border border-slate-100"><?= e($line) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
