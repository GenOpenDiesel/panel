<?php

namespace Pterodactyl\Services\Files;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Server\FileSizeTooLargeException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class FileContentSearchService
{
    private const MAX_LINE_LENGTH = 500;

    private const MAX_MATCHES_PER_FILE = 10;

    private const MAX_MATCHES_TOTAL = 200;

    public function __construct(private DaemonFileRepository $fileRepository)
    {
    }

    /**
     * @return array{
     *     query: string,
     *     results: array<int, array<string, mixed>>,
     *     meta: array<string, int|bool>,
     *     progress: array<string, int|bool|string|null>,
     *     search_id: string|null
     * }
     */
    public function searchChunk(Server $server, string $query, ?string $directory = '/', ?string $searchId = null): array
    {
        $query = trim($query);

        if ($searchId) {
            $cacheKey = $this->cacheKey($server, $searchId);
            $state = Cache::get($cacheKey);

            if (!is_array($state) || ($state['query'] ?? '') !== $query) {
                throw new \InvalidArgumentException('Sesja wyszukiwania wygasła lub jest nieprawidłowa. Uruchom wyszukiwanie ponownie.');
            }
        } else {
            $searchId = Str::uuid()->toString();
            $cacheKey = $this->cacheKey($server, $searchId);
            $state = $this->initialState($query, $directory ?? '/');
        }

        $maxFileSize = (int) config('pterodactyl.file_search.max_file_size', 524288);
        $maxFilesPerScan = (int) config('pterodactyl.file_search.max_files_per_scan', 500);
        $filesPerChunk = (int) config('pterodactyl.file_search.files_per_chunk', 15);
        $cacheTtl = (int) config('pterodactyl.file_search.session_ttl', 3600);

        $results = [];
        $filesProcessedThisChunk = 0;
        $queue = $state['queue'];
        $pendingEntries = $state['pending_entries'];
        $currentDirectory = $state['current_directory'];
        $stats = $state['stats'];
        $matchTotal = $state['match_total'];

        while (true) {
            if ($stats['files_scanned'] >= $maxFilesPerScan || $matchTotal >= self::MAX_MATCHES_TOTAL) {
                $stats['truncated'] = true;
                break;
            }

            if ($filesProcessedThisChunk >= $filesPerChunk) {
                break;
            }

            if (empty($pendingEntries)) {
                if (empty($queue)) {
                    $currentDirectory = null;
                    break;
                }

                $currentDirectory = array_shift($queue);
                ++$stats['directories_scanned'];

                try {
                    $pendingEntries = $this->fileRepository->setServer($server)->getDirectory($currentDirectory);
                } catch (DaemonConnectionException) {
                    ++$stats['errors'];
                    $pendingEntries = [];
                    continue;
                } catch (\Throwable) {
                    ++$stats['errors'];
                    $pendingEntries = [];
                    continue;
                }
            }

            while (!empty($pendingEntries)) {
                if ($filesProcessedThisChunk >= $filesPerChunk) {
                    break 2;
                }

                if ($stats['files_scanned'] >= $maxFilesPerScan || $matchTotal >= self::MAX_MATCHES_TOTAL) {
                    $stats['truncated'] = true;
                    break 2;
                }

                $entry = array_shift($pendingEntries);
                $name = $entry['name'] ?? '';
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                if ($entry['symlink'] ?? false) {
                    continue;
                }

                $path = $this->joinPath($currentDirectory, $name);
                $isFile = $entry['file'] ?? true;

                if (!$isFile) {
                    if ($this->isExcludedDirectory($name)) {
                        ++$stats['directories_skipped'];
                        continue;
                    }

                    $queue[] = $path;
                    continue;
                }

                if (!$this->hasAllowedExtension($name)) {
                    ++$stats['files_skipped_extension'];
                    continue;
                }

                $size = (int) ($entry['size'] ?? 0);
                if ($size <= 0 || $size > $maxFileSize) {
                    ++$stats['files_skipped_size'];
                    continue;
                }

                ++$stats['files_scanned'];
                ++$filesProcessedThisChunk;

                try {
                    $content = $this->fileRepository->setServer($server)->getContent($path, $maxFileSize);
                } catch (FileSizeTooLargeException) {
                    ++$stats['files_skipped_size'];
                    --$stats['files_scanned'];
                    continue;
                } catch (DaemonConnectionException) {
                    ++$stats['errors'];
                    continue;
                } catch (\Throwable) {
                    ++$stats['errors'];
                    continue;
                }

                $fileMatches = $this->findMatches($path, $content, $query);
                if (!empty($fileMatches)) {
                    array_push($results, ...$fileMatches);
                    $matchTotal += count($fileMatches);
                    $stats['matches'] += count($fileMatches);
                }

                if ($matchTotal >= self::MAX_MATCHES_TOTAL) {
                    $stats['truncated'] = true;
                    break 2;
                }
            }

            if (empty($pendingEntries)) {
                $currentDirectory = null;
            }
        }

        if ((!empty($queue) || !empty($pendingEntries)) && $stats['files_scanned'] >= $maxFilesPerScan) {
            $stats['truncated'] = true;
        }

        $directoriesPending = count($queue) + (empty($pendingEntries) ? 0 : 1);
        $done = empty($queue) && empty($pendingEntries) || $stats['truncated'];
        $progress = $this->buildProgress($stats, $maxFilesPerScan, $directoriesPending, $done);

        if ($done) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, [
                'queue' => $queue,
                'current_directory' => $currentDirectory,
                'pending_entries' => array_map(fn (array $entry) => $this->slimEntry($entry), $pendingEntries),
                'stats' => $stats,
                'match_total' => $matchTotal,
                'query' => $query,
            ], $cacheTtl);
        }

        return [
            'query' => $query,
            'results' => $results,
            'meta' => $stats,
            'progress' => $progress,
            'search_id' => $done ? null : $searchId,
        ];
    }

    private function cacheKey(Server $server, string $searchId): string
    {
        return "file_search:{$server->uuid}:{$searchId}";
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function slimEntry(array $entry): array
    {
        return [
            'name' => $entry['name'] ?? '',
            'file' => $entry['file'] ?? true,
            'symlink' => $entry['symlink'] ?? false,
            'size' => $entry['size'] ?? 0,
        ];
    }

    /**
     * @param  array<string, int|bool>  $stats
     * @return array<string, int|bool|string|null>
     */
    private function buildProgress(array $stats, int $filesLimit, int $directoriesPending, bool $done): array
    {
        $filesScanned = (int) ($stats['files_scanned'] ?? 0);
        $directoriesScanned = (int) ($stats['directories_scanned'] ?? 0);

        if ($done) {
            $percent = 100;
        } elseif ($filesScanned > 0) {
            $percent = (int) min(99, floor(($filesScanned / max($filesLimit, 1)) * 100));
        } elseif ($directoriesScanned > 0) {
            $percent = (int) min(15, max(2, $directoriesScanned * 2));
        } else {
            $percent = 1;
        }

        return [
            'done' => $done,
            'percent' => $percent,
            'files_scanned' => $filesScanned,
            'files_limit' => $filesLimit,
            'directories_scanned' => (int) ($stats['directories_scanned'] ?? 0),
            'directories_pending' => $directoriesPending,
            'matches' => (int) ($stats['matches'] ?? 0),
            'current_phase' => $filesScanned > 0 || $done ? 'scanning_files' : 'listing_directories',
        ];
    }

    /**
     * @return array{
     *     queue: string[],
     *     current_directory: string|null,
     *     pending_entries: array<int, array<string, mixed>>,
     *     stats: array<string, int|bool>,
     *     match_total: int,
     *     query: string
     * }
     */
    private function initialState(string $query, string $directory): array
    {
        return [
            'queue' => [$this->normalizeDirectory($directory)],
            'current_directory' => null,
            'pending_entries' => [],
            'stats' => [
                'files_scanned' => 0,
                'files_skipped_size' => 0,
                'files_skipped_extension' => 0,
                'directories_skipped' => 0,
                'directories_scanned' => 0,
                'errors' => 0,
                'matches' => 0,
                'truncated' => false,
            ],
            'match_total' => 0,
            'query' => $query,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findMatches(string $path, string $content, string $query): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $matches = [];

        foreach ($lines as $index => $line) {
            if (count($matches) >= self::MAX_MATCHES_PER_FILE) {
                break;
            }

            if (mb_stripos($line, $query, 0, 'UTF-8') === false) {
                continue;
            }

            $matches[] = [
                'file' => $path,
                'line' => $index + 1,
                'content' => $this->truncateLine(trim($line)),
            ];
        }

        return $matches;
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = '/' . trim(str_replace('\\', '/', $directory), '/');

        return $directory === '/' ? '/' : rtrim($directory, '/');
    }

    private function joinPath(string $directory, string $name): string
    {
        return $directory === '/' ? '/' . $name : $directory . '/' . $name;
    }

    private function isExcludedDirectory(string $name): bool
    {
        $excluded = config('pterodactyl.file_search.excluded_directories', ['world', 'cache', 'logs']);

        return in_array(strtolower($name), array_map('strtolower', $excluded), true);
    }

    private function hasAllowedExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        $allowed = config('pterodactyl.file_search.extensions', []);

        return in_array($extension, $allowed, true);
    }

    private function truncateLine(string $line): string
    {
        if (mb_strlen($line, 'UTF-8') <= self::MAX_LINE_LENGTH) {
            return $line;
        }

        return mb_substr($line, 0, self::MAX_LINE_LENGTH, 'UTF-8') . '...';
    }
}
