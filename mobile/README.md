# KupiTelefon Android (APK / AAB)

Android wrapper oko [https://kupitelefon.rs](https://kupitelefon.rs) (Capacitor WebView).

- **Package:** `rs.kupitelefon.app`
- **App name:** KupiTelefon
- **Verzija:** vidi `android/app/build.gradle` (`versionCode`, `versionName`)

## Brzo

| Cilj | Komanda | Rezultat |
|------|---------|----------|
| Sideload APK (debug potpis) | vidi „Ponovni build“ ispod | `dist/KupiTelefon.apk` |
| **Google Play AAB** | `.\scripts\build-play-bundle.ps1` | `dist/KupiTelefon.aab` |
| Release keystore (1×) | `.\scripts\create-release-keystore.ps1` | `android/keystore/` |
| SHA za App Links | `.\scripts\print-cert-fingerprint.ps1` | → `assetlinks.json` |

## Google Play Store

**Kompletan vodič:** [PLAY_STORE.md](./PLAY_STORE.md)

Obavezno pre submit-a:
1. `create-release-keystore.ps1` + `keystore.properties`
2. Firebase `google-services.json` + FCM u `.env`
3. `print-cert-fingerprint.ps1` → ažurira `public/.well-known/assetlinks.json` (release fingerprint)
4. Privacy URL: https://kupitelefon.rs/privatnost
5. `build-play-bundle.ps1` → upload `dist/KupiTelefon.aab`

## Ponovni build (sideload / test)

Na ovom PC-u Gradle mora da koristi home **bez** `!` u putanji (Windows korisnik `daki!`):

```powershell
cd C:\Projekti\berza\mobile
$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"
$env:GRADLE_USER_HOME = "C:\gradle-home"
$env:ANDROID_HOME = "C:\Android\Sdk"
npx cap sync android
cd android
.\gradlew.bat assembleRelease --no-daemon
Copy-Item app\build\outputs\apk\release\app-release.apk ..\dist\KupiTelefon.apk -Force
```

Bez `keystore.properties` APK je potpisan debug ključem (OK za test, ne za Play).

## Push notifikacije

Vidi [PUSH_SETUP.md](./PUSH_SETUP.md) — Firebase (`google-services.json` + service account u `.env`).

## App Links

Vidi [ASSETLINKS.md](./ASSETLINKS.md).
