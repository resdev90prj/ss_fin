<?php
require_once __DIR__ . '/Model.php';

class User extends Model
{
    private ?bool $alertPreferencesTableAvailable = null;
    private ?bool $managerUserColumnAvailable = null;
    private ?bool $moduleAccessTableAvailable = null;

    public function findByEmail(string $email, bool $onlyActive = true): ?array
    {
        $sql = 'SELECT * FROM users WHERE email = :email';
        if ($onlyActive) {
            $sql .= ' AND status = 1';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByIdDetailed(int $id): ?array
    {
        $sql = 'SELECT ' . $this->userSelectFields() . '
                FROM users u
                ' . $this->managerJoinClause() . '
                WHERE u.id = :id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        $rows = [$user];
        $this->hydrateManagementMetadata($rows);
        $user = $rows[0];
        return $user;
    }

    public function all(): array
    {
        $sql = 'SELECT ' . $this->userSelectFields() . '
                FROM users u
                ' . $this->managerJoinClause() . '
                ORDER BY u.id DESC';

        $rows = $this->db->query($sql)->fetchAll();
        $this->hydrateManagementMetadata($rows);
        return $rows;
    }

    public function listForManagement(array $actor, array $filters = []): array
    {
        $actorRole = (string)($actor['role'] ?? '');
        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0 || !in_array($actorRole, ['admin', 'gestor_financeiro'], true)) {
            return [];
        }

        $relationship = trim((string)($filters['relationship'] ?? 'all'));
        $managerUserId = (int)($filters['manager_user_id'] ?? 0);
        $roleFilter = trim((string)($filters['role'] ?? ''));

        $sql = 'SELECT ' . $this->userSelectFields() . '
                FROM users u
                ' . $this->managerJoinClause();
        $where = [];
        $params = [];

        if ($actorRole === 'gestor_financeiro') {
            if (!$this->hasManagerUserColumn()) {
                return [];
            }

            $where[] = 'u.role = :managed_role';
            $where[] = 'u.manager_user_id = :actor_manager_user_id';
            $params['managed_role'] = 'user';
            $params['actor_manager_user_id'] = $actorId;
        } else {
            if ($relationship === 'managed') {
                if (!$this->hasManagerUserColumn()) {
                    return [];
                }

                $where[] = 'u.role = :managed_role';
                $where[] = 'u.manager_user_id IS NOT NULL';
                $params['managed_role'] = 'user';

                if ($managerUserId > 0) {
                    $where[] = 'u.manager_user_id = :manager_user_id';
                    $params['manager_user_id'] = $managerUserId;
                }
            } elseif ($relationship === 'managers') {
                $where[] = 'u.role = :manager_role';
                $params['manager_role'] = 'gestor_financeiro';
            }

            if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'gestor_financeiro', 'user'], true)) {
                $where[] = 'u.role = :role_filter';
                $params['role_filter'] = $roleFilter;
            }
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY u.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $this->hydrateManagementMetadata($rows);
        return $rows;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int
    {
        $fields = ['name', 'email', 'password', 'role', 'status'];
        $placeholders = [':name', ':email', ':password', ':role', ':status'];
        $params = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'status' => $data['status'],
        ];

        if ($this->hasManagerUserColumn()) {
            $fields[] = 'manager_user_id';
            $placeholders[] = ':manager_user_id';
            $params['manager_user_id'] = !empty($data['manager_user_id']) ? (int)$data['manager_user_id'] : null;
        }

