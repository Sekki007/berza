<?php

declare(strict_types=1);

/**
 * Događaji cena. JSON fajl je izvor dok Sead ne pokrene database/ad_price_events.sql.
 * PHP ne piše u produkcionu MySQL tabelu.
 */

function priceEventLog(): array
{
    $rows = readJsonFile('ad_price_events.json');
    return array_values(array_filter($rows, 'is_array'));
}

function recordAdPriceEvent(array $ad, string $event, ?float $price = null): void
{
    $allowed = ['created', 'price_change', 'sold', 'deactivated', 'reactivated'];
    if (!in_array($event, $allowed, true)) {
        return;
    }
    if (function_exists('isBuyListing') && isBuyListing($ad)) {
        return;
    }
    $amount = $price ?? (function_exists('adPriceEur') ? adPriceEur($ad) : (float)($ad['price'] ?? 0));
    $rows = priceEventLog();
    $rows[] = [
        'id' => count($rows) + 1,
        'ad_id' => (int)($ad['id'] ?? 0),
        'event' => $event,
        'price' => round($amount, 2),
        'currency' => 'EUR',
        'model_id' => trim((string)($ad['phone_model_id'] ?? '')),
        'storage' => trim((string)($ad['storage'] ?? '')),
        'condition_state' => trim((string)($ad['condition_state'] ?? '')),
        'city' => function_exists('normalizeCity') ? normalizeCity((string)($ad['location'] ?? ''))['city'] : trim((string)($ad['location'] ?? '')),
        'created_at' => date('Y-m-d H:i:s'),
        'estimated' => 0,
    ];
    writeJsonFile('ad_price_events.json', $rows);
}

/**
 * @param list<float> $prices
 * @return array{count:int,min:float,p25:float,median:float,p75:float,max:float}|null
 */
function priceStatsFromValues(array $prices): ?array
{
    $clean = [];
    foreach ($prices as $price) {
        $price = (float)$price;
        if ($price > 0) {
            $clean[] = $price;
        }
    }
    if (count($clean) < 3) {
        return null;
    }
    sort($clean);
    $median = pricePercentile($clean, 0.5);
    $kept = array_values(array_filter($clean, static fn(float $price): bool => $price >= $median * 0.2 && $price <= $median * 3));
    if (count($kept) < 3) {
        return null;
    }
    sort($kept);
    return [
        'count' => count($kept),
        'min' => $kept[0],
        'p25' => pricePercentile($kept, 0.25),
        'median' => pricePercentile($kept, 0.5),
        'p75' => pricePercentile($kept, 0.75),
        'max' => $kept[count($kept) - 1],
    ];
}

/** @param list<float> $sorted */
function pricePercentile(array $sorted, float $p): float
{
    $n = count($sorted);
    if ($n === 0) {
        return 0.0;
    }
    $index = ($n - 1) * $p;
    $low = (int)floor($index);
    $high = (int)ceil($index);
    if ($low === $high) {
        return $sorted[$low];
    }
    $weight = $index - $low;
    return $sorted[$low] * (1 - $weight) + $sorted[$high] * $weight;
}

function priceStats(?string $modelId, ?string $storage = null, ?string $condition = null, int $days = 90): ?array
{
    $since = time() - ($days * 86400);
    $prices = [];
    foreach (priceEventLog() as $row) {
        if ($modelId !== null && (string)($row['model_id'] ?? '') !== $modelId) {
            continue;
        }
        if ($storage !== null && $storage !== '' && (string)($row['storage'] ?? '') !== $storage) {
            continue;
        }
        if ($condition !== null && $condition !== '' && mb_strtolower((string)($row['condition_state'] ?? '')) !== mb_strtolower($condition)) {
            continue;
        }
        $ts = strtotime((string)($row['created_at'] ?? ''));
        if ($ts === false || $ts < $since) {
            continue;
        }
        if ((float)($row['price'] ?? 0) <= 0) {
            continue;
        }
        $prices[] = (float)$row['price'];
    }
    return priceStatsFromValues($prices);
}
