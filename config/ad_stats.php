<?php

declare(strict_types=1);

function ensureAdStatsFile(): void
{
    if (function_exists('usesMySqlStorage') && usesMySqlStorage()) {
        return;
    }
    if (!file_exists(dataPath('ad_stats.json'))) {
        writeJsonFile('ad_stats.json', []);
    }
}

function getAllAdStats(): array
{
    ensureAdStatsFile();
    return readJsonFile('ad_stats.json');
}

function getAdStats(int $adId): array
{
    $all = getAllAdStats();
    $key = (string)$adId;
    $row = $all[$key] ?? [];
    return [
        'ad_id' => $adId,
        'views' => (int)($row['views'] ?? 0),
        'phone_reveals' => (int)($row['phone_reveals'] ?? 0),
        'messages_started' => (int)($row['messages_started'] ?? 0),
        'daily' => is_array($row['daily'] ?? null) ? $row['daily'] : [],
    ];
}

function bumpAdStat(int $adId, string $field, int $by = 1): void
{
    if ($adId <= 0 || $by === 0) {
        return;
    }
    if (!in_array($field, ['views', 'phone_reveals', 'messages_started'], true)) {
        return;
    }
    ensureAdStatsFile();
    $all = getAllAdStats();
    $key = (string)$adId;
    if (!isset($all[$key]) || !is_array($all[$key])) {
        $all[$key] = [
            'ad_id' => $adId,
            'views' => 0,
            'phone_reveals' => 0,
            'messages_started' => 0,
            'daily' => [],
        ];
    }
    $all[$key][$field] = (int)($all[$key][$field] ?? 0) + $by;
    $day = date('Y-m-d');
    if (!isset($all[$key]['daily'][$day]) || !is_array($all[$key]['daily'][$day])) {
        $all[$key]['daily'][$day] = ['views' => 0, 'phone_reveals' => 0, 'messages_started' => 0];
    }
    $all[$key]['daily'][$day][$field] = (int)($all[$key]['daily'][$day][$field] ?? 0) + $by;

    // Keep ~60 days of buckets
    $daily = $all[$key]['daily'];
    if (count($daily) > 60) {
        ksort($daily);
        $daily = array_slice($daily, -60, null, true);
        $all[$key]['daily'] = $daily;
    }

    writeJsonFile('ad_stats.json', $all);
}

function sumAdStatsForUser(int $userId, int $days = 30): array
{
    $ads = getAdsByUserId($userId);
    $totals = ['views' => 0, 'phone_reveals' => 0, 'messages_started' => 0];
    $perAd = [];
    $cutoff = date('Y-m-d', time() - max(1, $days) * 86400);

    foreach ($ads as $ad) {
        $adId = (int)($ad['id'] ?? 0);
        $stats = getAdStats($adId);
        $row = [
            'ad' => $ad,
            'views' => (int)($ad['views'] ?? $stats['views']),
            'phone_reveals' => $stats['phone_reveals'],
            'messages_started' => $stats['messages_started'],
            'period_views' => 0,
            'period_phone_reveals' => 0,
            'period_messages' => 0,
        ];
        foreach ($stats['daily'] as $day => $bucket) {
            if ($day < $cutoff) {
                continue;
            }
            $row['period_views'] += (int)($bucket['views'] ?? 0);
            $row['period_phone_reveals'] += (int)($bucket['phone_reveals'] ?? 0);
            $row['period_messages'] += (int)($bucket['messages_started'] ?? 0);
        }
        $totals['views'] += $row['views'];
        $totals['phone_reveals'] += $row['phone_reveals'];
        $totals['messages_started'] += $row['messages_started'];
        $perAd[] = $row;
    }

    usort($perAd, static fn($a, $b) => ($b['views'] <=> $a['views']));
    return ['totals' => $totals, 'ads' => $perAd, 'days' => $days];
}
