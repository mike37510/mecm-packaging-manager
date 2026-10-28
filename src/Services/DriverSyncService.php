<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\DriverRepository;
use App\Repositories\DriverSyncRepository;

/**
 * Probes a vendor driver URL (headers / checksum sidecar) without downloading the pack.
 * SHA-256 is verified later by deploy-drivers.ps1 on the MECM console host.
 */
final class DriverSyncService
{
    private const USER_AGENT = 'Mozilla/5.0 MECM-Packaging-Manager-Drivers/1.0';
    private const RANGE_BYTES = 511;
    private const SIDECAR_MAX = 16384;

    public function __construct(
        private readonly DriverRepository $drivers,
        private readonly DriverSyncRepository $syncs,
        private readonly AuditLogRepository $auditLogs,
        private readonly DellDriverPackResolver $dellPacks,
        private readonly HpDriverPackResolver $hpPacks,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   status?: string,
     *   message?: string,
     *   sha256?: string,
     *   previous_sha256?: ?string,
     *   file_name?: string,
     *   file_size?: int,
     *   changed?: bool,
     *   version?: ?string
     * }
     */
    public function check(int $driverId, ?int $userId, ?string $ip): array
    {
        @set_time_limit(60);
        $driver = $this->drivers->findById($driverId);
        if ($driver === null) {
            return ['ok' => false, 'error' => __('flash.driver_not_found')];
        }
        if (!$driver->hasDownloadUrl()) {
            return ['ok' => false, 'error' => __('driver.err_url')];
        }

        $packUrl = $driver->downloadUrl;
        $catalogMeta = null;
        $catalogKind = null;
        $catalogSource = $driver->catalogUrl ?: $driver->downloadUrl;
        if (DellDriverPackResolver::isCatalogUrl($catalogSource)) {
            $resolved = $this->dellPacks->resolve($catalogSource);
            if (empty($resolved['ok'])) {
                $this->recordError($driverId, $catalogSource, $driver->lastSyncSha256, (string) ($resolved['error'] ?? 'dell'), $userId, $ip);

                return ['ok' => false, 'error' => $resolved['error'] ?? __('driver.err_dell_catalog')];
            }
            $packUrl = (string) $resolved['download_url'];
            $catalogMeta = $resolved;
            $catalogKind = 'dell';
            $this->applyCatalogPatch($driverId, $catalogSource, $packUrl, $resolved, $driver);
            $driver = $this->drivers->findById($driverId) ?? $driver;
        } elseif (HpDriverPackResolver::isCatalogUrl($catalogSource)) {
            $resolved = $this->hpPacks->resolve($catalogSource, $driver->model, $driver->osTarget);
            if (empty($resolved['ok'])) {
                $this->recordError($driverId, $catalogSource, $driver->lastSyncSha256, (string) ($resolved['error'] ?? 'hp'), $userId, $ip);

                return ['ok' => false, 'error' => $resolved['error'] ?? __('driver.err_hp_catalog')];
            }
            $packUrl = (string) $resolved['download_url'];
            $catalogMeta = $resolved;
            $catalogKind = 'hp';
            $this->applyCatalogPatch($driverId, $catalogSource, $packUrl, $resolved, $driver);
            $driver = $this->drivers->findById($driverId) ?? $driver;
        }

        $probe = $this->probeUrl($packUrl);
        if (empty($probe['ok'])) {
            $this->recordError($driverId, $catalogSource, $driver->lastSyncSha256, (string) ($probe['error'] ?? 'probe'), $userId, $ip);

            return ['ok' => false, 'error' => $probe['error'] ?? __('flash.driver_sync_failed')];
        }

        $sha = $this->resolveSha256($probe, $driver->expectedSha256);
        $fileName = $this->guessFilename(
            (string) ($probe['final_url'] ?? $packUrl),
            $probe['content_disposition'] ?? null
        );
        if (is_array($catalogMeta) && !empty($catalogMeta['file_name'])) {
            $fileName = (string) $catalogMeta['file_name'];
        }
        $size = isset($probe['size']) ? (int) $probe['size'] : 0;
        $packVersion = is_array($catalogMeta) ? trim((string) ($catalogMeta['version'] ?? '')) : '';
        if ($packVersion === '') {
            $packVersion = $this->guessPackVersion($fileName) ?? '';
        }
        if ($catalogKind === null && preg_match('/^sp\d+/i', $fileName) === 1) {
            $catalogKind = 'hp';
        }
        $fingerprint = $this->packFingerprint($catalogKind, $catalogMeta, $packVersion, $packUrl, $sha, $size, $probe, $fileName);
        $previous = $driver->lastSyncSha256;
        $packagedFp = $driver->lastPackagedFingerprint;
        $neverPackaged = $packagedFp === null || $packagedFp === '';
        $changed = $neverPackaged || !hash_equals($packagedFp, $fingerprint);
        $status = $changed ? 'update_available' : 'up_to_date';
        $message = $this->syncMessage($changed, $neverPackaged, $packVersion, $fileName, $catalogKind);

        $syncPatch = [
            'last_sync_sha256' => $sha !== '' ? $sha : null,
            'last_sync_fingerprint' => $fingerprint,
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_filename' => $fileName,
            'last_sync_size' => $size > 0 ? $size : null,
            'last_sync_error' => null,
            'sync_status' => $status,
        ];
        if ($packVersion !== '') {
            $syncPatch['version'] = $packVersion;
        }
        $this->drivers->patch($driverId, $syncPatch);

        $this->syncs->create([
            'driver_id' => $driverId,
            'source_url' => DriverService::isVendorCatalogUrl($catalogSource) ? $catalogSource : $packUrl,
            'file_name' => $fileName,
            'sha256' => $sha !== '' ? $sha : null,
            'file_size' => $size,
            'previous_sha256' => $previous,
            'status' => $status,
            'message' => $message,
        ]);

        if ($changed) {
            $this->auditLogs->log($userId, 'driver.sync_update_available', 'driver', $driverId, [
                'sha256' => $sha !== '' ? $sha : null,
                'previous_sha256' => $previous,
                'file_name' => $fileName,
                'file_size' => $size,
                'downloaded' => false,
            ], $ip);
        } else {
            $this->auditLogs->log($userId, 'driver.sync_up_to_date', 'driver', $driverId, [
                'sha256' => $sha !== '' ? $sha : null,
                'file_name' => $fileName,
                'downloaded' => false,
            ], $ip);
        }

        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
            'sha256' => $sha,
            'previous_sha256' => $previous,
            'file_name' => $fileName,
            'file_size' => $size,
            'changed' => $changed,
            'version' => $packVersion !== '' ? $packVersion : ($driver->version !== '' ? $driver->version : null),
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function applyCatalogPatch(
        int $driverId,
        string $catalogSource,
        string $packUrl,
        array $resolved,
        \App\Models\Driver $driver,
    ): void {
        $patchCatalog = [
            'catalog_url' => $catalogSource,
            'download_url' => $packUrl,
        ];
        $newVersion = trim((string) ($resolved['version'] ?? ''));
        if ($newVersion !== '') {
            $patchCatalog['version'] = $newVersion;
        }
        $newModel = trim((string) ($resolved['model'] ?? ''));
        if ($newModel !== '' && $driver->model === '') {
            $patchCatalog['model'] = $newModel;
        }
        $this->drivers->patch($driverId, $patchCatalog);
    }

    /**
     * @param array<string, mixed>|null $catalogMeta
     * @param array<string, mixed> $probe
     */
    private function packFingerprint(
        ?string $catalogKind,
        ?array $catalogMeta,
        string $packVersion,
        string $packUrl,
        ?string $sha,
        int $size,
        array $probe,
        string $fileName,
    ): string {
        if ($catalogKind === 'hp' && is_array($catalogMeta)) {
            $softpaq = strtolower(trim((string) ($catalogMeta['softpaq'] ?? '')));
            $ver = strtolower(trim((string) ($catalogMeta['pack_version'] ?? $packVersion)));
            if ($softpaq !== '' || $ver !== '') {
                return 'packver:' . $softpaq . ':' . $ver . ':' . strtolower($packUrl);
            }
        }
        if ($packVersion !== '') {
            return 'packver:' . strtolower($packVersion) . ':' . strtolower($packUrl);
        }

        return $this->fingerprint($sha, $size, $probe, $fileName);
    }

    private function guessPackVersion(string $fileName): ?string
    {
        if (preg_match('/_A(\d+)\.(exe|cab|zip)$/i', $fileName, $m) === 1) {
            return 'A' . $m[1];
        }
        if (preg_match('/\bsp(\d+)\b/i', $fileName, $m) === 1) {
            return 'SP' . $m[1];
        }

        return null;
    }

    private function syncMessage(bool $changed, bool $neverPackaged, string $packVersion, string $fileName, ?string $catalogKind = null): string
    {
        if ($packVersion !== '') {
            $prefix = $catalogKind === 'hp' ? 'hp' : ($catalogKind === 'dell' ? 'dell' : 'dell');
            if (!$changed) {
                return __('driver.sync_' . $prefix . '_same', [
                    'version' => $packVersion,
                    'file' => $fileName,
                ]);
            }
            if ($neverPackaged) {
                return __('driver.sync_' . $prefix . '_found', [
                    'version' => $packVersion,
                    'file' => $fileName,
                ]);
            }

            return __('driver.sync_' . $prefix . '_new', [
                'version' => $packVersion,
                'file' => $fileName,
            ]);
        }

        if (!$changed) {
            return __('driver.sync_same');
        }

        return $neverPackaged ? __('driver.sync_found') : __('driver.sync_changed');
    }

    private function recordError(
        int $driverId,
        string $url,
        ?string $previousSha,
        string $error,
        ?int $userId,
        ?string $ip,
    ): void {
        $this->drivers->patch($driverId, [
            'sync_status' => 'error',
            'last_sync_error' => $error,
            'last_sync_at' => date('Y-m-d H:i:s'),
        ]);
        $this->syncs->create([
            'driver_id' => $driverId,
            'source_url' => $url,
            'file_name' => 'unknown',
            'sha256' => null,
            'file_size' => 0,
            'previous_sha256' => $previousSha,
            'status' => 'error',
            'message' => $error,
        ]);
        $this->auditLogs->log($userId, 'driver.sync_error', 'driver', $driverId, [
            'error' => $error,
        ], $ip);
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   size?: int,
     *   final_url?: string,
     *   content_disposition?: ?string,
     *   etag?: ?string,
     *   last_modified?: ?string,
     *   content_type?: ?string,
     *   sha256?: ?string
     * }
     */
    private function probeUrl(string $url): array
    {
        $head = $this->http($url, true, null, 0);
        $meta = $head;
        if (empty($head['ok']) || $this->needsRangeFallback($head)) {
            $range = $this->http($url, false, '0-' . self::RANGE_BYTES, self::RANGE_BYTES + 1);
            if (empty($range['ok'])) {
                return ['ok' => false, 'error' => $range['error'] ?? $head['error'] ?? __('flash.driver_sync_failed')];
            }
            $meta = $this->mergeProbe($head, $range);
            if ($this->looksLikeHtml($meta['content_type'] ?? null, $range['snippet'] ?? '')) {
                return ['ok' => false, 'error' => __('driver.err_html')];
            }
        } elseif ($this->looksLikeHtml($head['content_type'] ?? null, '')) {
            return ['ok' => false, 'error' => __('driver.err_html')];
        }

        $finalUrl = (string) ($meta['final_url'] ?? $url);
        $sidecarSha = $this->fetchSidecarSha256($finalUrl);
        if ($sidecarSha !== null) {
            $meta['sha256'] = $sidecarSha;
        }

        $meta['ok'] = true;

        return $meta;
    }

    /**
     * @param array<string, mixed> $head
     */
    private function needsRangeFallback(array $head): bool
    {
        if (empty($head['ok'])) {
            return true;
        }
        $type = strtolower((string) ($head['content_type'] ?? ''));
        if ($type === '' || str_starts_with($type, 'text/html')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $head
     * @param array<string, mixed> $range
     * @return array<string, mixed>
     */
    private function mergeProbe(array $head, array $range): array
    {
        $out = $range;
        foreach (['size', 'content_disposition', 'etag', 'last_modified', 'content_type', 'sha256', 'final_url'] as $key) {
            if ((!isset($out[$key]) || $out[$key] === null || $out[$key] === '' || $out[$key] === 0)
                && isset($head[$key]) && $head[$key] !== null && $head[$key] !== ''
            ) {
                $out[$key] = $head[$key];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $probe
     */
    private function resolveSha256(array $probe, ?string $expectedFromForm): string
    {
        foreach ([$probe['sha256'] ?? null, $expectedFromForm] as $candidate) {
            $sha = $this->normalizeSha256($candidate);
            if ($sha !== null) {
                return $sha;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $probe
     */
    private function fingerprint(?string $sha, int $size, array $probe, string $fileName): string
    {
        if (is_string($sha) && $sha !== '') {
            return 'sha256:' . strtolower($sha);
        }

        return 'meta:' . hash('sha256', implode('|', [
            (string) $size,
            strtolower((string) ($probe['etag'] ?? '')),
            (string) ($probe['last_modified'] ?? ''),
            $fileName,
            (string) ($probe['final_url'] ?? ''),
        ]));
    }

    private function fetchSidecarSha256(string $fileUrl): ?string
    {
        $candidates = [];
        $candidates[] = $fileUrl . '.sha256';
        $candidates[] = $fileUrl . '.sha256.txt';
        $candidates[] = $fileUrl . '.sha256sum';
        $path = parse_url($fileUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '' && str_contains(basename($path), '.')) {
            $base = preg_replace('/\.[^.]+$/', '', $fileUrl) ?? $fileUrl;
            $candidates[] = $base . '.sha256';
        }

        foreach (array_unique($candidates) as $sidecar) {
            $res = $this->http($sidecar, false, null, self::SIDECAR_MAX);
            if (empty($res['ok'])) {
                continue;
            }
            $sha = $this->parseChecksumBody((string) ($res['body'] ?? ''));
            if ($sha !== null) {
                return $sha;
            }
        }

        return null;
    }

    private function parseChecksumBody(string $body): ?string
    {
        $body = trim($body);
        if ($body === '' || strlen($body) > self::SIDECAR_MAX) {
            return null;
        }
        if (preg_match('/\b([a-f0-9]{64})\b/i', $body, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function normalizeSha256(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $sha = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            return null;
        }
        if ($sha === str_repeat('0', 64)) {
            return null;
        }

        return $sha;
    }

    private function looksLikeHtml(?string $contentType, string $snippet): bool
    {
        $type = strtolower((string) $contentType);
        if (str_contains($type, 'text/html') || str_contains($type, 'application/xhtml')) {
            return true;
        }

        return preg_match('/^\s*<(!DOCTYPE|html)/i', $snippet) === 1;
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   status?: int,
     *   size?: int,
     *   final_url?: string,
     *   content_disposition?: ?string,
     *   etag?: ?string,
     *   last_modified?: ?string,
     *   content_type?: ?string,
     *   sha256?: ?string,
     *   body?: string,
     *   snippet?: string
     * }
     */
    private function http(string $url, bool $headOnly, ?string $range, int $maxBody): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'cURL init failed'];
        }

        $headers = [
            'content_disposition' => null,
            'etag' => null,
            'last_modified' => null,
            'content_type' => null,
            'sha256' => null,
            'content_length' => null,
        ];
        $body = '';
        $tooLarge = false;

        $opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: */*'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$headers): int {
                $this->captureHeader($header, $headers);

                return strlen($header);
            },
        ];

        if ($headOnly) {
            $opts[CURLOPT_NOBODY] = true;
            $opts[CURLOPT_RETURNTRANSFER] = true;
        } else {
            $opts[CURLOPT_RETURNTRANSFER] = false;
            if ($range !== null) {
                $opts[CURLOPT_RANGE] = $range;
            }
            $opts[CURLOPT_WRITEFUNCTION] = static function ($ch, string $data) use (&$body, &$tooLarge, $maxBody): int {
                $body .= $data;
                if ($maxBody > 0 && strlen($body) > $maxBody) {
                    $tooLarge = true;

                    return 0;
                }

                return strlen($data);
            };
        }

        curl_setopt_array($ch, $opts);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $lengthDownload = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        $size = 0;
        if (is_numeric($headers['content_length'])) {
            $size = (int) $headers['content_length'];
        } elseif ($lengthDownload > 0) {
            $size = $lengthDownload;
        }

        if ($tooLarge) {
            if ($this->looksLikeHtml($headers['content_type'], '')) {
                return ['ok' => false, 'error' => __('driver.err_html')];
            }

            return [
                'ok' => true,
                'status' => $status,
                'size' => $size,
                'final_url' => is_string($finalUrl) ? $finalUrl : $url,
                'content_disposition' => $headers['content_disposition'],
                'etag' => $headers['etag'],
                'last_modified' => $headers['last_modified'],
                'content_type' => $headers['content_type'],
                'sha256' => $headers['sha256'],
                'body' => '',
                'snippet' => '',
            ];
        }
        if ($ok === false || $errno !== 0) {
            return ['ok' => false, 'error' => 'cURL : ' . ($error !== '' ? $error : 'échec')];
        }
        if ($status >= 400) {
            return ['ok' => false, 'status' => $status, 'error' => 'HTTP ' . $status];
        }

        return [
            'ok' => true,
            'status' => $status,
            'size' => $size,
            'final_url' => is_string($finalUrl) ? $finalUrl : $url,
            'content_disposition' => $headers['content_disposition'],
            'etag' => $headers['etag'],
            'last_modified' => $headers['last_modified'],
            'content_type' => $headers['content_type'],
            'sha256' => $headers['sha256'],
            'body' => $headOnly ? '' : $body,
            'snippet' => $headOnly ? '' : substr($body, 0, 256),
        ];
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function captureHeader(string $header, array &$headers): void
    {
        if (preg_match('/^Content-Disposition:\s*(.+)$/i', $header, $m) === 1) {
            $headers['content_disposition'] = trim($m[1]);
        } elseif (preg_match('/^Content-Type:\s*([^;]+)/i', $header, $m) === 1) {
            $headers['content_type'] = trim($m[1]);
        } elseif (preg_match('/^Content-Length:\s*(\d+)/i', $header, $m) === 1) {
            $headers['content_length'] = (int) $m[1];
        } elseif (preg_match('/^ETag:\s*(.+)$/i', $header, $m) === 1) {
            $headers['etag'] = trim($m[1]);
        } elseif (preg_match('/^Last-Modified:\s*(.+)$/i', $header, $m) === 1) {
            $headers['last_modified'] = trim($m[1]);
        } elseif (preg_match('/^Digest:\s*(.+)$/i', $header, $m) === 1) {
            $parsed = $this->shaFromDigestHeader(trim($m[1]));
            if ($parsed !== null) {
                $headers['sha256'] = $parsed;
            }
        } elseif (preg_match('/^(?:X-Checksum-SHA256|x-amz-meta-sha256):\s*([a-f0-9]{64})\s*$/i', $header, $m) === 1) {
            $headers['sha256'] = strtolower($m[1]);
        }
    }

    private function shaFromDigestHeader(string $digest): ?string
    {
        if (preg_match('/sha-256=([^,\s]+)/i', $digest, $m) !== 1) {
            return null;
        }
        $raw = trim($m[1], '"');
        if (preg_match('/^[a-f0-9]{64}$/i', $raw) === 1) {
            return strtolower($raw);
        }
        $bin = base64_decode($raw, true);
        if ($bin !== false && strlen($bin) === 32) {
            return bin2hex($bin);
        }

        return null;
    }

    private function guessFilename(string $url, ?string $disposition): string
    {
        if (is_string($disposition) && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m) === 1) {
            $name = basename(rawurldecode(trim($m[1], " \t\"'")));
            if ($name !== '') {
                return $name;
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        $base = is_string($path) ? basename($path) : '';
        if ($base !== '' && $base !== '/' && str_contains($base, '.')) {
            return $base;
        }

        return 'driver-pack.bin';
    }
}
