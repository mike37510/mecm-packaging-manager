<#
.SYNOPSIS
    Liste toutes les options BIOS HP (nom + valeur actuelle).
.DESCRIPTION
    Utilise Get-HPBIOSSettingsList puis affiche chaque option et sa valeur.
    Option -ExportToFile pour sauvegarder dans un fichier (ex. BIOS_Options_MONPC.txt).
.NOTES
    Utilise le dossier Modules a cote du script (portable).
#>

param(
    [switch]$ExportToFile
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ModulesDir = Join-Path $ScriptDir "Modules"
if (Test-Path -LiteralPath $ModulesDir -PathType Container) {
    $env:PSModulePath = "$ModulesDir;$env:PSModulePath"
}

$ModuleNames = @("HPCMSL", "HP.ClientManagementScriptLibrary", "HP.ClientManagement")
$loaded = $false
foreach ($ModuleName in $ModuleNames) {
    if (Get-Module -Name $ModuleName -ErrorAction SilentlyContinue) { $loaded = $true; break }
    if (Get-Module -Name $ModuleName -ListAvailable -ErrorAction SilentlyContinue) {
        Import-Module -Name $ModuleName -ErrorAction Stop
        $loaded = $true
        break
    }
}
if (-not $loaded) {
    Write-Error "Module CMSL introuvable. Verifiez le dossier Modules."
    exit 1
}

try {
    $manufacturer = Get-HPDeviceManufacturer -ErrorAction Stop
} catch { $manufacturer = $null }
if ($manufacturer -notmatch "HP|Hewlett") {
    Write-Error "Cette machine n'est pas un PC HP."
    exit 1
}

Write-Host "Recuperation de toutes les options BIOS..." -ForegroundColor Cyan
$all = Get-HPBIOSSettingsList -ErrorAction Stop
$count = @($all).Count
Write-Host "Options trouvees : $count" -ForegroundColor Green
Write-Host ""

$lines = @()
$lines += "=== Liste des options BIOS - $($env:COMPUTERNAME) - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ==="
$lines += ""

foreach ($s in $all) {
    $name = [string]$s.Name
    if ([string]::IsNullOrWhiteSpace($name)) { continue }
    $val = Get-HPBIOSSettingValue -Name $name -ErrorAction SilentlyContinue
    $line = "  $name`t: $val"
    $lines += $line
    if (-not $ExportToFile) {
        Write-Host $line
    }
}

if ($ExportToFile) {
    $outPath = Join-Path $ScriptDir ("BIOS_Options_{0}_{1}.txt" -f $env:COMPUTERNAME, (Get-Date -Format 'yyyyMMdd_HHmmss'))
    $lines | Set-Content -Path $outPath -Encoding UTF8
    Write-Host "Liste exportee dans : $outPath" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Pour exporter dans un fichier : .\Get-HPBiosAllSettings.ps1 -ExportToFile" -ForegroundColor Gray
}
