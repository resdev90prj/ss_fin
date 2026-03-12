<h2 class="text-2xl font-bold mb-4">Entrar</h2>
<form method="POST" action="index.php?route=login_submit" class="space-y-4">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <div>
    <label class="block text-sm mb-1">E-mail</label>
    <input name="email" type="email" required class="w-full border rounded p-2" value="<?= e(old('email')) ?>">
  </div>
  <div>
    <label class="block text-sm mb-1">Senha</label>
    <input name="password" type="password" required class="w-full border rounded p-2">
  </div>
  <button class="w-full bg-slate-900 text-white py-2 rounded">Acessar</button>
</form>
