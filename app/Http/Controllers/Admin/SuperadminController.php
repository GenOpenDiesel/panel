<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Backups\BackupActivityLogService;
use Pterodactyl\Services\Logs\LatestLogAnalysisService;
use Pterodactyl\Http\Requests\Admin\Superadmin\AnalyzeLatestLogsRequest;

class SuperadminController extends Controller
{
    public function __construct(
        private BackupActivityLogService $backupActivityLogService,
        private LatestLogAnalysisService $latestLogAnalysisService,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'backups');

        if (!in_array($tab, ['backups', 'logs'], true)) {
            $tab = 'backups';
        }

        return view('admin.superadmin.index', [
            'activeTab' => $tab,
        ]);
    }

    public function analyzeLogs(AnalyzeLatestLogsRequest $request): JsonResponse
    {
        set_time_limit(300);

        $phrases = $request->input('phrases', '');
        $parsed = $this->latestLogAnalysisService->parsePhrases($phrases);

        if (empty($parsed)) {
            return new JsonResponse([
                'error' => 'Podaj co najmniej jedną frazę do wyszukania.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(
            $this->latestLogAnalysisService->analyze($phrases)
        );
    }

    public function backupLogs(Request $request): JsonResponse
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
