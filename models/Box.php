<?php
require_once __DIR__ . '/Model.php';

class Box extends Model
{
    public function allByUser(int $userId): array
    {
        $sql = 'SELECT b.*, a.name AS account_name
                FROM boxes b
                LEFT JOIN accounts a ON a.id = b.account_id AND a.user_id = b.user_id
                WHERE b.user_id = :user_id
                ORDER BY b.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function activeByUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM boxes WHERE user_id = :user_id AND status='active' ORDER BY name");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM boxes WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO boxes (user_id, account_id, name, objective, balance, status) VALUES (:user_id, :account_id, :name, :objective, :balance, :status)';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $sql = 'UPDATE boxes SET account_id = :account_id, name = :name, objective = :objective, balance = :balance, status = :status WHERE id = :id AND user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
}
