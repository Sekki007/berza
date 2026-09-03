<?php

declare(strict_types=1);

function reservedShopSlugs(): array
{
    return [
        'admin', 'api', 'assets', 'izlog', 'usluge', 'servisi', 'oglas', 'oglasi', 'login', 'register',
        'nalog', 'poruke', 'favorites', 'dashboard', 'report', 'sitemap', 'robots', 'uploads',
        'forgot-password', 'reset-password', 'verify-phone', 'verify-email', 'uporedi',
        'kako-radi', 'privatnost', 'uslovi', 'index', 'www', 'mail', 'podrska', 'support', 'kupitelefon',
        'prodavnice', 'grad', 'vodic', 'vodici', 'blog', 'ocene',
    ];
}

function normalizeShopSlug(string $raw): string
{
    $map = [
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
        'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'dj',
    ];
    $raw = strtr(trim($raw), $map);
    $raw = mb_strtolower($raw);
    $raw = preg_replace('/[^a-z0-9-]+/', '-', $raw) ?? '';
    $raw = preg_replace('/-+/', '-', $raw) ?? '';
    $raw = trim($raw, '-');
    if (strlen($raw) > 40) {
        $raw = rtrim(substr($raw, 0, 40), '-');
    }
    return $raw;
}

function isValidShopSlug(string $slug): bool
{
    if ($slug === '' || strlen($slug) < 3 || strlen($slug) > 40) {
        return false;
    }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return false;
    }
    return !in_array($slug, reservedShopSlugs(), true);
}

function findUserByShopSlug(string $slug): ?array
{
    $slug = normalizeShopSlug($slug);
    if ($slug === '') {
        return null;
    }
    foreach (getUsers() as $user) {
        $userSlug = normalizeShopSlug((string)($user['shop_slug'] ?? ''));
        if ($userSlug !== '' && $userSlug === $slug) {
            return $user;
        }
    }
    return null;
}

function shopSlugTaken(string $slug, int $exceptUserId = 0): bool
{
    $slug = normalizeShopSlug($slug);
    if ($slug === '') {
        return true;
    }
    $bySlug = findUserByShopSlug($slug);
    if ($bySlug && (int)($bySlug['id'] ?? 0) !== $exceptUserId) {
        return true;
    }
    // Ne dozvoli da slug poklopi tuđe login username (stari linkovi)
    $byUser = findUserByUsername($slug);
    if ($byUser && (int)($byUser['id'] ?? 0) !== $exceptUserId) {
        return true;
    }
    return false;
}

function allocateUniqueShopSlug(string $base, int $exceptUserId = 0): string
{
    $base = normalizeShopSlug($base);
    if ($base === '' || !isValidShopSlug($base)) {
        $base = 'izlog';
    }
    if (in_array($base, reservedShopSlugs(), true)) {
        $base = 'izlog';
    }
    $candidate = $base;
    $i = 2;
    while (shopSlugTaken($candidate, $exceptUserId) || !isValidShopSlug($candidate)) {
        $suffix = '-' . $i;
        $trim = 40 - strlen($suffix);
        $candidate = rtrim(substr($base, 0, max(1, $trim)), '-') . $suffix;
        $i++;
        if ($i > 9999) {
            $candidate = 'izlog-' . $exceptUserId;
            break;
        }
    }
    return $candidate;
}

/**
 * Javni slug izloga. Ako nedostaje, kreira se iz username-a (jednom).
 */
function ensureUserShopSlug(int $userId): string
{
    $user = findUserById($userId);
    if (!$user) {
        return '';
    }
    $existing = normalizeShopSlug((string)($user['shop_slug'] ?? ''));
    if ($existing !== '' && isValidShopSlug($existing) && !shopSlugTaken($existing, $userId)) {
        if ((string)($user['shop_slug'] ?? '') !== $existing) {
            patchUser($userId, ['shop_slug' => $existing]);
        }
        return $existing;
    }
    $base = normalizeShopSlug((string)($user['username'] ?? ''));
    if ($base === '') {
        $base = 'izlog-' . $userId;
    }
    $slug = allocateUniqueShopSlug($base, $userId);
    patchUser($userId, ['shop_slug' => $slug]);
    return $slug;
}

