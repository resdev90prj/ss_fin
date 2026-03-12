<?php
$scopedUserId = (int)($scopedUserId ?? 0);
$loggedUserId = (int)($loggedUserId ?? 0);
?>

<h2 class="text-2xl font-bold mb-4">Usuários</h2>
<p class="text-sm text-slate-600 mb-4">Gestão administrativa de usuários com isolamento por login e escopo de visualização.</p>

<?php if ($scopedUserId > 0 && $scopedUserId !== $loggedUserId): ?>
<div class="bg-amber-100 border border-amber-200 text-amber-800 rounded p-3 mb-4 flex items-center justify-between gap-3">
  <span>Você está visualizando o sistema no escopo do usuário ID <?= (int)$scopedUserId ?>.</span>
  <form method="POST" action="index.php?route=users_clear_scope">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button class="bg-amber-700 hover:bg-amber-600 text-white rounded px-3 py-1">Voltar para meu usuário</button>
  </form>
</div>
<?php endif; ?>

<form method="POST" action="index.php?route=users_store" class="bg-white p-4 rounded shadow mb-6 grid md:grid-cols-6 gap-3">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input name="name" placeholder="Nome" required class="border rounded p-2 md:col-span-2">
  <input type="email" name="email" placeholder="E-mail" required class="border rounded p-2 md:col-span-2">
  <input type="password" name="password" placeholder="Senha (mín. 6)" required class="border rounded p-2">
  <select name="role" class="border rounded p-2">
    <option value="user">user</option>
    <option value="admin">admin</option>
  </select>
  <select name="status" class="border rounded p-2">
    <option value="1">Ativo</option>
    <option value="0">Inativo</option>
  </select>
  <button class="bg-slate-900 text-white rounded p-2 md:col-span-1">Criar usuário</button>
</form>

<div class="bg-white rounded shadow overflow-auto">
<table class="w-full text-sm">
  <thead class="bg-slate-200">
    <tr>
      <th class="p-2 text-left">ID</th>
      <th class="p-2 text-left">Nome</th>
      <th class="p-2 text-left">E-mail</th>
      <th class="p-2 text-center">Perfil</th>
      <th class="p-2 text-center">Status</th>
      <th class="p-2 text-center">Criado em</th>
      <th class="p-2 text-center">Ações</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <?php
      $isSelf = (int)$u['id'] === $loggedUserId;
      $isScoped = (int)$u['id'] === $scopedUserId;
      $isActive = (int)$u['status'] === 1;
    ?>
    <tr class="border-t align-top">
      <td class="p-2"><?= (int)$u['id'] ?></td>
      <td class="p-2"><?= e($u['name']) ?><?= $isSelf ? ' <span class="text-xs text-slate-500">(você)</span>' : '' ?></td>
      <td class="p-2"><?= e($u['email']) ?></td>
      <td class="p-2 text-center"><?= e($u['role']) ?></td>
      <td class="p-2 text-center"><?= $isActive ? 'ativo' : 'inativo' ?></td>
      <td class="p-2 text-center"><?= e((string)$u['created_at']) ?></td>
      <td class="p-2 text-center space-y-2">
        <form method="POST" action="index.php?route=users_scope">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <button class="text-blue-700"><?= $isScoped ? 'Escopo ativo' : 'Ver dados' ?></button>
        </form>

        <form method="POST" action="index.php?route=users_toggle_status" onsubmit="return confirm('Alterar status deste usuário?');">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="<?= $isActive ? 'text-amber-700' : 'text-emerald-700' ?>"><?= $isActive ? 'Desativar' : 'Ativar' ?></button>
        </form>

        <details>
          <summary class="cursor-pointer text-blue-600">Editar</summary>
          <form method="POST" action="index.php?route=users_update" class="mt-2 space-y-1 text-left">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <input name="name" value="<?= e($u['name']) ?>" class="border rounded p-1 w-full" required>
            <input name="email" type="email" value="<?= e($u['email']) ?>" class="border rounded p-1 w-full" required>
            <select name="role" class="border rounded p-1 w-full">
              <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
              <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
            </select>
            <select name="status" class="border rounded p-1 w-full">
              <option value="1" <?= (int)$u['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
              <option value="0" <?= (int)$u['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
            </select>
            <button class="bg-blue-600 text-white rounded px-2 py-1 w-full">Salvar edição</button>
          </form>
        </details>

        <details>
          <summary class="cursor-pointer text-blue-600">Resetar senha</summary>
          <form method="POST" action="index.php?route=users_reset_password" class="mt-2 space-y-1 text-left" onsubmit="return confirm('Redefinir senha deste usuário?');">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <input type="password" name="new_password" placeholder="Nova senha" minlength="6" required class="border rounded p-1 w-full">
            <button class="bg-slate-700 text-white rounded px-2 py-1 w-full">Salvar nova senha</button>
          </form>
        </details>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>