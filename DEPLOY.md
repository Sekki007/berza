# Deploy — TelefonBerza (PHP)

## Važno pre domena / IP putanje

Aplikacija koristi apsolutne URL-ove tipa `/index.php`, `/assets/...`.

**Preporuka:** DocumentRoot = folder `public/` (sajt na root-u domena ili IP-a).

Ne stavljaј projekat kao `http://IP/mobiberza.rs/` bez dodatnog BASE_PATH (linkovi bi se lomili).

---

## Nginx (primer — kao na Contabo / VPS)

PHP socket proveri: `ls /run/php/` (npr. `php8.3-fpm.sock`).

```nginx
server {
    listen 80;
    server_name berza.duckdns.org;

    root /var/www/berza/public;
    index index.php index.html;

    access_log /var/log/nginx/berza-access.log;
    error_log  /var/log/nginx/berza-error.log;

    # Pretty URL: /oglas/123-slug, /izlog/username, /usluge/username
    rewrite ^/oglas/(\d+)(?:-.*)?/?$ /oglas.php?id=$1 last;
    rewrite ^/izlog/([^/]+)/?$ /izlog.php?u=$1 last;
    rewrite ^/usluge/([^/]+)/?$ /usluge.php?u=$1 last;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Bez ova `rewrite` pravila, pretty URL linkovi neće raditi (otvaranje oglasa, izloga i mini sajta).


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

# MySQL (preporučeno)
sudo mysql < database/schema.sql
# uredi .env pa:
php tools/import_json_to_mysql.php
php tools/validate_mysql_import.php
# kratki downtime/cutover:
php tools/mysql_cutover.php --apply
# post-cutover health check:
php tools/mysql_post_cutover_check.php
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

## Storage režimi

| Režim | Opis |
|------|------|
| `STORAGE_DRIVER=json` | Čitanje/pisanje iz `data/*.json` |
| `STORAGE_DRIVER=mysql` | Čitanje/pisanje iz MySQL tabele `json_documents` |

Rollback:

```bash
php tools/mysql_rollback_to_json.php --backup backups/mysql-cutover-YYYYmmdd_HHMMSS
```
