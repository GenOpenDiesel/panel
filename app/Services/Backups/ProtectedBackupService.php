<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;

class ProtectedBackupService
{
    /** @var array<int, int[]> */
    private array $protectedIdsCache = [];

    /**
     * Returns the IDs of the oldest downloadable backups that are protected from deletion.
     *
     * @return int[]
     */
    public function getProtectedIds(Server $server): array
    {
        if (!array_key_exists($server->id, $this->protectedIdsCache)) {
            $count = max(0, (int) config('backups.protected_downloadable_count', 2));

            if ($count === 0) {
                $this->protectedIdsCache[$server->id] = [];

                return $this->protectedIdsCache[$server->id];
            }

            $this->protectedIdsCache[$server->id] = $server->backups()
                ->where('is_successful', true)
                ->whereNotNull('completed_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($count)
                ->pluck('id')
                ->all();
        }

        return $this->protectedIdsCache[$server->id];
    }

    public function isProtected(Backup $backup): bool
    {
        if (!$backup->is_successful || is_null($backup->completed_at)) {
            return false;
        }

        if (!$backup->relationLoaded('server')) {
            $backup->load('server');
        }

        return in_array($backup->id, $this->getProtectedIds($backup->server), true);
    }

    public function canDelete(Backup $backup, ?User $user = null): bool
    {
        if (!$this->isProtected($backup)) {
            return true;
        }

        // Automatic purges (no user context) may delete protected backups; protection
        // then shifts to the next oldest downloadable backups dynamically.
        if ($user === null) {
            return true;
        }

        return $user->root_admin;
    }
}