function userShopSlug(array $user): string
{
    $id = (int)($user['id'] ?? 0);
    $existing = normalizeShopSlug((string)($user['shop_slug'] ?? ''));
    if ($existing !== '' && isValidShopSlug($existing)) {
        return $existing;
    }
    if ($id > 0) {
        return ensureUserShopSlug($id);
    }
    return normalizeShopSlug((string)($user['username'] ?? ''));
}

function shopUrlForUser(array $user): string
{
    $slug = userShopSlug($user);
    if ($slug === '') {
        return '/izlog.php';
    }
    return '/izlog/' . rawurlencode($slug);
}

/**
 * Stranica sa ocenama.
 * Koristi /izlog_ocene.php?u=slug jer pretty /izlog/slug/ocene zahteva poseban Nginx rewrite
 * koji često „proguta“ kategorija-pravilo.
 */
function shopReviewsUrl(array $user, string $filter = ''): string
{
    $slug = userShopSlug($user);
    if ($slug === '') {
        return '/izlog_ocene.php';
    }
    $url = '/izlog_ocene.php?u=' . rawurlencode($slug);
    if ($filter === 'positive' || $filter === 'negative') {
        $url .= '&filter=' . $filter;
    }
    return $url;
}

/** @deprecated Prefer shopUrlForUser(); accepts slug or legacy username. */
function shopUrl(string $slugOrUsername): string
{
    $slugOrUsername = trim($slugOrUsername);
    if ($slugOrUsername === '') {
        return '/izlog.php';
    }
    $user = findUserByShopSlug($slugOrUsername) ?? findUserByUsername($slugOrUsername);
    if ($user) {
        return shopUrlForUser($user);
    }
    return '/izlog/' . rawurlencode(normalizeShopSlug($slugOrUsername) ?: $slugOrUsername);
}

function resolveShopUserFromParam(string $param): ?array
{
    $param = trim($param);
    if ($param === '') {
        return null;
    }
    $bySlug = findUserByShopSlug($param);
    if ($bySlug) {
        return $bySlug;
    }
    return findUserByUsername($param);
}

function slugifyTitle(string $title): string
{
    $map = [
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
        'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'dj',
    ];
    $title = strtr($title, $map);
    $title = mb_strtolower($title);
    $title = preg_replace('/[^a-z0-9]+/u', '-', $title) ?? '';
    $title = trim($title, '-');
    if (strlen($title) > 60) {
        $title = rtrim(substr($title, 0, 60), '-');
    }
    return $title;
}

function adUrl(array $ad): string
{
    $id = (int)($ad['id'] ?? 0);
    if ($id <= 0) {
        return '/index.php';
    }
    $slug = slugifyTitle((string)($ad['title'] ?? ''));
    return '/oglas/' . $id . ($slug !== '' ? '-' . $slug : '');
}

/** Statički asset sa cache-busting verzijom iz filemtime. */
function assetUrl(string $path): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    $file = dirname(__DIR__) . '/public' . $path;
    $stamp = is_file($file) ? (string)filemtime($file) : (string)time();
    return $path . '?v=' . $stamp;
}

function absoluteUrl(string $path): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return appBaseUrl() . $path;
}

function getPublicAdsByUserId(int $userId, bool $includeSold = true): array
{
    $ads = array_filter(getAdsByUserId($userId), static function ($ad) use ($includeSold) {
        if ((int)($ad['is_active'] ?? 0) !== 1) {
            return false;
        }
        if (!$includeSold && !empty($ad['is_sold'])) {
            return false;
        }
        return true;
    });

    return array_values($ads);
}

