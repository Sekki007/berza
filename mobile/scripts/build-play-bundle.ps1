# Build Google Play AAB (+ opciono APK za test)
# Pokreni: .\mobile\scripts\build-play-bundle.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$mobile = Join-Path $root 'mobile'
$android = Join-Path $mobile 'android'
$dist = Join-Path $mobile 'dist'

if (-not (Test-Path (Join-Path $android 'keystore.properties'))) {
    Write-Error 'Nema keystore.properties - prvo pokreni .\mobile\scripts\create-release-keystore.ps1'
    exit 1
}

$env:JAVA_HOME = if ($env:JAVA_HOME) { $env:JAVA_HOME } else { 'C:\Program Files\Android\Android Studio\jbr' }
$env:GRADLE_USER_HOME = if ($env:GRADLE_USER_HOME) { $env:GRADLE_USER_HOME } else { 'C:\gradle-home' }
$env:ANDROID_HOME = if ($env:ANDROID_HOME) { $env:ANDROID_HOME } else { 'C:\Android\Sdk' }

Push-Location $mobile
try {
    npx cap sync android
} finally {
    Pop-Location
}

Push-Location $android
try {
    .\gradlew.bat bundleRelease assembleRelease --no-daemon
} finally {
    Pop-Location
}

if (-not (Test-Path $dist)) {
    New-Item -ItemType Directory -Path $dist | Out-Null
}

$bundle = Join-Path $android 'app\build\outputs\bundle\release\app-release.aab'
$apk = Join-Path $android 'app\build\outputs\apk\release\app-release.apk'

if (Test-Path $bundle) {
    Copy-Item $bundle (Join-Path $dist 'KupiTelefon.aab') -Force
    Write-Host "Play bundle: $dist\KupiTelefon.aab"
}
if (Test-Path $apk) {
    Copy-Item $apk (Join-Path $dist 'KupiTelefon-release.apk') -Force
    Write-Host "Test APK: $dist\KupiTelefon-release.apk"
}

Write-Host 'Gotovo. Upload KupiTelefon.aab u Google Play Console - Internal testing ili Production.'
