<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdateDashboardLayoutRequest;

class DashboardLayoutController extends ClientApiController
{
    /**
     * Return the authenticated user's dashboard layout preferences.
     */
    public function index(Request $request): JsonResponse
    {
        return new JsonResponse([
            'object' => 'dashboard_layout',
            'attributes' => $request->user()->dashboard_layout ?? $this->defaultLayout(),
        ]);
    }

    /**
     * Persist the authenticated user's dashboard layout preferences.
     */
    public function update(UpdateDashboardLayoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['dashboard_layout' => $request->input('layout')])->save();

        return new JsonResponse([
            'object' => 'dashboard_layout',
            'attributes' => $user->dashboard_layout,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLayout(): array
    {
        return [
            'sortMode' => 'name_asc',
            'sections' => [],
            'unsectionedOrder' => [],
        ];
    }
}
