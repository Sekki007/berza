<?php

declare(strict_types=1);

/**
 * Ponašanje detalja oglasa: kontakt linkovi, sakrivena prazna polja, slični oglasi.
 * Pokretanje: php tests/listing_detail_test.php
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/oglas/6';
$_SERVER['HTTP_USER_AGENT'] = '';
ini_set('display_errors', '0');

$dataDir = dirname(__DIR__) . '/data';
$restorePaths = [
    $dataDir . '/ads.json',
    $dataDir . '/notifications.json',
    $dataDir . '/users.json',
    $dataDir . '/ad_stats.json',
];
$restore = [];
foreach ($restorePaths as $path) {
    $restore[$path] = is_file($path) ? (string)file_get_contents($path) : null;
}
$guidesPath = $dataDir . '/guides.json';
$guidesExisted = is_file($guidesPath);
$viewsSeedPath = $dataDir . '/.ad_views_seeded_v1';
$viewsSeedExisted = is_file($viewsSeedPath);
register_shutdown_function(static function () use ($restore, $guidesPath, $guidesExisted, $viewsSeedPath, $viewsSeedExisted): void {
    foreach ($restore as $path => $contents) {
        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
            continue;
        }
        file_put_contents($path, $contents);
    }
    if (!$guidesExisted && is_file($guidesPath)) {
        unlink($guidesPath);
    }
    if (!$viewsSeedExisted && is_file($viewsSeedPath)) {
        unlink($viewsSeedPath);
    }
});

require_once dirname(__DIR__) . '/config/bootstrap.php';

/**
 * Lokalni seed je istekao. Za render privremeno produžava aktivne oglase,
 * pa odmah vraća originalni JSON.
 */
