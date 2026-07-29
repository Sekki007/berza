<?php

declare(strict_types=1);

function googleTagGa4Id(): string
{
    return strtoupper(trim((string)(siteSettings()['google_tag_ga4_id'] ?? '')));
}

function googleTagAdsId(): string
{
    return strtoupper(trim((string)(siteSettings()['google_tag_ads_id'] ?? '')));
}

function googleTagEnabled(): bool
{
    if (empty(siteSettings()['google_tag_enabled'])) {
        return false;
    }
    return googleTagGa4Id() !== '' || googleTagAdsId() !== '';
}

function googleTagRequireConsent(): bool
{
    return !empty(siteSettings()['google_tag_require_consent']);
}

/**
 * @param array<string,mixed> $params
 */
function queueGoogleTagEvent(string $event, array $params = []): void
{
    if ($event === '') {
        return;
    }
    if (!isset($_SESSION['google_tag_events']) || !is_array($_SESSION['google_tag_events'])) {
        $_SESSION['google_tag_events'] = [];
    }
    $_SESSION['google_tag_events'][] = [
        'event' => $event,
        'params' => $params,
    ];
}

/**
 * @return list<array{event:string,params:array}>
 */
function consumeGoogleTagEvents(): array
{
    $events = $_SESSION['google_tag_events'] ?? [];
    unset($_SESSION['google_tag_events']);
    if (!is_array($events)) {
        return [];
    }
    $out = [];
    foreach ($events as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['event'] ?? ''));
        if ($name === '') {
            continue;
        }
        $params = $row['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }
        $out[] = ['event' => $name, 'params' => $params];
    }
    return $out;
}

/**
 * @param array<string,mixed> $params
 */
function googleTagPageEvent(string $event, array $params = []): void
{
    if (!isset($GLOBALS['google_tag_page_events']) || !is_array($GLOBALS['google_tag_page_events'])) {
        $GLOBALS['google_tag_page_events'] = [];
    }
    $GLOBALS['google_tag_page_events'][] = [
        'event' => $event,
        'params' => $params,
    ];
}

/**
 * @return list<array{event:string,params:array}>
 */
function googleTagPageEvents(): array
{
    $events = $GLOBALS['google_tag_page_events'] ?? [];
    return is_array($events) ? $events : [];
}

function renderGoogleTagBootstrap(): string
{
    if (!googleTagEnabled()) {
        return '';
    }

    $ga4 = googleTagGa4Id();
    $ads = googleTagAdsId();
    $requireConsent = googleTagRequireConsent();
    $events = array_merge(consumeGoogleTagEvents(), googleTagPageEvents());

    $payload = [
        'ga4' => $ga4,
        'ads' => $ads,
        'requireConsent' => $requireConsent,
        'events' => $events,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{}';
    }

    $banner = '';
    if ($requireConsent && function_exists('renderMarketingConsentBanner')) {
        $banner = renderMarketingConsentBanner();
    }

    return $banner . "\n" . '<script id="kp-google-tag-config" type="application/json">' . $json . '</script>' . "\n"
        . '<script src="/assets/js/google-tag.js?v=20260729a" defer></script>';
}

