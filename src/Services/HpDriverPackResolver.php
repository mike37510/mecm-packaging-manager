<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resolves an HP SoftPaq driver pack from the public Driver Pack matrix.
 * Example: https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html
 *
 * Version lives on the SoftPaq link title ("Version: 2.00 A 1"), not in the file name.
 * PHP never downloads the .exe (often gigabytes).
 */
final class HpDriverPackResolver
{
    public const MATRIX_X64 = 'https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html';
    public const MATRIX_X86 = 'https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x86.html';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    private const MAX_HTML = 2_500_000;
    private const CACHE_TTL = 900;

    /**
     * Form OS choices (slug => label). Empty slug = newest Windows 11 column that has a pack.
     *
     * @var array<string, string>
     */
    public const OS_CHOICES = [
        '' => '',
        'win11-25h2' => 'Windows 11 25H2',
        'win11-24h2' => 'Windows 11 24H2',
        'win11-iot-24h2-ltsc' => 'Windows 11 IoT 24H2 LTSC',
        'win11-23h2' => 'Windows 11 23H2',
        'win11-22h2' => 'Windows 11 22H2',
        'win11-21h2' => 'Windows 11 21H2',
    ];

    public function __construct(
        private readonly ?string $cacheDir = null,
    ) {
    }

    public static function defaultMatrixUrl(string $architecture = 'x64'): string
    {
        return strtolower($architecture) === 'x86' ? self::MATRIX_X86 : self::MATRIX_X64;
    }

    public static function isCatalogUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($host !== 'ftp.hp.com' && $host !== 'www.ftp.hp.com') {
            return false;
        }

