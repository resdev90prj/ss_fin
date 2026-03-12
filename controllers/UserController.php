<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Category.php';

class UserController
{
    public function index(): void
    {
        require_admin();

        view('users/index', [
            'title' => 'Usuarios',
            'users' => (new User())->all(),
            'scopedUserId' => scoped_user_id(),
            'loggedUserId' => logged_user_id(),
        ]);
    }

    public function store(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'user');
        $status = $this->normalizeStatus($_POST['status'] ?? '1');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nome e e-mail validos sao obrigatorios.');
            redirect('index.php?route=users');
        }

        if (strlen($password) < 6) {
            flash('error', 'A senha deve ter ao menos 6 caracteres.');
            redirect('index.php?route=users');
        }

        if ($password !== $confirmPassword) {
            flash('error', 'A confirmacao da senha nao confere.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        if ($userModel->emailExists($email)) {
            flash('error', 'Ja existe usuario com este e-mail.');
            redirect('index.php?route=users');
        }

        $newUserId = $userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
        ]);

        (new Category())->ensureDefaultsForUser($newUserId);

        flash('success', 'Usuario criado com sucesso.');
        redirect('index.php?route=users');
    }

    public function update(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Usuario invalido.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            flash('error', 'Usuario nao encontrado.');
            redirect('index.php?route=users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'user');
        $status = $this->normalizeStatus($_POST['status'] ?? '1');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nome e e-mail validos sao obrigatorios.');
            redirect('index.php?route=users');
        }

        if ($userModel->emailExists($email, $id)) {
            flash('error', 'Ja existe usuario com este e-mail.');
            redirect('index.php?route=users');
        }

        $isSelf = $id === (int)(logged_user_id() ?? 0);
        if ($isSelf && $status === 0) {
            flash('error', 'Nao e permitido desativar o proprio usuario.');
            redirect('index.php?route=users');
        }

        $userModel->updateByAdmin($id, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
        ]);

        if ($isSelf) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;
            if ($role !== 'admin') {
                clear_scope_user_id();
            }
        }

        flash('success', 'Usuario atualizado com sucesso.');
        redirect('index.php?route=users');
    }

    public function toggleStatus(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Usuario invalido.');
            redirect('index.php?route=users');
        }

        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            flash('error', 'Usuario nao encontrado.');
            redirect('index.php?route=users');
        }

        if ($id === (int)(logged_user_id() ?? 0) && (int)$target['status'] === 1) {
            flash('error', 'Nao e permitido desativar o proprio usuario.');
            redirect('index.php?route=users');
        }

        $nextStatus = (int)$target['status'] === 1 ? 0 : 1;
        $userModel->setStatus($id, $nextStatus);

        if ($nextStatus === 0 && (int)(scoped_user_id() ?? 0) === $id) {
            clear_scope_user_id();
        }

        flash('success', $nextStatus === 1 ? 'Usuario ativado.' : 'Usuario desativado.');
        redirect('index.php?route=users');
    }

    public function resetPassword(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=users');
        }

        $id = (int)($_POST['id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($id <= 0 || strlen($newPassword) < 6) {
            flash('error', 'Senha invalida. Use ao menos 6 caracteres.');
            redirect('index.php?route=users');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'A confirmacao da nova senha nao confere.');
            redirect('index.php?route=users');
        }

        $target = (new User())->findById($id);
        if (!$target) {
            flash('error', 'Usuario nao encontrado.');
            redirect('index.php?route=users');
        }

        (new User())->resetPassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        flash('success', 'Senha redefinida com sucesso.');
        redirect('index.php?route=users');
    }

    public function profile(): void
    {
        require_login();

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $userModel = new User();
        $user = $userModel->findById($loggedUserId);
        if (!$user) {
            flash('error', 'Usuario nao encontrado.');
            redirect('index.php?route=login');
        }

        view('users/profile', [
            'title' => 'Meu acesso',
            'user' => $user,
            'alertPreferences' => $userModel->alertPreferencesByUserId($loggedUserId),
            'alertPreferenceTableAvailable' => $userModel->hasAlertPreferencesTable(),
        ]);
    }

    public function profileUpdate(): void
    {
        require_login();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=profile');
        }

        $loggedUserId = (int)(logged_user_id() ?? 0);
        if ($loggedUserId <= 0) {
            flash('error', 'Usuario invalido.');
            redirect('index.php?route=login');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nome e e-mail validos sao obrigatorios.');
            redirect('index.php?route=profile');
        }

        $userModel = new User();
        if ($userModel->emailExists($email, $loggedUserId)) {
            flash('error', 'Ja existe usuario com este e-mail.');
            redirect('index.php?route=profile');
        }

        $userModel->updateOwnProfile($loggedUserId, $name, $email);

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;

        flash('success', 'Seus dados foram atualizados.');
        redirect('index.php?route=profile');
    }

    public function profilePassword(): void
    {
        require_login();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=profile');
        }

        $loggedUserId = (int)(logged_user_id() ?? 0);
        if ($loggedUserId <= 0) {
            flash('error', 'Usuario invalido.');
            redirect('index.php?route=login');
        }

        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 6) {
            flash('error', 'A nova senha deve ter ao menos 6 caracteres.');
            redirect('index.php?route=profile');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'A confirmacao da nova senha nao confere.');
            redirect('index.php?route=profile');
        }

        $userModel = new User();
        $user = $userModel->findById($loggedUserId);
        if (!$user) {
            flash('error', 'Usuario nao encontrado.');
            redirect('index.php?route=login');
        }

        if (!password_verify($currentPassword, (string)$user['password'])) {
            flash('error', 'Senha atual invalida.');
            redirect('index.php?route=profile');
        }

        $userModel->resetPassword($loggedUserId, password_hash($newPassword, PASSWORD_DEFAULT));

        flash('success', 'Sua senha foi alterada com sucesso.');
        redirect('index.php?route=profile');
    }

    public function profileAlerts(): void
    {
        require_login();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=profile');
        }

        $loggedUserId = (int)(logged_user_id() ?? 0);
        if ($loggedUserId <= 0) {
            flash('error', 'Usuario invalido.');
            redirect('index.php?route=login');
        }

        $receberAlertaEmail = isset($_POST['receber_alerta_email']) && $_POST['receber_alerta_email'] === '1';
        $emailNotificacao = trim((string)($_POST['email_notificacao'] ?? ''));
        $alertaFrequencia = strtolower(trim((string)($_POST['alerta_frequencia'] ?? 'daily')));
        $alertaHorario = trim((string)($_POST['alerta_horario'] ?? '08:00'));

        if (!in_array($alertaFrequencia, ['daily', 'weekdays', 'manual'], true)) {
            $alertaFrequencia = 'daily';
        }

        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $alertaHorario)) {
            flash('error', 'Horario de alerta invalido. Use HH:MM.');
            redirect('index.php?route=profile');
        }

        if ($emailNotificacao !== '' && !filter_var($emailNotificacao, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'E-mail de notificacao invalido.');
            redirect('index.php?route=profile');
        }

        $userModel = new User();
        $ok = $userModel->updateOwnAlertPreferences($loggedUserId, [
            'receber_alerta_email' => $receberAlertaEmail,
            'email_notificacao' => $emailNotificacao,
            'alerta_frequencia' => $alertaFrequencia,
            'alerta_horario' => $alertaHorario,
        ]);

        if (!$ok) {
            flash('error', 'Nao foi possivel salvar preferencias de alerta. Aplique o patch SQL da Central de Alertas.');
            redirect('index.php?route=profile');
        }

        flash('success', 'Preferencias de alerta atualizadas com sucesso.');
        redirect('index.php?route=profile');
    }

    public function scope(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=users');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            flash('error', 'Usuario invalido para escopo.');
            redirect('index.php?route=users');
        }

        $target = (new User())->findById($userId);
        if (!$target) {
            flash('error', 'Usuario nao encontrado para escopo.');
            redirect('index.php?route=users');
        }

        set_scope_user_id($userId);
        flash('success', 'Escopo de visualizacao alterado para: ' . (string)$target['name']);
        redirect('index.php?route=dashboard');
    }

    public function clearScope(): void
    {
        require_admin();

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=dashboard');
        }

        clear_scope_user_id();
        flash('success', 'Escopo de visualizacao retornou para seu usuario administrador.');
        redirect('index.php?route=dashboard');
    }

    private function normalizeRole(string $role): string
    {
        return $role === 'admin' ? 'admin' : 'user';
    }

    private function normalizeStatus(string $status): int
    {
        return $status === '0' ? 0 : 1;
    }
}

