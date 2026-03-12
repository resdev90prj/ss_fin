<?php
require_once __DIR__ . '/Model.php';

class Account extends Model
{
    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM accounts WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function activeByUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE user_id = :user_id AND status = 'active' ORDER BY name");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM accounts WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO accounts (user_id, name, type, institution, initial_balance, status) VALUES (:user_id, :name, :type, :institution, :initial_balance, :status)';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $sql = 'UPDATE accounts SET name = :name, type = :type, institution = :institution, initial_balance = :initial_balance, status = :status WHERE id = :id AND user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function toggleStatus(int $id, int $userId): bool
    {
        $sql = "UPDATE accounts SET status = IF(status='active','inactive','active') WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
