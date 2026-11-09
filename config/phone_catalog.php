<?php

declare(strict_types=1);

/**
 * Katalog telefona.
 *
 * Izvor istine za modele je data/phone_catalog_seed.json, a posle uvoza i
 * dokument phone_models.json (isti sloj kao ostali JSON, uključujući MySQL
 * json_documents). Oglasi dobijaju samo dodatna polja veze. Javni tekst,
 * cena i telefon oglasa se ovde ne menjaju.
 */

function phoneCatalogSeedPath(): string
{
    return dirname(__DIR__) . '/data/phone_catalog_seed.json';
}

function phoneCatalogStoreFilename(): string
{
    return 'phone_models.json';
}

function phoneCatalogResetCache(): void
{
    phoneCatalogBundle(true);
}

/** @return array<string,mixed> */
function phoneCatalogBundle(bool $reset = false): array
{
    static $bundle = null;
    if ($reset) {
        $bundle = null;
        return ['ok' => false, 'errors' => [], 'models' => [], 'order' => [], 'sources' => [], 'coverage_note' => ''];
    }
    if (is_array($bundle)) {
        return $bundle;
    }

    $raw = null;
    if (function_exists('readJsonFile')) {
        $stored = readJsonFile(phoneCatalogStoreFilename());
        if (is_array($stored) && isset($stored['models']) && is_array($stored['models']) && $stored['models'] !== []) {
            $raw = $stored;
        }
    }
    if ($raw === null) {
        $path = phoneCatalogSeedPath();
        if (!is_file($path)) {
            $bundle = phoneCatalogEmptyBundle(['Nema data/phone_catalog_seed.json.']);
            return $bundle;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        $raw = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($raw)) {
        $bundle = phoneCatalogEmptyBundle(['Katalog nije ispravan JSON.']);
        return $bundle;
    }
    $bundle = phoneCatalogNormalizeBundle($raw);
    return $bundle;
}

/** @param list<string> $errors
 *  @return array<string,mixed>
 */
function phoneCatalogEmptyBundle(array $errors): array
{
    return [
        'ok' => false,
        'errors' => $errors,
        'models' => [],
        'order' => [],
        'sources' => [],
        'coverage_note' => '',
    ];
}

/**
 * @return list<string>
 */
function phoneCatalogValidateBundle(array $raw): array
{
    $errors = [];
    $sources = $raw['sources'] ?? null;
    $models = $raw['models'] ?? null;
    if (!is_array($sources) || $sources === []) {
        $errors[] = 'Nema izvora.';
        $sources = [];
    }
    if (!is_array($models) || $models === []) {
        $errors[] = 'Nema modela.';
        $models = [];
    }

    $sourceIds = [];
    foreach ($sources as $i => $source) {
        if (!is_array($source)) {
            $errors[] = 'Izvor #' . $i . ' nije objekat.';
            continue;
        }
        $id = trim((string)($source['id'] ?? ''));
        $url = trim((string)($source['url'] ?? ''));
        $kind = trim((string)($source['kind'] ?? ''));
        if ($id === '' || isset($sourceIds[$id])) {
            $errors[] = 'Izvor ima prazan ili dupli id.';
            continue;
        }
        if (!in_array($kind, ['manufacturer', 'wikidata', 'licensed', 'public'], true)) {
            $errors[] = 'Izvor ' . $id . ' ima nepoznat kind.';
        }
        if (preg_match('#^https://#', $url) !== 1) {
            $errors[] = 'Izvor ' . $id . ' nema https URL.';
        }
        if (trim((string)($source['publisher'] ?? '')) === '' || trim((string)($source['title'] ?? '')) === '') {
            $errors[] = 'Izvor ' . $id . ' nema publisher ili title.';
        }
        $sourceIds[$id] = true;
    }

    $seen = [];
    $paths = [];
    foreach ($models as $i => $model) {
        if (!is_array($model)) {
            $errors[] = 'Model #' . $i . ' nije objekat.';
            continue;
        }
        $id = trim((string)($model['id'] ?? ''));
        $brand = trim((string)($model['brand'] ?? ''));
        $name = trim((string)($model['name'] ?? ''));
        $brandSlug = trim((string)($model['brand_slug'] ?? ''));
        $slug = trim((string)($model['slug'] ?? ''));
        if ($id === '' || isset($seen[$id])) {
            $errors[] = 'Model ima prazan ili dupli id (' . $id . ').';
            continue;
        }
        $seen[$id] = true;
        if ($brand === '' || $name === '') {
            $errors[] = 'Model ' . $id . ' nema marku ili naziv.';
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $brandSlug) !== 1 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors[] = 'Model ' . $id . ' ima neispravan slug.';
        }
        $path = $brandSlug . '/' . $slug;
        if (isset($paths[$path])) {
            $errors[] = 'Dupla putanja /katalog/' . $path;
        }
        $paths[$path] = true;
        $modelSources = $model['source_ids'] ?? [];
        if (!is_array($modelSources) || $modelSources === []) {
            $errors[] = 'Model ' . $id . ' nema izvor identiteta.';
            $modelSources = [];
        }
        foreach ($modelSources as $sourceId) {
            if (!isset($sourceIds[(string)$sourceId])) {
                $errors[] = 'Model ' . $id . ' cita nepoznat izvor ' . $sourceId;
            }
        }
        foreach (['specs', 'highlights', 'variants', 'colors', 'aliases'] as $listKey) {
            if (isset($model[$listKey]) && !is_array($model[$listKey])) {
                $errors[] = 'Model ' . $id . ' polje ' . $listKey . ' nije lista.';
            }
        }
        foreach (($model['variants'] ?? []) as $variant) {
            if (!is_array($variant)) {
                $errors[] = 'Model ' . $id . ' ima losu varijantu.';
                continue;
            }
            $storage = phoneCatalogNormalizeStorage((string)($variant['storage'] ?? ''));
            $variantId = trim((string)($variant['id'] ?? ''));
            $sourceId = trim((string)($variant['source_id'] ?? ''));
            if ($variantId === '' || $storage === '' || !isset($sourceIds[$sourceId])) {
                $errors[] = 'Model ' . $id . ' ima varijantu bez id, kapaciteta ili izvora.';
            }
        }
        foreach (['specs', 'highlights'] as $specKey) {
            foreach (($model[$specKey] ?? []) as $row) {
                if (!is_array($row)) {
                    $errors[] = 'Model ' . $id . ' ima prazan red specifikacije.';
                    continue;
                }
                $value = trim((string)($row['value'] ?? $row['detail'] ?? $row['title'] ?? ''));
                $sourceId = trim((string)($row['source_id'] ?? ''));
                if ($value === '' || !isset($sourceIds[$sourceId])) {
                    $errors[] = 'Model ' . $id . ' ima specifikaciju bez vrednosti ili izvora.';
                }
            }
        }
        if (array_key_exists('image', $model) && $model['image'] !== null) {
            $image = $model['image'];
            $license = is_array($image) ? strtolower(trim((string)($image['license'] ?? ''))) : '';
            $imageUrl = is_array($image) ? trim((string)($image['url'] ?? '')) : '';
            if (!in_array($license, ['cc0', 'cc-by', 'cc-by-sa'], true) || preg_match('#^https://#', $imageUrl) !== 1) {
                $errors[] = 'Model ' . $id . ' ima sliku bez dozvoljene licence (cc0, cc-by, cc-by-sa) i https URL-a.';
            }
        }
    }

    return $errors;
}

