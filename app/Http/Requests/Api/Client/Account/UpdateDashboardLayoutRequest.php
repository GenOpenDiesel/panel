<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateDashboardLayoutRequest extends ClientApiRequest
{
    public function authorize(): bool
    {
        return parent::authorize();
    }

    public function rules(): array
    {
        return [
            'layout' => 'required|array',
            'layout.sortMode' => 'required|string|in:custom,name_asc,name_desc',
            'layout.sections' => 'present|array',
            'layout.sections.*.id' => 'required|string|max:36',
            'layout.sections.*.name' => 'required|string|max:191',
            'layout.sections.*.serverUuids' => 'present|array',
            'layout.sections.*.serverUuids.*' => 'string|uuid',
            'layout.unsectionedOrder' => 'present|array',
            'layout.unsectionedOrder.*' => 'string|uuid',
        ];
    }
}
