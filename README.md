# TelefonBerza (PHP + JSON + JavaScript)

Berza oglasa za mobilne telefone, delove i servisne usluge.

## Pokretanje (lokalno)

```bash
php -S localhost:8000 router.php
```

http://localhost:8000

Demo admin: `admin` / `admin123`

## Deploy

Vidi [DEPLOY.md](DEPLOY.md) — Apache, domen `mobiberza.rs`, Git, MySQL.

## MySQL

- Schema: `database/schema.sql`
- Import iz JSON: `php tools/import_json_to_mysql.php`
- Kopiraj `.env.example` → `.env` i podesi `DB_*`

Aplikacija **trenutno radi na JSON** (`data/`). MySQL je spreman; prelazak koda na MySQL je sledeći korak (`STORAGE_DRIVER`).

## Struktura

| Put | Opis |
|-----|------|
| `public/` | Web root (DocumentRoot na serveru) |
| `config/` | PHP logika |
| `data/` | JSON podaci |
| `database/schema.sql` | MySQL tabele |
| `tools/` | Import / utility skripte |
