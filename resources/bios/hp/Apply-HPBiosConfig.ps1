<#
.SYNOPSIS
    Applique une configuration BIOS HP sur la machine locale via HP Client Management Script Library (CMSL).
.DESCRIPTION
    Utilise le module PowerShell HP CMSL pour appliquer un fichier de config BIOS et optionnellement
    definir le mot de passe Setup. Concu pour une sequence de taches SCCM (nouvelle machine).
    Necessite l'installation du module HP.ClientManagementScriptLibrary sur la machine cible.
.NOTES
    Code de sortie : 0 = succes, 1 = erreur (module absent, non HP, exception).
    Logs CMSL : C:\ProgramData\HP\CMSL\Logs\
#>

# ========== CONFIGURATION - Modifier les valeurs ci-dessous ==========
# Fichier de configuration BIOS. Format BCU (repset), CSV, XML ou JSON. Relatif au script ou chemin absolu.
$ConfigPath = "bios_config_base.cfg"

# Mot de passe BIOS Setup : soit aleatoire (politique HP : 8 car. min, 1 MAJ, 1 min, 1 chiffre), soit fixe.
# Si $UseRandomPassword = $true : generation d'un mot de passe aleatoire (ecrit dans le log uniquement si defini avec succes).
# Si $UseRandomPassword = $false et $PasswordPlainText renseigne : utilisation de ce mot de passe (log uniquement si succes).
$UseRandomPassword = $true
$PasswordPlainText = ""

# Mot de passe BIOS actuel (si le BIOS en a deja un et que tu appliques la config). Vide = pas de MDP existant.
$CurrentBiosPassword = ""

# Dossier local pour le log (vide = %TEMP%). Survie au redeploiement MECM / mot de passe.
$LogLocalPath = ""
# Partage UNC ou deposer une copie du log (vide = pas de copie reseau).
$LogSharePath = ""

# Capturer l'etat du BIOS avant modification dans le log (section "BIOS AVANT MODIFICATION"). Long, mettre $false pour accelerer.
$CaptureBiosBefore = $true
# ====================================================================

try { chcp 65001 | Out-Null } catch { }
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Dossier Modules a cote du script (portable) : on l'ajoute au chemin de recherche des modules
$ModulesDir = Join-Path $ScriptDir "Modules"
if (Test-Path -LiteralPath $ModulesDir -PathType Container) {
    $env:PSModulePath = "$ModulesDir;$env:PSModulePath"
}

# Resoudre le chemin du fichier config
if ([System.IO.Path]::IsPathRooted($ConfigPath)) {
    $ConfigPathResolved = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($ConfigPath)
} else {
    $ConfigPathResolved = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath((Join-Path $ScriptDir $ConfigPath))
}

if (-not (Test-Path -LiteralPath $ConfigPathResolved -PathType Leaf)) {
    Write-Error "Fichier de config introuvable : $ConfigPathResolved"
    exit 1
}

# Fichier de log : toujours en local d'abord (mot de passe), puis copie optionnelle sur le partage.
$ComputerName = $env:COMPUTERNAME
if ([string]::IsNullOrWhiteSpace($ComputerName)) { $ComputerName = "Unknown" }
$TimeStamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$LogFileName = "BIOS_Apply_CMSL_{0}_{1}.log" -f $ComputerName, $TimeStamp

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
$LogFilePath = Join-Path $logDir $LogFileName

