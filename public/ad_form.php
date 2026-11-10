<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $requestPath === '/ad_form.php') {
    $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
    $target = '/postavi-oglas' . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $target, true, 301);
    exit;
}
requireLogin();
require_once __DIR__ . '/partials/ad-form-chips.php';

$cfg = categoriesConfig();
$schema = adFormSchema();
$userId = (int)currentUser()['id'];
$adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $adId > 0;

$ad = [
    'title' => '',
    'description' => '',
    'ad_type' => 'telefon',
    'listing_type' => 'sell',
    'category_group' => 'phones',
    'brand' => '',
    'model' => '',
    'storage' => '',
    'ram' => '',
    'color' => '',
    'sim_status' => '',
    'battery_health' => null,
    'has_warranty' => 0,
    'warranty_months' => null,
    'accessories' => [],
    'device_type' => 'phone',
    'equipment_type' => '',
    'compatible_models' => '',
    'originality' => '',
    'service_types' => [],
    'supported_brands' => [],
    'has_work_warranty' => 0,
    'work_warranty_months' => null,
    'service_extras' => [],
    'contact_methods' => ['call', 'message'],
    'pickup_methods' => ['pickup'],
    'price' => '',
    'currency' => 'eur',
    'price_type' => 'fixed',
    'condition_state' => 'Polovno',
    'location' => '',
    'contact_phone' => '',
    'shop_name' => '',
    'shop_category_id' => '',
    'badge' => '',
    'images' => [],
    'is_active' => 1,
    'is_promoted' => 0,
    'is_sold' => 0,
];

$profile = findUserById($userId) ?? currentUser();

if ($isEdit) {
    $found = getAdById($adId);
    if (!$found || !userOwnsAd($found, $userId)) {
        setFlash('danger', 'Oglas nije pronađen ili nemate pristup.');
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }
    $ad = array_merge($ad, $found);
} else {
    $ad['contact_phone'] = trim((string)($profile['phone'] ?? ''));
    $ad['shop_name'] = trim((string)(($profile['shop_name'] ?? '') ?: getSellerShopName($profile)));
    $ad['location'] = trim((string)($profile['location'] ?? ''));
    if ($ad['location'] === '') {
        foreach (getAdsByUserId($userId) as $prev) {
            $prevLoc = trim((string)($prev['location'] ?? ''));
            if ($prevLoc !== '') {
                $ad['location'] = $prevLoc;
                break;
            }
        }
    }
    // Brzi unos: zadrži tip / kategoriju / kontakt sa prethodnog oglasa
    $prefill = $_SESSION['kt_quick_prefill'] ?? null;
    if (is_array($prefill) && (isset($_GET['more']) || !empty($prefill['active']))) {
        foreach (['ad_type', 'location', 'contact_phone', 'shop_category_id', 'currency', 'condition_state', 'brand'] as $key) {
            if (!empty($prefill[$key])) {
                $ad[$key] = $prefill[$key];
            }
        }
        if (($ad['ad_type'] ?? '') === 'servis') {
            $ad['listing_type'] = 'service';
        }
    }
}

if (!$isEdit && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && function_exists('phoneCatalogModel')) {
    $prefillCatalog = trim((string)($_GET['katalog'] ?? ''));
    $prefillModel = $prefillCatalog !== '' ? phoneCatalogModel($prefillCatalog) : null;
    if ($prefillModel && trim((string)$ad['model']) === '') {
        $ad['phone_model_id'] = $prefillModel['id'];
        $ad['phone_variant_id'] = null;
        $ad['brand'] = $prefillModel['brand'];
        $ad['model'] = $prefillModel['name'];
        $ad['device_type'] = 'phone';
        $ad['ad_type'] = 'telefon';
    }
}

