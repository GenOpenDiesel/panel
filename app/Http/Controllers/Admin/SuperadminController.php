<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Backups\BackupActivityLogService;
use Pterodactyl\Services\Backups\BackupClonePluginTemplateService;
use Pterodactyl\Services\Logs\LatestLogAnalysisService;
use Pterodactyl\Http\Requests\Admin\Superadmin\UpdateClonePluginTemplateRequest;
use Pterodactyl\Http\Requests\Admin\Superadmin\AnalyzeLatestLogsRequest;

class SuperadminController extends Controller
{
    public function __construct(
        private BackupActivityLogService $backupActivityLogService,
        private BackupClonePluginTemplateService $pluginTemplateService,
        private LatestLogAnalysisService $latestLogAnalysisService,
        private AlertsMessageBag $alert,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'backups');

        if (!in_array($tab, ['backups', 'clone', 'logs'], true)) {
            $tab = 'backups';
        }

        return view('admin.superadmin.index', [
            'activeTab' => $tab,
            'pluginTemplate' => $this->pluginTemplateService->get(),
        ]);
    }

    public function updateCloneTemplate(UpdateClonePluginTemplateRequest $request): RedirectResponse
    {
        try {
            $this->pluginTemplateService->validate($request->input('plugin_template'));
            $this->pluginTemplateService->set($request->input('plugin_template'));
        } catch (\InvalidArgumentException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.superadmin', ['tab' => 'clone'])->withInput();
        }

        $this->alert->success('Szablon usuwania pluginów został zapisany.')->flash();

        return redirect()->route('admin.superadmin', ['tab' => 'clone']);
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