$LogFilePathShare = $null
if (-not [string]::IsNullOrWhiteSpace($LogSharePath)) {
    $LogSharePath = $LogSharePath.Trim().TrimEnd('\')
    $LogFilePathShare = Join-Path $LogSharePath $LogFileName
}

function Write-LogMessage {
    param([string]$Path, [string]$Text)
    if (-not $Path) { return }
    try {
        Add-Content -LiteralPath $Path -Value $Text -Encoding UTF8 -ErrorAction Stop
    } catch {
        Write-Warning "Impossible d'ecrire dans le log $Path : $_"
    }
}

# Retourne un tableau de lignes "  Nom`t: Valeur" pour toutes les options BIOS (comme Get-HPBiosAllSettings).
function Get-HPBiosSnapshotLines {
    $all = Get-HPBIOSSettingsList -ErrorAction Stop
    $lines = @()
    foreach ($s in $all) {
        $name = [string]$s.Name
        if ([string]::IsNullOrWhiteSpace($name)) { continue }
        $val = Get-HPBIOSSettingValue -Name $name -ErrorAction SilentlyContinue
        $lines += "  $name`t: $val"
    }
    return $lines
}

# Parse un fichier BCU (REPSET) et retourne les lignes "  Nom : Valeur" des parametres a appliquer.
function Get-BcuFileSettingsLines {
    param([string]$FilePath)
    $lines = @()
    $content = Get-Content -LiteralPath $FilePath -Encoding UTF8 -ErrorAction Stop
    $i = 0
    while ($i -lt $content.Count) {
        $line = $content[$i].TrimEnd()
        $i++
        if ($line.Length -eq 0 -or $line.StartsWith(";") -or $line -eq "BIOSConfig 1.0") { continue }
        $settingName = $line
        $valueToSet = $null
        while ($i -lt $content.Count) {
            $next = $content[$i]
            if ($next.Trim().Length -eq 0 -or $next.TrimStart().StartsWith(";")) { $i++; continue }
            if (-not $next.StartsWith("`t") -and -not $next.StartsWith(" ")) { break }
            $val = $next.Trim()
            if ($val.StartsWith("*")) { $valueToSet = $val.Substring(1).Trim() }
            elseif ($null -eq $valueToSet) { $valueToSet = $val }
            $i++
        }
        if (-not [string]::IsNullOrWhiteSpace($settingName) -and -not [string]::IsNullOrWhiteSpace($valueToSet)) {
            $lines += "  $settingName : $valueToSet"
        }
    }
    return $lines
}

# Genere un mot de passe aleatoire conforme a la politique BIOS HP (8 car. min, au moins 1 MAJ, 1 min, 1 chiffre).
function Get-RandomBiosPassword {
    # Lettres dont la touche/caractère est identique en AZERTY et QWERTY
    # (exclut A Q W Z M qui changent de place). Pas de symboles. Pas de 0/1/I/O (ambiguïté).
    $Length = 12
    $Upper  = 'ERTYUPSDFGHJKLXCVBN'
    $Lower  = 'ertyupsdfghjklxcvbn'
    $Digits = '23456789'
    $All    = $Upper + $Lower + $Digits
    $chars = [System.Collections.ArrayList]::new()
    [void]$chars.Add($Upper[(Get-Random -Maximum $Upper.Length)])
    [void]$chars.Add($Lower[(Get-Random -Maximum $Lower.Length)])
    [void]$chars.Add($Digits[(Get-Random -Maximum $Digits.Length)])
    for ($i = 3; $i -lt $Length; $i++) {
        [void]$chars.Add($All[(Get-Random -Maximum $All.Length)])
    }
    $shuffled = $chars | Get-Random -Count $chars.Count
    return -join $shuffled
}

# Charger le module CMSL (noms possibles selon install : HPCMSL, HP.ClientManagementScriptLibrary, HP.ClientManagement)
$ModuleNames = @("HPCMSL", "HP.ClientManagementScriptLibrary", "HP.ClientManagement")
$ModuleLoaded = $false
foreach ($ModuleName in $ModuleNames) {
    if (Get-Module -Name $ModuleName -ErrorAction SilentlyContinue) {
        $ModuleLoaded = $true
        break
    }
    if (Get-Module -Name $ModuleName -ListAvailable -ErrorAction SilentlyContinue) {
        Import-Module -Name $ModuleName -ErrorAction Stop
        $ModuleLoaded = $true
        break
    }
}
if (-not $ModuleLoaded) {
    Write-Error "Aucun module HP CMSL trouve (HPCMSL, HP.ClientManagementScriptLibrary, HP.ClientManagement). Installez depuis PowerShell : Install-Module -Name HPCMSL -Force -AcceptLicense"
    if ($LogFilePath) { Write-LogMessage -Path $LogFilePath -Text "ERREUR : Module CMSL non installe." }
    exit 1
}

# Verifier que la machine est un PC HP
try {
    $Manufacturer = Get-HPDeviceManufacturer -ErrorAction Stop
} catch {
    $Manufacturer = $null
}
if ($Manufacturer -notmatch "HP|Hewlett") {
    Write-Error "Cette machine n'est pas un PC HP (manufacturer: $Manufacturer). CMSL ne s'applique qu'aux appareils HP."
    if ($LogFilePath) { Write-LogMessage -Path $LogFilePath -Text "ERREUR : Machine non HP ($Manufacturer)." }
    exit 1
}

# Securite : si un mot de passe BIOS est deja defini, exiger le mot de passe actuel pour toute modification (depuis Windows aussi)
try {
    $BiosPasswordIsSet = Get-HPBIOSSetupPasswordIsSet -ErrorAction Stop
} catch {
    $BiosPasswordIsSet = $false
}
if ($BiosPasswordIsSet -and [string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
    Write-Error "Un mot de passe BIOS est defini sur cette machine. Indiquez-le dans la variable CurrentBiosPassword pour autoriser les modifications (config ou nouveau mot de passe). Sans cela, aucune modification ne sera appliquee."
    if ($LogFilePath) { Write-LogMessage -Path $LogFilePath -Text "ERREUR : Mot de passe BIOS defini mais CurrentBiosPassword non fourni." }
    exit 1
}

# Determiner le mot de passe a appliquer : aleatoire ou fixe (sans l'ecrire dans le log ici)
$PasswordToSet = $null
if ($UseRandomPassword) {
    $PasswordToSet = Get-RandomBiosPassword
} elseif (-not [string]::IsNullOrWhiteSpace($PasswordPlainText)) {
    $PasswordToSet = $PasswordPlainText
}

$StartTime = Get-Date
Write-Host "Apply-HPBiosConfig (CMSL) : application config BIOS HP." -ForegroundColor Cyan
Write-Host "  Config   : $ConfigPathResolved" -ForegroundColor Gray
if ($PasswordToSet) { Write-Host "  Mot de passe : $(if ($UseRandomPassword) { '(aleatoire)' } else { '(fixe)' })" -ForegroundColor Gray }
Write-Host "  Machine  : $($env:COMPUTERNAME)" -ForegroundColor Gray
if ($LogFilePath) { Write-Host "  Log      : $LogFilePath (local, puis copie vers partage)" -ForegroundColor Gray }

if ($LogFilePath) {
    $pwdLine = if ($PasswordToSet) { "Mot de passe a definir : $PasswordToSet" } else { "Mot de passe : (aucun)" }
    $header = @"
================================================================================
  Application config BIOS HP (CMSL) - $($StartTime.ToString('yyyy-MM-dd HH:mm:ss'))
================================================================================
Machine    : $($env:COMPUTERNAME)
Config     : $ConfigPathResolved
$pwdLine
--------------------------------------------------------------------------------
"@
    Write-LogMessage -Path $LogFilePath -Text $header
    if ($CaptureBiosBefore) {
        Write-Host "Capture du BIOS avant modification..." -ForegroundColor Gray
        Write-LogMessage -Path $LogFilePath -Text "=== BIOS AVANT MODIFICATION ==="
        $snapshotLines = Get-HPBiosSnapshotLines
        foreach ($line in $snapshotLines) { Write-LogMessage -Path $LogFilePath -Text $line }
        Write-LogMessage -Path $LogFilePath -Text ""
    }
}

$exitCode = 0

try {
    # 1) Appliquer la config BIOS depuis le fichier (format BCU / repset)
    Write-Host "Application de la config depuis le fichier..." -ForegroundColor Gray
    $params = @{
        File      = $ConfigPathResolved
        Format    = 'BCU'
        NoSummary = $false
    }
    if (-not [string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
        $params['Password'] = $CurrentBiosPassword
    }
    $configResult = Set-HPBIOSSettingValuesFromFile @params -ErrorAction Stop
    if ($LogFilePath) {
        Write-LogMessage -Path $LogFilePath -Text "=== MODIFICATIONS APPLIQUEES ==="
        Write-LogMessage -Path $LogFilePath -Text ""
        Write-LogMessage -Path $LogFilePath -Text "--- Parametres du fichier (contenu applique) ---"
        $bcuLines = Get-BcuFileSettingsLines -FilePath $ConfigPathResolved
        foreach ($line in $bcuLines) { Write-LogMessage -Path $LogFilePath -Text $line }
        Write-LogMessage -Path $LogFilePath -Text ""
        Write-LogMessage -Path $LogFilePath -Text "--- Resultat Set-HPBIOSSettingValuesFromFile ---"
        Write-LogMessage -Path $LogFilePath -Text "Fichier : $ConfigPathResolved"
        if ($null -ne $configResult) {
            $detail = $configResult | Out-String
            if (-not [string]::IsNullOrWhiteSpace($detail)) {
                foreach ($line in ($detail -split "`r?`n")) { Write-LogMessage -Path $LogFilePath -Text $line }
            }
        }
        Write-LogMessage -Path $LogFilePath -Text "Config appliquee : OK"
        Write-LogMessage -Path $LogFilePath -Text ""
    }

    # 2) Definir le mot de passe Setup si un mot de passe a ete choisi (aleatoire ou fixe)
    if (-not [string]::IsNullOrWhiteSpace($PasswordToSet)) {
        Write-Host "Definition du mot de passe BIOS Setup..." -ForegroundColor Gray
        $pwdParams = @{ NewPassword = $PasswordToSet }
        if (-not [string]::IsNullOrWhiteSpace($CurrentBiosPassword)) {
            $pwdParams['Password'] = $CurrentBiosPassword
        }
        Set-HPBIOSSetupPassword @pwdParams -ErrorAction Stop
        # Ecrire le mot de passe dans le log UNIQUEMENT car il a bien ete defini avec succes
        if ($LogFilePath) {
            Write-LogMessage -Path $LogFilePath -Text "--- Mot de passe Setup ---"
            Write-LogMessage -Path $LogFilePath -Text "Mot de passe Setup defini : OK ($(Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))"
            Write-LogMessage -Path $LogFilePath -Text "Mot de passe (a conserver) : $PasswordToSet"
            Write-LogMessage -Path $LogFilePath -Text ""
        }
    }

    Write-Host "Termine avec succes." -ForegroundColor Green
} catch {
    $exitCode = 1
    $msg = $_.Exception.Message
    Write-Warning "Erreur : $msg"
    if ($msg -match "password policy|Security.*policy") {
        Write-Host "Conseil : le mot de passe doit respecter la politique BIOS HP (souvent 8 car. min, 1 MAJ, 1 min, 1 chiffre)." -ForegroundColor Yellow
    }
    if ($LogFilePath) {
        Write-LogMessage -Path $LogFilePath -Text "ERREUR : $msg"
    }
}

$EndTime = Get-Date
if ($LogFilePath) {
    $footer = @"

--------------------------------------------------------------------------------
Resultat : $(if ($exitCode -eq 0) { 'SUCCES' } else { 'ECHEC' })
Fin      : $($EndTime.ToString('yyyy-MM-dd HH:mm:ss'))
================================================================================
"@
    Write-LogMessage -Path $LogFilePath -Text $footer

    # Copier le log local vers le partage, valider la copie, puis supprimer le local
    if ($LogFilePathShare -and (Test-Path -LiteralPath $LogFilePath -PathType Leaf)) {
        Write-LogMessage -Path $LogFilePath -Text "=== COPIE LOG VERS PARTAGE ==="
        Write-LogMessage -Path $LogFilePath -Text "Chemin local  : $LogFilePath"
        Write-LogMessage -Path $LogFilePath -Text "Chemin partage: $LogFilePathShare"
        $copyOk = $false
        try {
            Write-LogMessage -Path $LogFilePath -Text "Tentative de copie du log vers le partage..."
            Copy-Item -LiteralPath $LogFilePath -Destination $LogFilePathShare -Force -ErrorAction Stop
            if (Test-Path -LiteralPath $LogFilePathShare -PathType Leaf) {
                $localLen = (Get-Item -LiteralPath $LogFilePath -ErrorAction SilentlyContinue).Length
                $shareLen = (Get-Item -LiteralPath $LogFilePathShare -ErrorAction SilentlyContinue).Length
                Write-LogMessage -Path $LogFilePath -Text "Taille locale : $localLen octets"
                Write-LogMessage -Path $LogFilePath -Text "Taille partage: $shareLen octets"
                if ($localLen -eq $shareLen -and $localLen -gt 0) {
                    $copyOk = $true
                    Write-LogMessage -Path $LogFilePath -Text "Verification copie partage : OK"
                } else {
                    Write-LogMessage -Path $LogFilePath -Text "Verification copie partage : ECHEC (tailles differentes ou nulles)"
                }
            } else {
                Write-LogMessage -Path $LogFilePath -Text "Verification copie partage : ECHEC (fichier introuvable sur le partage)"
            }
        } catch {
            Write-Warning "Impossible de copier le log vers le partage : $_"
            $errMsg = $_.Exception.Message
            Write-LogMessage -Path $LogFilePath -Text "ERREUR copie vers partage : $errMsg"
        }
        if ($copyOk) {
            try {
                Remove-Item -LiteralPath $LogFilePath -Force -ErrorAction Stop
                Write-Host "Log copie sur le partage puis copie locale supprimee : $LogFilePathShare" -ForegroundColor Green
                Write-LogMessage -Path $LogFilePathShare -Text "Copie locale supprimee apres verification reussie."
            } catch {
                Write-Warning "Log copie sur $LogFilePathShare mais suppression du local a echoue : $_"
                $errMsg2 = $_.Exception.Message
                Write-LogMessage -Path $LogFilePath -Text "ERREUR suppression log local apres copie : $errMsg2"
            }
        } else {
            Write-Host "Log conserve en local (copie partage non validee) : $LogFilePath" -ForegroundColor Yellow
            Write-Host "  Contient le mot de passe BIOS en cas de succes." -ForegroundColor Yellow
            Write-LogMessage -Path $LogFilePath -Text "Copie partage NON validee -> log conserve en local."
        }
    } elseif ($LogFilePathShare) {
        # On avait un chemin de partage mais le log local n'existe plus (cas anormal)
        Write-LogMessage -Path $LogFilePath -Text "AVERTISSEMENT : Log local introuvable lors de la tentative de copie vers le partage."
    }
}

exit $exitCode