/** @return array<string,mixed> */
function phoneCatalogNormalizeBundle(array $raw): array
{
    $errors = phoneCatalogValidateBundle($raw);
    if ($errors !== []) {
        return phoneCatalogEmptyBundle($errors);
    }

    $sources = [];
    foreach ($raw['sources'] as $source) {
        $id = trim((string)$source['id']);
        $sources[$id] = [
            'id' => $id,
            'kind' => (string)$source['kind'],
            'publisher' => trim((string)$source['publisher']),
            'title' => trim((string)$source['title']),
            'url' => trim((string)$source['url']),
            'retrieved_at' => trim((string)($source['retrieved_at'] ?? '')),
            'note' => trim((string)($source['note'] ?? '')),
        ];
    }

    $models = [];
    $order = [];
    foreach ($raw['models'] as $model) {
        $id = trim((string)$model['id']);
        $variants = [];
        foreach (($model['variants'] ?? []) as $variant) {
            $storage = phoneCatalogNormalizeStorage((string)$variant['storage']);
            $variants[(string)$variant['id']] = [
                'id' => (string)$variant['id'],
                'storage' => $storage,
                'source_id' => (string)$variant['source_id'],
            ];
        }
        $aliases = [];
        foreach (array_merge(
            [(string)$model['name'], (string)($model['official_name'] ?? ''), (string)$model['brand'] . ' ' . (string)$model['name']],
            is_array($model['aliases'] ?? null) ? $model['aliases'] : []
        ) as $alias) {
            $folded = phoneCatalogFold((string)$alias);
            if ($folded !== '') {
                $aliases[$folded] = phoneCatalogTokens($folded);
            }
        }
        $specs = [];
        foreach (($model['specs'] ?? []) as $row) {
            $specs[] = [
                'group' => trim((string)($row['group'] ?? '')),
                'label' => trim((string)($row['label'] ?? '')),
                'value' => trim((string)($row['value'] ?? '')),
                'source_id' => (string)$row['source_id'],
            ];
        }
        $highlights = [];
        foreach (($model['highlights'] ?? []) as $row) {
            $highlights[] = [
                'title' => trim((string)($row['title'] ?? '')),
                'detail' => trim((string)($row['detail'] ?? '')),
                'source_id' => (string)$row['source_id'],
            ];
        }
        $colors = [];
        foreach (($model['colors'] ?? []) as $color) {
            $color = trim((string)$color);
            if ($color !== '') {
                $colors[] = $color;
            }
        }
        $sourceIds = array_values(array_map('strval', $model['source_ids']));
        $models[$id] = [
            'id' => $id,
            'brand' => trim((string)$model['brand']),
            'brand_slug' => (string)$model['brand_slug'],
            'name' => trim((string)$model['name']),
            'official_name' => trim((string)($model['official_name'] ?? '')),
            'slug' => (string)$model['slug'],
            'source_ids' => $sourceIds,
            'aliases' => array_values($aliases),
            'variants' => $variants,
            'highlights' => $highlights,
            'specs' => $specs,
            'colors' => $colors,
            'image' => null,
        ];
        $order[] = $id;
    }

    return [
        'ok' => true,
        'errors' => [],
        'models' => $models,
        'order' => $order,
        'sources' => $sources,
        'coverage_note' => trim((string)($raw['coverage_note'] ?? '')),
    ];
}

/** @return list<array<string,mixed>> */
function phoneCatalogModels(): array
{
    $bundle = phoneCatalogBundle();
    $out = [];
    foreach ($bundle['order'] as $id) {
        if (isset($bundle['models'][$id])) {
            $out[] = $bundle['models'][$id];
        }
    }
    return $out;
}

function phoneCatalogModel(string $id): ?array
{
    $bundle = phoneCatalogBundle();
    return $bundle['models'][$id] ?? null;
}

function phoneCatalogFindBySlug(string $brandSlug, string $modelSlug): ?array
{
    $brandSlug = strtolower(trim($brandSlug));
    $modelSlug = strtolower(trim($modelSlug));
    foreach (phoneCatalogModels() as $model) {
        if ($model['brand_slug'] === $brandSlug && $model['slug'] === $modelSlug) {
            return $model;
        }
    }
    return null;
}

