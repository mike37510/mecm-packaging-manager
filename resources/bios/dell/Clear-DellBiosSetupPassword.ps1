<#
.SYNOPSIS
    Supprime le mot de passe BIOS Setup Dell (cctk --setuppwd=).
.NOTES
    Nécessite le mot de passe actuel et Dell Command | Configure.
#>

# ========== CONFIGURATION ==========
$CurrentBiosPassword = ""
$CctkPath = ""
# ===================================

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

if ([string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
    Write-Error "Renseignez `$CurrentBiosPassword (mot de passe Setup actuel)."
    exit 1
}

$cctk = Find-Cctk -Explicit $CctkPath
if (-not $cctk) {
    Write-Error "cctk.exe introuvable."
    exit 1
}

& $cctk --setuppwd= --valsetuppwd=$CurrentBiosPassword
if ($LASTEXITCODE -ne 0) {
    Write-Error "Échec suppression MDP (code $LASTEXITCODE)."
    exit 1
}
Write-Host "Mot de passe Setup supprimé."
exit 0
