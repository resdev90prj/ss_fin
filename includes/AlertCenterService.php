<?php
require_once __DIR__ . '/../models/Target.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/NotificationDispatchLog.php';
require_once __DIR__ . '/notifications/NotificationMessage.php';
require_once __DIR__ . '/notifications/NotificationProviderInterface.php';
require_once __DIR__ . '/notifications/EmailNotificationProvider.php';
require_once __DIR__ . '/notifications/WhatsAppNotificationProvider.php';

class AlertCenterService
{
    private User $userModel;
    private Target $targetModel;
    private NotificationDispatchLog $logModel;
    private array $config;
    private array $rulesConfig;
    private array $emailConfig;
    private array $dispatchConfig;
    private string $baseUrl;
    private string $fallbackLogFile;

    /** @var array<string, NotificationProviderInterface> */
    private array $providers;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (require __DIR__ . '/config.php');
        $notificationsConfig = is_array($this->config['notifications'] ?? null) ? $this->config['notifications'] : [];

        $this->emailConfig = is_array($notificationsConfig['email'] ?? null) ? $notificationsConfig['email'] : [];
        $this->rulesConfig = is_array($notificationsConfig['rules'] ?? null) ? $notificationsConfig['rules'] : [];
        $this->dispatchConfig = is_array($notificationsConfig['dispatch'] ?? null) ? $notificationsConfig['dispatch'] : [];

        $this->baseUrl = rtrim((string)($this->config['base_url'] ?? ''), '/');
        $this->fallbackLogFile = (string)($this->dispatchConfig['log_file'] ?? (dirname(__DIR__) . '/logs/alerts_dispatch.log'));

        $this->userModel = new User();
        $this->targetModel = new Target();
        $this->logModel = new NotificationDispatchLog();

