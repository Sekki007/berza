# Google Play Store — KupiTelefon Android

Package: `rs.kupitelefon.app`  
Projekat: `mobile/android/` (Capacitor WebView → https://kupitelefon.rs)  
**Target SDK:** Android 16 (API 36) — obavezno od 31.08.2026.

---

## Šta je već spremno u kodu

- Capacitor Android shell, splash, ikone, deep links
- FCM push plugin + server API (`config/push.php`, `public/api/push_token.php`)
- Politika privatnosti: https://kupitelefon.rs/privatnost
- Uslovi korišćenja: https://kupitelefon.rs/uslovi
- Release signing preko `keystore.properties` (vidi skripte ispod)
- App Links: `public/.well-known/assetlinks.json`
- targetSdk / compileSdk **36** (Play Store zahtev 2026)

---

## Koraci koje TI moraš da uradiš (van repoa)

### 1) Google Play Console nalog (~25 USD jednokratno)

1. https://play.google.com/console
2. Kreiraj developer nalog
3. Nova aplikacija → **KupiTelefon**

### 2) Release keystore (JEDNOM — čuvaj lozinke!)

```powershell
cd C:\Projekti\berza
.\mobile\scripts\create-release-keystore.ps1
```

Kreira:
- `mobile/android/keystore/kupitelefon-release.jks`
- `mobile/android/keystore.properties` (gitignored)

**Bez ovog fajla i lozinki nema Play update-a.**

### 3) Firebase (push)

1. https://console.firebase.google.com → novi projekat
2. Dodaj Android app → package `rs.kupitelefon.app`
3. Preuzmi `google-services.json` → stavi u `mobile/android/app/google-services.json`
4. Project Settings → Service accounts → Generate new private key
5. Na serveru u `.env`:
   ```
   FCM_ENABLED=true
   FCM_PROJECT_ID=tvoj-projekat-id
   FCM_SERVICE_ACCOUNT_JSON=/putanja/do/service-account.json
   ```

Detalji: [PUSH_SETUP.md](./PUSH_SETUP.md)

### 4) App Links fingerprint

```powershell
.\mobile\scripts\print-cert-fingerprint.ps1
```

Kopiraj **release SHA256** u `public/.well-known/assetlinks.json`  
Ili automatski:

```powershell
.\mobile\scripts\print-cert-fingerprint.ps1
```

Skripta dodaje release + debug fingerprint u `assetlinks.json` ako već nisu tu.

Deploy `assetlinks.json` na produkciju pre testa App Links.

### 5) Build AAB za Play

```powershell
.\mobile\scripts\build-play-bundle.ps1
```

Upload fajl: `mobile/dist/KupiTelefon.aab`

---

## Play Console — obavezna polja

### Store listing (srpski)

**Naslov (max 30):** KupiTelefon — oglasi telefona

**Kratak opis (max 80):**  
Kupuj i prodaj telefone, delove i opremu. Besplatni oglasi za firme i fizička lica.

**Pun opis (primer):**  
KupiTelefon.rs — najbrže mesto za kupovinu i prodaju telefona u Srbiji.

• Telefoni, tableti, satovi  
• Delovi i oprema  
• Servisne usluge  
• Direktan kontakt sa prodavcem  
• Besplatno postavljanje oglasa  

Android aplikacija omogućava brži pristup, push obaveštenja o porukama i direktne linkove ka oglasima.  
Potreban je internet.

**Kategorija:** Shopping  
**Email:** podrska@kupitelefon.rs  
**Privacy policy URL:** https://kupitelefon.rs/privatnost  
**Website:** https://kupitelefon.rs

### Grafika (pripremi ručno)

| Asset | Dimenzije |
|-------|-----------|
| Ikona | 512×512 PNG |
| Feature graphic | 1024×500 |
| Phone screenshots | min. 2, 1080×1920 ili slično |

### Data safety (Google forma)

Označi da aplikacija prikuplja:

| Tip | Svrha | Deljenje |
|-----|-------|----------|
| Ime, telefon, email | Nalog, kontakt | Ne prodaje se |
| Poruke korisnika | Komunikacija | Ne prodaje se |
| Fotografije oglasa | Objava oglasa | Javno u oglasu |
| Device ID / FCM token | Push notifikacije | Firebase (Google) |
| Analytics (GA4) | Statistika | Google (uz saglasnost) |
| Crash logs | Stabilnost | Po potrebi hosting |

**Encryption in transit:** Da (HTTPS)  
**Users can request deletion:** Da (email podrska@kupitelefon.rs)  
**Account required:** Da (za oglase i poruke)

### Content rating

Popuni upitnik IARC — marketplace sa user-generated content.  
Verovatno **PEGI 3 / Everyone**, uz napomenu da korisnici objavljuju oglase.

### App access

Ako review traži test nalog, pripremi:
- test username / lozinka
- uputstvo: login → oglasi → poruke

---

## Test pre submit-a

- [ ] Login / registracija / SMS OTP
- [ ] Postavi oglas + upload slika
- [ ] Poruke + push notifikacija
- [ ] Tap na push → otvara pravi oglas/thread
- [ ] Deep link: https://kupitelefon.rs/oglas/123-...
- [ ] Odjava / brisanje naloga (ručno preko podrške dok nema self-delete)

```powershell
adb shell pm get-app-links rs.kupitelefon.app
```

---

## Verzije

U `mobile/android/app/build.gradle`:
- `versionCode` — uvećavaj za svaki Play upload (+1)
- `versionName` — prikaz korisnicima (npr. 1.12)

---

## Internal testing (preporučeno)

1. Play Console → Testing → Internal testing
2. Upload `KupiTelefon.aab`
3. Dodaj testere (email)
4. Posle 1–2 dana → Production

---

## Šta ne mogu automatski

- Kreiranje Play Console naloga
- Plaćanje Google developer fee
- Firebase projekat i `google-services.json` sa tvog Google naloga
- Screenshots iz Play Console
- Odobrenje Google review tima

Sve ostalo u repou je spremno za build i submit.
