<#
.SYNOPSIS
    Exporte toutes les options BIOS Lenovo vers un fichier .ini (TBCT).
.DESCRIPTION
    Appelle Export-LnvWmiSettings -NoKey (lecture seule / inventaire).
.NOTES
    Prérequis : module Lenovo.BIOS.Config, PC Lenovo, PowerShell admin.
#>

# ========== CONFIGURATION ==========
$OutputPath = ""                         # Vide = %TEMP%\LenovoBiosExport_<PC>_<date>.ini
$ModulePath = ""
# ===================================

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

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

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $env:TEMP ("LenovoBiosExport_{0}_{1:yyyyMMdd_HHmmss}.ini" -f $env:COMPUTERNAME, (Get-Date))
} elseif (-not [System.IO.Path]::IsPathRooted($OutputPath)) {
    $OutputPath = Join-Path $ScriptDir $OutputPath
}

Export-LnvWmiSettings -ConfigFile $OutputPath -NoKey
Write-Host "Export écrit : $OutputPath"
exit 0