function getSellerShopName(array $user, array $ads = []): string
{
    $shop = trim((string)($user['shop_name'] ?? ''));
    if ($shop !== '') {
        return $shop;
    }

    // Firma: javno prikazuj naziv firme, ne lično ime.
    if (function_exists('userAccountType') && userAccountType($user) === 'business') {
        foreach ($ads as $ad) {
            $fromAd = trim((string)($ad['shop_name'] ?? ''));
            if ($fromAd !== '') {
                return $fromAd;
            }
        }
        return 'Firma';
    }

    foreach ($ads as $ad) {
        $fromAd = trim((string)($ad['shop_name'] ?? ''));
        if ($fromAd !== '') {
            return $fromAd;
        }
    }

    return (string)($user['full_name'] ?? 'Prodavac');
}

function getAllRatings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $ratings = readJsonFile('ratings.json');
    usort($ratings, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    $cache = $ratings;
    return $cache;
}

function normalizeRatingVote($score): string
{
    if ($score === 'positive' || $score === 1 || $score === '1' || (is_numeric($score) && (int)$score >= 4)) {
        return 'positive';
    }
    if ($score === 'negative' || $score === -1 || $score === '-1' || (is_numeric($score) && (int)$score > 0 && (int)$score <= 2)) {
        return 'negative';
    }
    // Legacy 3 stars → treat as positive
    if (is_numeric($score) && (int)$score === 3) {
        return 'positive';
    }
    return '';
}

function getSellerRatings(int $sellerId): array
{
    return array_values(array_filter(
        getAllRatings(),
        static fn($r) => (int)($r['seller_id'] ?? 0) === $sellerId
    ));
}

function getSellerRatingSummary(int $sellerId): array
{
    static $summaryCache = [];
    if (isset($summaryCache[$sellerId])) {
        return $summaryCache[$sellerId];
    }

    $positive = 0;
    $negative = 0;

    foreach (getSellerRatings($sellerId) as $rating) {
        $vote = normalizeRatingVote($rating['vote'] ?? $rating['score'] ?? '');
        if ($vote === 'positive') {
            $positive++;
        } elseif ($vote === 'negative') {
            $negative++;
        }
    }

    $summaryCache[$sellerId] = [
        'positive' => $positive,
        'negative' => $negative,
        'count' => $positive + $negative,
        'avg' => 0.0,
    ];
    return $summaryCache[$sellerId];
}

function getUserRatingsForSeller(int $sellerId, int $fromUserId): array
{
    return array_values(array_filter(
        getSellerRatings($sellerId),
        static fn($r) => (int)($r['from_user_id'] ?? 0) === $fromUserId
    ));
}

function getUserRatingForSeller(int $sellerId, int $fromUserId): ?array
{
    $ratings = getUserRatingsForSeller($sellerId, $fromUserId);
    return $ratings[0] ?? null;
}

function ratingConversationKey(int $userA, int $userB, int $adId): string
{
    $min = min($userA, $userB);
    $max = max($userA, $userB);
    return $min . '-' . $max . '-ad' . $adId;
}

function ratingAccountMinAgeDays(): int
{
    return 1;
}

function ratingCooldownDays(): int
{
    return 3;
}

function ratingConversationMaxAgeDays(): int
{
    return 60;
}

function ratingMinMessageCount(): int
{
    return 2;
}

/** Bez ključnih reči — dovoljno obostrane poruke za ocenu. */
function ratingMinMessagesWithoutSaleHint(): int
{
    return 4;
}

