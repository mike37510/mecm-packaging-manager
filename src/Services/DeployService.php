<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Procedure\StepType;
use App\Models\DeployProfile;
use App\Models\PackagingProcedure;
use App\Models\Software;
use App\Repositories\AuditLogRepository;
use App\Repositories\DeployProfileRepository;
use App\Repositories\ProcedureRepository;
use App\Repositories\SoftwareRepository;

/**
 * Assistant Déployer : métadonnées MECM → deploy.json + deploy.ps1.
 */
final class DeployService
{
    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly ProcedureRepository $procedures,
        private readonly DeployProfileRepository $profiles,
        private readonly AuditLogRepository $auditLogs,
        private readonly MecmConfigService $mecmConfig,
    ) {
    }

    /**
     * Profile merged with catalogue defaults (for the assistant form).
     *
     * @return array{software: Software, profile: DeployProfile|null, form: array<string, mixed>, manifest_preview: array<string, mixed>}
     */
    public function assistantData(int $softwareId): ?array
    {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return null;
        }

        $procedure = $this->procedures->getOrCreateActive($softwareId);
        $profile = $this->profiles->findBySoftware($softwareId);
        $form = $this->defaultsFor($software, $procedure);
        if ($profile !== null) {
            $form = $this->mergeForm($form, $profile->payload);
        }
        $form = $this->ensureMecmTargets($form);
        $form = $this->syncCatalogueFields($form, $software);

        return [
            'software' => $software,
            'profile' => $profile,
            'form' => $form,
            'manifest_preview' => $this->buildManifestArray($software, $procedure, $form),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: list<string>}
     */
    public function save(int $softwareId, array $input, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $procedure = $this->procedures->getOrCreateActive($softwareId);
        $form = $this->normalizeInput($input, $software, $procedure);
        $errors = $this->validate($form);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->profiles->upsert($softwareId, $form);
        $this->auditLogs->log(
            $userId,
            'deploy.profile_saved',
            'software',
            $softwareId,
            [
                'application_name' => $form['application_name'],
                'site_code' => $form['site_code'],
            ],
            $ip
        );

        return ['ok' => true];
    }

    /**
     * Full deploy.json document for the ZIP (uses saved profile or defaults).
     */
    public function buildDeployJson(Software $software, PackagingProcedure $procedure): string
    {
        $profile = $this->profiles->findBySoftware($software->id);
        $form = $this->defaultsFor($software, $procedure);
        if ($profile !== null) {
            $form = $this->mergeForm($form, $profile->payload);
        }
        $form = $this->ensureMecmTargets($form);
        $form = $this->syncCatalogueFields($form, $software);

        $manifest = $this->buildManifestArray($software, $procedure, $form);

        return (string) json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * PowerShell importer for Configuration Manager (run on admin / site workstation).
     */
    public function buildDeployPs1(Software $software, PackagingProcedure $procedure): string
    {
        $json = $this->buildDeployJson($software, $procedure);
        $manifest = json_decode($json, true);
        if (!is_array($manifest)) {
            $manifest = [];
        }

        $appName = (string) ($manifest['application']['name'] ?? $software->name);
        $defaultSite = strtoupper(trim((string) ($manifest['mecm']['site_code'] ?? '')));
        $defaultServer = strtolower(trim((string) ($manifest['mecm']['site_server'] ?? '')));
        $defaultDp = strtolower(trim((string) ($manifest['mecm']['distribution_point'] ?? '')));

        $siteLit = $this->psSingleQuoted($defaultSite);
        $serverLit = $this->psSingleQuoted($defaultServer);
        $dpLit = $this->psSingleQuoted($defaultDp);

        $script = <<<PS
#Requires -Version 5.1
<#
.SYNOPSIS
  Importe / met a jour l application MECM/MECM decrite dans deploy.json (meme dossier).

.DESCRIPTION
  A executer sur un poste disposant du module ConfigurationManager (console MECM).
  Site code, serveur de site et DP sont deja renseignes - lancez simplement :

    .\\deploy.ps1

  Options rares : -WhatIf , -SkipDistribution , -SkipCollectionDeployment
  Cree aussi le deploiement vers la collection indiquee dans deploy.json.
#>
[CmdletBinding(SupportsShouldProcess = \$true)]
param(
    [string] \$SiteCode = {$siteLit},
    [string] \$SiteServer = {$serverLit},
    [string] \$DistributionPoint = {$dpLit},
    [string] \$CollectionId = '',
    [string] \$DeployJsonPath = '',
    [switch] \$SkipDistribution,
    [switch] \$SkipCollectionDeployment
)

\$ErrorActionPreference = 'Stop'
\$here = \$PSScriptRoot
if ([string]::IsNullOrWhiteSpace(\$DeployJsonPath)) {
    \$DeployJsonPath = Join-Path \$here 'deploy.json'
}
if (-not (Test-Path -LiteralPath \$DeployJsonPath)) {
    throw "deploy.json introuvable : \$DeployJsonPath"
}

\$raw = Get-Content -LiteralPath \$DeployJsonPath -Raw -Encoding UTF8
\$cfg = \$raw | ConvertFrom-Json
\$appName = [string]\$cfg.application.name
\$publisher = [string]\$cfg.application.publisher
\$version = [string]\$cfg.application.software_version
\$description = [string]\$cfg.application.description
\$tech = [string]\$cfg.deployment_type.technology
\$installCmd = [string]\$cfg.deployment_type.install_command
\$uninstallCmd = [string]\$cfg.deployment_type.uninstall_command
\$detectType = [string]\$cfg.deployment_type.detection.type
\$detectPath = [string]\$cfg.deployment_type.detection.path
\$productCode = [string]\$cfg.deployment_type.detection.product_code
\$maxMin = [int]\$cfg.deployment_type.max_runtime_minutes
if (\$maxMin -le 0) { \$maxMin = 60 }
\$dtName = [string]\$cfg.deployment_type.name
if ([string]::IsNullOrWhiteSpace(\$dtName)) { \$dtName = 'Install' }

if ([string]::IsNullOrWhiteSpace(\$SiteCode) -and \$cfg.mecm.site_code) {
    \$SiteCode = [string]\$cfg.mecm.site_code
}
if ([string]::IsNullOrWhiteSpace(\$SiteServer) -and \$cfg.mecm.site_server) {
    \$SiteServer = [string]\$cfg.mecm.site_server
}
if ([string]::IsNullOrWhiteSpace(\$DistributionPoint) -and \$cfg.mecm.distribution_point) {
    \$DistributionPoint = [string]\$cfg.mecm.distribution_point
}
if ([string]::IsNullOrWhiteSpace(\$CollectionId) -and \$cfg.mecm.collection_id) {
    \$CollectionId = [string]\$cfg.mecm.collection_id
}
\$deployPurposeRaw = if (\$cfg.mecm.deployment_purpose) { [string]\$cfg.mecm.deployment_purpose } else { 'available' }
\$deployPurpose = if (\$deployPurposeRaw -match '^(?i)required$') { 'Required' } else { 'Available' }
\$userNotification = 'DisplaySoftwareCenterOnly'
if (\$cfg.mecm.user_notification) {
    \$un = [string]\$cfg.mecm.user_notification
    if (\$un -match '^(?i)display.?all$') { \$userNotification = 'DisplayAll' }
    elseif (\$un -match '^(?i)hide') { \$userNotification = 'HideAll' }
}
if ([string]::IsNullOrWhiteSpace(\$SiteCode)) {
    throw 'SiteCode manquant dans deploy.json.'
}
if ([string]::IsNullOrWhiteSpace(\$SiteServer)) {
    throw 'SiteServer (serveur principal) manquant dans deploy.json.'
}
if ([string]::IsNullOrWhiteSpace(\$DistributionPoint)) {
    throw 'DistributionPoint manquant dans deploy.json.'
}
if (-not \$SkipCollectionDeployment -and [string]::IsNullOrWhiteSpace(\$CollectionId)) {
    throw 'CollectionId manquant dans deploy.json (ID du regroupement de deploiement).'
}

Write-Host "deploy.ps1   : v5.1 (2026-08-09)"
Write-Host "Application : \$appName"
Write-Host "Version     : \$version"
Write-Host "Technologie : \$tech"
Write-Host "Contenu     : \$here"
Write-Host "Site        : \$SiteCode"
Write-Host "Site server : \$SiteServer"
Write-Host "DP          : \$DistributionPoint"
Write-Host "Collection  : \$(if (\$SkipCollectionDeployment) { '(ignoree)' } else { \$CollectionId })"
Write-Host "Objectif    : \$deployPurpose"

# Centre logiciel (deploy.json → onglet Software Center)
\$sc = \$cfg.software_center
\$localizedName = if (\$sc -and \$sc.localized_name) { [string]\$sc.localized_name } else { \$appName }
\$localizedDesc = if (\$sc -and \$sc.localized_description) { [string]\$sc.localized_description } else { \$description }
\$infoUrl = [string]\$cfg.application.info_url
\$linkText = if (\$sc -and \$sc.link_text) { [string]\$sc.link_text } else { '' }
\$privacyUrl = if (\$sc -and \$sc.privacy_url) { [string]\$sc.privacy_url } else { '' }
\$langId = 1036
if (\$sc -and \$sc.language_id) { \$langId = [int]\$sc.language_id }
if (\$langId -le 0) { \$langId = 1036 }
\$keywords = @()
if (\$sc -and \$sc.keywords) { \$keywords = @(@(\$sc.keywords) | ForEach-Object { [string]\$_ } | Where-Object { \$_ }) }
\$iconPath = \$null
if (\$sc -and \$sc.icon_file) {
    \$cand = Join-Path \$here (([string]\$sc.icon_file) -replace '/', '\')
    if (Test-Path -LiteralPath \$cand) { \$iconPath = \$cand }
}
if (-not \$iconPath) {
    \$iconDir = Join-Path \$here 'icon'
    if (Test-Path -LiteralPath \$iconDir) {
        \$found = Get-ChildItem -LiteralPath \$iconDir -Filter 'sccm-icon.*' -File -ErrorAction SilentlyContinue | Select-Object -First 1
        if (\$found) { \$iconPath = \$found.FullName }
    }
}
Write-Host "Centre log. : \$localizedName"
if (\$iconPath) { Write-Host "Icone       : \$iconPath" }

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

\$existing = Get-CMApplication -Name \$appName -ErrorAction SilentlyContinue
\$legacyName = \$null
if (-not \$existing -and -not [string]::IsNullOrWhiteSpace(\$version) -and \$appName.EndsWith(' ' + \$version)) {
    \$legacyName = \$appName.Substring(0, \$appName.Length - \$version.Length - 1).Trim()
    if (-not [string]::IsNullOrWhiteSpace(\$legacyName)) {
        \$existing = Get-CMApplication -Name \$legacyName -ErrorAction SilentlyContinue
    }
}

if (\$PSCmdlet.ShouldProcess(\$appName, 'New/Set-CMApplication + DeploymentType')) {
    if (-not \$existing) {
        \$newParams = @{
            Name                 = \$appName
            Publisher            = \$publisher
            SoftwareVersion      = \$version
            Description          = \$description
            ReleaseDate          = (Get-Date)
            LocalizedName        = \$localizedName
            LocalizedDescription = \$localizedDesc
        }
        if (\$iconPath) { \$newParams['IconLocationFile'] = \$iconPath }
        if (-not [string]::IsNullOrWhiteSpace(\$infoUrl)) { \$newParams['UserDocumentation'] = \$infoUrl }
        if (-not [string]::IsNullOrWhiteSpace(\$linkText)) { \$newParams['LinkText'] = \$linkText }
        if (-not [string]::IsNullOrWhiteSpace(\$privacyUrl)) { \$newParams['PrivacyUrl'] = \$privacyUrl }
        if (\$keywords.Count -gt 0) { \$newParams['Keyword'] = \$keywords }
        \$null = New-CMApplication @newParams
        Write-Host "Application creee : \$appName"
    } else {
        if (\$legacyName) {
            Set-CMApplication -Name \$legacyName -NewName \$appName -ErrorAction SilentlyContinue
            Write-Host "Application renommee : \$legacyName -> \$appName"
        }
        \$setParams = @{
            Name                       = \$appName
            Publisher                  = \$publisher
            SoftwareVersion            = \$version
            Description                = \$description
            LocalizedApplicationName   = \$localizedName
            LocalizedDescription       = \$localizedDesc
            ApplyToLanguageById        = \$langId
        }
        if (\$iconPath) { \$setParams['IconLocationFile'] = \$iconPath }
        if (-not [string]::IsNullOrWhiteSpace(\$infoUrl)) { \$setParams['UserDocumentation'] = \$infoUrl }
        if (-not [string]::IsNullOrWhiteSpace(\$linkText)) { \$setParams['LinkText'] = \$linkText }
        if (-not [string]::IsNullOrWhiteSpace(\$privacyUrl)) { \$setParams['PrivacyUrl'] = \$privacyUrl }
        if (\$keywords.Count -gt 0) { \$setParams['Keyword'] = \$keywords }
        Set-CMApplication @setParams -ErrorAction Stop
        Write-Host "Application mise a jour (Centre logiciel) : \$appName"
    }

    \$existingDt = Get-CMDeploymentType -ApplicationName \$appName -DeploymentTypeName \$dtName -ErrorAction SilentlyContinue
    if (\$existingDt) {
        Remove-CMDeploymentType -ApplicationName \$appName -DeploymentTypeName \$dtName -Force -ErrorAction SilentlyContinue
        Write-Host "Ancien Deployment Type supprime : \$dtName"
    }

    # Detection via clause uniquement.
    # Ne PAS combiner -ScriptLanguage avec -AddDetectionClause :
    # ScriptLanguage = jeux ByApp*Script ; AddDetectionClause = jeux ByApp*DetectionClause
    # → AmbiguousParameterSet si les deux sont presents.
    if (\$detectType -eq 'msi' -and -not [string]::IsNullOrWhiteSpace(\$productCode)) {
        \$clause = New-CMDetectionClauseWindowsInstaller -ProductCode \$productCode -Existence
    } elseif (-not [string]::IsNullOrWhiteSpace(\$detectPath)) {
        \$clause = New-CMDetectionClauseFile -Path (Split-Path -Parent \$detectPath) -FileName (Split-Path -Leaf \$detectPath) -Existence -Is64Bit
    } else {
        throw 'Detection manquante dans deploy.json (path ou product_code).'
    }

    Write-Host "Ajout Deployment Type : \$dtName ..."
    try {
        \$null = Add-CMScriptDeploymentType `
            -ApplicationName \$appName `
            -DeploymentTypeName \$dtName `
            -ContentLocation \$here `
            -InstallCommand \$installCmd `
            -AddDetectionClause \$clause `
            -UninstallCommand \$uninstallCmd `
            -MaximumRuntimeMins ([Math]::Max(\$maxMin, 120)) `
            -LogonRequirementType WhetherOrNotUserLoggedOn `
            -InstallationBehaviorType InstallForSystem
    } catch {
        throw ("Echec Add-CMScriptDeploymentType : {0} [{1}]" -f \$_.Exception.Message, \$_.FullyQualifiedErrorId)
    }

    Write-Host "Deployment Type ajoute : \$dtName"

    if (-not \$SkipDistribution) {
        Start-CMContentDistribution -ApplicationName \$appName -DistributionPointName \$DistributionPoint -ErrorAction Stop
        Write-Host "Distribution lancee vers \$DistributionPoint"
    } else {
        Write-Host "Distribution ignoree (-SkipDistribution)."
    }

    if (-not \$SkipCollectionDeployment) {
        \$col = Get-CMCollection -Id \$CollectionId -ErrorAction SilentlyContinue
        if (-not \$col) {
            throw "Collection MECM introuvable pour l ID : \$CollectionId"
        }
        \$colLabel = [string]\$col.Name

        \$existingDeploy = Get-CMApplicationDeployment -Name \$appName -CollectionId \$CollectionId -ErrorAction SilentlyContinue
        if (\$existingDeploy) {
            Remove-CMApplicationDeployment -ApplicationName \$appName -CollectionId \$CollectionId -Force -ErrorAction SilentlyContinue
            Write-Host "Ancien deploiement collection supprime : \$CollectionId (\$colLabel)"
        }

        \$depParams = @{
            Name              = \$appName
            CollectionId      = \$CollectionId
            DeployAction      = 'Install'
            DeployPurpose     = \$deployPurpose
            UserNotification  = \$userNotification
            AvailableDateTime = (Get-Date)
            TimeBaseOn        = 'LocalTime'
            Comment           = 'MECM Packaging Manager'
        }
        if (\$deployPurpose -eq 'Required') {
            \$depParams['DeadlineDateTime'] = (Get-Date)
        }

        Write-Host "Deploiement collection : \$CollectionId - \$colLabel (\$deployPurpose) ..."
        try {
            \$null = New-CMApplicationDeployment @depParams
        } catch {
            throw ("Echec New-CMApplicationDeployment : {0} [{1}]" -f \$_.Exception.Message, \$_.FullyQualifiedErrorId)
        }
        Write-Host "Deploiement cree vers \$CollectionId (\$colLabel)"
    } else {
        Write-Host "Deploiement collection ignore (-SkipCollectionDeployment)."
    }
}

Write-Host "OK - application, contenu et deploiement collection a jour dans MECM."
PS;

        return $this->toCrLf($script);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(Software $software, PackagingProcedure $procedure): array
    {
        $isMsi = $software->isMsiFamily();
        $productCode = $this->extractProductCode($procedure);
        $witness = $this->witnessPath($software);
        $mecm = $this->mecmConfig->get();
        $desc = trim((string) ($software->description ?? '')) !== ''
            ? (string) $software->description
            : ($software->name . ' — package MECM Packaging Manager');
        $appName = trim($software->name . ' ' . $software->currentVersion);

        return [
            'application_name' => $appName,
            'publisher' => $software->publisher,
            'software_version' => $software->currentVersion,
            'description' => $desc,
            'info_url' => $this->defaultInfoUrl($software),
            'localized_name' => $appName,
            'localized_description' => $desc,
            'link_text' => 'Documentation',
            'privacy_url' => '',
            'keywords' => trim((string) $software->category),
            'category' => $software->category,
            'deployment_type_name' => 'Install',
            'technology' => $isMsi ? 'msi' : 'script',
            'install_command' => 'powershell.exe -ExecutionPolicy Bypass -File .\\install.ps1',
            'uninstall_command' => 'powershell.exe -ExecutionPolicy Bypass -File .\\uninstall.ps1',
            'detection_type' => ($isMsi && $productCode) ? 'msi' : 'file',
            'detection_path' => $witness,
            'product_code' => $productCode ?? '',
            'max_runtime_minutes' => 60,
            'installation_behavior' => 'system',
            'reboot_behavior' => 'based_on_exit_code',
            'site_code' => (string) ($mecm['site_code'] ?? ''),
            'site_server' => (string) ($mecm['site_server'] ?? ''),
            'distribution_point' => (string) ($mecm['distribution_point'] ?? ''),
            'collection_id' => (string) ($mecm['collection_id'] ?? ''),
            'deployment_purpose' => (string) ($mecm['deployment_purpose'] ?? 'available'),
            'user_notification' => 'software_center',
            'admin_folder' => '',
            'notes' => '',
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    private function mergeForm(array $base, array $saved): array
    {
        foreach ($base as $key => $default) {
            if (!array_key_exists($key, $saved)) {
                continue;
            }
            $val = $saved[$key];
            if (is_string($val) || is_int($val) || is_float($val) || is_bool($val) || $val === null) {
                $base[$key] = $val === null ? '' : $val;
            }
        }

        // Always refresh version from catalogue when generating defaults overlay
        return $base;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input, Software $software, PackagingProcedure $procedure): array
    {
        $defaults = $this->defaultsFor($software, $procedure);
        $out = [];
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $input)) {
                $out[$key] = $default;
                continue;
            }
            $val = $input[$key];
            if (is_int($default)) {
                $out[$key] = (int) $val;
            } else {
                $out[$key] = trim((string) $val);
            }
        }

        // Keep catalogue version in sync at save time
        $out = $this->syncCatalogueFields($out, $software);
        if ($out['publisher'] === '') {
            $out['publisher'] = $software->publisher;
        }
        $out['site_code'] = strtoupper(trim((string) $out['site_code']));
        $out['site_server'] = strtolower(trim((string) $out['site_server']));
        $out['distribution_point'] = strtolower(trim((string) $out['distribution_point']));
        $out['collection_id'] = strtoupper(trim((string) ($out['collection_id'] ?? '')));
        $purpose = strtolower(trim((string) ($out['deployment_purpose'] ?? 'available')));
        $out['deployment_purpose'] = $purpose === 'required' ? 'required' : 'available';
        $notif = strtolower(trim((string) ($out['user_notification'] ?? 'software_center')));
        if (!in_array($notif, ['software_center', 'display_all', 'hide_all'], true)) {
            $notif = 'software_center';
        }
        $out['user_notification'] = $notif;

        return $out;
    }

    /**
     * @param array<string, mixed> $form
     * @return list<string>
     */
    private function validate(array $form): array
    {
        $errors = [];
        if (trim((string) $form['application_name']) === '') {
            $errors[] = 'Le nom d’application MECM est obligatoire.';
        }
        if (trim((string) $form['install_command']) === '') {
            $errors[] = 'La commande d’installation est obligatoire.';
        }
        if (trim((string) $form['uninstall_command']) === '') {
            $errors[] = 'La commande de désinstallation est obligatoire.';
        }
        $det = (string) $form['detection_type'];
        if ($det === 'file' && trim((string) $form['detection_path']) === '') {
            $errors[] = 'Le chemin de détection (témoin) est obligatoire.';
        }
        if ($det === 'msi' && trim((string) $form['product_code']) === '') {
            $errors[] = 'Le Product Code MSI est obligatoire pour une détection MSI.';
        }
        $mins = (int) $form['max_runtime_minutes'];
        if ($mins < 5 || $mins > 720) {
            $errors[] = 'La durée max doit être entre 5 et 720 minutes.';
        }
        $site = strtoupper(trim((string) $form['site_code']));
        $server = strtolower(trim((string) $form['site_server']));
        $dp = strtolower(trim((string) $form['distribution_point']));
        if ($site === '') {
            $errors[] = 'Site code MECM obligatoire (configurez-le aussi dans Configuration).';
        } elseif (!preg_match('/^[A-Z0-9]{3}$/', $site)) {
            $errors[] = 'Site code invalide (3 caractères, ex. SIT).';
        }
        if ($server === '') {
            $errors[] = 'Serveur principal de site obligatoire.';
        }
        if ($dp === '') {
            $errors[] = 'Distribution Point obligatoire.';
        }
        if (trim((string) ($form['collection_id'] ?? '')) === '') {
            $errors[] = 'ID de collection obligatoire (regroupement MECM cible).';
        } elseif (!preg_match('/^[A-Za-z0-9]+$/', trim((string) $form['collection_id']))) {
            $errors[] = 'ID de collection invalide (ex. SIT00015).';
        }
        $purpose = strtolower(trim((string) ($form['deployment_purpose'] ?? '')));
        if ($purpose !== '' && !in_array($purpose, ['available', 'required'], true)) {
            $errors[] = 'Objectif de déploiement invalide (available ou required).';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function buildManifestArray(Software $software, PackagingProcedure $procedure, array $form): array
    {
        $appName = (string) $form['application_name'];
        $desc = (string) $form['description'];
        $localizedName = trim((string) ($form['localized_name'] ?? ''));
        if ($localizedName === '') {
            $localizedName = $appName;
        }
        $localizedDesc = trim((string) ($form['localized_description'] ?? ''));
        if ($localizedDesc === '') {
            $localizedDesc = $desc;
        }
        $infoUrl = (string) $form['info_url'];
        $linkText = trim((string) ($form['link_text'] ?? ''));
        if ($linkText === '' && $infoUrl !== '') {
            $linkText = 'Documentation';
        }
        $keywordsRaw = trim((string) ($form['keywords'] ?? $form['category'] ?? ''));
        $keywords = array_values(array_filter(array_map(
            static fn (string $k): string => trim($k),
            preg_split('/[,;]+/', $keywordsRaw) ?: []
        ), static fn (string $k): bool => $k !== ''));

        $iconFile = '';
        if ($software->iconPath) {
            $ext = strtolower(pathinfo($software->iconPath, PATHINFO_EXTENSION)) ?: 'png';
            $iconFile = 'icon/sccm-icon.' . $ext;
        }

        return [
            'schema_version' => 3,
            'generated_by' => 'MECM Packaging Manager',
            'software_id' => $software->id,
            'exported_at' => date('c'),
            'application' => [
                'name' => $appName,
                'publisher' => (string) $form['publisher'],
                'software_version' => (string) $form['software_version'],
                'description' => $desc,
                'localized_description' => $localizedDesc,
                'info_url' => $infoUrl,
                'category' => (string) $form['category'],
            ],
            'software_center' => [
                'language_id' => 1036,
                'localized_name' => $localizedName,
                'localized_description' => $localizedDesc,
                'user_documentation' => $infoUrl,
                'link_text' => $linkText,
                'privacy_url' => (string) ($form['privacy_url'] ?? ''),
                'keywords' => $keywords,
                'icon_file' => $iconFile,
                'is_featured' => false,
            ],
            'deployment_type' => [
                'name' => (string) $form['deployment_type_name'],
                'technology' => (string) $form['technology'],
                'install_command' => (string) $form['install_command'],
                'uninstall_command' => (string) $form['uninstall_command'],
                'detection' => [
                    'type' => (string) $form['detection_type'],
                    'path' => (string) $form['detection_path'],
                    'product_code' => (string) $form['product_code'],
                ],
                'installation_behavior' => (string) $form['installation_behavior'],
                'reboot_behavior' => (string) $form['reboot_behavior'],
                'max_runtime_minutes' => (int) $form['max_runtime_minutes'],
            ],
            'content' => [
                'source_folder' => '.',
                'requires_install_ps1' => true,
                'requires_uninstall_ps1' => true,
                'icon_file' => $iconFile,
            ],
            'mecm' => [
                'site_code' => (string) $form['site_code'],
                'site_server' => (string) $form['site_server'],
                'distribution_point' => (string) $form['distribution_point'],
                'collection_id' => (string) ($form['collection_id'] ?? ''),
                'deployment_purpose' => (string) ($form['deployment_purpose'] ?? 'available'),
                'user_notification' => (string) ($form['user_notification'] ?? 'software_center'),
                'admin_folder' => (string) $form['admin_folder'],
            ],
            'notes' => (string) ($form['notes'] ?? ''),
            'lab' => [
                'architecture' => $software->architecture,
                'installer_type' => $software->installerType,
                'procedure_id' => $procedure->id,
            ],
        ];
    }

    /**
     * Align version + nom MECM (Nom + version) depuis le catalogue.
     * Si le profil a un nom « brut » ou « Nom x.y », on le resynchronise.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function syncCatalogueFields(array $form, Software $software): array
    {
        $form['software_version'] = $software->currentVersion;
        $base = trim($software->name);
        $ver = trim($software->currentVersion);
        $desired = trim($base . ' ' . $ver);
        $name = trim((string) ($form['application_name'] ?? ''));

        if ($name === '' || $name === $base || preg_match('/^' . preg_quote($base, '/') . '\s+\S+$/u', $name) === 1) {
            $form['application_name'] = $desired;
        }

        $localized = trim((string) ($form['localized_name'] ?? ''));
        if ($localized === '' || $localized === $base || preg_match('/^' . preg_quote($base, '/') . '\s+\S+$/u', $localized) === 1) {
            $form['localized_name'] = (string) $form['application_name'];
        }

        if (trim((string) ($form['localized_description'] ?? '')) === '') {
            $form['localized_description'] = (string) ($form['description'] ?? '');
        }
        if (trim((string) ($form['link_text'] ?? '')) === '' && trim((string) ($form['info_url'] ?? '')) !== '') {
            $form['link_text'] = 'Documentation';
        }
        if (trim((string) ($form['keywords'] ?? '')) === '' && trim((string) ($form['category'] ?? '')) !== '') {
            $form['keywords'] = (string) $form['category'];
        }

        $doc = trim((string) ($software->documentationUrl ?? ''));
        $download = trim((string) ($software->downloadUrl ?? ''));
        $versionCheck = trim((string) ($software->versionCheckUrl ?? ''));
        $currentInfo = trim((string) ($form['info_url'] ?? ''));
        if ($doc !== '' && ($currentInfo === '' || $currentInfo === $download || $currentInfo === $versionCheck)) {
            $form['info_url'] = $doc;
            if (trim((string) ($form['link_text'] ?? '')) === '') {
                $form['link_text'] = 'Documentation';
            }
        }

        return $form;
    }

    /**
     * MECM UserDocumentation: prefer the dedicated documentation URL over the installer URL.
     */
    private function defaultInfoUrl(Software $software): string
    {
        $doc = trim((string) ($software->documentationUrl ?? ''));
        if ($doc !== '') {
            return $doc;
        }

        return (string) ($software->downloadUrl ?? $software->versionCheckUrl ?? '');
    }

    /**
     * Fill empty site/DP from global MECM settings.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function ensureMecmTargets(array $form): array
    {
        $mecm = $this->mecmConfig->get();
        if (trim((string) ($form['site_code'] ?? '')) === '') {
            $form['site_code'] = (string) ($mecm['site_code'] ?? '');
        }
        if (trim((string) ($form['site_server'] ?? '')) === '') {
            $form['site_server'] = (string) ($mecm['site_server'] ?? '');
        }
        if (trim((string) ($form['distribution_point'] ?? '')) === '') {
            $form['distribution_point'] = (string) ($mecm['distribution_point'] ?? '');
        }
        if (trim((string) ($form['collection_id'] ?? '')) === '') {
            $form['collection_id'] = (string) ($mecm['collection_id'] ?? '');
        }
        if (trim((string) ($form['deployment_purpose'] ?? '')) === '') {
            $form['deployment_purpose'] = (string) ($mecm['deployment_purpose'] ?? 'available');
        }
        $form['site_code'] = strtoupper(trim((string) $form['site_code']));
        $form['site_server'] = strtolower(trim((string) ($form['site_server'] ?? '')));
        $form['distribution_point'] = strtolower(trim((string) ($form['distribution_point'] ?? '')));
        $form['collection_id'] = strtoupper(trim((string) ($form['collection_id'] ?? '')));
        $purpose = strtolower(trim((string) ($form['deployment_purpose'] ?? 'available')));
        $form['deployment_purpose'] = $purpose === 'required' ? 'required' : 'available';

        return $form;
    }

    private function witnessPath(Software $software): string
    {
        $app = preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'App';
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'app');
        $file = $slug . '.' . $software->currentVersion . '.log';

        return 'C:\\SCCM_Temoins\\' . $app . '\\' . $file;
    }

    private function extractProductCode(PackagingProcedure $procedure): ?string
    {
        foreach ($procedure->steps as $step) {
            if ($step->type !== StepType::UninstallCommand) {
                continue;
            }
            $code = trim((string) ($step->payload['product_code'] ?? ''));
            if ($code !== '') {
                return $code;
            }
        }

        return null;
    }

    private function psSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function toCrLf(string $script): string
    {
        $normalized = str_replace(["\r\n", "\n"], "\r\n", $script);
        // BOM UTF-8 : Windows PowerShell 5.1 lit correctement les scripts hors ASCII
        if (!str_starts_with($normalized, "\xEF\xBB\xBF")) {
            $normalized = "\xEF\xBB\xBF" . $normalized;
        }

        return $normalized;
    }
}