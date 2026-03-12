<?php
require_once __DIR__ . '/NotificationProviderInterface.php';
require_once __DIR__ . '/NotificationMessage.php';

final class EmailNotificationProvider implements NotificationProviderInterface
{
    private bool $enabled;
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $config = [])
    {
        $this->enabled = !empty($config['enabled']);
        $this->fromEmail = trim((string)($config['from_email'] ?? ''));
        $this->fromName = trim((string)($config['from_name'] ?? 'SaaS IA Finan'));
    }

    public function channel(): string
    {
        return 'email';
    }

    public function providerName(): string
    {
        return 'php_mail';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && function_exists('mail');
    }

    public function send(NotificationMessage $message): array
    {
        if (!$this->isEnabled()) {
            return [
                'status' => 'skipped',
                'provider' => $this->providerName(),
                'error' => 'Provider de e-mail desabilitado ou funcao mail() indisponivel.',
            ];
        }

        if (!filter_var($message->to, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'failed',
                'provider' => $this->providerName(),
                'error' => 'E-mail de destino invalido.',
            ];
        }

        $fromEmail = filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL) ? $this->fromEmail : 'no-reply@localhost';
        $fromName = $this->sanitizeHeader($this->fromName);
        $subject = $this->sanitizeHeader($message->subject);

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail);

        $ok = @mail($message->to, $subject, $message->bodyText, implode("\r\n", $headers));
        if (!$ok) {
            return [
                'status' => 'failed',
                'provider' => $this->providerName(),
                'error' => 'Falha ao enviar e-mail via mail().',
            ];
        }

        return [
            'status' => 'sent',
            'provider' => $this->providerName(),
            'error' => '',
        ];
    }

    private function sanitizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\r", "\n"], ' ', $value);
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}

