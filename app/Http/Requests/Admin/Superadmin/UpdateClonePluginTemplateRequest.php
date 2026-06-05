<?php

namespace Pterodactyl\Http\Requests\Admin\Superadmin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class UpdateClonePluginTemplateRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'plugin_template' => 'required|string|max:2000',
        ];
    }
}