$formError = '';
$formErrors = [];
$canPostService = canPostServiceAds($profile);
$editingOwnService = $isEdit && getAdType($ad) === 'servis';
$allowServiceType = $canPostService || $editingOwnService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/ad_form.php' . ($isEdit ? '?id=' . $adId : ''));
    $adType = trim((string)($_POST['ad_type'] ?? 'telefon'));
    if (!in_array($adType, ['telefon', 'delovi', 'servis'], true)) {
        $adType = 'telefon';
    }
    if ($adType === 'servis' && !$allowServiceType) {
        $adType = 'telefon';
        $formError = 'Servisne usluge mogu da objavljuju samo registrovane firme sa potvrđenim PIB-om. Pošalji zahtev u Nalog → Firma.';
        $formErrors['ad_type'] = $formError;
    }

    $priceType = normalizeAdPriceType((string)($_POST['price_type'] ?? 'fixed'));
    if ($priceType === 'contact') {
        $priceType = 'negotiable';
    }
    $currency = normalizeAdCurrency((string)($_POST['currency'] ?? 'eur'));
    $priceAmount = $priceType === 'fixed' ? (float)($_POST['price'] ?? 0) : 0;
    $extras = parseAdFormExtras($_POST, $adType);

    $condition = trim((string)($_POST['condition_state'] ?? ''));
    if ($adType === 'delovi') {
        $condition = trim((string)($_POST['condition_state_parts'] ?? $condition));
    }
    if ($condition === '') {
        $condition = $adType === 'delovi' ? 'Novo' : ($adType === 'telefon' ? 'Polovno' : '');
    }

    $brand = trim((string)($_POST['brand'] ?? ''));
    if ($adType === 'delovi') {
        $brandParts = trim((string)($_POST['brand_parts'] ?? ''));
        if ($brandParts !== '') {
            $brand = $brandParts;
        }
    }
    if ($adType === 'servis') {
        $brand = '';
        $condition = '';
    }

    $storage = $adType === 'telefon' ? trim((string)($_POST['storage'] ?? '')) : '';
    $model = $adType === 'servis' ? '' : trim((string)($_POST['model'] ?? ''));
    $categoryGroup = trim((string)($_POST['category_group'] ?? ''));
    if ($adType === 'telefon') {
        $categoryGroup = 'phones';
    } elseif ($adType === 'servis') {
        $categoryGroup = 'service';
    } else {
        // Chip vrste opreme → category_group
        $partsKind = trim((string)($_POST['parts_kind'] ?? ''));
        $kindMap = adFormSchema()['parts_kind_chips'] ?? [];
        if ($partsKind !== '' && isset($kindMap[$partsKind])) {
            $categoryGroup = (string)$kindMap[$partsKind]['category_group'];
            if (trim((string)($extras['equipment_type'] ?? '')) === '') {
                $extras['equipment_type'] = (string)$kindMap[$partsKind]['equipment_type'];
            }
        }
        $groupCfg = $cfg['groups'][$categoryGroup] ?? null;
        if (!$groupCfg || (string)($groupCfg['ad_type'] ?? '') !== 'delovi') {
            $categoryGroup = 'other_parts';
        }
    }

    $postedTitle = trim((string)($_POST['title'] ?? ''));
    $keptFromPost = [];
    if (is_array($_POST['keep_images'] ?? null)) {
        foreach ($_POST['keep_images'] as $keptUrl) {
            $keptUrl = trim((string)$keptUrl);
            if ($keptUrl !== '') {
                $keptFromPost[] = $keptUrl;
            }
        }
    }
    $baseImages = $isEdit ? (is_array($ad['images'] ?? null) ? $ad['images'] : []) : [];
    if (!$isEdit && is_array($_SESSION['ad_form_staged_images'] ?? null)) {
        $baseImages = array_values(array_unique(array_merge($baseImages, $_SESSION['ad_form_staged_images'])));
    }
    if ($keptFromPost !== []) {
        $baseImages = array_values(array_filter($baseImages, static fn($u) => in_array((string)$u, $keptFromPost, true)));
        foreach ($keptFromPost as $keptUrl) {
            if (!in_array($keptUrl, $baseImages, true)) {
                $baseImages[] = $keptUrl;
            }
        }
    }

    $payload = array_merge([
        'title' => $postedTitle,
        'description' => trim((string)($_POST['description'] ?? '')),
        'ad_type' => $adType,
        'category_group' => $categoryGroup,
        'brand' => $brand,
        'model' => $model,
        'storage' => $storage,
        'price' => $priceAmount,
        'currency' => $currency,
        'price_type' => $priceType,
        'condition_state' => $condition,
        'location' => trim((string)($_POST['location'] ?? '')),
        'country' => 'Srbija',
        'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
        'shop_name' => trim((string)($_POST['shop_name'] ?? '')),
        'shop_category_id' => normalizeAdShopCategoryId($profile, (string)($_POST['shop_category_id'] ?? '')),
        'badge' => trim((string)($_POST['badge'] ?? '')),
        // Nova objava je uvek aktivna — forma nema „Aktivan” na create, pa bi
        // isset($_POST['is_active']) inače uvek dao 0 (neaktivan oglas).
        'is_active' => $isEdit ? (isset($_POST['is_active']) ? 1 : 0) : 1,
        'is_sold' => isset($_POST['is_sold']) ? 1 : 0,
        'is_promoted' => (int)($ad['is_promoted'] ?? 0),
        'promoted_until' => $ad['promoted_until'] ?? null,
        'images' => $baseImages,
    ], $extras);
    if (function_exists('phoneCatalogAssignmentFromPost')) {
        $payload = array_merge($payload, phoneCatalogAssignmentFromPost(
            $_POST,
            $adType,
            (string)($payload['device_type'] ?? ''),
            (string)($payload['storage'] ?? ''),
            (string)($payload['brand'] ?? ''),
            (string)($payload['model'] ?? '')
        ));
    }

    if (isAdmin() && isset($_POST['is_promoted'])) {
        $payload['is_promoted'] = 1;
    } elseif (isAdmin() && !isset($_POST['is_promoted'])) {
        $payload['is_promoted'] = 0;
        $payload['promoted_until'] = null;
    }

    if (!empty($payload['is_sold'])) {
        $payload['is_promoted'] = 0;
    }

    $ad = array_merge($ad, $payload);

    $keptImageUrls = is_array($_POST['keep_images'] ?? null) ? array_map('strval', $_POST['keep_images']) : [];
    $originalImageUrls = is_array($payload['images'] ?? null) ? $payload['images'] : [];
    $hasKeptImage = array_intersect($originalImageUrls, $keptImageUrls) !== [];
    $hasValidUpload = false;
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
        foreach ($_FILES['images']['name'] as $imageIndex => $_imageName) {
            if ((int)($_FILES['images']['error'][$imageIndex] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmpImage = (string)($_FILES['images']['tmp_name'][$imageIndex] ?? '');
            $imageType = $tmpImage !== '' ? (mime_content_type($tmpImage) ?: '') : '';
            $nameHint = mb_strtolower((string)$_imageName);
            if (($imageType === '' || $imageType === 'application/octet-stream') && (str_ends_with($nameHint, '.heic') || str_ends_with($nameHint, '.heif'))) {
                $imageType = 'image/heic';
            }
            if (in_array($imageType, $allowedImageTypes, true)) {
                $hasValidUpload = true;
                break;
            }
        }
    }

    $listingTypeNow = normalizeListingType((string)($payload['listing_type'] ?? 'sell'), $adType);
    $photosRequired = ($payload['ad_type'] ?? '') === 'telefon' && $listingTypeNow !== 'buy';

    // Naslov nije obavezan — predloži sa servera.
    if ($payload['title'] === '') {
        $payload['title'] = suggestAdTitle($payload);
        $ad['title'] = $payload['title'];
    }

    if ($formError !== '') {
        // već postavljen (npr. servis bez potvrđenog PIB-a)
    } elseif ($payload['location'] === '') {
        $formError = 'Grad je obavezan.';
        $formErrors['location'] = $formError;
    } elseif ($payload['contact_phone'] === '') {
        $formError = 'Broj telefona je obavezan.';
        $formErrors['contact_phone'] = $formError;
    } elseif ($photosRequired && !$hasKeptImage && !$hasValidUpload && $originalImageUrls === []) {
        $formError = 'Za oglas telefona obavezna je najmanje jedna fotografija uređaja.';
        $formErrors['images'] = $formError;
    } elseif (($payload['ad_type'] ?? '') === 'servis' && !$allowServiceType) {
        $formError = 'Servisne usluge mogu da objavljuju samo registrovane firme sa potvrđenim PIB-om.';
        $formErrors['ad_type'] = $formError;
    } elseif ($payload['price_type'] === 'fixed' && $payload['price'] <= 0 && $adType !== 'servis') {
        $formError = 'Unesi cenu ili označi Po dogovoru.';
        $formErrors['price'] = $formError;
    } elseif ($payload['price'] < 0) {
        $formError = 'Cena nije validna.';
        $formErrors['price'] = $formError;
    } elseif ($adType === 'servis' && $payload['price_type'] === 'fixed' && $payload['price'] <= 0) {
        $payload['price_type'] = 'negotiable';
        $payload['price'] = 0;
        $ad['price_type'] = 'negotiable';
        $ad['price'] = 0;
    } elseif ($payload['price_type'] === 'fixed') {
        $priceConfirmed = !empty($_POST['price_confirmed']);
        $priceErr = validateAdPrice(
            (float)$payload['price'],
            (string)$payload['currency'],
            (string)$payload['ad_type'],
            $priceConfirmed
        );
        if ($priceErr !== null) {
            $formError = $priceErr;
            $formErrors['price'] = $priceErr;
        }
    }

    if ($formError !== '') {
        // Sačuvaj nove upload-e da korisnik ne bira ponovo.
        $stagedNew = stageAdFormUploads($userId);
        if ($stagedNew !== []) {
            $mergedImages = array_values(array_unique(array_merge($originalImageUrls, $stagedNew)));
            $_SESSION['ad_form_staged_images'] = $mergedImages;
            $ad['images'] = $mergedImages;
            $payload['images'] = $mergedImages;
        } elseif ($originalImageUrls !== []) {
            $_SESSION['ad_form_staged_images'] = $originalImageUrls;
            $ad['images'] = $originalImageUrls;
        }
    }

    if ($formError === '') {
        if ($payload['shop_name'] === '') {
            $payload['shop_name'] = trim((string)(($profile['shop_name'] ?? '') ?: getSellerShopName($profile)));
        }
        if ($payload['title'] === '') {
            $payload['title'] = 'Oglas';
        }

        if ($isEdit) {
            saveAd($payload, $adId);
            unset($_SESSION['ad_form_staged_images']);
            setFlash('success', 'Oglas je uspešno izmenjen.');
            header('Location: /nalog.php?tab=oglasi');
            exit;
        }

        $submitToken = trim((string)($_POST['ad_submit_token'] ?? ''));
        $reuseId = 0;
        if (consumeAdSubmitToken($submitToken) === 'replay') {
            $reuseId = usedAdSubmitAdId($submitToken);
        }

        if ($reuseId > 0) {
            bindAdSubmitToken($submitToken, $reuseId);
            $newId = $reuseId;
            setFlash('success', 'Oglas je uspešno objavljen.');
            $addAnother = isset($_POST['save_and_add_another']);
            if ($addAnother) {
                $_SESSION['kt_quick_prefill'] = [
                    'active' => 1,
                    'ad_type' => (string)($payload['ad_type'] ?? 'telefon'),
                    'location' => (string)($payload['location'] ?? ''),
                    'contact_phone' => (string)($payload['contact_phone'] ?? ''),
                    'shop_category_id' => (string)($payload['shop_category_id'] ?? ''),
                    'currency' => (string)($payload['currency'] ?? 'eur'),
                    'condition_state' => (string)($payload['condition_state'] ?? ''),
                    'brand' => (string)($payload['brand'] ?? ''),
                ];
                header('Location: /ad_form.php?more=1');
                exit;
            }
            unset($_SESSION['kt_quick_prefill']);
            header('Location: /nalog.php?tab=oglasi');
            exit;
        }

        $newId = saveAd($payload);
        bindAdSubmitToken($submitToken, $newId);
        unset($_SESSION['ad_form_staged_images']);
        clearStagedAdFormUploads($userId);

        $profilePatch = [];
        if (trim((string)($profile['phone'] ?? '')) === '' && $payload['contact_phone'] !== '') {
            $profilePatch['phone'] = $payload['contact_phone'];
        }
        if (trim((string)($profile['shop_name'] ?? '')) === '' && $payload['shop_name'] !== '') {
            $profilePatch['shop_name'] = $payload['shop_name'];
        }
        if (trim((string)($profile['location'] ?? '')) === '' && $payload['location'] !== '') {
            $profilePatch['location'] = $payload['location'];
        }
        if ($profilePatch !== []) {
            updateUserProfile($userId, array_merge([
                'full_name' => (string)($profile['full_name'] ?? ''),
                'phone' => (string)($profile['phone'] ?? ''),
                'email' => (string)($profile['email'] ?? ''),
            ], $profilePatch));
        }

        $promoPackage = trim((string)($_POST['promo_package'] ?? 'standard'));
        $wantHighlight = isset($_POST['promo_highlight']);
        $promoNotes = [];

        if (topPurchaseEnabled() && $promoPackage !== '' && $promoPackage !== 'standard') {
            $order = createTopOrder($userId, $newId, $promoPackage);
            if ($order && ($order['status'] ?? '') === 'paid') {
                $promoNotes[] = 'TOP aktiviran (' . (int)$order['days'] . ' dana).';
            } elseif ($order === null && creditsEnabled()) {
                $promoNotes[] = 'TOP nije aktiviran — proveri saldo kredita.';
            }
        }

        if ($wantHighlight) {
            $hi = activateAdHighlight($newId, $userId, 7);
            if (!empty($hi['ok'])) {
                $promoNotes[] = 'Istaknut plavom bojom.';
            } elseif (($hi['error'] ?? '') === 'credits') {
                $promoNotes[] = 'Plavo isticanje nije aktivirano — nema dovoljno kredita.';
            }
        }

        $msg = 'Oglas je uspešno objavljen.';
        if ($promoNotes) {
            $msg .= ' ' . implode(' ', $promoNotes);
        }
        setFlash('success', $msg);
        queueFacebookPixelEvent('PostAd', [
            'content_ids' => [(string)$newId],
            'content_type' => 'product',
            'content_name' => (string)($payload['title'] ?? ''),
        ], true);
        queueGoogleTagEvent('post_ad', [
            'content_id' => (string)$newId,
            'content_type' => 'product',
            'content_name' => (string)($payload['title'] ?? ''),
        ]);

        $addAnother = isset($_POST['save_and_add_another']);
        if ($addAnother) {
            $_SESSION['kt_quick_prefill'] = [
                'active' => 1,
                'ad_type' => (string)($payload['ad_type'] ?? 'telefon'),
                'location' => (string)($payload['location'] ?? ''),
                'contact_phone' => (string)($payload['contact_phone'] ?? ''),
                'shop_category_id' => (string)($payload['shop_category_id'] ?? ''),
                'currency' => (string)($payload['currency'] ?? 'eur'),
                'condition_state' => (string)($payload['condition_state'] ?? ''),
                'brand' => (string)($payload['brand'] ?? ''),
            ];
            setFlash('success', $msg . ' Možeš odmah dodati sledeći.');
            header('Location: /ad_form.php?more=1');
            exit;
        }
        unset($_SESSION['kt_quick_prefill']);
        header('Location: /nalog.php?tab=oglasi');
        exit;
    }
}

