<?php

declare(strict_types=1);

function openaiPixelId(): string
{
    $id = trim((string)(siteSettings()['openai_pixel_id'] ?? ''));
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?? '';
    return $id;
}

function openaiPixelEnabled(): bool
{
    if (empty(siteSettings()['openai_pixel_enabled'])) {
        return false;
    }
    return openaiPixelId() !== '';
}

function openaiPixelRequireConsent(): bool
{
    return !empty(siteSettings()['openai_pixel_require_consent']);
}

function openaiPixelDebug(): bool
{
    return !empty(siteSettings()['openai_pixel_debug']);
}

function renderOpenAiPixelBootstrap(): string
{
    if (!openaiPixelEnabled()) {
        return '';
    }

    $payload = [
        'pixelId' => openaiPixelId(),
        'requireConsent' => openaiPixelRequireConsent(),
        'debug' => openaiPixelDebug(),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{}';
    }

    $banner = '';
    if ($payload['requireConsent'] && function_exists('renderMarketingConsentBanner')) {
        $banner = renderMarketingConsentBanner();
    }

    return $banner . "\n" . '<script id="kp-openai-pixel-config" type="application/json">' . $json . '</script>' . "\n"
        . '<script src="/assets/js/openai-pixel.js?v=20261115a" defer></script>';
}
