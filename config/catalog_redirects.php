<?php

declare(strict_types=1);

function catalogRedirectsPath(): string
{
    return dirname(__DIR__) . '/data/catalog_redirects.json';
}

/** @return array<string,array{to:string,status:int}> */
function catalogRedirectMap(): array
{
    $path = catalogRedirectsPath();
    if (!is_file($path)) {
        return [];
    }
    $raw = json_decode((string)file_get_contents($path), true);
    $rows = is_array($raw) ? ($raw['redirects'] ?? []) : [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $from = trim((string)($row['from'] ?? ''), '/');
        if ($from === '') {
            continue;
        }
        $status = (int)($row['status'] ?? 301);
        if (!in_array($status, [301, 410], true)) {
            $status = 301;
        }
        $out[$from] = [
            'to' => trim((string)($row['to'] ?? '')),
            'status' => $status,
        ];
    }
    return $out;
}

/** @return array{to:string,status:int}|null */
function catalogRedirectFor(string $brandSlug, string $modelSlug): ?array
{
    $key = strtolower(trim($brandSlug)) . '/' . strtolower(trim($modelSlug));
    $map = catalogRedirectMap();
    return $map[$key] ?? null;
}

/**
 * @param array<string,array<string,mixed>> $byPath
 */
function catalogCleanupTarget(array $model, array $byPath): ?string
{
    $slug = (string)($model['slug'] ?? '');
    $brand = (string)($model['brand_slug'] ?? '');
    $candidates = [];
    if (preg_match('/^(\d+)(.*)$/', $slug, $m) === 1 && !str_starts_with($slug, 'iphone')) {
        $candidates[] = $brand . '/iphone-' . $m[1] . $m[2];
    }
    if (preg_match('/^(s\d+)(.*)$/', $slug, $m) === 1) {
        $candidates[] = $brand . '/galaxy-' . $m[1] . $m[2];
    }
    $stripped = preg_replace('/-(novo|polovno|dual-sim|crni|beli).*$/', '', $slug);
    if (is_string($stripped) && $stripped !== '') {
        $candidates[] = $brand . '/' . $stripped;
    }
    foreach ($candidates as $candidate) {
        $candidate = strtolower($candidate);
        if ($candidate !== '' && isset($byPath[$candidate]) && $candidate !== strtolower($brand . '/' . $slug)) {
            return $candidate;
        }
    }
    return null;
}

function catalogApplyRedirect(string $brandSlug, string $modelSlug): bool
{
    $rule = catalogRedirectFor($brandSlug, $modelSlug);
    if ($rule === null) {
        return false;
    }
    if ($rule['status'] === 410 || $rule['to'] === '') {
        http_response_code(410);
        if (function_exists('renderPublicStatusPage')) {
            renderPublicStatusPage(
                410,
                'Model više nije u katalogu',
                'Ova adresa je uklonjena jer nije bila model telefona. Pogledaj katalog ili oglase.',
                [
                    ['href' => '/katalog', 'label' => 'Katalog telefona'],
                    ['href' => '/oglasi/telefoni', 'label' => 'Oglasi telefona'],
                ]
            );
        }
        return true;
    }
    $target = $rule['to'];
    if (!str_starts_with($target, '/')) {
        $target = '/katalog/' . $target;
    }
    header('Location: ' . $target, true, 301);
    return true;
}