$currentType = getAdType($ad);
if ($currentType === 'servis' && !$allowServiceType) {
    $currentType = 'telefon';
    $ad['ad_type'] = 'telefon';
    $ad['listing_type'] = 'sell';
}
$currentPriceType = adPriceType($ad) === 'contact' ? 'negotiable' : adPriceType($ad);
$currentCurrency = adCurrency($ad);
$currentListing = normalizeListingType((string)($ad['listing_type'] ?? 'sell'), $currentType);
$phoneBrands = $schema['phone_brands'];
$existingImages = is_array($ad['images'] ?? null) ? $ad['images'] : [];
if (!$isEdit && $existingImages === [] && is_array($_SESSION['ad_form_staged_images'] ?? null)) {
    $existingImages = array_values(array_filter($_SESSION['ad_form_staged_images'], static fn($u) => is_string($u) && $u !== ''));
    $ad['images'] = $existingImages;
}
$titlePreview = trim((string)($ad['title'] ?? ''));
if ($titlePreview === '') {
    $titlePreview = suggestAdTitle($ad);
}
$groupMeta = [];
foreach ($cfg['groups'] as $key => $group) {
    $groupMeta[$key] = [
        'ad_type' => (string)($group['ad_type'] ?? ''),
        'brand' => (string)($group['brand'] ?? ''),
        'equipment_type' => (string)($group['equipment_type'] ?? ''),
        'equipment_bucket' => categoryGroupEquipmentBucket($key, $group),
    ];
}

