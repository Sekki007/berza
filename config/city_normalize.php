<?php

declare(strict_types=1);

/**
 * Grad ostaje kanonski naziv iz liste sajta. Opština/deo grada ide u district
 * i ne pravi poseban landing dok za to ne bude posebne stranice.
 *
 * @return array{city:string,district:string}
 */
function normalizeCity(string $raw): array
{
    $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    if ($raw === '') {
        return ['city' => '', 'district' => ''];
    }

    $aliases = cityDistrictAliases();
    $key = mb_strtolower($raw);
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }

    $parts = preg_split('/\s*[|\/,]\s*/u', $raw) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
    if (count($parts) >= 2) {
        $city = canonicalCityName($parts[0]);
        $district = canonicalDistrictName($parts[1]);
        if ($city !== '') {
            return ['city' => $city, 'district' => $district];
        }
    }

    $city = canonicalCityName($raw);
    if ($city !== '') {
        $districtKey = mb_strtolower($raw);
        if (isset($aliases[$districtKey]) && $aliases[$districtKey]['district'] !== '') {
            return $aliases[$districtKey];
        }
        return ['city' => $city, 'district' => ''];
    }

    return ['city' => $raw, 'district' => ''];
}

function canonicalCityName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $wanted = mb_strtolower($name);
    $aliases = [
        'bg' => 'Beograd',
        'beograd' => 'Beograd',
        'novi bg' => 'Beograd',
        'novi bg.' => 'Beograd',
        'nbg' => 'Beograd',
        'novi beograd' => 'Beograd',
        'ns' => 'Novi Sad',
        'novi sad' => 'Novi Sad',
    ];
    if (isset($aliases[$wanted])) {
        return $aliases[$wanted];
    }
    if (function_exists('citySlug') && function_exists('listingFindCityBySlug')) {
        $found = listingFindCityBySlug(citySlug($name));
        if ($found !== null) {
            return $found;
        }
    }
    if (function_exists('categoriesConfig')) {
        foreach ((array)(categoriesConfig()['cities'] ?? []) as $city) {
            $city = trim((string)$city);
            if ($city !== '' && mb_strtolower($city) === $wanted) {
                return $city;
            }
        }
    }
    return '';
}

function canonicalDistrictName(string $name): string
{
    $key = mb_strtolower(trim($name));
    $key = rtrim($key, '.');
    $map = [
        'novi bg' => 'Novi Beograd',
        'novi beograd' => 'Novi Beograd',
        'nbg' => 'Novi Beograd',
        'zvezdara' => 'Zvezdara',
        'vozdovac' => 'Voždovac',
        'voždovac' => 'Voždovac',
        'cukarica' => 'Čukarica',
        'čukarica' => 'Čukarica',
        'palilula' => 'Palilula',
        'zemun' => 'Zemun',
        'savski venac' => 'Savski venac',
        'stari grad' => 'Stari grad',
        'rakovica' => 'Rakovica',
        'grocka' => 'Grocka',
    ];
    return $map[$key] ?? trim($name);
}

/** @return array<string,array{city:string,district:string}> */
function cityDistrictAliases(): array
{
    $rows = [
        'beograd | novi bg.' => ['Beograd', 'Novi Beograd'],
        'beograd | novi bg' => ['Beograd', 'Novi Beograd'],
        'beograd | novi beograd' => ['Beograd', 'Novi Beograd'],
        'beograd|novi bg.' => ['Beograd', 'Novi Beograd'],
        'novi beograd' => ['Beograd', 'Novi Beograd'],
        'novi bg.' => ['Beograd', 'Novi Beograd'],
        'novi bg' => ['Beograd', 'Novi Beograd'],
        'beograd | zemun' => ['Beograd', 'Zemun'],
        'beograd | zvezdara' => ['Beograd', 'Zvezdara'],
        'beograd | vozdovac' => ['Beograd', 'Voždovac'],
        'beograd | voždovac' => ['Beograd', 'Voždovac'],
        'beograd | cukarica' => ['Beograd', 'Čukarica'],
        'beograd | čukarica' => ['Beograd', 'Čukarica'],
    ];
    $out = [];
    foreach ($rows as $key => $pair) {
        $out[$key] = ['city' => $pair[0], 'district' => $pair[1]];
    }
    return $out;
}

function adCityParts(array $ad): array
{
    return normalizeCity((string)($ad['location'] ?? ''));
}
