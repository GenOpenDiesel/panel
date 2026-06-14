<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaperMcService
{
    private const API_BASE = 'https://api.papermc.io/v2/projects/paper';

    /**
     * @return string[]
     */
    public function getVersions(): array
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->get(self::API_BASE);

        if (!$response->successful()) {
            throw new BadRequestHttpException('Unable to fetch PaperMC versions at this time.');
        }

        $versions = $response->json('versions');

        if (!is_array($versions) || empty($versions)) {
            throw new BadRequestHttpException('PaperMC returned an empty version list.');
        }

        return array_values($versions);
    }

    /**
     * @return array{version: string, build: int, download_url: string, file_name: string}
     */
    public function getLatestBuildDownload(string $version): array
    {
        if (!$this->isValidVersion($version)) {
            throw new BadRequestHttpException('The selected PaperMC version is invalid.');
        }

        $versionResponse = Http::timeout(15)
            ->acceptJson()
            ->get(self::API_BASE . '/versions/' . rawurlencode($version));

        if (!$versionResponse->successful()) {
            throw new BadRequestHttpException('The selected PaperMC version was not found.');
        }

        $builds = $versionResponse->json('builds');
        if (!is_array($builds) || empty($builds)) {
            throw new BadRequestHttpException('No builds were found for the selected PaperMC version.');
        }

        $latestBuild = (int) max(array_map('intval', $builds));

        $buildResponse = Http::timeout(15)
            ->acceptJson()
            ->get(self::API_BASE . '/versions/' . rawurlencode($version) . '/builds/' . $latestBuild);

        if (!$buildResponse->successful()) {
            throw new BadRequestHttpException('Unable to fetch PaperMC build details.');
        }

        $downloadName = $buildResponse->json('downloads.application.name');
        if (!is_string($downloadName) || $downloadName === '' || !$this->isSafeFileName($downloadName)) {
            throw new BadRequestHttpException('PaperMC returned an invalid download file name.');
        }

        return [
            'version' => $version,
            'build' => $latestBuild,
            'download_url' => self::API_BASE . '/versions/' . rawurlencode($version) . '/builds/' . $latestBuild . '/downloads/' . rawurlencode($downloadName),
            'file_name' => $downloadName,
        ];
    }

    public function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+(?:\.\d+)?(?:-pre\d+)?$/', $version);
    }

    public function isSafeFileName(string $name): bool
    {
        return $name !== ''
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && !str_contains($name, '..');
    }
}
