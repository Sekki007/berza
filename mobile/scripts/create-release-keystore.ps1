# Generise release keystore za Google Play (JEDNOM - cuvaj lozinke!).
# Pokreni: .\mobile\scripts\create-release-keystore.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$keystoreDir = Join-Path $root 'mobile\android\keystore'
$keystoreFile = Join-Path $keystoreDir 'kupitelefon-release.jks'
$propsExample = Join-Path $root 'mobile\android\keystore.properties.example'
$propsFile = Join-Path $root 'mobile\android\keystore.properties'

if (-not (Test-Path $keystoreDir)) {
    New-Item -ItemType Directory -Path $keystoreDir | Out-Null
}

if (Test-Path $keystoreFile) {
    Write-Host "Keystore vec postoji: $keystoreFile"
    exit 0
}

$javaHome = $env:JAVA_HOME
if (-not $javaHome) {
    $javaHome = 'C:\Program Files\Android\Android Studio\jbr'
}
$keytool = Join-Path $javaHome 'bin\keytool.exe'
if (-not (Test-Path $keytool)) {
    throw 'keytool nije pronadjen. Postavi JAVA_HOME ili instaliraj Android Studio JBR.'
}

Write-Host 'Unesi lozinku za keystore (minimum 6 karaktera). Sacuvaj je - bez nje nema Play update-a!'
$storePass = Read-Host 'Store password' -AsSecureString
$storePassPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($storePass)
)
$keyPassPlain = $storePassPlain

$dname = 'CN=KupiTelefon, OU=Mobile, O=KupiTelefon.rs, L=Beograd, ST=Serbia, C=RS'
& $keytool -genkeypair -v `
    -keystore $keystoreFile `
    -alias kupitelefon `
    -keyalg RSA -keysize 2048 -validity 10000 `
    -storepass $storePassPlain -keypass $keyPassPlain `
    -dname $dname

Write-Host ''
Write-Host "Keystore kreiran: $keystoreFile"

if (-not (Test-Path $propsFile)) {
    Copy-Item $propsExample $propsFile
    $content = Get-Content $propsFile -Raw
    $content = $content -replace 'PROMENI_LOZINKU', $storePassPlain
    Set-Content -Path $propsFile -Value $content -NoNewline
    Write-Host 'Kreiran keystore.properties - proveri putanju storeFile.'
}

Write-Host ''
Write-Host 'Sledece: pokreni print-cert-fingerprint.ps1 (azurira assetlinks.json) i deploy na produkciju.'
