<?php
require_once __DIR__ . '/db.php';

class CategoryAutoClassifier
{
    private PDO $db;

    private const STOPWORDS = [
        'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'nas', 'nos', 'para', 'por', 'com', 'sem', 'a', 'o', 'as', 'os',
        'pg', 'pag', 'pagto', 'compra', 'debito', 'credito', 'transferencia', 'pix', 'ted', 'doc', 'sa', 'ltda', 'me',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getConnection();
    }

    public function suggest(int $userId, string $description, string $transactionType): array
    {
        $normalized = self::normalizeDescription($description);
        $financeType = $this->normalizeFinanceType($transactionType);

        if ($userId <= 0 || $normalized === '') {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'empty_description', $normalized);
        }

        $best = $this->buildSuggestion(null, null, 'low', 0.0, 'not_found', $normalized);

        $exact = $this->layerExact($userId, $normalized, $financeType);
        if ($this->hasCategory($exact)) {
            if ($exact['confidence'] === 'high') {
                return $exact;
            }
            $best = $this->pickBest($best, $exact);
        }

        $partial = $this->layerPartialSimilarity($userId, $normalized, $financeType);
        if ($this->hasCategory($partial)) {
            if ($partial['confidence'] === 'high') {
                return $partial;
            }
            $best = $this->pickBest($best, $partial);
        }

        $keywords = $this->layerKeywords($userId, $normalized, $financeType);
        if ($this->hasCategory($keywords)) {
            if ($keywords['confidence'] === 'high') {
                return $keywords;
            }
            $best = $this->pickBest($best, $keywords);
        }

        $historical = $this->layerHistoricalFrequency($userId, $normalized, $financeType);
        if ($this->hasCategory($historical)) {
            $best = $this->pickBest($best, $historical);
        }

