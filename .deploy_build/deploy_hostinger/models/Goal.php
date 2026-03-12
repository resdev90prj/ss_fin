<?php
require_once __DIR__ . '/Model.php';

class Goal extends Model
{
    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM goals WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO goals (user_id, title, target_amount, current_amount, target_date, status)
                VALUES (:user_id, :title, :target_amount, :current_amount, :target_date, :status)';
        return $this->db->prepare($sql)->execute($data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $sql = 'UPDATE goals SET title=:title, target_amount=:target_amount, current_amount=:current_amount, target_date=:target_date, status=:status
                WHERE id=:id AND user_id=:user_id';
        return $this->db->prepare($sql)->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->prepare('DELETE FROM goals WHERE id=:id AND user_id=:user_id')->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM goals WHERE id=:id AND user_id=:user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
