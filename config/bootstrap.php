<?php

declare(strict_types=1);

/**
 * Trajna prijava (bitno za Android WebView app):
 * session cookie sa lifetime > 0 preživljava zatvaranje app-a.
 */
function sessionCookieSecure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    $fwd = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $fwd === 'https';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $lifetime = 60 * 60 * 24 * 30; // 30 dana
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => sessionCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function rememberCookieName(): string
{
    return 'kt_remember';
}

function rememberCookieTtl(): int
{
    return 60 * 60 * 24 * 30; // 30 dana
}

function setSessionUserFromProfile(array $user): void
{
    $isAdminUser = !empty($user['is_admin']) || (($user['username'] ?? '') === 'admin');
    $_SESSION['user'] = [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'full_name' => (string)($user['full_name'] ?? ''),
        'is_admin' => $isAdminUser,
    ];
}

function clearRememberCookie(): void
{
    setcookie(rememberCookieName(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => sessionCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[rememberCookieName()]);
}

function rememberTokenEncode(string $token): string
{
    return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
}

function rememberTokenDecode(string $encoded): ?string
{
    $raw = strtr($encoded, '-_', '+/');
    $pad = strlen($raw) % 4;
    if ($pad > 0) {
        $raw .= str_repeat('=', 4 - $pad);
    }
    $bin = base64_decode($raw, true);
    return is_string($bin) ? $bin : null;
}

function clearRememberLoginForUser(int $userId): void
{
    if ($userId > 0 && function_exists('patchUser')) {
        patchUser($userId, [
            'remember_selector' => '',
            'remember_token_hash' => '',
            'remember_expires_at' => null,
        ]);
    }
    clearRememberCookie();
}

function issueRememberLogin(array $user, bool $enabled): void
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        clearRememberCookie();
        return;
    }
    if (!$enabled) {
        clearRememberLoginForUser($userId);
        return;
    }

    $selector = bin2hex(random_bytes(9));
    $token = random_bytes(32);
    $hash = hash('sha256', $token);
    $expiresAtTs = time() + rememberCookieTtl();
    $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);

    if (function_exists('patchUser')) {
        patchUser($userId, [
            'remember_selector' => $selector,
            'remember_token_hash' => $hash,
            'remember_expires_at' => $expiresAt,
        ]);
    }

    setcookie(rememberCookieName(), $selector . ':' . rememberTokenEncode($token), [
        'expires' => $expiresAtTs,
        'path' => '/',
        'secure' => sessionCookieSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tryRememberLogin(): void
{
    if (isLoggedIn()) {
        return;
    }
    $raw = trim((string)($_COOKIE[rememberCookieName()] ?? ''));
    if ($raw === '' || !str_contains($raw, ':')) {
        return;
    }
    [$selector, $encoded] = explode(':', $raw, 2);
    $selector = trim($selector);
    $token = rememberTokenDecode(trim($encoded));
    if ($selector === '' || $token === null) {
        clearRememberCookie();
        return;
    }

    $matchedUser = null;
    foreach (getUsers() as $u) {
        if ((string)($u['remember_selector'] ?? '') === $selector) {
            $matchedUser = $u;
            break;
        }
    }
    if (!$matchedUser) {
        clearRememberCookie();
        return;
    }

    $expiresAt = trim((string)($matchedUser['remember_expires_at'] ?? ''));
    $expiresTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
    if ($expiresTs === false || $expiresTs < time()) {
        clearRememberLoginForUser((int)($matchedUser['id'] ?? 0));
        return;
    }

    $storedHash = trim((string)($matchedUser['remember_token_hash'] ?? ''));
    $actualHash = hash('sha256', $token);
    if ($storedHash === '' || !hash_equals($storedHash, $actualHash)) {
        clearRememberLoginForUser((int)($matchedUser['id'] ?? 0));
        return;
    }

    session_regenerate_id(true);
    setSessionUserFromProfile($matchedUser);
    issueRememberLogin($matchedUser, true); // rotacija tokena
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/database.php';

function dataPath(string $filename): string
{
    return dirname(__DIR__) . '/data/' . $filename;
}

function storageDriver(): string
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }
    $raw = strtolower(trim((string)envValue('STORAGE_DRIVER', 'json')));
    $driver = $raw === 'mysql' ? 'mysql' : 'json';
    return $driver;
}

function usesMySqlStorage(): bool
{
    return storageDriver() === 'mysql';
}

function jsonStorageDefaults(): array
{
    require_once __DIR__ . '/settings.php';
    return [
        'users.json' => [
            [
                'id' => 1,
                'username' => 'admin',
                'password_hash' => '$2y$10$IePWFxxngm51mSE78bxi8.44l4n7pWf.8kmDDHmmcf9WSODhPPZfK',
                'full_name' => 'Administrator',
                'phone' => '0601234567',
                'is_admin' => true,
                'created_at' => '2026-07-22 11:00:00',
            ],
        ],
        'ads.json' => [],
        'settings.json' => defaultSiteSettings(),
        'messages.json' => [],
        'ratings.json' => [],
        'reports.json' => [],
        'notifications.json' => [],
        'push_tokens.json' => [],
        'top_orders.json' => [],
        'credit_deposits.json' => [],
        'credit_transactions.json' => [],
        'saved_searches.json' => [],
        'guides.json' => [],
        'ad_stats.json' => [],
        'sms_rate_limits.json' => [],
        'nbs_rate_cache.json' => [],
        'top_state.json' => [],
        'expiry_state.json' => [],
        'saved_search_state.json' => [],
    ];
}

function jsonStorageEncode(array $payload): string
{
    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]';
}