function saleIntentKeywords(): array
{
    return [
        // dogovor / kupovina
        'dogovoreno', 'dogovorili', 'dogovor', 'dogovaramo', 'dogovorimo',
        'kupio', 'kupila', 'kupili', 'kupujem', 'kupovina', 'kupovinu',
        'prodao', 'prodala', 'prodali', 'prodajem', 'prodaja',
        'uzeo', 'uzela', 'uzeli', 'uzeto', 'uzeti', 'uzimam',
        'rezervišem', 'rezervisem', 'rezervisano', 'rezervacija',
        // preuzimanje / dostava
        'preuzeo', 'preuzela', 'preuzeto', 'preuzeti', 'preuzimam', 'preuzimanje',
        'stiglo', 'stigla', 'stigao', 'isporučeno', 'isporuceno',
        'dostavljeno', 'dostavljena', 'dostava', 'šaljem', 'saljem', 'pošaljem', 'posaljem',
        'dolazim', 'dođem', 'dodjem', 'vidimo se', 'vidimo', 'sastanak',
        'lično', 'licno', 'kod mene', 'kod tebe', 'adresa', 'lokacija',
        // plaćanje
        'plaćeno', 'placeno', 'platila', 'platio', 'plaćam', 'placam',
        'uplaćeno', 'uplaceno', 'uplatio', 'uplatila', 'uplatim',
        'keš', 'kes', 'novac', 'dinara', 'din ', ' eur', 'euro', 'rsd',
        // potvrda da je sve prošlo
        'hvala na', 'hvala', 'sve ok', 'sve okej', 'sve u redu', 'sve super',
        'uspešno', 'uspesno', 'primio', 'primila', 'završeno', 'zavrseno',
        'gotovo', 'urađeno', 'uradjeno', 'zadovoljan', 'zadovoljna',
        'super', 'odlično', 'odlicno', 'preporuka', 'preporučujem', 'preporucujem',
        'javim se kad', 'javljaću', 'javljacu', 'čekam te', 'cekam te',
        'može dogovor', 'moze dogovor', 'ok je', 'sve okej',
    ];
}

function conversationSuggestsSale(array $messages): bool
{
    $text = '';
    foreach ($messages as $msg) {
        $text .= ' ' . mb_strtolower((string)($msg['body'] ?? ''));
    }
    foreach (saleIntentKeywords() as $keyword) {
        if (str_contains($text, $keyword)) {
            return true;
        }
    }
    return false;
}

function conversationEligibleForRating(array $thread): bool
{
    if (empty($thread['both_sides'])) {
        return false;
    }
    $count = (int)($thread['message_count'] ?? 0);
    if ($count < ratingMinMessageCount()) {
        return false;
    }
    if (!empty($thread['suggests_sale'])) {
        return true;
    }
    // Mlad sajt: dovoljno obostrane komunikacije i bez eksplicitnog "kupio/prodao"
    return $count >= ratingMinMessagesWithoutSaleHint();
}

/**
 * Konverzacije između dva korisnika grupisane po oglasu.
 * @return list<array{ad_id:int,key:string,messages:array,last_at:string,from_count:int,to_count:int,suggests_sale:bool}>
 */
function getConversationsBetweenUsers(int $userA, int $userB): array
{
    $threads = [];
    foreach (getMessages() as $msg) {
        $from = (int)($msg['from_user_id'] ?? 0);
        $to = (int)($msg['to_user_id'] ?? 0);
        $pair = [$from, $to];
        sort($pair);
        if ($pair !== [min($userA, $userB), max($userA, $userB)]) {
            continue;
        }
        if ($from <= 0 || $to <= 0) {
            continue;
        }
        $adId = (int)($msg['ad_id'] ?? 0);
        if ($adId <= 0) {
            continue;
        }
        if (!isset($threads[$adId])) {
            $threads[$adId] = [];
        }
        $threads[$adId][] = $msg;
    }

    $result = [];
    foreach ($threads as $adId => $messages) {
        usort($messages, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));
        $fromA = 0;
        $fromB = 0;
        foreach ($messages as $msg) {
            if ((int)($msg['from_user_id'] ?? 0) === $userA) {
                $fromA++;
            }
            if ((int)($msg['from_user_id'] ?? 0) === $userB) {
                $fromB++;
            }
        }
        $last = (string)($messages[count($messages) - 1]['created_at'] ?? '');
        $result[] = [
            'ad_id' => (int)$adId,
            'key' => ratingConversationKey($userA, $userB, (int)$adId),
            'messages' => $messages,
            'message_count' => count($messages),
            'last_at' => $last,
            'from_count' => $fromA,
            'other_count' => $fromB,
            'both_sides' => $fromA > 0 && $fromB > 0,
            'suggests_sale' => conversationSuggestsSale($messages),
        ];
    }

    usort($result, static fn($a, $b) => strcmp((string)$b['last_at'], (string)$a['last_at']));
    return $result;
}

