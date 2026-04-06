<?php

function current_user_role(): string
{
    return (string)(current_user()['role'] ?? '');
}

function is_financial_manager(): bool
{
    return current_user_role() === 'gestor_financeiro';
}

function can_use_visual_scope(): bool
{
    return is_admin() || is_financial_manager();
}

function can_manage_users(): bool
{
    return is_admin() || is_financial_manager();
}

function can_access_user_admin(): bool
{
    return is_admin();
}

function can_access_manager_area(): bool
{
    return is_admin() || is_financial_manager();
}

function access_role_labels(): array
{
    return [
        'admin' => 'Administrador',
        'gestor_financeiro' => 'Gestor financeiro',
        'user' => 'Cliente',
    ];
}

function access_role_label(string $role): string
{
    $labels = access_role_labels();
    return $labels[$role] ?? ucfirst($role);
}

function access_assignable_module_catalog(): array
{
    return [
        'accounts' => [
            'key' => 'accounts',
            'label' => 'Contas',
            'description' => 'Cadastro e manutencao das contas financeiras.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'boxes' => [
            'key' => 'boxes',
            'label' => 'Caixas',
            'description' => 'Organizacao de caixas e reservas.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'categories' => [
            'key' => 'categories',
            'label' => 'Categorias',
            'description' => 'Classificacao de receitas e despesas.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'transactions' => [
            'key' => 'transactions',
            'label' => 'Transacoes',
            'description' => 'Receitas, despesas e conciliacao operacional.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'withdrawals' => [
            'key' => 'withdrawals',
            'label' => 'Retiradas',
            'description' => 'Retiradas do socio e movimentos relacionados.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'debts' => [
            'key' => 'debts',
            'label' => 'Dividas',
            'description' => 'Dividas, parcelas, pagamentos e estornos.',
            'group' => 'finance',
            'group_label' => 'Financeiro',
        ],
        'budgets' => [
            'key' => 'budgets',
            'label' => 'Orcamentos',
            'description' => 'Orcamentos mensais por categoria.',
            'group' => 'planning',
            'group_label' => 'Planejamento',
        ],
        'planning' => [
            'key' => 'planning',
            'label' => 'Planejamento',
            'description' => 'Metas, alvos, agenda de execucao e score semanal.',
            'group' => 'planning',
            'group_label' => 'Planejamento',
        ],
        'imports' => [
            'key' => 'imports',
            'label' => 'Importacao',
            'description' => 'Uploads e fila OFX.',
            'group' => 'operations',
            'group_label' => 'Operacao',
        ],
        'reports' => [
            'key' => 'reports',
            'label' => 'Relatorios',
            'description' => 'Leituras gerenciais e relatorios consolidados.',
            'group' => 'analysis',
            'group_label' => 'Analise',
        ],
    ];
}

function access_assignable_module_keys(): array
{
    return array_keys(access_assignable_module_catalog());
}

function access_base_module_keys(): array
{
    return ['dashboard', 'profile'];
}

function access_actor_navigation_modules(array $actor): array
{
    $modules = [];
    $role = (string)($actor['role'] ?? '');

    if ($role === 'admin') {
        $modules[] = 'users';
        $modules[] = 'manager_clients';
    } elseif ($role === 'gestor_financeiro') {
        $modules[] = 'manager_clients';
    }

    return $modules;
}

function access_default_configurable_modules(string $role, bool $isManagedClient): array
{
    if ($role === 'admin' || $role === 'gestor_financeiro') {
        return access_assignable_module_keys();
    }

    if ($isManagedClient) {
        return [];
    }

    return access_assignable_module_keys();
}

function access_user_is_managed_client(array $user): bool
{
    return (string)($user['role'] ?? 'user') === 'user'
        && (int)($user['manager_user_id'] ?? 0) > 0;
}

function access_user_model(): User
{
    static $model = null;

    if (!$model instanceof User) {
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
    }

    return $model;
}

function access_current_actor_record(): ?array
{
    static $cache = [];

    $userId = logged_user_id();
    if ($userId === null || $userId <= 0) {
        return null;
    }

    if (!array_key_exists($userId, $cache)) {
        $cache[$userId] = access_user_model()->findByIdDetailed($userId);
    }

    return $cache[$userId];
}

function access_effective_user_record(): ?array
{
    static $cache = [];

    $userId = current_user_id();
    if ($userId === null || $userId <= 0) {
        return null;
    }

    if (!array_key_exists($userId, $cache)) {
        $cache[$userId] = access_user_model()->findByIdDetailed($userId);
    }

    return $cache[$userId];
}

