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
        $userModel = new User();
        $user = $userModel->findByIdDetailed($loggedUserId);
        if (!$user) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Nao foi possivel carregar seu acesso.'], 404);
        }

        api_json_response(true, 'Perfil carregado com sucesso.', [
            'user' => [
                'id' => (int)$user['id'],
                'name' => (string)$user['name'],
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
                'role_label' => access_role_label((string)$user['role']),
                'status' => (int)$user['status'],
                'manager_user_id' => !empty($user['manager_user_id']) ? (int)$user['manager_user_id'] : null,
                'manager_name' => $user['manager_name'] ?? null,
            ],
            'enabled_modules' => $this->enabledConfigurableModulesForUser($user),
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
        api_require_user_management_access();

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $relationship = trim((string)($_GET['relationship'] ?? ''));
        if ($relationship === '' && (string)$actor['role'] === 'gestor_financeiro') {
            $relationship = 'managed';
        }

        $roleFilter = trim((string)($_GET['role'] ?? ''));
        $managerUserId = (int)($_GET['manager_user_id'] ?? 0);

        $userModel = new User();
        $rows = $userModel->listForManagement($actor, [
            'relationship' => $relationship !== '' ? $relationship : 'all',
            'manager_user_id' => $managerUserId,
            'role' => $roleFilter,
        ]);

        $loggedUserId = (int)(logged_user_id() ?? 0);
        $scopedUserId = (int)(scoped_user_id() ?? 0);
        $summary = [
            'total' => count($rows),
            'active_count' => 0,
            'inactive_count' => 0,
            'admin_count' => 0,
            'manager_count' => 0,
            'user_count' => 0,
            'managed_clients_count' => 0,
            'dashboard_only_count' => 0,
            'planning_enabled_count' => 0,
        ];

        $items = [];
        foreach ($rows as $row) {
            $item = $this->presentManagementUser($row, $actor, $loggedUserId, $scopedUserId);
            $items[] = $item;

            $summary[(int)$item['status'] === 1 ? 'active_count' : 'inactive_count']++;
            if ($item['role'] === 'admin') {
                $summary['admin_count']++;
            } elseif ($item['role'] === 'gestor_financeiro') {
                $summary['manager_count']++;
            } else {
                $summary['user_count']++;
            }

            if ($item['is_managed_client']) {
                $summary['managed_clients_count']++;
            }
            if ($item['enabled_modules'] === []) {
                $summary['dashboard_only_count']++;
            }
            if (in_array('planning', $item['enabled_modules'], true)) {
                $summary['planning_enabled_count']++;
            }
        }

        api_json_response(true, 'Usuarios carregados com sucesso.', [
            'items' => $items,
            'summary' => $summary,
            'filters' => [
                'relationship' => $relationship !== '' ? $relationship : 'all',
                'role' => $roleFilter,
                'manager_user_id' => $managerUserId > 0 ? $managerUserId : null,
            ],
            'lookups' => [
                'roles' => $this->roleLookupsForActor($actor),
                'managers' => array_map(static fn(array $manager): array => [
                    'id' => (int)$manager['id'],
                    'name' => (string)$manager['name'],
                    'email' => (string)$manager['email'],
                ], $userModel->managerOptions()),
                'modules' => array_values(access_assignable_module_catalog()),
            ],
            'scope' => [
                'logged_user_id' => $loggedUserId,
                'scoped_user_id' => $scopedUserId > 0 ? $scopedUserId : null,
            ],
            'capabilities' => access_management_capabilities($actor),
        ]);
    }

    public function store(): void
    {
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $confirmPassword = (string)($payload['confirm_password'] ?? '');
        $status = $this->normalizeStatus((string)($payload['status'] ?? '1'));
        $role = $this->normalizeRoleForActor((string)($payload['role'] ?? 'user'), $actor);

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

        $this->ensureRoleSupported($userModel, $role);
        $managerUserId = $this->resolveManagerUserId($userModel, $payload, $role, $actor, null);
        $enabledModules = $this->resolveEnabledModulesForCreate($payload, $role, $managerUserId !== null);

        $this->ensureRequiredSchema(
            $userModel,
            $managerUserId !== null,
            $role === 'user' && array_key_exists('enabled_modules', $payload),
        );

        $newUserId = $userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
            'manager_user_id' => $managerUserId,
        ]);
        (new Category())->ensureDefaultsForUser($newUserId);
        $this->persistModuleAccess($userModel, $newUserId, $enabledModules);

        api_json_response(true, 'Usuario criado com sucesso.');
    }

    public function update(): void
    {
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $id = (int)($payload['id'] ?? 0);
        $userModel = new User();
        $target = $userModel->findByIdDetailed($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        $allowSelfManagement = is_admin() && $id === (int)($actor['id'] ?? 0);
        if (!access_can_manage_target_user($actor, $target, $allowSelfManagement)) {
            api_json_response(false, 'Permissao insuficiente para editar este usuario.', [], ['Este usuario esta fora do seu escopo de gestao.'], 403);
        }

        $name = trim((string)($payload['name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $role = $this->normalizeRoleForActor((string)($payload['role'] ?? ($target['role'] ?? 'user')), $actor, $target);
        $status = $this->normalizeStatus((string)($payload['status'] ?? (string)($target['status'] ?? '1')));

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

        $this->ensureRoleSupported($userModel, $role);
        $managerUserId = $this->resolveManagerUserId($userModel, $payload, $role, $actor, $target);
        $enabledModules = $this->resolveEnabledModulesForUpdate($payload, $role, $managerUserId !== null, $target);

        $this->ensureRequiredSchema(
            $userModel,
            $managerUserId !== null,
            $role === 'user' && array_key_exists('enabled_modules', $payload),
        );

        $userModel->updateByAdmin($id, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'manager_user_id' => $managerUserId,
        ]);
        $this->persistModuleAccess($userModel, $id, $enabledModules);

        if ($isSelf) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;

            if (!in_array($role, ['admin', 'gestor_financeiro'], true)) {
                clear_scope_user_id();
            }
        }

        api_json_response(true, 'Usuario atualizado com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function toggleStatus(): void
    {
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $id = (int)($payload['id'] ?? 0);
        $userModel = new User();
        $target = $userModel->findByIdDetailed($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        $allowSelfManagement = is_admin() && $id === (int)($actor['id'] ?? 0);
        if (!access_can_manage_target_user($actor, $target, $allowSelfManagement)) {
            api_json_response(false, 'Permissao insuficiente para alterar este usuario.', [], ['Este usuario esta fora do seu escopo de gestao.'], 403);
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
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $id = (int)($payload['id'] ?? 0);
        $newPassword = (string)($payload['new_password'] ?? '');
        $confirmPassword = (string)($payload['confirm_password'] ?? '');

        if ($id <= 0 || strlen($newPassword) < 6) {
            api_json_response(false, 'Senha invalida.', [], ['Use ao menos 6 caracteres para a nova senha.'], 422);
        }

        if ($newPassword !== $confirmPassword) {
            api_json_response(false, 'A confirmacao da nova senha nao confere.', [], ['Confirme a nova senha corretamente.'], 422);
        }

        $target = (new User())->findByIdDetailed($id);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado.', [], ['Usuario invalido.'], 404);
        }

        $allowSelfManagement = is_admin() && $id === (int)($actor['id'] ?? 0);
        if (!access_can_manage_target_user($actor, $target, $allowSelfManagement)) {
            api_json_response(false, 'Permissao insuficiente para redefinir a senha deste usuario.', [], ['Este usuario esta fora do seu escopo de gestao.'], 403);
        }

        (new User())->resetPassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        api_json_response(true, 'Senha redefinida com sucesso.');
    }

    public function scope(): void
    {
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        $actor = access_current_actor_record();
        if (!$actor) {
            api_json_response(false, 'Sessao invalida.', [], ['Nao foi possivel resolver o usuario autenticado.'], 401);
        }

        $userId = (int)($payload['user_id'] ?? 0);
        $target = (new User())->findByIdDetailed($userId);
        if (!$target) {
            api_json_response(false, 'Usuario nao encontrado para escopo.', [], ['Escolha um usuario valido para o escopo.'], 404);
        }

        if (!access_can_scope_to_target_user($actor, $target)) {
            api_json_response(false, 'Permissao insuficiente para trocar o escopo.', [], ['Este usuario esta fora do seu escopo de visualizacao.'], 403);
        }

        set_scope_user_id($userId);

        api_json_response(true, 'Escopo de visualizacao alterado com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    public function clearScope(): void
    {
        api_require_user_management_access();
        $payload = api_request_data();
        api_verify_csrf_or_fail($payload['csrf_token'] ?? $payload['_csrf'] ?? null);

        clear_scope_user_id();

        api_json_response(true, 'Escopo de visualizacao limpo com sucesso.', [
            'session' => api_session_payload(),
        ]);
    }

    private function presentManagementUser(array $row, array $actor, int $loggedUserId, int $scopedUserId): array
    {
        $enabledModules = $this->enabledConfigurableModulesForUser($row);
        $userId = (int)($row['id'] ?? 0);

        return [
            'id' => $userId,
            'name' => (string)($row['name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'role' => (string)($row['role'] ?? 'user'),
            'role_label' => access_role_label((string)($row['role'] ?? 'user')),
            'status' => (int)($row['status'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'manager_user_id' => !empty($row['manager_user_id']) ? (int)$row['manager_user_id'] : null,
            'manager_name' => $row['manager_name'] ?? null,
            'enabled_modules' => $enabledModules,
            'module_count' => count($enabledModules),
            'has_custom_module_rules' => !empty($row['has_module_access_rows']),
            'is_self' => $userId === $loggedUserId,
            'is_scoped' => $userId > 0 && $userId === $scopedUserId,
            'is_managed_client' => access_user_is_managed_client($row),
            'can_manage' => access_can_manage_target_user($actor, $row, is_admin() && $userId === $loggedUserId),
            'can_scope' => access_can_scope_to_target_user($actor, $row),
        ];
    }

    private function enabledConfigurableModulesForUser(array $user): array
    {
        $assignable = access_assignable_module_keys();
        $effectiveModules = access_effective_modules_for_user($user);

        $enabled = [];
        foreach ($effectiveModules as $moduleKey) {
            if (in_array($moduleKey, $assignable, true)) {
                $enabled[] = $moduleKey;
            }
        }

        return array_values(array_unique($enabled));
    }

    private function roleLookupsForActor(array $actor): array
    {
        $roles = access_allowed_roles_for_actor($actor);
        $items = [];

        foreach ($roles as $role) {
            $items[] = [
                'value' => $role,
                'label' => access_role_label($role),
            ];
        }

        return $items;
    }

    private function normalizeRoleForActor(string $role, array $actor, ?array $target = null): string
    {
        $allowedRoles = access_allowed_roles_for_actor($actor, $target);
        if (in_array($role, $allowedRoles, true)) {
            return $role;
        }

        if ($target) {
            $targetRole = (string)($target['role'] ?? 'user');
            if (in_array($targetRole, $allowedRoles, true)) {
                return $targetRole;
            }
        }

        return $allowedRoles[0] ?? 'user';
    }

    private function normalizeStatus(string $status): int
    {
        return $status === '0' ? 0 : 1;
    }

    private function resolveManagerUserId(User $userModel, array $payload, string $role, array $actor, ?array $target): ?int
    {
        if ($role !== 'user') {
            return null;
        }

        if ((string)($actor['role'] ?? '') === 'gestor_financeiro') {
            return (int)$actor['id'];
        }

        $managerUserId = 0;
        if (array_key_exists('manager_user_id', $payload)) {
            $managerUserId = (int)($payload['manager_user_id'] ?? 0);
        } elseif ($target && !empty($target['manager_user_id'])) {
            $managerUserId = (int)$target['manager_user_id'];
        }

        if ($managerUserId <= 0) {
            return null;
        }

        if ($target && $managerUserId === (int)($target['id'] ?? 0)) {
            api_json_response(false, 'Gestor responsavel invalido.', [], ['O usuario nao pode ser gestor de si mesmo.'], 422);
        }

        $manager = $userModel->findById($managerUserId);
        if (!$manager || (string)($manager['role'] ?? '') !== 'gestor_financeiro') {
            api_json_response(false, 'Gestor responsavel invalido.', [], ['Escolha um gestor financeiro valido.'], 422);
        }

        return $managerUserId;
    }

    private function resolveEnabledModulesForCreate(array $payload, string $role, bool $isManagedClient): array
    {
        if ($role !== 'user') {
            return access_default_configurable_modules($role, false);
        }

        if (array_key_exists('enabled_modules', $payload)) {
            return $this->sanitizeEnabledModules($payload['enabled_modules']);
        }

        return access_default_configurable_modules($role, $isManagedClient);
    }

    private function resolveEnabledModulesForUpdate(array $payload, string $role, bool $isManagedClient, array $target): array
    {
        if ($role !== 'user') {
            return access_default_configurable_modules($role, false);
        }

        if (array_key_exists('enabled_modules', $payload)) {
            return $this->sanitizeEnabledModules($payload['enabled_modules']);
        }

        $targetRole = (string)($target['role'] ?? 'user');
        $targetManaged = access_user_is_managed_client($target);
        if ($role === $targetRole && $isManagedClient === $targetManaged) {
            return $this->enabledConfigurableModulesForUser($target);
        }

        return access_default_configurable_modules($role, $isManagedClient);
    }

    private function sanitizeEnabledModules($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $allowed = access_assignable_module_keys();
        $items = [];

        foreach ($value as $moduleKey) {
            $moduleKey = trim((string)$moduleKey);
            if ($moduleKey !== '' && in_array($moduleKey, $allowed, true)) {
                $items[$moduleKey] = $moduleKey;
            }
        }

        return array_values($items);
    }

    private function persistModuleAccess(User $userModel, int $userId, array $enabledModules): void
    {
        if (!$userModel->hasModuleAccessTable()) {
            return;
        }

        $enabledMap = array_fill_keys($enabledModules, true);
        $moduleAccess = [];
        foreach (access_assignable_module_keys() as $moduleKey) {
            $moduleAccess[$moduleKey] = isset($enabledMap[$moduleKey]);
        }

        $userModel->saveModuleAccessMap($userId, $moduleAccess);
    }

    private function ensureRoleSupported(User $userModel, string $role): void
    {
        if ($userModel->supportsRoleValue($role)) {
            return;
        }

        api_json_response(false, 'Perfil indisponivel na estrutura atual do banco.', [], [
            'Aplique o patch SQL desta feature para permitir o papel gestor financeiro no campo role.',
        ], 422);
    }

    private function ensureRequiredSchema(User $userModel, bool $needsManagerColumn, bool $needsModuleTable): void
    {

        if ($needsManagerColumn && !$userModel->hasManagerUserColumn()) {
            api_json_response(false, 'Estrutura de gestor-cliente indisponivel no banco.', [], [
                'Aplique o patch SQL desta feature antes de usar vinculo entre gestor e cliente.',
            ], 422);
        }

        if ($needsModuleTable && !$userModel->hasModuleAccessTable()) {
            api_json_response(false, 'Estrutura de modulos por usuario indisponivel no banco.', [], [
                'Aplique o patch SQL desta feature antes de usar modulos habilitados por usuario.',
            ], 422);
        }
    }
}
