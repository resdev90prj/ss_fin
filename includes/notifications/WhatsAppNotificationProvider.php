<?php
require_once __DIR__ . '/NotificationProviderInterface.php';
require_once __DIR__ . '/NotificationMessage.php';

/**
 * Stub para futura integracao real de WhatsApp (Z-API, Evolution, Meta Cloud API).
 * Mantido desacoplado da regra de negocio para evolucao sem refatoracao invasiva.
 */
final class WhatsAppNotificationProvider implements NotificationProviderInterface
{
    private bool $enabled;
    private string $provider;

    public function __construct(array $config = [])
    {
        $this->enabled = !empty($config['enabled']);
        $this->provider = trim((string)($config['provider'] ?? 'not_configured'));
    }

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function providerName(): string
    {
        return $this->provider !== '' ? 'whatsapp_' . $this->provider : 'whatsapp_not_configured';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->provider !== '' && $this->provider !== 'not_configured';
    }

    public function send(NotificationMessage $message): array
    {
        return [
            'status' => 'skipped',
            'provider' => $this->providerName(),
            'error' => 'Provider de WhatsApp ainda nao implementado nesta versao.',
        ];
    }
}

