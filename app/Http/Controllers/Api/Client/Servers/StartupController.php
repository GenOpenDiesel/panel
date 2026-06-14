<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Servers\PaperMcService;
use Pterodactyl\Services\Servers\StartupCommandService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Transformers\Api\Client\EggVariableTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\DownloadPaperMcRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;
use Illuminate\Http\JsonResponse;

class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
        private ServerVariableRepository $repository,
        private PaperMcService $paperMcService,
        private DaemonFileRepository $fileRepository,
    ) {
        parent::__construct();
    }

    /**
     * Returns the startup information for the server including all the variables.
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'docker_images' => $server->egg->docker_images,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }

    /**
     * Updates a single variable for a server.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        if (is_null($variable) || !$variable->user_viewable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit does not exist.');
        } elseif (!$variable->user_editable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit is read-only.');
        }

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $this->validate($request, ['value' => $variable->rules]);

        $this->repository->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value') ?? '',
                ])
                ->log();
        }

        return $this->fractal->item($variable)
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }

    /**
     * Returns available PaperMC versions for the startup downloader.
     */
    public function paperVersions(GetStartupRequest $request, Server $server): JsonResponse
    {
        $versions = $this->paperMcService->getVersions();

        return new JsonResponse([
            'versions' => array_reverse($versions),
        ]);
    }

    /**
     * Downloads the latest PaperMC build for a version and saves it as the server's jar file.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function downloadPaper(DownloadPaperMcRequest $request, Server $server): JsonResponse
    {
        $jarVariable = $server->variables()->where('env_variable', 'SERVER_JARFILE')->first();

        if (is_null($jarVariable)) {
            throw new BadRequestHttpException('This server does not have a SERVER_JARFILE startup variable.');
        }

        $targetFile = $jarVariable->server_value ?? $jarVariable->default_value;
        if (!is_string($targetFile) || $targetFile === '' || !$this->paperMcService->isSafeFileName($targetFile)) {
            throw new BadRequestHttpException('The configured server jar file name is invalid.');
        }

        $build = $this->paperMcService->getLatestBuildDownload($request->input('version'));
        $tempPath = $this->paperMcService->downloadToTemporaryFile($build['download_url']);

        try {
            $this->fileRepository->setServer($server)->writeFile(
                '/' . ltrim($targetFile, '/'),
                $tempPath
            );
        } finally {
            @unlink($tempPath);
        }

        Activity::event('server:startup.paper-download')
            ->property('version', $build['version'])
            ->property('build', $build['build'])
            ->property('filename', $targetFile)
            ->log();

        return new JsonResponse([
            'version' => $build['version'],
            'build' => $build['build'],
            'source_file' => $build['file_name'],
            'filename' => $targetFile,
        ]);
    }
}
