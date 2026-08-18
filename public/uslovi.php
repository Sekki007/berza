<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $requestPath === '/uslovi.php') {
    header('Location: /uslovi', true, 301);
    exit;
}

$site = siteSettings();
$siteName = (string)($site['site_name'] ?? 'KupiTelefon.rs');
$pageTitle = 'Uslovi korišćenja — ' . $siteName;
$pageDescription = 'Pravila korišćenja KupiTelefon.rs: oglasi, nalog, poruke, ocene i odgovornost kupca i prodavca.';
$canonicalUrl = absoluteUrl('/uslovi');
$activePage = 'oglasi';
$showSearch = false;
$updatedAt = '18.08.2026.';
$contactEmail = 'podrska@kupitelefon.rs';

$toc = [
    ['id' => 'clanak-1', 'label' => 'Priroda usluge'],
    ['id' => 'clanak-2', 'label' => 'Nalog'],
    ['id' => 'clanak-3', 'label' => 'Oglasi'],
    ['id' => 'clanak-4', 'label' => 'Zabranjen sadržaj'],
    ['id' => 'clanak-5', 'label' => 'Poruke i ocene'],
    ['id' => 'clanak-6', 'label' => 'Kupovina i plaćanje'],
    ['id' => 'clanak-7', 'label' => 'Odgovornost'],
    ['id' => 'clanak-8', 'label' => 'Isticanje i krediti'],
    ['id' => 'clanak-9', 'label' => 'Obustava naloga'],
    ['id' => 'clanak-10', 'label' => 'Izmene uslova'],
];

require __DIR__ . '/partials/layout-start.php';
?>

