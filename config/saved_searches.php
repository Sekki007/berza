<?php

declare(strict_types=1);

function ensureSavedSearchesFile(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('saved_searches.json'))) {
        writeJsonFile('saved_searches.json', []);
    }
}

function getAllSavedSearches(): array
{
    ensureSavedSearchesFile();
    return readJsonFile('saved_searches.json');
}

function getSavedSearchesForUser(int $userId): array
{
    $items = array_values(array_filter(
        getAllSavedSearches(),
        static fn($s) => (int)($s['user_id'] ?? 0) === $userId
    ));
    usort($items, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $items;
}

function findSavedSearch(int $id): ?array
{
    foreach (getAllSavedSearches() as $item) {
        if ((int)($item['id'] ?? 0) === $id) {
            return $item;
        }
    }
    return null;
}

function savedSearchFiltersFromInput(array $input): array
{
    $deviceType = trim((string)($input['device_type'] ?? ''));
    if ($deviceType !== '' && !in_array($deviceType, allowedDeviceTypes(), true)) {
        $deviceType = '';
    }
    $filters = [
        'q' => trim((string)($input['q'] ?? '')),
        'brand' => trim((string)($input['brand'] ?? '')),
        'model' => trim((string)($input['model'] ?? '')),
        'location' => trim((string)($input['location'] ?? '')),
        'condition' => trim((string)($input['condition'] ?? '')),
        'type' => trim((string)($input['type'] ?? '')),
        'listing_type' => trim((string)($input['listing_type'] ?? '')),
        'device_type' => $deviceType,
        'min_price' => trim((string)($input['min_price'] ?? '')),
        'max_price' => trim((string)($input['max_price'] ?? '')),
        'category_group' => trim((string)($input['category_group'] ?? '')),
        'equipment_group' => trim((string)($input['equipment_group'] ?? '')),
    ];
    if (!in_array($filters['type'], ['telefon', 'delovi', 'servis', ''], true)) {
        $filters['type'] = '';
    }
    if (!in_array($filters['listing_type'], ['sell', 'buy', 'trade', ''], true)) {
        $filters['listing_type'] = '';
    }
    return array_filter($filters, static fn($v) => $v !== '');
}

function savedSearchToPublicFilters(array $search): array
{
    $f = $search['filters'] ?? [];
    if (!is_array($f)) {
        $f = [];
    }
    $type = trim((string)($f['type'] ?? ''));
    $listingType = trim((string)($f['listing_type'] ?? ''));
    if (!in_array($listingType, ['sell', 'buy', 'trade'], true)) {
        $listingType = '';
    }
    $deviceType = trim((string)($f['device_type'] ?? ''));
    if ($deviceType !== '' && !in_array($deviceType, allowedDeviceTypes(), true)) {
        $deviceType = '';
    }
    return [
        'q' => trim((string)($f['q'] ?? '')),
        'brand' => trim((string)($f['brand'] ?? '')),
        'model' => trim((string)($f['model'] ?? '')),
        'location' => trim((string)($f['location'] ?? '')),
        'condition' => trim((string)($f['condition'] ?? '')),
        'category_group' => trim((string)($f['category_group'] ?? '')),
        'equipment_group' => trim((string)($f['equipment_group'] ?? '')),
        'min_price' => trim((string)($f['min_price'] ?? '')),
        'max_price' => trim((string)($f['max_price'] ?? '')),
        'device_type' => $deviceType,
        'listing_type' => $listingType,
        'types' => $type !== '' ? [$type] : [],
        'sort' => 'newest',
    ];
}

function savedSearchLabel(array $search): string
{
    $name = trim((string)($search['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $f = $search['filters'] ?? [];
    $parts = [];
    foreach (['q', 'brand', 'model', 'location', 'type', 'listing_type', 'condition'] as $key) {
        $v = trim((string)($f[$key] ?? ''));
        if ($v !== '') {
            if ($key === 'listing_type') {
                $v = match ($v) {
                    'sell' => 'Prodajem',
                    'buy' => 'Tražim',
                    'trade' => 'Zamena',
                    default => $v,
                };
            }
            $parts[] = $v;
        }
    }
    $dt = trim((string)($f['device_type'] ?? ''));
    if ($dt !== '' && in_array($dt, allowedDeviceTypes(), true)) {
        $parts[] = deviceTypeLabel($dt);
    }
    $min = trim((string)($f['min_price'] ?? ''));
    $max = trim((string)($f['max_price'] ?? ''));
    if ($min !== '' || $max !== '') {
        $parts[] = trim($min . '–' . $max . ' €', '– ');
    }
    return $parts !== [] ? implode(' · ', $parts) : 'Sačuvana pretraga';
}

function savedSearchMatchedIds(array $search): array
{
    $matched = getPublicAds(savedSearchToPublicFilters($search));
    return array_values(array_map(static fn($a) => (int)($a['id'] ?? 0), $matched));
}

function savedSearchAlertLink(array $search, array $newIds): string
{
    if (count($newIds) === 1) {
        $ad = getAdById((int)$newIds[0]);
        if ($ad && (int)($ad['is_active'] ?? 0) === 1) {
            return adUrl($ad);
        }
    }
    $query = buildFilterQuery($search['filters'] ?? []);
    return '/index.php' . ($query !== '' ? '?' . $query : '');
}

function createSavedSearch(int $userId, array $filters, string $name = '', bool $alertEnabled = true): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $filters = savedSearchFiltersFromInput($filters);
    if ($filters === []) {
        return null;
    }
    $existing = getSavedSearchesForUser($userId);
    if (count($existing) >= 20) {
        return null;
    }

    $items = getAllSavedSearches();
    $maxId = 0;
    foreach ($items as $item) {
        $maxId = max($maxId, (int)($item['id'] ?? 0));
    }

    $matchedIds = savedSearchMatchedIds(['filters' => $filters]);
    $lastSeen = $matchedIds !== [] ? max($matchedIds) : 0;

    $row = [
        'id' => $maxId + 1,
        'user_id' => $userId,
        'name' => trim($name),
        'filters' => $filters,
        'alert_enabled' => $alertEnabled,
        'last_match_ids' => array_slice($matchedIds, 0, 150),
        'last_seen_ad_id' => $lastSeen,
        'last_checked_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $items[] = $row;
    writeJsonFile('saved_searches.json', $items);
    return $row;
}

function updateSavedSearch(int $id, int $userId, array $patch): bool
{
    $items = getAllSavedSearches();
    foreach ($items as &$item) {
        if ((int)($item['id'] ?? 0) !== $id || (int)($item['user_id'] ?? 0) !== $userId) {
            continue;
        }
        if (array_key_exists('name', $patch)) {
            $item['name'] = trim((string)$patch['name']);
        }
        if (array_key_exists('alert_enabled', $patch)) {
            $item['alert_enabled'] = !empty($patch['alert_enabled']);
        }
        if (array_key_exists('filters', $patch) && is_array($patch['filters'])) {
            $item['filters'] = savedSearchFiltersFromInput($patch['filters']);
        }
        writeJsonFile('saved_searches.json', $items);
        return true;
    }
    return false;
}

function deleteSavedSearch(int $id, int $userId): bool
{
    $items = getAllSavedSearches();
    $before = count($items);
    $items = array_values(array_filter(
        $items,
        static fn($s) => !((int)($s['id'] ?? 0) === $id && (int)($s['user_id'] ?? 0) === $userId)
    ));
    if (count($items) === $before) {
        return false;
    }
    writeJsonFile('saved_searches.json', $items);
    return true;
}

function processSavedSearchAlerts(bool $force = false, ?array $targetAd = null): array
{
    $result = ['checked' => 0, 'notified' => 0, 'skipped' => false];
    $statePath = 'saved_search_state.json';
    $state = readJsonFile($statePath);
    $lastRun = strtotime((string)($state['last_run'] ?? '')) ?: 0;
    if ($targetAd === null && !$force && $lastRun > 0 && (time() - $lastRun) < 900) {
        $result['skipped'] = true;
        return $result;
    }

    $targetId = (int)($targetAd['id'] ?? 0);
    $items = getAllSavedSearches();
    $changed = false;

    foreach ($items as &$search) {
        if (empty($search['alert_enabled'])) {
            continue;
        }
        $userId = (int)($search['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $result['checked']++;

        $currentIds = savedSearchMatchedIds($search);
        $currentMax = $currentIds !== [] ? max($currentIds) : 0;
        $prevSeen = (int)($search['last_seen_ad_id'] ?? 0);
        $hasBaseline = $prevSeen > 0;
        if (!$hasBaseline) {
            $legacyIds = array_map('intval', (array)($search['last_match_ids'] ?? []));
            if ($legacyIds !== []) {
                $prevSeen = max($legacyIds);
                $hasBaseline = true;
            } else {
                $prevSeen = 0;
            }
        }

        $newIds = [];
        if (!$hasBaseline) {
            // Prva provera bez zabeleženog stanja: samo postavi početnu tačku, bez lažnog alerta.
        } elseif ($targetId > 0) {
            if ($targetId > $prevSeen && in_array($targetId, $currentIds, true)) {
                $newIds = [$targetId];
            }
        } else {
            $newIds = array_values(array_filter($currentIds, static fn(int $id) => $id > $prevSeen));
        }

        if ($newIds !== []) {
            $label = savedSearchLabel($search);
            $count = count($newIds);
            $previewTitles = [];
            foreach ($newIds as $nid) {
                $adRow = getAdById($nid);
                if ($adRow) {
                    $previewTitles[] = (string)($adRow['title'] ?? 'Oglas');
                }
                if (count($previewTitles) >= 3) {
                    break;
                }
            }
            $preview = implode(', ', $previewTitles);
            notifyUser(
                $userId,
                'saved_search_match',
                'Nova ponuda za sačuvanu pretragu',
                ($count === 1 ? '1 novi oglas' : "{$count} novih oglasa") . " za „{$label}”" . ($preview !== '' ? ": {$preview}" : ''),
                savedSearchAlertLink($search, $newIds)
            );
            $result['notified']++;
        }

        $search['last_match_ids'] = array_slice($currentIds, 0, 150);
        $search['last_seen_ad_id'] = max($prevSeen, $currentMax, $targetId > 0 ? $targetId : 0);
        $search['last_checked_at'] = date('Y-m-d H:i:s');
        $changed = true;
    }
    unset($search);

    if ($changed) {
        writeJsonFile('saved_searches.json', $items);
    }
    writeJsonFile($statePath, ['last_run' => date('Y-m-d H:i:s'), 'last_result' => $result]);
    return $result;
}

function notifySavedSearchesForAd(int $adId): array
{
    $ad = getAdById($adId);
    if (!$ad || (int)($ad['is_active'] ?? 0) !== 1) {
        return ['checked' => 0, 'notified' => 0, 'skipped' => true];
    }
    return processSavedSearchAlerts(true, $ad);
}
