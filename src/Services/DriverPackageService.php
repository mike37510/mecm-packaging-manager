<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Repositories\AuditLogRepository;
use App\Repositories\DriverRepository;
use ZipArchive;

/**
 * Builds a thin ZIP: driver.json + deploy-drivers.ps1 (download at deploy time, import into MECM).
 */
final class DriverPackageService
{
    public function __construct(
        private readonly DriverRepository $drivers,
        private readonly MecmConfigService $mecmConfig,
        private readonly AuditLogRepository $auditLogs,
        private readonly string $exportPath,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, filename?: string, path?: string}
     */
    public function generate(int $driverId, ?int $userId, ?string $ip): array
    {
        $driver = $this->drivers->findById($driverId);
        if ($driver === null) {
            return ['ok' => false, 'error' => __('flash.driver_not_found')];
        }
        if (!$driver->hasDownloadUrl()) {
            return ['ok' => false, 'error' => __('driver.err_url')];
        }
        if ($driver->lastSyncAt === null || $driver->lastSyncAt === '') {
            return ['ok' => false, 'error' => __('driver.err_sync_first')];
        }

        $mecm = $this->mecmConfig->get();
        $dir = rtrim($this->exportPath, '/') . '/drivers/' . $driverId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => __('driver.err_export_dir')];
        }

        $stamp = date('Ymd_His');
        $slug = $this->slug($driver->manufacturer . '_' . $driver->name . '_' . $driver->version);
        $filename = $slug . '_' . $stamp . '.zip';
        $path = $dir . '/' . $filename;

        $manifest = $this->manifest($driver, $mecm);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'JSON encode failed'];
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => __('driver.err_zip')];
        }

        $prefix = $slug . '/';
        $zip->addFromString($prefix . 'driver.json', $json . "\n");
        $zip->addFromString($prefix . 'deploy-drivers.ps1', $this->buildDeployPs1($manifest));
        $zip->addFromString($prefix . 'README.txt', $this->buildReadme($driver));
        $zip->close();
        @chmod($path, 0664);

        if ($driver->lastExportPath && $driver->lastExportPath !== $path && is_file($driver->lastExportPath)) {
            @unlink($driver->lastExportPath);
        }

        $fresh = $this->drivers->findById($driverId) ?? $driver;
        $this->drivers->patch($driverId, [
            'last_packaged_sha256' => $fresh->lastSyncSha256,
            'last_packaged_fingerprint' => $fresh->lastSyncFingerprint,
            'last_packaged_at' => date('Y-m-d H:i:s'),
            'last_export_path' => $path,
            'last_export_filename' => $filename,
            'sync_status' => 'up_to_date',
        ]);

        $this->auditLogs->log($userId, 'driver.zip_exported', 'driver', $driverId, [
            'filename' => $filename,
            'sha256' => $driver->lastSyncSha256,
        ], $ip);

        return ['ok' => true, 'filename' => $filename, 'path' => $path];
    }

    /**
     * @param array<string, mixed> $mecm
     * @return array<string, mixed>
     */
    private function manifest(Driver $driver, array $mecm): array
    {
        $pkgName = trim($driver->manufacturer . ' ' . $driver->name . ' ' . $driver->version);

        return [
            'format' => 'mecm-driver-deploy',
            'driver' => [
                'name' => $driver->name,
                'manufacturer' => $driver->manufacturer,
                'model' => $driver->model,
                'version' => $driver->version,
                'architecture' => $driver->architecture,
                'os_target' => $driver->osTarget,
                'download_url' => $driver->downloadUrl,
                'sha256' => $driver->lastSyncSha256 ?: '',
                'file_name' => $driver->lastSyncFilename ?: 'driver-pack.bin',
                'file_size' => $driver->lastSyncSize,
                'description' => $driver->description,
                'package_name' => $pkgName,
            ],
            'mecm' => [
                'site_code' => $mecm['site_code'] ?? '',
                'site_server' => $mecm['site_server'] ?? '',
                'distribution_point' => $mecm['distribution_point'] ?? '',
                'driver_source_unc' => $mecm['driver_source_unc'] ?? '',
                'driver_package_unc' => $mecm['driver_package_unc'] ?? '',
                'driver_category' => $mecm['driver_category'] ?? '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function buildDeployPs1(array $manifest): string
    {
        $site = $this->psQuote((string) ($manifest['mecm']['site_code'] ?? ''));
        $server = $this->psQuote((string) ($manifest['mecm']['site_server'] ?? ''));
        $dp = $this->psQuote((string) ($manifest['mecm']['distribution_point'] ?? ''));

        $script = <<<PS
#Requires -Version 5.1
<#
.SYNOPSIS
  Telecharge le pack driver (URL dans driver.json), verifie le SHA-256, importe dans MECM.

.DESCRIPTION
  A executer sur un poste avec le module ConfigurationManager (console MECM).
  Les binaires ne sont PAS dans ce ZIP : le script les telecharge, les depose
  sur le partage UNC configure, puis cree / met a jour le Driver Package.

    .\\deploy-drivers.ps1
#>
[CmdletBinding(SupportsShouldProcess = \$true)]
param(
    [string] \$SiteCode = {$site},
    [string] \$SiteServer = {$server},
    [string] \$DistributionPoint = {$dp},
    [string] \$DriverJsonPath = '',
    [switch] \$SkipDistribution
)

\$ErrorActionPreference = 'Stop'
\$here = \$PSScriptRoot
if ([string]::IsNullOrWhiteSpace(\$DriverJsonPath)) {
    \$DriverJsonPath = Join-Path \$here 'driver.json'
}
if (-not (Test-Path -LiteralPath \$DriverJsonPath)) {
    throw "driver.json introuvable : \$DriverJsonPath"
}

\$cfg = (Get-Content -LiteralPath \$DriverJsonPath -Raw -Encoding UTF8) | ConvertFrom-Json
\$drv = \$cfg.driver
\$mecm = \$cfg.mecm

if ([string]::IsNullOrWhiteSpace(\$SiteCode) -and \$mecm.site_code) { \$SiteCode = [string]\$mecm.site_code }
if ([string]::IsNullOrWhiteSpace(\$SiteServer) -and \$mecm.site_server) { \$SiteServer = [string]\$mecm.site_server }
if ([string]::IsNullOrWhiteSpace(\$DistributionPoint) -and \$mecm.distribution_point) { \$DistributionPoint = [string]\$mecm.distribution_point }

\$sourceRoot = [string]\$mecm.driver_source_unc
\$packageRoot = [string]\$mecm.driver_package_unc
\$category = [string]\$mecm.driver_category
\$url = [string]\$drv.download_url
\$expectedSha = ([string]\$drv.sha256).ToLowerInvariant()
\$fileName = [string]\$drv.file_name
\$packageName = [string]\$drv.package_name
\$manufacturer = [string]\$drv.manufacturer
\$version = [string]\$drv.version

if ([string]::IsNullOrWhiteSpace(\$SiteCode)) { throw 'SiteCode manquant (Configuration MECM).' }
if ([string]::IsNullOrWhiteSpace(\$SiteServer)) { throw 'SiteServer manquant (Configuration MECM).' }
if ([string]::IsNullOrWhiteSpace(\$DistributionPoint)) { throw 'DistributionPoint manquant (Configuration MECM).' }
if ([string]::IsNullOrWhiteSpace(\$sourceRoot)) { throw 'driver_source_unc manquant (Configuration MECM / Drivers).' }
if ([string]::IsNullOrWhiteSpace(\$packageRoot)) { throw 'driver_package_unc manquant (Configuration MECM / Drivers).' }
if ([string]::IsNullOrWhiteSpace(\$url)) { throw 'download_url manquant dans driver.json.' }
if ([string]::IsNullOrWhiteSpace(\$fileName)) { \$fileName = 'driver-pack.bin' }
if ([string]::IsNullOrWhiteSpace(\$packageName)) { \$packageName = "\$manufacturer \$(\$drv.name) \$version" }

function Get-SafeFolderName([string] \$Name) {
    \$n = \$Name -replace '[\\\\/:*?"<>|]', '_'
    \$n = \$n.Trim()
    if ([string]::IsNullOrWhiteSpace(\$n)) { return 'driver' }
    return \$n
}

\$folderName = Get-SafeFolderName ("\$manufacturer-" + [string]\$drv.name + "-\$version")
\$extractDir = Join-Path \$sourceRoot \$folderName
\$downloadPath = Join-Path \$extractDir \$fileName
\$pkgContent = Join-Path \$packageRoot \$folderName

Write-Host "deploy-drivers.ps1"
Write-Host "Package     : \$packageName"
Write-Host "URL         : \$url"
Write-Host "SHA-256     : \$expectedSha"
Write-Host "Source UNC  : \$extractDir"
Write-Host "Package UNC : \$pkgContent"
Write-Host "Site        : \$SiteCode / \$SiteServer"
Write-Host "DP          : \$DistributionPoint"

New-Item -ItemType Directory -Force -Path \$extractDir | Out-Null
New-Item -ItemType Directory -Force -Path \$pkgContent | Out-Null

Write-Host "Telechargement…"
\$ProgressPreference = 'SilentlyContinue'
Invoke-WebRequest -Uri \$url -OutFile \$downloadPath -UseBasicParsing
if (-not (Test-Path -LiteralPath \$downloadPath)) {
    throw "Telechargement echoue : \$downloadPath"
}

\$actualSha = (Get-FileHash -LiteralPath \$downloadPath -Algorithm SHA256).Hash.ToLowerInvariant()
if (\$expectedSha -match '^[a-f0-9]{64}$') {
    if (\$actualSha -ne \$expectedSha) {
        throw "SHA-256 different. Attendu \$expectedSha / obtenu \$actualSha"
    }
    Write-Host "Hash OK."
} else {
    Write-Host "SHA-256 calcule (aucune empreinte fournie par l'atelier) : \$actualSha"
}

\$ext = [IO.Path]::GetExtension(\$downloadPath).ToLowerInvariant()
\$infRoot = \$extractDir
if (\$ext -eq '.zip') {
    \$infRoot = Join-Path \$extractDir '_extracted'
    if (Test-Path -LiteralPath \$infRoot) { Remove-Item -LiteralPath \$infRoot -Recurse -Force }
    Expand-Archive -LiteralPath \$downloadPath -DestinationPath \$infRoot -Force
    Write-Host "ZIP extrait vers \$infRoot"
} elseif (\$ext -eq '.cab') {
    \$infRoot = Join-Path \$extractDir '_extracted'
    if (Test-Path -LiteralPath \$infRoot) { Remove-Item -LiteralPath \$infRoot -Recurse -Force }
    New-Item -ItemType Directory -Force -Path \$infRoot | Out-Null
    \$null = & expand.exe \$downloadPath -F:* \$infRoot
    Write-Host "CAB extrait vers \$infRoot"
} elseif (\$ext -eq '.exe') {
    \$infRoot = Join-Path \$extractDir '_extracted'
    if (Test-Path -LiteralPath \$infRoot) { Remove-Item -LiteralPath \$infRoot -Recurse -Force }
    New-Item -ItemType Directory -Force -Path \$infRoot | Out-Null
    Write-Host "Extraction EXE vers \$infRoot"
    \$isHp = (\$manufacturer -match '(?i)^hp$') -or (\$fileName -match '(?i)^sp\d+')
    \$extracted = \$false
    if (\$isHp) {
        \$p = Start-Process -FilePath \$downloadPath -ArgumentList @('/s','/e',"/f\$infRoot") -Wait -PassThru -WindowStyle Hidden
        if (\$p -and \$p.ExitCode -eq 0) { \$extracted = \$true }
    }
    if (-not \$extracted) {
        \$p = Start-Process -FilePath \$downloadPath -ArgumentList @('/s',"/e=\$infRoot") -Wait -PassThru -WindowStyle Hidden
        if (\$p -and \$p.ExitCode -eq 0) { \$extracted = \$true }
    }
    \$infCount = @(Get-ChildItem -LiteralPath \$infRoot -Filter *.inf -Recurse -ErrorAction SilentlyContinue).Count
    if (\$infCount -eq 0) {
        throw "Aucun fichier INF apres extraction de \$fileName. Extraire manuellement le pack vers \$infRoot."
    }
    Write-Host "EXE extrait (\$infCount INF) vers \$infRoot"
}

\$cmModule = Get-Module -ListAvailable -Name ConfigurationManager | Select-Object -First 1
if (-not \$cmModule) {
    throw 'Module ConfigurationManager introuvable. Installez / lancez la console MECM sur ce poste.'
}
Import-Module ConfigurationManager -ErrorAction Stop

\$existingDrive = Get-PSDrive -Name \$SiteCode -PSProvider CMSite -ErrorAction SilentlyContinue
if (-not \$existingDrive) {
    \$init = Join-Path (\$env:SMS_ADMIN_UI_PATH) 'ConfigMgr.ps1'
    if (\$env:SMS_ADMIN_UI_PATH -and (Test-Path -LiteralPath \$init)) {
        . \$init
    }
    New-PSDrive -Name \$SiteCode -PSProvider CMSite -Root "\$SiteCode`:" -CMSiteServer \$SiteServer -Description "MECM Packaging Manager" -ErrorAction Stop | Out-Null
}
Set-Location "\$(\$SiteCode):\\"

if (\$PSCmdlet.ShouldProcess(\$packageName, 'Import-CMDriver + DriverPackage')) {
    \$importParams = @{
        UncFileLocation = \$infRoot
        ImportFolder    = \$true
        ErrorAction     = 'Stop'
    }
    if (-not [string]::IsNullOrWhiteSpace(\$category)) {
        \$catObj = Get-CMCategory -Name \$category -ErrorAction SilentlyContinue
        if (-not \$catObj) {
            \$catObj = New-CMCategory -Name \$category -CategoryType DriverCategories -ErrorAction SilentlyContinue
        }
        if (\$catObj) { \$importParams['AdministrativeCategory'] = \$catObj }
    }
    Write-Host "Import des drivers depuis \$infRoot…"
    \$imported = @(Import-CMDriver @importParams)
    Write-Host ("Drivers importes : " + \$imported.Count)

    \$pkg = Get-CMDriverPackage -Name \$packageName -ErrorAction SilentlyContinue
    if (-not \$pkg) {
        \$pkg = New-CMDriverPackage -Name \$packageName -Path \$pkgContent -Description ([string]\$drv.description)
        Write-Host "Driver Package cree : \$packageName"
    } else {
        Write-Host "Driver Package existant : \$packageName"
    }

    foreach (\$cmDriver in \$imported) {
        try {
            Add-CMDriverToDriverPackage -DriverId \$cmDriver.CI_ID -DriverPackageName \$packageName -ErrorAction Stop | Out-Null
        } catch {
            Write-Host "  (deja dans le package ou ignore) \$(\$cmDriver.LocalizedDisplayName)"
        }
    }

    if (-not \$SkipDistribution) {
        Write-Host "Distribution vers \$DistributionPoint…"
        Start-CMContentDistribution -DriverPackageName \$packageName -DistributionPointName \$DistributionPoint -ErrorAction Stop
        Write-Host "Distribution lancee."
    }
}

Write-Host "Termine."
PS;

        return $this->toCrLf($script);
    }

    private function buildReadme(Driver $driver): string
    {
        return "MECM driver deploy\r\n"
            . "==================\r\n\r\n"
            . $driver->name . " (" . $driver->manufacturer . ")\r\n"
            . "URL : " . $driver->downloadUrl . "\r\n"
            . "SHA-256 : " . (($driver->lastSyncSha256 !== null && $driver->lastSyncSha256 !== '') ? $driver->lastSyncSha256 : '(calcule par le script apres telechargement)') . "\r\n\r\n"
            . "1. Copiez ce dossier sur un poste avec la console MECM.\r\n"
            . "2. Verifiez Configuration (partages UNC Drivers) dans l'atelier.\r\n"
            . "3. PowerShell admin : .\\deploy-drivers.ps1\r\n"
            . "Le script telecharge le pack, verifie le hash, importe les INF, cree le Driver Package, distribue au DP.\r\n";
    }

    private function psQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function toCrLf(string $script): string
    {
        $normalized = str_replace(["\r\n", "\n"], "\r\n", $script);
        if (!str_starts_with($normalized, "\xEF\xBB\xBF")) {
            $normalized = "\xEF\xBB\xBF" . $normalized;
        }

        return $normalized;
    }

    private function slug(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? 'driver';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'driver';
    }
}
