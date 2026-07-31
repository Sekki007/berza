# KupiTelefon Android (APK)

Android wrapper oko [https://kupitelefon.rs](https://kupitelefon.rs) (Capacitor WebView).

- **Package:** `rs.kupitelefon.app`
- **App name:** KupiTelefon

## Gotov APK

Instaliraj ovaj fajl na telefon:

**`C:\Projekti\berza\mobile\dist\KupiTelefon.apk`** (~2.9 MB)

### Instalacija

1. Prebaci APK na telefon (USB, Drive, Telegram…).
2. **Podešavanja → Bezbednost** → dozvoli instalaciju iz nepoznatih izvora.
3. Otvori APK i instaliraj.
4. App otvara `https://kupitelefon.rs`.

## Ponovni build

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

## Push notifikacije

Vidi [PUSH_SETUP.md](./PUSH_SETUP.md) — treba Firebase (`google-services.json` + service account u `.env`).

## Napomena

Ovo nije Play Store build (potpisan debug keystore-om radi sideload-a). Za Google Play treba zaseban release keystore i Play Console.
