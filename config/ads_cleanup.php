<?php

declare(strict_types=1);

/**
 * Admin čišćenje: duplikati istog korisnika + pogrešan tip/kategorija.
 */

function cleanupNormalizeTitle(string $title): string
{
    $t = mb_strtolower(trim($title));
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return trim($t);
}

function cleanupAdSortKey(array $ad): array
{
    return [
        (int)($ad['is_active'] ?? 0) === 1 ? 1 : 0,
        (int)($ad['id'] ?? 0),
        strtotime((string)($ad['created_at'] ?? '')) ?: 0,
    ];
}

/**
 * Uporedi dva oglasa: bolji (keep) je veći.
 */
function cleanupPreferAd(array $a, array $b): array
{
    $ka = cleanupAdSortKey($a);
    $kb = cleanupAdSortKey($b);
    if ($ka[0] !== $kb[0]) {
        return $ka[0] > $kb[0] ? $a : $b;
    }
    if ($ka[1] !== $kb[1]) {
        return $ka[1] > $kb[1] ? $a : $b;
    }
    return $ka[2] >= $kb[2] ? $a : $b;
}

function cleanupSourceId(array $ad): string
{
    $sid = trim((string)($ad['kp_source_id'] ?? ''));
    if ($sid !== '') {
        return $sid;
    }
    $url = trim((string)($ad['kp_source_url'] ?? ''));
    if ($url !== '' && preg_match('#/oglas/(\d+)#i', $url, $m)) {
        return (string)$m[1];
    }
    return '';
}

/**
 * @return list<array{
 *   key: string,
 *   reason: string,
 *   user_id: int,
 *   keep: array<string,mixed>,
 *   remove: list<array<string,mixed>>
 * }>
 */
