<#
.SYNOPSIS
    Supprime le mot de passe BIOS Setup sur la machine locale (CMSL).
.DESCRIPTION
    Appelle Clear-HPBIOSSetupPassword avec le mot de passe actuel pour remettre le BIOS sans mot de passe.
    Utile pour tester Apply-HPBiosConfig.ps1 sur une machine sans MDP. Utilise le dossier Modules a cote du script.
.NOTES
    Code de sortie : 0 = succes, 1 = erreur.
#>

# ========== CONFIGURATION ==========
# Mot de passe BIOS Setup ACTUEL (celui qui est defini sur le BIOS). Obligatoire pour pouvoir le supprimer.
$CurrentBiosPassword = ""
# ===================================

try { chcp 65001 | Out-Null } catch { }
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ModulesDir = Join-Path $ScriptDir "Modules"
if (Test-Path -LiteralPath $ModulesDir -PathType Container) {
    $env:PSModulePath = "$ModulesDir;$env:PSModulePath"
}

if ([string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
    Write-Error "Indiquez le mot de passe BIOS actuel dans le script (variable CurrentBiosPassword) pour pouvoir le supprimer."
    exit 1
}

$ModuleNames = @("HPCMSL", "HP.ClientManagementScriptLibrary", "HP.ClientManagement")
$ModuleLoaded = $false
foreach ($ModuleName in $ModuleNames) {
    if (Get-Module -Name $ModuleName -ErrorAction SilentlyContinue) { $ModuleLoaded = $true; break }
    if (Get-Module -Name $ModuleName -ListAvailable -ErrorAction SilentlyContinue) {
        Import-Module -Name $ModuleName -ErrorAction Stop
        $ModuleLoaded = $true
        break
    }
}
if (-not $ModuleLoaded) {
    Write-Error "Module CMSL introuvable. Verifiez le dossier Modules ou installez HPCMSL."
    exit 1
}

try {
    $Manufacturer = Get-HPDeviceManufacturer -ErrorAction Stop
} catch { $Manufacturer = $null }
if ($Manufacturer -notmatch "HP|Hewlett") {
    Write-Error "Cette machine n'est pas un PC HP."
    exit 1
}

Write-Host "Suppression du mot de passe BIOS Setup..." -ForegroundColor Cyan
try {
    Clear-HPBIOSSetupPassword -Password $CurrentBiosPassword -ErrorAction Stop
    Write-Host "Mot de passe BIOS Setup supprime avec succes." -ForegroundColor Green
    exit 0
} catch {
    Write-Warning "Erreur : $($_.Exception.Message)"
    exit 1
}
