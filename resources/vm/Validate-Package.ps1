param(
  [Parameter(Mandatory = $true)]
  [ValidateSet('Install', 'Finish', 'Abort')]
  [string]$Phase,

  [Parameter(Mandatory = $true)][string]$Zip,
  [Parameter(Mandatory = $true)][string]$Job
)

$script:RunnerPath = $MyInvocation.MyCommand.Path
$ErrorActionPreference = 'Continue'
$result = [ordered]@{
  ok = $false
  phase = $Phase
  awaiting_confirmation = $false
  aborted = $false
  install_exit = $null
  uninstall_exit = $null
  detection_after_install = $null
  detection_after_uninstall = $null
  witness_path = ''
  cleanup_ok = $false
  message = ''
  install_script = ''
  uninstall_script = ''
  job_dir = $Job
  zip_path = $Zip
}

function Get-WitnessPathFromPackage {
  param([string]$Root)
  $det = Get-ChildItem -LiteralPath $Root -Recurse -Filter 'detection.txt' -ErrorAction SilentlyContinue | Select-Object -First 1
  if (-not $det) { return $null }
  $text = Get-Content -LiteralPath $det.FullName -Raw -ErrorAction SilentlyContinue
  if (-not $text) { return $null }
  if ($text -match '(?im)Chemin\s*:\s*(C:\\SCCM_Temoins\\[^\r\n]+)') {
    return $Matches[1].Trim()
  }
  if ($text -match '(?im)(C:\\SCCM_Temoins\\[^\r\n]+)') {
    return $Matches[1].Trim()
  }
  return $null
}

function Remove-WitnessCompletely {
  param([string]$WitnessPath)
  if ([string]::IsNullOrWhiteSpace($WitnessPath)) { return $true }
  try {
    if (Test-Path -LiteralPath $WitnessPath) {
      Remove-Item -LiteralPath $WitnessPath -Force -ErrorAction Stop
      Write-Output "CLEANUP temoin supprime : $WitnessPath"
    } else {
      Write-Output "CLEANUP temoin deja absent : $WitnessPath"
    }
    $parent = Split-Path -Parent $WitnessPath
    if ($parent -and (Test-Path -LiteralPath $parent)) {
      $left = @(Get-ChildItem -LiteralPath $parent -Force -ErrorAction SilentlyContinue)
      if ($left.Count -eq 0) {
        Remove-Item -LiteralPath $parent -Force -ErrorAction SilentlyContinue
        Write-Output "CLEANUP dossier app temoin vide : $parent"
      }
    }
    return -not (Test-Path -LiteralPath $WitnessPath)
  } catch {
    Write-Output "CLEANUP erreur : $($_.Exception.Message)"
    return $false
  }
}

function Remove-JobArtifacts {
  param([string]$ZipPath, [string]$JobPath, [bool]$RemoveRunner)
  try {
    if ($ZipPath -and (Test-Path -LiteralPath $ZipPath)) {
      Remove-Item -LiteralPath $ZipPath -Force -ErrorAction SilentlyContinue
    }
    if ($JobPath -and (Test-Path -LiteralPath $JobPath)) {
      Remove-Item -LiteralPath $JobPath -Recurse -Force -ErrorAction SilentlyContinue
    }
    if ($RemoveRunner -and $script:RunnerPath -and (Test-Path -LiteralPath $script:RunnerPath)) {
      Remove-Item -LiteralPath $script:RunnerPath -Force -ErrorAction SilentlyContinue
    }
    Write-Output 'CLEANUP job+zip distant OK'
  } catch {
    Write-Output "CLEANUP job erreur : $($_.Exception.Message)"
  }
}

function Resolve-PackageScripts {
  param([string]$JobPath)
  $install = Get-ChildItem -LiteralPath $JobPath -Recurse -Filter 'install.ps1' -ErrorAction SilentlyContinue | Select-Object -First 1
  $uninstall = Get-ChildItem -LiteralPath $JobPath -Recurse -Filter 'uninstall.ps1' -ErrorAction SilentlyContinue | Select-Object -First 1
  return @{ Install = $install; Uninstall = $uninstall }
}

