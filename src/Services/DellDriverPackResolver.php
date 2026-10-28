<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resolves the latest Dell Command | Deploy driver pack from a support KB page.
 * Example: https://www.dell.com/support/kbdoc/fr-fr/000223166/...
 */
final class DellDriverPackResolver
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    private const MAX_HTML = 2_000_000;

    public static function isCatalogUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if (!str_ends_with($host, 'dell.com')) {
            return false;
        }

        return str_contains($path, '/support/kbdoc/')
            || str_contains($path, '/support/article/');
    }

    public static function isDirectPackUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($host !== 'downloads.dell.com' && $host !== 'dl.dell.com') {
            return false;
        }

        return (bool) preg_match('/\.(exe|cab|zip)$/', $path);
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   catalog_url?: string,
     *   download_url?: string,
     *   file_name?: string,
     *   version?: string,
     *   model?: string,
     *   platform?: string,
     *   platform_id?: string,
     *   release_date?: string,
     *   size_label?: string
     * }
     */
    public function resolve(string $catalogUrl): array
    {
        $catalogUrl = trim($catalogUrl);
        if (!self::isCatalogUrl($catalogUrl)) {
            return ['ok' => false, 'error' => __('driver.err_dell_not_kb')];
        }

        $candidates = $this->catalogCandidates($catalogUrl);
        $lastError = __('driver.err_dell_catalog');
        foreach ($candidates as $tryUrl) {
            $html = $this->fetchHtml($tryUrl);
            if (empty($html['ok'])) {
                $lastError = (string) ($html['error'] ?? $lastError);
                continue;
            }
            $parsed = $this->parseHtml((string) $html['body'], $tryUrl);
            if (!empty($parsed['ok'])) {
                $parsed['catalog_url'] = $catalogUrl;

                return $parsed;
            }
            $lastError = (string) ($parsed['error'] ?? $lastError);
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /**
     * @return list<string>
     */
    private function catalogCandidates(string $url): array
    {
        $out = [$url];
        $en = $this->rewriteLocale($url, 'en-us');
        if ($en !== null && strcasecmp($en, $url) !== 0) {
            $out[] = $en;
        }

        return array_values(array_unique($out));
    }

    private function rewriteLocale(string $url, string $locale): ?string
    {
        if (preg_match('#^(https?://[^/]+)/support/kbdoc/[a-z]{2}-[a-z]{2}/(\d{6,})#i', $url, $m) !== 1) {
            return null;
        }

        return $m[1] . '/support/kbdoc/' . $locale . '/' . $m[2];
    }

    /**
     * @return array{ok: bool, error?: string, body?: string}
     */
    private function fetchHtml(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'cURL init failed'];
        }

        $body = '';
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: en-US,en;q=0.9,fr;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use (&$body, &$tooLarge): int {
                $body .= $data;
                if (strlen($body) > self::MAX_HTML) {
                    $tooLarge = true;

                    return 0;
                }

                return strlen($data);
            },
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($tooLarge) {
            return ['ok' => false, 'error' => __('driver.err_dell_catalog')];
        }
        if ($ok === false || $status >= 400 || $status === 0) {
            $hint = $status > 0 ? ('HTTP ' . $status) : $err;

            return ['ok' => false, 'error' => __('driver.err_dell_catalog') . ' (' . $hint . ')'];
        }
        if (stripos($body, 'Access Denied') !== false && !str_contains($body, 'ExistingCabDownloadLink')) {
            return ['ok' => false, 'error' => __('driver.err_dell_catalog') . ' (HTTP 403)'];
        }

        return ['ok' => true, 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHtml(string $html, string $pageUrl): array
    {
        $fromTable = $this->parseSummaryTable($html);
        $links = $this->collectPackLinks($html);
        if ($fromTable['download_url'] ?? null) {
            $links[] = (string) $fromTable['download_url'];
        }
        $links = array_values(array_unique(array_filter($links)));
        $preferred = $this->pickLink($links, $pageUrl, $fromTable['download_url'] ?? null);
        if ($preferred === null) {
            return ['ok' => false, 'error' => __('driver.err_dell_no_pack')];
        }

        $fileName = $this->fileNameFromUrl($preferred);
        $version = (string) ($fromTable['version'] ?? '');
        if ($version === '') {
            $version = $this->versionFromFileName($fileName) ?? '';
        }

        return [
            'ok' => true,
            'download_url' => $preferred,
            'file_name' => $fileName,
            'version' => $version,
            'model' => (string) ($fromTable['model'] ?? ''),
            'platform' => (string) ($fromTable['platform'] ?? ''),
            'platform_id' => (string) ($fromTable['platform_id'] ?? ''),
            'release_date' => (string) ($fromTable['release_date'] ?? ''),
            'size_label' => (string) ($fromTable['size_label'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseSummaryTable(string $html): array
    {
        $out = [];
        if (preg_match(
            '#<table[^>]*id=["\']CurrentDriverCABSummary["\'][^>]*>(.*?)</table>#is',
            $html,
            $table
        ) !== 1) {
            if (preg_match('#id=["\']ExistingCabDownloadLink["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $m) === 1) {
                $out['download_url'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return $out;
        }

        if (preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $table[1], $rows) !== false) {
            foreach ($rows[1] as $rowHtml) {
                if (!preg_match_all('#<td[^>]*>(.*?)</td>#is', $rowHtml, $cells)) {
                    continue;
                }
                $texts = array_map(static fn (string $c): string => trim(html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $cells[1]);
                if ($texts === [] || preg_match('/^(model|modèle)$/i', $texts[0]) === 1) {
                    continue;
                }
                if (count($texts) >= 4 && preg_match('/^A\d+$/i', $texts[3]) === 1) {
                    $out['model'] = $texts[0];
                    $out['platform'] = $texts[1] ?? '';
                    $out['platform_id'] = $texts[2] ?? '';
                    $out['version'] = strtoupper($texts[3]);
                    $out['release_date'] = $texts[4] ?? '';
                    $out['size_label'] = $texts[5] ?? '';
                }
                if (preg_match('#href=["\'](https?://(?:downloads|dl)\.dell\.com/[^"\']+)["\']#i', $rowHtml, $href) === 1) {
                    $out['download_url'] = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (isset($out['version'], $out['download_url'])) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function collectPackLinks(string $html): array
    {
        $links = [];
        if (preg_match_all('#https?://(?:downloads|dl)\.dell\.com/FOLDER[^"\'\s<>]+#i', $html, $m) > 0) {
            foreach ($m[0] as $raw) {
                $url = html_entity_decode(rtrim($raw, '.,);'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = str_replace('&amp;', '&', $url);
                if (self::isDirectPackUrl($url) || preg_match('/\.(exe|cab|zip)(?:\?|$)/i', $url) === 1) {
                    $links[] = $url;
                }
            }
        }

        return $links;
    }

    /**
     * @param list<string> $links
     */
    private function pickLink(array $links, string $pageUrl, ?string $tableLink): ?string
    {
        if ($tableLink) {
            return $this->normalizePackUrl($tableLink);
        }
        if ($links === []) {
            return null;
        }

        $wantWin11 = str_contains(strtolower($pageUrl), 'windows-11') || str_contains(strtolower($pageUrl), 'win11');
        $scored = [];
        foreach ($links as $url) {
            $url = $this->normalizePackUrl($url);
            $name = strtolower($this->fileNameFromUrl($url));
            $score = $this->versionSortKey($name);
            if ($wantWin11 && str_contains($name, 'win11')) {
                $score += 1000;
            }
            if (!$wantWin11 && str_contains($name, 'win10')) {
                $score += 1000;
            }
            $scored[] = [$score, $url];
        }
        usort($scored, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return $scored[0][1] ?? null;
    }

    private function normalizePackUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_replace(['%5F', '%5f'], '_', $url);
    }

    private function fileNameFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = rawurldecode(basename($path));

        return $name !== '' ? $name : 'driver-pack.bin';
    }

    private function versionFromFileName(string $fileName): ?string
    {
        if (preg_match('/_A(\d+)\.(exe|cab|zip)$/i', $fileName, $m) === 1) {
            return 'A' . $m[1];
        }

        return null;
    }

    private function versionSortKey(string $fileName): int
    {
        if (preg_match('/_a(\d+)\./i', $fileName, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }
}
