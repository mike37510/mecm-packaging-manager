<#
.SYNOPSIS
    Applique une configuration BIOS Dell via Dell Command | Configure (cctk.exe).
.DESCRIPTION
    Lit un fichier .ini (section [cctk]) et l'applique avec : cctk.exe -i <fichier>
    Optionnellement définit un mot de passe Setup (--setuppwd).
.NOTES
    Prérequis : Dell Command | Configure installé, PC Dell, PowerShell admin.
    Codes de sortie : 0 = succès, 1 = erreur.
#>

# ========== CONFIGURATION ==========
$ConfigPath = "bios_config.ini"          # Fichier INI CCTK (relatif au script ou absolu)
$CctkPath = ""                           # Vide = cherche dans Program Files
$UseRandomPassword = $true
$PasswordPlainText = ""
$CurrentBiosPassword = ""                # MDP Setup actuel si déjà défini
$LogLocalPath = ""                       # Dossier local (vide = %TEMP%)
$LogSharePath = ""                       # UNC optionnel pour copie du log
# ===================================

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

function Find-Cctk {
    param([string]$Explicit)
    if ($Explicit -and (Test-Path -LiteralPath $Explicit)) { return $Explicit }
    $candidates = @(
        "${env:ProgramFiles(x86)}\Dell\Command Configure\X86_64\cctk.exe",
        "${env:ProgramFiles}\Dell\Command Configure\X86_64\cctk.exe",
        "${env:ProgramFiles(x86)}\Dell\Command Configure\cctk.exe",
        (Join-Path $ScriptDir "tools\cctk.exe"),
        (Join-Path $ScriptDir "cctk.exe")
    )
    foreach ($p in $candidates) {
        if ($p -and (Test-Path -LiteralPath $p)) { return $p }
    }
    return $null
}

function New-RandomBiosPassword {
    # Lettres identiques AZERTY/QWERTY (exclut A Q W Z M). Pas de symboles. Pas de 0/1/I/O.
    $upper = 'ERTYUPSDFGHJKLXCVBN'
    $lower = 'ertyupsdfghjklxcvbn'
    $digits = '23456789'
    $chars = ($upper + $lower + $digits).ToCharArray()
    $pwd = -join (
        ($upper.ToCharArray() | Get-Random),
        ($lower.ToCharArray() | Get-Random),
        ($digits.ToCharArray() | Get-Random),
        (1..5 | ForEach-Object { $chars | Get-Random })
    )
    return -join ($pwd.ToCharArray() | Get-Random -Count $pwd.Length)
}

$cctk = Find-Cctk -Explicit $CctkPath
if (-not $cctk) {
    Write-Error "cctk.exe introuvable. Installez Dell Command | Configure ou renseignez `$CctkPath."
    exit 1
}

$configFull = if ([System.IO.Path]::IsPathRooted($ConfigPath)) { $ConfigPath } else { Join-Path $ScriptDir $ConfigPath }
if (-not (Test-Path -LiteralPath $configFull)) {
    Write-Error "Fichier de config introuvable : $configFull"
    exit 1
}

$logDir = $null
if (-not [string]::IsNullOrWhiteSpace($LogLocalPath)) {
    $expandedLocal = [Environment]::ExpandEnvironmentVariables($LogLocalPath.Trim().TrimEnd('\'))
    if (-not (Test-Path -LiteralPath $expandedLocal -PathType Container)) {
        try { New-Item -ItemType Directory -Path $expandedLocal -Force | Out-Null } catch { }
    }
    if (Test-Path -LiteralPath $expandedLocal -PathType Container) { $logDir = $expandedLocal }
}
if (-not $logDir) {
    $logDir = if (Test-Path -LiteralPath $env:TEMP -PathType Container) { $env:TEMP } else { $ScriptDir }
}
$logLocal = Join-Path $logDir ("DellBiosConfig_{0}_{1:yyyyMMdd_HHmmss}.log" -f $env:COMPUTERNAME, (Get-Date))
function Write-Log([string]$msg) {
    $line = "[{0:yyyy-MM-dd HH:mm:ss}] {1}" -f (Get-Date), $msg
    Add-Content -LiteralPath $logLocal -Value $line
    Write-Host $line
}

Write-Log "Machine=$env:COMPUTERNAME cctk=$cctk config=$configFull"

try {
    $mfr = (Get-CimInstance -ClassName Win32_ComputerSystem).Manufacturer
    if ($mfr -notmatch 'Dell') {
        Write-Log "ATTENTION: fabricant détecté='$mfr' (attendu Dell)."
    }

    $argsApply = @('-i', $configFull)
    if ($CurrentBiosPassword) {
        $argsApply += "--valsetuppwd=$CurrentBiosPassword"
    }
    Write-Log ("Apply: cctk " + ($argsApply -join ' '))
    & $cctk @argsApply
    if ($LASTEXITCODE -ne 0) {
        throw "cctk -i a échoué (code $LASTEXITCODE)."
    }
    Write-Log "Configuration INI appliquée."

    $newPwd = $null
    if ($UseRandomPassword) {
        $newPwd = New-RandomBiosPassword
    } elseif ($PasswordPlainText) {
        $newPwd = $PasswordPlainText
    }

    if ($newPwd) {
        $pwdArgs = @("--setuppwd=$newPwd")
        if ($CurrentBiosPassword) { $pwdArgs += "--valsetuppwd=$CurrentBiosPassword" }
        Write-Log "Définition du mot de passe Setup…"
        & $cctk @pwdArgs
        if ($LASTEXITCODE -ne 0) {
            throw "Échec définition setuppwd (code $LASTEXITCODE)."
        }
        Write-Log "Setup password défini avec succès : $newPwd"
    }

    Write-Log "SUCCÈS"
    if ($LogSharePath) {
        try {
            $dest = Join-Path $LogSharePath (Split-Path -Leaf $logLocal)
            Copy-Item -LiteralPath $logLocal -Destination $dest -Force
            Write-Log "Log copié : $dest"
            Remove-Item -LiteralPath $logLocal -Force -ErrorAction SilentlyContinue
        } catch {
            Write-Log "Copie partage échouée, log local conservé : $logLocal"
        }
    } else {
        Write-Log "Log local : $logLocal"
    }
    exit 0
} catch {
    Write-Log "ERREUR: $_"
    Write-Error $_
    exit 1
}
