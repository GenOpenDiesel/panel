<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Files\FileContentSearchService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\SearchFileContentRequest;

class FileSearchController extends ClientApiController
{
    public function __construct(private FileContentSearchService $searchService)
    {
        parent::__construct();
    }

    public function search(SearchFileContentRequest $request, Server $server): JsonResponse
    {
        set_time_limit(120);

        $query = $request->input('query');
        $directory = $request->input('directory', '/');
        $searchId = $request->input('search_id');

        try {
            $result = $this->searchService->searchChunk($server, $query, $directory, $searchId);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result['progress']['done'] ?? false) {
            Activity::event('server:file.search')
                ->property('query', $query)
                ->property('directory', $directory)
                ->property('matches', $result['meta']['matches'] ?? 0)
                ->log();
        }

        return new JsonResponse($result);
    }
}
