<?php

declare(strict_types=1);

/**
 * Wikidata sync za katalog telefona.
 *
 * Povlači modele pametnih telefona (identitet + dostupne specifikacije)
 * i spaja ih sa postojećim phone_models dokumentom. Proizvođačke
 * specifikacije (kind=manufacturer) se ne prepisuju.
 */

function phoneCatalogWikidataEndpoint(): string
{
    return 'https://query.wikidata.org/sparql';
}

/**
 * Brendovi koje sync uvodi. Ključ je Wikidata Q-id proizvođača.
 * Isti brand_slug može imati više Q-id (npr. Nokia + HMD Global).
 *
 * @return array<string,array{brand:string,brand_slug:string}>
 */
function phoneCatalogWikidataBrands(): array
{
    // Q-id = P176 manufacturer koji na Wikidata stvarno ima smartphone stavke.
    return [
        'Q312' => ['brand' => 'Apple', 'brand_slug' => 'apple'],
        'Q20718' => ['brand' => 'Samsung', 'brand_slug' => 'samsung'],
        'Q95' => ['brand' => 'Google', 'brand_slug' => 'google'],
        'Q463094' => ['brand' => 'Google', 'brand_slug' => 'google'], // Google LLC (stariji P176)
        'Q1636958' => ['brand' => 'Xiaomi', 'brand_slug' => 'xiaomi'],
        'Q160120' => ['brand' => 'Huawei', 'brand_slug' => 'huawei'],
        'Q16499972' => ['brand' => 'OnePlus', 'brand_slug' => 'oneplus'],
        'Q2084900' => ['brand' => 'Oppo', 'brand_slug' => 'oppo'],
        'Q56275466' => ['brand' => 'Realme', 'brand_slug' => 'realme'],
        'Q27070331' => ['brand' => 'Honor', 'brand_slug' => 'honor'],
        'Q152864' => ['brand' => 'Asus', 'brand_slug' => 'asus'],
        'Q1418' => ['brand' => 'Nokia', 'brand_slug' => 'nokia'],
        'Q24090301' => ['brand' => 'Nokia', 'brand_slug' => 'nokia'], // HMD Global
        'Q18301787' => ['brand' => 'Vivo', 'brand_slug' => 'vivo'],
        'Q30594096' => ['brand' => 'Infinix', 'brand_slug' => 'infinix'],
        'Q6014034' => ['brand' => 'Sony', 'brand_slug' => 'sony'],
        'Q162345' => ['brand' => 'LG', 'brand_slug' => 'lg'],
        'Q136484' => ['brand' => 'ZTE', 'brand_slug' => 'zte'],
        'Q1143917' => ['brand' => 'Meizu', 'brand_slug' => 'meizu'],
        'Q14799' => ['brand' => 'Lenovo', 'brand_slug' => 'lenovo'],
        'Q186012' => ['brand' => 'HTC', 'brand_slug' => 'htc'],
        'Q502121' => ['brand' => 'BlackBerry', 'brand_slug' => 'blackberry'],
    ];
}

/**
 * Jedinstveni brand_slug → prikazni redosled za admin / CLI etape.
 *
 * @return list<array{slug:string,brand:string,qids:list<string>}>
 */
function phoneCatalogWikidataBrandStages(): array
{
    $bySlug = [];
    foreach (phoneCatalogWikidataBrands() as $qid => $meta) {
        $slug = (string)$meta['brand_slug'];
        if (!isset($bySlug[$slug])) {
            $bySlug[$slug] = [
                'slug' => $slug,
                'brand' => (string)$meta['brand'],
                'qids' => [],
            ];
        }
        $bySlug[$slug]['qids'][] = $qid;
    }
    return array_values($bySlug);
}

/** @return list<string> */
function phoneCatalogWikidataBrandSlugs(): array
{
    return array_map(static fn(array $s): string => $s['slug'], phoneCatalogWikidataBrandStages());
}

function phoneCatalogWikidataNormalizeBrandSlug(?string $brandSlug): ?string
{
    $brandSlug = mb_strtolower(trim((string)$brandSlug));
    if ($brandSlug === '' || $brandSlug === 'all') {
        return null;
    }
    return in_array($brandSlug, phoneCatalogWikidataBrandSlugs(), true) ? $brandSlug : null;
}

/**
 * @return array<string,array{brand:string,brand_slug:string}>
 */
function phoneCatalogWikidataBrandsFiltered(?string $brandSlug = null): array
{
    $brandSlug = phoneCatalogWikidataNormalizeBrandSlug($brandSlug);
    if ($brandSlug === null) {
        return phoneCatalogWikidataBrands();
    }
    $out = [];
    foreach (phoneCatalogWikidataBrands() as $qid => $meta) {
        if ($meta['brand_slug'] === $brandSlug) {
            $out[$qid] = $meta;
        }
    }
    return $out;
}

