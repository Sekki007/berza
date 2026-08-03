<?php

declare(strict_types=1);

/**
 * SEO direktorijum verifikovanih servisa: /servisi, /servisi/{grad}, /servisi/{grad}/{slug}
 */

/**
 * Gradovi za pretragu: prvo oni sa servisima, zatim ostali iz settings.
 *
 * @return list<array{city:string,slug:string,count:int,url:string}>
 */
function directoryCitySearchIndex(): array
{
    $bySlug = [];
    foreach (directoryCityStats() as $row) {
        $bySlug[$row['slug']] = $row;
    }
    foreach (directoryCities() as $city) {
        $slug = citySlug($city);
        if ($slug === '' || isset($bySlug[$slug])) {
            continue;
        }
        $bySlug[$slug] = [
            'city' => $city,
            'slug' => $slug,
            'count' => 0,
            'url' => directoryCityUrl($city),
        ];
    }
    $out = array_values($bySlug);
    usort($out, static function (array $a, array $b): int {
        if ($a['count'] !== $b['count']) {
            return $b['count'] <=> $a['count'];
        }
        return strcasecmp($a['city'], $b['city']);
    });
    return $out;
}

/**
 * @return list<string>
 */
function directoryCities(): array
{
    $cities = siteSettings()['cities'] ?? [];
    if (!is_array($cities)) {
        return [];
    }
    $out = [];
    foreach ($cities as $city) {
        $city = trim((string)$city);
        if ($city !== '') {
            $out[] = $city;
        }
    }
    return $out;
}

function findCityBySlug(string $slug): ?string
{
    $slug = citySlug($slug);
    if ($slug === '') {
        return null;
    }
    foreach (directoryCities() as $city) {
        if (citySlug($city) === $slug) {
            return $city;
        }
    }
    return null;
}

function isDirectoryServiceFirm(?array $user): bool
{
    if (!$user || !empty($user['is_blocked'])) {
        return false;
    }
    $kind = userBusinessKind($user);
    if (!in_array($kind, ['service', 'both'], true)) {
        return false;
    }
    return isBusinessVerified($user) || isVerifiedSeller($user);
}

