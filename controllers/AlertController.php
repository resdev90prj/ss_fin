<?php
require_once __DIR__ . '/../includes/AlertCenterService.php';

class AlertController
{
    public function dispatch(): void
    {
        require_admin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('index.php?route=dashboard');
        }

        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            flash('error', 'Token CSRF invalido.');
            redirect('index.php?route=dashboard');
        }

        $onlyUserId = null;
        if (isset($_POST['user_id']) && ctype_digit((string)$_POST['user_id'])) {
            $candidate = (int)$_POST['user_id'];
            $onlyUserId = $candidate > 0 ? $candidate : null;
        }

        try {
            $summary = (new AlertCenterService())->dispatchAll($onlyUserId, 'web_manual');
            $message = sprintf(
                'Central de Alertas executada: usuarios processados=%d, usuarios com alertas=%d, envios ok=%d, falhas=%d, ignorados=%d.',
                (int)$summary['users_processed'],
                (int)$summary['users_with_alerts'],
                (int)$summary['dispatch_sent'],
                (int)$summary['dispatch_failed'],
                (int)$summary['dispatch_skipped']
            );

            if ((int)$summary['dispatch_failed'] > 0) {
                flash('error', $message);
            } else {
                flash('success', $message);
            }
        } catch (Throwable $e) {
            flash('error', 'Erro ao executar Central de Alertas: ' . $e->getMessage());
        }

        redirect('index.php?route=dashboard');
    }
}