<div class="legal-page">
    <div class="breadcrumb"><a href="/">Početna</a> › Uslovi korišćenja</div>

    <header class="legal-hero">
        <p class="legal-kicker">Pravni dokument</p>
        <h1>Uslovi korišćenja</h1>
        <p class="legal-lead">
            Korišćenjem sajta <a href="https://kupitelefon.rs">kupitelefon.rs</a> i Android aplikacije
            <strong>KupiTelefon</strong> prihvataš ove uslove. Ako se ne slažeš, nemoj koristiti platformu.
        </p>
        <dl class="legal-meta">
            <div>
                <dt>Operator</dt>
                <dd><?= h($siteName) ?></dd>
            </div>
            <div>
                <dt>Usluga</dt>
                <dd>Online oglasnik</dd>
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
                <h2><span>Član 1.</span> Priroda usluge</h2>
                <p>
                    <?= h($siteName) ?> je oglasnik: povezuje kupce, prodavce i servise.
                    Nismo strana u kupoprodaji, ne držimo novac, ne šaljemo robu i ne garantujemo
                    da je oglas tačan ili da će dogovor biti ispunjen.
                </p>
                <p>
                    Lični podaci se obrađuju prema
                    <a href="/privatnost">Politici privatnosti</a>.
                </p>
            </article>

            <article class="legal-article" id="clanak-2">
                <h2><span>Član 2.</span> Nalog</h2>
                <ul>
                    <li>Za objavu oglasa i poruke potreban je nalog sa potvrđenim brojem telefona.</li>
                    <li>Odgovoran si za tačnost podataka i čuvanje lozinke.</li>
                    <li>Jedan nalog je namenjen jednoj osobi ili jednoj firmi — ne deliti pristup sa trećima radi zloupotrebe.</li>
                    <li>Možeš zatražiti brisanje naloga preko profila ili na <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a>.</li>
                </ul>
            </article>

            <article class="legal-article" id="clanak-3">
                <h2><span>Član 3.</span> Oglasi</h2>
                <p>Objavom oglasa izjavljuješ da:</p>
                <ul>
                    <li>imaš pravo da prodaš ili ponudiš navedeni uređaj, deo ili uslugu,</li>
                    <li>opis, cena, lokacija i fotografije odgovaraju stvarnom stanju,</li>
                    <li>nećeš koristiti oglas za reklamu tuđih oglasnika ili spam.</li>
                </ul>
                <p>
                    Zadržavamo pravo da uredimo, sklonimo ili odbijemo oglas koji krši ova pravila
                    ili šteti drugim korisnicima.
                </p>
            </article>

            <article class="legal-article" id="clanak-4">
                <h2><span>Član 4.</span> Zabranjen sadržaj</h2>
                <p>Nije dozvoljeno:</p>
                <ol class="legal-list">
                    <li>Oglasi ukradene robe, prevarantske šeme, lažni kontakt ili lažne fotografije</li>
                    <li>Uređaji sa blokiranim IMEI-jem, ako to nije jasno navedeno</li>
                    <li>Uvrede, pretnje, diskriminacija i uznemiravanje</li>
                    <li>Masovno slanje istih oglasa ili automatizovani spam</li>
                    <li>Prikupljanje tuđih podataka mimo namene platforme</li>
                </ol>
            </article>

            <article class="legal-article" id="clanak-5">
                <h2><span>Član 5.</span> Poruke i ocene</h2>
                <p>
                    Poruke služe dogovoru oko oglasa. Ocene treba da odražavaju stvarno iskustvo
                    trgovine. Lažne ocene, ucene ocenom i ocene bez osnova možemo ukloniti.
                </p>
                <p>
                    Sumnjiv oglas ili korisnika prijavi preko stranice oglasa ili izloga.
                    Prijave pregledamo, ali ne garantujemo rok ni ishod.
                </p>
            </article>

            <article class="legal-article" id="clanak-6">
                <h2><span>Član 6.</span> Kupovina i plaćanje</h2>
                <ul>
                    <li>Cenu, način plaćanja i isporuku dogovarate direktno.</li>
                    <li>Preporučujemo lično preuzimanje i proveru uređaja pre uplate.</li>
                    <li>Avans nepoznatim osobama, posebno nepovratnim metodama, je na tvoj rizik.</li>
                    <li>Za sporove između korisnika nismo arbitar; možeš prijaviti oglas i obratiti se nadležnim organima.</li>
                </ul>
            </article>

            <article class="legal-article" id="clanak-7">
                <h2><span>Član 7.</span> Odgovornost</h2>
                <p>
                    Platformu pružamo „kakva jeste“. Ne odgovaramo za štetu nastalu dogovorom,
                    kvalitetom robe, kašnjenjem, prevarom trećih lica ili nedostupnošću sajta,
                    osim gde zakon izričito nalaže drugačije.
                </p>
                <p>
                    Sadržaj oglasa je odgovornost korisnika koji ga je objavio.
                </p>
            </article>

            <article class="legal-article" id="clanak-8">
                <h2><span>Član 8.</span> Isticanje i krediti</h2>
                <p>
                    TOP isticanje, obnova oglasa i slične opcije su dodatne usluge na platformi.
                    Krediti se troše prema prikazu u nalogu. Neispravljena tehnička greška
                    (npr. neuspelo isticanje) može se nadoknaditi kreditom; novac se ne vraća
                    osim ako je to zakonski obavezno.
                </p>
            </article>

            <article class="legal-article" id="clanak-9">
                <h2><span>Član 9.</span> Obustava naloga</h2>
                <p>
                    Možemo privremeno ili trajno ograničiti nalog, oglase ili poruke ako postoji
                    sumnja na prevaru, spam, kršenje ovih uslova ili štetu drugim korisnicima.
                    Odluku možemo doneti bez prethodnog obaveštenja kada je to potrebno radi zaštite.
                </p>
            </article>

            <article class="legal-article" id="clanak-10">
                <h2><span>Član 10.</span> Izmene uslova</h2>
                <p>
                    Uslove možemo ažurirati. Nova verzija važi od objave na ovoj stranici.
                    Nastavak korišćenja posle izmene znači da prihvataš novu verziju.
                </p>
            </article>

            <div class="legal-contact">
                <h2>Kontakt</h2>
                <p>
                    Pitanja o uslovima: <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a>.
                    Politika privatnosti: <a href="/privatnost">/privatnost</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>
