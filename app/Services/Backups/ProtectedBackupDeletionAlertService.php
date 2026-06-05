<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProtectedBackupDeletionAlertService
{
    /**
     * Sends a Discord webhook alert when a user attempts to delete a protected backup.
     */
    public function notify(Backup $backup, User $user): void
    {
        $webhookUrl = config('backups.protected_delete_webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        if (!$backup->relationLoaded('server')) {
            $backup->load('server');
        }

        $server = $backup->server;

        try {
            Http::timeout(5)->post($webhookUrl, [
                'embeds' => [
                    [
                        'title' => 'Próba usunięcia chronionego backupu',
                        'color' => 15158332,
                        'fields' => [
                            [
                                'name' => 'Użytkownik',
                                'value' => sprintf('%s (%s)', $user->email, $user->username),
                                'inline' => true,
                            ],
                            [
                                'name' => 'Root admin',
                                'value' => $user->root_admin ? 'Tak' : 'Nie',
                                'inline' => true,
                            ],
                            [
                                'name' => 'Serwer',
                                'value' => sprintf('%s (`%s`)', $server->name, $server->uuid),
                                'inline' => false,
                            ],
                            [
                                'name' => 'Backup',
                                'value' => sprintf('%s (`%s`)', $backup->name, $backup->uuid),
                                'inline' => false,
                            ],
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send protected backup deletion webhook.', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
