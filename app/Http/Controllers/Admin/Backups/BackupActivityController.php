<?php

namespace Pterodactyl\Http\Controllers\Admin\Backups;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Backups\BackupActivityLogService;

class BackupActivityController extends Controller
{
    public function __construct(private BackupActivityLogService $backupActivityLogService)
    {
    }

    public function index(): View
    {
        return view('admin.backups.activity');
    }

    public function logs(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = min(max($request->integer('per_page', 50), 10), 100);

        $paginator = $this->backupActivityLogService->paginate($page, $perPage);

        return new JsonResponse([
            'data' => $this->backupActivityLogService->formatEntries($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