        $this->providers = [
            'email' => new EmailNotificationProvider($this->emailConfig),
            'whatsapp' => new WhatsAppNotificationProvider(
                is_array($notificationsConfig['whatsapp'] ?? null) ? $notificationsConfig['whatsapp'] : []
            ),
        ];
    }

    public function dispatchAll(?int $onlyUserId = null, string $trigger = 'manual'): array
    {
        $startedAt = date('c');
        $users = $this->resolveUsers($onlyUserId);

        $summary = [
            'trigger' => $trigger,
            'started_at' => $startedAt,
            'finished_at' => null,
            'users_scanned' => count($users),
            'users_processed' => 0,
            'users_with_alerts' => 0,
            'dispatch_sent' => 0,
            'dispatch_failed' => 0,
            'dispatch_skipped' => 0,
            'results' => [],
        ];

        foreach ($users as $user) {
            $result = $this->dispatchForUser($user, $trigger);
            $summary['users_processed']++;
            if ((int)($result['alert_count'] ?? 0) > 0) {
                $summary['users_with_alerts']++;
            }

            foreach ($result['dispatches'] as $dispatch) {
                $status = (string)($dispatch['status'] ?? 'skipped');
                if ($status === 'sent') {
                    $summary['dispatch_sent']++;
                } elseif ($status === 'failed') {
                    $summary['dispatch_failed']++;
                } else {
                    $summary['dispatch_skipped']++;
                }
            }

            $summary['results'][] = $result;
        }

        $summary['finished_at'] = date('c');
        return $summary;
    }

    private function resolveUsers(?int $onlyUserId): array
    {
        if ($onlyUserId !== null && $onlyUserId > 0) {
            $user = $this->userModel->findById($onlyUserId);
            if (!$user || (int)($user['status'] ?? 0) !== 1) {
                return [];
            }

            $preferences = $this->userModel->alertPreferencesByUserId($onlyUserId);
            return [[
                'id' => (int)$user['id'],
                'name' => (string)$user['name'],
                'email' => (string)$user['email'],
                'status' => (int)$user['status'],
                'receber_alerta_email' => (int)($preferences['receber_alerta_email'] ?? 1),
                'email_notificacao' => (string)($preferences['email_notificacao'] ?? ''),
                'alerta_frequencia' => (string)($preferences['alerta_frequencia'] ?? 'daily'),
                'alerta_horario' => (string)($preferences['alerta_horario'] ?? '08:00'),
            ]];
        }

        $limit = (int)($this->dispatchConfig['max_users_per_run'] ?? 100);
        $limit = max(1, min($limit, 500));
        return $this->userModel->activeUsersForAlerts($limit);
    }

    private function dispatchForUser(array $user, string $trigger): array
    {
        $userId = (int)($user['id'] ?? 0);
        $userName = trim((string)($user['name'] ?? ''));
        $notificationEmail = trim((string)($user['email_notificacao'] ?? ''));
        if ($notificationEmail === '') {
            $notificationEmail = trim((string)($user['email'] ?? ''));
        }

        $preferences = [
            'receber_alerta_email' => (int)($user['receber_alerta_email'] ?? 1) === 1,
            'email_notificacao' => $notificationEmail,
            'alerta_frequencia' => $this->normalizeFrequency((string)($user['alerta_frequencia'] ?? 'daily')),
            'alerta_horario' => $this->normalizeHour((string)($user['alerta_horario'] ?? '08:00')),
        ];

        $snapshot = $this->collectUserAlertSnapshot($userId);
        $subjectPrefix = trim((string)($this->emailConfig['subject_prefix'] ?? '[SaaS IA Finan]'));
        $subject = trim($subjectPrefix . ' Central de Alertas: ' . (int)$snapshot['alert_count'] . ' alerta(s) ativos');
        $bodyText = $this->buildEmailBody($userName, $snapshot);

        $dispatches = [];
        $emailProvider = $this->providers['email'];

        if (!(bool)$preferences['receber_alerta_email']) {
            $dispatches[] = $this->registerDispatch([
                'user_id' => $userId,
                'channel' => 'email',
                'provider' => $emailProvider->providerName(),
                'alert_code' => 'alerts_digest',
                'subject' => $subject,
                'message_preview' => $this->previewMessage($bodyText),
                'status' => 'skipped',
                'error_message' => 'Recebimento de alertas por e-mail desativado pelo usuario.',
                'payload_json' => [
                    'trigger' => $trigger,
                    'alert_count' => (int)$snapshot['alert_count'],
                ],
            ]);
        } elseif (!filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            $dispatches[] = $this->registerDispatch([
                'user_id' => $userId,
                'channel' => 'email',
                'provider' => $emailProvider->providerName(),
                'alert_code' => 'alerts_digest',
                'subject' => $subject,
                'message_preview' => $this->previewMessage($bodyText),
                'status' => 'failed',
                'error_message' => 'E-mail de notificacao invalido.',
                'payload_json' => [
                    'trigger' => $trigger,
                    'alert_count' => (int)$snapshot['alert_count'],
                ],
            ]);
        } elseif ((int)$snapshot['alert_count'] <= 0) {
            $dispatches[] = $this->registerDispatch([
                'user_id' => $userId,
                'channel' => 'email',
                'provider' => $emailProvider->providerName(),
                'alert_code' => 'alerts_digest',
                'subject' => $subject,
                'message_preview' => $this->previewMessage($bodyText),
                'status' => 'skipped',
                'error_message' => 'Nenhum alerta ativo para envio.',
                'payload_json' => [
                    'trigger' => $trigger,
                    'alert_count' => 0,
                ],
            ]);
        } else {
            $schedule = $this->canDispatchNow(
                $userId,
                'email',
                $preferences['alerta_frequencia'],
                $preferences['alerta_horario'],
                $trigger
            );
            if (!$schedule['allowed']) {
                $dispatches[] = $this->registerDispatch([
                    'user_id' => $userId,
                    'channel' => 'email',
                    'provider' => $emailProvider->providerName(),
                    'alert_code' => 'alerts_digest',
                    'subject' => $subject,
                    'message_preview' => $this->previewMessage($bodyText),
                    'status' => 'skipped',
                    'error_message' => (string)$schedule['reason'],
                    'payload_json' => [
                        'trigger' => $trigger,
                        'alert_count' => (int)$snapshot['alert_count'],
                    ],
                ]);
            } else {
                $sendResult = $emailProvider->send(new NotificationMessage(
                    $userId,
                    $notificationEmail,
                    $subject,
                    $bodyText,
                    [
                        'trigger' => $trigger,
                        'alert_count' => (int)$snapshot['alert_count'],
                    ]
                ));

                $dispatches[] = $this->registerDispatch([
                    'user_id' => $userId,
                    'channel' => 'email',
                    'provider' => (string)($sendResult['provider'] ?? $emailProvider->providerName()),
                    'alert_code' => 'alerts_digest',
                    'subject' => $subject,
                    'message_preview' => $this->previewMessage($bodyText),
                    'status' => (string)($sendResult['status'] ?? 'failed'),
                    'error_message' => (string)($sendResult['error'] ?? ''),
                    'payload_json' => [
                        'trigger' => $trigger,
                        'alert_count' => (int)$snapshot['alert_count'],
                    ],
                ]);
            }
        }

        $whatsProvider = $this->providers['whatsapp'];
        if ($whatsProvider->isEnabled()) {
            $dispatches[] = $this->registerDispatch([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => $whatsProvider->providerName(),
                'alert_code' => 'alerts_digest',
                'subject' => $subject,
                'message_preview' => '',
                'status' => 'skipped',
                'error_message' => 'Canal pronto para evolucao, sem provider de envio implementado.',
                'payload_json' => [
                    'trigger' => $trigger,
                    'alert_count' => (int)$snapshot['alert_count'],
                ],
            ]);
        }

        return [
            'user_id' => $userId,
            'user_name' => $userName,
            'alert_count' => (int)$snapshot['alert_count'],
            'summary' => $snapshot['summary'],
            'dispatches' => $dispatches,
        ];
    }

    private function collectUserAlertSnapshot(int $userId): array
    {
        $agenda = $this->targetModel->executionAgendaData($userId, 180);
        $weekly = $this->targetModel->executionWeeklyScoreData($userId, 2);
        $objectiveHealth = $this->targetModel->activeObjectiveProgressSnapshot($userId, 7);

        $summary = $agenda['summary'] ?? [];
        $overdueCount = (int)($summary['overdue_count'] ?? 0);
        $dueTodayCount = (int)($summary['due_today_count'] ?? 0);
        $due3DaysCount = (int)($summary['due_3_days_count'] ?? 0);
        $pendingCount = (int)($summary['pending_count'] ?? 0);

        $manyPendingThreshold = max(3, (int)($this->rulesConfig['many_pending_threshold'] ?? 12));
        $lowExecutionThreshold = max(0, min(100, (int)($this->rulesConfig['low_execution_score_threshold'] ?? 50)));
        $currentWeek = $weekly['current_week'] ?? [];
        $weeklyScore = (int)($currentWeek['score'] ?? 0);
        $plannedInWeek = (int)($currentWeek['planned_count'] ?? 0);

        $alerts = [];
        if ($overdueCount > 0) {
            $alerts[] = [
                'code' => 'action_overdue',
                'level' => 'critical',
                'title' => 'Acoes atrasadas',
                'message' => 'Existem ' . $overdueCount . ' acao(oes) atrasada(s).',
            ];
        }
        if ($dueTodayCount > 0) {
            $alerts[] = [
                'code' => 'action_due_today',
                'level' => 'high',
                'title' => 'Acoes vencem hoje',
                'message' => 'Existem ' . $dueTodayCount . ' acao(oes) vencendo hoje.',
            ];
        }
        if ($due3DaysCount > 0) {
            $alerts[] = [
                'code' => 'action_due_3_days',
                'level' => 'medium',
                'title' => 'Acoes vencem em ate 3 dias',
                'message' => 'Existem ' . $due3DaysCount . ' acao(oes) com vencimento em ate 3 dias.',
            ];
        }
        if ($pendingCount >= $manyPendingThreshold) {
            $alerts[] = [
                'code' => 'many_pending_actions',
                'level' => 'medium',
                'title' => 'Muitas acoes pendentes',
                'message' => 'Total de pendencias abertas: ' . $pendingCount . '.',
            ];
        }
        if (!empty($objectiveHealth['is_stalled'])) {
            $alerts[] = [
                'code' => 'active_objective_stalled',
                'level' => 'high',
                'title' => 'Objetivo ativo sem avancos recentes',
                'message' => 'Sem conclusoes recentes no objetivo ativo "' . (string)($objectiveHealth['objective_title'] ?? '') . '".',
            ];
        }
        if ($weeklyScore <= $lowExecutionThreshold && ($plannedInWeek > 0 || $overdueCount > 0)) {
            $alerts[] = [
                'code' => 'low_weekly_execution',
                'level' => 'high',
                'title' => 'Baixa execucao semanal',
                'message' => 'Score semanal atual em ' . $weeklyScore . ' pontos.',
            ];
        }

        $priorityItems = [];
        foreach (($agenda['items'] ?? []) as $item) {
            $priorityRank = (int)($item['priority_rank'] ?? 6);
            $isStrategic = !empty($item['is_active_objective']) || !empty($item['is_active_target']);
            if ($priorityRank <= 3 || $isStrategic) {
                $priorityItems[] = $item;
            }
            if (count($priorityItems) >= 8) {
                break;
            }
        }

        return [
            'active_target' => $agenda['active_target'] ?? null,
            'active_objective' => $agenda['active_objective'] ?? null,
            'summary' => [
                'overdue_count' => $overdueCount,
                'due_today_count' => $dueTodayCount,
                'due_3_days_count' => $due3DaysCount,
                'pending_count' => $pendingCount,
                'weekly_score' => $weeklyScore,
            ],
            'alerts' => $alerts,
            'priority_items' => $priorityItems,
            'alert_count' => count($alerts),
        ];
    }

    private function buildEmailBody(string $userName, array $snapshot): string
    {
        $summary = $snapshot['summary'] ?? [];
        $alerts = $snapshot['alerts'] ?? [];
        $priorityItems = $snapshot['priority_items'] ?? [];
        $activeTarget = $snapshot['active_target'] ?? null;
        $activeObjective = $snapshot['active_objective'] ?? null;

        $lines = [];
        $lines[] = 'Central de Alertas - SaaS IA Finan';
        $lines[] = 'Gerado em: ' . date('d/m/Y H:i');
        if ($userName !== '') {
            $lines[] = 'Usuario: ' . $userName;
        }
        $lines[] = '';
        if (is_array($activeTarget) && !empty($activeTarget['title'])) {
            $lines[] = 'Alvo ativo: ' . (string)$activeTarget['title'];
        }
        if (is_array($activeObjective) && !empty($activeObjective['title'])) {
            $lines[] = 'Objetivo ativo: ' . (string)$activeObjective['title'];
        }
        $lines[] = '';
        $lines[] = 'Resumo de execucao:';
        $lines[] = '- Atrasadas: ' . (int)($summary['overdue_count'] ?? 0);
        $lines[] = '- Vencem hoje: ' . (int)($summary['due_today_count'] ?? 0);
        $lines[] = '- Vencem em ate 3 dias: ' . (int)($summary['due_3_days_count'] ?? 0);
        $lines[] = '- Pendentes abertas: ' . (int)($summary['pending_count'] ?? 0);
        $lines[] = '- Score semanal: ' . (int)($summary['weekly_score'] ?? 0);
        $lines[] = '';

        if (empty($alerts)) {
            $lines[] = 'Nenhum alerta ativo no momento.';
        } else {
            $lines[] = 'Alertas ativos:';
            $index = 1;
            foreach ($alerts as $alert) {
                $level = strtoupper((string)($alert['level'] ?? 'info'));
                $title = (string)($alert['title'] ?? 'Alerta');
                $message = (string)($alert['message'] ?? '');
                $lines[] = $index . '. [' . $level . '] ' . $title . ' - ' . $message;
                $index++;
            }
        }

        $lines[] = '';
        if (!empty($priorityItems)) {
            $lines[] = 'Acoes prioritarias:';
            foreach ($priorityItems as $item) {
                $lines[] = '- ' . (string)($item['title'] ?? 'Acao')
                    . ' | Objetivo: ' . (string)($item['objective_title'] ?? '-')
                    . ' | Decisao: ' . (string)($item['decision_title'] ?? '-')
                    . ' | Prazo: ' . $this->formatDate((string)($item['planned_date'] ?? ''))
                    . ' | Urgencia: ' . (string)($item['urgency_level'] ?? '-');
            }
            $lines[] = '';
        }

        $lines[] = 'Acesso rapido:';
        $lines[] = '- Dashboard: ' . $this->routeUrl('dashboard');
        $lines[] = '- Alvos e Execucao: ' . $this->routeUrl('targets');
        $lines[] = '';
        $lines[] = 'Mensagem automatica da Central de Alertas.';

        return implode(PHP_EOL, $lines);
    }

    private function canDispatchNow(int $userId, string $channel, string $frequency, string $preferredHour, string $trigger): array
    {
        $frequency = $this->normalizeFrequency($frequency);
        $preferredHour = $this->normalizeHour($preferredHour);
        $now = new DateTimeImmutable('now');

        if (in_array($trigger, ['manual', 'web_manual'], true)) {
            return ['allowed' => true, 'reason' => 'Envio manual autorizado.'];
        }

        if ($frequency === 'manual') {
            return ['allowed' => false, 'reason' => 'Envio definido como manual.'];
        }

        if ($frequency === 'weekdays' && (int)$now->format('N') >= 6) {
            return ['allowed' => false, 'reason' => 'Envio permitido apenas em dias uteis.'];
        }

        $nowTime = $now->format('H:i');
        if ($nowTime < $preferredHour) {
            return ['allowed' => false, 'reason' => 'Horario preferido ainda nao atingido (' . $preferredHour . ').'];
        }

        $lastSentAt = $this->logModel->lastSentAt($userId, $channel);
        if (is_string($lastSentAt) && $lastSentAt !== '') {
            $lastTs = strtotime($lastSentAt);
            if ($lastTs !== false && date('Y-m-d', $lastTs) === $now->format('Y-m-d')) {
                return ['allowed' => false, 'reason' => 'Alerta ja enviado hoje para este canal.'];
            }
        }

        return ['allowed' => true, 'reason' => 'OK'];
    }

    private function registerDispatch(array $entry): array
    {
        $status = (string)($entry['status'] ?? 'skipped');
        if (!in_array($status, ['sent', 'failed', 'skipped'], true)) {
            $entry['status'] = 'failed';
        }

        $saved = false;
        try {
            $saved = $this->logModel->create($entry);
        } catch (Throwable $e) {
            $saved = false;
            $entry['error_message'] = trim((string)($entry['error_message'] ?? '') . ' | log_db_error=' . $e->getMessage());
        }

        if (!$saved) {
            $this->writeFallbackLog($entry);
        }

        return [
            'channel' => (string)($entry['channel'] ?? ''),
            'provider' => (string)($entry['provider'] ?? ''),
            'status' => (string)($entry['status'] ?? 'skipped'),
            'error_message' => (string)($entry['error_message'] ?? ''),
        ];
    }

    private function writeFallbackLog(array $entry): void
    {
        $file = $this->fallbackLogFile;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'at' => date('c'),
            'user_id' => (int)($entry['user_id'] ?? 0),
            'channel' => (string)($entry['channel'] ?? ''),
            'provider' => (string)($entry['provider'] ?? ''),
            'status' => (string)($entry['status'] ?? ''),
            'alert_code' => (string)($entry['alert_code'] ?? ''),
            'error_message' => (string)($entry['error_message'] ?? ''),
        ];
        @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function normalizeFrequency(string $frequency): string
    {
        $frequency = strtolower(trim($frequency));
        if (!in_array($frequency, ['daily', 'weekdays', 'manual'], true)) {
            return (string)($this->dispatchConfig['default_frequency'] ?? 'daily');
        }
        return $frequency;
    }

    private function normalizeHour(string $hour): string
    {
        $hour = trim($hour);
        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $hour)) {
            $defaultHour = trim((string)($this->dispatchConfig['default_hour'] ?? '08:00'));
            if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $defaultHour)) {
                return $defaultHour;
            }
            return '08:00';
        }
        return $hour;
    }

    private function routeUrl(string $route): string
    {
        if ($this->baseUrl === '') {
            return 'index.php?route=' . $route;
        }
        return $this->baseUrl . '/index.php?route=' . $route;
    }

    private function formatDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '-';
        }
        $ts = strtotime($date);
        return $ts !== false ? date('d/m/Y', $ts) : $date;
    }

    private function previewMessage(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if (strlen($body) <= 180) {
            return $body;
        }
        return substr($body, 0, 177) . '...';
    }
}