function hasRatedConversation(int $sellerId, int $fromUserId, string $conversationKey): bool
{
    foreach (getUserRatingsForSeller($sellerId, $fromUserId) as $rating) {
        if (($rating['conversation_key'] ?? '') === $conversationKey) {
            return true;
        }
        // Legacy: same ad_id without key
        if (empty($rating['conversation_key']) && (int)($rating['ad_id'] ?? 0) > 0) {
            $legacyKey = ratingConversationKey($sellerId, $fromUserId, (int)$rating['ad_id']);
            if ($legacyKey === $conversationKey) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Pravila za ocenu (ublaženo za mlad marketplace).
 * @return array{allowed:bool,reasons:list<string>,eligible:list<array>,rules:list<string>}
 */
function getRatingEligibility(int $fromUserId, int $sellerId): array
{
    $minAge = ratingAccountMinAgeDays();
    $cooldown = ratingCooldownDays();
    $maxAge = ratingConversationMaxAgeDays();
    $minMsgs = ratingMinMessageCount();
    $minMsgsNoHint = ratingMinMessagesWithoutSaleHint();

    $rules = [
        "ako ste se nedavno registrovali (manje od {$minAge} " . ($minAge === 1 ? 'dan' : 'dana') . '),',
        'ako nemate obostranu konverzaciju porukama vezanu za oglas,',
        "ako ste ga već ocenili pre manje od {$cooldown} dana,",
        "ako je konverzacija starija od {$maxAge} dana,",
        'ako ste korisnika već ocenili iz iste konverzacije.',
    ];

    $reasons = [];
    if ($fromUserId <= 0) {
        return ['allowed' => false, 'reasons' => ['Morate biti prijavljeni.'], 'eligible' => [], 'rules' => $rules];
    }
    if ($fromUserId === $sellerId) {
        return ['allowed' => false, 'reasons' => ['Ne možete oceniti sebe.'], 'eligible' => [], 'rules' => $rules];
    }

    $fromUser = findUserById($fromUserId);
    if (!$fromUser) {
        return ['allowed' => false, 'reasons' => ['Korisnik nije pronađen.'], 'eligible' => [], 'rules' => $rules];
    }

    $createdAt = strtotime((string)($fromUser['created_at'] ?? ''));
    if ($createdAt === false || (time() - $createdAt) < $minAge * 86400) {
        $reasons[] = $minAge === 1
            ? 'Nedavno ste se registrovali — ocena je moguća nakon 1 dana od registracije.'
            : "Nedavno ste se registrovali — ocena je moguća nakon {$minAge} dana od registracije.";
    }

    $lastRating = getUserRatingForSeller($sellerId, $fromUserId);
    if ($lastRating) {
        $ratedAt = strtotime((string)($lastRating['updated_at'] ?? $lastRating['created_at'] ?? ''));
        if ($ratedAt !== false && (time() - $ratedAt) < $cooldown * 86400) {
            $daysLeft = (int)ceil(($cooldown * 86400 - (time() - $ratedAt)) / 86400);
            $reasons[] = "Već ste ocenili ovog korisnika pre manje od {$cooldown} dana" . ($daysLeft > 0 ? " (ponovo za ~{$daysLeft} dana)" : '') . '.';
        }
    }

    $conversations = getConversationsBetweenUsers($fromUserId, $sellerId);
    $eligible = [];
    $hadAnyThread = false;
    $hadTooOld = false;
    $hadTooShort = false;
    $hadAlreadyRated = false;

    foreach ($conversations as $thread) {
        $hadAnyThread = true;
        $lastTs = strtotime((string)$thread['last_at']);
        if ($lastTs === false || (time() - $lastTs) > $maxAge * 86400) {
            $hadTooOld = true;
            continue;
        }
        if (!$thread['both_sides'] || (int)$thread['message_count'] < $minMsgs) {
            $hadTooShort = true;
            continue;
        }
        if (!conversationEligibleForRating($thread)) {
            $hadTooShort = true;
            continue;
        }
        if (hasRatedConversation($sellerId, $fromUserId, $thread['key'])) {
            $hadAlreadyRated = true;
            continue;
        }
        $ad = getAdById((int)$thread['ad_id']);
        $eligible[] = [
            'ad_id' => (int)$thread['ad_id'],
            'key' => $thread['key'],
            'title' => $ad ? (string)$ad['title'] : ('Oglas #' . $thread['ad_id']),
            'last_at' => $thread['last_at'],
            'message_count' => $thread['message_count'],
        ];
    }

    if (!$hadAnyThread) {
        $reasons[] = 'Nemate konverzaciju porukama sa ovim korisnikom u vezi oglasa.';
    } else {
        if ($eligible === [] && $hadAlreadyRated) {
            $reasons[] = 'Već ste ocenili korisnika iz iste konverzacije.';
        }
        if ($eligible === [] && $hadTooOld && !$hadAlreadyRated) {
            $reasons[] = "Konverzacija je starija od {$maxAge} dana.";
        }
        if ($eligible === [] && $hadTooShort && !$hadAlreadyRated) {
            $reasons[] = "Treba obostrana konverzacija (najmanje {$minMsgs} poruke). Sa {$minMsgsNoHint}+ poruka ocena je dostupna i bez eksplicitnog dogovora u tekstu.";
        }
    }

    // Hard block if account too new or cooldown, even if eligible conversations exist
    $hardBlock = false;
    foreach ($reasons as $r) {
        if (str_contains($r, 'Nedavno ste se registrovali') || str_contains($r, 'manje od ' . $cooldown . ' dana')) {
            $hardBlock = true;
            break;
        }
    }

    $allowed = !$hardBlock && $eligible !== [];

    if ($allowed) {
        $reasons = [];
    } else {
        $reasons = array_values(array_unique($reasons));
    }

    return [
        'allowed' => $allowed,
        'reasons' => $reasons,
        'eligible' => $eligible,
        'rules' => $rules,
    ];
}

function saveSellerRating(int $sellerId, int $fromUserId, string $vote, string $comment = '', ?int $adId = null, ?string $conversationKey = null): bool
{
    if ($sellerId <= 0 || $fromUserId <= 0 || $sellerId === $fromUserId) {
        return false;
    }

    $vote = normalizeRatingVote($vote);
    if ($vote === '') {
        return false;
    }
    if (!findUserById($sellerId) || !findUserById($fromUserId)) {
        return false;
    }

    $eligibility = getRatingEligibility($fromUserId, $sellerId);
    if (!$eligibility['allowed'] || $eligibility['eligible'] === []) {
        return false;
    }

    $chosen = null;
    foreach ($eligibility['eligible'] as $thread) {
        if ($conversationKey !== null && $conversationKey !== '' && $thread['key'] === $conversationKey) {
            $chosen = $thread;
            break;
        }
        if ($adId !== null && (int)$thread['ad_id'] === $adId) {
            $chosen = $thread;
            break;
        }
    }
    if ($chosen === null) {
        $chosen = $eligibility['eligible'][0];
    }

    $conversationKey = (string)$chosen['key'];
    $adId = (int)$chosen['ad_id'];

    if (hasRatedConversation($sellerId, $fromUserId, $conversationKey)) {
        return false;
    }

    $comment = mb_substr(trim($comment), 0, 500);
    $ratings = readJsonFile('ratings.json');
    $maxId = 0;
    foreach ($ratings as $rating) {
        $maxId = max($maxId, (int)($rating['id'] ?? 0));
    }

    $ratings[] = [
        'id' => $maxId + 1,
        'seller_id' => $sellerId,
        'from_user_id' => $fromUserId,
        'vote' => $vote,
        'score' => $vote === 'positive' ? 1 : -1,
        'comment' => $comment,
        'ad_id' => $adId,
        'conversation_key' => $conversationKey,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    writeJsonFile('ratings.json', $ratings);
    return true;
}

/** KP-style: 👍 347   👎 0 — $reviewsBaseUrl = shopReviewsUrl() ili /izlog/slug */
function renderReputation(array $summary, ?string $reviewsBaseUrl = null): string
{
    $pos = (int)($summary['positive'] ?? 0);
    $neg = (int)($summary['negative'] ?? 0);
    $count = $pos + $neg;

    $allUrl = null;
    $posUrl = null;
    $negUrl = null;

    if ($reviewsBaseUrl !== null && $reviewsBaseUrl !== '') {
        if (str_contains($reviewsBaseUrl, 'izlog_ocene.php')) {
            $allUrl = preg_replace('/([?&])filter=[^&]*/', '$1', $reviewsBaseUrl) ?? $reviewsBaseUrl;
            $allUrl = preg_replace('/\?&/', '?', $allUrl) ?? $allUrl;
            $allUrl = rtrim($allUrl, '?&');
            $sep = str_contains($allUrl, '?') ? '&' : '?';
            $posUrl = $allUrl . $sep . 'filter=positive';
            $negUrl = $allUrl . $sep . 'filter=negative';
        } elseif (preg_match('#/izlog/([^/?#]+)#', $reviewsBaseUrl, $m)) {
            $slug = rawurldecode($m[1]);
            $allUrl = '/izlog_ocene.php?u=' . rawurlencode($slug);
            $posUrl = $allUrl . '&filter=positive';
            $negUrl = $allUrl . '&filter=negative';
        } else {
            $allUrl = $reviewsBaseUrl;
            $sep = str_contains($allUrl, '?') ? '&' : '?';
            $posUrl = $allUrl . $sep . 'filter=positive';
            $negUrl = $allUrl . $sep . 'filter=negative';
        }
    }

    if ($count === 0) {
        if ($allUrl) {
            return '<a class="rep-thumbs rep-thumbs-link" href="' . h($allUrl) . '"><span class="rating-meta">Još nema ocena</span></a>';
        }
        return '<span class="rep-thumbs"><span class="rating-meta">Još nema ocena</span></span>';
    }

    $upInner = '<span class="rep-thumb-icon">👍</span> ' . $pos;
    $downInner = '<span class="rep-thumb-icon">👎</span> ' . $neg;

    if ($posUrl && $negUrl) {
        $up = '<a class="rep-thumb rep-thumb-up" href="' . h($posUrl) . '" title="Pozitivne ocene">' . $upInner . '</a>';
        $down = '<a class="rep-thumb rep-thumb-down" href="' . h($negUrl) . '" title="Negativne ocene">' . $downInner . '</a>';
    } else {
        $up = '<span class="rep-thumb rep-thumb-up">' . $upInner . '</span>';
        $down = '<span class="rep-thumb rep-thumb-down">' . $downInner . '</span>';
    }

    return '<span class="rep-thumbs" title="Pozitivne / negativne ocene">' . $up . $down . '</span>';
}

/** @deprecated Use renderReputation() */
function renderStars(float $avg, int $count = 0): string
{
    if ($count <= 0 && $avg <= 0) {
        return renderReputation(['positive' => 0, 'negative' => 0]);
    }
    return renderReputation(['positive' => (int)$count, 'negative' => 0]);
}
