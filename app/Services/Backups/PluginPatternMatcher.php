<?php

namespace Pterodactyl\Services\Backups;

class PluginPatternMatcher
{
    /**
     * Matches plugin file names against comma-separated or array patterns.
     * Only files are matched — directories are always excluded.
     *
     * Pattern rules:
     * - Case-insensitive
     * - Trailing * is optional (goxy and goxy* behave the same)
     * - Matches file names that start with the pattern prefix
     *
     * @param  array<int, array{name: string, type: string}>  $plugins
     * @return string[]
     */
    public function match(array $plugins, string|array $patterns): array
    {
        $parsed = $this->parsePatterns($patterns);

        if (empty($parsed)) {
            return [];
        }

        $matched = [];

        foreach ($plugins as $plugin) {
            if (($plugin['type'] ?? '') === 'directory') {
                continue;
            }

            $name = $plugin['name'] ?? '';
            if ($name === '' || !$this->isSafePluginName($name)) {
                continue;
            }

            foreach ($parsed as $pattern) {
                if ($this->matchesPattern($name, $pattern)) {
                    $matched[] = $name;
                    break;
                }
            }
        }

        sort($matched, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values(array_unique($matched));
    }

    /**
     * @return string[]
     */
    public function parsePatterns(string|array $patterns): array
    {
        if (is_string($patterns)) {
            $patterns = explode(',', $patterns);
        }

        return collect($patterns)
            ->filter(fn ($pattern) => is_string($pattern))
            ->map(fn (string $pattern) => trim($pattern))
            ->filter(fn (string $pattern) => $pattern !== '' && $this->isSafePattern($pattern))
            ->values()
            ->all();
    }

    private function matchesPattern(string $fileName, string $pattern): bool
    {
        $prefix = rtrim($pattern, '*');
        if ($prefix === '') {
            return false;
        }

        return str_starts_with(strtolower($fileName), strtolower($prefix));
    }

    private function isSafePattern(string $pattern): bool
    {
        return !str_contains($pattern, '/')
            && !str_contains($pattern, '\\')
            && !str_contains($pattern, '..');
    }

    private function isSafePluginName(string $name): bool
    {
        return $name !== ''
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && !str_contains($name, '..');
    }
}
