# Deploy — TelefonBerza (PHP)

## Važno pre domena / IP putanje

Aplikacija koristi apsolutne URL-ove tipa `/index.php`, `/assets/...`.

**Preporuka:** DocumentRoot = folder `public/` (sajt na root-u domena ili IP-a).

Ne stavljaј projekat kao `http://IP/mobiberza.rs/` bez dodatnog BASE_PATH (linkovi bi se lomili).

---

## Opcija A — najbolja: domen `mobiberza.rs` → server

1. DNS kod registrara: A record `mobiberza.rs` → tvoja server IP
2. Apache virtual host (primer):

```apache
<VirtualHost *:80>
    ServerName mobiberza.rs
    ServerAlias www.mobiberza.rs
    DocumentRoot /var/www/berza/public

    <Directory /var/www/berza/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/berza-error.log
    CustomLog ${APACHE_LOG_DIR}/berza-access.log combined
</VirtualHost>
```

3. Kod u `/var/www/berza` (git clone), document root = `public/`
4. `router.php` nije potreban na Apache-u ako DocumentRoot = `public/`

Opciono HTTPS (certbot):

```bash
sudo certbot --apache -d mobiberza.rs -d www.mobiberza.rs
```

Pristup: `https://mobiberza.rs`

---

## Opcija B — test samo preko IP (bez putanje)

```apache
DocumentRoot /var/www/berza/public
```

Pristup: `http://TVOJA_IP/`

---

## Opcija C — `http://IP/mobiberza/` (podfolder) — NE preporučeno sad

Moguće je, ali treba BASE_PATH u celom projektu. Bolje Opcija A ili B dok testiraš.

Ako baš mora folder:

```bash
# /var/www/html/mobiberza -> symlink na public
ln -s /var/www/berza/public /var/www/html/mobiberza
```

…i onda sve linkove u kodu prilagoditi (kasnije).

---

## Deploy koraci (kratko)

```bash
cd /var/www
git clone git@github.com:Sekki007/berza.git berza
cd berza
cp .env.example .env
# uredi .env (DB_*)

# dozvole
chmod -R 775 data public/uploads
chown -R www-data:www-data data public/uploads

# MySQL (opciono za sada — app i dalje koristi JSON)
sudo mysql < database/schema.sql
# uredi .env pa:
php tools/import_json_to_mysql.php
```

PHP potreban: `pdo_mysql`, `gd` ili `imagick` (za slike), `mbstring`, `json`.

---

## Git (sa tvog PC-a)

Ne radi samo `README.md` — gurni ceo projekat:

```bash
cd c:\Projekti\berza
git init
git add .
git commit -m "Initial TelefonBerza commit"
git branch -M main
git remote add origin git@github.com:Sekki007/berza.git
git push -u origin main
```

Ako remote već postoji sa drugim istorijom:

```bash
git push -u origin main --force
```

(samo ako si siguran da GitHub repo može da se pregazi)

---

## Šta radi sada vs MySQL

| Sloj | Stanje |
|------|--------|
| Sajt (PHP) | **JSON** u `data/` — radi odmah na serveru |
| MySQL `schema.sql` | Spreman (sve tabele) |
| `tools/import_json_to_mysql.php` | Kopira JSON → MySQL |
| `STORAGE_DRIVER=mysql` | Sledeći korak (prebaciti data sloj) |

Za test na serveru **ne moraš** MySQL odmah — dovoljan je PHP + `data/` + `public/uploads`.