function withFreshAds(callable $fn): mixed
{
    global $restore;
    $path = dirname(__DIR__) . '/data/ads.json';
    $original = $restore[$path] ?? (string)file_get_contents($path);
    $ads = json_decode($original, true);
    if (!is_array($ads)) {
        $ads = [];
    }
    foreach ($ads as &$ad) {
        if (!is_array($ad) || !empty($ad['is_sold'])) {
            continue;
        }
        $ad['is_active'] = 1;
        $ad['expires_at'] = '2027-12-31 23:59:59';
    }
    unset($ad);
    file_put_contents($path, json_encode($ads, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    try {
        return $fn();
    } finally {
        file_put_contents($path, $original);
    }
}

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "OK  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$phone = '060 123 4567';
check(adTelHref($phone) === 'tel:0601234567', 'tel href skida razmake');
check(adTelHref('+381 60 123 4567') === 'tel:+381601234567', 'tel href zadržava vodeći plus');
check(adTelHref('"><script>alert(1)</script>') === '', 'tel href odbija tekst bez broja');
check(adTelHref('12345') === '', 'tel href odbija prekratak broj');
check(viberChatLink($phone) === 'viber://chat?number=%2B381601234567', 'Viber koristi viber:// i 381');
check(viberChatLink('nema') === '', 'Viber bez broja je prazan');
check(whatsappLink($phone, 'Zdravo & ćao') === 'https://wa.me/381601234567?text=' . rawurlencode('Zdravo & ćao'), 'WhatsApp je https wa.me');
check(whatsappLink('javascript:alert(1)', 'x') === '', 'WhatsApp bez broja je prazan');
check(!str_contains(adTelHref($phone) . viberChatLink($phone) . whatsappLink($phone, 'a'), 'javascript:'), 'nema javascript šeme');

$legacy = ['contact_phone' => '0601234567', 'title' => 'Test'];
$legacyLinks = adContactActionLinks($legacy, true, 'Poruka');
check(array_column($legacyLinks, 'key') === ['call', 'viber', 'whatsapp'], 'stari oglas dobija Pozovi, Viber i WhatsApp');

$limited = ['contact_phone' => '0601234567', 'contact_methods' => ['call', 'message']];
$limitedLinks = adContactActionLinks($limited, true, 'Poruka');
check(array_column($limitedLinks, 'key') === ['call'], 'eksplicitni kanali gase Viber i WhatsApp');

$noPhone = ['title' => 'Bez broja', 'contact_methods' => ['call', 'viber', 'whatsapp']];
check(adContactActionLinks($noPhone, true, 'Poruka') === [], 'bez broja nema kontakt dugmadi');

$waOff = adContactActionLinks($legacy, false, 'Poruka');
check(array_column($waOff, 'key') === ['call', 'viber'], 'isključen WhatsApp se ne prikazuje');

$phoneAd = [
    'ad_type' => 'telefon',
    'condition_state' => 'Kao novo',
    'storage' => '256 GB',
    'ram' => '8GB',
    'battery_health' => 90,
    'sim_status' => 'SIM Free',
    'color' => 'Crna',
    'location' => 'Lazarevac',
    'brand' => 'Apple',
    'model' => '16 pro',
    'has_warranty' => 0,
];
$facts = adKeyFactCards($phoneAd);
$labels = array_column($facts, 'label');
check(in_array('Stanje', $labels, true) && in_array('Battery Health', $labels, true), 'ključne informacije imaju stanje i bateriju');
check(in_array('Memorija / RAM', $labels, true), 'memorija i RAM idu u jednu karticu');
check(!in_array('Garancija', $labels, true), 'garancija se ne izmišlja');
$bh = null;
foreach ($facts as $fact) {
    if ($fact['label'] === 'Battery Health') {
        $bh = $fact;
    }
}
check(is_array($bh) && ($bh['meter'] ?? null) === 90 && $bh['value'] === '90%', 'battery health ima procenat iz podataka');

$empty = ['ad_type' => 'telefon', 'brand' => 'Apple'];
$emptyLabels = array_column(adKeyFactCards($empty), 'label');
check(!in_array('Stanje', $emptyLabels, true) && !in_array('Battery Health', $emptyLabels, true) && !in_array('SIM', $emptyLabels, true), 'prazna polja se ne prikazuju');

$parts = ['ad_type' => 'delovi', 'condition_state' => 'Novo', 'equipment_type' => 'Kamera'];
$partLabels = array_column(adKeyFactCards($parts), 'label');
check(in_array('Tip', $partLabels, true) && !in_array('Battery Health', $partLabels, true) && !in_array('RAM', $partLabels, true), 'delovi nemaju BH i RAM');

$withPickup = $phoneAd;
$withPickup['pickup_methods'] = ['pickup'];
$loc = null;
foreach (adKeyFactCards($withPickup) as $fact) {
    if ($fact['label'] === 'Lokacija') {
        $loc = $fact;
    }
}
check(is_array($loc) && ($loc['note'] ?? '') === 'Lično preuzimanje', 'lično preuzimanje samo kad je sačuvano');
check(adPickupLabels(['ad_type' => 'telefon']) === [], 'nedostajući način preuzimanja ostaje prazan');

check(adHighlightChips($phoneAd) === ['Kao novo', '256 GB', 'BH 90%', 'SIM Free'], 'čipovi dolaze iz polja oglasa');
check(adAccessoryLabels(['ad_type' => 'telefon', 'accessories' => ['cable', 'nepoznato', 'case']]) === ['Kabl', 'Maska'], 'oprema ignoriše nepoznate ključeve');
check(adDescriptionIsLong(str_repeat('a', 421)) === true && adDescriptionIsLong('kratak opis') === false, 'dugačak opis se sklapa');

$ad6 = getAdById(6);
check(is_array($ad6), 'oglas 6 postoji u podacima');
if (is_array($ad6)) {
    withFreshAds(static function () use ($ad6): void {
        $similar = getSimilarAds($ad6, 4);
        $ids = array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $similar);
        check(!in_array(6, $ids, true), 'slični oglasi ne uključuju trenutni');
        check($ids !== [], 'postoje relevantni aktivni slični oglasi');
        check(count($ids) === count(array_unique($ids)), 'slični oglasi se ne ponavljaju');
        $allPublic = true;
        foreach ($similar as $item) {
            if ((int)($item['is_active'] ?? 0) !== 1 || !empty($item['is_sold'])) {
                $allPublic = false;
            }
        }
        check($allPublic, 'slični oglasi su aktivni i nisu prodati');
    });
}

function renderAd(int $adId): string
{
    $_GET['id'] = (string)$adId;
    $_SERVER['REQUEST_URI'] = '/oglas/' . $adId;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_USER_AGENT'] = '';
    ob_start();
    include dirname(__DIR__) . '/public/oglas.php';
    return (string)ob_get_clean();
}

$html = (string)withFreshAds(static fn(): string => renderAd(6));
check($html !== '' && !str_contains($html, 'Fatal error'), 'stranica oglasa se renderuje');
check(str_contains($html, 'data-seller-card') && str_contains($html, 'data-offer'), 'kartica prodavca i ponuda postoje');
$sellerPos = strpos($html, 'data-seller-card');
$offerPos = strpos($html, 'data-offer');
check($sellerPos !== false && $offerPos !== false && $sellerPos < $offerPos, 'u desnoj koloni prodavac je ispred naslova');
check(substr_count($html, '<h1') === 1, 'jedan naslov h1');
check(str_contains($html, 'href="tel:0601234567"'), 'Pozovi koristi tel broj oglasa');
check(str_contains($html, 'href="viber://chat?number=%2B381601234567"'), 'Viber link je bezbedan');
check(!str_contains($html, 'wa.me/'), 'isključen WhatsApp se ne renderuje na stranici');
check(str_contains($html, 'kp-contact-dock'), 'mobilna traka postoji kad ima kontakta');
check(str_contains($html, 'Sve specifikacije'), 'specifikacije su u sklopivom bloku');
check(str_contains($html, 'Ključne informacije'), 'ključne informacije postoje');
check(!str_contains($html, '#1205') && !str_contains($html, 'Milisav') && !str_contains($html, '670 €'), 'nema hardkodovanih podataka iz mockupa');
check(str_contains($html, 'application/ld+json') || str_contains($html, 'ViewContent') || str_contains($html, 'fbq'), 'SEO ili pixel markup ostaje');
check(str_contains($html, 'id="poruka"') || str_contains($html, 'Pošalji poruku na platformi'), 'poruke na platformi ostaju');
check(str_contains($html, 'fetchpriority="high"') && str_contains($html, 'loading="lazy"'), 'glavna slika ima prioritet, ostale lazy');

$partsHtml = (string)withFreshAds(static fn(): string => renderAd(2));
check(!str_contains($partsHtml, 'Battery Health'), 'oglas dela ne prikazuje battery health');
check(str_contains($partsHtml, 'data-contact-channel="call"'), 'deo sa brojem i dalje ima poziv');
check(!str_contains($partsHtml, 'besplatnu IMEI proveru'), 'IMEI napomena je samo za telefone');

$detailCss = (string)file_get_contents(dirname(__DIR__) . '/public/assets/css/style.css');
$railRule = '';
if (preg_match('/\.kp-detail-rail\s*\{[^}]*position:\s*sticky;[^}]*\}/s', $detailCss, $railMatch) === 1) {
    $railRule = $railMatch[0];
}
check($railRule !== '' && str_contains($railRule, 'position: sticky'), 'desktop kolona i dalje može da bude sticky');
check($railRule !== '' && !preg_match('/overflow\s*:\s*(auto|scroll)/', $railRule), 'desna kolona nema unutrašnji scrollbar');
check($railRule !== '' && !preg_match('/max-height\s*:\s*calc\(\s*100vh/', $railRule), 'desna kolona nije odsječena na visinu ekrana');
check($railRule !== '' && str_contains($railRule, 'overflow: visible'), 'sadržaj desne kolone ostaje u skrolu stranice');

if ($failed > 0) {
    echo "\n{$failed} provera nije prošlo.\n";
    exit(1);
}

echo "\nSve provere su prošle.\n";
exit(0);
