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
$updatedAt = '18.08.2026.';
$contactEmail = 'podrska@kupitelefon.rs';

$toc = [
    ['id' => 'clanak-1', 'label' => 'Ko smo'],
    ['id' => 'clanak-2', 'label' => 'Podaci koje prikupljamo'],
    ['id' => 'clanak-3', 'label' => 'Svrha obrade'],
    ['id' => 'clanak-4', 'label' => 'Pravni osnov'],
    ['id' => 'clanak-5', 'label' => 'Deljenje podataka'],
    ['id' => 'clanak-6', 'label' => 'Kolačići'],
    ['id' => 'clanak-7', 'label' => 'Android aplikacija'],
    ['id' => 'clanak-8', 'label' => 'Rok čuvanja'],
    ['id' => 'clanak-9', 'label' => 'Prava korisnika'],
    ['id' => 'clanak-10', 'label' => 'Bezbednost'],
    ['id' => 'clanak-11', 'label' => 'Maloletna lica'],
    ['id' => 'clanak-12', 'label' => 'Izmene politike'],
];

require __DIR__ . '/partials/layout-start.php';
?>

<div class="legal-page">
    <div class="breadcrumb"><a href="/">Početna</a> › Politika privatnosti</div>

    <header class="legal-hero">
        <p class="legal-kicker">Pravni dokument</p>
        <h1>Politika privatnosti</h1>
        <p class="legal-lead">
            Ova politika opisuje kako <?= h($siteName) ?> („mi“, „platforma“) obrađuje lične podatke
            kada koristiš veb sajt <a href="https://kupitelefon.rs">kupitelefon.rs</a>,
            Android aplikaciju <strong>KupiTelefon</strong> i povezane usluge.
        </p>
        <dl class="legal-meta">
            <div>
                <dt>Operator</dt>
                <dd><?= h($siteName) ?></dd>
            </div>
            <div>
                <dt>Package</dt>
                <dd><code>rs.kupitelefon.app</code></dd>
            </div>
            <div>
                <dt>Poslednje ažuriranje</dt>
                <dd><?= h($updatedAt) ?></dd>
            </div>
            <div>
                <dt>Kontakt</dt>
                <dd><a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a></dd>
            </div>
        </dl>
    </header>

    <div class="legal-layout">
        <nav class="legal-toc" aria-label="Sadržaj">
            <h2>Sadržaj</h2>
            <ol>
                <?php foreach ($toc as $i => $item): ?>
                    <li><a href="#<?= h($item['id']) ?>"><?= ($i + 1) . '. ' . h($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="legal-body">
            <article class="legal-article" id="clanak-1">
                <h2><span>Član 1.</span> Ko smo</h2>
                <p>
                    <?= h($siteName) ?> je online oglasnik za telefone, delove, opremu i servisne usluge.
                    Pitanja u vezi sa privatnošću šalji na
                    <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a>.
                </p>
            </article>

            <article class="legal-article" id="clanak-2">
                <h2><span>Član 2.</span> Podaci koje prikupljamo</h2>
                <div class="legal-table-wrap">
                    <table class="legal-table">
                        <thead>
                            <tr>
                                <th>Kategorija</th>
                                <th>Primeri</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Nalog</th>
                                <td>Korisničko ime, ime i prezime, broj telefona, opciono email, lozinka (hash)</td>
                            </tr>
                            <tr>
                                <th>Oglasi</th>
                                <td>Naslov, opis, cena, lokacija, fotografije, kontakt telefon, podaci o prodavcu/firmi</td>
                            </tr>
                            <tr>
                                <th>Poruke</th>
                                <td>Sadržaj poruka između korisnika u vezi sa oglasima</td>
                            </tr>
                            <tr>
                                <th>Tehnički podaci</th>
                                <td>IP adresa, tip uređaja/pregledača, cookie identifikatori, log grešaka</td>
                            </tr>
                            <tr>
                                <th>Push (Android)</th>
                                <td>FCM token uređaja za obaveštenja o porukama i nalogu</td>
                            </tr>
                            <tr>
                                <th>Analitika</th>
                                <td>Google Analytics 4 i Meta Pixel — samo uz saglasnost</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="legal-article" id="clanak-3">
                <h2><span>Član 3.</span> Svrha obrade</h2>
                <ol class="legal-list">
                    <li>Kreiranje i održavanje korisničkog naloga</li>
                    <li>Objavljivanje i prikaz oglasa</li>
                    <li>Komunikacija između kupaca i prodavaca</li>
                    <li>Verifikacija telefona (SMS OTP) i bezbednost naloga</li>
                    <li>Push obaveštenja u Android aplikaciji</li>
                    <li>Statistika poseta i poboljšanje platforme (uz saglasnost za marketing kolačiće)</li>
                    <li>Sprečavanje zloupotrebe, spam-a i prevara</li>
                </ol>
            </article>

            <article class="legal-article" id="clanak-4">
                <h2><span>Član 4.</span> Pravni osnov</h2>
                <p>Podatke obrađujemo na sledećim osnovama:</p>
                <ul>
                    <li><strong>Izvršenje usluge</strong> — nalog, oglasi, poruke, verifikacija</li>
                    <li><strong>Legitimni interes</strong> — bezbednost platforme i sprečavanje zloupotrebe</li>
                    <li><strong>Saglasnost</strong> — analitika i reklame putem cookie banera</li>
                </ul>
            </article>

            <article class="legal-article" id="clanak-5">
                <h2><span>Član 5.</span> Deljenje podataka</h2>
                <p>Podatke ne prodajemo. Možemo koristiti pouzdane procesore:</p>
                <div class="legal-table-wrap">
                    <table class="legal-table">
                        <thead>
                            <tr>
                                <th>Procesor</th>
                                <th>Svrha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th>Hosting / server</th><td>Smeštaj sajta i podataka</td></tr>
                            <tr><th>SMS gateway</th><td>Slanje OTP kodova za verifikaciju</td></tr>
                            <tr><th>Email (SMTP)</th><td>Transakcione poruke i obaveštenja</td></tr>
                            <tr><th>Google Firebase / FCM</th><td>Push notifikacije u Android aplikaciji</td></tr>
                            <tr><th>Google Analytics</th><td>Statistika poseta (uz saglasnost)</td></tr>
                            <tr><th>Meta Pixel</th><td>Merenje kampanja (uz saglasnost)</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>
                    Podatke koje sam objaviš u oglasu (npr. telefon, lokacija) vide drugi korisnici.
                    Kupovina i plaćanje se dogovaraju direktno između kupca i prodavca —
                    platforma ne učestvuje u transakciji.
                </p>
            </article>

            <article class="legal-article" id="clanak-6">
                <h2><span>Član 6.</span> Kolačići</h2>
                <ul>
                    <li><strong>Neophodni</strong> — prijava, sesija i bezbednost (uvek aktivni)</li>
                    <li><strong>Marketing / analitika</strong> — Google i Meta, samo ako izabereš „Prihvati sve“</li>
                </ul>
                <p>
                    Izbor možeš promeniti brisanjem kolačića <code>kt_marketing_consent</code> u pregledaču.
                </p>
            </article>

            <article class="legal-article" id="clanak-7">
                <h2><span>Član 7.</span> Android aplikacija</h2>
                <p>
                    Aplikacija prikazuje isti sadržaj kao sajt preko HTTPS veze.
                    Za push obaveštenja traži dozvolu na Android 13+.
                    Aplikacija zahteva internet i ne radi potpuno offline.
                </p>
            </article>

            <article class="legal-article" id="clanak-8">
                <h2><span>Član 8.</span> Rok čuvanja</h2>
                <p>
                    Podatke naloga i oglasa čuvamo dok koristiš uslugu ili dok ne zatražiš brisanje.
                    Logovi i rezervne kopije mogu se čuvati kraće ili duže radi bezbednosti i zakonskih obaveza.
                </p>
            </article>

            <article class="legal-article" id="clanak-9">
                <h2><span>Član 9.</span> Prava korisnika</h2>
                <p>Možeš zatražiti:</p>
                <ol class="legal-list">
                    <li>Pristup sopstvenim podacima</li>
                    <li>Ispravku netačnih podataka (Nalog → Profil)</li>
                    <li>Brisanje naloga i povezanih oglasa</li>
                    <li>Povlačenje saglasnosti za marketing kolačiće</li>
                </ol>
                <p>Zahtev pošalji na <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a>.</p>
            </article>

            <article class="legal-article" id="clanak-10">
                <h2><span>Član 10.</span> Bezbednost</h2>
                <p>
                    Koristimo HTTPS, hash lozinki, CSRF zaštitu i ograničen pristup admin delu.
                    Nijedan internet servis ne može garantovati apsolutnu bezbednost.
                </p>
            </article>

            <article class="legal-article" id="clanak-11">
                <h2><span>Član 11.</span> Maloletna lica</h2>
                <p>Usluga nije namenjena osobama mlađim od 16 godina.</p>
            </article>

            <article class="legal-article" id="clanak-12">
                <h2><span>Član 12.</span> Izmene politike</h2>
                <p>
                    Politiku možemo ažurirati. Nova verzija biće objavljena na ovoj stranici,
                    sa datumom poslednje izmene.
                </p>
            </article>

            <aside class="legal-contact">
                <h2>Kontakt za privatnost</h2>
                <p>
                    <?= h($siteName) ?><br>
                    E-mail: <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a><br>
                    Sajt: <a href="https://kupitelefon.rs">https://kupitelefon.rs</a>
                </p>
            </aside>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