function phoneCatalogFold(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = strtr($value, [
        'š' => 's', 'đ' => 'dj', 'č' => 'c', 'ć' => 'c', 'ž' => 'z',
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u',
    ]);
    $value = preg_replace('/\b(iphone|pixel)(\d)/u', '$1 $2', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

/** @return list<string> */
function phoneCatalogTokens(string $folded): array
{
    if ($folded === '') {
        return [];
    }
    return array_values(array_filter(explode(' ', $folded), static fn(string $token): bool => $token !== ''));
}

function phoneCatalogNormalizeStorage(string $raw): string
{
    $folded = phoneCatalogFold($raw);
    if (preg_match('/\b(16|32|64|128|256|512)\s*gb\b/', $folded, $match) === 1) {
        return $match[1] . 'GB';
    }
    if (preg_match('/\b(1|2)\s*tb\b/', $folded, $match) === 1) {
        return $match[1] . 'TB';
    }
    return '';
}

function phoneCatalogAdStorage(array $ad): string
{
    $fromField = phoneCatalogNormalizeStorage((string)($ad['storage'] ?? ''));
    if ($fromField !== '') {
        return $fromField;
    }
    $explicit = phoneCatalogNormalizeStorage((string)($ad['phone_storage_norm'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }
    return phoneCatalogNormalizeStorage((string)($ad['title'] ?? '') . ' ' . (string)($ad['model'] ?? ''));
}

function phoneCatalogAdIsLinkablePhone(array $ad): bool
{
    if (!function_exists('shopAdIsPhoneListing') || !shopAdIsPhoneListing($ad)) {
        return false;
    }
    $text = mb_strtolower(trim((string)($ad['title'] ?? '') . ' ' . (string)($ad['model'] ?? '')));
    if ($text === '') {
        return false;
    }
    if (preg_match('/\b(ekran|displej|lcd|flex|kućište|kuciste|matičn|maticn|rezervni)\b/u', $text) === 1) {
        return false;
    }
    return true;
}

/**
 * Najduži tačan naziv pobedi. Sledeća reč pro/max/plus/mini/ultra/... sprečava
 * da "iPhone 15" proguta "iPhone 15 Pro". Nema približnog poklapanja.
 *
 * @return array{status:string,model_id:?string,variant_id:?string,storage:?string,alias:?string,reason:string}
 */
function phoneCatalogMatchAd(array $ad): array
{
    $none = ['status' => 'unmatched', 'model_id' => null, 'variant_id' => null, 'storage' => null, 'alias' => null, 'reason' => 'nema_modela'];
    if (!phoneCatalogAdIsLinkablePhone($ad)) {
        $none['status'] = 'skip';
        $none['reason'] = 'nije_telefon';
        return $none;
    }

    $adBrand = phoneCatalogFold((string)($ad['brand'] ?? ''));
    $haystack = phoneCatalogTokens(phoneCatalogFold(
        (string)($ad['brand'] ?? '') . ' ' . (string)($ad['model'] ?? '') . ' ' . (string)($ad['title'] ?? '')
    ));
    if ($haystack === []) {
        return $none;
    }

    $best = null;
    $bestLen = 0;
    $tie = false;
    $bestAlias = '';
    foreach (phoneCatalogModels() as $model) {
        $modelBrand = phoneCatalogFold((string)$model['brand']);
        if ($adBrand !== '' && $adBrand !== 'ostalo' && $adBrand !== $modelBrand) {
            continue;
        }
        foreach ($model['aliases'] as $aliasTokens) {
            if (!is_array($aliasTokens) || !phoneCatalogAliasMatches($aliasTokens, $haystack)) {
                continue;
            }
            $len = count($aliasTokens);
            if ($best === null || $len > $bestLen) {
                $best = $model;
                $bestLen = $len;
                $tie = false;
                $bestAlias = implode(' ', $aliasTokens);
            } elseif ($len === $bestLen && $best['id'] !== $model['id']) {
                $tie = true;
            }
        }
    }

    if ($tie || $best === null) {
        if ($tie) {
            $none['status'] = 'ambiguous';
            $none['reason'] = 'dva_modela';
        }
        return $none;
    }

    $storage = phoneCatalogAdStorage($ad);
    $variantId = null;
    if ($storage !== '') {
        foreach ($best['variants'] as $variant) {
            if ($variant['storage'] === $storage) {
                $variantId = (string)$variant['id'];
                break;
            }
        }
    }

    return [
        'status' => 'linked',
        'model_id' => (string)$best['id'],
        'variant_id' => $variantId,
        'storage' => $storage !== '' ? $storage : null,
        'alias' => $bestAlias,
        'reason' => 'tacan_naziv',
    ];
}

/** @param list<string> $alias @param list<string> $haystack */
function phoneCatalogAliasMatches(array $alias, array $haystack): bool
{
    $size = count($alias);
    $total = count($haystack);
    if ($size === 0 || $size > $total) {
        return false;
    }
    $block = ['pro' => true, 'max' => true, 'plus' => true, 'mini' => true, 'ultra' => true, 'fe' => true, 'lite' => true, 'se' => true, 'air' => true, 'fold' => true, 'flip' => true, 'note' => true];
    $skip = ['5g' => true, '4g' => true, 'lte' => true];
    for ($i = 0; $i <= $total - $size; $i++) {
        $matches = true;
        for ($j = 0; $j < $size; $j++) {
            if ($haystack[$i + $j] !== $alias[$j]) {
                $matches = false;
                break;
            }
        }
        if (!$matches) {
            continue;
        }
        $next = $i + $size;
        while ($next < $total && isset($skip[$haystack[$next]])) {
            $next++;
        }
        if ($next < $total && isset($block[$haystack[$next]])) {
            continue;
        }
        return true;
    }
    return false;
}

function phoneCatalogAdCountsAsOffer(array $ad): bool
{
    if ((int)($ad['is_active'] ?? 0) !== 1 || !empty($ad['is_sold'])) {
        return false;
    }
    if (!phoneCatalogAdIsLinkablePhone($ad)) {
        return false;
    }
    $listing = function_exists('getAdListingType') ? getAdListingType($ad) : 'sell';
    return $listing === 'sell';
}

function phoneCatalogBattery(array $ad): ?int
{
    if (!isset($ad['battery_health']) || $ad['battery_health'] === '' || $ad['battery_health'] === null || !is_numeric($ad['battery_health'])) {
        return null;
    }
    $value = (int)$ad['battery_health'];
    if ($value < 1 || $value > 100) {
        return null;
    }
    return $value;
}

function phoneCatalogWarrantyKnown(array $ad): ?bool
{
    if (!array_key_exists('has_warranty', $ad) || $ad['has_warranty'] === '' || $ad['has_warranty'] === null) {
        return null;
    }
    return (int)$ad['has_warranty'] === 1;
}

/** @param list<array<string,mixed>>|null $ads
 *  @return array<string,list<array<string,mixed>>>
 */
function phoneCatalogOffersByModel(?array $ads = null): array
{
    $ads = $ads ?? (function_exists('getAllAds') ? getAllAds() : []);
    $grouped = [];
    foreach ($ads as $ad) {
        if (!is_array($ad) || !phoneCatalogAdCountsAsOffer($ad)) {
            continue;
        }
        $id = trim((string)($ad['phone_model_id'] ?? ''));
        if ($id === '' || phoneCatalogModel($id) === null) {
            continue;
        }
        $grouped[$id][] = $ad;
    }
    return $grouped;
}

/** @param list<array<string,mixed>> $offers
 *  @return array{active:int,fixed:int,negotiable:int,min:?float,max:?float,median:?float,prices:list<float>}
 */
function phoneCatalogPriceStats(array $offers): array
{
    $prices = [];
    $negotiable = 0;
    foreach ($offers as $ad) {
        if (!is_array($ad) || !phoneCatalogAdCountsAsOffer($ad)) {
            continue;
        }
        if (!function_exists('isAdPriceOpen') || isAdPriceOpen($ad)) {
            $negotiable++;
            continue;
        }
        $eur = function_exists('adPriceEur') ? adPriceEur($ad) : (float)($ad['price'] ?? 0);
        if ($eur <= 0) {
            $negotiable++;
            continue;
        }
        $prices[] = round($eur, 2);
    }
    sort($prices, SORT_NUMERIC);
    $count = count($prices);
    $median = null;
    if ($count === 1) {
        $median = $prices[0];
    } elseif ($count > 1) {
        $mid = intdiv($count, 2);
        $median = $count % 2 === 1 ? $prices[$mid] : round(($prices[$mid - 1] + $prices[$mid]) / 2, 2);
    }
    return [
        'active' => count($offers),
        'fixed' => $count,
        'negotiable' => $negotiable,
        'min' => $count > 0 ? $prices[0] : null,
        'max' => $count > 0 ? $prices[$count - 1] : null,
        'median' => $median,
        'prices' => $prices,
    ];
}

function phoneCatalogFormatEur(float $amount): string
{
    $rounded = round($amount, 2);
    if (abs($rounded - round($rounded)) < 0.001) {
        return number_format($rounded, 0, ',', '.') . ' €';
    }
    return number_format($rounded, 2, ',', '.') . ' €';
}

function phoneCatalogPriceMethodology(): string
{
    return 'Minimum, medijana i maksimum računaju se samo od aktivnih oglasa za prodaju ovog modela koji imaju fiksnu cenu. Oglasi „Po dogovoru“ i „Na kontakt“ ulaze u broj aktivnih oglasa, ali ne u cene. Kupovina, zamena, prodati i neaktivni oglasi se ne računaju. Iznos u dinarima prikazuje se u evrima po kursu koji sajt već koristi.';
}

function phoneCatalogRecognitionNote(int $active, int $fixed): string
{
    if ($active <= 0) {
        return 'Nema prepoznatih aktivnih oglasa za ovaj model.';
    }
    return $fixed . ' od ' . $active . ' aktivnih oglasa ima fiksnu cenu i ulazi u minimum, medijanu i maksimum. Oglasi su povezani sa ovim modelom preko model_id.';
}

function phoneCatalogEmptyOffersText(string $name): string
{
    return 'Trenutno nema aktivnih oglasa za ' . $name . '. Cena nije prikazana jer je nemamo iz oglasa.';
}

/** @param list<array<string,mixed>> $offers
 *  @return list<array{label:string,source:string}>
 */
function phoneCatalogMemoryChips(array $model, array $offers): array
{
    $chips = [];
    foreach ($model['variants'] as $variant) {
        $chips[$variant['storage']] = ['label' => $variant['storage'], 'source' => 'official'];
    }
    if ($chips === []) {
        foreach ($offers as $ad) {
            $storage = phoneCatalogAdStorage($ad);
            if ($storage !== '' && !isset($chips[$storage])) {
                $chips[$storage] = ['label' => $storage, 'source' => 'listing'];
            }
        }
    }
    return array_values($chips);
}

function phoneCatalogUrl(array $model): string
{
    return '/katalog/' . rawurlencode((string)$model['brand_slug']) . '/' . rawurlencode((string)$model['slug']);
}

function phoneCatalogIndexUrl(): string
{
    return '/katalog';
}

function phoneCatalogUrlForAd(array $ad): string
{
    $id = trim((string)($ad['phone_model_id'] ?? ''));
    if ($id === '') {
        return '';
    }
    $model = phoneCatalogModel($id);
    return $model ? phoneCatalogUrl($model) : '';
}

/** @return list<string> */
function phoneCatalogSitemapPaths(): array
{
    $paths = [phoneCatalogIndexUrl()];
    foreach (phoneCatalogModels() as $model) {
        $paths[] = phoneCatalogUrl($model);
    }
    return $paths;
}

function phoneCatalogNavKey(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/') ?: '/';
    if ($path === '/katalog' || str_starts_with($path, '/katalog/')) {
        return 'katalog';
    }
    if ($path === '/oglasi/oprema') {
        return 'oprema';
    }
    if ($path === '/oglasi/delovi') {
        return 'delovi';
    }
    if ($path === '/oglasi/servis') {
        return 'servis';
    }
    if ($path === '/' || $path === '/oglasi' || str_starts_with($path, '/oglasi/')) {
        return 'oglasi';
    }
    return '';
}

function renderPhoneCatalogNav(): string
{
    $current = phoneCatalogNavKey();
    $items = [
        ['key' => 'oglasi', 'href' => '/', 'label' => 'Oglasi'],
        ['key' => 'katalog', 'href' => '/katalog', 'label' => 'Katalog telefona', 'badge' => 'Novo'],
        ['key' => 'delovi', 'href' => '/oglasi/delovi', 'label' => 'Delovi'],
        ['key' => 'oprema', 'href' => '/oglasi/oprema', 'label' => 'Oprema'],
        ['key' => 'servis', 'href' => '/oglasi/servis', 'label' => 'Servis'],
    ];
    $html = '<nav class="site-catnav" aria-label="Kategorije"><div class="site-catnav-inner">';
    foreach ($items as $item) {
        $on = $current === $item['key'];
        $html .= '<a href="' . h($item['href']) . '"' . ($on ? ' class="is-on" aria-current="page"' : '') . '>' . h($item['label']);
        if (!empty($item['badge'])) {
            $html .= ' <span class="site-catnav-new">' . h((string)$item['badge']) . '</span>';
        }
        $html .= '</a>';
    }
    $html .= '</div></nav>';
    return $html;
}

/**
 * Polja koja forma sme da upiše. Opis, naslov, cena, grad i stanje se ne diraju.
 *
 * @return array<string,mixed>
 */
function phoneCatalogAssignmentFromPost(array $post, string $adType, string $deviceType, string $storageRaw, string $brandRaw, string $modelRaw): array
{
    $manual = [
        'phone_model_id' => null,
        'phone_variant_id' => null,
        'phone_storage_norm' => null,
        'phone_model_link' => [
            'method' => 'manual',
            'at' => date('Y-m-d H:i:s'),
        ],
    ];
    if ($adType !== 'telefon' || normalizeDeviceType($deviceType) !== 'phone') {
        $manual['phone_model_link'] = null;
        return $manual;
    }

    $storageNorm = phoneCatalogNormalizeStorage($storageRaw);
    if ($storageNorm === '') {
        $storageNorm = phoneCatalogNormalizeStorage($modelRaw . ' ' . $storageRaw);
    }
    $manual['phone_storage_norm'] = $storageNorm !== '' ? $storageNorm : null;

    $id = trim((string)($post['phone_model_id'] ?? ''));
    if ($id === '') {
        return $manual;
    }
    $model = phoneCatalogModel($id);
    if ($model === null) {
        return $manual;
    }

    $variantPosted = trim((string)($post['phone_variant_id'] ?? ''));
    $variantId = null;
    if ($variantPosted !== '' && isset($model['variants'][$variantPosted])) {
        $variantId = $variantPosted;
        $storageNorm = (string)$model['variants'][$variantPosted]['storage'];
    } elseif ($storageNorm !== '') {
        foreach ($model['variants'] as $variant) {
            if ($variant['storage'] === $storageNorm) {
                $variantId = (string)$variant['id'];
                break;
            }
        }
    }

    $fields = [
        'phone_model_id' => (string)$model['id'],
        'phone_variant_id' => $variantId,
        'phone_storage_norm' => $storageNorm !== '' ? $storageNorm : null,
        'phone_model_link' => [
            'method' => 'seller',
            'confidence' => 'selected',
            'at' => date('Y-m-d H:i:s'),
            'model_id' => (string)$model['id'],
            'variant_id' => $variantId,
        ],
        'brand' => (string)$model['brand'],
        'model' => (string)$model['name'],
    ];
    if ($variantPosted !== '' && $variantId !== null) {
        $fields['storage'] = $storageNorm;
    }
    unset($brandRaw);
    return $fields;
}

/**
 * @param list<array<string,mixed>> $ads
 * @return array{ads:list<array<string,mixed>>,summary:array<string,mixed>}
 */
function phoneCatalogPlanLinks(array $ads, bool $apply = false, bool $force = false): array
{
    $summary = [
        'total' => count($ads),
        'linked' => 0,
        'already' => 0,
        'skipped_not_phone' => 0,
        'skipped_manual' => 0,
        'skipped_seller' => 0,
        'conflict' => 0,
        'ambiguous' => 0,
        'unmatched' => 0,
        'rows' => [],
    ];
    $next = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $id = (int)($ad['id'] ?? 0);
        $match = phoneCatalogMatchAd($ad);
        $method = '';
        if (is_array($ad['phone_model_link'] ?? null)) {
            $method = (string)($ad['phone_model_link']['method'] ?? '');
        }
        $existing = trim((string)($ad['phone_model_id'] ?? ''));
        $row = [
            'id' => $id,
            'title' => trim((string)($ad['title'] ?? '')),
            'result' => $match['status'],
            'model_id' => $match['model_id'],
            'variant_id' => $match['variant_id'],
            'storage' => $match['storage'],
            'reason' => $match['reason'],
        ];

        $keep = $ad;
        if (!$force && $method === 'manual') {
            $summary['skipped_manual']++;
            $row['result'] = 'skipped_manual';
        } elseif (!$force && $method === 'seller' && $existing !== '') {
            $summary['skipped_seller']++;
            $row['result'] = 'skipped_seller';
        } elseif ($match['status'] === 'skip') {
            $summary['skipped_not_phone']++;
        } elseif ($match['status'] === 'ambiguous') {
            $summary['ambiguous']++;
        } elseif ($match['status'] !== 'linked') {
            $summary['unmatched']++;
        } elseif (
            $existing === (string)$match['model_id']
            && (string)($ad['phone_variant_id'] ?? '') === (string)($match['variant_id'] ?? '')
            && (string)($ad['phone_storage_norm'] ?? '') === (string)($match['storage'] ?? '')
        ) {
            $summary['already']++;
            $row['result'] = 'already';
        } elseif ($existing !== '' && $existing !== (string)$match['model_id'] && !$force) {
            $summary['conflict']++;
            $row['result'] = 'conflict';
            $row['existing'] = $existing;
        } else {
            $summary['linked']++;
            $row['result'] = 'linked';
            if ($apply) {
                $keep['phone_model_id'] = $match['model_id'];
                $keep['phone_variant_id'] = $match['variant_id'];
                $keep['phone_storage_norm'] = $match['storage'];
                $keep['phone_model_link'] = [
                    'method' => 'migration',
                    'confidence' => 'exact',
                    'at' => date('Y-m-d H:i:s'),
                    'model_id' => $match['model_id'],
                    'variant_id' => $match['variant_id'],
                    'alias' => $match['alias'],
                ];
            }
        }
        if (count($summary['rows']) < 80) {
            $summary['rows'][] = $row;
        }
        $next[] = $keep;
    }
    return ['ads' => $next, 'summary' => $summary];
}

/** @param array<string,mixed> $query
 *  @param array<string,list<array<string,mixed>>> $offersByModel
 *  @return array<string,mixed>
 */
function phoneCatalogSanitizeIndexQuery(array $query, array $offersByModel): array
{
    $facets = phoneCatalogIndexFacets($offersByModel);
    $brands = [];
    $wanted = $query['brand'] ?? [];
    if (!is_array($wanted)) {
        $wanted = $wanted === '' || $wanted === null ? [] : explode(',', (string)$wanted);
    }
    foreach ($wanted as $brand) {
        $brand = trim((string)$brand);
        if (in_array($brand, $facets['brands'], true) && !in_array($brand, $brands, true)) {
            $brands[] = $brand;
        }
    }
    $band = trim((string)($query['price_band'] ?? ''));
    if (!in_array($band, ['lt100', '100-300', '300-600', 'gt600'], true) || !$facets['price']) {
        $band = '';
    }
    $condition = trim((string)($query['condition'] ?? ''));
    if (!in_array($condition, $facets['conditions'], true)) {
        $condition = '';
    }
    $batteryRaw = trim((string)($query['min_battery'] ?? ''));
    $battery = in_array($batteryRaw, ['80', '85', '90', '95'], true) ? (int)$batteryRaw : 0;
    if (!$facets['battery']) {
        $battery = 0;
    }
    $storage = phoneCatalogNormalizeStorage((string)($query['storage'] ?? ''));
    if ($storage === '' || !in_array($storage, $facets['storages'], true)) {
        $storage = '';
    }
    $sort = trim((string)($query['sort'] ?? 'ads'));
    if (!in_array($sort, ['ads', 'price_asc', 'name'], true)) {
        $sort = 'ads';
    }
    $q = trim((string)preg_replace('/[^\p{L}\p{N} .+\/-]/u', '', (string)($query['q'] ?? '')) ?? '');
    $q = trim(mb_substr($q, 0, 80));
    return [
        'q' => $q,
        'brands' => $brands,
        'price_band' => $band,
        'condition' => $condition,
        'min_battery' => $battery,
        'storage' => $storage,
        'with_ads' => (string)($query['with_ads'] ?? '') === '1',
        'sort' => $sort,
        'facets' => $facets,
    ];
}

/** @param array<string,list<array<string,mixed>>> $offersByModel
 *  @return array{brands:list<string>,conditions:list<string>,storages:list<string>,battery:bool,price:bool}
 */
function phoneCatalogIndexFacets(array $offersByModel): array
{
    $brands = [];
    $conditions = [];
    $storages = [];
    $battery = false;
    $price = false;
    foreach (phoneCatalogModels() as $model) {
        if (!in_array($model['brand'], $brands, true)) {
            $brands[] = $model['brand'];
        }
        foreach ($model['variants'] as $variant) {
            if (!in_array($variant['storage'], $storages, true)) {
                $storages[] = $variant['storage'];
            }
        }
        foreach ($offersByModel[$model['id']] ?? [] as $ad) {
            $condition = trim((string)($ad['condition_state'] ?? ''));
            if ($condition !== '' && !in_array($condition, $conditions, true)) {
                $conditions[] = $condition;
            }
            $storage = phoneCatalogAdStorage($ad);
            if ($storage !== '' && !in_array($storage, $storages, true)) {
                $storages[] = $storage;
            }
            if (phoneCatalogBattery($ad) !== null) {
                $battery = true;
            }
            if (function_exists('isAdPriceOpen') && !isAdPriceOpen($ad)) {
                $price = true;
            }
        }
    }
    $rank = ['64GB' => 64, '128GB' => 128, '256GB' => 256, '512GB' => 512, '1TB' => 1024, '32GB' => 32];
    usort($storages, static function (string $a, string $b) use ($rank): int {
        return ($rank[$a] ?? 9999) <=> ($rank[$b] ?? 9999);
    });
    sort($conditions, SORT_STRING);
    return [
        'brands' => $brands,
        'conditions' => $conditions,
        'storages' => $storages,
        'battery' => $battery,
        'price' => $price,
    ];
}

function phoneCatalogPriceInBand(float $eur, string $band): bool
{
    return match ($band) {
        'lt100' => $eur < 100,
        '100-300' => $eur >= 100 && $eur <= 300,
        '300-600' => $eur >= 300 && $eur <= 600,
        'gt600' => $eur >= 600,
        default => true,
    };
}

/** @param array<string,mixed> $filters
 *  @param array<string,list<array<string,mixed>>> $offersByModel
 *  @return list<array<string,mixed>>
 */
function phoneCatalogFilterModels(array $filters, array $offersByModel): array
{
    $q = phoneCatalogFold((string)($filters['q'] ?? ''));
    $out = [];
    foreach (phoneCatalogModels() as $model) {
        $offers = $offersByModel[$model['id']] ?? [];
        $stats = phoneCatalogPriceStats($offers);
        if (!empty($filters['with_ads']) && $stats['active'] < 1) {
            continue;
        }
        $brands = $filters['brands'] ?? [];
        if (is_array($brands) && $brands !== [] && !in_array($model['brand'], $brands, true)) {
            continue;
        }
        if ($q !== '') {
            $blob = phoneCatalogFold($model['brand'] . ' ' . $model['name'] . ' ' . $model['official_name']);
            if (!str_contains($blob, $q)) {
                continue;
            }
        }
        $band = (string)($filters['price_band'] ?? '');
        if ($band !== '') {
            $hit = false;
            foreach ($stats['prices'] as $eur) {
                if (phoneCatalogPriceInBand((float)$eur, $band)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
        }
        $condition = (string)($filters['condition'] ?? '');
        if ($condition !== '') {
            $hit = false;
            foreach ($offers as $ad) {
                if ((string)($ad['condition_state'] ?? '') === $condition) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
        }
        $minBattery = (int)($filters['min_battery'] ?? 0);
        if ($minBattery > 0) {
            $hit = false;
            foreach ($offers as $ad) {
                $battery = phoneCatalogBattery($ad);
                if ($battery !== null && $battery >= $minBattery) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
        }
        $storage = (string)($filters['storage'] ?? '');
        if ($storage !== '') {
            $hit = false;
            foreach ($model['variants'] as $variant) {
                if ($variant['storage'] === $storage) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                foreach ($offers as $ad) {
                    if (phoneCatalogAdStorage($ad) === $storage) {
                        $hit = true;
                        break;
                    }
                }
            }
            if (!$hit) {
                continue;
            }
        }
        $model['_offers'] = $offers;
        $model['_stats'] = $stats;
        $out[] = $model;
    }

    $sort = (string)($filters['sort'] ?? 'ads');
    usort($out, static function (array $a, array $b) use ($sort): int {
        if ($sort === 'name') {
            return strcmp($a['brand'] . ' ' . $a['name'], $b['brand'] . ' ' . $b['name']);
        }
        if ($sort === 'price_asc') {
            $ap = $a['_stats']['min'];
            $bp = $b['_stats']['min'];
            if ($ap === null && $bp !== null) {
                return 1;
            }
            if ($ap !== null && $bp === null) {
                return -1;
            }
            if ($ap !== null && $bp !== null && $ap !== $bp) {
                return $ap <=> $bp;
            }
        }
        $count = ($b['_stats']['active'] <=> $a['_stats']['active']);
        if ($count !== 0) {
            return $count;
        }
        return strcmp($a['brand'] . ' ' . $a['name'], $b['brand'] . ' ' . $b['name']);
    });
    return $out;
}

function phoneCatalogIndexFiltersActive(array $filters): bool
{
    return trim((string)($filters['q'] ?? '')) !== ''
        || ($filters['brands'] ?? []) !== []
        || (string)($filters['price_band'] ?? '') !== ''
        || (string)($filters['condition'] ?? '') !== ''
        || (int)($filters['min_battery'] ?? 0) > 0
        || (string)($filters['storage'] ?? '') !== ''
        || !empty($filters['with_ads'])
        || (string)($filters['sort'] ?? 'ads') !== 'ads';
}

/** @param list<array<string,mixed>> $offers
 *  @return array<string,mixed>
 */
function phoneCatalogModelFacets(array $offers): array
{
    $storage = [];
    $condition = [];
    $cities = [];
    $sim = [];
    $battery = false;
    $warranty = false;
    foreach ($offers as $ad) {
        $value = phoneCatalogAdStorage($ad);
        if ($value !== '' && !isset($storage[$value])) {
            $storage[$value] = true;
        }
        $state = trim((string)($ad['condition_state'] ?? ''));
        if ($state !== '' && !isset($condition[$state])) {
            $condition[$state] = true;
        }
        $city = trim((string)($ad['location'] ?? ''));
        if ($city !== '' && !isset($cities[$city])) {
            $cities[$city] = true;
        }
        $simStatus = trim((string)($ad['sim_status'] ?? ''));
        if ($simStatus !== '' && !isset($sim[$simStatus])) {
            $sim[$simStatus] = true;
        }
        if (phoneCatalogBattery($ad) !== null) {
            $battery = true;
        }
        if (phoneCatalogWarrantyKnown($ad) !== null) {
            $warranty = true;
        }
    }
    return [
        'storages' => array_keys($storage),
        'conditions' => array_keys($condition),
        'cities' => array_keys($cities),
        'sims' => array_keys($sim),
        'battery' => $battery,
        'warranty' => $warranty,
    ];
}

/** @param list<array<string,mixed>> $offers
 *  @return list<array<string,mixed>>
 */
function phoneCatalogFilterOffers(array $offers, array $query, array $facets): array
{
    $storage = phoneCatalogNormalizeStorage((string)($query['storage'] ?? ''));
    if ($storage !== '' && !in_array($storage, $facets['storages'], true)) {
        $storage = '';
    }
    $condition = trim((string)($query['condition'] ?? ''));
    if (!in_array($condition, $facets['conditions'], true)) {
        $condition = '';
    }
    $city = trim((string)($query['grad'] ?? ''));
    if (!in_array($city, $facets['cities'], true)) {
        $city = '';
    }
    $sim = trim((string)($query['sim'] ?? ''));
    if (!in_array($sim, $facets['sims'], true)) {
        $sim = '';
    }
    $battery = in_array((string)($query['min_battery'] ?? ''), ['80', '85', '90', '95'], true) ? (int)$query['min_battery'] : 0;
    if (!$facets['battery']) {
        $battery = 0;
    }
    $warranty = trim((string)($query['garancija'] ?? ''));
    if (!$facets['warranty'] || !in_array($warranty, ['1', '0'], true)) {
        $warranty = '';
    }
    $filtered = [];
    foreach ($offers as $ad) {
        if ($storage !== '' && phoneCatalogAdStorage($ad) !== $storage) {
            continue;
        }
        if ($condition !== '' && (string)($ad['condition_state'] ?? '') !== $condition) {
            continue;
        }
        if ($city !== '' && trim((string)($ad['location'] ?? '')) !== $city) {
            continue;
        }
        if ($sim !== '' && trim((string)($ad['sim_status'] ?? '')) !== $sim) {
            continue;
        }
        if ($battery > 0) {
            $value = phoneCatalogBattery($ad);
            if ($value === null || $value < $battery) {
                continue;
            }
        }
        if ($warranty !== '') {
            $known = phoneCatalogWarrantyKnown($ad);
            if ($known === null || ($warranty === '1') !== $known) {
                continue;
            }
        }
        $filtered[] = $ad;
    }
    return $filtered;
}

function phoneCatalogOfferFiltersActive(array $query, array $facets): bool
{
    $storage = phoneCatalogNormalizeStorage((string)($query['storage'] ?? ''));
    $condition = trim((string)($query['condition'] ?? ''));
    $city = trim((string)($query['grad'] ?? ''));
    $sim = trim((string)($query['sim'] ?? ''));
    $battery = (int)($query['min_battery'] ?? 0);
    $warranty = trim((string)($query['garancija'] ?? ''));
    return ($storage !== '' && in_array($storage, $facets['storages'], true))
        || ($condition !== '' && in_array($condition, $facets['conditions'], true))
        || ($city !== '' && in_array($city, $facets['cities'], true))
        || ($sim !== '' && in_array($sim, $facets['sims'], true))
        || ($battery > 0 && !empty($facets['battery']))
        || (($warranty === '1' || $warranty === '0') && !empty($facets['warranty']));
}

/** @return array<string,mixed> */
function phoneCatalogPublicOffer(array $ad): array
{
    $open = function_exists('isAdPriceOpen') ? isAdPriceOpen($ad) : ((float)($ad['price'] ?? 0) <= 0);
    $battery = phoneCatalogBattery($ad);
    $warranty = phoneCatalogWarrantyKnown($ad);
    $image = function_exists('adPrimaryListingThumb') ? (string)adPrimaryListingThumb($ad) : '';
    return [
        'id' => (int)($ad['id'] ?? 0),
        'title' => function_exists('adDisplayTitle') ? adDisplayTitle($ad) : trim((string)($ad['title'] ?? '')),
        'href' => function_exists('adUrl') ? adUrl($ad) : '/oglas/' . (int)($ad['id'] ?? 0),
        'image' => $image,
        'price_label' => $open ? 'Po dogovoru' : (function_exists('formatAdPrice') ? formatAdPrice($ad) : phoneCatalogFormatEur((float)($ad['price'] ?? 0))),
        'price_open' => $open,
        'condition' => trim((string)($ad['condition_state'] ?? '')),
        'battery' => $battery,
        'warranty' => $warranty,
        'city' => trim((string)($ad['location'] ?? '')),
        'storage' => phoneCatalogAdStorage($ad),
        'sim' => trim((string)($ad['sim_status'] ?? '')),
        'shop' => trim((string)($ad['shop_name'] ?? '')),
    ];
}

function phoneCatalogIndexMeta(): array
{
    $name = function_exists('seoSiteName') ? seoSiteName() : 'KupiTelefon';
    $count = count(phoneCatalogModels());
    return [
        'title' => 'Katalog telefona — ' . $name,
        'description' => 'Katalog od ' . $count . ' modela telefona na ' . $name . '. Broj oglasa i raspon cena računaju se iz aktivnih oglasa. Specifikacije su unete samo kada postoji proveren izvor.',
    ];
}

function phoneCatalogModelMeta(array $model, array $stats): array
{
    $name = function_exists('seoSiteName') ? seoSiteName() : 'KupiTelefon';
    $label = $model['brand'] . ' ' . $model['name'];
    $activeLabel = ((int)($stats['active'] ?? 0) === 1)
        ? '1 aktivan oglas'
        : ((int)($stats['active'] ?? 0) . ' aktivnih oglasa');
    if (($stats['active'] ?? 0) < 1) {
        $description = $label . ' u katalogu ' . $name . '. Trenutno nema aktivnih oglasa, pa cena nije prikazana.';
    } elseif (($stats['fixed'] ?? 0) > 0) {
        $description = $label . ': ' . $activeLabel . ' na ' . $name . ', fiksne cene od '
            . phoneCatalogFormatEur((float)$stats['min']) . ' do ' . phoneCatalogFormatEur((float)$stats['max']) . '.';
    } else {
        $description = $label . ': ' . $activeLabel . ' na ' . $name . '. Nema fiksne cene, oglasi su po dogovoru.';
    }
    if ($model['specs'] === []) {
        $description .= ' Specifikacije za ovaj model nisu unete.';
    } else {
        $description .= ' Specifikacije su iz navedenog proizvođačkog izvora.';
    }
    return [
        'title' => $label . ' — katalog telefona — ' . $name,
        'description' => function_exists('seoTruncate') ? seoTruncate($description, 180) : $description,
    ];
}

/** @return list<array<string,mixed>> */
function phoneCatalogSourcesForModel(array $model): array
{
    $bundle = phoneCatalogBundle();
    $out = [];
    foreach ($model['source_ids'] as $sourceId) {
        if (isset($bundle['sources'][$sourceId])) {
            $out[] = $bundle['sources'][$sourceId];
        }
    }
    return $out;
}

/**
 * Učitaj sirovi katalog iz skladišta ili seeda.
 *
 * @return array<string,mixed>
 */
function phoneCatalogLoadRawDocument(): array
{
    if (function_exists('readJsonFile')) {
        $stored = readJsonFile(phoneCatalogStoreFilename());
        if (is_array($stored) && isset($stored['models']) && is_array($stored['models']) && $stored['models'] !== []) {
            return $stored;
        }
    }
    $decoded = json_decode((string)file_get_contents(phoneCatalogSeedPath()), true);
    return is_array($decoded) ? $decoded : ['version' => 1, 'coverage_note' => '', 'sources' => [], 'models' => []];
}

/**
 * @param array<string,mixed> $bundle normalizovan bundle (ok=true)
 * @return array<string,mixed>
 */
function phoneCatalogExportDocument(array $bundle): array
{
    return [
        'version' => 1,
        'coverage_note' => (string)($bundle['coverage_note'] ?? ''),
        'sources' => array_values($bundle['sources'] ?? []),
        'models' => array_map(static function (array $model): array {
            return [
                'id' => $model['id'],
                'brand' => $model['brand'],
                'brand_slug' => $model['brand_slug'],
                'name' => $model['name'],
                'official_name' => $model['official_name'],
                'slug' => $model['slug'],
                'source_ids' => $model['source_ids'],
                'aliases' => array_map(
                    static fn(array $tokens): string => implode(' ', $tokens),
                    $model['aliases']
                ),
                'colors' => $model['colors'],
                'variants' => array_values($model['variants']),
                'highlights' => $model['highlights'],
                'specs' => $model['specs'],
                'image' => null,
            ];
        }, array_values($bundle['models'] ?? [])),
    ];
}

/** @param array<string,mixed> $bundle normalizovan bundle (ok=true) */
function phoneCatalogWriteBundle(array $bundle): void
{
    if (empty($bundle['ok'])) {
        throw new InvalidArgumentException('Neispravan katalog bundle.');
    }
    writeJsonFile(phoneCatalogStoreFilename(), phoneCatalogExportDocument($bundle));
    phoneCatalogResetCache();
}