function phoneCatalogWikidataMinYear(): int
{
    return 2014;
}

/**
 * Tipovi stavki: smartphone + mobile phone (šire pokrivanje novijih modela).
 *
 * @return list<string>
 */
function phoneCatalogWikidataInstanceTypes(): array
{
    return [
        'Q19723451', // smartphone
        'Q22645',    // mobile phone
    ];
}

function phoneCatalogWikidataUserAgent(): string
{
    return 'KupiTelefonCatalogBot/1.0 (https://kupitelefon.rs; wikidata phone catalog sync)';
}

function phoneCatalogWikidataSparql(?string $brandSlug = null): string
{
    $brandsMap = phoneCatalogWikidataBrandsFiltered($brandSlug);
    $brandValues = [];
    foreach (array_keys($brandsMap) as $qid) {
        $brandValues[] = 'wd:' . $qid;
    }
    $brands = implode(' ', $brandValues);
    $types = implode(' ', array_map(static fn(string $q): string => 'wd:' . $q, phoneCatalogWikidataInstanceTypes()));
    $minYear = phoneCatalogWikidataMinYear();

    return <<<SPARQL
SELECT ?item ?itemLabel ?brand ?mass ?massUnit ?height ?width ?depth ?display ?mem ?memUnit ?inception ?cpuLabel WHERE {
  VALUES ?brand { {$brands} }
  VALUES ?type { {$types} }
  ?item wdt:P31 ?type ;
        wdt:P176 ?brand .
  ?item rdfs:label ?itemLabel FILTER (LANG(?itemLabel) = "en")
  FILTER (!REGEX(?itemLabel, "^Q[0-9]+$"))
  FILTER (!CONTAINS(LCASE(?itemLabel), "series"))
  FILTER (!CONTAINS(LCASE(?itemLabel), "concept"))
  OPTIONAL { ?item wdt:P571 ?inception }
  OPTIONAL { ?item wdt:P577 ?published }
  BIND(COALESCE(?inception, ?published) AS ?start)
  # Mobile phone bez datuma često vuče stare feature telefone — traži datum.
  # Smartphone bez datuma i dalje ulazi (noviji modeli ponekad nemaju P571).
  FILTER (
    (BOUND(?start) && YEAR(?start) >= {$minYear})
    || (!BOUND(?start) && ?type = wd:Q19723451)
  )
  OPTIONAL {
    ?item p:P2067/psv:P2067 [ wikibase:quantityAmount ?mass ; wikibase:quantityUnit ?massUnit ] .
  }
  OPTIONAL {
    ?item p:P2048/psv:P2048 [ wikibase:quantityAmount ?height ]
  }
  OPTIONAL {
    ?item p:P2049/psv:P2049 [ wikibase:quantityAmount ?width ]
  }
  OPTIONAL {
    ?item p:P2610/psv:P2610 [ wikibase:quantityAmount ?depth ]
  }
  OPTIONAL {
    ?item p:P3063/psv:P3063 [ wikibase:quantityAmount ?display ]
  }
  OPTIONAL {
    ?item p:P2928/psv:P2928 [ wikibase:quantityAmount ?mem ; wikibase:quantityUnit ?memUnit ] .
  }
  OPTIONAL {
    ?item wdt:P880 ?cpu .
    ?cpu rdfs:label ?cpuLabel FILTER (LANG(?cpuLabel) = "en")
  }
}
SPARQL;
}

/**
 * @return array{ok:bool,error?:string,bindings?:list<array<string,mixed>>,http_status?:int}
 */
function phoneCatalogWikidataResolveBrandArg(?string $brandSlug): array
{
    if ($brandSlug === null) {
        return ['ok' => true, 'slug' => null];
    }
    $raw = trim($brandSlug);
    if ($raw === '' || strcasecmp($raw, 'all') === 0) {
        return ['ok' => true, 'slug' => null];
    }
    $normalized = phoneCatalogWikidataNormalizeBrandSlug($raw);
    if ($normalized === null) {
        return ['ok' => false, 'error' => 'Nepoznat brend za sync: ' . $raw, 'slug' => null];
    }
    return ['ok' => true, 'slug' => $normalized];
}

