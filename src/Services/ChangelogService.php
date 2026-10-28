<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lit et découpe CHANGELOG.md en versions affichables.
 */
final class ChangelogService
{
    public function __construct(
        private readonly string $changelogPath,
        private readonly string $appVersion,
    ) {
    }

    /**
     * @return array{
     *   version: string,
     *   releases: list<array{id: string, version: string, date: ?string, title: string, markdown: string}>
     * }
     */
    public function getDocument(): array
    {
        $releases = $this->parseReleases($this->readRaw());

        return [
            'version' => $this->appVersion,
            'releases' => $releases,
        ];
    }

    public function readRaw(): string
    {
        if (!is_readable($this->changelogPath)) {
            return "# Changelog\n\nFichier CHANGELOG.md introuvable.\n";
        }

        $content = file_get_contents($this->changelogPath);
        return $content !== false ? $content : "# Changelog\n\nImpossible de lire CHANGELOG.md.\n";
    }

    /**
     * @return list<array{id: string, version: string, date: ?string, title: string, markdown: string}>
     */
    private function parseReleases(string $markdown): array
    {
        $parts = preg_split('/^##\s+/m', $markdown) ?: [];
        array_shift($parts); // drop preamble before first ##

        $releases = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $lines = preg_split("/\r\n|\n|\r/", $part) ?: [];
            $heading = trim((string) array_shift($lines));
            $body = trim(implode("\n", $lines));

            $version = $heading;
            $date = null;

            if (preg_match('/^\[([^\]]+)\](?:\s*-\s*(.+))?$/u', $heading, $m)) {
                $version = $m[1];
                $date = isset($m[2]) ? trim($m[2]) : null;
            }

            $id = strtolower(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $version) ?? $version);
            $title = $date ? sprintf('%s — %s', $version, $date) : $version;

            $releases[] = [
                'id' => $id,
                'version' => $version,
                'date' => $date,
                'title' => $title,
                'markdown' => "## {$heading}\n\n{$body}",
            ];
        }

        return $releases;
    }
}
