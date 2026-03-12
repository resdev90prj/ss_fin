<?php
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/../includes/CategoryAutoClassifier.php';

class Transaction extends Model
{
    public function listByUser(int $userId, array $filters = [], bool $prioritizeOthers = false): array
    {
        $sql = 'SELECT t.*, c.name AS category_name, a.name AS account_name, b.name AS box_name
                FROM transactions t
                JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
                JOIN accounts a ON a.id = t.account_id AND a.user_id = t.user_id
                LEFT JOIN boxes b ON b.id = t.box_id AND b.user_id = t.user_id
                WHERE t.user_id = :user_id';
        $params = ['user_id' => $userId];

        if (!empty($filters['from'])) {
            $sql .= ' AND t.transaction_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND t.transaction_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND t.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND t.category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= ' AND t.account_id = :account_id';
            $params['account_id'] = (int)$filters['account_id'];
        }

        if ($prioritizeOthers) {
            $sql .= ' ORDER BY CASE WHEN c.name = :others_name THEN 0 ELSE 1 END ASC, t.transaction_date DESC, t.id DESC';
            $params['others_name'] = 'Outros gastos';
        } else {
            $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM transactions WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): bool
    {
        $sql = 'INSERT INTO transactions (user_id, account_id, box_id, category_id, type, mode, description, amount, transaction_date, payment_method, notes, source)
                VALUES (:user_id, :account_id, :box_id, :category_id, :type, :mode, :description, :amount, :transaction_date, :payment_method, :notes, :source)';
        $ok = $this->db->prepare($sql)->execute($data);
        if ($ok) {
            $this->learnCategoryMapping((int)$data['user_id'], (string)$data['description'], (int)$data['category_id']);
        }
        return $ok;
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $data['id'] = $id;
        $data['user_id'] = $userId;
        $sql = 'UPDATE transactions SET account_id=:account_id, box_id=:box_id, category_id=:category_id, type=:type, mode=:mode, description=:description,
                amount=:amount, transaction_date=:transaction_date, payment_method=:payment_method, notes=:notes
                WHERE id=:id AND user_id=:user_id';
        $ok = $this->db->prepare($sql)->execute($data);
        if ($ok) {
            $this->learnCategoryMapping($userId, (string)$data['description'], (int)$data['category_id']);
        }
        return $ok;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function countOthersPending(int $userId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
                WHERE t.user_id = :user_id
                  AND c.name = :others_name";
        $params = [
            'user_id' => $userId,
            'others_name' => 'Outros gastos',
        ];

        if (!empty($filters['from'])) {
            $sql .= ' AND t.transaction_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND t.transaction_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND t.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND t.category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= ' AND t.account_id = :account_id';
            $params['account_id'] = (int)$filters['account_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    public function autoClassifyOthers(int $userId): array
    {
        $bootstrappedFromTransactions = 0;
        if (!$this->hasClassifierMemory($userId)) {
            $bootstrappedFromTransactions = $this->bootstrapClassifierMemoryFromTransactions($userId);
        }

        $sql = "SELECT t.id, t.description, t.type, t.category_id
                FROM transactions t
                JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
                WHERE t.user_id = :user_id
                  AND c.name = :others_name
                ORDER BY t.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'others_name' => 'Outros gastos',
        ]);
        $rows = $stmt->fetchAll();

        $classifier = new CategoryAutoClassifier($this->db);
        $updateStmt = $this->db->prepare('UPDATE transactions SET category_id = :category_id WHERE id = :id AND user_id = :user_id');
        $othersCategoryId = $this->findOthersCategoryId($userId);

        $processed = count($rows);
        $reclassified = 0;
        $highConfidence = 0;
        $mediumConfidence = 0;
        $unchanged = 0;
        $diagnostics = [
            'description_empty' => 0,
            'primary_suggested_others' => 0,
            'primary_low_confidence' => 0,
            'history_no_match' => 0,
            'history_insufficient_tokens' => 0,
            'history_weak_signal' => 0,
            'update_failed' => 0,
        ];

        foreach ($rows as $row) {
            $currentCategoryId = (int)$row['category_id'];
            $description = trim((string)($row['description'] ?? ''));
            $type = (string)($row['type'] ?? 'expense');

            if ($description === '') {
                $diagnostics['description_empty']++;
                $unchanged++;
                continue;
            }

            $suggestion = $classifier->suggest($userId, $description, $type);
            $confidence = (string)($suggestion['confidence'] ?? 'low');
            $suggestedCategoryId = (int)($suggestion['category_id'] ?? 0);
            $targetCategoryId = 0;
            $targetConfidence = 'low';

            if (
                in_array($confidence, ['high', 'medium'], true) &&
                $suggestedCategoryId > 0 &&
                $suggestedCategoryId !== $currentCategoryId &&
                ($othersCategoryId === null || $suggestedCategoryId !== $othersCategoryId)
            ) {
                $targetCategoryId = $suggestedCategoryId;
                $targetConfidence = $confidence;
            } else {
                if (
                    $othersCategoryId !== null &&
                    $suggestedCategoryId === $othersCategoryId &&
                    in_array($confidence, ['high', 'medium'], true)
                ) {
                    $diagnostics['primary_suggested_others']++;
                } elseif (!in_array($confidence, ['high', 'medium'], true)) {
                    $diagnostics['primary_low_confidence']++;
                }
            }

            if ($targetCategoryId <= 0) {
                $historySuggestion = $this->suggestFromHistoryWithoutOthers(
                    $userId,
                    $description,
                    $type,
                    $othersCategoryId
                );

                if ((int)($historySuggestion['category_id'] ?? 0) > 0) {
                    $targetCategoryId = (int)$historySuggestion['category_id'];
                    $targetConfidence = (string)$historySuggestion['confidence'];
                } else {
                    $reason = (string)($historySuggestion['reason'] ?? 'history_no_match');
                    if ($reason === 'history_insufficient_tokens') {
                        $diagnostics['history_insufficient_tokens']++;
                    } elseif ($reason === 'history_weak_signal') {
                        $diagnostics['history_weak_signal']++;
                    } else {
                        $diagnostics['history_no_match']++;
                    }
                }
            }

            if ($targetCategoryId <= 0 || $targetCategoryId === $currentCategoryId) {
                $unchanged++;
                continue;
            }

            $ok = $updateStmt->execute([
                'category_id' => $targetCategoryId,
                'id' => (int)$row['id'],
                'user_id' => $userId,
            ]);

            if (!$ok) {
                $diagnostics['update_failed']++;
                $unchanged++;
                continue;
            }

            $classifier->learn($userId, $description, $targetCategoryId);
            $reclassified++;
            if ($targetConfidence === 'high') {
                $highConfidence++;
            } else {
                $mediumConfidence++;
            }
        }

        return [
            'processed' => $processed,
            'reclassified' => $reclassified,
            'high_confidence' => $highConfidence,
            'medium_confidence' => $mediumConfidence,
            'unchanged' => $unchanged,
            'remaining_others' => $this->countOthersPending($userId),
            'diagnostics' => $diagnostics,
            'bootstrapped_from_transactions' => $bootstrappedFromTransactions,
        ];
    }

    private function findOthersCategoryId(int $userId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE user_id = :user_id AND name = :name LIMIT 1");
        $stmt->execute([
            'user_id' => $userId,
            'name' => 'Outros gastos',
        ]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    private function suggestFromHistoryWithoutOthers(
        int $userId,
        string $description,
        string $transactionType,
        ?int $othersCategoryId
    ): array {
        $normalized = CategoryAutoClassifier::normalizeDescription($description);
        if ($normalized === '') {
            return [
                'category_id' => 0,
                'confidence' => 'low',
                'reason' => 'history_empty_description',
            ];
        }

        $financeType = $this->normalizeFinanceType($transactionType);

        $exactSql = "SELECT m.category_id, SUM(m.usage_count) AS freq
                     FROM category_classifier_memory m
                     INNER JOIN categories c ON c.id = m.category_id
                     WHERE m.user_id = :user_id_mem
                       AND c.user_id = :user_id_cat
                       AND c.status = 'active'
                       AND (c.type = 'both' OR c.type = :type)
                       AND m.normalized_description = :normalized";

        $exactParams = [
            'user_id_mem' => $userId,
            'user_id_cat' => $userId,
            'type' => $financeType,
            'normalized' => $normalized,
        ];

        if ($othersCategoryId !== null) {
            $exactSql .= ' AND c.id <> :others_category_id';
            $exactParams['others_category_id'] = $othersCategoryId;
        }

        $exactSql .= ' GROUP BY m.category_id ORDER BY freq DESC LIMIT 1';
        $exactStmt = $this->db->prepare($exactSql);
        $exactStmt->execute($exactParams);
        $exactRow = $exactStmt->fetch();

        if ($exactRow && (int)$exactRow['category_id'] > 0) {
            $freq = (int)($exactRow['freq'] ?? 0);
            return [
                'category_id' => (int)$exactRow['category_id'],
                'confidence' => $freq >= 3 ? 'high' : 'medium',
                'reason' => 'history_exact_non_others',
            ];
        }

        $tokens = array_values(array_unique(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            static function (string $token): bool {
                return strlen($token) >= 3 && !preg_match('/^\d+$/', $token);
            }
        )));

        if (empty($tokens)) {
            return [
                'category_id' => 0,
                'confidence' => 'low',
                'reason' => 'history_insufficient_tokens',
            ];
        }

        $whereParts = [];
        $partialParams = [
            'user_id_mem' => $userId,
            'user_id_cat' => $userId,
            'type' => $financeType,
        ];

        foreach ($tokens as $i => $token) {
            $key = 'token_' . $i;
            $whereParts[] = "m.normalized_description LIKE :{$key}";
            $partialParams[$key] = '%' . $token . '%';
        }

        $partialSql = "SELECT m.category_id, SUM(m.usage_count) AS freq
                       FROM category_classifier_memory m
                       INNER JOIN categories c ON c.id = m.category_id
                       WHERE m.user_id = :user_id_mem
                         AND c.user_id = :user_id_cat
                         AND c.status = 'active'
                         AND (c.type = 'both' OR c.type = :type)
                         AND (" . implode(' OR ', $whereParts) . ")";

        if ($othersCategoryId !== null) {
            $partialSql .= ' AND c.id <> :others_category_id';
            $partialParams['others_category_id'] = $othersCategoryId;
        }

        $partialSql .= ' GROUP BY m.category_id ORDER BY freq DESC LIMIT 2';
        $partialStmt = $this->db->prepare($partialSql);
        $partialStmt->execute($partialParams);
        $partialRows = $partialStmt->fetchAll();

        if (empty($partialRows)) {
            return [
                'category_id' => 0,
                'confidence' => 'low',
                'reason' => 'history_no_match',
            ];
        }

        $top = $partialRows[0];
        $topCategoryId = (int)($top['category_id'] ?? 0);
        $topFreq = (int)($top['freq'] ?? 0);
        $secondFreq = isset($partialRows[1]) ? (int)($partialRows[1]['freq'] ?? 0) : 0;

        if ($topCategoryId <= 0 || $topFreq < 2) {
            return [
                'category_id' => 0,
                'confidence' => 'low',
                'reason' => 'history_weak_signal',
            ];
        }

        $confidence = ($topFreq >= 5 && ($secondFreq === 0 || $topFreq >= ($secondFreq * 2))) ? 'high' : 'medium';

        return [
            'category_id' => $topCategoryId,
            'confidence' => $confidence,
            'reason' => 'history_partial_non_others',
        ];
    }

    private function normalizeFinanceType(string $transactionType): string
    {
        return $transactionType === 'income' ? 'income' : 'expense';
    }

    private function hasClassifierMemory(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM category_classifier_memory WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        return (bool)$stmt->fetch();
    }

    private function bootstrapClassifierMemoryFromTransactions(int $userId): int
    {
        $sql = "SELECT t.description, t.category_id
                FROM transactions t
                JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
                WHERE t.user_id = :user_id
                  AND c.name <> :others_name
                  AND t.description IS NOT NULL
                  AND TRIM(t.description) <> ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'others_name' => 'Outros gastos',
        ]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $classifier = new CategoryAutoClassifier($this->db);
        $learned = 0;
        foreach ($rows as $row) {
            $description = trim((string)($row['description'] ?? ''));
            $categoryId = (int)($row['category_id'] ?? 0);
            if ($description === '' || $categoryId <= 0) {
                continue;
            }
            $classifier->learn($userId, $description, $categoryId);
            $learned++;
        }

        return $learned;
    }

    public function summaryMonth(int $userId, string $month): array
    {
        [$startDate, $nextMonthStart] = $this->monthBoundaries($month);

        $sql = "SELECT
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS incomes,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expenses,
                SUM(CASE WHEN type='partner_withdrawal' THEN amount ELSE 0 END) AS withdrawals
                FROM transactions
                WHERE user_id = :user_id
                  AND transaction_date >= :start_date
                  AND transaction_date < :next_month_start";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'next_month_start' => $nextMonthStart,
        ]);
        return $stmt->fetch() ?: ['incomes' => 0, 'expenses' => 0, 'withdrawals' => 0];
    }

    public function balanceTotal(int $userId): float
    {
        $sql = "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0) AS balance FROM transactions WHERE user_id=:user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return (float)($row['balance'] ?? 0);
    }

    public function expensesByCategoryMonth(int $userId, string $month): array
    {
        [$startDate, $nextMonthStart] = $this->monthBoundaries($month);

        $sql = "SELECT c.name, SUM(t.amount) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
                WHERE t.user_id = :user_id
                  AND t.type IN ('expense','partner_withdrawal')
                  AND t.transaction_date >= :start_date
                  AND t.transaction_date < :next_month_start
                GROUP BY c.name
                ORDER BY total DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'next_month_start' => $nextMonthStart,
        ]);
        return $stmt->fetchAll();
    }

    private function monthBoundaries(string $month): array
    {
        $base = $month . '-01';
        $startDate = date('Y-m-d', strtotime($base));
        $nextMonthStart = date('Y-m-01', strtotime($base . ' +1 month'));
        return [$startDate, $nextMonthStart];
    }

    public function monthlyEvolution(int $userId, int $months = 6): array
    {
        $sql = "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS period,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS incomes,
                SUM(CASE WHEN type IN ('expense','partner_withdrawal') THEN amount ELSE 0 END) AS expenses
                FROM transactions
                WHERE user_id = :user_id
                  AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY period ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function monthlyEvolutionRange(int $userId, string $startMonth, string $endMonth): array
    {
        $startDate = $startMonth . '-01';
        $endDate = date('Y-m-t', strtotime($endMonth . '-01'));

        $sql = "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS period,
                SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS incomes,
                SUM(CASE WHEN type IN ('expense','partner_withdrawal') THEN amount ELSE 0 END) AS expenses
                FROM transactions
                WHERE user_id = :user_id
                  AND transaction_date BETWEEN :start_date AND :end_date
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY period ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        return $stmt->fetchAll();
    }

    public function existsByOfxFitId(int $userId, int $accountId, string $fitId): bool
    {
        if (trim($fitId) === '') {
            return false;
        }

        $sql = "SELECT id
                FROM transactions
                WHERE user_id = :user_id
                  AND account_id = :account_id
                  AND source = 'import_ofx'
                  AND notes LIKE :fitid
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'account_id' => $accountId,
            'fitid' => '%OFX_FITID:' . $fitId . '%',
        ]);

        return (bool)$stmt->fetch();
    }

    public function findPotentialDuplicates(
        int $userId,
        int $accountId,
        string $transactionDate,
        float $amount,
        string $type
    ): array {
        $sql = "SELECT id, description
                FROM transactions
                WHERE user_id = :user_id
                  AND account_id = :account_id
                  AND transaction_date = :transaction_date
                  AND amount = :amount
                  AND type = :type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'account_id' => $accountId,
            'transaction_date' => $transactionDate,
            'amount' => $amount,
            'type' => $type,
        ]);

        return $stmt->fetchAll();
    }

    private function learnCategoryMapping(int $userId, string $description, int $categoryId): void
    {
        if ($userId <= 0 || $categoryId <= 0 || trim($description) === '') {
            return;
        }

        try {
            (new CategoryAutoClassifier($this->db))->learn($userId, $description, $categoryId);
        } catch (Throwable $e) {
            return;
        }
    }
}
