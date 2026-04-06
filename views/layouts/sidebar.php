<?php if (is_logged_in()): ?>
<?php $loggedUserName = trim((string)(current_user()['name'] ?? '')); ?>
<?php $activeScopeUserId = (int)(scoped_user_id() ?? 0); ?>
<?php $loggedUserId = (int)(logged_user_id() ?? 0); ?>
<?php $isScopedAsAnother = can_use_visual_scope() && $activeScopeUserId > 0 && $activeScopeUserId !== $loggedUserId; ?>
<?php $scopeLabel = is_admin() ? 'Escopo admin ativo' : 'Escopo do gestor ativo'; ?>
<?php $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')); ?>
<?php $assetBasePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/'); ?>
<div class="flex min-h-screen">
  <aside class="w-64 bg-slate-900 text-white p-4 space-y-2">
    <h1 class="text-lg font-bold mb-4"><?= e($loggedUserName !== '' ? 'Ola, ' . $loggedUserName : 'Ola') ?></h1>

    <?php if ($isScopedAsAnother): ?>
      <div class="rounded bg-amber-100 text-amber-900 p-2 text-xs mb-2">
        <?= e($scopeLabel) ?> para usuario ID <?= $activeScopeUserId ?>.
      </div>
      <form method="POST" action="index.php?route=users_clear_scope" class="mb-2">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="w-full text-left px-3 py-2 rounded bg-amber-700 hover:bg-amber-600 text-white">Voltar para meu usuario</button>
      </form>
    <?php endif; ?>

    <?php if (has_current_module_access('dashboard')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=dashboard">Dashboard</a>
    <?php endif; ?>
    <?php if (has_current_module_access('accounts')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=accounts">Contas</a>
    <?php endif; ?>
    <?php if (has_current_module_access('boxes')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=boxes">Caixas</a>
    <?php endif; ?>
    <?php if (has_current_module_access('categories')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=categories">Categorias</a>
    <?php endif; ?>
    <?php if (has_current_module_access('transactions')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=transactions">Receitas/Despesas</a>
    <?php endif; ?>
    <?php if (has_current_module_access('withdrawals')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=withdrawals">Retiradas</a>
    <?php endif; ?>
    <?php if (has_current_module_access('debts')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=debts">Dividas</a>
    <?php endif; ?>
    <?php if (has_current_module_access('budgets')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=budgets">Orcamentos</a>
    <?php endif; ?>
    <?php if (has_current_module_access('planning')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=goals">Metas</a>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=targets">Alvos e Execucao</a>
    <?php endif; ?>
    <?php if (has_current_module_access('reports')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=reports">Relatorios</a>
    <?php endif; ?>
    <?php if (has_current_module_access('imports')): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=imports">Importacao</a>
    <?php endif; ?>
    <?php if (can_access_user_admin()): ?>
      <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=users">Usuarios</a>
    <?php endif; ?>
    <a class="block px-3 py-2 rounded hover:bg-slate-700" href="index.php?route=profile">Meu acesso</a>
    <a class="block px-3 py-2 rounded bg-red-700 hover:bg-red-600 mt-6" href="index.php?route=logout">Sair</a>
    <div class="pt-4 mt-4 border-t border-slate-700 flex justify-center">
      <img src="<?= e($assetBasePath . '/public_html/assets/branding/finance_logo_v3.ico?v=20260308_3') ?>" alt="Logo IA Finan" class="w-16 h-16 object-contain">
    </div>
  </aside>
  <main class="flex-1 p-6">
<?php else: ?>
  <main class="max-w-md mx-auto mt-16 p-6 bg-white rounded-xl shadow">
<?php endif; ?>

<?php if ($msg = flash('success')): ?>
<div class="mb-4 rounded bg-emerald-100 text-emerald-800 p-3"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="mb-4 rounded bg-red-100 text-red-800 p-3"><?= e($msg) ?></div>
<?php endif; ?>
