<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Software;

/**
 * Résout la dernière release GitHub et choisit l'asset Windows adapté.
 */
final class GithubReleaseDetector
{
    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   owner?: string,
     *   repo?: string,
     *   tag?: string,
     *   version?: string,
     *   asset_name?: string,
     *   download_url?: string,
     *   html_url?: string,
     *   installer_type?: ?string
     * }
     */
    public function resolve(Software $software): array
    {
        $repoUrl = trim((string) ($software->versionCheckUrl ?: $software->downloadUrl));
        $parsed = $this->parseGithubRepo($repoUrl);
        if ($parsed === null) {
            return [
                'ok' => false,
                'error' => 'URL GitHub invalide. Attendu : https://github.com/{owner}/{repo}/releases',
            ];
        }

        [$owner, $repo] = $parsed;
        $apiUrl = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo);
        $release = $this->fetchJson($apiUrl);
        if (!$release['ok']) {
            return ['ok' => false, 'error' => $release['error'] ?? 'API GitHub inaccessible.'];
        }

        /** @var array<string, mixed> $data */
        $data = $release['data'];
        if (!empty($data['draft']) || !empty($data['prerelease'])) {
            // /latest already skips drafts/prereleases, but keep safe
        }

        $tag = (string) ($data['tag_name'] ?? '');
        $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];
        if ($assets === []) {
            return ['ok' => false, 'error' => 'Aucune asset sur la dernière release GitHub (' . $tag . ').'];
        }

        $asset = $this->pickAsset($assets, $software);
        if ($asset === null) {
            return [
                'ok' => false,
                'error' => 'Aucun installateur Windows adapté trouvé dans la release ' . $tag
                    . ' (type=' . $software->installerType . ', arch=' . $software->architecture . ').',
            ];
        }

        return [
            'ok' => true,
            'owner' => $owner,
            'repo' => $repo,
            'tag' => $tag,
            'version' => $this->normalizeVersion($tag),
            'asset_name' => (string) $asset['name'],
            'download_url' => (string) $asset['browser_download_url'],
            'html_url' => (string) ($data['html_url'] ?? ''),
            'installer_type' => $this->installerTypeFromAssetName((string) $asset['name']),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function parseGithubRepo(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // api.github.com/repos/owner/repo/...
        if (preg_match('#api\.github\.com/repos/([^/]+)/([^/]+)#i', $url, $m)) {
            return [$m[1], preg_replace('/\.git$/i', '', $m[2]) ?? $m[2]];
        }

        // github.com/owner/repo[...]
        if (preg_match('#github\.com/([^/]+)/([^/]+)#i', $url, $m)) {
            $repo = preg_replace('/\.git$/i', '', $m[2]) ?? $m[2];
            $repo = rtrim($repo, '/');

            return [$m[1], $repo];
        }

        // Shorthand owner/repo
        if (preg_match('#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $url, $m)) {
            return [$m[1], $m[2]];
        }

        return null;
    }

    public function normalizeVersion(string $tag): string
    {
        $tag = trim($tag);
        if (preg_match('/^v?(\d+(?:\.\d+)*)$/i', $tag, $m)) {
            return $m[1];
        }
        // "7-Zip 26.02" style already stripped by tag_name usually
        if (preg_match('/(\d+(?:\.\d+)+)/', $tag, $m)) {
            return $m[1];
        }

        return ltrim($tag, 'vV');
    }

    /**
     * Choisit l'asset Windows.
     * Règle produit : si un MSI/MSIX officiel est disponible, il est TOUJOURS préféré à l'EXE.
     *
     * @param list<array<string, mixed>> $assets
     * @return array<string, mixed>|null
     */
    public function pickAsset(array $assets, Software $software): ?array
    {
        $arch = $software->architecture;
        $scored = [];

        foreach ($assets as $asset) {
            $name = strtolower((string) ($asset['name'] ?? ''));
            if ($name === '' || empty($asset['browser_download_url'])) {
                continue;
            }

            // Skip non-Windows / sources
            if (preg_match('/(linux|mac|darwin|src|source|\.tar\.|\.xz$|\.dmg$|\.appimage$)/i', $name)) {
                continue;
            }
            if (str_ends_with($name, '.7z') && !str_ends_with($name, '.exe') && !str_ends_with($name, '.msi')) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['exe', 'msi', 'msix', 'zip'], true)) {
                continue;
            }

            $score = 10;

            // Toujours privilégier un MSI officiel s'il existe
            if ($ext === 'msi' || $ext === 'msix') {
                $score += 200;
            } elseif ($ext === 'exe') {
                $score += 40;
            } elseif ($ext === 'zip') {
                $score += 5;
            }

            // Éviter les variantes portables / minimales (Git for Windows, etc.)
            if (preg_match('/(portable|mingit|busybox|pdb)/i', $name)) {
                $score -= 120;
            }

            // Brave : offline StandaloneSetup (~150 Mo) → Program Files (MECM/system-level).
            // StandaloneSilentSetup installe en %LOCALAPPDATA% (inadapté MECM machine).
            // Stub BraveBrowserSetup.exe (~1 Mo) retélécharge pendant l'install → à éviter.
            if (str_contains($name, 'standalonesilentsetup') && str_ends_with($name, '.exe')) {
                $score += 40;
            } elseif (str_contains($name, 'standalonesetup') && str_ends_with($name, '.exe')) {
                $score += 180;
            } elseif (preg_match('/bravebrowsersetup\.exe$|bravebrowsersilentsetup\.exe$/i', $name)
                && !str_contains($name, 'standalone')) {
                $score -= 100;
            }

            $isArm = (bool) preg_match('/(^|[^a-z])(arm64|aarch64|arm)([^a-z]|$)/i', $name);
            $isX64 = (bool) preg_match('/(x64|x86_64|amd64|win64|64-bit)/i', $name);
            $isX86 = (bool) preg_match('/(x86|win32|ia32|32-bit)(?!_64)/i', $name) && !$isX64;

            if ($arch === 'x64' || $arch === 'multi') {
                if ($isX64) {
                    $score += 40;
                }
                if ($isArm) {
                    $score -= 40;
                }
                if ($isX86) {
                    $score -= 10;
                }
                // bare installer without arch token — acceptable fallback
                if (!$isX64 && !$isX86 && !$isArm && preg_match('/\.(msi|msix|exe)$/', $name)) {
                    $score += 5;
                }
            } elseif ($arch === 'x86') {
                if ($isX86) {
                    $score += 40;
                }
                if ($isX64 || $isArm) {
                    $score -= 30;
                }
            } elseif ($arch === 'arm') {
                if ($isArm) {
                    $score += 40;
                }
                if ($isX64 || $isX86) {
                    $score -= 30;
                }
            }

            $scored[] = ['score' => $score, 'asset' => $asset];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored[0]['score'] > 0 ? $scored[0]['asset'] : null;
    }

    /**
     * Type d'installeur déduit du nom de fichier (msi > exe).
     */
    public function installerTypeFromAssetName(string $assetName): ?string
    {
        $ext = strtolower(pathinfo($assetName, PATHINFO_EXTENSION));

        return match ($ext) {
            'msi' => 'msi',
            'msix' => 'msix',
            'exe' => 'exe',
            'zip' => 'zip',
            default => null,
        };
    }

    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    private function fetchJson(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL requis pour l\'API GitHub.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: SCCM-Packaging-Factory/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            return ['ok' => false, 'error' => 'GitHub API : ' . ($error !== '' ? $error : 'échec')];
        }
        if ($status === 404) {
            return ['ok' => false, 'error' => 'Dépôt ou release GitHub introuvable (404).'];
        }
        if ($status === 403) {
            return ['ok' => false, 'error' => 'GitHub API rate-limit / accès refusé (403).'];
        }
        if ($status >= 400) {
            return ['ok' => false, 'error' => 'GitHub API HTTP ' . $status];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'error' => 'Réponse GitHub JSON invalide.'];
        }

        return ['ok' => true, 'data' => $data];
    }
}
