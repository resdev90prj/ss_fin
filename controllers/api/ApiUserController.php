<?php
declare(strict_types=1);

require_once api_project_root() . '/models/User.php';
require_once api_project_root() . '/models/Category.php';

class ApiUserController
{
    public function profile(): void
    {
        api_require_login();

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $user = (new User())->findById($loggedUserId);
        if (!$user) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Nao foi possivel carregar seu acesso.'], 404);
        }

        $userModel = new User();
        api_json_response(true, 'Perfil carregado com sucesso.', [
            'user' => [
                'id' => (int)$user['id'],
                'name' => (string)$user['name'],
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
                'status' => (int)$user['status'],
            ],
            'alert_preferences' => $userModel->alertPreferencesByUserId($loggedUserId),
            'alert_preference_table_available' => $userModel->hasAlertPreferencesTable(),
        ]);
    }

    public function profileUpdate(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_json_response(false, 'Nome e e-mail validos sao obrigatorios.', [], ['Revise os dados basicos informados.'], 422);
        }

        $userModel = new User();
        if ($userModel->emailExists($email, $loggedUserId)) {
            api_json_response(false, 'Ja existe usuario com este e-mail.', [], ['Use outro e-mail.'], 422);
        }

        $userModel->updateOwnProfile($loggedUserId, $name, $email);
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;

        api_json_response(true, 'Dados atualizados com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function profilePassword(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $currentPassword = (string)($payload['current_password'] ?? '');
        $newPassword = (string)($payload['new_password'] ?? '');
        $confirmPassword = (string)($payload['confirm_password'] ?? '');

        if (strlen($newPassword) < 6) {
            api_json_response(false, 'A nova senha deve ter ao menos 6 caracteres.', [], ['Use uma senha com no minimo 6 caracteres.'], 422);
        }

        if ($newPassword !== $confirmPassword) {
            api_json_response(false, 'A confirmacao da nova senha nao confere.', [], ['Confirme a nova senha corretamente.'], 422);
        }

        $userModel = new User();
        $user = $userModel->findById($loggedUserId);
        if (!$user || !password_verify($currentPassword, (string)$user['password'])) {
            api_json_response(false, 'Senha atual invalida.', [], ['A senha atual informada nao confere.'], 422);
        }

        $userModel->resetPassword($loggedUserId, password_hash($newPassword, PASSWORD_DEFAULT));

        api_json_response(true, 'Senha alterada com sucesso.');
    }

    public function profileAlerts(): void
    {
        api_require_login();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $receberAlertaEmail = !empty($payload['receber_alerta_email']);
        $emailNotificacao = trim((string)($payload['email_notificacao'] ?? ''));
        $alertaFrequencia = strtolower(trim((string)($payload['alerta_frequencia'] ?? 'daily')));
        $alertaHorario = trim((string)($payload['alerta_horario'] ?? '08:00'));

        if (!in_array($alertaFrequencia, ['daily', 'weekdays', 'manual'], true)) {
            $alertaFrequencia = 'daily';
        }

        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $alertaHorario)) {
            api_json_response(false, 'Horario de alerta invalido.', [], ['Use o formato HH:MM.'], 422);
        }

        if ($emailNotificacao !== '' && !filter_var($emailNotificacao, FILTER_VALIDATE_EMAIL)) {
            api_json_response(false, 'E-mail de notificacao invalido.', [], ['Informe um e-mail valido para notificacoes.'], 422);
        }

        $userModel = new User();
        $ok = $userModel->updateOwnAlertPreferences($loggedUserId, [
            'receber_alerta_email' => $receberAlertaEmail,
            'email_notificacao' => $emailNotificacao,
            'alerta_frequencia' => $alertaFrequencia,
            'alerta_horario' => $alertaHorario,
        ]);

        if (!$ok) {
            api_json_response(false, 'Nao foi possivel salvar preferencias de alerta.', [], ['A tabela de preferencias pode nao estar disponivel no banco.'], 422);
        }

        api_json_response(true, 'Preferencias de alerta salvas com sucesso.');
    }

    public function index(): void
    {
        api_require_admin();

        $users = (new User())->all();
        $loggedUserId = (int)(logged_user_id() ?? 0);
        $scopedUserId = (int)(scoped_user_id() ?? 0);

        $summary = [
            'total' => count($users),
            'active_count' => 0,
            'inactive_count' => 0,
            'admin_count' => 0,
            'user_count' => 0,
        ];

        foreach ($users as &$user) {
            $isActive = (int)($user['status'] ?? 0) === 1;
            $role = (string)($user['role'] ?? 'user');
            $user['is_self'] = (int)($user['id'] ?? 0) === $loggedUserId;
            $user['is_scoped'] = (int)($user['id'] ?? 0) === $scopedUserId;

            $summary[$isActive ? 'active_count' : 'inactive_count']++;
            $summary[$role === 'admin' ? 'admin_count' : 'user_count']++;
        }
        unset($user);

        api_json_response(true, 'Usuarios carregados com sucesso.', [
            'items' => $users,
            'summary' => $summary,
            'scope' => [
                'logged_user_id' => $loggedUserId,
                'scoped_user_id' => $scopedUserId,
            ],
        ]);
    }

    public function store(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirmPassword = (string)($payload['confirm_password'] ?? '');
        $role = $this->normalizeRole((string)($payload['role'] ?? 'user'));
        $status = $this->normalizeStatus((string)($payload['status'] ?? '1'));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_json_response(false, 'Nome e e-mail validos sao obrigatorios.', [], ['Revise os dados do novo usuario.'], 422);
        }

        if (strlen($password) < 6) {
            api_json_response(false, 'A senha deve ter ao menos 6 caracteres.', [], ['Use uma senha com no minimo 6 caracteres.'], 422);
        }

        if ($password !== $confirmPassword) {
            api_json_response(false, 'A confirmacao da senha nao confere.', [], ['Confirme a senha corretamente.'], 422);
        }

        $userModel = new User();
        if ($userModel->emailExists($email)) {
            api_json_response(false, 'Ja existe usuario com este e-mail.', [], ['Use outro e-mail.'], 422);
        }

        $newUserId = $userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
        ]);
        (new Category())->ensureDefaultsForUser($newUserId);

        api_json_response(true, 'Usuario criado com sucesso.');
    }

    public function update(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $id = (int)($payload['id'] ?? 0);
        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $role = $this->normalizeRole((string)($payload['role'] ?? 'user'));
        $status = $this->normalizeStatus((string)($payload['status'] ?? '1'));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_json_response(false, 'Nome e e-mail validos sao obrigatorios.', [], ['Revise os dados do usuario.'], 422);
        }

        if ($userModel->emailExists($email, $id)) {
            api_json_response(false, 'Ja existe usuario com este e-mail.', [], ['Use outro e-mail.'], 422);
        }

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $isSelf = $id === $loggedUserId;
        if ($isSelf && $status === 0) {
            api_json_response(false, 'Nao e permitido desativar o proprio usuario.', [], ['Mantenha seu usuario ativo.'], 422);
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

        api_json_response(true, 'Usuario atualizado com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function toggleStatus(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $id = (int)($payload['id'] ?? 0);
        $userModel = new User();
        $target = $userModel->findById($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        $loggedUserId = (int)(logged_user_id() ?? 0);
        if ($id === $loggedUserId && (int)$target['status'] === 1) {
            api_json_response(false, 'Nao e permitido desativar o proprio usuario.', [], ['Mantenha seu usuario ativo.'], 422);
        }

        $nextStatus = (int)$target['status'] === 1 ? 0 : 1;
        $userModel->setStatus($id, $nextStatus);

        if ($nextStatus === 0 && (int)(scoped_user_id() ?? 0) === $id) {
            clear_scope_user_id();
        }

        api_json_response(true, $nextStatus === 1 ? 'Usuario ativado com sucesso.' : 'Usuario desativado com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function resetPassword(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $id = (int)($payload['id'] ?? 0);
        $newPassword = (string)($payload['new_password'] ?? '');
        $confirmPassword = (string)($payload['confirm_password'] ?? '');

        if ($id <= 0 || strlen($newPassword) < 6) {
            api_json_response(false, 'Senha invalida.', [], ['Use ao menos 6 caracteres para a nova senha.'], 422);
        }

        if ($newPassword !== $confirmPassword) {
            api_json_response(false, 'A confirmacao da nova senha nao confere.', [], ['Confirme a nova senha corretamente.'], 422);
        }

        $target = (new User())->findById($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        (new User())->resetPassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        api_json_response(true, 'Senha redefinida com sucesso.');
    }

    public function scope(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $userId = (int)($payload['user_id'] ?? 0);
        $target = (new User())->findById($userId);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado para escopo.', [], ['Escolha um usuario valido para o escopo.'], 404);
        }

        set_scope_user_id($userId);

        api_json_response(true, 'Escopo de visualizacao alterado com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function clearScope(): void
    {
        api_require_admin();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        clear_scope_user_id();

        api_json_response(true, 'Escopo de visualizacao limpo com sucesso.', [
            'session' => api_session_payload(),
        ]);
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
