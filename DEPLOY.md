# Deploy — KupiTelefon (PHP)

## Važno pre domena / IP putanje

Aplikacija koristi apsolutne URL-ove tipa `/index.php`, `/assets/...`.

**Preporuka:** DocumentRoot = folder `public/` (sajt na root-u domena ili IP-a).

Ne stavljaј projekat kao `http://IP/kupitelefon.rs/` bez dodatnog BASE_PATH (linkovi bi se lomili).

---

## Nginx (primer — kao na Contabo / VPS)

PHP socket proveri: `ls /run/php/` (npr. `php8.3-fpm.sock`).

```nginx
server {
    listen 80;
    server_name kupitelefon.rs www.kupitelefon.rs;

    root /var/www/berza/public;
    index index.php index.html;

    access_log /var/log/nginx/kupitelefon-access.log;
    error_log  /var/log/nginx/kupitelefon-error.log;

    # Pretty URL: /oglas/123-slug, /izlog/slug, /izlog/slug/kategorija, /usluge/..., /servisi/...
    rewrite ^/oglas/(\d+)(?:-.*)?/?$ /oglas.php?id=$1 last;
    rewrite ^/izlog/([^/]+)/([^/]+)/?$ /izlog.php?$args&u=$1&cat=$2&cat_from_path=1 last;
    rewrite ^/izlog/([^/]+)/?$ /izlog.php?$args&u=$1 last;
    rewrite ^/usluge/([^/]+)/?$ /usluge.php?u=$1 last;
    rewrite ^/servisi/([^/]+)/([^/]+)/?$ /servisi.php?city=$1&slug=$2&$args last;
    rewrite ^/servisi/([^/]+)/?$ /servisi.php?city=$1&$args last;
    rewrite ^/servisi/?$ /servisi.php?$args last;
    rewrite ^/index\.php$ / permanent;
    rewrite ^/(prijava|login)/?$ /login.php?$args last;
    rewrite ^/(registracija|registracij|register)/?$ /register.php?$args last;
    rewrite ^/(postavi-oglas|dodaj-oglas)/?$ /ad_form.php?$args last;
    rewrite ^/kako-radi/?$ /kako-radi.php?$args last;
    rewrite ^/privatnost/?$ /privatnost.php?$args last;
    rewrite ^/oglasi/?$ /index.php last;
    rewrite ^/oglasi/(.*)$ /index.php?$args last;
    rewrite ^/vodici/?$ /vodici.php last;
    rewrite ^/blog/?$ /vodici.php last;
    rewrite ^/(vodic|blog)/([^/]+)/?$ /vodic.php?slug=$2&$args last;
    rewrite ^/sitemap\.xml$ /sitemap.php last;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Dugo keširanje statičkih fajlova (PageSpeed: cache policy)
    location ~* ^/(assets|uploads)/ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
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

### HTTP / www → HTTPS (obavezno)

Ako neko pošalje `www.kupitelefon.rs` bez `https://`, telefoni i chat aplikacije često otvore **HTTP**.
Kod nas je provereno:

- `https://kupitelefon.rs` → radi
- `https://www.kupitelefon.rs` → radi
- `http://kupitelefon.rs` i `http://www.kupitelefon.rs` → **404** (Cloudflare)

**1) Cloudflare (glavni fix)**

1. Otvori [Cloudflare Dashboard](https://dash.cloudflare.com) → domen `kupitelefon.rs`
2. **SSL/TLS** → **Edge Certificates**
3. Uključi **Always Use HTTPS**
4. (Preporuka) **Rules** → **Redirect Rules** → nova pravila:
   - `http://*` → `https://kupitelefon.rs${uri}` (301), ili
   - `www.kupitelefon.rs/*` → `https://kupitelefon.rs/${path}` (301)

**2) Nginx (backup na origin serveru)**

Ispred glavnog `server` bloka dodaj:

```nginx
server {
    listen 80;
    server_name kupitelefon.rs www.kupitelefon.rs;
    return 301 https://kupitelefon.rs$request_uri;
}

server {
    listen 443 ssl http2;
    server_name www.kupitelefon.rs;
    # isti SSL cert kao za kupitelefon.rs (Let's Encrypt / Cloudflare origin)
    return 301 https://kupitelefon.rs$request_uri;
}
```

Glavni HTTPS `server` neka ima samo `server_name kupitelefon.rs;`.

Posle izmene: `sudo nginx -t && sudo systemctl reload nginx`.

---

## Opcija B — test samo preko IP (bez putanje)

```apache
DocumentRoot /var/www/berza/public
```

Pristup: `http://TVOJA_IP/`

---

## Opcija C — `http://IP/kupitelefon/` (podfolder) — NE preporučeno sad

Moguće je, ali treba BASE_PATH u celom projektu. Bolje Opcija A ili B dok testiraš.

Ako baš mora folder:

```bash
# /var/www/html/kupitelefon -> symlink na public
ln -s /var/www/berza/public /var/www/html/kupitelefon
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

### Cron za alerte (preporučeno)

Da alerti stižu i kada je sajt manje posećen, dodaj cron:

```bash
*/10 * * * * /usr/bin/php /var/www/berza/tools/run_alerts.php >> /var/log/kupitelefon-alerts.log 2>&1
```

---

## Git (sa tvog PC-a)

Ne radi samo `README.md` — gurni ceo projekat:

```bash
cd c:\Projekti\berza
git init
git add .
git commit -m "Initial KupiTelefon commit"
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