function phoneCatalogWikidataFetchBindings(?string $sparql = null, ?string $brandSlug = null): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'PHP curl nije dostupan.'];
    }

    $resolved = phoneCatalogWikidataResolveBrandArg($brandSlug);
    if (empty($resolved['ok'])) {
        return ['ok' => false, 'error' => (string)$resolved['error']];
    }
    $brandSlug = $resolved['slug'];

    $query = $sparql ?? phoneCatalogWikidataSparql($brandSlug);
    $url = phoneCatalogWikidataEndpoint() . '?query=' . rawurlencode($query);
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init nije uspeo.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Accept: application/sparql-results+json',
            'User-Agent: ' . phoneCatalogWikidataUserAgent(),
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '') {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Prazan odgovor Wikidata.', 'http_status' => $status];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'error' => 'Wikidata HTTP ' . $status, 'http_status' => $status];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !isset($decoded['results']['bindings']) || !is_array($decoded['results']['bindings'])) {
        return ['ok' => false, 'error' => 'Wikidata JSON nije ispravan.', 'http_status' => $status];
    }

    /** @var list<array<string,mixed>> $bindings */
    $bindings = array_values($decoded['results']['bindings']);
    return ['ok' => true, 'bindings' => $bindings, 'http_status' => $status];
}

function phoneCatalogWikidataBindingValue(array $row, string $key): string
{
    $cell = $row[$key] ?? null;
    if (!is_array($cell)) {
        return '';
    }
    return trim((string)($cell['value'] ?? ''));
}

function phoneCatalogWikidataQidFromUri(string $uri): string
{
    if (preg_match('/(Q\d+)$/', $uri, $m) === 1) {
        return $m[1];
    }
    return '';
}