        return $best;
    }

    public function fallbackCategoryId(int $userId, string $transactionType): ?int
    {
        $financeType = $this->normalizeFinanceType($transactionType);

        if ($financeType === 'income') {
            $categoryId = $this->findCategoryIdByName($userId, 'Receitas', $financeType);
            if ($categoryId !== null) {
                return $categoryId;
            }
        } else {
            $expenseFallbacks = ['Outros gastos', 'Despesas empresariais', 'Gastos pessoais'];
            foreach ($expenseFallbacks as $name) {
                $categoryId = $this->findCategoryIdByName($userId, $name, $financeType);
                if ($categoryId !== null) {
                    return $categoryId;
                }
            }
        }

        $sql = "SELECT id
                FROM categories
                WHERE user_id = :user_id
                  AND status = 'active'
                  AND (type = 'both' OR type = :type)
                ORDER BY is_default DESC, id ASC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'type' => $financeType,
        ]);

        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public function learn(int $userId, string $description, int $categoryId): void
    {
        if ($userId <= 0 || $categoryId <= 0) {
            return;
        }

        $normalized = self::normalizeDescription($description);
        if ($normalized === '') {
            return;
        }

        $stmtCategory = $this->db->prepare('SELECT id FROM categories WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmtCategory->execute([
            'id' => $categoryId,
            'user_id' => $userId,
        ]);

        if (!$stmtCategory->fetch()) {
            return;
        }

        $sql = "INSERT INTO category_classifier_memory (user_id, normalized_description, category_id, usage_count, last_used_at)
                VALUES (:user_id, :normalized_description, :category_id, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    usage_count = usage_count + 1,
                    last_used_at = VALUES(last_used_at)";

        $this->db->prepare($sql)->execute([
            'user_id' => $userId,
            'normalized_description' => $normalized,
            'category_id' => $categoryId,
        ]);
    }

    public static function normalizeDescription(string $description): string
    {
        $value = mb_strtolower(trim($description), 'UTF-8');
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = preg_replace('/[^a-z0-9\s]/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string)$value);
        return trim((string)$value);
    }

    private function layerExact(int $userId, string $normalized, string $financeType): array
    {
        $sql = "SELECT m.category_id, c.name AS category_name, m.usage_count
                FROM category_classifier_memory m
                INNER JOIN categories c ON c.id = m.category_id
                WHERE m.user_id = :user_id_mem
                  AND m.normalized_description = :normalized
                  AND c.user_id = :user_id_cat
                  AND c.status = 'active'
                  AND (c.type = 'both' OR c.type = :type)
                ORDER BY m.usage_count DESC, m.last_used_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id_mem' => $userId,
            'user_id_cat' => $userId,
            'normalized' => $normalized,
            'type' => $financeType,
        ]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'exact_no_match', $normalized);
        }

        $total = 0;
        foreach ($rows as $row) {
            $total += (int)$row['usage_count'];
        }

        $top = $rows[0];
        $topCount = (int)$top['usage_count'];
        $ratio = $total > 0 ? $topCount / $total : 0;

        $confidence = ($topCount >= 3 || $ratio >= 0.8) ? 'high' : 'medium';
        $score = $confidence === 'high' ? 0.95 : max(0.7, min(0.89, 0.65 + $ratio));

        return $this->buildSuggestion(
            (int)$top['category_id'],
            (string)$top['category_name'],
            $confidence,
            $score,
            'exact_match',
            $normalized
        );
    }

    private function layerPartialSimilarity(int $userId, string $normalized, string $financeType): array
    {
        $tokens = $this->extractKeywords($normalized);
        if (empty($tokens)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'partial_no_tokens', $normalized);
        }

        $rows = $this->fetchMemoryCandidatesByTokens($userId, $tokens, $financeType, 300);
        if (empty($rows)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'partial_no_candidates', $normalized);
        }

        $aggregated = [];
        foreach ($rows as $row) {
            $candidateNormalized = (string)$row['normalized_description'];
            $similarity = $this->similarityScore($normalized, $candidateNormalized);
            if ($similarity < 0.35) {
                continue;
            }

            $categoryId = (int)$row['category_id'];
            $usage = (int)$row['usage_count'];
            $weighted = $similarity * (1 + log(1 + $usage));

            if (!isset($aggregated[$categoryId])) {
                $aggregated[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => (string)$row['category_name'],
                    'sum_weight' => 0.0,
                    'max_similarity' => 0.0,
                ];
            }

            $aggregated[$categoryId]['sum_weight'] += $weighted;
            if ($similarity > $aggregated[$categoryId]['max_similarity']) {
                $aggregated[$categoryId]['max_similarity'] = $similarity;
            }
        }

        if (empty($aggregated)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'partial_low_similarity', $normalized);
        }

        usort($aggregated, static function (array $a, array $b): int {
            if ($a['sum_weight'] === $b['sum_weight']) {
                return $b['max_similarity'] <=> $a['max_similarity'];
            }
            return $b['sum_weight'] <=> $a['sum_weight'];
        });

        $top = $aggregated[0];
        $secondWeight = isset($aggregated[1]) ? (float)$aggregated[1]['sum_weight'] : 0.0;
        $dominance = $secondWeight > 0 ? ((float)$top['sum_weight'] / $secondWeight) : 99.0;
        $topSimilarity = (float)$top['max_similarity'];

        $confidence = 'low';
        if ($topSimilarity >= 0.85 || ($topSimilarity >= 0.72 && $dominance >= 1.35)) {
            $confidence = 'high';
        } elseif ($topSimilarity >= 0.55) {
            $confidence = 'medium';
        }

        return $this->buildSuggestion(
            (int)$top['category_id'],
            (string)$top['category_name'],
            $confidence,
            min(0.93, max(0.45, $topSimilarity)),
            'partial_similarity',
            $normalized
        );
    }

    private function layerKeywords(int $userId, string $normalized, string $financeType): array
    {
        $tokens = $this->extractKeywords($normalized);
        if (empty($tokens)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'keywords_no_tokens', $normalized);
        }

        $whereParts = [];
        $params = [
            'type' => $financeType,
        ];

        foreach ($tokens as $i => $token) {
            $key = 'k' . $i;
            $whereParts[] = "m.normalized_description LIKE :{$key}";
            $params[$key] = '%' . $token . '%';
        }

        if (empty($whereParts)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'keywords_no_where', $normalized);
        }

        $sql = "SELECT m.category_id, c.name AS category_name, SUM(m.usage_count) AS freq
                FROM category_classifier_memory m
                INNER JOIN categories c ON c.id = m.category_id
                WHERE m.user_id = :user_id_mem
                  AND c.user_id = :user_id_cat
                  AND c.status = 'active'
                  AND (c.type = 'both' OR c.type = :type)
                  AND (" . implode(' OR ', $whereParts) . ")
                GROUP BY m.category_id, c.name
                ORDER BY freq DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $params['user_id_mem'] = $userId;
        $params['user_id_cat'] = $userId;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'keywords_no_match', $normalized);
        }

        $top = $rows[0];
        $topFreq = (int)$top['freq'];
        $totalFreq = 0;
        foreach ($rows as $row) {
            $totalFreq += (int)$row['freq'];
        }

        $ratio = $totalFreq > 0 ? $topFreq / $totalFreq : 0;
        $confidence = 'low';
        if ($topFreq >= 8 && $ratio >= 0.75) {
            $confidence = 'high';
        } elseif ($topFreq >= 2 && $ratio >= 0.5) {
            $confidence = 'medium';
        }

        return $this->buildSuggestion(
            (int)$top['category_id'],
            (string)$top['category_name'],
            $confidence,
            min(0.9, max(0.4, 0.4 + $ratio)),
            'keywords_frequency',
            $normalized
        );
    }

    private function layerHistoricalFrequency(int $userId, string $normalized, string $financeType): array
    {
        $sql = "SELECT m.category_id, c.name AS category_name, m.normalized_description, m.usage_count
                FROM category_classifier_memory m
                INNER JOIN categories c ON c.id = m.category_id
                WHERE m.user_id = :user_id_mem
                  AND c.user_id = :user_id_cat
                  AND c.status = 'active'
                  AND (c.type = 'both' OR c.type = :type)
                ORDER BY m.usage_count DESC, m.last_used_at DESC
                LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id_mem' => $userId,
            'user_id_cat' => $userId,
            'type' => $financeType,
        ]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'historical_empty', $normalized);
        }

        $aggregated = [];
        foreach ($rows as $row) {
            $similarity = $this->similarityScore($normalized, (string)$row['normalized_description']);
            if ($similarity < 0.45) {
                continue;
            }

            $categoryId = (int)$row['category_id'];
            $usage = (int)$row['usage_count'];
            $weighted = $usage * $similarity;

            if (!isset($aggregated[$categoryId])) {
                $aggregated[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => (string)$row['category_name'],
                    'sum_weight' => 0.0,
                    'max_similarity' => 0.0,
                ];
            }

            $aggregated[$categoryId]['sum_weight'] += $weighted;
            if ($similarity > $aggregated[$categoryId]['max_similarity']) {
                $aggregated[$categoryId]['max_similarity'] = $similarity;
            }
        }

        if (empty($aggregated)) {
            return $this->buildSuggestion(null, null, 'low', 0.0, 'historical_no_similarity', $normalized);
        }

        usort($aggregated, static function (array $a, array $b): int {
            return $b['sum_weight'] <=> $a['sum_weight'];
        });

        $top = $aggregated[0];
        $topSimilarity = (float)$top['max_similarity'];
        $confidence = $topSimilarity >= 0.8 ? 'high' : ($topSimilarity >= 0.55 ? 'medium' : 'low');

        return $this->buildSuggestion(
            (int)$top['category_id'],
            (string)$top['category_name'],
            $confidence,
            min(0.92, max(0.4, $topSimilarity)),
            'historical_frequency',
            $normalized
        );
    }

    private function fetchMemoryCandidatesByTokens(int $userId, array $tokens, string $financeType, int $limit): array
    {
        $whereParts = [];
        $params = [
            'type' => $financeType,
        ];

        $tokens = array_values(array_unique($tokens));
        foreach ($tokens as $i => $token) {
            $key = 'token_' . $i;
            $whereParts[] = "m.normalized_description LIKE :{$key}";
            $params[$key] = '%' . $token . '%';
        }

        if (empty($whereParts)) {
            return [];
        }

        $limit = max(1, min(500, $limit));

        $sql = "SELECT m.category_id, c.name AS category_name, m.normalized_description, m.usage_count
                FROM category_classifier_memory m
                INNER JOIN categories c ON c.id = m.category_id
                WHERE m.user_id = :user_id_mem
                  AND c.user_id = :user_id_cat
                  AND c.status = 'active'
                  AND (c.type = 'both' OR c.type = :type)
                  AND (" . implode(' OR ', $whereParts) . ")
                ORDER BY m.usage_count DESC, m.last_used_at DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $params['user_id_mem'] = $userId;
        $params['user_id_cat'] = $userId;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function extractKeywords(string $normalized): array
    {
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $token = trim((string)$part);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $token)) {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    private function similarityScore(string $left, string $right): float
    {
        $leftTokens = $this->extractKeywords($left);
        $rightTokens = $this->extractKeywords($right);

        if (empty($leftTokens) || empty($rightTokens)) {
            similar_text($left, $right, $percent);
            return max(0.0, min(1.0, ((float)$percent) / 100));
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));

        $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0.0;

        similar_text($left, $right, $percent);
        $textSimilarity = max(0.0, min(1.0, ((float)$percent) / 100));

        return (0.65 * $jaccard) + (0.35 * $textSimilarity);
    }

    private function normalizeFinanceType(string $transactionType): string
    {
        return $transactionType === 'income' ? 'income' : 'expense';
    }

    private function hasCategory(array $suggestion): bool
    {
        return isset($suggestion['category_id']) && is_numeric($suggestion['category_id']) && (int)$suggestion['category_id'] > 0;
    }

    private function buildSuggestion(
        ?int $categoryId,
        ?string $categoryName,
        string $confidence,
        float $score,
        string $reason,
        string $normalizedDescription
    ): array {
        $allowedConfidence = ['high', 'medium', 'low'];
        if (!in_array($confidence, $allowedConfidence, true)) {
            $confidence = 'low';
        }

        return [
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'confidence' => $confidence,
            'score' => round(max(0.0, min(1.0, $score)), 4),
            'reason' => $reason,
            'normalized_description' => $normalizedDescription,
        ];
    }

    private function pickBest(array $left, array $right): array
    {
        $rank = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
        ];

        $leftConfidence = $rank[$left['confidence'] ?? 'low'] ?? 1;
        $rightConfidence = $rank[$right['confidence'] ?? 'low'] ?? 1;

        if ($rightConfidence > $leftConfidence) {
            return $right;
        }
        if ($rightConfidence < $leftConfidence) {
            return $left;
        }

        $leftScore = (float)($left['score'] ?? 0);
        $rightScore = (float)($right['score'] ?? 0);

        return $rightScore > $leftScore ? $right : $left;
    }

    private function findCategoryIdByName(int $userId, string $name, string $financeType): ?int
    {
        $sql = "SELECT id
                FROM categories
                WHERE user_id = :user_id
                  AND status = 'active'
                  AND name = :name
                  AND (type = 'both' OR type = :type)
                ORDER BY is_default DESC, id ASC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $financeType,
        ]);

        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
