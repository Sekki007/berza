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
- Validacija importa: `php tools/validate_mysql_import.php`
- Cutover (snapshot + import + `STORAGE_DRIVER=mysql`): `php tools/mysql_cutover.php --apply`
- Post-cutover check: `php tools/mysql_post_cutover_check.php`
- Rollback na JSON: `php tools/mysql_rollback_to_json.php --backup backups/mysql-cutover-YYYYmmdd_HHMMSS`
- Kopiraj `.env.example` → `.env` i podesi `DB_*`

Aplikacija podržava oba drajvera preko `STORAGE_DRIVER`:
- `json` → čita/piše `data/*.json`
- `mysql` → čita/piše MySQL tabelu `json_documents`

## Struktura

| Put | Opis |
|-----|------|
| `public/` | Web root (DocumentRoot na serveru) |
| `config/` | PHP logika |
| `data/` | JSON podaci |
| `database/schema.sql` | MySQL tabele |
| `tools/` | Import / utility skripte |
