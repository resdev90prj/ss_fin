<?php
$user = $user ?? [];
$userStatus = (int)($user['status'] ?? 0) === 1 ? 'ativo' : 'inativo';
$alertPreferences = $alertPreferences ?? [];
$alertPreferenceTableAvailable = !empty($alertPreferenceTableAvailable);
?>

<h2 class="text-2xl font-bold mb-4">Meu acesso</h2>
<p class="text-sm text-slate-600 mb-4">Atualize seus dados basicos, senha e preferencias da Central de Alertas.</p>

<?php if (!$alertPreferenceTableAvailable): ?>
  <div class="bg-amber-100 border border-amber-200 text-amber-800 rounded p-3 mb-4">
    Preferencias de alerta indisponiveis. Aplique o patch SQL <code>database/patches/20260312_alert_center_notifications.sql</code>.
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <form method="POST" action="index.php?route=profile_update" class="bg-white p-4 rounded shadow space-y-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <h3 class="font-semibold">Dados basicos</h3>

    <div>
      <label class="block text-sm mb-1">Nome</label>
      <input type="text" name="name" value="<?= e((string)($user['name'] ?? '')) ?>" required class="w-full border rounded p-2">
    </div>

    <div>
      <label class="block text-sm mb-1">E-mail</label>
      <input type="email" name="email" value="<?= e((string)($user['email'] ?? '')) ?>" required class="w-full border rounded p-2">
    </div>

    <div class="text-sm text-slate-600">
      <p>Perfil: <strong><?= e((string)($user['role'] ?? 'user')) ?></strong></p>
      <p>Status: <strong><?= e($userStatus) ?></strong></p>
    </div>

    <button class="bg-blue-600 hover:bg-blue-500 text-white rounded px-3 py-2">Salvar dados</button>
  </form>

  <form method="POST" action="index.php?route=profile_password" class="bg-white p-4 rounded shadow space-y-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <h3 class="font-semibold">Alterar senha</h3>

    <div>
      <label class="block text-sm mb-1">Senha atual</label>
      <input type="password" name="current_password" minlength="6" required class="w-full border rounded p-2">
    </div>

    <div>
      <label class="block text-sm mb-1">Nova senha</label>
      <input type="password" name="new_password" minlength="6" required class="w-full border rounded p-2">
    </div>

    <div>
      <label class="block text-sm mb-1">Confirmar nova senha</label>
      <input type="password" name="confirm_password" minlength="6" required class="w-full border rounded p-2">
    </div>

    <button class="bg-slate-900 hover:bg-slate-800 text-white rounded px-3 py-2">Salvar nova senha</button>
  </form>

  <form method="POST" action="index.php?route=profile_alerts" class="bg-white p-4 rounded shadow space-y-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <h3 class="font-semibold">Central de Alertas</h3>

    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="receber_alerta_email" value="1" <?= !empty($alertPreferences['receber_alerta_email']) ? 'checked' : '' ?>>
      Receber alertas por e-mail
    </label>

    <div>
      <label class="block text-sm mb-1">E-mail de notificacao</label>
      <input type="email" name="email_notificacao" value="<?= e((string)($alertPreferences['email_notificacao'] ?? '')) ?>" class="w-full border rounded p-2" placeholder="Padrao: e-mail do login">
    </div>

    <div>
      <label class="block text-sm mb-1">Frequencia</label>
      <select name="alerta_frequencia" class="w-full border rounded p-2">
        <?php $freq = (string)($alertPreferences['alerta_frequencia'] ?? 'daily'); ?>
        <option value="daily" <?= $freq === 'daily' ? 'selected' : '' ?>>Diaria</option>
        <option value="weekdays" <?= $freq === 'weekdays' ? 'selected' : '' ?>>Dias uteis</option>
        <option value="manual" <?= $freq === 'manual' ? 'selected' : '' ?>>Manual (somente disparo admin/CLI)</option>
      </select>
    </div>

    <div>
      <label class="block text-sm mb-1">Horario preferido</label>
      <input type="time" name="alerta_horario" value="<?= e((string)($alertPreferences['alerta_horario'] ?? '08:00')) ?>" class="w-full border rounded p-2">
      <p class="text-xs text-slate-500 mt-1">Usado para execucoes automaticas via cron/script.</p>
    </div>

    <button class="bg-emerald-600 hover:bg-emerald-500 text-white rounded px-3 py-2">Salvar preferencias</button>
  </form>
</div>
