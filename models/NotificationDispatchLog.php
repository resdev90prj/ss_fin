<?php
require_once __DIR__ . '/Model.php';

class NotificationDispatchLog extends Model
{
    private ?bool $tableAvailable = null;

    public function create(array $data): bool
    {
        if (!$this->hasTable()) {
            return false;
        }

        $sql = 'INSERT INTO notification_dispatch_logs
                (user_id, channel, provider, alert_code, subject, message_preview, status, error_message, payload_json)
                VALUES
                (:user_id, :channel, :provider, :alert_code, :subject, :message_preview, :status, :error_message, :payload_json)';

        $payload = $data['payload_json'] ?? null;
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } elseif (!is_string($payload)) {
            $payload = null;
        }

        return $this->db->prepare($sql)->execute([
            'user_id' => (int)($data['user_id'] ?? 0),
            'channel' => (string)($data['channel'] ?? ''),
            'provider' => (string)($data['provider'] ?? ''),
            'alert_code' => (string)($data['alert_code'] ?? ''),
            'subject' => (string)($data['subject'] ?? ''),
            'message_preview' => (string)($data['message_preview'] ?? ''),
            'status' => (string)($data['status'] ?? 'skipped'),
            'error_message' => (string)($data['error_message'] ?? ''),
            'payload_json' => $payload,
        ]);
    }

    public function lastSentAt(int $userId, string $channel): ?string
    {
        if (!$this->hasTable()) {
            return null;
        }

        $sql = 'SELECT created_at
                FROM notification_dispatch_logs
                WHERE user_id = :user_id
                  AND channel = :channel
                  AND status = "sent"
                ORDER BY id DESC
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'channel' => $channel,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return (string)($row['created_at'] ?? null);
    }

    private function hasTable(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'notification_dispatch_logs'");
            $this->tableAvailable = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $this->tableAvailable = false;
        }

        return $this->tableAvailable;
    }
}