        $sql = sprintf(
            'INSERT INTO users (%s) VALUES (%s)',
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $this->db->prepare($sql)->execute($params);

        return (int)$this->db->lastInsertId();
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $sets = [
            'name = :name',
            'email = :email',
            'role = :role',
            'status = :status',
        ];
        $params = [
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status'],
        ];

        if ($this->hasManagerUserColumn()) {
            $sets[] = 'manager_user_id = :manager_user_id';
            $params['manager_user_id'] = !empty($data['manager_user_id']) ? (int)$data['manager_user_id'] : null;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        return $this->db->prepare($sql)->execute($params);
    }

    public function updateOwnProfile(int $id, string $name, string $email): bool
    {
        $sql = 'UPDATE users
                SET name = :name,
                    email = :email
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }

    public function setStatus(int $id, int $status): bool
    {
        return $this->db->prepare('UPDATE users SET status = :status WHERE id = :id')
            ->execute(['id' => $id, 'status' => $status]);
    }

    public function resetPassword(int $id, string $passwordHash): bool
    {
        return $this->db->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute(['id' => $id, 'password' => $passwordHash]);
    }

    public function activeUserIds(): array
    {
        $rows = $this->db->query('SELECT id FROM users WHERE status = 1 ORDER BY id ASC')->fetchAll();
        return array_map(static fn(array $row): int => (int)$row['id'], $rows);
    }

    public function managerOptions(): array
    {
        $stmt = $this->db->prepare('SELECT id, name, email
                                    FROM users
                                    WHERE role = :role AND status = 1
                                    ORDER BY name ASC, id ASC');
        $stmt->execute(['role' => 'gestor_financeiro']);
        return $stmt->fetchAll();
    }

    public function moduleAccessStateByUserId(int $userId): array
    {
        $state = $this->moduleAccessStateByUserIds([$userId]);

        return [
            'map' => $state['maps'][$userId] ?? [],
            'has_records' => !empty($state['records'][$userId]),
        ];
    }

    public function moduleAccessStateByUserIds(array $userIds): array
    {
        $normalizedIds = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $normalizedIds[$userId] = $userId;
            }
        }

        if ($normalizedIds === [] || !$this->hasModuleAccessTable()) {
            return ['maps' => [], 'records' => []];
        }

        $params = [];
        $placeholders = [];
        $index = 0;
        foreach (array_values($normalizedIds) as $userId) {
            $param = 'user_id_' . $index++;
            $placeholders[] = ':' . $param;
            $params[$param] = $userId;
        }

        $sql = 'SELECT user_id, module_key, is_enabled
                FROM user_module_access
                WHERE user_id IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $maps = [];
        $records = [];

        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            $moduleKey = (string)($row['module_key'] ?? '');
            if ($userId <= 0 || $moduleKey === '') {
                continue;
            }

            $records[$userId] = true;
            if (!isset($maps[$userId])) {
                $maps[$userId] = [];
            }
            $maps[$userId][$moduleKey] = (int)($row['is_enabled'] ?? 0) === 1;
        }

