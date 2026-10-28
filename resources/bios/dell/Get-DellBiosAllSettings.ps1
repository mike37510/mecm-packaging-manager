<#
.SYNOPSIS
    Exporte les options BIOS Dell réplicables via cctk -o (fichier INI).
.DESCRIPTION
    Génère un .ini CCTK à partir de la machine locale (lecture).
#>

param(
    [switch]$ExportToFile,
    [string]$CctkPath = ""
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

function Find-Cctk {
    param([string]$Explicit)
    if ($Explicit -and (Test-Path -LiteralPath $Explicit)) { return $Explicit }
    @(
        "${env:ProgramFiles(x86)}\Dell\Command Configure\X86_64\cctk.exe",
        "${env:ProgramFiles}\Dell\Command Configure\X86_64\cctk.exe",
        (Join-Path $ScriptDir "tools\cctk.exe"),
        (Join-Path $ScriptDir "cctk.exe")
    ) | ForEach-Object { if ($_ -and (Test-Path -LiteralPath $_)) { return $_ } }
    return $null
}

$cctk = Find-Cctk -Explicit $CctkPath
if (-not $cctk) {
    Write-Error "cctk.exe introuvable. Installez Dell Command | Configure."
    exit 1
}

$outFile = Join-Path $ScriptDir ("BIOS_Options_{0}_{1:yyyyMMdd_HHmmss}.ini" -f $env:COMPUTERNAME, (Get-Date))
& $cctk -o $outFile
if ($LASTEXITCODE -ne 0) {
    Write-Error "cctk -o a échoué (code $LASTEXITCODE)."
    exit 1
}

Write-Host "Export INI : $outFile"
if (-not $ExportToFile) {
    Get-Content -LiteralPath $outFile
}
exit 0
