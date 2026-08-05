<?php

declare(strict_types=1);

function getAllGuides(): array
{
    $rows = readJsonFile('guides.json');
    if ($rows === []) {
        $rows = defaultGuidesSeed();
        if ($rows !== []) {
            writeJsonFile('guides.json', $rows);
        }
    }
    usort($rows, static fn($a, $b) => strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? '')));
    return $rows;
}

function defaultGuidesSeed(): array
{
    $now = date('Y-m-d H:i:s');
    return [
        [
            'id' => 1,
            'title' => 'Kako proveriti polovan iPhone pre kupovine',
            'slug' => 'provera-polovnog-iphone-a',
            'excerpt' => 'Brza check-lista za bateriju, Face ID, mrežu i istoriju uređaja.',
            'body_html' => '<p>Pre kupovine proveri serijski broj, stanje baterije, Face ID, zvuk i kameru. Testiraj SIM, Wi-Fi i Bluetooth, pa uporedi IMEI na kutiji i telefonu.</p><p>Otvorene oglase iPhone uređaja vidi na <a href="/oglasi/apple">Apple oglasima</a>, a ako ti treba dijagnostika pogledaj <a href="/oglasi/servis">servisne oglase</a>.</p>',
            'status' => 'published',
            'seo_title' => 'Provera polovnog iPhone-a: kompletan vodič',
            'seo_description' => 'Na šta da obratiš pažnju pre kupovine polovnog iPhone-a: baterija, Face ID, mreža i originalnost.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
        [
            'id' => 2,
            'title' => 'Bezbedna kupovina telefona bez prevare',
            'slug' => 'bezbedna-kupovina-telefona',
            'excerpt' => 'Kako da smanjiš rizik kod online kupovine i šta da tražiš od prodavca.',
            'body_html' => '<p>Uvek traži jasne slike, proveri reputaciju prodavca i insistiraj na porukama kroz platformu. Za skupe uređaje preporuka je lično preuzimanje uz proveru na licu mesta.</p><p>Pogledaj i aktivne ponude po gradovima na <a href="/oglasi/telefoni">telefoni</a> i firmama na <a href="/servisi">servisi</a>.</p>',
            'status' => 'published',
            'seo_title' => 'Bezbedna kupovina telefona online',
            'seo_description' => 'Praktični saveti za sigurnu kupovinu telefona i izbegavanje prevara.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
        [
            'id' => 3,
            'title' => 'Kada se isplati zamena ekrana',
            'slug' => 'kada-se-isplati-zamena-ekrana',
            'excerpt' => 'Kako odlučiti da li da menjaš ekran ili da menjaš uređaj.',
            'body_html' => '<p>Zamena ekrana se obično isplati kod novijih modela i kada ostatak telefona radi stabilno. Uporedi cenu popravke sa tržišnom cenom uređaja i proveri garanciju servisa.</p><p>Ponude za popravke proveri na <a href="/oglasi/servis">servis oglasima</a>.</p>',
            'status' => 'published',
            'seo_title' => 'Zamena ekrana telefona: kada ima smisla',
            'seo_description' => 'Vodič za procenu da li je zamena ekrana finansijski isplativa.',
            'og_image' => '',
            'author_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $now,
        ],
    ];
}

function getPublishedGuides(): array
{
    $rows = array_values(array_filter(
        getAllGuides(),
        static fn($g) => (string)($g['status'] ?? 'draft') === 'published'
    ));
    usort($rows, static fn($a, $b) => strcmp((string)($b['published_at'] ?? $b['updated_at'] ?? ''), (string)($a['published_at'] ?? $a['updated_at'] ?? '')));
    return $rows;
}

function guideUrl(array $guide): string
{
    $slug = trim((string)($guide['slug'] ?? ''));
    if ($slug === '') {
        return '/vodici';
    }
    return '/vodic/' . rawurlencode($slug);
}

function getGuideById(int $id): ?array
{
    foreach (getAllGuides() as $guide) {
        if ((int)($guide['id'] ?? 0) === $id) {
            return $guide;
        }
    }
    return null;
}

function getGuideBySlug(string $slug, bool $includeDraft = false): ?array
{
    $slug = listingFacetSlug($slug);
    foreach (getAllGuides() as $guide) {
        if ((string)($guide['slug'] ?? '') !== $slug) {
            continue;
        }
        if (!$includeDraft && (string)($guide['status'] ?? 'draft') !== 'published') {
            return null;
        }
        return $guide;
    }
    return null;
}

function guideSlugTaken(string $slug, int $exceptId = 0): bool
{
    $slug = listingFacetSlug($slug);
    foreach (getAllGuides() as $guide) {
        if ((int)($guide['id'] ?? 0) === $exceptId) {
            continue;
        }
        if ((string)($guide['slug'] ?? '') === $slug) {
            return true;
        }
    }
    return in_array($slug, reservedShopSlugs(), true);
}

function allocateUniqueGuideSlug(string $base, int $exceptId = 0): string
{
    $base = listingFacetSlug($base);
    if ($base === '' || in_array($base, reservedShopSlugs(), true)) {
        $base = 'vodic';
    }
    $candidate = $base;
    $i = 2;
    while (guideSlugTaken($candidate, $exceptId)) {
        $suffix = '-' . $i;
        $trim = 60 - strlen($suffix);
        $candidate = rtrim(substr($base, 0, max(1, $trim)), '-') . $suffix;
        $i++;
        if ($i > 9999) {
            break;
        }
    }
    return $candidate;
}

function saveGuide(array $input, ?int $guideId = null): ?int
{
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        return null;
    }
    $rows = getAllGuides();

    $status = (string)($input['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    $slugRaw = trim((string)($input['slug'] ?? ''));
    $slugBase = $slugRaw !== '' ? $slugRaw : slugifyTitle($title);
    $targetId = $guideId ?? 0;
    $slug = allocateUniqueGuideSlug($slugBase, $targetId);

    $payload = [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => trim((string)($input['excerpt'] ?? '')),
        'body_html' => trim((string)($input['body_html'] ?? '')),
        'status' => $status,
        'seo_title' => trim((string)($input['seo_title'] ?? '')),
        'seo_description' => trim((string)($input['seo_description'] ?? '')),
        'og_image' => trim((string)($input['og_image'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($guideId !== null) {
        foreach ($rows as &$row) {
            if ((int)($row['id'] ?? 0) !== $guideId) {
                continue;
            }
            $payload['id'] = $guideId;
            $payload['created_at'] = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
            $payload['author_id'] = (int)($row['author_id'] ?? (int)(currentUser()['id'] ?? 0));
            $oldStatus = (string)($row['status'] ?? 'draft');
            $payload['published_at'] = (string)($row['published_at'] ?? '');
            if ($status === 'published' && $oldStatus !== 'published') {
                $payload['published_at'] = date('Y-m-d H:i:s');
            }
            $row = array_merge($row, $payload);
            writeJsonFile('guides.json', $rows);
            return $guideId;
        }
        return null;
    }

    $maxId = 0;
    foreach ($rows as $row) {
        $maxId = max($maxId, (int)($row['id'] ?? 0));
    }
    $newId = $maxId + 1;
    $payload['id'] = $newId;
    $payload['created_at'] = date('Y-m-d H:i:s');
    $payload['author_id'] = (int)(currentUser()['id'] ?? 0);
    $payload['published_at'] = $status === 'published' ? date('Y-m-d H:i:s') : '';
    $rows[] = $payload;
    writeJsonFile('guides.json', $rows);
    return $newId;
}

function deleteGuide(int $guideId): bool
{
    $rows = getAllGuides();
    $before = count($rows);
    $rows = array_values(array_filter($rows, static fn($g) => (int)($g['id'] ?? 0) !== $guideId));
    if (count($rows) === $before) {
        return false;
    }
    writeJsonFile('guides.json', $rows);
    return true;
}
