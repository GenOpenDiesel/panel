<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\ProtectedBackupService;

class BackupTransformer extends BaseClientTransformer
{
    public function __construct(private ProtectedBackupService $protectedBackupService)
    {
        parent::__construct();
    }

    public function getResourceName(): string
    {
        return Backup::RESOURCE_NAME;
    }

    public function transform(Backup $backup): array
    {
        return [
            'uuid' => $backup->uuid,
            'is_successful' => $backup->is_successful,
            'is_locked' => $backup->is_locked,
            'is_protected' => $this->protectedBackupService->isProtected($backup),
            'name' => $backup->name,
            'ignored_files' => $backup->ignored_files,
            'checksum' => $backup->checksum,
            'bytes' => $backup->bytes,
            'created_at' => $backup->created_at->toAtomString(),
            'completed_at' => $backup->completed_at ? $backup->completed_at->toAtomString() : null,
        ];
    }
}
