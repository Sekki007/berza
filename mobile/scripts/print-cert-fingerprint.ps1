# Ispise SHA-256 fingerprint za assetlinks.json (App Links)
# Opciono azurira public/.well-known/assetlinks.json
# Pokreni: .\mobile\scripts\print-cert-fingerprint.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$propsFile = Join-Path $root 'mobile\android\keystore.properties'
$assetLinksFile = Join-Path $root 'public\.well-known\assetlinks.json'

function Get-Sha256Fingerprint {
    param([string]$Keytool, [string]$Keystore, [string]$Alias, [string]$StorePass)
    $out = & $Keytool -list -v -keystore $Keystore -alias $Alias -storepass $StorePass 2>$null
    $line = $out | Select-String 'SHA256:' | Select-Object -First 1
    if (-not $line) { return $null }
    $fp = ($line -replace '.*SHA256:\s*', '').Trim()
    return $fp.ToUpper()
}

function Update-AssetLinks {
    param([string[]]$Fingerprints)
    if (-not (Test-Path $assetLinksFile)) {
        Write-Warning "Nema $assetLinksFile"
        return
    }
    $json = Get-Content $assetLinksFile -Raw | ConvertFrom-Json
    $target = $json[0].target
    $existing = @($target.sha256_cert_fingerprints)
    $changed = $false
    foreach ($fp in $Fingerprints) {
        if ($fp -and ($existing -notcontains $fp)) {
            $existing += $fp
            $changed = $true
            Write-Host "Dodat fingerprint u assetlinks.json: $fp"
        }
    }
    if (-not $changed) {
        Write-Host 'assetlinks.json vec sadrzi sve fingerprint-e.'
        return
    }
    $target.sha256_cert_fingerprints = $existing
    $entry = [ordered]@{
        relation = @('delegate_permission/common.handle_all_urls')
        target   = $target
    }
    @($entry) | ConvertTo-Json -Depth 6 | Set-Content $assetLinksFile -Encoding UTF8
    Write-Host "Azuriran: $assetLinksFile"
}

$javaHome = $env:JAVA_HOME
if (-not $javaHome) { $javaHome = 'C:\Program Files\Android\Android Studio\jbr' }
$keytool = Join-Path $javaHome 'bin\keytool.exe'

$allFps = @()

if (-not (Test-Path $propsFile)) {
    Write-Host 'Nema keystore.properties - prvo pokreni create-release-keystore.ps1'
} else {
    $props = @{}
    Get-Content $propsFile | ForEach-Object {
        if ($_ -match '^\s*([^#=]+)=(.*)$') { $props[$matches[1].Trim()] = $matches[2].Trim() }
    }
    $storeFile = Join-Path (Split-Path $propsFile) ($props['storeFile'] -replace '\.\./', '')
    if (-not (Test-Path $storeFile)) {
        $storeFile = Join-Path $root 'mobile\android\' ($props['storeFile'] -replace '\.\./', '')
    }
    Write-Host "Release keystore: $storeFile"
    $releaseFp = Get-Sha256Fingerprint -Keytool $keytool -Keystore $storeFile -Alias $props['keyAlias'] -StorePass $props['storePassword']
    if ($releaseFp) {
        Write-Host "Release SHA256: $releaseFp"
        $allFps += $releaseFp
    }
}

Write-Host ''
Write-Host 'Debug keystore (sideload):'
$debugKeystore = Join-Path $env:USERPROFILE '.android\debug.keystore'
if (Test-Path $debugKeystore) {
    $debugFp = Get-Sha256Fingerprint -Keytool $keytool -Keystore $debugKeystore -Alias 'androiddebugkey' -StorePass 'android'
    if ($debugFp) {
        Write-Host "Debug SHA256: $debugFp"
        $allFps += $debugFp
    }
}

if ($allFps.Count -gt 0) {
    Update-AssetLinks -Fingerprints $allFps
    Write-Host ''
    Write-Host 'Deploy assetlinks.json na produkciju pre testa App Links.'
}
