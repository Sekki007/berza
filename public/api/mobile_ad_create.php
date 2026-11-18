<?php

declare(strict_types=1);

if (!defined('KT_SKIP_BACKGROUND')) {
    define('KT_SKIP_BACKGROUND', true);
}

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/api_mobile.php';

mobileApiCors();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function mobileCreateCsrf(): string
{
    $token = (string)($_POST['_csrf'] ?? $_POST['csrf'] ?? '');
    if ($token === '') {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    return $token;
}

function mobileNormalizeImagesUpload(): void
{
    if (!isset($_FILES['images'])) {
        return;
    }
    if (is_array($_FILES['images']['name'] ?? null)) {
        return;
    }
    if (trim((string)($_FILES['images']['name'] ?? '')) === '') {
        return;
    }
    $_FILES['images'] = [
        'name' => [$_FILES['images']['name']],
        'type' => [$_FILES['images']['type'] ?? ''],
        'tmp_name' => [$_FILES['images']['tmp_name'] ?? ''],
        'error' => [$_FILES['images']['error'] ?? UPLOAD_ERR_NO_FILE],
        'size' => [$_FILES['images']['size'] ?? 0],
    ];
}

if ($method === 'GET') {
    if (!isLoggedIn()) {
        mobileApiJson(['ok' => false, 'error' => 'auth_required', 'csrf' => csrfToken()], 401);
    }
    $brands = function_exists('adPhoneBrands') ? adPhoneBrands() : getBrands();
    $cities = function_exists('getCitiesWithActiveAds') ? getCitiesWithActiveAds(40) : [];
    mobileApiJson([
        'ok' => true,
        'csrf' => csrfToken(),
        'ad_submit_token' => issueAdSubmitToken(),
        'brands' => array_values($brands),
        'cities' => array_values($cities),
    ]);
}

if ($method !== 'POST') {
    mobileApiJson(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!isLoggedIn()) {
    mobileApiJson(['ok' => false, 'error' => 'auth_required', 'csrf' => csrfToken()], 401);
}

if (!verifyCsrf(mobileCreateCsrf())) {
    mobileApiJson(['ok' => false, 'error' => 'csrf', 'csrf' => csrfToken()], 403);
}

mobileNormalizeImagesUpload();

$submitToken = trim((string)($_POST['ad_submit_token'] ?? ''));
$tokenState = consumeAdSubmitToken($submitToken);
if ($tokenState === 'missing') {
    mobileApiJson([
        'ok' => false,
        'error' => 'submit_token',
        'message' => 'Sesija za objavu je istekla. Probaj ponovo.',
        'csrf' => csrfToken(),
        'ad_submit_token' => issueAdSubmitToken(),
    ], 422);
}
if ($tokenState === 'bound') {
    $existingId = (int)(($_SESSION['ad_submit_tokens'][$submitToken]['ad_id'] ?? 0));
    if ($existingId > 0) {
        $existing = getAdById($existingId);
        mobileApiJson([
            'ok' => true,
            'duplicate' => true,
            'ad_id' => $existingId,
            'ad' => $existing ? mobileFormatAdCard($existing) : null,
            'csrf' => csrfToken(),
        ]);
    }
}

$adType = trim((string)($_POST['ad_type'] ?? 'telefon'));
if (!in_array($adType, ['telefon', 'delovi', 'servis'], true)) {
    $adType = 'telefon';
}
$listingType = trim((string)($_POST['listing_type'] ?? 'sell'));
if (!in_array($listingType, ['sell', 'buy', 'trade'], true)) {
    $listingType = 'sell';
}

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$brand = trim((string)($_POST['brand'] ?? ''));
$model = trim((string)($_POST['model'] ?? ''));
$storage = trim((string)($_POST['storage'] ?? ''));
$condition = trim((string)($_POST['condition_state'] ?? $_POST['condition'] ?? ''));
$contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
$priceRaw = trim((string)($_POST['price'] ?? ''));
$priceType = trim((string)($_POST['price_type'] ?? ''));
$price = $priceRaw === '' ? null : (int)preg_replace('/[^\d]/', '', $priceRaw);
if ($priceType === '') {
    $priceType = ($price === null || $price <= 0) ? 'open' : 'fixed';
}

if ($title === '' || mb_strlen($title) < 3) {
    mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Naslov je obavezan.', 'csrf' => csrfToken(), 'ad_submit_token' => issueAdSubmitToken()], 422);
}
if ($description === '' || mb_strlen($description) < 10) {
    mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Opis mora imati bar 10 karaktera.', 'csrf' => csrfToken(), 'ad_submit_token' => issueAdSubmitToken()], 422);
}
if ($location === '') {
    mobileApiJson(['ok' => false, 'error' => 'validation', 'message' => 'Unesi grad / lokaciju.', 'csrf' => csrfToken(), 'ad_submit_token' => issueAdSubmitToken()], 422);
}

$payload = [
    'title' => $title,
    'description' => $description,
    'location' => $location,
    'brand' => $brand,
    'model' => $model,
    'storage' => $storage,
    'condition_state' => $condition,
    'contact_phone' => $contactPhone,
    'price' => $price,
    'price_type' => $priceType,
    'currency' => 'eur',
    'ad_type' => $adType,
    'listing_type' => $listingType,
    'device_type' => trim((string)($_POST['device_type'] ?? 'phone')) ?: 'phone',
    'is_active' => 1,
    'is_sold' => 0,
    'images' => [],
];

if ($adType === 'servis') {
    $payload['service_type'] = trim((string)($_POST['service_type'] ?? 'servis'));
}
if ($adType === 'delovi') {
    $payload['part_type'] = trim((string)($_POST['part_type'] ?? ''));
    $payload['equipment_group'] = trim((string)($_POST['equipment_group'] ?? 'parts'));
}

try {
    $adId = saveAd($payload);
    if ($adId <= 0) {
        mobileApiJson(['ok' => false, 'error' => 'save_failed', 'message' => 'Čuvanje nije uspelo.', 'csrf' => csrfToken(), 'ad_submit_token' => issueAdSubmitToken()], 500);
    }
    if ($submitToken !== '') {
        bindAdSubmitToken($submitToken, $adId);
    }
    $saved = getAdById($adId);
    mobileApiJson([
        'ok' => true,
        'ad_id' => $adId,
        'ad' => $saved ? mobileFormatAdCard($saved) : null,
        'path' => $saved && function_exists('adUrl') ? adUrl($saved) : ('/oglas.php?id=' . $adId),
        'csrf' => csrfToken(),
        'note' => empty($saved['images'] ?? []) ? 'Dodaj fotografije u „Moji oglasi” za bolju prodaju.' : null,
    ]);
} catch (Throwable $e) {
    mobileApiJson([
        'ok' => false,
        'error' => 'exception',
        'message' => 'Greška pri čuvanju oglasa.',
        'csrf' => csrfToken(),
        'ad_submit_token' => issueAdSubmitToken(),
    ], 500);
}
