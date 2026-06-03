<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Backups;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CreateServerFromBackupRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_BACKUP_RESTORE;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|nullable|string|max:191',
            'node_id' => 'sometimes|nullable|integer|exists:nodes,id',
        ];
    }
}