function findDuplicateAdGroups(?array $ads = null): array
{
    $ads = $ads ?? readJsonFile('ads.json');
    if (!is_array($ads)) {
        return [];
    }

    /** @var array<string, list<array<string,mixed>>> $bySource */
    $bySource = [];
    /** @var array<string, list<array<string,mixed>>> $byTitle */
    $byTitle = [];

    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $userId = (int)($ad['created_by'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $sid = cleanupSourceId($ad);
        if ($sid !== '') {
            $key = 'src:' . $userId . ':' . $sid;
            $bySource[$key][] = $ad;
            continue;
        }
        $norm = cleanupNormalizeTitle((string)($ad['title'] ?? ''));
        if ($norm === '' || mb_strlen($norm) < 8) {
            continue;
        }
        $key = 'title:' . $userId . ':' . $norm;
        $byTitle[$key][] = $ad;
    }

    $groups = [];
    foreach ($bySource as $key => $list) {
        if (count($list) < 2) {
            continue;
        }
        $groups[] = cleanupBuildDupGroup($key, 'isti KP source_id', $list);
    }
    foreach ($byTitle as $key => $list) {
        if (count($list) < 2) {
            continue;
        }
        // Ako su već pokriveni preko source_id, ne dupliraj istu grupu po ID-jevima
        $ids = [];
        foreach ($list as $ad) {
            $ids[(int)($ad['id'] ?? 0)] = true;
        }
        $already = false;
        foreach ($groups as $g) {
            $gIds = [(int)($g['keep']['id'] ?? 0) => true];
            foreach ($g['remove'] as $r) {
                $gIds[(int)($r['id'] ?? 0)] = true;
            }
            $overlap = count(array_intersect_key($ids, $gIds));
            if ($overlap >= 2) {
                $already = true;
                break;
            }
        }
        if ($already) {
            continue;
        }
        $groups[] = cleanupBuildDupGroup($key, 'isti naslov', $list);
    }

    usort($groups, static function ($a, $b) {
        return ((int)($b['keep']['created_by'] ?? 0)) <=> ((int)($a['keep']['created_by'] ?? 0))
            ?: count($b['remove']) <=> count($a['remove']);
    });

    return $groups;
}

/**
 * @param list<array<string,mixed>> $list
 * @return array{key:string,reason:string,user_id:int,keep:array<string,mixed>,remove:list<array<string,mixed>>}
 */
function cleanupBuildDupGroup(string $key, string $reason, array $list): array
{
    $keep = $list[0];
    foreach ($list as $ad) {
        $keep = cleanupPreferAd($keep, $ad);
    }
    $keepId = (int)($keep['id'] ?? 0);
    $remove = [];
    foreach ($list as $ad) {
        if ((int)($ad['id'] ?? 0) === $keepId) {
            continue;
        }
        $remove[] = $ad;
    }
    return [
        'key' => $key,
        'reason' => $reason,
        'user_id' => (int)($keep['created_by'] ?? 0),
        'keep' => $keep,
        'remove' => $remove,
    ];
}

/**
 * @return list<int>
 */
function cleanupDuplicateRemoveIds(array $groups): array
{
    $ids = [];
    foreach ($groups as $g) {
        foreach ($g['remove'] ?? [] as $ad) {
            $id = (int)($ad['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
    }
    return array_map('intval', array_keys($ids));
}

/**
 * @return list<array{
 *   ad: array<string,mixed>,
 *   current_ad_type: string,
 *   current_category_group: string,
 *   suggested_ad_type: string,
 *   suggested_category_group: string,
 *   reason: string
 * }>
 */
function findMiscategorizedAds(?array $ads = null): array
{
    $ads = $ads ?? readJsonFile('ads.json');
    if (!is_array($ads)) {
        return [];
    }

    $out = [];
    foreach ($ads as $ad) {
        if (!is_array($ad)) {
            continue;
        }
        $title = trim((string)($ad['title'] ?? ''));
        $desc = trim((string)($ad['description'] ?? ''));
        if ($title === '') {
            continue;
        }
        $blob = $title . ' ' . $desc;
        $guess = kpGuessCategoryGroup($blob);
        $curType = trim((string)($ad['ad_type'] ?? 'telefon'));
        $curGroup = trim((string)($ad['category_group'] ?? ''));
        $sugType = (string)($guess['ad_type'] ?? 'telefon');
        $sugGroup = (string)($guess['category_group'] ?? 'phones');

        if ($curType === $sugType && ($curGroup === '' || $curGroup === $sugGroup)) {
            continue;
        }
        // Ako je tip isti a group prazan za telefon/servis — nije greška
        if ($curType === $sugType && $curGroup === '' && in_array($sugType, ['telefon', 'servis'], true)) {
            continue;
        }

        $reason = $curType !== $sugType
            ? ('tip ' . $curType . ' → ' . $sugType)
            : ('grupa ' . ($curGroup !== '' ? $curGroup : '—') . ' → ' . $sugGroup);

        $out[] = [
            'ad' => $ad,
            'current_ad_type' => $curType,
            'current_category_group' => $curGroup,
            'suggested_ad_type' => $sugType,
            'suggested_category_group' => $sugGroup,
            'reason' => $reason,
        ];
    }

    return $out;
}

/**
 * @param list<int> $adIds
 * @param list<int> $allowedIds
 * @return array{deleted:int, skipped:int, errors:list<string>}
 */
function cleanupDeleteDuplicateAds(array $adIds, array $allowedIds): array
{
    $allowed = array_fill_keys(array_map('intval', $allowedIds), true);
    $deleted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($adIds as $id) {
        $id = (int)$id;
        if ($id <= 0 || !isset($allowed[$id])) {
            $skipped++;
            continue;
        }
        if (deleteAdById($id)) {
            $deleted++;
        } else {
            $errors[] = 'Nije obrisan oglas #' . $id;
        }
    }

    return ['deleted' => $deleted, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * @param array<int, array{ad_type?:string, category_group?:string}> $fixes keyed by ad id
 * @param array<int, array{suggested_ad_type:string, suggested_category_group:string}> $allowed keyed by ad id
 * @return array{fixed:int, skipped:int, errors:list<string>}
 */
function applyCategoryFixes(array $fixes, array $allowed): array
{
    $ads = readJsonFile('ads.json');
    $fixed = 0;
    $skipped = 0;
    $errors = [];
    $changed = false;

    foreach ($ads as &$ad) {
        if (!is_array($ad)) {
            continue;
        }
        $id = (int)($ad['id'] ?? 0);
        if ($id <= 0 || !isset($fixes[$id])) {
            continue;
        }
        if (!isset($allowed[$id])) {
            $skipped++;
            continue;
        }

        $sugType = trim((string)($fixes[$id]['ad_type'] ?? $allowed[$id]['suggested_ad_type'] ?? ''));
        $sugGroup = trim((string)($fixes[$id]['category_group'] ?? $allowed[$id]['suggested_category_group'] ?? ''));
        if ($sugType === '' || !in_array($sugType, ['telefon', 'delovi', 'servis'], true)) {
            $skipped++;
            continue;
        }
        if ($sugType === 'telefon') {
            $sugGroup = 'phones';
        } elseif ($sugType === 'servis') {
            $sugGroup = 'service';
        } elseif ($sugGroup === '' || !isset(categoriesConfig()['groups'][$sugGroup])) {
            $sugGroup = 'other_parts';
        }

        $ad['ad_type'] = $sugType;
        $ad['category_group'] = $sugGroup;
        $ad['category'] = categoryFromAdType($sugType);
        if ($sugType === 'telefon' && empty($ad['device_type'])) {
            $ad['device_type'] = kpGuessDeviceType(
                trim((string)($ad['title'] ?? '')) . ' ' . trim((string)($ad['description'] ?? ''))
            );
        }
        if ($sugType === 'delovi' && empty($ad['equipment_type'])) {
            $ad['equipment_type'] = kpGuessEquipmentType(
                trim((string)($ad['title'] ?? '')) . ' ' . trim((string)($ad['description'] ?? '')),
                $sugGroup
            );
        }
        if ($sugType === 'servis') {
            $ad['brand'] = '';
        }
        $ad['updated_at'] = date('Y-m-d H:i:s');
        $ad = normalizeAdDefaults($ad);
        $fixed++;
        $changed = true;
    }
    unset($ad);

    if ($changed) {
        writeJsonFile('ads.json', $ads);
    }

    return ['fixed' => $fixed, 'skipped' => $skipped, 'errors' => $errors];
}

function cleanupUsername(int $userId): string
{
    if ($userId <= 0) {
        return '—';
    }
    $u = findUserById($userId);
    if (!$u) {
        return '#' . $userId;
    }
    $name = trim((string)($u['username'] ?? ''));
    return $name !== '' ? $name : ('#' . $userId);
}
