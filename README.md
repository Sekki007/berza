# KupiTelefon.rs

PHP marketplace za telefone, delove, opremu i servis — [kupitelefon.rs](https://kupitelefon.rs).

## Struktura

| Deo | Putanja |
|-----|---------|
| Javni sajt | `public/` |
| Konfiguracija | `config/` |
| Podaci (JSON) | `data/` |
| Android app | `mobile/android/` |
| Deploy (Nginx) | `DEPLOY.md` |

## Android / Google Play

- Brzi sideload build: [mobile/README.md](./mobile/README.md)
- **Play Store checklist:** [mobile/PLAY_STORE.md](./mobile/PLAY_STORE.md)
- Push (FCM): [mobile/PUSH_SETUP.md](./mobile/PUSH_SETUP.md)
- App Links: [mobile/ASSETLINKS.md](./mobile/ASSETLINKS.md)

## Lokalni razvoj

```bash
php -S localhost:8080 router.php
```

Env primer: `.env.example`

## Pravno

Politika privatnosti (produkcija): `/privatnost`
