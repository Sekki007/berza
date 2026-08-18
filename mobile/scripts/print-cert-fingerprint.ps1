# Ispiše SHA-256 fingerprint za assetlinks.json (App Links)
# Pokreni: .\mobile\scripts\print-cert-fingerprint.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$propsFile = Join-Path $root 'mobile\android\keystore.properties'

if (-not (Test-Path $propsFile)) {
    Write-Host "Nema keystore.properties — prvo pokreni create-release-keystore.ps1"
    Write-Host "Debug fingerprint (trenutni assetlinks.json):"
}

$javaHome = $env:JAVA_HOME
if (-not $javaHome) { $javaHome = 'C:\Program Files\Android\Android Studio\jbr' }
$keytool = Join-Path $javaHome 'bin\keytool.exe'

if (Test-Path $propsFile) {
    $props = @{}
    Get-Content $propsFile | ForEach-Object {
        if ($_ -match '^\s*([^#=]+)=(.*)$') { $props[$matches[1].Trim()] = $matches[2].Trim() }
    }
    $storeFile = Join-Path (Split-Path $propsFile) ($props['storeFile'] -replace '\.\./', '')
    if (-not (Test-Path $storeFile)) {
        $storeFile = Join-Path $root 'mobile\android\' ($props['storeFile'] -replace '\.\./', '')
    }
    Write-Host "Release keystore: $storeFile"
    $pass = $props['storePassword']
    & $keytool -list -v -keystore $storeFile -alias $props['keyAlias'] -storepass $pass |
        Select-String 'SHA256:'
}

Write-Host "`nDebug keystore (sideload):"
& $keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android 2>$null |
    Select-String 'SHA256:'

Write-Host "`nKopiraj SHA256 u public/.well-known/assetlinks.json (format sa dvotačkama, velika slova)."
