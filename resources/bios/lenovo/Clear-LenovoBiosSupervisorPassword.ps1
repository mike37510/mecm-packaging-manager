<#
.SYNOPSIS
    Supprime le mot de passe Supervisor BIOS Lenovo (pap / svp).
.DESCRIPTION
    Utilise Update-LnvPassword -Type pap avec un nouveau mot de passe vide.
.NOTES
    Prérequis : module Lenovo.BIOS.Config, PC Lenovo, PowerShell admin.
    Il faut connaître le mot de passe Supervisor actuel.
#>

# ========== CONFIGURATION ==========
$CurrentBiosPassword = ""                # MDP Supervisor actuel (obligatoire)
$ModulePath = ""                         # Vide = Import-Module Lenovo.BIOS.Config
# ===================================

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

if ([string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
    Write-Error "Renseignez `$CurrentBiosPassword (mot de passe Supervisor actuel)."
    exit 1
}

function Import-LenovoBiosModule {
    param([string]$Explicit)
    if ($Explicit) {
        Import-Module -Name $Explicit -Force -ErrorAction Stop
        return
    }
    $local = Join-Path $ScriptDir "Lenovo.BIOS.Config"
    if (Test-Path -LiteralPath $local) {
        Import-Module -Name $local -Force -ErrorAction Stop
        return
    }
    Import-Module -Name "Lenovo.BIOS.Config" -ErrorAction Stop
}

Import-LenovoBiosModule -Explicit $ModulePath

$old = ConvertTo-SecureString -String $CurrentBiosPassword -AsPlainText -Force
$empty = ConvertTo-SecureString -String '' -AsPlainText -Force
Update-LnvPassword -Type pap -Old $old -New $empty
Write-Host "Mot de passe Supervisor effacé (si supporté par le firmware)."
exit 0
