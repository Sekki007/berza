<?php

declare(strict_types=1);

function compareMaxAds(): int
{
    return 3;
}

function compareIds(): array
{
    if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
        $_SESSION['compare'] = [];
    }
    return array_values(array_unique(array_map('intval', $_SESSION['compare'])));
}

function isInCompare(int $adId): bool
{
    return in_array($adId, compareIds(), true);
}

function toggleCompare(int $adId): array
{
    $ids = compareIds();
    if ($adId <= 0) {
        return ['ids' => $ids, 'added' => false, 'full' => false];
    }
    if (in_array($adId, $ids, true)) {
        $ids = array_values(array_filter($ids, static fn($id) => $id !== $adId));
        $_SESSION['compare'] = $ids;
        return ['ids' => $ids, 'added' => false, 'full' => false];
    }
    if (count($ids) >= compareMaxAds()) {
        return ['ids' => $ids, 'added' => false, 'full' => true];
    }
    $ids[] = $adId;
    $_SESSION['compare'] = $ids;
    return ['ids' => $ids, 'added' => true, 'full' => false];
}

function clearCompare(): void
{
    $_SESSION['compare'] = [];
}

function getCompareAds(): array
{
    $ads = [];
    foreach (compareIds() as $id) {
        $ad = getAdById($id);
        if ($ad && (int)($ad['is_active'] ?? 0) === 1) {
            $ads[] = $ad;
        }
    }
    return $ads;
}
