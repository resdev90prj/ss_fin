<?php
require_once __DIR__ . '/Model.php';

class Category extends Model
{
    /**
     * Categorias base do sistema. A estratégia "global" é replicar este template por usuário.
     */
    private const SYSTEM_DEFAULTS = [
        ['name' => 'Receitas', 'type' => 'income'],
        ['name' => 'Despesas empresariais', 'type' => 'expense'],
        ['name' => 'Gastos pessoais', 'type' => 'expense'],
        ['name' => 'Retirada do sócio', 'type' => 'expense'],
        ['name' => 'Impostos', 'type' => 'expense'],
        ['name' => 'Dívidas', 'type' => 'expense'],
        ['name' => 'Transferências', 'type' => 'both'],
        ['name' => 'Reserva financeira', 'type' => 'both'],
        ['name' => 'Outros gastos', 'type' => 'expense'],
    ];

    public function allByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE user_id = :user_id ORDER BY is_default DESC, name ASC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function activeByUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE user_id = :user_id AND status='active' ORDER BY name ASC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO categories (user_id, name, type, is_default, status) VALUES (:user_id, :name, :type, 0, :status)';
        return $this->db->prepare($sql)->execute($data);
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $sql = 'UPDATE categories SET name = :name, type = :type, status = :status WHERE id = :id AND user_id = :user_id';
        return $this->db->prepare($sql)->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        $sql = 'DELETE FROM categories WHERE id = :id AND user_id = :user_id AND is_default = 0';
        return $this->db->prepare($sql)->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function getDefaultOthers(int $userId): ?array
    {
        $sql = 'SELECT * FROM categories WHERE user_id = :user_id AND name = :name LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'name' => 'Outros gastos']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function ensureDefaultsForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $sql = 'INSERT IGNORE INTO categories (user_id, name, type, is_default, status)
                VALUES (:user_id, :name, :type, 1, "active")';
        $stmt = $this->db->prepare($sql);

        foreach (self::SYSTEM_DEFAULTS as $category) {
            $stmt->execute([
                'user_id' => $userId,
                'name' => $category['name'],
                'type' => $category['type'],
            ]);
        }
    }
}