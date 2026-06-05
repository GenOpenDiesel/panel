<?php

namespace Pterodactyl\Http\Requests\Admin\Superadmin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AnalyzeLatestLogsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'phrases' => 'required|string|max:5000',
        ];
    }
}
