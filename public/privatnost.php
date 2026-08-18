<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $requestPath === '/privatnost.php') {
    header('Location: /privatnost', true, 301);
    exit;
}

$site = siteSettings();
$siteName = (string)($site['site_name'] ?? 'KupiTelefon.rs');
$pageTitle = 'Politika privatnosti — ' . $siteName;
$pageDescription = 'Kako KupiTelefon.rs prikuplja, koristi i štiti podatke korisnika, uključujući Android aplikaciju, poruke, push obaveštenja i analitiku.';
$canonicalUrl = absoluteUrl('/privatnost');
$activePage = 'oglasi';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/">Početna</a> › Politika privatnosti</div>

        <div class="form-card">
            <h1>Politika privatnosti</h1>
            <p class="form-hint" style="margin-bottom:16px;">Poslednje ažuriranje: <?= date('d.m.Y.') ?></p>

            <div class="detail-desc legal-doc">
                <p>
                    Ova politika opisuje kako <?= h($siteName) ?> („mi“, „platforma“) obrađuje podatke kada koristiš
                    veb sajt <a href="https://kupitelefon.rs">kupitelefon.rs</a>, mobilnu Android aplikaciju
                    <strong>KupiTelefon</strong> (package: <code>rs.kupitelefon.app</code>) i povezane usluge.
                </p>

                <h2>1. Ko smo</h2>
                <p>
                    <?= h($siteName) ?> je online oglasnik za telefone, delove, opremu i servisne usluge.
                    Kontakt za privatnost i podršku: <a href="mailto:podrska@kupitelefon.rs">podrska@kupitelefon.rs</a>.
                </p>

                <h2>2. Koje podatke prikupljamo</h2>
                <ul>
                    <li><strong>Nalog:</strong> korisničko ime, ime i prezime, broj telefona, opciono email, lozinka (čuva se kao hash).</li>
                    <li><strong>Oglasi:</strong> naslov, opis, cena, lokacija, fotografije, kontakt telefon, podaci o prodavcu/ firmi.</li>
                    <li><strong>Poruke:</strong> sadržaj poruka između korisnika u vezi sa oglasima.</li>
                    <li><strong>Tehnički podaci:</strong> IP adresa, tip uređaja/pregledača, cookie identifikatori, log grešaka.</li>
                    <li><strong>Push (Android app):</strong> FCM token uređaja za slanje obaveštenja o porukama i aktivnostima na nalogu.</li>
                    <li><strong>Analitika (uz saglasnost):</strong> Google Analytics 4 i Meta Pixel — merenje poseta, registracija i objava oglasa.</li>
                </ul>

                <h2>3. Zašto koristimo podatke</h2>
                <ul>
                    <li>Kreiranje i održavanje korisničkog naloga</li>
                    <li>Objavljivanje i prikaz oglasa</li>
                    <li>Komunikacija između kupaca i prodavaca</li>
                    <li>Verifikacija telefona (SMS OTP) i bezbednost naloga</li>
                    <li>Push obaveštenja u Android aplikaciji</li>
                    <li>Statistika poseta i poboljšanje platforme (samo uz tvoju saglasnost za marketing kolačiće)</li>
                    <li>Sprečavanje zloupotrebe, spam-a i prevara</li>
                </ul>

                <h2>4. Pravni osnov</h2>
                <p>
                    Podatke obrađujemo radi izvršenja usluge (ugovor), legitimnog interesa (bezbednost platforme)
                    i, za analitiku/reklame, na osnovu tvoje saglasnosti putem cookie banera.
                </p>

                <h2>5. Deljenje podataka</h2>
                <p>Podatke ne prodajemo. Možemo koristiti pouzdane procesore:</p>
                <ul>
                    <li><strong>Hosting / server</strong> — smeštaj sajta i baze</li>
                    <li><strong>SMS gateway</strong> — slanje OTP kodova za verifikaciju</li>
                    <li><strong>Email (SMTP)</strong> — transakcione poruke i obaveštenja</li>
                    <li><strong>Google Firebase / FCM</strong> — push notifikacije u Android aplikaciji</li>
                    <li><strong>Google Analytics</strong> — statistika poseta (uz saglasnost)</li>
                    <li><strong>Meta (Facebook) Pixel</strong> — merenje kampanja (uz saglasnost)</li>
                </ul>
                <p>
                    Oglasi i kontakt podatke koje sam objaviš vide drugi korisnici platforme.
                    Kupovina i plaćanje se dogovaraju direktno između kupca i prodavca — platforma ne učestvuje u transakciji.
                </p>

                <h2>6. Kolačići</h2>
                <p>
                    Koristimo neophodne kolačiće za prijavu i bezbednost sesije.
                    Marketing/analitičke kolačiće (Google, Meta) aktiviramo samo ako izabereš „Prihvati sve“ u baneru.
                    Možeš promeniti izbor brisanjem kolačića <code>kt_marketing_consent</code> u pregledaču.
                </p>

                <h2>7. Android aplikacija</h2>
                <p>
                    Aplikacija prikazuje isti sadržaj kao sajt putem sigurne veze (HTTPS).
                    Za push obaveštenja traži dozvolu na Android 13+.
                    Aplikacija zahteva internet konekciju; ne radi potpuno offline.
                </p>

                <h2>8. Čuvanje podataka</h2>
                <p>
                    Podatke naloga i oglasa čuvamo dok koristiš uslugu ili dok ne zatražiš brisanje.
                    Logovi i backup kopije mogu se čuvati kraće ili duže radi bezbednosti i zakonskih obaveza.
                </p>

                <h2>9. Tvoja prava</h2>
                <p>Možeš zatražiti:</p>
                <ul>
                    <li>Pristup sopstvenim podacima</li>
                    <li>Ispravku netačnih podataka (u Nalog → Profil)</li>
                    <li>Brisanje naloga i povezanih oglasa</li>
                    <li>Povlačenje saglasnosti za marketing kolačiće</li>
                </ul>
                <p>Piši na <a href="mailto:podrska@kupitelefon.rs">podrska@kupitelefon.rs</a>.</p>

                <h2>10. Bezbednost</h2>
                <p>
                    Koristimo HTTPS, hash lozinki, CSRF zaštitu i ograničen pristup admin delu.
                    Ipak, nijedan internet servis ne može garantovati apsolutnu bezbednost.
                </p>

                <h2>11. Deca</h2>
                <p>Usluga nije namenjena osobama mlađim od 16 godina.</p>

                <h2>12. Izmene</h2>
                <p>
                    Politiku možemo ažurirati. Nova verzija biće objavljena na ovoj stranici sa datumom izmene.
                </p>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
