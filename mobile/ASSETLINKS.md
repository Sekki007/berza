# Digital Asset Links — KupiTelefon Android app

Fajl `public/.well-known/assetlinks.json` mora biti dostupan na:

`https://kupitelefon.rs/.well-known/assetlinks.json`

## Fingerprints

Trenutno u fajlu je **debug** SHA-256 (sideload test).

Za **Google Play / release** dodaj fingerprint release keystore-a:

```powershell
.\mobile\scripts\print-cert-fingerprint.ps1
```

U `sha256_cert_fingerprints` niz može biti više vrednosti (debug + release):

```json
"sha256_cert_fingerprints": [
  "DB:75:69:... debug ...",
  "AA:BB:CC:... release ..."
]
```

Posle deploy-a proveri:

```powershell
adb shell pm get-app-links rs.kupitelefon.app
```

Package mora biti tačno: `rs.kupitelefon.app`
