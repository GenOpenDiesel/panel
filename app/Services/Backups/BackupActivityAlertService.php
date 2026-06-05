<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\ActivityLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackupActivityAlertService
{
    private const EVENT_TITLES = [
        'server:backup.download' => 'Pobranie backupu',
        'server:backup.start' => 'Utworzenie backupu',
        'server:backup.complete' => 'Wgranie backupu',
    ];

    private const EVENT_COLORS = [
        'server:backup.download' => 3447003,
        'server:backup.start' => 16776960,
        'server:backup.complete' => 3066993,
    ];

    public function __construct(private BackupActivityLogService $backupActivityLogService)
    {
    }

    public function notify(ActivityLog $activityLog): void
    {
        $webhookUrl = config('backups.activity_webhook_url');

        if (empty($webhookUrl) || !in_array($activityLog->event, BackupActivityLogService::EVENTS, true)) {
            return;
        }

        $entry = $this->backupActivityLogService->formatEntry($activityLog);

        try {
            Http::timeout(5)->post($webhookUrl, [
                'embeds' => [
                    [
                        'title' => self::EVENT_TITLES[$activityLog->event] ?? $entry['event_label'],
                        'color' => self::EVENT_COLORS[$activityLog->event] ?? 9807270,
                        'fields' => $this->buildFields($entry),
                        'timestamp' => $entry['timestamp'] ?? now()->toIso8601String(),
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send backup activity webhook.', [
                'activity_log_id' => $activityLog->id,
                'event' => $activityLog->event,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, array<string, mixed>>
     */
    private function buildFields(array $entry): array
    {
        $actor = $entry['actor'] ?? [];
        $actorValue = ($actor['email'] ?? null)
            ? sprintf('%s (%s)', $actor['email'], $actor['username'] ?? '-')
            : ($actor['name'] ?? 'System');

        $fields = [
            [
                'name' => 'Akcja',
                'value' => $entry['event_label'] ?? '-',
                'inline' => true,
            ],
            [
                'name' => 'Użytkownik',
                'value' => $actorValue,
                'inline' => true,
            ],
            [
                'name' => 'IP',
                'value' => $entry['ip'] ?? '-',
                'inline' => true,
            ],
            [
                'name' => 'Serwer',
                'value' => $entry['server_name'] ?? '-',
                'inline' => false,
            ],
            [
                'name' => 'Backup',
                'value' => $entry['backup_name'] ?? '-',
                'inline' => false,
            ],
        ];

        if (!empty($entry['server_id'])) {
            $fields[] = [
                'name' => 'Panel',
                'value' => sprintf('[Otwórz serwer](%s)', url('/admin/servers/view/' . $entry['server_id'])),
                'inline' => false,
            ];
        }

        return $fields;
    }
}
