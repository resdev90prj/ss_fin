<?php
require_once __DIR__ . '/Model.php';

class User extends Model
{
    private ?bool $alertPreferencesTableAvailable = null;

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

    public function all(): array
    {
        $sql = 'SELECT id, name, email, role, status, created_at, updated_at
                FROM users
                ORDER BY id DESC';
        return $this->db->query($sql)->fetchAll();
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
        $sql = 'INSERT INTO users (name, email, password, role, status)
                VALUES (:name, :email, :password, :role, :status)';

        $this->db->prepare($sql)->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $sql = 'UPDATE users
                SET name = :name,
                    email = :email,
                    role = :role,
                    status = :status
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);
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
