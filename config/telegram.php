<?php

declare(strict_types=1);

function telegramEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('TELEGRAM_ENABLED', 'false')));
    if (!in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
        return false;
    }
    return telegramBotToken() !== '';
}

function telegramBotToken(): string
{
    return trim((string)envValue('TELEGRAM_BOT_TOKEN', ''));
}

function telegramBotUsername(): string
{
    return ltrim(trim((string)envValue('TELEGRAM_BOT_USERNAME', '')), '@');
}

function telegramWebhookSecret(): string
{
    return trim((string)envValue('TELEGRAM_WEBHOOK_SECRET', ''));
}

function telegramLinkTtlMinutes(): int
{
    return max(5, (int)envValue('TELEGRAM_LINK_TTL_MIN', '15'));
}

function telegramLinkCodeLength(): int
{
    return max(6, min(10, (int)envValue('TELEGRAM_LINK_CODE_LEN', '7')));
}

function telegramChannelId(): string
{
    return trim((string)envValue('TELEGRAM_CHANNEL_ID', ''));
}

function telegramChannelUsername(): string
{
    return ltrim(trim((string)envValue('TELEGRAM_CHANNEL_USERNAME', '')), '@');
}

function telegramChannelChatId(): string
{
    $id = telegramChannelId();
    if ($id !== '') {
        return $id;
    }
    $user = telegramChannelUsername();
    return $user !== '' ? ('@' . $user) : '';
}

function telegramChannelUrl(): string
{
    $user = telegramChannelUsername();
    if ($user !== '') {
        return 'https://t.me/' . $user;
    }
    return '';
}

function telegramWelcomeEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('TELEGRAM_WELCOME_ENABLED', 'true')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function telegramPostAdsEnabled(): bool
{
    $flag = strtolower(trim((string)envValue('TELEGRAM_POST_ADS_ENABLED', 'true')));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function telegramWelcomeDeleteSeconds(): int
{
    $settings = function_exists('siteSettings') ? siteSettings() : [];
    if (isset($settings['telegram_welcome_delete_sec'])) {
        return max(0, (int)$settings['telegram_welcome_delete_sec']);
    }
    return max(0, (int)envValue('TELEGRAM_WELCOME_DELETE_SEC', '45'));
}

function telegramApiUrl(string $method): string
{
    return 'https://api.telegram.org/bot' . telegramBotToken() . '/' . ltrim($method, '/');
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,result?:array,description?:string}
 */
function telegramApiRequest(string $method, array $payload = []): array
{
    if (!telegramEnabled() || !function_exists('curl_init')) {
        return ['ok' => false, 'description' => 'Telegram nije podešen ili cURL nedostaje.'];
    }

    $ch = curl_init(telegramApiUrl($method));
    if ($ch === false) {
        return ['ok' => false, 'description' => 'curl_init failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'description' => $curlErr !== '' ? $curlErr : 'Prazan odgovor Telegram API-ja.'];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'description' => 'Neispravan JSON odgovor.'];
    }
    if (empty($decoded['ok'])) {
        return [
            'ok' => false,
            'description' => trim((string)($decoded['description'] ?? 'Telegram API greška')),
        ];
    }
    return [
        'ok' => true,
        'result' => is_array($decoded['result'] ?? null) ? $decoded['result'] : [],
    ];
}

function generateTelegramLinkCode(int $length): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function startTelegramLink(int $userId): ?array
{
    if ($userId <= 0 || !telegramEnabled()) {
        return null;
    }
    $user = findUserById($userId);
    if (!$user) {
        return null;
    }

    $expiresAt = date('Y-m-d H:i:s', time() + telegramLinkTtlMinutes() * 60);
    $code = generateTelegramLinkCode(telegramLinkCodeLength());
    for ($i = 0; $i < 6; $i++) {
        $exists = false;
        foreach (getUsers() as $row) {
            if (strcasecmp((string)($row['telegram_link_code'] ?? ''), $code) === 0) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            break;
        }
        $code = generateTelegramLinkCode(telegramLinkCodeLength());
    }

    patchUser($userId, [
        'telegram_link_code' => $code,
        'telegram_link_expires_at' => $expiresAt,
    ]);

    $botUser = telegramBotUsername();
    $botLink = $botUser !== '' ? ('https://t.me/' . $botUser . '?start=link_' . rawurlencode($code)) : '';

    return [
        'code' => $code,
        'expires_at' => $expiresAt,
        'bot_link' => $botLink,
    ];
}

function clearTelegramLinkCode(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    patchUser($userId, [
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
    ]);
}

function unlinkTelegram(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    return patchUser($userId, [
        'telegram_chat_id' => null,
        'telegram_username' => null,
        'telegram_linked_at' => null,
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
        'notify_telegram' => false,
    ]);
}

function findUserByTelegramLinkCode(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $now = time();
    foreach (getUsers() as $user) {
        if (strcasecmp((string)($user['telegram_link_code'] ?? ''), $code) !== 0) {
            continue;
        }
        $exp = strtotime((string)($user['telegram_link_expires_at'] ?? ''));
        if ($exp === false || $exp < $now) {
            continue;
        }
        return $user;
    }
    return null;
}

function linkTelegramChatToUser(int $userId, int|string $chatId, string $username = ''): bool
{
    if ($userId <= 0 || (string)$chatId === '') {
        return false;
    }
    return patchUser($userId, [
        'telegram_chat_id' => (string)$chatId,
        'telegram_username' => ltrim(trim($username), '@'),
        'telegram_linked_at' => date('Y-m-d H:i:s'),
        'telegram_link_code' => null,
        'telegram_link_expires_at' => null,
        'notify_telegram' => true,
    ]);
}

/**
 * @param array{parse_mode?:string,disable_web_page_preview?:bool,reply_markup?:array} $extra
 */
function telegramSendMessage(string $chatId, string $text, array $extra = []): bool
{
    if (!telegramEnabled() || trim($chatId) === '' || trim($text) === '') {
        return false;
    }

    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ], $extra);

    $res = telegramApiRequest('sendMessage', $payload);
    return !empty($res['ok']);
}