function ensureMySqlStorageTable(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec(
        'CREATE TABLE IF NOT EXISTS json_documents (
            filename VARCHAR(120) NOT NULL PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $ready = true;
}

function readJsonFromMySql(string $filename): array
{
    ensureMySqlStorageTable();
    $stmt = db()->prepare('SELECT payload FROM json_documents WHERE filename = :filename LIMIT 1');
    $stmt->execute([':filename' => $filename]);
    $payload = $stmt->fetchColumn();
    if (!is_string($payload) || trim($payload) === '') {
        return [];
    }
    $decoded = json_decode($payload, true);
    return is_array($decoded) ? $decoded : [];
}

function writeJsonToMySql(string $filename, array $payload): void
{
    ensureMySqlStorageTable();
    $stmt = db()->prepare(
        'INSERT INTO json_documents (filename, payload, updated_at)
         VALUES (:filename, :payload, NOW())
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = NOW()'
    );
    $stmt->execute([
        ':filename' => $filename,
        ':payload' => jsonStorageEncode($payload),
    ]);
}

function readJsonFile(string $filename): array
{
    if (usesMySqlStorage()) {
        return readJsonFromMySql($filename);
    }
    $path = dataPath($filename);
    if (!file_exists($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function writeJsonFile(string $filename, array $payload): void
{
    if (usesMySqlStorage()) {
        writeJsonToMySql($filename, $payload);
        return;
    }
    $path = dataPath($filename);
    file_put_contents($path, jsonStorageEncode($payload));
}

function ensureJsonDataFiles(): void
{
    $defaults = jsonStorageDefaults();
    if (usesMySqlStorage()) {
        ensureMySqlStorageTable();
        $stmt = db()->prepare(
            'INSERT INTO json_documents (filename, payload, updated_at)
             VALUES (:filename, :payload, NOW())
             ON DUPLICATE KEY UPDATE filename = filename'
        );
        foreach ($defaults as $filename => $payload) {
            $stmt->execute([
                ':filename' => $filename,
                ':payload' => jsonStorageEncode(is_array($payload) ? $payload : []),
            ]);
        }
        return;
    }

    $dataDir = dirname(__DIR__) . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    foreach ($defaults as $filename => $payload) {
        if (!file_exists(dataPath($filename))) {
            writeJsonFile($filename, is_array($payload) ? $payload : []);
        }
    }
}

function getUsers(): array
{
    return readJsonFile('users.json');
}

function findUserByUsername(string $username): ?array
{
    foreach (getUsers() as $user) {
        if (($user['username'] ?? '') === $username) {
            return $user;
        }
    }
    return null;
}

function findUserById(int $id): ?array
{
    foreach (getUsers() as $user) {
        if ((int)($user['id'] ?? 0) === $id) {
            return $user;
        }
    }
    return null;
}

/**
 * @return int|false New user id on success
 */
function registerUser(string $username, string $password, string $fullName, string $phone = ''): int|false
{
    if ($username === '' || $password === '' || $fullName === '') {
        return false;
    }
    if (mb_strlen($username) < 3) {
        return false;
    }
    if (findUserByUsername($username)) {
        return false;
    }

    $normalizedPhone = normalizePhoneRs($phone);
    if ($normalizedPhone === null || !isAllowedSmsPhone($normalizedPhone)) {
        return false;
    }

    if (findUserByPhone($normalizedPhone)) {
        return false;
    }

    $users = getUsers();
    $maxId = 0;
    foreach ($users as $user) {
        $maxId = max($maxId, (int)($user['id'] ?? 0));
    }

    $newId = $maxId + 1;
    $shopSlug = allocateUniqueShopSlug(normalizeShopSlug($username) ?: ('izlog-' . $newId), $newId);
    $users[] = [
        'id' => $newId,
        'username' => $username,
        'shop_slug' => $shopSlug,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'phone' => $normalizedPhone,
        'phone_verified_at' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    writeJsonFile('users.json', $users);
    return $newId;
}

function updateUserPassword(int $userId, string $newPassword): bool
{
    if ($userId <= 0 || strlen($newPassword) < 6) {
        return false;
    }
    return patchUser($userId, [
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    ]);
}

function updateUserProfile(int $userId, array $data): bool
{
    $users = getUsers();
    foreach ($users as &$user) {
        if ((int)$user['id'] !== $userId) {
            continue;
        }
        $user['full_name'] = trim((string)($data['full_name'] ?? $user['full_name']));
        if (array_key_exists('phone', $data)) {
            $rawPhone = trim((string)$data['phone']);
            $normalized = $rawPhone !== '' ? normalizePhoneRs($rawPhone) : null;
            $oldPhone = normalizePhoneRs((string)($user['phone'] ?? ''));
            if ($normalized === null && $rawPhone !== '') {
                return false;
            }
            if ($normalized !== $oldPhone) {
                if ($normalized !== null) {
                    $existing = findUserByPhone($normalized);
                    if ($existing && (int)($existing['id'] ?? 0) !== $userId) {
                        return false;
                    }
                }
                $user['phone'] = $normalized ?? '';
                $user['phone_verified_at'] = null;
                $user['otp_purpose'] = null;
                $user['otp_hash'] = null;
                $user['otp_sent_at'] = null;
                $user['otp_attempts'] = 0;
                $user['otp_verified_at'] = null;
            }
        }
        if (array_key_exists('shop_name', $data)) {
            $user['shop_name'] = trim((string)$data['shop_name']);
        }
        if (array_key_exists('shop_slug', $data)) {
            $rawSlug = trim((string)$data['shop_slug']);
            $slug = normalizeShopSlug($rawSlug);
            if ($slug === '' || !isValidShopSlug($slug) || shopSlugTaken($slug, $userId)) {
                return false;
            }
            $user['shop_slug'] = $slug;
        }
        if (array_key_exists('account_type', $data) || array_key_exists('business_kind', $data) || array_key_exists('pib', $data)) {
            $newType = array_key_exists('account_type', $data)
                ? ((string)$data['account_type'] === 'business' ? 'business' : 'private')
                : userAccountType($user);
            $oldType = userAccountType($user);
            $oldKind = userBusinessKind($user);
            $oldPib = normalizePib((string)($user['pib'] ?? '')) ?? '';

            if ($newType === 'private') {
                $user['account_type'] = 'private';
                $user['business_kind'] = '';
                $user['pib'] = '';
                $user['business_status'] = 'none';
                $user['business_verified_at'] = null;
                $user['business_requested_at'] = null;
                $user['business_reject_reason'] = null;
            } else {
                $kind = array_key_exists('business_kind', $data)
                    ? (string)$data['business_kind']
                    : userBusinessKind($user);
                if (!in_array($kind, allowedBusinessKinds(), true)) {
                    $kind = '';
                }
                $pibRaw = array_key_exists('pib', $data) ? trim((string)$data['pib']) : (string)($user['pib'] ?? '');
                $pib = $pibRaw !== '' ? normalizePib($pibRaw) : null;
                if ($pibRaw !== '' && $pib === null) {
                    return false;
                }

                $user['account_type'] = 'business';
                $user['business_kind'] = $kind;
                $user['pib'] = $pib ?? '';

                $changedIdentity = $oldType !== 'business'
                    || $oldKind !== $kind
                    || $oldPib !== (string)($pib ?? '');
                if ($changedIdentity && userBusinessStatus($user) === 'approved') {
                    $user['business_status'] = 'none';
                    $user['business_verified_at'] = null;
                }
            }
        }
        if (array_key_exists('shop_bio', $data)) {
            $user['shop_bio'] = trim((string)$data['shop_bio']);
        }
        if (array_key_exists('location', $data)) {
            $user['location'] = trim((string)$data['location']);
        }
        if (array_key_exists('email', $data)) {
            $email = trim((string)$data['email']);
            $email = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
            $oldEmail = trim((string)($user['email'] ?? ''));
            if ($email !== $oldEmail) {
                $user['email'] = $email;
                $user['email_verified_at'] = null;
                $user['email_verify_token'] = null;
                $user['email_verify_sent_at'] = null;
            } else {
                $user['email'] = $email;
            }
        }
        if (array_key_exists('notify_email', $data)) {
            $user['notify_email'] = !empty($data['notify_email']);
        }
        if (array_key_exists('notify_telegram', $data)) {
            $user['notify_telegram'] = !empty($data['notify_telegram']);
        }
        if (array_key_exists('notify_telegram_messages', $data)) {
            $user['notify_telegram_messages'] = !empty($data['notify_telegram_messages']);
        }
        if (array_key_exists('notify_telegram_alerts', $data)) {
            $user['notify_telegram_alerts'] = !empty($data['notify_telegram_alerts']);
        }
        if (array_key_exists('notify_telegram_system', $data)) {
            $user['notify_telegram_system'] = !empty($data['notify_telegram_system']);
        }
        if (array_key_exists('notify_push', $data)) {
            $user['notify_push'] = !empty($data['notify_push']);
        }
        writeJsonFile('users.json', $users);
        $sessionId = (int)($_SESSION['user']['id'] ?? 0);
        if ($sessionId === $userId) {
            $_SESSION['user']['full_name'] = $user['full_name'];
            if (array_key_exists('email', $user)) {
                $_SESSION['user']['email'] = $user['email'];
            }
            if (array_key_exists('notify_email', $user)) {
                $_SESSION['user']['notify_email'] = $user['notify_email'];
            }
            if (array_key_exists('notify_telegram', $user)) {
                $_SESSION['user']['notify_telegram'] = $user['notify_telegram'];
            }
            if (array_key_exists('notify_telegram_messages', $user)) {
                $_SESSION['user']['notify_telegram_messages'] = $user['notify_telegram_messages'];
            }
            if (array_key_exists('notify_telegram_alerts', $user)) {
                $_SESSION['user']['notify_telegram_alerts'] = $user['notify_telegram_alerts'];
            }
            if (array_key_exists('notify_telegram_system', $user)) {
                $_SESSION['user']['notify_telegram_system'] = $user['notify_telegram_system'];
            }
            if (array_key_exists('notify_push', $user)) {
                $_SESSION['user']['notify_push'] = $user['notify_push'];
            }
        }
        return true;
    }
    return false;
}

function categoryFromAdType(string $type): string
{
    return match ($type) {
        'delovi' => 'Delovi',
        'servis' => 'Servisne usluge',
        default => 'Telefoni',
    };
}

function adTypeFromCategory(string $category): string
{
    $c = mb_strtolower(trim($category));
    if (str_contains($c, 'servis')) {
        return 'servis';
    }
    if (str_contains($c, 'deo') || str_contains($c, 'oprema')) {
        return 'delovi';
    }
    return 'telefon';
}

function getAdType(array $ad): string
{
    $type = (string)($ad['ad_type'] ?? '');
    if (in_array($type, ['telefon', 'delovi', 'servis'], true)) {
        return $type;
    }
    return adTypeFromCategory((string)($ad['category'] ?? ''));
}

/** Tipovi opreme koji spadaju u „Delovi“ (rezervni delovi). */
function equipmentPartsTypes(): array
{
    return ['Rezervni delovi'];
}

/** Tipovi opreme koji spadaju u „Oprema“ (maske, punjači…). */
function equipmentOpremaTypes(): array
{
    return ['Maska/Futrola', 'Zaštitno staklo', 'Punjač/Kabl', 'Slušalice', 'PowerBank', 'Ostalo'];
}

/**
 * Podgrupa za tip=delovi: parts | oprema.
 * Rezervni delovi → parts; sve ostalo (uključujući prazno) → oprema.
 */
function adEquipmentGroup(array $ad): string
{
    if (getAdType($ad) !== 'delovi') {
        return '';
    }
    $eq = trim((string)($ad['equipment_type'] ?? ''));
    if (in_array($eq, equipmentPartsTypes(), true)) {
        return 'parts';
    }
    return 'oprema';
}

function adTypeLabel(string $type): string
{
    return match ($type) {
        'servis' => 'Servis',
        'delovi' => 'Oprema',
        default => 'Uređaj',
    };
}

function formatRelativeTime(string $createdAt): string
{
    $ts = strtotime($createdAt);
    if ($ts === false) {
        return 'danas';
    }
    $diff = time() - $ts;
    if ($diff < 3600) {
        $m = max(1, (int)floor($diff / 60));
        return "pre {$m}m";
    }
    if ($diff < 86400) {
        $h = max(1, (int)floor($diff / 3600));
        return "pre {$h}h";
    }
    $d = max(1, (int)floor($diff / 86400));
    return $d === 1 ? 'juče' : "pre {$d} dana";
}

function formatPrice(float $price, string $currency = 'eur'): string
{
    $currency = normalizeAdCurrency($currency);
    $suffix = $currency === 'rsd' ? ' din' : ' €';
    return number_format($price, 0, ',', '.') . $suffix;
}

function normalizeAdCurrency(string $currency): string
{
    $c = strtolower(trim($currency));
    return $c === 'rsd' || $c === 'din' ? 'rsd' : 'eur';
}

/** @return 'fixed'|'negotiable'|'contact' */
function normalizeAdPriceType(string $type): string
{
    $t = strtolower(trim($type));
    if (in_array($t, ['negotiable', 'po_dogovoru', 'dogovor'], true)) {
        return 'negotiable';
    }
    if (in_array($t, ['contact', 'na_kontakt', 'kontakt'], true)) {
        return 'contact';
    }
    return 'fixed';
}

function adCurrency(array $ad): string
{
    return normalizeAdCurrency((string)($ad['currency'] ?? 'eur'));
}

/** @return 'fixed'|'negotiable'|'contact' */
function adPriceType(array $ad): string
{
    $explicit = trim((string)($ad['price_type'] ?? ''));
    if ($explicit !== '') {
        return normalizeAdPriceType($explicit);
    }
    // Stari oglasi bez price_type: 0 = po dogovoru
    return ((float)($ad['price'] ?? 0) <= 0) ? 'negotiable' : 'fixed';
}

function adPriceTypeLabel(array $ad): string
{
    return match (adPriceType($ad)) {
        'negotiable' => 'Po dogovoru',
        'contact' => 'Na kontakt',
        default => 'Fiksno',
    };
}

function formatAdPrice(array $ad): string
{
    $type = adPriceType($ad);
    if ($type === 'negotiable') {
        return 'Cena: po dogovoru';
    }
    if ($type === 'contact') {
        return 'Cena: na kontakt';
    }
    $eur = adPriceEur($ad);
    if ($eur <= 0) {
        return 'Cena: po dogovoru';
    }
    // Na sajtu uvek prikazujemo EUR (RSD unos se pretvara kursom).
    return formatPrice($eur, 'eur');
}

/** Pomoćni prikaz u dinarima, npr. „≈ 99.450 din“. */
function formatAdPriceRsd(array $ad): string
{
    if (isAdPriceOpen($ad)) {
        return '';
    }
    $eur = adPriceEur($ad);
    if ($eur <= 0) {
        return '';
    }
    $rsd = (int)round($eur * eurRsdRate());
    if ($rsd <= 0) {
        return '';
    }
    return '≈ ' . number_format($rsd, 0, ',', '.') . ' din';
}

function isAdPriceOpen(array $ad): bool
{
    return adPriceType($ad) !== 'fixed' || (float)($ad['price'] ?? 0) <= 0;
}

function eurRsdRate(): float
{
    refreshNbsEurRsdRateIfStale(12);
    $settings = siteSettings();
    if (!empty($settings['eur_rsd_auto_nbs'])) {
        $cache = readNbsRateCache();
        if ($cache && ($cache['rate'] ?? 0) > 0) {
            return (float)$cache['rate'];
        }
    }
    $rate = (float)($settings['eur_rsd_rate'] ?? 117);
    return $rate > 0 ? $rate : 117.0;
}

/** EUR ekvivalent za sort/filter/prikaz (0 ako nije fiksna cena). */
function adPriceEur(array $ad): float
{
    if (adPriceType($ad) !== 'fixed') {
        return 0.0;
    }
    $price = (float)($ad['price'] ?? 0);
    if ($price <= 0) {
        return 0.0;
    }
    if (adCurrency($ad) === 'rsd') {
        return $price / eurRsdRate();
    }
    return $price;
}

/** Iznos u EUR ekvivalentu (nezavisno od oglasa). */
function amountToEur(float $amount, string $currency): float
{
    if ($amount <= 0) {
        return 0.0;
    }
    return normalizeAdCurrency($currency) === 'rsd' ? $amount / eurRsdRate() : $amount;
}

/** Soft upozorenje — traži eksplicitnu potvrdu iznad ovoga. */
function warnAdPriceEur(): float
{
    return 2000.0;
}

/** Hard maksimum cene u EUR (sprečava greške tipa dinari u EUR polju). */
function maxAdPriceEur(string $adType = 'telefon'): float
{
    return match ($adType) {
        'servis' => 15000.0,
        'delovi' => 5000.0,
        default => 5000.0,
    };
}

/**
 * Validacija cene oglasa. Vraća poruku greške ili null.
 *
 * @param bool $confirmed korisnik je potvrdio da visoka cena nije greška
 */
function validateAdPrice(float $amount, string $currency, string $adType, bool $confirmed = false): ?string
{
    if ($amount <= 0) {
        return 'Unesi cenu ili označi Po dogovoru.';
    }
    $eur = amountToEur($amount, $currency);
    $max = maxAdPriceEur($adType);
    $maxFmt = number_format($max, 0, ',', '.');
    $amtFmt = number_format($amount, 0, ',', '.');

    if ($eur > $max) {
        if (normalizeAdCurrency($currency) === 'eur' && $amount >= 10000) {
            return "Cena od {$amtFmt} € deluje kao greška (možda si uneo dinare u polje za evre, ili višak nula?). Maksimum je {$maxFmt} €.";
        }
        return "Cena je previsoka (maks. {$maxFmt} €). Proveri iznos i valutu (EUR / RSD).";
    }

    if ($eur > warnAdPriceEur() && !$confirmed) {
        $eurFmt = number_format($eur, 0, ',', '.');
        return "Cena od ~{$eurFmt} € izgleda visoko. Potvrdi ispod da nije greška u kucanju, ili smanji iznos.";
    }

    return null;
}

function getAllAds(): array
{
    $ads = readJsonFile('ads.json');
    usort($ads, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $ads;
}

function getAdById(int $adId): ?array
{
    foreach (getAllAds() as $ad) {
        if ((int)($ad['id'] ?? 0) === $adId) {
            return $ad;
        }
    }
    return null;
}

function getAdsByUserId(int $userId): array
{
    return array_values(array_filter(getAllAds(), static fn($ad) => (int)($ad['created_by'] ?? 0) === $userId));
}

function saveAd(array $payload, ?int $adId = null): int
{
    require_once __DIR__ . '/ads_helpers.php';

    $ads = readJsonFile('ads.json');
    $adType = (string)($payload['ad_type'] ?? 'telefon');
    $payload['ad_type'] = $adType;
    $payload['category'] = categoryFromAdType($adType);
    $payload = normalizeAdDefaults($payload);

    if ($adId !== null) {
        foreach ($ads as &$ad) {
            if ((int)$ad['id'] === $adId) {
                $wasActive = (int)($ad['is_active'] ?? 0) === 1;
                $payload['id'] = $adId;
                $payload['created_by'] = $ad['created_by'] ?? (currentUser()['id'] ?? 1);
                $payload['created_at'] = $ad['created_at'] ?? date('Y-m-d H:i:s');
                $payload['views'] = (int)($ad['views'] ?? $payload['views']);
                $payload['updated_at'] = date('Y-m-d H:i:s');
                $payload['images'] = handleAdImageUploads($adId, $payload['images'] ?? ($ad['images'] ?? []));
                invalidateAdOgImage($adId);
                ensureAdOgImage($payload, true);
                $payload['expires_at'] = $ad['expires_at'] ?? ($payload['expires_at'] ?? null);
                $payload['expiry_warned_at'] = $ad['expiry_warned_at'] ?? ($payload['expiry_warned_at'] ?? null);
                if (!array_key_exists('promoted_until', $payload)) {
                    $payload['promoted_until'] = $ad['promoted_until'] ?? null;
                }
                if (adExpiryEnabled() && empty($payload['expires_at']) && (int)($payload['is_active'] ?? 0) === 1) {
                    $payload['expires_at'] = computeAdExpiresAt((string)$payload['created_at']);
                }
                $ad = $payload;
                writeJsonFile('ads.json', $ads);
                $isActiveNow = (int)($payload['is_active'] ?? 0) === 1;
                if (!$wasActive && $isActiveNow && function_exists('notifySavedSearchesForAd')) {
                    notifySavedSearchesForAd($adId);
                }
                return $adId;
            }
        }
    }

    $maxId = 0;
    foreach ($ads as $ad) {
        $maxId = max($maxId, (int)($ad['id'] ?? 0));
    }

    $newId = $maxId + 1;
    $payload['id'] = $newId;
    $payload['created_by'] = (int)(currentUser()['id'] ?? 1);
    $payload['created_at'] = date('Y-m-d H:i:s');
    $payload['updated_at'] = date('Y-m-d H:i:s');
    $payload['images'] = handleAdImageUploads($newId, $payload['images'] ?? []);
    ensureAdOgImage($payload, true);
    if (adExpiryEnabled()) {
        $payload['expires_at'] = computeAdExpiresAt($payload['created_at']);
        $payload['expiry_warned_at'] = null;
    }

    $ads[] = $payload;
    writeJsonFile('ads.json', $ads);
    if ((int)($payload['is_active'] ?? 0) === 1 && function_exists('notifySavedSearchesForAd')) {
        notifySavedSearchesForAd($newId);
    }
    return $newId;
}

function deleteAdById(int $adId): bool
{
    $ads = readJsonFile('ads.json');
    $before = count($ads);
    $ads = array_values(array_filter($ads, static fn($ad) => (int)($ad['id'] ?? 0) !== $adId));
    writeJsonFile('ads.json', $ads);
    return count($ads) < $before;
}

function getPublicAds(array $filters = []): array
{
    require_once __DIR__ . '/ads_helpers.php';
    require_once __DIR__ . '/search.php';

    $search = trim((string)($filters['q'] ?? ''));
    $brand = trim((string)($filters['brand'] ?? ''));
    $model = trim((string)($filters['model'] ?? ''));
    $types = $filters['types'] ?? [];
    $maxPrice = isset($filters['max_price']) && $filters['max_price'] !== '' ? (float)$filters['max_price'] : null;
    $minPrice = isset($filters['min_price']) && $filters['min_price'] !== '' ? (float)$filters['min_price'] : null;
    $location = trim((string)($filters['location'] ?? ''));
    $condition = trim((string)($filters['condition'] ?? ''));
    $categoryGroup = trim((string)($filters['category_group'] ?? ''));
    $deviceType = trim((string)($filters['device_type'] ?? ''));
    if ($deviceType !== '' && !in_array($deviceType, allowedDeviceTypes(), true)) {
        $deviceType = '';
    }
    $includeSold = !empty($filters['include_sold']);

    $ads = array_filter(getAllAds(), static fn($ad) => (int)($ad['is_active'] ?? 0) === 1);
    if (!$includeSold) {
        $ads = array_filter($ads, static fn($ad) => empty($ad['is_sold']));
    }

    // Istekli TOP → tretiraj kao ne-promovisane u sortiranju (cleanup radi processTopExpirations)
    $ads = array_map(static function ($ad) {
        if (!empty($ad['is_promoted']) && !empty($ad['promoted_until'])) {
            $ts = strtotime((string)$ad['promoted_until']);
            if ($ts !== false && $ts <= time()) {
                $ad['is_promoted'] = 0;
            }
        }
        return $ad;
    }, $ads);

    $tokens = searchTokens($search);
    $relevance = [];
    if ($tokens !== []) {
        $filtered = [];
        foreach ($ads as $ad) {
            $score = scoreAdAgainstTokens($ad, $tokens);
            if ($score < 0) {
                continue;
            }
            $relevance[(int)($ad['id'] ?? 0)] = $score;
            $filtered[] = $ad;
        }
        $ads = $filtered;
    }

    if ($brand !== '') {
        $b = mb_strtolower($brand);
        $ads = array_filter($ads, static fn($ad) => mb_strtolower((string)($ad['brand'] ?? '')) === $b);
    }

    if ($model !== '') {
        $m = mb_strtolower($model);
        $ads = array_filter($ads, static fn($ad) => str_contains(mb_strtolower((string)($ad['model'] ?? '')), $m));
    }

    if ($categoryGroup !== '') {
        $ads = array_filter($ads, static fn($ad) => (string)($ad['category_group'] ?? '') === $categoryGroup);
    }

    if (is_array($types) && $types !== []) {
        $ads = array_filter($ads, static fn($ad) => in_array(getAdType($ad), $types, true));
    }

    if ($deviceType !== '') {
        $ads = array_filter($ads, static function ($ad) use ($deviceType) {
            return getAdType($ad) === 'telefon' && getAdDeviceType($ad) === $deviceType;
        });
    }

    $equipmentGroup = trim((string)($filters['equipment_group'] ?? ''));
    if (in_array($equipmentGroup, ['parts', 'oprema'], true)) {
        $ads = array_filter($ads, static function ($ad) use ($equipmentGroup) {
            if (getAdType($ad) !== 'delovi') {
                return false;
            }
            return adEquipmentGroup($ad) === $equipmentGroup;
        });
    }

    if ($maxPrice !== null || $minPrice !== null) {
        $ads = array_filter($ads, static function ($ad) use ($minPrice, $maxPrice) {
            if (adPriceType($ad) !== 'fixed') {
                return false;
            }
            $eur = adPriceEur($ad);
            if ($minPrice !== null && $eur < $minPrice) {
                return false;
            }
            if ($maxPrice !== null && $eur > $maxPrice) {
                return false;
            }
            return true;
        });
    }

    if ($location !== '') {
        $loc = mb_strtolower($location);
        $ads = array_filter($ads, static fn($ad) => mb_strtolower((string)($ad['location'] ?? '')) === $loc);
    }

    if ($condition !== '') {
        $ads = array_filter($ads, static fn($ad) => (string)($ad['condition_state'] ?? '') === $condition);
    }

    $sort = (string)($filters['sort'] ?? 'newest');
    $ads = array_values($ads);

    if ($tokens !== [] && ($sort === 'newest' || $sort === 'relevance')) {
        usort($ads, static function ($a, $b) use ($relevance) {
            $sa = $relevance[(int)($a['id'] ?? 0)] ?? 0;
            $sb = $relevance[(int)($b['id'] ?? 0)] ?? 0;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            if (!empty($a['is_promoted']) && empty($b['is_promoted'])) {
                return -1;
            }
            if (empty($a['is_promoted']) && !empty($b['is_promoted'])) {
                return 1;
            }
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        return $ads;
    }

    return sortAds($ads, $sort);
}

function getBrands(): array
{
    $brands = [];
    foreach (getPublicAds() as $ad) {
        $brand = trim((string)($ad['brand'] ?? ''));
        if ($brand !== '') {
            $brands[$brand] = true;
        }
    }
    $result = array_keys($brands);
    sort($result);
    return $result;
}

function getDashboardStats(): array
{
    $allAds = getAllAds();
    $total = count($allAds);
    $active = 0;
    $inactive = 0;
    $priceSum = 0.0;
    $priceCount = 0;
    $byType = ['telefon' => 0, 'delovi' => 0, 'servis' => 0];

    foreach ($allAds as $ad) {
        $type = getAdType($ad);
        $byType[$type] = ($byType[$type] ?? 0) + 1;
        $isActive = (int)($ad['is_active'] ?? 0) === 1;
        if ($isActive) {
            $active++;
            $eur = adPriceEur($ad);
            if ($eur > 0) {
                $priceSum += $eur;
                $priceCount++;
            }
        } else {
            $inactive++;
        }
    }

    return [
        'adsTotal' => $total,
        'activeTotal' => $active,
        'inactiveTotal' => $inactive,
        'avgPrice' => $priceCount > 0 ? ($priceSum / $priceCount) : 0.0,
        'latestAds' => array_slice($allAds, 0, 8),
        'byType' => $byType,
        'usersTotal' => count(getUsers()),
        'messagesTotal' => count(getMessages()),
        'openReports' => getOpenReportsCount(),
        'blockedUsers' => countBlockedUsers(),
    ];
}

function getMessages(): array
{
    $messages = readJsonFile('messages.json');
    usort($messages, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $messages;
}

function getMessagesForUser(int $userId): array
{
    return array_values(array_filter(getMessages(), static function ($msg) use ($userId) {
        return (int)($msg['to_user_id'] ?? 0) === $userId || (int)($msg['from_user_id'] ?? 0) === $userId;
    }));
}

function getUnreadMessageCount(int $userId): int
{
    $count = 0;
    foreach (getMessagesForUser($userId) as $msg) {
        if ((int)($msg['to_user_id'] ?? 0) === $userId && empty($msg['is_read'])) {
            $count++;
        }
    }
    return $count;
}

function messageThreadPartnerId(array $msg, int $userId): int
{
    $from = (int)($msg['from_user_id'] ?? 0);
    $to = (int)($msg['to_user_id'] ?? 0);
    if ($from === $userId) {
        return $to;
    }
    if ($to === $userId) {
        return $from;
    }
    return 0;
}

function messageThreadKey(int $adId, int $userA, int $userB): string
{
    return $adId . ':' . min($userA, $userB) . ':' . max($userA, $userB);
}

/** @return list<array{key:string,ad_id:int,partner_id:int,partner_name:string,ad_title:string,last_body:string,last_at:string,unread:int,total:int}> */
function getMessageThreads(int $userId): array
{
    $threads = [];
    foreach (getMessagesForUser($userId) as $msg) {
        $adId = (int)($msg['ad_id'] ?? 0);
        $partnerId = messageThreadPartnerId($msg, $userId);
        if ($adId <= 0 || $partnerId <= 0) {
            continue;
        }
        $key = messageThreadKey($adId, $userId, $partnerId);
        if (!isset($threads[$key])) {
            $partner = findUserById($partnerId);
            $ad = getAdById($adId);
            $threads[$key] = [
                'key' => $key,
                'ad_id' => $adId,
                'partner_id' => $partnerId,
                'partner_name' => (string)($partner['full_name'] ?? $msg['from_name'] ?? 'Korisnik'),
                'ad_title' => (string)($ad['title'] ?? ('Oglas #' . $adId)),
                'last_body' => (string)($msg['body'] ?? ''),
                'last_at' => (string)($msg['created_at'] ?? ''),
                'unread' => 0,
                'total' => 0,
            ];
        }
        $threads[$key]['total']++;
        if ((int)($msg['to_user_id'] ?? 0) === $userId && empty($msg['is_read'])) {
            $threads[$key]['unread']++;
        }
        // messages already sorted newest first; first seen is latest
        if ($threads[$key]['last_at'] === '' || strcmp((string)($msg['created_at'] ?? ''), $threads[$key]['last_at']) > 0) {
            $threads[$key]['last_body'] = (string)($msg['body'] ?? '');
            $threads[$key]['last_at'] = (string)($msg['created_at'] ?? '');
        }
    }

    $list = array_values($threads);
    usort($list, static fn($a, $b) => strcmp((string)$b['last_at'], (string)$a['last_at']));
    return $list;
}

function getThreadMessages(int $userId, int $adId, int $partnerId): array
{
    $messages = array_values(array_filter(getMessagesForUser($userId), static function ($msg) use ($userId, $adId, $partnerId) {
        if ((int)($msg['ad_id'] ?? 0) !== $adId) {
            return false;
        }
        return messageThreadPartnerId($msg, $userId) === $partnerId;
    }));
    usort($messages, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));
    return $messages;
}

function markThreadRead(int $userId, int $adId, int $partnerId): void
{
    $messages = readJsonFile('messages.json');
    $changed = false;
    foreach ($messages as &$msg) {
        if ((int)($msg['ad_id'] ?? 0) !== $adId) {
            continue;
        }
        if ((int)($msg['to_user_id'] ?? 0) !== $userId) {
            continue;
        }
        if ((int)($msg['from_user_id'] ?? 0) !== $partnerId) {
            continue;
        }
        if (empty($msg['is_read'])) {
            $msg['is_read'] = true;
            $changed = true;
        }
    }
    unset($msg);
    if ($changed) {
        writeJsonFile('messages.json', $messages);
    }
}

function deleteThread(int $userId, int $adId, int $partnerId): int
{
    $messages = readJsonFile('messages.json');
    $count = count($messages);
    $messages = array_values(array_filter($messages, static function ($msg) use ($userId, $adId, $partnerId) {
        if ((int)($msg['ad_id'] ?? 0) !== $adId) {
            return true;
        }
        $from = (int)($msg['from_user_id'] ?? 0);
        $to = (int)($msg['to_user_id'] ?? 0);
        $pair = [$from, $to];
        sort($pair);
        $expected = [$userId, $partnerId];
        sort($expected);
        return $pair !== $expected;
    }));
    $deleted = $count - count($messages);
    if ($deleted > 0) {
        writeJsonFile('messages.json', $messages);
    }
    return $deleted;
}

function saveMessage(array $payload): ?int
{
    $from = (int)($payload['from_user_id'] ?? 0);
    $to = (int)($payload['to_user_id'] ?? 0);
    $adId = (int)($payload['ad_id'] ?? 0);
    $body = trim((string)($payload['body'] ?? ''));

    if ($from <= 0 || $to <= 0 || $adId <= 0 || $body === '') {
        return null;
    }
    if ($from === $to) {
        return null;
    }

    $messages = readJsonFile('messages.json');
    $maxId = 0;
    foreach ($messages as $msg) {
        $maxId = max($maxId, (int)($msg['id'] ?? 0));
    }

    $id = $maxId + 1;
    $messages[] = [
        'id' => $id,
        'ad_id' => $adId,
        'from_user_id' => $from,
        'from_name' => (string)($payload['from_name'] ?? ''),
        'from_phone' => (string)($payload['from_phone'] ?? ''),
        'to_user_id' => $to,
        'body' => $body,
        'created_at' => date('Y-m-d H:i:s'),
        'is_read' => false,
    ];
    writeJsonFile('messages.json', $messages);

    $ad = getAdById($adId);
    $adTitle = (string)($ad['title'] ?? ('Oglas #' . $adId));
    $fromName = trim((string)($payload['from_name'] ?? 'Kupac'));
    $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 77) . '…' : $body;
    notifyUser(
        $to,
        'new_message',
        'Nova poruka',
        "{$fromName} ti je poslao poruku za „{$adTitle}”:\n{$preview}",
        '/poruke.php?ad_id=' . $adId . '&with=' . $from
    );

    // First message in thread counts as contact
    $prior = 0;
    foreach ($messages as $msg) {
        if ((int)($msg['ad_id'] ?? 0) !== $adId) {
            continue;
        }
        $pair = [min((int)($msg['from_user_id'] ?? 0), (int)($msg['to_user_id'] ?? 0)), max((int)($msg['from_user_id'] ?? 0), (int)($msg['to_user_id'] ?? 0))];
        if ($pair[0] === min($from, $to) && $pair[1] === max($from, $to)) {
            $prior++;
        }
    }
    if ($prior === 1 && function_exists('bumpAdStat')) {
        bumpAdStat($adId, 'messages_started');
    }

    return $id;
}

function markMessageRead(int $messageId, int $userId): void
{
    $messages = readJsonFile('messages.json');
    foreach ($messages as &$msg) {
        if ((int)($msg['id'] ?? 0) === $messageId && (int)($msg['to_user_id'] ?? 0) === $userId) {
            $msg['is_read'] = true;
            writeJsonFile('messages.json', $messages);
            return;
        }
    }
}

function renderUnreadBadge(int $count): string
{
    if ($count <= 0) {
        return '';
    }
    $label = $count > 99 ? '99+' : (string)$count;
    return '<span class="notif-badge" aria-label="' . h($label) . ' nepročitanih">' . h($label) . '</span>';
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/ads_helpers.php';
require_once __DIR__ . '/ad_form_schema.php';
require_once __DIR__ . '/nbs_rate.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/ratings.php';
require_once __DIR__ . '/guides.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/email_templates.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/promotion.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/trust.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/saved_searches.php';
require_once __DIR__ . '/compare.php';
require_once __DIR__ . '/ad_stats.php';
require_once __DIR__ . '/storefront.php';
require_once __DIR__ . '/shop_catalog.php';
require_once __DIR__ . '/services_directory.php';
require_once __DIR__ . '/listings_directory.php';
require_once __DIR__ . '/facebook_pixel.php';
require_once __DIR__ . '/google_tag.php';
require_once __DIR__ . '/google_analytics.php';
require_once __DIR__ . '/imei_check.php';

ensureJsonDataFiles();
tryRememberLogin();
ensureUploadsDir();
ensureCreditFiles();
checkMaintenanceMode();
requireNotBlocked();
processAdExpirations();
refreshNbsEurRsdRateIfStale(12);
processTopExpirations();

// Pozadinska obrada ne sme da obori zahtev korisnika.
try {
    processSavedSearchAlerts();
} catch (Throwable $e) {
    error_log('processSavedSearchAlerts failed: ' . $e->getMessage());
}