function directoryServiceName(array $user): string
{
    $name = trim((string)($user['shop_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $name = trim((string)($user['full_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return (string)($user['username'] ?? 'Servis');
}

function directoryServiceUrl(array $user, ?string $city = null): string
{
    $cityName = $city !== null && $city !== ''
        ? $city
        : trim((string)($user['location'] ?? ''));
    $citySlugVal = citySlug($cityName);
    $slug = userShopSlug($user);
    if ($citySlugVal === '' || $slug === '') {
        return '/servisi';
    }
    return '/servisi/' . rawurlencode($citySlugVal) . '/' . rawurlencode($slug);
}

function directoryCityUrl(string $city): string
{
    $slug = citySlug($city);
    return $slug === '' ? '/servisi' : '/servisi/' . rawurlencode($slug);
}

/**
 * @return list<array>
 */
function listDirectoryServices(?string $city = null): array
{
    $cityNorm = $city !== null ? mb_strtolower(trim($city)) : '';
    $out = [];
    foreach (getUsers() as $user) {
        if (!isDirectoryServiceFirm($user)) {
            continue;
        }
        $loc = trim((string)($user['location'] ?? ''));
        if ($loc === '') {
            continue;
        }
        if ($cityNorm !== '' && mb_strtolower($loc) !== $cityNorm) {
            continue;
        }
        $out[] = $user;
    }
    usort($out, static function (array $a, array $b): int {
        return strcasecmp(directoryServiceName($a), directoryServiceName($b));
    });
    return $out;
}

/**
 * Gradovi koji imaju bar jedan servis (+ broj).
 *
 * @return list<array{city:string,slug:string,count:int,url:string}>
 */
function directoryCityStats(): array
{
    $counts = [];
    foreach (listDirectoryServices(null) as $user) {
        $city = trim((string)($user['location'] ?? ''));
        if ($city === '') {
            continue;
        }
        $counts[$city] = ($counts[$city] ?? 0) + 1;
    }

    $stats = [];
    foreach (directoryCities() as $city) {
        $count = (int)($counts[$city] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $stats[] = [
            'city' => $city,
            'slug' => citySlug($city),
            'count' => $count,
            'url' => directoryCityUrl($city),
        ];
    }
    // Gradovi van liste settings, ali sa servisima
    foreach ($counts as $city => $count) {
        $found = false;
        foreach ($stats as $row) {
            if ($row['city'] === $city) {
                $found = true;
                break;
            }
        }
        if (!$found && $count > 0) {
            $stats[] = [
                'city' => $city,
                'slug' => citySlug($city),
                'count' => $count,
                'url' => directoryCityUrl($city),
            ];
        }
    }

    usort($stats, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcasecmp($a['city'], $b['city']));
    return $stats;
}

function findDirectoryService(string $city, string $slug): ?array
{
    $cityName = findCityBySlug($city);
    $slug = normalizeShopSlug($slug);
    if ($slug === '') {
        return null;
    }
    $user = findUserByShopSlug($slug);
    if (!$user && $slug !== '') {
        $user = findUserByUsername($slug);
    }
    if (!$user || !isDirectoryServiceFirm($user)) {
        return null;
    }
    $userCity = trim((string)($user['location'] ?? ''));
    if ($cityName !== null && mb_strtolower($userCity) !== mb_strtolower($cityName)) {
        // Dozvoli match ako slug grada odgovara lokaciji korisnika (van settings liste)
        if (citySlug($userCity) !== citySlug($city)) {
            return null;
        }
    } elseif ($cityName === null && citySlug($userCity) !== citySlug($city)) {
        return null;
    }
    return $user;
}

function seoDirectoryHubMeta(): array
{
    $name = seoSiteName();
    $n = count(listDirectoryServices(null));
    return [
        'title' => 'Mobilni servisi u Srbiji — verifikovane firme | ' . $name,
        'description' => seoTruncate(
            'Spisak verifikovanih mobilnih servisa u Srbiji' . ($n > 0 ? " ({$n})" : '') .
            '. Pronađi servis po gradu, kontakt i oglase na ' . $name . '.'
        ),
    ];
}

function seoDirectoryCityMeta(string $city, int $count): array
{
    $name = seoSiteName();
    return [
        'title' => 'Mobilni servis ' . $city . ' — spisak firmi | ' . $name,
        'description' => seoTruncate(
            ($count > 0 ? "{$count} verifikovan" . ($count === 1 ? 'i servis' : 'ih servisa') : 'Verifikovani mobilni servisi') .
            " u gradu {$city}. Kontakt, izlog i usluge na {$name}."
        ),
    ];
}

function seoDirectoryServiceMeta(array $user, string $city): array
{
    $name = seoSiteName();
    $shop = directoryServiceName($user);
    $kind = businessKindLabel(userBusinessKind($user));
    $bio = trim((string)($user['shop_bio'] ?? ''));
    return [
        'title' => $shop . ' — mobilni servis ' . $city . ' | ' . $name,
        'description' => seoTruncate(
            $bio !== ''
                ? $bio
                : "{$shop} ({$kind}) u gradu {$city}. Verifikovana firma na {$name} — kontakt, izlog i usluge."
        ),
    ];
}

function seoDirectoryServiceJsonLd(array $user, string $city): array
{
    $shop = directoryServiceName($user);
    $url = absoluteUrl(directoryServiceUrl($user, $city));
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'ElectronicsStore',
        'name' => $shop,
        'url' => $url,
        'description' => seoTruncate((string)($user['shop_bio'] ?? $shop), 300),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string)($user['shop_page_address'] ?? '')) ?: null,
            'addressLocality' => $city,
            'addressCountry' => 'RS',
        ], static fn($v) => $v !== null && $v !== ''),
    ];
    $phone = trim((string)($user['phone'] ?? ''));
    if ($phone !== '') {
        $data['telephone'] = $phone;
    }
    $logo = userShopLogoUrl($user);
    if ($logo !== '') {
        $data['image'] = absoluteUrl($logo);
        $data['logo'] = absoluteUrl($logo);
    }
    return $data;
}