/**
 * @param array{parse_mode?:string,disable_web_page_preview?:bool} $extra
 * @return array{ok:bool,message_id?:int,description?:string}
 */
function telegramSendMessageDetailed(string $chatId, string $text, array $extra = []): array
{
    if (!telegramEnabled() || trim($chatId) === '' || trim($text) === '') {
        return ['ok' => false, 'description' => 'invalid'];
    }
    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ], $extra);
    $res = telegramApiRequest('sendMessage', $payload);
    if (empty($res['ok'])) {
        return ['ok' => false, 'description' => (string)($res['description'] ?? '')];
    }
    return [
        'ok' => true,
        'message_id' => (int)($res['result']['message_id'] ?? 0),
    ];
}

function telegramDeleteMessage(string $chatId, int $messageId): bool
{
    if ($messageId <= 0 || trim($chatId) === '') {
        return false;
    }
    $res = telegramApiRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
    ]);
    return !empty($res['ok']);
}

/**
 * @return array{ok:bool,message_id?:int,description?:string}
 */
function telegramSendPhoto(string $chatId, string $photoUrl, string $caption = ''): array
{
    if (!telegramEnabled() || trim($chatId) === '' || trim($photoUrl) === '') {
        return ['ok' => false, 'description' => 'invalid'];
    }
    $payload = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'disable_notification' => false,
    ];
    if (trim($caption) !== '') {
        $payload['caption'] = mb_substr(trim($caption), 0, 1024);
    }
    $res = telegramApiRequest('sendPhoto', $payload);
    if (empty($res['ok'])) {
        return ['ok' => false, 'description' => (string)($res['description'] ?? '')];
    }
    return [
        'ok' => true,
        'message_id' => (int)($res['result']['message_id'] ?? 0),
    ];
}

function telegramFormatAdChannelPost(array $ad): string
{
    $intent = function_exists('adIntentBadgeLabel') ? adIntentBadgeLabel($ad) : '';
    $title = function_exists('adDisplayTitle') ? adDisplayTitle($ad) : trim((string)($ad['title'] ?? 'Oglas'));
    $price = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $location = trim((string)($ad['location'] ?? ''));
    $category = function_exists('adCategoryLabel') ? adCategoryLabel($ad) : '';
    $url = absoluteUrl(adUrl($ad));

    $lines = [];
    if ($intent !== '') {
        $lines[] = '🔎 ' . $intent;
    } else {
        $lines[] = '📱 Novi oglas';
    }
    $lines[] = '';
    $lines[] = $title;
    if ($price !== '') {
        $lines[] = '💰 ' . $price;
    }
    if ($location !== '') {
        $lines[] = '📍 ' . $location;
    }
    if ($category !== '') {
        $lines[] = '🏷️ ' . $category;
    }
    $lines[] = '';
    $lines[] = '👉 ' . $url;
    $lines[] = '';
    $lines[] = 'kupitelefon.rs';
    return implode("\n", $lines);
}

