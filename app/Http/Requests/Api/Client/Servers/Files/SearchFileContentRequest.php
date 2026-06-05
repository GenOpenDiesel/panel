<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SearchFileContentRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function authorize(): bool
    {
        if (!$this->user()->root_admin) {
            return false;
        }

        return parent::authorize();
    }

    public function permission(): string
    {
        return Permission::ACTION_FILE_READ_CONTENT;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:1|max:500',
            'directory' => 'nullable|string|max:500',
            'search_id' => 'nullable|uuid',
        ];
    }
}
