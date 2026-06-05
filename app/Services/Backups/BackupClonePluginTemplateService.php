<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class BackupClonePluginTemplateService
{
    public const SETTING_KEY = 'settings::backup_clone_plugin_template';

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private PluginPatternMatcher $pluginPatternMatcher,
    ) {
    }

    public function get(): string
    {
        return (string) $this->settings->get(
            self::SETTING_KEY,
            config('backups.clone_plugin_template', 'luckperms*,goxy*')
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function set(string $template): void
    {
        $normalized = $this->normalize($template);
        $this->settings->set(self::SETTING_KEY, $normalized);
    }

    public function normalize(string $template): string
    {
        $patterns = $this->pluginPatternMatcher->parsePatterns($template);

        return implode(',', $patterns);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validate(string $template): void
    {
        $raw = collect(explode(',', $template))
            ->map(fn (string $pattern) => trim($pattern))
            ->filter(fn (string $pattern) => $pattern !== '');

        foreach ($raw as $pattern) {
            if (str_contains($pattern, '/') || str_contains($pattern, '\\') || str_contains($pattern, '..')) {
                throw new \InvalidArgumentException('Szablon zawiera niedozwolone znaki w nazwie pluginu.');
            }

            if (strlen($pattern) > 255) {
                throw new \InvalidArgumentException('Nazwa pluginu w szablonie nie może przekraczać 255 znaków.');
            }
        }
    }
}
