<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeUsageService;

class NodeUsageController extends Controller
{
    public function __construct(private NodeUsageService $nodeUsageService)
    {
    }

    public function index(): View
    {
        return view('admin.nodes.usage');
    }

    public function stats(): JsonResponse
    {
        return new JsonResponse($this->nodeUsageService->getAll());
    }
}