function access_effective_modules_for_user(array $user): array
{
    $role = (string)($user['role'] ?? 'user');
    $baseModules = access_base_module_keys();

    if ($role === 'admin' || $role === 'gestor_financeiro') {
        return array_values(array_unique(array_merge($baseModules, access_assignable_module_keys())));
    }

    $moduleMap = is_array($user['module_access_map'] ?? null) ? $user['module_access_map'] : null;
    $hasRecords = !empty($user['has_module_access_rows']);

    if ($moduleMap !== null && $hasRecords) {
        $enabled = [];
        foreach ($moduleMap as $moduleKey => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = (string)$moduleKey;
            }
        }

        return array_values(array_unique(array_merge($baseModules, $enabled)));
    }

    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        return $baseModules;
    }

    $moduleState = access_user_model()->moduleAccessStateByUserId($userId);
    if (!empty($moduleState['has_records'])) {
        $enabled = [];
        foreach ((array)($moduleState['map'] ?? []) as $moduleKey => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = (string)$moduleKey;
            }
        }

        return array_values(array_unique(array_merge($baseModules, $enabled)));
    }

    $defaults = access_default_configurable_modules($role, access_user_is_managed_client($user));
    return array_values(array_unique(array_merge($baseModules, $defaults)));
}

function access_current_effective_modules(): array
{
    $effectiveUser = access_effective_user_record();
    if (!$effectiveUser) {
        return access_base_module_keys();
    }

    return access_effective_modules_for_user($effectiveUser);
}

function access_current_navigation_modules(): array
{
    $actor = access_current_actor_record();
    $modules = access_current_effective_modules();

    if ($actor) {
        $modules = array_merge($modules, access_actor_navigation_modules($actor));
    }

    return array_values(array_unique($modules));
}

function has_current_module_access(string $moduleKey): bool
{
    return in_array($moduleKey, access_current_navigation_modules(), true);
}

function access_can_manage_target_user(array $actor, array $target, bool $allowSelf = false): bool
{
    $actorId = (int)($actor['id'] ?? 0);
    $targetId = (int)($target['id'] ?? 0);

    if ($allowSelf && $actorId > 0 && $actorId === $targetId) {
        return true;
    }

    $actorRole = (string)($actor['role'] ?? '');
    if ($actorRole === 'admin') {
        return $targetId > 0;
    }

    if ($actorRole !== 'gestor_financeiro') {
        return false;
    }

    return (string)($target['role'] ?? '') === 'user'
        && (int)($target['manager_user_id'] ?? 0) === $actorId;
}

function access_can_scope_to_target_user(array $actor, array $target): bool
{
    if ((string)($actor['role'] ?? '') === 'admin') {
        return (int)($target['id'] ?? 0) > 0;
    }

    if ((string)($actor['role'] ?? '') !== 'gestor_financeiro') {
        return false;
    }

    return (string)($target['role'] ?? '') === 'user'
        && (int)($target['manager_user_id'] ?? 0) === (int)($actor['id'] ?? 0);
}

function access_allowed_roles_for_actor(array $actor, ?array $target = null): array
{
    if ((string)($actor['role'] ?? '') === 'admin') {
        return ['admin', 'gestor_financeiro', 'user'];
    }

    if ((string)($actor['role'] ?? '') === 'gestor_financeiro') {
        return ['user'];
    }

    return $target ? [(string)($target['role'] ?? 'user')] : ['user'];
}

function access_management_capabilities(?array $actor = null): array
{
    $actor = $actor ?: access_current_actor_record();
    $role = (string)($actor['role'] ?? '');

    return [
        'role' => $role,
        'role_label' => access_role_label($role),
        'is_admin' => $role === 'admin',
        'is_financial_manager' => $role === 'gestor_financeiro',
        'can_manage_users' => in_array($role, ['admin', 'gestor_financeiro'], true),
        'can_access_user_admin' => $role === 'admin',
        'can_access_manager_area' => in_array($role, ['admin', 'gestor_financeiro'], true),
        'can_use_visual_scope' => in_array($role, ['admin', 'gestor_financeiro'], true),
    ];
}

function require_user_management_access(): void
{
    if (!is_logged_in()) {
        flash('error', 'Faca login para continuar.');
        redirect('index.php?route=login');
    }

    if (!can_manage_users()) {
        flash('error', 'Acesso restrito a administradores e gestores financeiros.');
        redirect('index.php?route=dashboard');
    }
}

function require_module_access(string $moduleKey): void
{
    if (!is_logged_in()) {
        flash('error', 'Faca login para continuar.');
        redirect('index.php?route=login');
    }

    if (has_current_module_access($moduleKey)) {
        return;
    }

    $catalog = access_assignable_module_catalog();
    $label = $catalog[$moduleKey]['label'] ?? access_role_label($moduleKey);
    flash('error', 'Este acesso nao esta habilitado para o modulo ' . $label . '.');
    redirect('index.php?route=dashboard');
}
