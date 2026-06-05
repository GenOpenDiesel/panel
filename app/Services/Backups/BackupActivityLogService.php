<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BackupActivityLogService
{
    public const EVENTS = [
        'server:backup.download',
        'server:backup.start',
        'server:backup.complete',
    ];

    private const EVENT_LABELS = [
        'server:backup.download' => 'Pobranie',
        'server:backup.start' => 'Utworzenie',
        'server:backup.complete' => 'Wgranie',
    ];

    public function paginate(int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with(['actor', 'subjects'])
            ->whereIn('event', self::EVENTS)
            ->orderByDesc('timestamp')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function formatEntries(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (ActivityLog $log) => $this->formatEntry($log))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEntry(ActivityLog $log): array
    {
        $backup = $this->resolveBackup($log);
        $server = $this->resolveServer($log, $backup);

        return [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => self::EVENT_LABELS[$log->event] ?? $log->event,
            'timestamp' => $log->timestamp?->toIso8601String(),
            'ip' => $log->ip,
            'backup_name' => $log->properties?->get('name') ?? $backup?->name,
            'server_id' => $server?->id,
            'server_name' => $server?->name,
            'actor' => $this->formatActor($log),
        ];
    }

    private function resolveBackup(ActivityLog $log): ?Backup
    {
        $subject = $log->subjects->first(fn ($entry) => $entry->subject_type === (new Backup())->getMorphClass());

        if (!$subject) {
            return null;
        }

        return Backup::withTrashed()->with('server')->find($subject->subject_id);
    }

    private function resolveServer(ActivityLog $log, ?Backup $backup): ?Server
    {
        $subject = $log->subjects->first(fn ($entry) => $entry->subject_type === (new Server())->getMorphClass());

        if ($subject) {
            return Server::find($subject->subject_id);
        }

        return $backup?->server;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatActor(ActivityLog $log): array
    {
        $actor = $log->actor;

        if ($actor instanceof User) {
            return [
                'type' => 'user',
                'id' => $actor->id,
                'email' => $actor->email,
                'username' => $actor->username,
                'name' => trim($actor->name_first . ' ' . $actor->name_last),
            ];
        }

        return [
            'type' => 'system',
            'id' => null,
            'email' => null,
            'username' => null,
            'name' => 'System',
        ];
    }
}
