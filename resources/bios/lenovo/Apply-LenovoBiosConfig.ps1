<#
.SYNOPSIS
    Applique une configuration BIOS Lenovo via Lenovo.BIOS.Config (TBCT v2).
.DESCRIPTION
    Lit un fichier .ini au format SettingName,Value et l'applique avec :
      Import-LnvWmiSettings -ConfigFile <fichier>
      Save-LnvWmiSettings
    Optionnellement définit un mot de passe Supervisor (Update-LnvPassword -Type pap).
.NOTES
    Prérequis : module Lenovo.BIOS.Config (Think BIOS Config Tool v2), PC Lenovo Think*, PowerShell admin.
    Docs : https://docs.lenovocdrt.com/guides/tbct_v2/
    Codes de sortie : 0 = succès, 1 = erreur.
#>

# ========== CONFIGURATION ==========
$ConfigPath = "bios_config.ini"          # Fichier INI TBCT (relatif au script ou absolu)
$ModulePath = ""                         # Vide = Import-Module Lenovo.BIOS.Config (PATH / installé)
$UseRandomPassword = $true
$PasswordPlainText = ""
$CurrentBiosPassword = ""                # MDP Supervisor actuel si déjà défini
$LogLocalPath = ""                       # Dossier local (vide = %TEMP%)
$LogSharePath = ""                       # UNC optionnel pour copie du log
# ===================================

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

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

function ConvertTo-PlainSecureString([string]$Plain) {
    return ConvertTo-SecureString -String $Plain -AsPlainText -Force
}

function Import-LenovoBiosModule {
    param([string]$Explicit)
    if ($Explicit) {
        if (-not (Test-Path -LiteralPath $Explicit)) {
            throw "ModulePath introuvable : $Explicit"
        }
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
$logLocal = Join-Path $logDir ("LenovoBiosConfig_{0}_{1:yyyyMMdd_HHmmss}.log" -f $env:COMPUTERNAME, (Get-Date))
function Write-Log([string]$msg) {
    $line = "[{0:yyyy-MM-dd HH:mm:ss}] {1}" -f (Get-Date), $msg
    Add-Content -LiteralPath $logLocal -Value $line
    Write-Host $line
}

Write-Log "Machine=$env:COMPUTERNAME config=$configFull"

try {
    $mfr = (Get-CimInstance -ClassName Win32_ComputerSystem).Manufacturer
    if ($mfr -notmatch 'Lenovo') {
        Write-Log "ATTENTION: fabricant détecté='$mfr' (attendu Lenovo)."
    }

    Import-LenovoBiosModule -Explicit $ModulePath
    Write-Log "Module Lenovo.BIOS.Config chargé."

    $importParams = @{ ConfigFile = $configFull }
    if ($CurrentBiosPassword) {
        $importParams['Current'] = ConvertTo-PlainSecureString $CurrentBiosPassword
    }
    Write-Log "Import-LnvWmiSettings…"
    Import-LnvWmiSettings @importParams
    Write-Log "Save-LnvWmiSettings…"
    Save-LnvWmiSettings
    Write-Log "Configuration INI appliquée."

    $newPwd = $null
    if ($UseRandomPassword) {
        $newPwd = New-RandomBiosPassword
    } elseif ($PasswordPlainText) {
        $newPwd = $PasswordPlainText
    }

    if ($newPwd) {
        Write-Log "Définition du mot de passe Supervisor (pap)…"
        $upd = @{ Type = 'pap'; New = (ConvertTo-PlainSecureString $newPwd) }
        if ($CurrentBiosPassword) {
            $upd['Old'] = ConvertTo-PlainSecureString $CurrentBiosPassword
        } else {
            # Première définition : Old vide selon firmware / module
            $upd['Old'] = ConvertTo-PlainSecureString ''
        }
        Update-LnvPassword @upd
        Write-Log "Supervisor password défini avec succès : $newPwd"
    }

    if (Get-Command Test-LnvTBCPendingReboot -ErrorAction SilentlyContinue) {
        if (Test-LnvTBCPendingReboot) {
            Write-Log "Un redémarrage est requis pour appliquer certains paramètres."
        }
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
    Write-Log ("ERREUR: " + $_.Exception.Message)
    Write-Error $_
    exit 1
}