# JSON ASCII maison : ConvertTo-Json PS5.1 est trop fragile (null / accents / chemins).
function ConvertTo-ResultJson {
  param($Data)
  $esc = {
    param($s)
    if ($null -eq $s) { return '' }
    $t = [string]$s
    $t = $t.Replace('\', '\\').Replace('"', '\"').Replace("`r", '\r').Replace("`n", '\n').Replace("`t", '\t')
    return $t
  }
  $bool = {
    param($v)
    if ($v -eq $true) { return 'true' }
    return 'false'
  }
  $numOrNull = {
    param($v)
    if ($null -eq $v -or $v -eq '') { return 'null' }
    return ([string][int]$v)
  }
  $strOrNull = {
    param($v)
    if ($null -eq $v -or $v -eq '') { return 'null' }
    return ('"' + (& $esc $v) + '"')
  }
  $triBool = {
    param($v)
    if ($null -eq $v) { return 'null' }
    return (& $bool $v)
  }

  $parts = @(
    ('"ok":' + (& $bool $Data['ok']))
    ('"phase":"' + (& $esc $Data['phase']) + '"')
    ('"awaiting_confirmation":' + (& $bool $Data['awaiting_confirmation']))
    ('"aborted":' + (& $bool $Data['aborted']))
    ('"install_exit":' + (& $numOrNull $Data['install_exit']))
    ('"uninstall_exit":' + (& $numOrNull $Data['uninstall_exit']))
    ('"detection_after_install":' + (& $triBool $Data['detection_after_install']))
    ('"detection_after_uninstall":' + (& $triBool $Data['detection_after_uninstall']))
    ('"witness_path":' + (& $strOrNull $Data['witness_path']))
    ('"cleanup_ok":' + (& $bool $Data['cleanup_ok']))
    ('"message":"' + (& $esc $Data['message']) + '"')
    ('"install_script":' + (& $strOrNull $Data['install_script']))
    ('"uninstall_script":' + (& $strOrNull $Data['uninstall_script']))
    ('"job_dir":' + (& $strOrNull $Data['job_dir']))
    ('"zip_path":' + (& $strOrNull $Data['zip_path']))
  )
  return ('{' + ($parts -join ',') + '}')
}

try {
  if ($Phase -eq 'Install') {
    New-Item -ItemType Directory -Force -Path $Job | Out-Null
    if (-not (Test-Path -LiteralPath $Zip)) {
      throw "ZIP introuvable sur la VM : $Zip"
    }
    Expand-Archive -LiteralPath $Zip -DestinationPath $Job -Force

    $scripts = Resolve-PackageScripts -JobPath $Job
    if (-not $scripts.Install) { throw 'install.ps1 introuvable dans le ZIP' }
    if (-not $scripts.Uninstall) { throw 'uninstall.ps1 introuvable dans le ZIP' }

    $result.install_script = $scripts.Install.FullName
    $result.uninstall_script = $scripts.Uninstall.FullName
    $pkgRoot = $scripts.Install.DirectoryName
    Set-Location -LiteralPath $pkgRoot

    $witness = Get-WitnessPathFromPackage -Root $pkgRoot
    if (-not $witness) {
      throw 'Impossible de lire le chemin temoin depuis detection.txt'
    }
    $result.witness_path = $witness
    Write-Output "WITNESS_PATH=$witness"

    Write-Output '=== PRECLEAN (temoin) ==='
    [void](Remove-WitnessCompletely -WitnessPath $witness)

    Write-Output "=== INSTALL $($scripts.Install.FullName) ==="
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $scripts.Install.FullName
    $result.install_exit = [int]$LASTEXITCODE
    Write-Output "INSTALL_EXIT=$($result.install_exit)"

    if ($result.install_exit -ne 0 -and $result.install_exit -ne 3010) {
      $result.message = "Installation echouee (exit $($result.install_exit))"
      Write-Output '=== CLEANUP apres echec install ==='
      [void](Remove-WitnessCompletely -WitnessPath $witness)
      Remove-JobArtifacts -ZipPath $Zip -JobPath $Job -RemoveRunner $true
    } else {
      Write-Output '=== DETECTION apres install (temoin doit exister) ==='
      $result.detection_after_install = [bool](Test-Path -LiteralPath $witness)
      Write-Output "DETECTION_AFTER_INSTALL=$($result.detection_after_install)"

      if (-not $result.detection_after_install) {
        $result.message = "Detection KO apres install : temoin absent ($witness)"
        Remove-JobArtifacts -ZipPath $Zip -JobPath $Job -RemoveRunner $true
      } else {
        $result.ok = $true
        $result.awaiting_confirmation = $true
        $result.message = 'Install + detection OK - en attente de confirmation operateur avant uninstall'
        Write-Output '=== PAUSE CONFIRMATION OPERATEUR ==='
        Write-Output 'Logiciel laisse installe. Job+ZIP conserves pour la phase Finish/Abort.'
        Write-Output 'Verifiez manuellement sur la VM puis confirmez dans MECM Packaging Manager.'
      }
    }
  }
  elseif ($Phase -eq 'Finish') {
    if (-not (Test-Path -LiteralPath $Job)) {
      throw "Dossier job introuvable pour Finish : $Job"
    }
    $scripts = Resolve-PackageScripts -JobPath $Job
    if (-not $scripts.Uninstall) { throw 'uninstall.ps1 introuvable pour Finish' }

    $result.uninstall_script = $scripts.Uninstall.FullName
    $pkgRoot = $scripts.Uninstall.DirectoryName
    $witness = Get-WitnessPathFromPackage -Root $pkgRoot
    if (-not $witness) {
      throw 'Impossible de lire le chemin temoin pour Finish'
    }
    $result.witness_path = $witness
    $result.detection_after_install = $true
    Write-Output "WITNESS_PATH=$witness"
    Write-Output "=== UNINSTALL $($scripts.Uninstall.FullName) ==="
    Set-Location -LiteralPath $pkgRoot
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $scripts.Uninstall.FullName
    $result.uninstall_exit = [int]$LASTEXITCODE
    Write-Output "UNINSTALL_EXIT=$($result.uninstall_exit)"

    if ($result.uninstall_exit -ne 0 -and $result.uninstall_exit -ne 3010) {
      $result.message = "Desinstallation echouee (exit $($result.uninstall_exit))"
    } else {
      Write-Output '=== DETECTION apres uninstall (temoin doit etre absent) ==='
      $stillThere = [bool](Test-Path -LiteralPath $witness)
      if ($stillThere) {
        Write-Output 'Temoin encore present apres uninstall - suppression forcee'
      }
      $result.cleanup_ok = [bool](Remove-WitnessCompletely -WitnessPath $witness)
      $result.detection_after_uninstall = [bool](Test-Path -LiteralPath $witness)
      Write-Output "DETECTION_AFTER_UNINSTALL_PRESENT=$($result.detection_after_uninstall) CLEANUP_OK=$($result.cleanup_ok)"

      if ($result.detection_after_uninstall) {
        $result.message = "Detection KO apres uninstall : temoin encore present ($witness)"
      } elseif (-not $result.cleanup_ok) {
        $result.message = "Cleanup temoin incomplet ($witness)"
      } else {
        $result.ok = $true
        $result.message = 'Install + confirmation + uninstall + cleanup temoin OK'
      }
    }

    Write-Output '=== FINAL CLEANUP job ==='
    Remove-JobArtifacts -ZipPath $Zip -JobPath $Job -RemoveRunner $true
  }
  elseif ($Phase -eq 'Abort') {
    $result.aborted = $true
    $result.message = 'Annule par operateur - logiciel laisse installe sur la VM'

    if (Test-Path -LiteralPath $Job) {
      $scripts = Resolve-PackageScripts -JobPath $Job
      $pkgRoot = $null
      if ($scripts.Install) { $pkgRoot = $scripts.Install.DirectoryName }
      elseif ($scripts.Uninstall) { $pkgRoot = $scripts.Uninstall.DirectoryName }
      if ($pkgRoot) {
        $witness = Get-WitnessPathFromPackage -Root $pkgRoot
        if ($witness) {
          $result.witness_path = $witness
          Write-Output '=== CLEANUP temoin (abort) ==='
          $result.cleanup_ok = [bool](Remove-WitnessCompletely -WitnessPath $witness)
        }
      }
    }

    Write-Output '=== CLEANUP job (abort, logiciel conserve) ==='
    Remove-JobArtifacts -ZipPath $Zip -JobPath $Job -RemoveRunner $true
    Write-Output 'ABORT_OK=True'
  }
} catch {
  $result.message = $_.Exception.Message
  Write-Output "ERROR: $($result.message)"
  if ($Phase -eq 'Install') {
    try { Remove-JobArtifacts -ZipPath $Zip -JobPath $Job -RemoveRunner $true } catch {}
  }
}

Write-Output ('__VM_RESULT__' + (ConvertTo-ResultJson -Data $result))

if ($Phase -eq 'Abort') { exit 0 }
if (-not $result.ok) { exit 2 }
exit 0
