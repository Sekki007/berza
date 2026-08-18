<?php

declare(strict_types=1);

function facebookPixelId(): string
{
    $id = preg_replace('/\D+/', '', (string)(siteSettings()['facebook_pixel_id'] ?? '')) ?? '';
    return $id;
}

function facebookPixelEnabled(): bool
{
    if (empty(siteSettings()['facebook_pixel_enabled'])) {
        return false;
    }
    return facebookPixelId() !== '';
}

function facebookPixelRequireConsent(): bool
{
    return !empty(siteSettings()['facebook_pixel_require_consent']);
}

function renderMarketingConsentBanner(): string
{
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;
    return <<<'HTML'
<div id="kp-cookie-banner" class="kp-cookie-banner" hidden>
  <div class="kp-cookie-banner-inner">
    <p>Koristimo kolačiće za analitiku i reklame (Meta/Google), da merimo registracije i oglase. Više u <a href="/privatnost">Politici privatnosti</a>. Možeš prihvatiti ili odbiti marketing kolačiće.</p>
    <div class="kp-cookie-banner-actions">
      <button type="button" class="btn-sm" data-kp-cookie="necessary">Samo neophodni</button>
      <button type="button" class="btn-sm btn-sm-primary" data-kp-cookie="all">Prihvati sve</button>
    </div>
  </div>
</div>
HTML;
}

/**
 * Queue a Pixel event to fire on the next page render (after redirect).
 *
 * @param array<string, mixed> $params
 */
function queueFacebookPixelEvent(string $event, array $params = [], bool $custom = false): void
{
    if ($event === '') {
        return;
    }
    if (!isset($_SESSION['fb_pixel_events']) || !is_array($_SESSION['fb_pixel_events'])) {
        $_SESSION['fb_pixel_events'] = [];
    }
    $_SESSION['fb_pixel_events'][] = [
        'event' => $event,
        'params' => $params,
        'custom' => $custom,
    ];
}

/**
 * @return list<array{event:string,params:array,custom:bool}>
 */
function consumeFacebookPixelEvents(): array
{
    $events = $_SESSION['fb_pixel_events'] ?? [];
    unset($_SESSION['fb_pixel_events']);
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
        $out[] = [
            'event' => $name,
            'params' => $params,
            'custom' => !empty($row['custom']),
        ];
    }
    return $out;
}

/**
 * Inline event for current page (ViewContent, Search…).
 *
 * @param array<string, mixed> $params
 */
function facebookPixelPageEvent(string $event, array $params = [], bool $custom = false): void
{
    if (!isset($GLOBALS['fb_pixel_page_events']) || !is_array($GLOBALS['fb_pixel_page_events'])) {
        $GLOBALS['fb_pixel_page_events'] = [];
    }
    $GLOBALS['fb_pixel_page_events'][] = [
        'event' => $event,
        'params' => $params,
        'custom' => $custom,
    ];
}

/**
 * @return list<array{event:string,params:array,custom:bool}>
 */
function facebookPixelPageEvents(): array
{
    $events = $GLOBALS['fb_pixel_page_events'] ?? [];
    return is_array($events) ? $events : [];
}

function renderFacebookPixelBootstrap(): string
{
    if (!facebookPixelEnabled()) {
        return '';
    }

    $pixelId = facebookPixelId();
    $requireConsent = facebookPixelRequireConsent();
    $queued = array_merge(consumeFacebookPixelEvents(), facebookPixelPageEvents());
    $payload = [
        'id' => $pixelId,
        'requireConsent' => $requireConsent,
        'events' => $queued,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{}';
    }

    $banner = $requireConsent ? renderMarketingConsentBanner() : '';

    return $banner . "\n" . '<script id="kp-fb-pixel-config" type="application/json">' . $json . '</script>' . "\n"
        . '<script src="/assets/js/facebook-pixel.js?v=20260729a" defer></script>';
}