$accessoriesSel = is_array($ad['accessories'] ?? null) ? $ad['accessories'] : [];
$serviceTypesSel = is_array($ad['service_types'] ?? null) ? $ad['service_types'] : [];
$supportedBrandsSel = is_array($ad['supported_brands'] ?? null) ? $ad['supported_brands'] : [];
$serviceExtrasSel = is_array($ad['service_extras'] ?? null) ? $ad['service_extras'] : [];
$contactSel = is_array($ad['contact_methods'] ?? null) ? $ad['contact_methods'] : ['call', 'message'];
$pickupSel = is_array($ad['pickup_methods'] ?? null) ? $ad['pickup_methods'] : ['pickup'];
$bizStatus = userBusinessStatus($profile);
$shopCategoriesForForm = canManageShopCategories($profile) ? getShopCategories($profile) : [];
$currentShopCategoryId = trim((string)($ad['shop_category_id'] ?? ''));

$adSubmitToken = '';
if (!$isEdit) {
    $adSubmitToken = trim((string)($_POST['ad_submit_token'] ?? ''));
    if ($adSubmitToken === '') {
        $adSubmitToken = issueAdSubmitToken();
    }
}

$pageTitle = ($isEdit ? 'Izmena oglasa' : 'Postavi oglas') . ' — KupiTelefon';
$activePage = 'dodaj';
$bodyClass = 'page-ad-form';
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
require __DIR__ . '/partials/ad-form-body.php';
require __DIR__ . '/partials/layout-end.php';
