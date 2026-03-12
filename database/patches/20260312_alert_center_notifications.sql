-- Central de Alertas: preferencias por usuario e logs de disparo

CREATE TABLE IF NOT EXISTS user_alert_preferences (
    user_id INT NOT NULL,
    receber_alerta_email TINYINT(1) NOT NULL DEFAULT 1,
    email_notificacao VARCHAR(191) NULL,
    alerta_frequencia VARCHAR(20) NOT NULL DEFAULT 'daily',
    alerta_horario CHAR(5) NOT NULL DEFAULT '08:00',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_dispatch_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    channel VARCHAR(30) NOT NULL,
    provider VARCHAR(60) NOT NULL,
    alert_code VARCHAR(80) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    message_preview VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL,
    error_message VARCHAR(255) NOT NULL DEFAULT '',
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_dispatch_logs_user (user_id),
    KEY idx_notification_dispatch_logs_channel_status (channel, status),
    KEY idx_notification_dispatch_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