function phoneCatalogWikidataFormatNumber(string $raw, int $decimals = 1): string
{
    if ($raw === '' || !is_numeric($raw)) {
        return '';
    }
    $num = (float)$raw;
    if (abs($num - round($num)) < 0.0001) {
        return (string)(int)round($num);
    }
    $formatted = number_format($num, $decimals, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function phoneCatalogWikidataFormatLengthMm(string $amount): string
{
    $n = phoneCatalogWikidataFormatNumber($amount, 1);
    return $n !== '' ? $n . ' mm' : '';
}

function phoneCatalogWikidataFormatMass(string $amount, string $unitUri): string
{
    $n = phoneCatalogWikidataFormatNumber($amount, 1);
    if ($n === '') {
        return '';
    }
    $unit = phoneCatalogWikidataQidFromUri($unitUri);
    if ($unit === 'Q11570') { // kilogram
        $grams = phoneCatalogWikidataFormatNumber((string)((float)$amount * 1000), 0);
        return $grams !== '' ? $grams . ' g' : '';
    }
    return $n . ' g';
}

function phoneCatalogWikidataFormatDisplay(string $amount): string
{
    $n = phoneCatalogWikidataFormatNumber($amount, 2);
    return $n !== '' ? $n . ' inča' : '';
}

function phoneCatalogWikidataFormatStorage(string $amount, string $unitUri): string
{
    if ($amount === '' || !is_numeric($amount)) {
        return '';
    }
    $num = (float)$amount;
    $unit = phoneCatalogWikidataQidFromUri($unitUri);
    // Q79738 gigabyte, Q2382367 gibibyte, Q2111 megabyte, Q208212 Q1121037 terabyte-ish
    if (in_array($unit, ['Q79738', 'Q2382367'], true) || $unit === '') {
        $gb = (int)round($num);
        if (in_array($gb, [16, 32, 64, 128, 256, 512], true)) {
            return $gb . 'GB';
        }
        if ($gb === 1024 || $gb === 1000) {
            return '1TB';
        }
        if ($gb === 2048 || $gb === 2000) {
            return '2TB';
        }
        if ($gb >= 16 && $gb <= 4096) {
            return phoneCatalogNormalizeStorage($gb . 'GB');
        }
    }
    if (in_array($unit, ['Q208212', 'Q1121037', 'Q691231'], true) || $num == 1.0 || $num == 2.0) {
        $tb = phoneCatalogNormalizeStorage(phoneCatalogWikidataFormatNumber($amount, 0) . 'TB');
        if ($tb !== '') {
            return $tb;
        }
    }
    return phoneCatalogNormalizeStorage(phoneCatalogWikidataFormatNumber($amount, 0) . 'GB');
}

function phoneCatalogWikidataModelName(string $brand, string $label): string
{
    $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
    $brand = trim($brand);
    if ($brand !== '' && preg_match('/^' . preg_quote($brand, '/') . '\s+/iu', $label) === 1) {
        $label = trim((string)preg_replace('/^' . preg_quote($brand, '/') . '\s+/iu', '', $label));
    }
    // "Samsung Electronics Galaxy …" already handled via brand map name "Samsung"
    return $label !== '' ? $label : trim($brand);
}

/**
 * Odbaci očigledno pogrešne Wikidata stavke (pogrešan proizvođač, serije, šum).
 */
function phoneCatalogWikidataLabelAllowed(string $brandSlug, string $label): bool
{
    $label = trim($label);
    if ($label === '' || preg_match('/^Q\d+$/', $label) === 1) {
        return false;
    }
    $lower = mb_strtolower($label);
    if (str_contains($lower, 'series') || str_contains($lower, 'concept')) {
        return false;
    }

    // Strana marka u nazivu → verovatno loš P176.
    $foreignNeedles = ['iphone', 'samsung', 'galaxy', 'xiaomi', 'redmi', 'poco', 'pixel', 'huawei', 'honor', 'oneplus', 'motorola', 'nokia', 'oppo', 'realme', 'vivo', 'tecno', 'infinix', 'sony', 'xperia', 'zenfone', 'nothing', 'fairphone', 'blackberry', 'lenovo', 'meizu', 'htc'];
    $ownTokens = match ($brandSlug) {
        'apple' => ['iphone'],
        'samsung' => ['samsung', 'galaxy'],
        'google' => ['pixel', 'nexus'],
        'xiaomi' => ['xiaomi', 'redmi', 'poco', 'mi'],
        'huawei' => ['huawei', 'mate', 'nova'],
        'oneplus' => ['oneplus'],
        'motorola' => ['motorola', 'moto'],
        'oppo' => ['oppo'],
        'realme' => ['realme'],
        'honor' => ['honor'],
        'nothing' => ['nothing'],
        'asus' => ['asus', 'zenfone', 'rog'],
        'nokia' => ['nokia'],
        'vivo' => ['vivo', 'iqoo'],
        'tecno' => ['tecno'],
        'infinix' => ['infinix'],
        'sony' => ['sony', 'xperia'],
        'lg' => ['lg'],
        'zte' => ['zte', 'nubia', 'axon'],
        'tcl' => ['tcl'],
        'meizu' => ['meizu'],
        'lenovo' => ['lenovo'],
        'fairphone' => ['fairphone'],
        'htc' => ['htc'],
        'blackberry' => ['blackberry'],
        default => [],
    };
    foreach ($foreignNeedles as $needle) {
        if (in_array($needle, $ownTokens, true)) {
            continue;
        }
        if (str_contains($lower, $needle)) {
            return false;
        }
    }

    return match ($brandSlug) {
        'apple' => (bool)preg_match('/\biphone\b/i', $label),
        'samsung' => (bool)preg_match('/\b(galaxy|samsung)\b/i', $label),
        'google' => (bool)preg_match('/\b(pixel|nexus)\b/i', $label),
        'xiaomi' => (bool)preg_match('/\b(xiaomi|redmi|poco|mi\b)/i', $label),
        'huawei' => (bool)preg_match('/\b(huawei|mate|p\s?\d|nova)\b/i', $label),
        'oneplus' => (bool)preg_match('/\boneplus\b/i', $label),
        'motorola' => (bool)preg_match('/\b(motorola|moto)\b/i', $label),
        'oppo' => (bool)preg_match('/\boppo\b/i', $label),
        'realme' => (bool)preg_match('/\brealme\b/i', $label),
        'honor' => (bool)preg_match('/\bhonor\b/i', $label),
        'nothing' => (bool)preg_match('/\bnothing\b/i', $label),
        'asus' => (bool)preg_match('/\b(asus|zenfone|rog)\b/i', $label),
        'nokia' => (bool)preg_match('/\bnokia\b/i', $label),
        'vivo' => (bool)preg_match('/\b(vivo|iqoo)\b/i', $label),
        'tecno' => (bool)preg_match('/\btecno\b/i', $label),
        'infinix' => (bool)preg_match('/\binfinix\b/i', $label),
        'sony' => (bool)preg_match('/\b(sony|xperia)\b/i', $label),
        'lg' => (bool)preg_match('/\blg\b/i', $label),
        'zte' => (bool)preg_match('/\b(zte|nubia|axon)\b/i', $label),
        'tcl' => (bool)preg_match('/\btcl\b/i', $label),
        'meizu' => (bool)preg_match('/\bmeizu\b/i', $label),
        'lenovo' => (bool)preg_match('/\blenovo\b/i', $label),
        'fairphone' => (bool)preg_match('/\bfairphone\b/i', $label),
        'htc' => (bool)preg_match('/\bhtc\b/i', $label),
        'blackberry' => (bool)preg_match('/\bblackberry\b/i', $label),
        default => true,
    };
}

function phoneCatalogWikidataSlug(string $value): string
{
    if (function_exists('slugifyTitle')) {
        return slugifyTitle($value);
    }
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-');
}

/**
 * Agregira SPARQL redove u sirove modele + izvore.
 *
 * @param list<array<string,mixed>> $bindings
 * @return array{ok:bool,error?:string,sources:list<array<string,mixed>>,models:list<array<string,mixed>>,stats:array<string,int>}
 */
function phoneCatalogWikidataBuildFromBindings(array $bindings): array
{
    $brands = phoneCatalogWikidataBrands();
    $today = gmdate('Y-m-d');
    $byQid = [];

    foreach ($bindings as $row) {
        if (!is_array($row)) {
            continue;
        }
        $itemUri = phoneCatalogWikidataBindingValue($row, 'item');
        $qid = phoneCatalogWikidataQidFromUri($itemUri);
        $brandQid = phoneCatalogWikidataQidFromUri(phoneCatalogWikidataBindingValue($row, 'brand'));
        $label = phoneCatalogWikidataBindingValue($row, 'itemLabel');
        if ($qid === '' || $brandQid === '' || $label === '' || !isset($brands[$brandQid])) {
            continue;
        }
        $brandMeta = $brands[$brandQid];
        if (!phoneCatalogWikidataLabelAllowed($brandMeta['brand_slug'], $label)) {
            continue;
        }

        if (!isset($byQid[$qid])) {
            $name = phoneCatalogWikidataModelName($brandMeta['brand'], $label);
            $slug = phoneCatalogWikidataSlug($name);
            if ($slug === '') {
                continue;
            }
            $sourceId = 'wd-' . $qid;
            $byQid[$qid] = [
                'qid' => $qid,
                'source_id' => $sourceId,
                'brand' => $brandMeta['brand'],
                'brand_slug' => $brandMeta['brand_slug'],
                'name' => $name,
                'official_name' => $label,
                'slug' => $slug,
                'id' => $brandMeta['brand_slug'] . '-' . $slug,
                'label' => $label,
                'mass' => '',
                'height' => '',
                'width' => '',
                'depth' => '',
                'display' => '',
                'cpu' => '',
                'mem' => [],
            ];
        }

        $entry = &$byQid[$qid];
        $mass = phoneCatalogWikidataFormatMass(
            phoneCatalogWikidataBindingValue($row, 'mass'),
            phoneCatalogWikidataBindingValue($row, 'massUnit')
        );
        if ($mass !== '' && $entry['mass'] === '') {
            $entry['mass'] = $mass;
        }
        $height = phoneCatalogWikidataFormatLengthMm(phoneCatalogWikidataBindingValue($row, 'height'));
        if ($height !== '' && $entry['height'] === '') {
            $entry['height'] = $height;
        }
        $width = phoneCatalogWikidataFormatLengthMm(phoneCatalogWikidataBindingValue($row, 'width'));
        if ($width !== '' && $entry['width'] === '') {
            $entry['width'] = $width;
        }
        $depth = phoneCatalogWikidataFormatLengthMm(phoneCatalogWikidataBindingValue($row, 'depth'));
        if ($depth !== '' && $entry['depth'] === '') {
            $entry['depth'] = $depth;
        }
        $display = phoneCatalogWikidataFormatDisplay(phoneCatalogWikidataBindingValue($row, 'display'));
        if ($display !== '' && $entry['display'] === '') {
            $entry['display'] = $display;
        }
        $cpu = phoneCatalogWikidataBindingValue($row, 'cpuLabel');
        if ($cpu !== '' && $entry['cpu'] === '') {
            $entry['cpu'] = $cpu;
        }
        $storage = phoneCatalogWikidataFormatStorage(
            phoneCatalogWikidataBindingValue($row, 'mem'),
            phoneCatalogWikidataBindingValue($row, 'memUnit')
        );
        if ($storage !== '') {
            $entry['mem'][$storage] = true;
        }
        unset($entry);
    }

    // Jedinstvene putanje /katalog/{brand}/{slug} unutar Wikidata skupa.
    $usedPaths = [];
    foreach ($byQid as $qid => &$entry) {
        $path = $entry['brand_slug'] . '/' . $entry['slug'];
        if (isset($usedPaths[$path])) {
            $suffix = mb_strtolower($qid);
            $entry['slug'] .= '-' . $suffix;
            $entry['id'] = $entry['brand_slug'] . '-' . $entry['slug'];
            $path = $entry['brand_slug'] . '/' . $entry['slug'];
        }
        $usedPaths[$path] = $qid;
    }
    unset($entry);

    $sources = [];
    $models = [];
    $withSpecs = 0;
    foreach ($byQid as $entry) {
        $sourceId = $entry['source_id'];
        $sources[$sourceId] = [
            'id' => $sourceId,
            'kind' => 'wikidata',
            'publisher' => 'Wikidata',
            'title' => $entry['label'],
            'url' => 'https://www.wikidata.org/wiki/' . $entry['qid'],
            'retrieved_at' => $today,
            'note' => 'Automatski sync. Specifikacije samo ako postoje na Wikidata stavci; prazna polja se ne pogađaju.',
        ];

        $specs = [];
        if ($entry['display'] !== '') {
            $specs[] = ['group' => 'Ekran', 'label' => 'Dijagonala', 'value' => $entry['display'], 'source_id' => $sourceId];
        }
        if ($entry['cpu'] !== '') {
            $specs[] = ['group' => 'Performanse', 'label' => 'Čipset', 'value' => $entry['cpu'], 'source_id' => $sourceId];
        }
        $dims = array_values(array_filter([$entry['height'], $entry['width'], $entry['depth']], static fn(string $v): bool => $v !== ''));
        if (count($dims) === 3) {
            $specs[] = [
                'group' => 'Kućište',
                'label' => 'Dimenzije',
                'value' => $dims[0] . ' × ' . $dims[1] . ' × ' . $dims[2],
                'source_id' => $sourceId,
            ];
        }
        if ($entry['mass'] !== '') {
            $specs[] = ['group' => 'Kućište', 'label' => 'Težina', 'value' => $entry['mass'], 'source_id' => $sourceId];
        }
        if ($specs !== []) {
            $withSpecs++;
        }

        $variants = [];
        foreach (array_keys($entry['mem']) as $storage) {
            $storageNorm = phoneCatalogNormalizeStorage((string)$storage);
            if ($storageNorm === '') {
                continue;
            }
            $variantId = $entry['id'] . '-' . mb_strtolower($storageNorm);
            $variants[] = [
                'id' => $variantId,
                'storage' => $storageNorm,
                'source_id' => $sourceId,
            ];
        }

        $aliases = array_values(array_unique(array_filter([
            $entry['name'],
            $entry['official_name'],
            $entry['brand'] . ' ' . $entry['name'],
        ])));

        $models[] = [
            'id' => $entry['id'],
            'brand' => $entry['brand'],
            'brand_slug' => $entry['brand_slug'],
            'name' => $entry['name'],
            'official_name' => $entry['official_name'],
            'slug' => $entry['slug'],
            'source_ids' => [$sourceId],
            'aliases' => $aliases,
            'colors' => [],
            'variants' => $variants,
            'highlights' => [],
            'specs' => $specs,
            'image' => null,
            '_wikidata_qid' => $entry['qid'],
        ];
    }

    usort($models, static function (array $a, array $b): int {
        $brand = strcmp((string)$a['brand'], (string)$b['brand']);
        if ($brand !== 0) {
            return $brand;
        }
        return strcmp((string)$a['name'], (string)$b['name']);
    });

    return [
        'ok' => true,
        'sources' => array_values($sources),
        'models' => $models,
        'stats' => [
            'incoming' => count($models),
            'with_specs' => $withSpecs,
            'bindings' => count($bindings),
        ],
    ];
}

/**
 * @param array<string,mixed> $existingRaw
 * @param array{sources:list<array<string,mixed>>,models:list<array<string,mixed>>} $incoming
 * @return array{ok:bool,error?:string,document?:array<string,mixed>,summary:array<string,int|list<string>>}
 */
function phoneCatalogWikidataMerge(array $existingRaw, array $incoming): array
{
    $sources = [];
    foreach (($existingRaw['sources'] ?? []) as $source) {
        if (!is_array($source)) {
            continue;
        }
        $id = trim((string)($source['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $sources[$id] = $source;
    }

    $models = [];
    $order = [];
    $byWd = [];
    foreach (($existingRaw['models'] ?? []) as $model) {
        if (!is_array($model)) {
            continue;
        }
        $id = trim((string)($model['id'] ?? ''));
        if ($id === '' || isset($models[$id])) {
            continue;
        }
        $models[$id] = $model;
        $order[] = $id;
        foreach (($model['source_ids'] ?? []) as $sourceId) {
            $sourceId = (string)$sourceId;
            if (str_starts_with($sourceId, 'wd-')) {
                $byWd[$sourceId] = $id;
            }
        }
    }

    $added = [];
    $updated = [];
    $skipped = 0;
    $usedIds = $models;

    foreach ($incoming['sources'] as $source) {
        $sid = (string)$source['id'];
        if (!isset($sources[$sid]) || ($sources[$sid]['kind'] ?? '') === 'wikidata') {
            $sources[$sid] = $source;
        }
    }

    foreach ($incoming['models'] as $incomingModel) {
        $sourceId = (string)($incomingModel['source_ids'][0] ?? '');
        if ($sourceId === '') {
            $skipped++;
            continue;
        }

        $targetId = $byWd[$sourceId] ?? null;
        if ($targetId === null) {
            $candidateId = (string)$incomingModel['id'];
            $qid = mb_strtolower((string)($incomingModel['_wikidata_qid'] ?? ''));
            $existingSameId = $usedIds[$candidateId] ?? null;
            $existingSharesSource = is_array($existingSameId)
                && in_array($sourceId, array_map('strval', $existingSameId['source_ids'] ?? []), true);
            if (is_array($existingSameId) && !$existingSharesSource) {
                $candidateId .= $qid !== '' ? '-' . $qid : '-wd';
                $incomingModel['slug'] = (string)$incomingModel['slug'] . ($qid !== '' ? '-' . $qid : '-wd');
            }

            // Izbegni sudar putanje /katalog/{brand}/{slug} sa postojećim modelom.
            $brandSlug = (string)$incomingModel['brand_slug'];
            $slug = (string)$incomingModel['slug'];
            foreach ($models as $existingId => $existingModel) {
                if ((string)($existingModel['brand_slug'] ?? '') !== $brandSlug) {
                    continue;
                }
                if ((string)($existingModel['slug'] ?? '') !== $slug) {
                    continue;
                }
                $existingSources = array_map('strval', $existingModel['source_ids'] ?? []);
                if (in_array($sourceId, $existingSources, true)) {
                    $targetId = (string)$existingId;
                    break;
                }
                $suffix = $qid !== '' ? $qid : 'wd';
                $slug .= '-' . $suffix;
                $candidateId = $brandSlug . '-' . $slug;
                $incomingModel['slug'] = $slug;
                break;
            }

            if ($targetId === null && isset($usedIds[$candidateId])) {
                $targetId = $candidateId;
            } elseif ($targetId === null) {
                unset($incomingModel['_wikidata_qid']);
                $incomingModel['id'] = $candidateId;
                $models[$candidateId] = $incomingModel;
                $usedIds[$candidateId] = $incomingModel;
                $order[] = $candidateId;
                $byWd[$sourceId] = $candidateId;
                $added[] = $candidateId;
                continue;
            }
        }

        $existing = $models[$targetId];
        $sourceIds = array_values(array_unique(array_map('strval', array_merge(
            is_array($existing['source_ids'] ?? null) ? $existing['source_ids'] : [],
            [$sourceId]
        ))));

        // Zadrži sve postojeće specifikacije koje nisu sa ovog Wikidata izvora
        // (uključujući manufacturer). Zatim dodaj sveže Wikidata redove.
        $keptSpecs = [];
        foreach (($existing['specs'] ?? []) as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            if ((string)($spec['source_id'] ?? '') === $sourceId) {
                continue;
            }
            $keptSpecs[] = $spec;
        }
        foreach (($incomingModel['specs'] ?? []) as $spec) {
            $keptSpecs[] = $spec;
        }

        $keptVariants = [];
        $variantKeys = [];
        foreach (($existing['variants'] ?? []) as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            if ((string)($variant['source_id'] ?? '') === $sourceId) {
                continue;
            }
            $keptVariants[] = $variant;
            $variantKeys[(string)($variant['storage'] ?? '')] = true;
        }
        foreach (($incomingModel['variants'] ?? []) as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $storage = phoneCatalogNormalizeStorage((string)($variant['storage'] ?? ''));
            $variantId = trim((string)($variant['id'] ?? ''));
            $variantSource = trim((string)($variant['source_id'] ?? ''));
            if ($storage === '' || $variantId === '' || $variantSource === '' || isset($variantKeys[$storage])) {
                continue;
            }
            $variant['storage'] = $storage;
            $keptVariants[] = $variant;
            $variantKeys[$storage] = true;
        }

        $aliases = [];
        foreach (array_merge(
            is_array($existing['aliases'] ?? null) ? $existing['aliases'] : [],
            is_array($incomingModel['aliases'] ?? null) ? $incomingModel['aliases'] : []
        ) as $alias) {
            $alias = trim((string)$alias);
            if ($alias !== '') {
                $aliases[mb_strtolower($alias)] = $alias;
            }
        }

        $models[$targetId] = [
            'id' => $targetId,
            'brand' => (string)($existing['brand'] ?? $incomingModel['brand']),
            'brand_slug' => (string)($existing['brand_slug'] ?? $incomingModel['brand_slug']),
            'name' => (string)($existing['name'] ?? $incomingModel['name']),
            'official_name' => trim((string)($existing['official_name'] ?? '')) !== ''
                ? (string)$existing['official_name']
                : (string)($incomingModel['official_name'] ?? ''),
            'slug' => (string)($existing['slug'] ?? $incomingModel['slug']),
            'source_ids' => $sourceIds,
            'aliases' => array_values($aliases),
            'colors' => is_array($existing['colors'] ?? null) ? $existing['colors'] : [],
            'variants' => $keptVariants,
            'highlights' => is_array($existing['highlights'] ?? null) ? $existing['highlights'] : [],
            'specs' => $keptSpecs,
            'image' => $existing['image'] ?? null,
        ];
        $updated[] = $targetId;
    }

    $document = [
        'version' => 1,
        'coverage_note' => trim((string)($existingRaw['coverage_note'] ?? '')),
        'sources' => array_values($sources),
        'models' => array_map(static fn(string $id): array => $models[$id], array_values(array_filter($order, static fn(string $id): bool => isset($models[$id])))),
    ];

    // Deduplicate order if needed and append any missing keys
    $seen = [];
    $orderedModels = [];
    foreach ($order as $id) {
        if (!isset($models[$id]) || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $orderedModels[] = $models[$id];
    }
    foreach ($models as $id => $model) {
        if (isset($seen[$id])) {
            continue;
        }
        $orderedModels[] = $model;
    }
    $document['models'] = $orderedModels;

    $bundle = phoneCatalogNormalizeBundle($document);
    if (empty($bundle['ok'])) {
        return [
            'ok' => false,
            'error' => 'Spojeni katalog nije prošao proveru: ' . implode('; ', $bundle['errors']),
            'summary' => [
                'added' => count($added),
                'updated' => count($updated),
                'skipped' => $skipped,
                'total' => count($orderedModels),
                'added_ids' => $added,
                'updated_ids' => $updated,
            ],
        ];
    }

    return [
        'ok' => true,
        'document' => phoneCatalogExportDocument($bundle),
        'bundle' => $bundle,
        'summary' => [
            'added' => count($added),
            'updated' => count(array_unique($updated)),
            'skipped' => $skipped,
            'total' => count($bundle['models']),
            'added_ids' => array_slice($added, 0, 40),
            'updated_ids' => array_slice(array_values(array_unique($updated)), 0, 40),
        ],
    ];
}

/**
 * @return array{ok:bool,error?:string,summary?:array<string,mixed>,document?:array<string,mixed>,bundle?:array<string,mixed>}
 */
function phoneCatalogWikidataSync(bool $apply = false, ?array $bindings = null, ?string $brandSlug = null): array
{
    $resolved = phoneCatalogWikidataResolveBrandArg($brandSlug);
    if (empty($resolved['ok'])) {
        return ['ok' => false, 'error' => (string)$resolved['error']];
    }
    $brandSlug = $resolved['slug'];

    if ($bindings === null) {
        $fetch = phoneCatalogWikidataFetchBindings(null, $brandSlug);
        if (empty($fetch['ok'])) {
            return ['ok' => false, 'error' => (string)($fetch['error'] ?? 'Wikidata fetch nije uspeo.')];
        }
        $bindings = $fetch['bindings'];
    }

    $built = phoneCatalogWikidataBuildFromBindings($bindings);
    if (empty($built['ok'])) {
        return ['ok' => false, 'error' => (string)($built['error'] ?? 'Gradnja modela nije uspela.')];
    }

    // Ako je etapa po brendu, zadrži samo modele tog slug-a (zaštita od šuma u fixture/testu).
    if ($brandSlug !== null) {
        $built['models'] = array_values(array_filter(
            $built['models'],
            static fn(array $m): bool => (string)($m['brand_slug'] ?? '') === $brandSlug
        ));
        $built['stats']['incoming'] = count($built['models']);
        $withSpecs = 0;
        foreach ($built['models'] as $m) {
            if (($m['specs'] ?? []) !== []) {
                $withSpecs++;
            }
        }
        $built['stats']['with_specs'] = $withSpecs;
    }

    $existing = phoneCatalogLoadRawDocument();
    $merged = phoneCatalogWikidataMerge($existing, [
        'sources' => $built['sources'],
        'models' => $built['models'],
    ]);
    if (empty($merged['ok'])) {
        return $merged;
    }

    $summary = $merged['summary'];
    $summary['incoming'] = $built['stats']['incoming'];
    $summary['incoming_with_specs'] = $built['stats']['with_specs'];
    $summary['bindings'] = $built['stats']['bindings'];
    $summary['brand'] = $brandSlug ?? 'all';

    if ($apply) {
        phoneCatalogWriteBundle($merged['bundle']);
        $summary['applied'] = 1;
    } else {
        $summary['applied'] = 0;
    }

    return [
        'ok' => true,
        'summary' => $summary,
        'document' => $merged['document'],
        'bundle' => $merged['bundle'],
    ];
}
