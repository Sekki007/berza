<?php

declare(strict_types=1);

/**
 * Sitemap index i delovi.
 *
 * lastmod oglasa: max(updated_at, created_at), osim kada je updated_at masovna
 * migracija. Masovni pečat je identičan updated_at na 20+ oglasa, ili dan na
 * kom 40+ oglasa deli isti pečat koji pokriva bar 80% tog dana. Za te oglase
 * lastmod je created_at. Pojedinačna izmena dobija drugačiji updated_at i
 * tada se opet koristi noviji od created/updated.
 */

const SEO_SITEMAP_MASS_UPDATED_MIN = 20;

/** @param list<array> $ads @return array<string,true> */
function seoMassUpdatedStamps(array $ads): array
{
    $exact = [];
    $byDay = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $updated = trim((string)($ad['updated_at'] ?? ''));
        if ($updated === '') {
            continue;
        }
        $exact[$updated] = ($exact[$updated] ?? 0) + 1;
        $day = substr($updated, 0, 10);
        $byDay[$day][$updated] = ($byDay[$day][$updated] ?? 0) + 1;
    }
    $mass = [];
    foreach ($exact as $stamp => $count) {
        if ($count >= SEO_SITEMAP_MASS_UPDATED_MIN) {
            $mass[$stamp] = true;
        }
    }
    foreach ($byDay as $stamps) {
        $total = array_sum($stamps);
        if ($total < 40) {
            continue;
        }
        arsort($stamps);
        $topStamp = (string)array_key_first($stamps);
        $topCount = (int)$stamps[$topStamp];
        if ($topCount >= SEO_SITEMAP_MASS_UPDATED_MIN && ($topCount / $total) >= 0.8) {
            $mass[$topStamp] = true;
        }
    }
    return $mass;
}

function seoAdSitemapLastmod(array $ad, array $massStamps): string
{
    $updated = trim((string)($ad['updated_at'] ?? ''));
    $created = trim((string)($ad['created_at'] ?? ''));
    if ($updated !== '' && isset($massStamps[$updated])) {
        return seoLastmodDate($created);
    }
    $updatedDay = seoLastmodDate($updated);
    $createdDay = seoLastmodDate($created);
    if ($updatedDay === '') {
        return $createdDay;
    }
    if ($createdDay === '') {
        return $updatedDay;
    }
    return $updatedDay >= $createdDay ? $updatedDay : $createdDay;
}

