<?php

declare(strict_types=1);

/**
 * Prodati oglas sa modelom koji ima oglase ili specifikacije ide na hub modela.
 * Prvih N dana (sold_grace_days, podrazumevano 7) ostaje stranica sa trakom PRODATO,
 * bez kontakta. sold_at mora postojati; stari prodati oglasi bez datuma ne ulaze u grejs.
 *
 * @return array{action:string,url:string}
 */
function soldAdOutcome(array $ad): array
{
    $active = (int)($ad['is_active'] ?? 0) === 1;
    $sold = !empty($ad['is_sold']);
    if ($active && !$sold) {
        return ['action' => 'public', 'url' => ''];
    }
    if (!$sold) {
        return ['action' => 'gone', 'url' => ''];
    }

    $settings = function_exists('siteSettings') ? siteSettings() : [];
    $redirectOn = !array_key_exists('sold_redirect_to_model', $settings) || !empty($settings['sold_redirect_to_model']);
    $graceDays = array_key_exists('sold_grace_days', $settings) ? (int)$settings['sold_grace_days'] : 7;
    $soldAt = trim((string)($ad['sold_at'] ?? ''));
    if ($graceDays > 0 && $soldAt !== '') {
        $ts = strtotime($soldAt);
        if ($ts !== false && $ts >= time() - ($graceDays * 86400)) {
            return ['action' => 'grace', 'url' => ''];
        }
    }

    if ($redirectOn && function_exists('phoneCatalogUrlForAd')) {
        $url = phoneCatalogUrlForAd($ad);
        $modelId = trim((string)($ad['phone_model_id'] ?? ''));
        if ($url !== '' && $modelId !== '' && function_exists('phoneCatalogModel')) {
            $model = phoneCatalogModel($modelId);
            $hasSpecs = is_array($model) && ($model['specs'] ?? []) !== [];
            $activeCount = 0;
            if (!$hasSpecs && function_exists('getPublicAds')) {
                foreach (getPublicAds(['sort' => 'newest']) as $other) {
                    if (!is_array($other)) {
                        continue;
                    }
                    if (trim((string)($other['phone_model_id'] ?? '')) === $modelId) {
                        $activeCount++;
                        break;
                    }
                }
            }
            if ($hasSpecs || $activeCount >= 1) {
                return [
                    'action' => 'redirect',
                    'url' => $url . '?prodat=' . (int)($ad['id'] ?? 0),
                ];
            }
        }
    }

    return ['action' => 'gone', 'url' => ''];
}

function stampSoldAt(array $ad, bool $sold): array
{
    if ($sold) {
        if (trim((string)($ad['sold_at'] ?? '')) === '') {
            $ad['sold_at'] = date('Y-m-d H:i:s');
        }
        return $ad;
    }
    $ad['sold_at'] = null;
    return $ad;
}
