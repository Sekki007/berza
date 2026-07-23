<?php

declare(strict_types=1);

function shopUrl(string $username): string
{
    $username = trim($username);
    if ($username === '') {
        return '/izlog.php';
    }
    return '/izlog/' . rawurlencode($username);
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

function absoluteUrl(string $path): string
{
    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return $host . $path;
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
    $ratings = readJsonFile('ratings.json');
    usort($ratings, static fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $ratings;
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

    return [
        'positive' => $positive,
        'negative' => $negative,
        'count' => $positive + $negative,
        'avg' => 0.0,
    ];
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

function saleIntentKeywords(): array
{
    return [
        'dogovoreno', 'dogovorili', 'dogovor', 'kupio', 'kupila', 'kupili',
        'prodao', 'prodala', 'prodali', 'preuzeo', 'preuzela', 'preuzeto',
        'stiglo', 'stigla', 'isporučeno', 'isporuceno', 'dostavljeno', 'dostavljena',
        'plaćeno', 'placeno', 'platila', 'platio', 'uplaćeno', 'uplaceno',
        'hvala na', 'sve ok', 'sve u redu', 'uspešno', 'uspesno', 'primio', 'primila',
        'završeno', 'zavrseno', 'dogovorili smo', 'vidimo se', 'javim se kad',
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
 * KP-style pravila za ocenu.
 * @return array{allowed:bool,reasons:list<string>,eligible:list<array>,rules:list<string>}
 */
function getRatingEligibility(int $fromUserId, int $sellerId): array
{
    $rules = [
        'ako ste se nedavno registrovali (manje od 3 dana),',
        'ako se iz Vaše konverzacije porukama ne može utvrditi da je do kupoprodaje došlo,',
        'ako ste ga već ocenili pre manje od 7 dana,',
        'ako je konverzacija starija od 30 dana,',
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
    if ($createdAt === false || (time() - $createdAt) < 3 * 86400) {
        $reasons[] = 'Nedavno ste se registrovali — ocena je moguća nakon 3 dana od registracije.';
    }

    $lastRating = getUserRatingForSeller($sellerId, $fromUserId);
    if ($lastRating) {
        $ratedAt = strtotime((string)($lastRating['updated_at'] ?? $lastRating['created_at'] ?? ''));
        if ($ratedAt !== false && (time() - $ratedAt) < 7 * 86400) {
            $daysLeft = (int)ceil((7 * 86400 - (time() - $ratedAt)) / 86400);
            $reasons[] = 'Već ste ocenili ovog korisnika pre manje od 7 dana' . ($daysLeft > 0 ? " (ponovo za ~{$daysLeft} dana)" : '') . '.';
        }
    }

    $conversations = getConversationsBetweenUsers($fromUserId, $sellerId);
    $eligible = [];
    $hadAnyThread = false;
    $hadTooOld = false;
    $hadNoSale = false;
    $hadAlreadyRated = false;
    $hadOneSided = false;

    foreach ($conversations as $thread) {
        $hadAnyThread = true;
        $lastTs = strtotime((string)$thread['last_at']);
        if ($lastTs === false || (time() - $lastTs) > 30 * 86400) {
            $hadTooOld = true;
            continue;
        }
        if (!$thread['both_sides'] || $thread['message_count'] < 3) {
            $hadOneSided = true;
            continue;
        }
        if (!$thread['suggests_sale']) {
            $hadNoSale = true;
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
            $reasons[] = 'Konverzacija je starija od 30 dana.';
        }
        if ($eligible === [] && $hadNoSale) {
            $reasons[] = 'Iz konverzacije se ne može utvrditi da je do kupoprodaje došlo (npr. dogovor, preuzimanje, plaćanje).';
        }
        if ($eligible === [] && $hadOneSided && !$hadNoSale && !$hadTooOld) {
            $reasons[] = 'Konverzacija mora imati poruke sa obe strane (najmanje 3 poruke ukupno).';
        }
    }

    // Hard block if account too new or 7-day cooldown, even if eligible conversations exist
    $hardBlock = false;
    foreach ($reasons as $r) {
        if (str_contains($r, 'Nedavno ste se registrovali') || str_contains($r, 'manje od 7 dana')) {
            $hardBlock = true;
            break;
        }
    }

    $allowed = !$hardBlock && $eligible !== [];

    // Clean duplicate-ish reasons when allowed
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

/** KP-style: 👍 347   👎 0 — optional $reviewsBaseUrl opens komentare (npr. /izlog.php?u=x) */
function renderReputation(array $summary, ?string $reviewsBaseUrl = null): string
{
    $pos = (int)($summary['positive'] ?? 0);
    $neg = (int)($summary['negative'] ?? 0);
    $count = $pos + $neg;

    if ($count === 0) {
        if ($reviewsBaseUrl) {
            return '<a class="rep-thumbs rep-thumbs-link" href="' . h($reviewsBaseUrl . '#ocene') . '"><span class="rating-meta">Još nema ocena</span></a>';
        }
        return '<span class="rep-thumbs"><span class="rating-meta">Još nema ocena</span></span>';
    }

    $upInner = '<span class="rep-thumb-icon">👍</span> ' . $pos;
    $downInner = '<span class="rep-thumb-icon">👎</span> ' . $neg;

    if ($reviewsBaseUrl) {
        $up = '<a class="rep-thumb rep-thumb-up" href="' . h($reviewsBaseUrl . '#ocene-positive') . '" title="Pogledaj pozitivne ocene">' . $upInner . '</a>';
        $down = '<a class="rep-thumb rep-thumb-down" href="' . h($reviewsBaseUrl . '#ocene-negative') . '" title="Pogledaj negativne ocene">' . $downInner . '</a>';
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
