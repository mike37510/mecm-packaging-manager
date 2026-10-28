<?php

declare(strict_types=1);

namespace App\Services;

/**
 * VirusTotal v3 URL (and optional file hash) lookup for installer sources.
 *
 * @see https://docs.virustotal.com/reference/scan-url
 */
final class VirusTotalService
{
    private const API = 'https://www.virustotal.com/api/v3';
    private const GUI_URL = 'https://www.virustotal.com/gui/url/';

    public function __construct(
        private readonly VirusTotalConfigService $config,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @return array{ok: bool, error?: string, message?: string}
     */
    public function ping(): array
    {
        $key = $this->config->apiKey();
        if ($key === '') {
            return ['ok' => false, 'error' => 'Clé API VirusTotal manquante. Enregistrez-la d’abord.'];
        }

        $res = $this->request('GET', '/users/' . rawurlencode($key));
        if (empty($res['ok'])) {
            return ['ok' => false, 'error' => $res['error'] ?? 'API VirusTotal injoignable.'];
        }

        $id = (string) ($res['json']['data']['id'] ?? '');
        if ($id !== '' && preg_match('/^[a-f0-9]{64}$/i', $id) !== 1) {
            return ['ok' => true, 'message' => 'API VirusTotal OK (' . $id . ')'];
        }

        return ['ok' => true, 'message' => 'API VirusTotal OK'];
    }

    /**
     * Scan an installer download URL. Optionally enrich with an existing file SHA-256 report.
     *
     * @return array{
     *   ok: bool,
     *   skipped?: bool,
     *   error?: string,
     *   malicious?: int,
     *   suspicious?: int,
     *   harmless?: int,
     *   undetected?: int,
     *   message?: string,
     *   permalink?: string
     * }
     */
    public function scanInstallerUrl(string $url, ?string $sha256 = null): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => true, 'skipped' => true, 'message' => 'VirusTotal désactivé'];
        }

        $url = trim($url);
        $hasUrl = $url !== '' && preg_match('#^https?://#i', $url) === 1;
        $hasSha = is_string($sha256) && preg_match('/^[a-f0-9]{64}$/i', $sha256) === 1;
        if (!$hasUrl && !$hasSha) {
            return ['ok' => false, 'error' => 'URL d’installateur absente ou invalide pour VirusTotal.'];
        }

        $malicious = 0;
        $suspicious = 0;
        $harmless = 0;
        $undetected = 0;
        $permalink = '';

        $urlMalicious = 0;
        $urlSuspicious = 0;
        $fileMalicious = 0;
        $fileSuspicious = 0;
        $engines = [];

        if ($hasUrl) {
            $urlReport = $this->analyzeUrl($url);
            if (empty($urlReport['ok'])) {
                return $urlReport;
            }
            $urlMalicious = (int) ($urlReport['malicious'] ?? 0);
            $urlSuspicious = (int) ($urlReport['suspicious'] ?? 0);
            $malicious = $urlMalicious;
            $suspicious = $urlSuspicious;
            $harmless = (int) ($urlReport['harmless'] ?? 0);
            $undetected = (int) ($urlReport['undetected'] ?? 0);
            $permalink = (string) ($urlReport['permalink'] ?? self::GUI_URL . $this->urlId($url));
            $engines = array_merge($engines, $urlReport['engines'] ?? []);
        }

        if ($hasSha) {
            $file = $this->lookupFile($sha256);
            if (!empty($file['ok'])) {
                $fileMalicious = (int) ($file['malicious'] ?? 0);
                $fileSuspicious = (int) ($file['suspicious'] ?? 0);
                $malicious = max($malicious, $fileMalicious);
                $suspicious = max($suspicious, $fileSuspicious);
                $harmless += (int) ($file['harmless'] ?? 0);
                $undetected += (int) ($file['undetected'] ?? 0);
                $engines = array_merge($engines, $file['engines'] ?? []);
                if ($fileMalicious > 0 || $permalink === '') {
                    $permalink = 'https://www.virustotal.com/gui/file/' . strtolower($sha256);
                }
            } elseif (!$hasUrl) {
                return ['ok' => false, 'error' => 'Empreinte SHA-256 inconnue de VirusTotal et pas d’URL à analyser.'];
            }
        }

        $engines = $this->uniqueEngines($engines);

        $summary = sprintf(
            'VirusTotal : %d malveillant / %d suspect / %d inoffensif / %d non détecté',
            $malicious,
            $suspicious,
            $harmless,
            $undetected
        );

        $base = [
            'ok' => true,
            'malicious' => $malicious,
            'suspicious' => $suspicious,
            'harmless' => $harmless,
            'undetected' => $undetected,
            'url_malicious' => $urlMalicious,
            'file_malicious' => $fileMalicious,
            'url_suspicious' => $urlSuspicious,
            'file_suspicious' => $fileSuspicious,
            'engines' => $engines,
            'permalink' => $permalink,
            'message' => $summary,
        ];

        if ($malicious > 0) {
            $base['needs_review'] = true;
            $base['hint'] = $this->reviewHint($urlMalicious, $fileMalicious, $engines);
            $base['message'] = $summary . ' — décision opérateur requise.';

            return $base;
        }

        if ($suspicious > 0) {
            $base['message'] = $summary . ' (suspect : continuer).';
        }

        return $base;
    }

    /**
     * @return array{ok: bool, error?: string, malicious?: int, suspicious?: int, harmless?: int, undetected?: int, permalink?: string}
     */
    private function analyzeUrl(string $url): array
    {
        $id = $this->urlId($url);
        $existing = $this->request('GET', '/urls/' . $id);
        if (!empty($existing['ok']) && isset($existing['json']['data']['attributes']['last_analysis_stats'])) {
            return $this->statsFromAttributes($existing['json']['data']['attributes'], $id);
        }
        if (empty($existing['ok']) && (int) ($existing['status'] ?? 0) !== 404) {
            return ['ok' => false, 'error' => $existing['error'] ?? 'Lecture VirusTotal impossible.'];
        }

        $submit = $this->request('POST', '/urls', ['url' => $url], 'application/x-www-form-urlencoded');
        if (empty($submit['ok'])) {
            return ['ok' => false, 'error' => $submit['error'] ?? 'Soumission VirusTotal impossible.'];
        }

        $analysisId = (string) ($submit['json']['data']['id'] ?? '');
        if ($analysisId === '') {
            return ['ok' => false, 'error' => 'VirusTotal n’a pas renvoyé d’identifiant d’analyse.'];
        }

        $analysis = $this->pollAnalysis($analysisId);
        if (empty($analysis['ok'])) {
            return $analysis;
        }

        $fresh = $this->request('GET', '/urls/' . $id);
        if (!empty($fresh['ok']) && isset($fresh['json']['data']['attributes']['last_analysis_stats'])) {
            return $this->statsFromAttributes($fresh['json']['data']['attributes'], $id);
        }

        $stats = $analysis['json']['data']['attributes']['stats'] ?? null;
        if (!is_array($stats)) {
            return ['ok' => false, 'error' => 'Rapport VirusTotal illisible.'];
        }

        return [
            'ok' => true,
            'malicious' => (int) ($stats['malicious'] ?? 0),
            'suspicious' => (int) ($stats['suspicious'] ?? 0),
            'harmless' => (int) ($stats['harmless'] ?? 0),
            'undetected' => (int) ($stats['undetected'] ?? 0),
            'permalink' => self::GUI_URL . $id,
            'engines' => $this->flaggedEngines($analysis['json']['data']['attributes'] ?? []),
        ];
    }

    /**
     * @return array{ok: bool, malicious?: int, suspicious?: int, harmless?: int, undetected?: int}
     */
    private function lookupFile(string $sha256): array
    {
        $res = $this->request('GET', '/files/' . strtolower($sha256));
        if (empty($res['ok'])) {
            return ['ok' => false];
        }
        $stats = $res['json']['data']['attributes']['last_analysis_stats'] ?? null;
        if (!is_array($stats)) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'malicious' => (int) ($stats['malicious'] ?? 0),
            'suspicious' => (int) ($stats['suspicious'] ?? 0),
            'harmless' => (int) ($stats['harmless'] ?? 0),
            'undetected' => (int) ($stats['undetected'] ?? 0),
            'engines' => $this->flaggedEngines($res['json']['data']['attributes'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{ok: true, malicious: int, suspicious: int, harmless: int, undetected: int, permalink: string}
     */
    private function statsFromAttributes(array $attributes, string $urlId): array
    {
        $stats = is_array($attributes['last_analysis_stats'] ?? null) ? $attributes['last_analysis_stats'] : [];

        return [
            'ok' => true,
            'malicious' => (int) ($stats['malicious'] ?? 0),
            'suspicious' => (int) ($stats['suspicious'] ?? 0),
            'harmless' => (int) ($stats['harmless'] ?? 0),
            'undetected' => (int) ($stats['undetected'] ?? 0),
            'permalink' => self::GUI_URL . $urlId,
            'engines' => $this->flaggedEngines($attributes),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<array{engine: string, category: string, result: string}>
     */
    private function flaggedEngines(array $attributes): array
    {
        $results = is_array($attributes['last_analysis_results'] ?? null)
            ? $attributes['last_analysis_results']
            : [];
        $out = [];
        foreach ($results as $engine => $row) {
            if (!is_array($row)) {
                continue;
            }
            $cat = (string) ($row['category'] ?? '');
            if (!in_array($cat, ['malicious', 'suspicious'], true)) {
                continue;
            }
            $out[] = [
                'engine' => (string) $engine,
                'category' => $cat,
                'result' => (string) ($row['result'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{engine: string, category: string, result: string}> $engines
     * @return list<array{engine: string, category: string, result: string}>
     */
    private function uniqueEngines(array $engines): array
    {
        $seen = [];
        $out = [];
        foreach ($engines as $row) {
            $key = strtolower($row['engine'] . '|' . $row['result']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array{engine: string, category: string, result: string}> $engines
     */
    private function reviewHint(int $urlMalicious, int $fileMalicious, array $engines): string
    {
        if ($urlMalicious === 0 && $fileMalicious > 0 && $fileMalicious <= 3) {
            return 'false_positive';
        }
        if ($fileMalicious >= 8 || $urlMalicious >= 5) {
            return 'malware';
        }
        $heuristic = 0;
        foreach ($engines as $row) {
            $blob = strtolower($row['engine'] . ' ' . $row['result']);
            if (preg_match('/heur|generic|nametrick|pua|riskware|trojan$/i', $blob) === 1) {
                $heuristic++;
            }
        }
        if ($urlMalicious === 0 && $heuristic === count($engines) && $engines !== []) {
            return 'false_positive';
        }

        return 'unclear';
    }

    private function pollAnalysis(string $analysisId): array
    {
        $deadline = time() + 90;
        $last = ['ok' => false, 'error' => 'Timeout VirusTotal (analyse URL).'];
        while (time() < $deadline) {
            sleep(4);
            $last = $this->request('GET', '/analyses/' . rawurlencode($analysisId));
            if (empty($last['ok'])) {
                continue;
            }
            $status = (string) ($last['json']['data']['attributes']['status'] ?? '');
            if ($status === 'completed') {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @param array<string, string> $form
     * @return array{ok: bool, error?: string, status?: int, json?: array<string, mixed>}
     */
    private function request(string $method, string $path, array $form = [], ?string $contentType = null): array
    {
        $key = $this->config->apiKey();
        $ch = curl_init(self::API . $path);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'cURL init VirusTotal impossible.'];
        }

        $headers = [
            'x-apikey: ' . $key,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($contentType === 'application/x-www-form-urlencoded') {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $opts[CURLOPT_HTTPHEADER] = $headers;
                $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
            }
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            return ['ok' => false, 'error' => 'VirusTotal : ' . ($error !== '' ? $error : 'échec réseau')];
        }

        $json = json_decode((string) $body, true);
        if ($status === 404) {
            return ['ok' => false, 'status' => 404, 'error' => 'URL inconnue de VirusTotal.', 'json' => is_array($json) ? $json : []];
        }
        if ($status === 429) {
            return ['ok' => false, 'status' => 429, 'error' => 'Quota VirusTotal dépassé (réessayez plus tard).'];
        }
        if ($status === 401 || $status === 403) {
            return ['ok' => false, 'status' => $status, 'error' => 'Clé API VirusTotal refusée.'];
        }
        if ($status >= 400) {
            $apiError = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';

            return ['ok' => false, 'status' => $status, 'error' => $apiError !== '' ? $apiError : ('HTTP ' . $status)];
        }

        return [
            'ok' => true,
            'status' => $status,
            'json' => is_array($json) ? $json : [],
        ];
    }

    private function urlId(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }
}
