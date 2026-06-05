<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\ServiceProvider;
use Pterodactyl\Extensions\Backups\BackupManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Pterodactyl\Services\Backups\ProtectedBackupService;

class BackupsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the S3 backup disk.
     */
    public function register(): void
    {
        $this->app->singleton(BackupManager::class, function ($app) {
            return new BackupManager($app);
        });

        $this->app->singleton(ProtectedBackupService::class);
    }

    public function provides(): array
    {
        return [BackupManager::class, ProtectedBackupService::class];
    }
}
