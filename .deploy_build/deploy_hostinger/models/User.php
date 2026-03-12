<?php
require_once __DIR__ . '/Model.php';

class User extends Model
{
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
}