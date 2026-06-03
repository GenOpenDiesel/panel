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
            'attributes' => $this->normalizeLayout($request->user()->dashboard_layout),
        ]);
    }

    /**
     * Persist the authenticated user's dashboard layout preferences.
     */
    public function update(UpdateDashboardLayoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $layout = $this->normalizeLayout($request->input('layout'));

        $user->forceFill(['dashboard_layout' => $layout])->save();

        return new JsonResponse([
            'object' => 'dashboard_layout',
            'attributes' => $layout,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $layout
     * @return array<string, mixed>
     */
    private function normalizeLayout(?array $layout): array
    {
        if (empty($layout)) {
            return $this->defaultScopedLayout();
        }

        if (array_key_exists('own', $layout) || array_key_exists('admin', $layout)) {
            return [
                'own' => $this->normalizeSingleLayout(is_array($layout['own'] ?? null) ? $layout['own'] : []),
                'admin' => $this->normalizeSingleLayout(is_array($layout['admin'] ?? null) ? $layout['admin'] : []),
            ];
        }

        return [
            'own' => $this->normalizeSingleLayout($layout),
            'admin' => $this->defaultSingleLayout(),
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function normalizeSingleLayout(array $layout): array
    {
        return [
            'sortMode' => in_array($layout['sortMode'] ?? null, ['custom', 'name_asc', 'name_desc'], true)
                ? $layout['sortMode']
                : 'name_asc',
            'sections' => array_values($layout['sections'] ?? []),
            'unsectionedOrder' => array_values($layout['unsectionedOrder'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultScopedLayout(): array
    {
        return [
            'own' => $this->defaultSingleLayout(),
            'admin' => $this->defaultSingleLayout(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSingleLayout(): array
    {
        return [
            'sortMode' => 'name_asc',
            'sections' => [],
            'unsectionedOrder' => [],
        ];
    }
}
