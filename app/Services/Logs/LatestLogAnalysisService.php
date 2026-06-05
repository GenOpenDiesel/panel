<?php

namespace Pterodactyl\Services\Logs;

use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Server\LogFileNotFoundException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class LatestLogAnalysisService
{
    public const DEFAULT_LOG_PATH = '/logs/latest.log';

    public const DEFAULT_TAIL_BYTES = 4194304;

    private const MAX_LINE_LENGTH = 500;

    private const MAX_MATCHES_PER_PHRASE = 5;

    public function __construct(private DaemonFileRepository $fileRepository)
    {
    }

    /**
     * @return array{phrases: string[], results: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function analyze(string $input, ?int $tailBytes = null): array
    {
        $phrases = $this->parsePhrases($input);
        $tailBytes = $tailBytes ?? (int) config('pterodactyl.log_analysis.tail_bytes', self::DEFAULT_TAIL_BYTES);
        $logPath = (string) config('pterodactyl.log_analysis.log_path', self::DEFAULT_LOG_PATH);

        $servers = Server::query()
            ->with('node')
            ->whereNotNull('installed_at')
            ->whereNull('status')
            ->orderBy('name')
            ->get();

        $results = [];
        $stats = [
            'servers_scanned' => 0,
            'servers_with_matches' => 0,
            'matches' => 0,
            'missing_file' => 0,
            'errors' => 0,
        ];

        foreach ($servers as $server) {
            ++$stats['servers_scanned'];
            $serverMatches = $this->analyzeServer($server, $logPath, $phrases, $tailBytes);

            if ($serverMatches['status'] === 'missing_file') {
                ++$stats['missing_file'];
                $results[] = $serverMatches['entry'];
                continue;
            }

            if ($serverMatches['status'] === 'error') {
                ++$stats['errors'];
                $results[] = $serverMatches['entry'];
                continue;
            }

            if (!empty($serverMatches['entries'])) {
                ++$stats['servers_with_matches'];
                $stats['matches'] += count($serverMatches['entries']);
                array_push($results, ...$serverMatches['entries']);
            }
        }

        return [
            'phrases' => $phrases,
            'results' => $results,
            'meta' => $stats,
        ];
    }

    /**
     * @return string[]
     */
    public function parsePhrases(string $input): array
    {
        $phrases = preg_split('/[\r\n,]+/', $input) ?: [];

        return collect($phrases)
            ->map(fn (string $phrase) => trim($phrase))
            ->filter(fn (string $phrase) => $phrase !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  string[]  $phrases
     * @return array{status: string, entry?: array<string, mixed>, entries?: array<int, array<string, mixed>>}
     */
    private function analyzeServer(Server $server, string $logPath, array $phrases, int $tailBytes): array
    {
        try {
            $content = $this->fileRepository
                ->setServer($server)
                ->getTailContent($logPath, $tailBytes);
        } catch (LogFileNotFoundException) {
            return [
                'status' => 'missing_file',
                'entry' => $this->formatResult($server, 'missing_file', null, null, 'Brak pliku latest.log'),
            ];
        } catch (DaemonConnectionException $exception) {
            return [
                'status' => 'error',
                'entry' => $this->formatResult($server, 'error', null, null, $this->humanizeError($exception)),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'error',
                'entry' => $this->formatResult($server, 'error', null, null, 'Nie udało się odczytać logu serwera.'),
            ];
        }

        if ($content === '') {
            return [
                'status' => 'missing_file',
                'entry' => $this->formatResult($server, 'missing_file', null, null, 'Plik latest.log jest pusty lub niedostępny.'),
            ];
        }

        $entries = $this->findMatches($server, $content, $phrases);

        return [
            'status' => 'ok',
            'entries' => $entries,
        ];
    }

    /**
     * @param  string[]  $phrases
     * @return array<int, array<string, mixed>>
     */
    private function findMatches(Server $server, string $content, array $phrases): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $entries = [];
        $phraseMatchCounts = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            foreach ($phrases as $phrase) {
                $count = $phraseMatchCounts[$phrase] ?? 0;
                if ($count >= self::MAX_MATCHES_PER_PHRASE) {
                    continue;
                }

                if (stripos($line, $phrase) === false) {
                    continue;
                }

                $entries[] = $this->formatResult($server, 'found', $phrase, $this->truncateLine($line));
                $phraseMatchCounts[$phrase] = $count + 1;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResult(
        Server $server,
        string $status,
        ?string $phrase,
        ?string $line,
        ?string $message = null,
    ): array {
        return [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'node_name' => $server->node->name ?? null,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'phrase' => $phrase,
            'line' => $line,
            'message' => $message,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'found' => 'znaleziono',
            'missing_file' => 'brak pliku',
            'error' => 'błąd',
            default => $status,
        };
    }

    private function truncateLine(string $line): string
    {
        if (strlen($line) <= self::MAX_LINE_LENGTH) {
            return $line;
        }

        return substr($line, 0, self::MAX_LINE_LENGTH) . '...';
    }

    private function humanizeError(DaemonConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'refused')) {
            return 'Serwer lub węzeł niedostępny.';
        }

        return 'Nie udało się odczytać logu serwera.';
    }
}