/** @return list<string> */
function seoAdSitemapImages(array $ad, int $limit = 3): array
{
    $out = [];
    foreach ((array)($ad['images'] ?? []) as $img) {
        if (!is_string($img) || trim($img) === '') {
            continue;
        }
        $loc = absoluteUrl($img);
        if (str_contains($loc, '?')) {
            continue;
        }
        $out[] = $loc;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * @return array<string,list<array{loc:string,lastmod?:string,images?:list<string>}>>
 */
function seoSitemapBuckets(): array
{
    $add = static function (array &$bucket, string $path, string $lastmod = '', array $images = []) : void {
        $path = trim($path);
        if ($path === '' || str_contains($path, '?')) {
            return;
        }
        $loc = absoluteUrl($path);
        if (str_contains($loc, '?')) {
            return;
        }
        foreach ($bucket as $row) {
            if (($row['loc'] ?? '') === $loc) {
                return;
            }
        }
        $entry = ['loc' => $loc];
        $lastmod = seoLastmodDate($lastmod);
        if ($lastmod !== '') {
            $entry['lastmod'] = $lastmod;
        }
        if ($images !== []) {
            $entry['images'] = $images;
        }
        $bucket[] = $entry;
    };

    $pages = [];
    foreach (['/', '/kako-radi', '/privatnost', '/uslovi', '/oglasi', '/katalog', '/vodici', '/servisi', '/provera-imei'] as $path) {
        $add($pages, $path);
    }
    foreach (getPublishedGuides() as $guide) {
        $updated = trim((string)($guide['updated_at'] ?? ''));
        if ($updated === '') {
            $updated = trim((string)($guide['published_at'] ?? $guide['created_at'] ?? ''));
        }
        $add($pages, guideUrl($guide), $updated);
    }

    $publicAds = getPublicAds(['sort' => 'newest']);
    $mass = seoMassUpdatedStamps($publicAds);

    $oglasi = [];
    foreach ($publicAds as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $add($oglasi, adUrl($ad), seoAdSitemapLastmod($ad, $mass), seoAdSitemapImages($ad));
    }

    $landing = [];
    foreach (listingLandingCandidatesForSitemap($publicAds) as $path) {
        $add($landing, $path);
    }

    $katalog = [];
    if (function_exists('phoneCatalogSitemapPaths')) {
        foreach (phoneCatalogSitemapPaths() as $path) {
            if ($path === '/katalog') {
                continue;
            }
            $add($katalog, $path);
        }
    }

    $servisi = [];
    foreach (directoryCityStats() as $cityRow) {
        $add($servisi, (string)($cityRow['url'] ?? ''));
    }
    foreach (listDirectoryServices(null) as $svcUser) {
        $svcUpdated = trim((string)($svcUser['verified_seller_at'] ?? ''));
        if ($svcUpdated === '') {
            $svcUpdated = trim((string)($svcUser['business_verified_at'] ?? ''));
        }
        if ($svcUpdated === '') {
            $svcUpdated = trim((string)($svcUser['created_at'] ?? ''));
        }
        $add($servisi, directoryServiceUrl($svcUser), $svcUpdated);
    }

    $izlozi = [];
    $sellersWithPublicAds = [];
    foreach (getAllAds() as $anyAd) {
        if (!is_array($anyAd) || (int)($anyAd['is_active'] ?? 0) !== 1) {
            continue;
        }
        $sellerId = (int)($anyAd['created_by'] ?? 0);
        if ($sellerId > 0) {
            $sellersWithPublicAds[$sellerId] = true;
        }
    }
    $seenUsers = [];
    foreach (getUsers() as $user) {
        if (!is_array($user) || !empty($user['is_blocked'])) {
            continue;
        }
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0 || isset($seenUsers[$uid])) {
            continue;
        }
        $seenUsers[$uid] = true;
        if (isset($sellersWithPublicAds[$uid])) {
            $add($izlozi, shopUrlForUser($user));
            foreach (getShopCategories($user) as $shopCat) {
                $add($izlozi, shopCatalogUrl($user, ['cat' => $shopCat['id']]));
            }
        }
        if (function_exists('storefrontIsActive') && storefrontIsActive($user)) {
            $add($izlozi, storefrontUrlForUser($user), (string)($user['shop_page_updated_at'] ?? ''));
        }
    }

    return [
        'pages' => $pages,
        'oglasi' => $oglasi,
        'landing' => $landing,
        'katalog' => $katalog,
        'servisi' => $servisi,
        'izlozi' => $izlozi,
    ];
}

function seoSitemapPartNames(): array
{
    return ['pages', 'oglasi', 'landing', 'katalog', 'servisi', 'izlozi'];
}

/** @param list<array{loc:string,lastmod?:string,images?:list<string>}> $urls */
function seoRenderUrlSet(array $urls, bool $withImages = false): string
{
    $ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
    if ($withImages) {
        $ns .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset ' . $ns . '>' . "\n";
    foreach ($urls as $row) {
        $loc = (string)($row['loc'] ?? '');
        if ($loc === '' || str_contains($loc, '?')) {
            continue;
        }
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
        if (!empty($row['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars((string)$row['lastmod'], ENT_XML1) . "</lastmod>\n";
        }
        if ($withImages) {
            foreach ((array)($row['images'] ?? []) as $image) {
                if (!is_string($image) || $image === '' || str_contains($image, '?')) {
                    continue;
                }
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>' . htmlspecialchars($image, ENT_XML1) . "</image:loc>\n";
                $xml .= "    </image:image>\n";
            }
        }
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";
    return $xml;
}

function seoRenderSitemapIndex(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (seoSitemapPartNames() as $part) {
        $xml .= "  <sitemap>\n";
        $xml .= '    <loc>' . htmlspecialchars(absoluteUrl('/sitemap-' . $part . '.xml'), ENT_XML1) . "</loc>\n";
        $xml .= "  </sitemap>\n";
    }
    $xml .= "</sitemapindex>\n";
    return $xml;
}

function seoSitemapDocument(string $part): string
{
    if ($part === '' || $part === 'index') {
        return seoRenderSitemapIndex();
    }
    if (!in_array($part, seoSitemapPartNames(), true)) {
        return '';
    }
    $buckets = seoSitemapBuckets();
    return seoRenderUrlSet($buckets[$part] ?? [], $part === 'oglasi');
}