/**
 * Objavi novi oglas u Telegram kanal. Ne baca greške ka saveAd toku.
 */
function telegramNotifyChannelNewAd(array $ad): bool
{
    if (!telegramEnabled() || !telegramPostAdsEnabled()) {
        return false;
    }
    if ((int)($ad['is_active'] ?? 0) !== 1) {
        return false;
    }
    if (!empty($ad['telegram_channel_posted_at'])) {
        return false;
    }

    $chatId = telegramChannelChatId();
    if ($chatId === '') {
        return false;
    }

    $text = telegramFormatAdChannelPost($ad);
    $photo = '';
    if (function_exists('adPrimaryImage')) {
        $rel = trim((string)adPrimaryImage($ad));
        if ($rel !== '') {
            $photo = absoluteUrl($rel);
        }
    }

    $ok = false;
    if ($photo !== '') {
        $sent = telegramSendPhoto($chatId, $photo, $text);
        $ok = !empty($sent['ok']);
    }
    if (!$ok) {
        $ok = telegramSendMessage($chatId, $text, ['disable_web_page_preview' => false]);
    }

    if ($ok) {
        $adId = (int)($ad['id'] ?? 0);
        if ($adId > 0) {
            // Označi da je već poslato (bez rekurzije saveAd).
            $ads = readJsonFile('ads.json');
            foreach ($ads as &$row) {
                if ((int)($row['id'] ?? 0) === $adId) {
                    $row['telegram_channel_posted_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($row);
            writeJsonFile('ads.json', $ads);
        }
    }
    return $ok;
}

function telegramPostChannelInfo(): bool
{
    $chatId = telegramChannelChatId();
    if ($chatId === '') {
        return false;
    }
    $text = telegramInfoText() . "\n\n(Bot odgovara na /info u privatnom chatu ili grupi. U kanalu članovi ne mogu da šalju komande — ovde bot objavljuje novosti i oglase.)";
    return telegramSendMessage($chatId, $text, ['disable_web_page_preview' => false]);
}

function telegramPreferenceKeyForType(string $type): string
{
    if ($type === 'new_message') {
        return 'notify_telegram_messages';
    }
    if (in_array($type, ['ad_expiry_warning', 'ad_expired', 'saved_search_match'], true)) {
        return 'notify_telegram_alerts';
    }
    return 'notify_telegram_system';
}

function userWantsTelegramType(?array $user, string $type): bool
{
    if (!$user || empty($user['telegram_chat_id'])) {
        return false;
    }
    if (array_key_exists('notify_telegram', $user) && empty($user['notify_telegram'])) {
        return false;
    }
    $prefKey = telegramPreferenceKeyForType($type);
    if (array_key_exists($prefKey, $user)) {
        return !empty($user[$prefKey]);
    }
    return true;
}

function sendUserTelegramNotification(int $userId, string $type, string $title, string $body, string $link = ''): bool
{
    $user = findUserById($userId);
    if (!userWantsTelegramType($user, $type)) {
        return false;
    }
    $chatId = trim((string)($user['telegram_chat_id'] ?? ''));
    if ($chatId === '') {
        return false;
    }

    $parts = ['🔔 ' . trim($title), trim($body)];
    $path = trim($link);
    if ($path !== '') {
        $absolute = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : rtrim(appBaseUrl(), '/') . '/' . ltrim($path, '/');
        $parts[] = 'Otvori: ' . $absolute;
    }
    $text = trim(implode("\n\n", array_filter($parts, static fn($v) => trim((string)$v) !== '')));
    return telegramSendMessage($chatId, $text) !== false;
}

function parseTelegramLinkCodeFromText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/^\/start(?:@\w+)?\s+link_([A-Za-z0-9]+)$/i', $text, $m)) {
        return strtoupper((string)$m[1]);
    }
    if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9]+)$/i', $text, $m)) {
        return strtoupper((string)$m[1]);
    }
    if (preg_match('/^[A-Za-z0-9]{6,10}$/', $text) === 1) {
        return strtoupper($text);
    }
    return '';
}

