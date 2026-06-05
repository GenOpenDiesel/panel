<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Backups;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class BackupCloneCleanupRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_DELETE;
    }

    public function rules(): array
    {
        return [
            'plugins' => 'present|array|max:500',
            'plugins.*' => [
                'string',
                'max:255',
                Rule::notRegex('/[\/\\\\]/'),
                Rule::notRegex('/\.\./'),
            ],
            'patterns' => 'sometimes|array|max:100',
            'patterns.*' => [
                'string',
                'max:255',
                Rule::notRegex('/[\/\\\\]/'),
                Rule::notRegex('/\.\./'),
            ],
        ];
    }
}
