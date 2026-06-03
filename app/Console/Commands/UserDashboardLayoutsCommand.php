<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class UserDashboardLayoutsCommand extends Command
{
    protected $signature = 'p:user-dashboard-layouts
                            {--user= : Filter by user id, username or email}
                            {--json : Output raw JSON}
                            {--all : Include users without a saved layout}';

    protected $description = 'Show how users organized their dashboard servers (sections and sort order).';

    public function handle(): int
    {
        $query = User::query()->select(['id', 'username', 'email', 'dashboard_layout']);

        if ($userFilter = $this->option('user')) {
            $query->where(function ($builder) use ($userFilter) {
                $builder->where('id', $userFilter)
                    ->orWhere('username', $userFilter)
                    ->orWhere('email', $userFilter);
            });
        } elseif (!$this->option('all')) {
            $query->whereNotNull('dashboard_layout');
        }

        $users = $query->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->warn('No users found with a saved dashboard layout.');

            return self::SUCCESS;
        }

        $serverUuids = $users->flatMap(function (User $user) {
            $layout = $user->dashboard_layout ?? [];

            return collect($layout['sections'] ?? [])
                ->flatMap(fn (array $section) => $section['serverUuids'] ?? [])
                ->merge($layout['unsectionedOrder'] ?? []);
        })->unique()->values();

        $servers = Server::query()
            ->whereIn('uuid', $serverUuids)
            ->get(['uuid', 'id', 'name'])
            ->keyBy('uuid');

        $output = $users->map(function (User $user) use ($servers) {
            $layout = $user->dashboard_layout ?? [
                'sortMode' => null,
                'sections' => [],
                'unsectionedOrder' => [],
            ];

            $sectioned = collect($layout['sections'] ?? [])
                ->flatMap(fn (array $section) => $section['serverUuids'] ?? []);

            $resolve = fn (string $uuid) => $servers->has($uuid)
                ? ['uuid' => $uuid, 'id' => $servers->get($uuid)->id, 'name' => $servers->get($uuid)->name]
                : ['uuid' => $uuid, 'id' => null, 'name' => null];

            return [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ],
                'layout' => [
                    'sortMode' => $layout['sortMode'] ?? null,
                    'sections' => collect($layout['sections'] ?? [])->map(fn (array $section) => [
                        'id' => $section['id'] ?? null,
                        'name' => $section['name'] ?? null,
                        'servers' => collect($section['serverUuids'] ?? [])->map($resolve)->values()->all(),
                    ])->values()->all(),
                    'unsectioned' => collect($layout['unsectionedOrder'] ?? [])
                        ->reject(fn (string $uuid) => $sectioned->contains($uuid))
                        ->map($resolve)
                        ->values()
                        ->all(),
                ],
            ];
        })->values()->all();

        if ($this->option('json')) {
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach ($output as $entry) {
            $user = $entry['user'];
            $layout = $entry['layout'];

            $this->newLine();
            $this->info(sprintf('%s (%s) [ID: %d]', $user['username'], $user['email'], $user['id']));
            $this->line('Sortowanie: ' . ($layout['sortMode'] ?? 'brak'));

            if (empty($layout['sections']) && empty($layout['unsectioned']) && is_null($layout['sortMode'])) {
                $this->line('  Brak zapisanego układu.');

                continue;
            }

            foreach ($layout['sections'] as $section) {
                $this->line('');
                $this->line('  [Sekcja] ' . ($section['name'] ?? '?'));

                if (empty($section['servers'])) {
                    $this->line('    (pusta)');

                    continue;
                }

                foreach ($section['servers'] as $server) {
                    $label = $server['name']
                        ? sprintf('%s (#%d)', $server['name'], $server['id'])
                        : sprintf('Nieznany serwer (%s)', $server['uuid']);

                    $this->line('    - ' . $label);
                }
            }

            if (!empty($layout['unsectioned'])) {
                $this->line('');
                $this->line('  [Pozostałe serwery]');

                foreach ($layout['unsectioned'] as $server) {
                    $label = $server['name']
                        ? sprintf('%s (#%d)', $server['name'], $server['id'])
                        : sprintf('Nieznany serwer (%s)', $server['uuid']);

                    $this->line('    - ' . $label);
                }
            }
        }

        return self::SUCCESS;
    }
}
