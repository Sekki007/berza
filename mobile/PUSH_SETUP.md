# Push notifikacije (Android)

Kad korisnik dobije poruku, `notifyUser()` šalje i **FCM push** na registrovane telefone.

## Šta je urađeno u kodu

- Server: `config/push.php` + `public/api/push_token.php`
- Token se čuva u `data/push_tokens.json`
- Sajt u app-u (`app.js`) registruje FCM token posle logina
- Nalog → Podešavanja → “Push notifikacije na telefon”

## Šta TI moraš jednom u Firebase-u

Bez ovoga push **neće stizati** (Google zahteva FCM):

1. Otvori [Firebase Console](https://console.firebase.google.com/) → Create project
2. **Add Android app**, package name: `rs.kupitelefon.app`
3. Preuzmi `google-services.json` → stavi u:
   `mobile/android/app/google-services.json`
4. Project settings → **Service accounts** → Generate new private key  
   Sačuvaj npr. `C:\Projekti\berza\secrets\fcm-service-account.json` (ne commit-uj)
5. U `.env` na serveru:

```env
FCM_ENABLED=true
FCM_PROJECT_ID=tvoj-firebase-project-id
FCM_SERVICE_ACCOUNT_JSON=C:\Projekti\berza\secrets\fcm-service-account.json
```

6. Rebuild APK (posle `google-services.json`):

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

7. Instaliraj novi APK → uloguj se → pošalji test poruku sa drugog naloga.