function telegramIsConfiguredChannel(string $chatId, string $chatUsername = ''): bool
{
    $configuredId = telegramChannelId();
    if ($configuredId !== '' && (string)$chatId === $configuredId) {
        return true;
    }
    $configuredUser = telegramChannelUsername();
    $chatUsername = ltrim(trim($chatUsername), '@');
    if ($configuredUser !== '' && $chatUsername !== '' && strcasecmp($configuredUser, $chatUsername) === 0) {
        return true;
    }
    // Ako nije setovan konkretan kanal, dozvoli sve kanale/grupe gde je bot admin.
    return $configuredId === '' && $configuredUser === '';
}

function telegramWelcomeMessageTemplate(): string
{
    $settings = function_exists('siteSettings') ? siteSettings() : [];
    $custom = trim((string)($settings['telegram_welcome_text'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    return "Zdravo {name}! 👋\n\nDobrodošao/la u KupiTelefon kanal.\nOvde delimo oglase, savete i novosti sa sajta.\n\n🌐 {site}\n📢 {channel}";
}

function telegramFormatWelcomeText(string $displayName): string
{
    $name = trim($displayName) !== '' ? trim($displayName) : 'dobrodošao/la';
    $site = rtrim(function_exists('appBaseUrl') ? appBaseUrl() : 'https://kupitelefon.rs', '/');
    $channel = telegramChannelUrl();
    if ($channel === '') {
        $channel = $site;
    }
    $tpl = telegramWelcomeMessageTemplate();
    return strtr($tpl, [
        '{name}' => $name,
        '{site}' => $site,
        '{channel}' => $channel,
    ]);
}

function telegramPrivateStartText(): string
{
    $site = rtrim(function_exists('appBaseUrl') ? appBaseUrl() : 'https://kupitelefon.rs', '/');
    $channel = telegramChannelUrl();
    $lines = [
        'Zdravo! Ja sam bot sajta KupiTelefon.rs 📱',
        '',
        'Moguće je:',
        '• povezati nalog i dobijati obaveštenja',
        '• pratiti novosti u našem kanalu',
        '',
        'Poveži nalog: otvori Nalog → Telegram na sajtu, pa klikni Start.',
        'Sajt: ' . $site,
    ];
    if ($channel !== '') {
        $lines[] = 'Kanal: ' . $channel;
    }
    $lines[] = '';
    $lines[] = 'Komande: /info /start /help /kanal';
    return implode("\n", $lines);
}

function telegramHelpText(): string
{
    $channel = telegramChannelUrl();
    $lines = [
        'KupiTelefon bot — pomoć',
        '',
        '/info — kratke informacije o sajtu',
        '/start — početna poruka',
        '/help — ova lista',
        '/kanal — link ka Telegram kanalu',
        '',
        'U grupi piši npr. /info@' . (telegramBotUsername() !== '' ? telegramBotUsername() : 'Kupitelefonrs_bot'),
        'Za obaveštenja: u Nalogu na sajtu klikni „Poveži Telegram”.',
    ];
    if ($channel !== '') {
        $lines[] = '';
        $lines[] = 'Kanal: ' . $channel;
    }
    return implode("\n", $lines);
}

function telegramInfoText(): string
{
    $site = rtrim(function_exists('appBaseUrl') ? appBaseUrl() : 'https://kupitelefon.rs', '/');
    $channel = telegramChannelUrl();
    $lines = [
        '📱 KupiTelefon.rs',
        '',
        'Berza polovnih telefona, delova i servisa u Srbiji.',
        'Objavi oglas besplatno i pronađi kupca brže.',
        '',
        'Sajt: ' . $site,
        'Objavi oglas: ' . $site . '/ad_form.php',
        'IMEI provera: ' . $site . '/provera-imei',
    ];
    if ($channel !== '') {
        $lines[] = 'Telegram kanal: ' . $channel;
    }
    $lines[] = '';
    $lines[] = 'Komande: /info /help /kanal /start';
    return implode("\n", $lines);
}

/**
 * @return list<string>
 */
function telegramWebhookAllowedUpdates(): array
{
    return ['message', 'edited_message', 'channel_post', 'chat_member', 'my_chat_member'];
}

/**
 * @return array{ok:bool,description?:string,result?:array}
 */
function telegramSetWebhook(string $publicUrl, ?string $secret = null): array
{
    $secret = $secret ?? telegramWebhookSecret();
    $payload = [
        'url' => $publicUrl,
        'allowed_updates' => telegramWebhookAllowedUpdates(),
        'drop_pending_updates' => false,
    ];
    if ($secret !== '') {
        $payload['secret_token'] = $secret;
    }
    return telegramApiRequest('setWebhook', $payload);
}

/**
 * @return array{ok:bool,description?:string,result?:array}
 */
function telegramGetWebhookInfo(): array
{
    return telegramApiRequest('getWebhookInfo', []);
}

function telegramMemberDisplayName(array $user): string
{
    $first = trim((string)($user['first_name'] ?? ''));
    $last = trim((string)($user['last_name'] ?? ''));
    $username = ltrim(trim((string)($user['username'] ?? '')), '@');
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }
    if ($username !== '') {
        return '@' . $username;
    }
    return 'novi član';
}

function telegramMentionHtml(array $user): string
{
    $id = (int)($user['id'] ?? 0);
    $label = htmlspecialchars(telegramMemberDisplayName($user), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($id > 0) {
        return '<a href="tg://user?id=' . $id . '">' . $label . '</a>';
    }
    return $label;
}

function handleTelegramChannelMemberUpdate(array $update): void
{
    if (!telegramWelcomeEnabled()) {
        return;
    }

    $cm = $update['chat_member'] ?? null;
    if (!is_array($cm)) {
        return;
    }

    $chat = is_array($cm['chat'] ?? null) ? $cm['chat'] : [];
    $chatId = (string)($chat['id'] ?? '');
    $chatType = (string)($chat['type'] ?? '');
    $chatUsername = (string)($chat['username'] ?? '');
    if ($chatId === '' || !in_array($chatType, ['channel', 'supergroup', 'group'], true)) {
        return;
    }
    if (!telegramIsConfiguredChannel($chatId, $chatUsername)) {
        return;
    }

    $oldStatus = (string)($cm['old_chat_member']['status'] ?? '');
    $newMember = is_array($cm['new_chat_member'] ?? null) ? $cm['new_chat_member'] : [];
    $newStatus = (string)($newMember['status'] ?? '');
    $user = is_array($newMember['user'] ?? null) ? $newMember['user'] : [];
    if (!empty($user['is_bot'])) {
        return;
    }

    $joined = in_array($newStatus, ['member', 'administrator', 'restricted'], true)
        && in_array($oldStatus, ['left', 'kicked', ''], true);
    if (!$joined) {
        return;
    }

    $name = telegramMemberDisplayName($user);
    $site = rtrim(function_exists('appBaseUrl') ? appBaseUrl() : 'https://kupitelefon.rs', '/');
    $channel = telegramChannelUrl();
    if ($channel === '') {
        $channel = $site;
    }
    $mention = telegramMentionHtml($user);
    $textHtml = 'Zdravo ' . $mention . "! 👋<br><br>"
        . 'Dobrodošao/la u <b>KupiTelefon</b> kanal.<br>'
        . 'Ovde delimo oglase, savete i novosti sa sajta.<br><br>'
        . '🌐 ' . htmlspecialchars($site, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>'
        . '📢 ' . htmlspecialchars($channel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $custom = trim((string)((function_exists('siteSettings') ? siteSettings() : [])['telegram_welcome_text'] ?? ''));
    if ($custom !== '') {
        $plain = telegramFormatWelcomeText($name);
        $textHtml = nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $textHtml = str_replace(
            htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $mention,
            $textHtml
        );
    }

    $sent = telegramSendMessageDetailed($chatId, $textHtml, [
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
    if (empty($sent['ok'])) {
        telegramSendMessage($chatId, telegramFormatWelcomeText($name));
    }

    // Privatna poruka radi samo ako je korisnik ranije pokrenuo bota (/start).
    $userId = (int)($user['id'] ?? 0);
    if ($userId > 0) {
        telegramSendMessage(
            (string)$userId,
            "Hvala što pratiš KupiTelefon! 🙌\n\n" . telegramPrivateStartText()
        );
    }
}

function handleTelegramChatMessage(array $msg): void
{
    $chat = is_array($msg['chat'] ?? null) ? $msg['chat'] : [];
    $chatType = (string)($chat['type'] ?? '');
    $isPrivate = $chatType === 'private';
    $isGroup = in_array($chatType, ['group', 'supergroup'], true);
    $isChannel = $chatType === 'channel';
    if (!$isPrivate && !$isGroup && !$isChannel) {
        return;
    }

    $chatId = (string)($chat['id'] ?? '');
    $text = trim((string)($msg['text'] ?? $msg['caption'] ?? ''));
    if ($chatId === '' || $text === '') {
        return;
    }

    $tgUser = trim((string)($msg['from']['username'] ?? ''));
    $firstToken = trim(explode(' ', $text, 2)[0]);
    $cmd = mb_strtolower($firstToken);
    $cmd = preg_replace('/@\w+$/', '', $cmd) ?? $cmd;

    // U grupi/kanalu reaguj samo na komande (ne na običan chat).
    $isCommand = str_starts_with($cmd, '/');
    if (!$isPrivate && !$isCommand) {
        return;
    }

    if (in_array($cmd, ['/info', '/about', 'info'], true)) {
        telegramSendMessage($chatId, telegramInfoText());
        return;
    }
    if (in_array($cmd, ['/help', 'help', '/pomoc', '/pomoć'], true)) {
        telegramSendMessage($chatId, telegramHelpText());
        return;
    }
    if (in_array($cmd, ['/kanal', '/channel', '/grupa'], true)) {
        $channel = telegramChannelUrl();
        telegramSendMessage(
            $chatId,
            $channel !== '' ? ('Naš kanal: ' . $channel) : ('Sajt: ' . rtrim(appBaseUrl(), '/'))
        );
        return;
    }
    if ($cmd === '/start' || str_starts_with($cmd, '/start')) {
        // Ako /start ima link kod, obradi ispod.
        $codeFromStart = parseTelegramLinkCodeFromText($text);
        if ($codeFromStart === '') {
            telegramSendMessage($chatId, $isPrivate ? telegramPrivateStartText() : telegramInfoText());
            return;
        }
    }

    // Povezivanje naloga radi samo u privatnom chatu.
    if (!$isPrivate) {
        if ($isCommand) {
            telegramSendMessage($chatId, "Nepoznata komanda.\nProbaj /info ili /help");
        }
        return;
    }

    $code = parseTelegramLinkCodeFromText($text);
    if ($code === '') {
        if ($isCommand) {
            telegramSendMessage($chatId, "Nepoznata komanda.\nProbaj /info ili /help");
            return;
        }
        telegramSendMessage(
            $chatId,
            "Pošalji kod za povezivanje iz KupiTelefon naloga.\n\nPrimer: AB12CD3\n\nIli koristi /info"
        );
        return;
    }

    $user = findUserByTelegramLinkCode($code);
    if (!$user) {
        telegramSendMessage(
            $chatId,
            'Kod nije važeći ili je istekao. U nalogu generiši novi Telegram kod.'
        );
        return;
    }

    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0 || !linkTelegramChatToUser($uid, $chatId, $tgUser)) {
        telegramSendMessage($chatId, 'Povezivanje nije uspelo. Pokušaj ponovo.');
        return;
    }

    $name = trim((string)($user['full_name'] ?? $user['username'] ?? 'korisniče'));
    $channel = telegramChannelUrl();
    $extra = $channel !== '' ? ("\n\nPrati i kanal: " . $channel) : '';
    telegramSendMessage(
        $chatId,
        "Uspešno povezano sa nalogom {$name}.\nTelegram obaveštenja su uključena." . $extra
    );
}

function handleTelegramWebhookUpdate(array $update): void
{
    if (isset($update['chat_member']) && is_array($update['chat_member'])) {
        handleTelegramChannelMemberUpdate($update);
        return;
    }

    // Bot dodat/uklonjen iz kanala — samo zabeleži tiho (nema akcije).
    if (isset($update['my_chat_member'])) {
        return;
    }

    $msg = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
    if (!is_array($msg)) {
        return;
    }

    // Grupe: stariji tip update-a za nove članove.
    if (!empty($msg['new_chat_members']) && is_array($msg['new_chat_members']) && telegramWelcomeEnabled()) {
        $chat = is_array($msg['chat'] ?? null) ? $msg['chat'] : [];
        $chatId = (string)($chat['id'] ?? '');
        $chatUsername = (string)($chat['username'] ?? '');
        if ($chatId !== '' && telegramIsConfiguredChannel($chatId, $chatUsername)) {
            foreach ($msg['new_chat_members'] as $member) {
                if (!is_array($member) || !empty($member['is_bot'])) {
                    continue;
                }
                $text = telegramFormatWelcomeText(telegramMemberDisplayName($member));
                telegramSendMessage($chatId, $text);
            }
        }
        // Nemoj return odmah — ako ima i text, obradi komandu ispod.
        if (trim((string)($msg['text'] ?? '')) === '') {
            return;
        }
    }

    handleTelegramChatMessage($msg);
}