        return str_contains($path, '/caps-softpaq/cmit/')
            && str_contains($path, 'driverpack_matrix');
    }

    public static function isDirectPackUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($host !== 'ftp.hp.com' && $host !== 'www.ftp.hp.com') {
            return false;
        }

        return (bool) preg_match('#/pub/softpaq/.+/sp\d+\.(exe|cva)$#', $path);
    }

    public static function osLabel(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        return self::OS_CHOICES[$slug] ?? $slug;
    }

    /**
     * Dropdown values. A leftover slug (e.g. old Win10) stays selectable on an existing record.
     *
     * @return array<string, string>
     */
    public static function osChoicesFor(string $existing = ''): array
    {
        $list = self::OS_CHOICES;
        $existing = trim($existing);
        if ($existing !== '' && !array_key_exists($existing, $list)) {
            $list[$existing] = self::osLabel($existing);
        }

        return $list;
    }

    public static function isAllowedOsTarget(string $slug, string $existing = ''): bool
    {
        $slug = trim($slug);
        if ($slug === '' || array_key_exists($slug, self::OS_CHOICES)) {
            return true;
        }

        return $existing !== '' && $slug === $existing;
    }

    public static function isWindows11Slug(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return $slug === '' || str_starts_with($slug, 'win11');
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
     *   os_slug?: string,
     *   os_label?: string,
     *   softpaq?: string,
     *   release_date?: string
     * }
     */
    public function resolve(string $catalogUrl, string $model, string $osTarget = ''): array
    {
        $catalogUrl = trim($catalogUrl);
        if (!self::isCatalogUrl($catalogUrl)) {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_not_matrix')];
        }
        $model = trim($model);
        if ($model === '') {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_model')];
        }

        $osFromUrl = $this->osFromCatalogUrl($catalogUrl);
        if ($osTarget === '' && $osFromUrl !== '') {
            $osTarget = $osFromUrl;
        }

        $fetchUrl = $this->stripQuery($catalogUrl);
        $html = $this->loadHtml($fetchUrl);
        if (empty($html['ok'])) {
            return ['ok' => false, 'error' => (string) ($html['error'] ?? $this->msg('driver.err_hp_catalog'))];
        }

        $picked = $this->resolveFromHtml((string) $html['body'], $model, $osTarget);
        if (!empty($picked['ok'])) {
            $picked['catalog_url'] = $catalogUrl;
        }

        return $picked;
    }

    /**
     * Parse a matrix HTML document and pick a SoftPaq (used by tests; no HTTP).
     *
     * @return array<string, mixed>
     */
    public function resolveFromHtml(string $html, string $model, string $osTarget = ''): array
    {
        $matrix = $this->parseMatrix($html);
        if ($matrix['rows'] === []) {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_catalog')];
        }

        $match = $this->matchRow($matrix['rows'], $model);
        if (empty($match['ok'])) {
            return $match;
        }

        /** @var array{models: list<string>, packs: array<string, array<string, string>>, matched_model: string} $row */
        $row = $match['row'];
        $pack = $this->pickPack($row['packs'], $osTarget);
        if (empty($pack['ok'])) {
            return $pack;
        }

        $fileName = (string) $pack['file_name'];
        $softpaq = (string) $pack['softpaq'];
        $packVer = (string) $pack['version'];
        $display = $packVer !== ''
            ? $packVer . ' (' . $softpaq . ')'
            : $softpaq;

        return [
            'ok' => true,
            'download_url' => (string) $pack['download_url'],
            'file_name' => $fileName,
            'version' => $display,
            'model' => (string) $row['matched_model'],
            'os_slug' => (string) $pack['os_slug'],
            'os_label' => (string) $pack['os_label'],
            'softpaq' => $softpaq,
            'release_date' => (string) ($pack['release_date'] ?? ''),
            'pack_version' => $packVer,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<array{models: list<string>, packs: array<string, array<string, string>>}>}
     */
    public function parseMatrix(string $html): array
    {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        $tables = $xpath->query('//table[.//th[contains(., "Windows")]]');
        if ($tables === false || $tables->length === 0) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [];
        $rows = [];
        /** @var \DOMElement $table */
        $table = $tables->item(0);
        foreach ($table->getElementsByTagName('tr') as $tr) {
            /** @var \DOMElement $tr */
            $thTexts = $this->cellTexts($tr, 'th');
            if ($thTexts !== [] && $this->looksLikeOsHeader($thTexts)) {
                $headers = $this->normalizeHeaders($thTexts);
                continue;
            }
            $tdNodes = [];
            foreach ($tr->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->tagName) === 'td') {
                    $tdNodes[] = $child;
                }
            }
            if ($tdNodes === []) {
                continue;
            }
            $firstText = trim($this->nodeText($tdNodes[0]));
            if ($firstText === '' || $this->isSectionHeading($tdNodes[0], $firstText)) {
                continue;
            }
            $models = $this->modelsFromCell($tdNodes[0]);
            if ($models === []) {
                continue;
            }
            $packs = [];
            $colCount = max(count($headers), count($tdNodes));
            for ($i = 1; $i < $colCount; $i++) {
                $slug = $headers[$i] ?? ('col-' . $i);
                if (!isset($tdNodes[$i])) {
                    continue;
                }
                $parsed = $this->packFromCell($tdNodes[$i]);
                if ($parsed !== null) {
                    $parsed['os_slug'] = $slug;
                    $parsed['os_label'] = self::OS_CHOICES[$slug] ?? $this->headerLabel($headers, $i);
                    $packs[$slug] = $parsed;
                }
            }
            $rows[] = [
                'models' => $models,
                'packs' => $packs,
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param list<array{models: list<string>, packs: array<string, array<string, string>>}> $rows
     * @return array{ok: bool, error?: string, row?: array{models: list<string>, packs: array<string, array<string, string>>, matched_model: string}}
     */
    private function matchRow(array $rows, string $query): array
    {
        $queryNorm = $this->normalizeModel($query);
        if ($queryNorm === '' || mb_strlen($queryNorm) < 3) {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_model')];
        }

        $best = [];
        $bestScore = 0;
        foreach ($rows as $row) {
            foreach ($row['models'] as $model) {
                $score = $this->modelScore($queryNorm, $this->normalizeModel($model), $model);
                if ($score <= 0) {
                    continue;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [[$row, $model]];
                } elseif ($score === $bestScore) {
                    $best[] = [$row, $model];
                }
            }
        }

        if ($best === [] || $bestScore < 40) {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_model_unknown')];
        }

        $firstPacks = $best[0][0]['packs'];
        $names = [];
        foreach ($best as [$row, $model]) {
            $names[] = $model;
            if ($row['packs'] !== $firstPacks && $this->packIdentity($row['packs']) !== $this->packIdentity($firstPacks)) {
                $shown = array_slice(array_unique($names), 0, 8);

                return [
                    'ok' => false,
                    'error' => $this->msg('driver.err_hp_model_ambiguous', [
                        'models' => implode(', ', $shown),
                    ]),
                ];
            }
        }

        $row = $best[0][0];
        $row['matched_model'] = $best[0][1];

        return ['ok' => true, 'row' => $row];
    }

    /**
     * @param array<string, array<string, string>> $packs
     * @return array<string, mixed>
     */
    private function pickPack(array $packs, string $osTarget): array
    {
        if ($packs === []) {
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_no_pack')];
        }

        $osTarget = trim($osTarget);
        if ($osTarget === '') {
            foreach ($packs as $slug => $pack) {
                if (self::isWindows11Slug((string) $slug)) {
                    return ['ok' => true] + $pack;
                }
            }

            $available = implode(', ', array_keys($packs));

            return [
                'ok' => false,
                'error' => $this->msg('driver.err_hp_os_missing', [
                    'os' => 'Windows 11',
                    'available' => $available,
                ]),
            ];
        }

        if (isset($packs[$osTarget])) {
            return ['ok' => true] + $packs[$osTarget];
        }

        foreach ($packs as $slug => $pack) {
            if (strcasecmp($slug, $osTarget) === 0) {
                return ['ok' => true] + $pack;
            }
        }

        $available = implode(', ', array_keys($packs));

        return [
            'ok' => false,
            'error' => $this->msg('driver.err_hp_os_missing', ['os' => $osTarget, 'available' => $available]),
        ];
    }

    /**
     * @param list<string> $cells
     */
    private function looksLikeOsHeader(array $cells): bool
    {
        $joined = implode(' ', $cells);

        return stripos($joined, 'Windows 11') !== false || stripos($joined, 'Windows 10') !== false;
    }

    /**
     * @param list<string> $cells
     * @return list<string>
     */
    private function normalizeHeaders(array $cells): array
    {
        $out = [];
        foreach ($cells as $i => $text) {
            $out[$i] = $i === 0 ? '' : $this->osSlug($text);
        }

        return $out;
    }

    public function osSlug(string $header): string
    {
        $h = strtolower(trim($header));
        if ($h === '') {
            return '';
        }
        $win = str_contains($h, 'windows 11') ? 'win11' : (str_contains($h, 'windows 10') ? 'win10' : 'win');
        $iot = str_contains($h, 'iot') ? '-iot' : '';
        $ltsc = (str_contains($h, 'ltsc') || str_contains($h, 'ltsb')) ? '-ltsc' : '';
        $rel = '';
        if (preg_match('/\b(\d{2}h\d)\b/i', $h, $m) === 1) {
            $rel = '-' . strtolower($m[1]);
        } elseif (preg_match('/\b(20(?:19|21|16))\b/', $h, $m) === 1) {
            $rel = '-' . $m[1];
        } elseif (preg_match('/\b(2004|1909|1903|1809|1803|1709|1703)\b/', $h, $m) === 1) {
            $rel = '-' . $m[1];
        }

        return $win . $iot . $rel . $ltsc;
    }

    /**
     * @param \DOMElement $tr
     * @return list<string>
     */
    private function cellTexts(\DOMElement $tr, string $tag): array
    {
        $out = [];
        foreach ($tr->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === $tag) {
                $out[] = trim($this->nodeText($child));
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function modelsFromCell(\DOMElement $td): array
    {
        $models = [];
        foreach ($td->getElementsByTagName('p') as $p) {
            $text = trim($this->nodeText($p));
            if ($text !== '' && $text !== '-') {
                $models[] = $text;
            }
        }
        if ($models === []) {
            $text = trim($this->nodeText($td));
            if ($text !== '' && $text !== '-') {
                $models[] = $text;
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * @return array<string, string>|null
     */
    private function packFromCell(\DOMElement $td): ?array
    {
        foreach ($td->getElementsByTagName('a') as $a) {
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $href = trim(html_entity_decode($a->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('#sp(\d+)\.exe(?:\?|$)#i', $href, $m) !== 1) {
                continue;
            }
            $title = html_entity_decode($a->getAttribute('title'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $version = '';
            $date = '';
            if (preg_match('/Version:\s*(.+?)\s+Release Date:\s*(\S+)/i', $title, $vm) === 1) {
                $version = trim($vm[1]);
                $date = trim($vm[2]);
            } elseif (preg_match('/Version:\s*(.+)/i', $title, $vm) === 1) {
                $version = trim($vm[1]);
            }
            $softpaq = 'SP' . $m[1];
            $fileName = 'sp' . $m[1] . '.exe';

            return [
                'download_url' => $href,
                'file_name' => $fileName,
                'softpaq' => $softpaq,
                'version' => $version,
                'release_date' => $date,
            ];
        }

        return null;
    }

    private function isSectionHeading(\DOMElement $td, string $text): bool
    {
        $id = strtolower($td->getAttribute('id'));
        $colspan = $td->getAttribute('colspan');
        if ($id === 'product-group-cell' || $colspan !== '') {
            return true;
        }
        $plain = strtolower($text);

        return in_array($plain, ['notebooks & tablets', 'desktops', 'workstations'], true);
    }

    /**
     * @param list<string> $headers
     */
    private function headerLabel(array $headers, int $i): string
    {
        return $headers[$i] ?? '';
    }

    private function normalizeModel(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = str_replace(['’', '`'], "'", $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/^hp\s+/u', '', $s) ?? $s;
        $s = preg_replace('/\b(notebook ai pc|notebook pc|mobile workstation pc|workstation pc|desktop pc|all-in-one|aio)\b/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s, " \t-");
    }

    private function modelScore(string $query, string $candidate, string $original): int
    {
        if ($candidate === '' || $query === '') {
            return 0;
        }
        if ($query === $candidate) {
            return 100;
        }
        if (str_starts_with($candidate, $query) || str_starts_with($query, $candidate)) {
            return 85;
        }
        if (str_contains($candidate, $query)) {
            $bonus = max(0, 30 - mb_strlen($candidate));

            return 70 + $bonus;
        }
        $qTokens = preg_split('/\s+/', $query) ?: [];
        $cTokens = preg_split('/\s+/', $candidate) ?: [];
        if ($qTokens === [] || $cTokens === []) {
            return 0;
        }
        $required = 0;
        $hit = 0;
        foreach ($qTokens as $tok) {
            if (mb_strlen($tok) < 2 && !ctype_digit($tok)) {
                continue;
            }
            $required++;
            foreach ($cTokens as $ct) {
                if (ctype_digit($tok)) {
                    if ($tok === $ct) {
                        $hit++;
                        break;
                    }
                    continue;
                }
                if ($tok === $ct || str_contains($ct, $tok)) {
                    $hit++;
                    break;
                }
            }
        }
        if ($required >= 2 && $hit === $required) {
            return 60;
        }

        unset($original);

        return 0;
    }

    /**
     * @param array<string, array<string, string>> $packs
     */
    private function packIdentity(array $packs): string
    {
        $ids = [];
        foreach ($packs as $slug => $pack) {
            $ids[] = $slug . ':' . ($pack['download_url'] ?? '');
        }

        return implode('|', $ids);
    }

    /**
     * @return array{ok: bool, error?: string, body?: string}
     */
    private function loadHtml(string $url): array
    {
        $cached = $this->readCache($url);
        if ($cached !== null) {
            return ['ok' => true, 'body' => $cached];
        }
        $fetched = $this->fetchHtml($url);
        if (!empty($fetched['ok']) && isset($fetched['body'])) {
            $this->writeCache($url, (string) $fetched['body']);
        }

        return $fetched;
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
            return ['ok' => false, 'error' => $this->msg('driver.err_hp_catalog')];
        }
        if ($ok === false || $status >= 400 || $status === 0) {
            $hint = $status > 0 ? ('HTTP ' . $status) : $err;

            return ['ok' => false, 'error' => $this->msg('driver.err_hp_catalog') . ' (' . $hint . ')'];
        }

        return ['ok' => true, 'body' => $body];
    }

    private function readCache(string $url): ?string
    {
        $file = $this->cacheFile($url);
        if ($file === null || !is_file($file)) {
            return null;
        }
        if ((time() - (int) filemtime($file)) > self::CACHE_TTL) {
            return null;
        }
        $body = file_get_contents($file);

        return is_string($body) && $body !== '' ? $body : null;
    }

    private function writeCache(string $url, string $body): void
    {
        $file = $this->cacheFile($url);
        if ($file === null || $body === '') {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, $body);
    }

    private function cacheFile(string $url): ?string
    {
        if ($this->cacheDir === null || $this->cacheDir === '') {
            return null;
        }

        return rtrim($this->cacheDir, '/') . '/hp-matrix-' . hash('sha256', $url) . '.html';
    }

    private function stripQuery(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }
        $out = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $out .= ':' . $parts['port'];
        }
        $out .= $parts['path'] ?? '';

        return $out;
    }

    private function osFromCatalogUrl(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }
        parse_str($query, $qs);
        $os = isset($qs['os']) ? trim((string) $qs['os']) : '';

        return $os;
    }

    private function nodeText(\DOMNode $node): string
    {
        return html_entity_decode(trim($node->textContent ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string, string> $replace
     */
    private function msg(string $key, array $replace = []): string
    {
        try {
            return __($key, $replace);
        } catch (\Throwable) {
            return $key;
        }
    }
}
