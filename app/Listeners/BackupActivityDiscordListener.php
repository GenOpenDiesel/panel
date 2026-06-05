<?php

namespace Pterodactyl\Listeners;

use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Events\ActivityLogged;
use Pterodactyl\Services\Backups\BackupActivityLogService;
use Pterodactyl\Services\Backups\BackupActivityAlertService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class BackupActivityDiscordListener implements ShouldDispatchAfterCommit
{
    public function __construct(private BackupActivityAlertService $backupActivityAlertService)
    {
    }

    public function handle(ActivityLogged $event): void
    {
        if (!in_array($event->model->event, BackupActivityLogService::EVENTS, true)) {
            return;
        }

        $activityLog = ActivityLog::query()
            ->with(['actor', 'subjects'])
            ->find($event->model->id);

        if (!$activityLog) {
            return;
        }

        $this->backupActivityAlertService->notify($activityLog);
    }
}
