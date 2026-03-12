<?php
require_once __DIR__ . '/Model.php';

class Budget extends Model
{
    public function allByUser(int $userId): array
    {
        $sql = 'SELECT b.*, c.name AS category_name
                FROM budgets b
                JOIN categories c ON c.id = b.category_id AND c.user_id = b.user_id
                WHERE b.user_id = :user_id
                ORDER BY b.month_ref DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function upsert(array $data): bool
    {
        $sql = 'INSERT INTO budgets (user_id, category_id, month_ref, amount_limit)
                VALUES (:user_id, :category_id, :month_ref, :amount_limit)
                ON DUPLICATE KEY UPDATE amount_limit = VALUES(amount_limit)';
        return $this->db->prepare($sql)->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->prepare('DELETE FROM budgets WHERE id=:id AND user_id=:user_id')->execute(['id' => $id, 'user_id' => $userId]);
    }
}