        return [
            'maps' => $maps,
            'records' => $records,
        ];
    }

    public function saveModuleAccessMap(int $userId, array $moduleAccess): bool
    {
        if ($userId <= 0 || !$this->hasModuleAccessTable()) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $this->db->prepare('DELETE FROM user_module_access WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);

            if ($moduleAccess !== []) {
                $stmt = $this->db->prepare('INSERT INTO user_module_access (user_id, module_key, is_enabled)
                                            VALUES (:user_id, :module_key, :is_enabled)');

                foreach ($moduleAccess as $moduleKey => $isEnabled) {
                    $moduleKey = trim((string)$moduleKey);
                    if ($moduleKey === '') {
                        continue;
                    }

                    $stmt->execute([
                        'user_id' => $userId,
                        'module_key' => $moduleKey,
                        'is_enabled' => $isEnabled ? 1 : 0,
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function clearModuleAccess(int $userId): bool
    {
        if ($userId <= 0 || !$this->hasModuleAccessTable()) {
            return false;
        }

        return $this->db->prepare('DELETE FROM user_module_access WHERE user_id = :user_id')
            ->execute(['user_id' => $userId]);
    }

    public function alertPreferencesByUserId(int $userId): array
    {
        $user = $this->findById($userId);
        $default = [
            'user_id' => $userId,
            'receber_alerta_email' => 1,
            'email_notificacao' => (string)($user['email'] ?? ''),
            'alerta_frequencia' => 'daily',
            'alerta_horario' => '08:00',
        ];

        if (!$this->hasAlertPreferencesTable()) {
            return $default;
        }

        $stmt = $this->db->prepare('SELECT user_id, receber_alerta_email, email_notificacao, alerta_frequencia, alerta_horario
                                    FROM user_alert_preferences
                                    WHERE user_id = :user_id
                                    LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return $default;
        }

        $email = trim((string)($row['email_notificacao'] ?? ''));
        if ($email === '') {
            $email = (string)($user['email'] ?? '');
        }

        return [
            'user_id' => $userId,
            'receber_alerta_email' => (int)($row['receber_alerta_email'] ?? 1) === 1 ? 1 : 0,
            'email_notificacao' => $email,
            'alerta_frequencia' => $this->normalizeAlertFrequency((string)($row['alerta_frequencia'] ?? 'daily')),
            'alerta_horario' => $this->normalizeAlertHour((string)($row['alerta_horario'] ?? '08:00')),
        ];
    }

    public function updateOwnAlertPreferences(int $userId, array $data): bool
    {
        if (!$this->hasAlertPreferencesTable()) {
            return false;
        }

        $receberAlertaEmail = !empty($data['receber_alerta_email']) ? 1 : 0;
        $emailNotificacao = trim((string)($data['email_notificacao'] ?? ''));
        $alertaFrequencia = $this->normalizeAlertFrequency((string)($data['alerta_frequencia'] ?? 'daily'));
        $alertaHorario = $this->normalizeAlertHour((string)($data['alerta_horario'] ?? '08:00'));

        $sql = 'INSERT INTO user_alert_preferences
                (user_id, receber_alerta_email, email_notificacao, alerta_frequencia, alerta_horario)
                VALUES
                (:user_id, :receber_alerta_email, :email_notificacao, :alerta_frequencia, :alerta_horario)
                ON DUPLICATE KEY UPDATE
                    receber_alerta_email = VALUES(receber_alerta_email),
                    email_notificacao = VALUES(email_notificacao),
                    alerta_frequencia = VALUES(alerta_frequencia),
                    alerta_horario = VALUES(alerta_horario)';

        return $this->db->prepare($sql)->execute([
            'user_id' => $userId,
            'receber_alerta_email' => $receberAlertaEmail,
            'email_notificacao' => $emailNotificacao !== '' ? $emailNotificacao : null,
            'alerta_frequencia' => $alertaFrequencia,
            'alerta_horario' => $alertaHorario,
        ]);
    }

    public function activeUsersForAlerts(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));

        if ($this->hasAlertPreferencesTable()) {
            $sql = "SELECT u.id, u.name, u.email, u.status,
                           COALESCE(p.receber_alerta_email, 1) AS receber_alerta_email,
                           COALESCE(NULLIF(TRIM(p.email_notificacao), ''), u.email) AS email_notificacao,
                           COALESCE(NULLIF(TRIM(p.alerta_frequencia), ''), 'daily') AS alerta_frequencia,
                           COALESCE(NULLIF(TRIM(p.alerta_horario), ''), '08:00') AS alerta_horario
                    FROM users u
                    LEFT JOIN user_alert_preferences p ON p.user_id = u.id
                    WHERE u.status = 1
                    ORDER BY u.id ASC
                    LIMIT {$limit}";

            return $this->db->query($sql)->fetchAll();
        }

        $sql = "SELECT id, name, email, status
                FROM users
                WHERE status = 1
                ORDER BY id ASC
                LIMIT {$limit}";

        $rows = $this->db->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['receber_alerta_email'] = 1;
            $row['email_notificacao'] = (string)($row['email'] ?? '');
            $row['alerta_frequencia'] = 'daily';
            $row['alerta_horario'] = '08:00';
        }
        unset($row);

        return $rows;
    }

    public function hasAlertPreferencesTable(): bool
    {
        if ($this->alertPreferencesTableAvailable !== null) {
            return $this->alertPreferencesTableAvailable;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'user_alert_preferences'");
            $this->alertPreferencesTableAvailable = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $this->alertPreferencesTableAvailable = false;
        }

        return $this->alertPreferencesTableAvailable;
    }

    public function hasManagerUserColumn(): bool
    {
        if ($this->managerUserColumnAvailable !== null) {
            return $this->managerUserColumnAvailable;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM users LIKE 'manager_user_id'");
            $this->managerUserColumnAvailable = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $this->managerUserColumnAvailable = false;
        }

        return $this->managerUserColumnAvailable;
    }

    public function hasModuleAccessTable(): bool
    {
        if ($this->moduleAccessTableAvailable !== null) {
            return $this->moduleAccessTableAvailable;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'user_module_access'");
            $this->moduleAccessTableAvailable = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $this->moduleAccessTableAvailable = false;
        }

        return $this->moduleAccessTableAvailable;
    }

    private function userSelectFields(): string
    {
        $fields = [
            'u.id',
            'u.name',
            'u.email',
            'u.role',
            'u.status',
            'u.created_at',
            'u.updated_at',
        ];

        if ($this->hasManagerUserColumn()) {
            $fields[] = 'u.manager_user_id';
            $fields[] = 'manager.name AS manager_name';
            $fields[] = 'manager.email AS manager_email';
        }

        return implode(', ', $fields);
    }

    private function managerJoinClause(): string
    {
        if (!$this->hasManagerUserColumn()) {
            return '';
        }

        return 'LEFT JOIN users manager ON manager.id = u.manager_user_id';
    }

    private function hydrateManagementMetadata(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $userIds = [];
        foreach ($rows as &$row) {
            $row['manager_user_id'] = isset($row['manager_user_id']) && (int)$row['manager_user_id'] > 0
                ? (int)$row['manager_user_id']
                : null;
            $row['manager_name'] = isset($row['manager_name']) ? (string)$row['manager_name'] : null;
            $row['manager_email'] = isset($row['manager_email']) ? (string)$row['manager_email'] : null;
            $userIds[] = (int)($row['id'] ?? 0);
        }
        unset($row);

        $moduleState = $this->moduleAccessStateByUserIds($userIds);
        foreach ($rows as &$row) {
            $userId = (int)($row['id'] ?? 0);
            $row['module_access_map'] = $moduleState['maps'][$userId] ?? [];
            $row['has_module_access_rows'] = !empty($moduleState['records'][$userId]);
        }
        unset($row);
    }

    private function normalizeAlertFrequency(string $frequency): string
    {
        $frequency = strtolower(trim($frequency));
        if (!in_array($frequency, ['daily', 'weekdays', 'manual'], true)) {
            return 'daily';
        }
        return $frequency;
    }

    private function normalizeAlertHour(string $hour): string
    {
        $hour = trim($hour);
        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $hour)) {
            return '08:00';
        }
        return $hour;
    }
}
