<?php

declare(strict_types=1);

/**
 * Registruje / proverava Telegram webhook za KupiTelefon bota.
 *
 * Primeri:
 *   php tools/telegram_setup.php
 *   php tools/telegram_setup.php status
 *   php tools/telegram_setup.php probe
 *   php tools/telegram_setup.php post-info
 *   php tools/telegram_setup.php test-ad 123
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!telegramEnabled()) {
    fwrite(STDERR, "Telegram nije uključen. U .env stavi TELEGRAM_ENABLED=true i TELEGRAM_BOT_TOKEN.\n");
    exit(1);
}

$arg = trim((string)($argv[1] ?? ''));
$arg2 = trim((string)($argv[2] ?? ''));

if ($arg === 'status') {
    $info = telegramGetWebhookInfo();
    if (empty($info['ok'])) {
        fwrite(STDERR, 'getWebhookInfo greška: ' . (string)($info['description'] ?? '') . PHP_EOL);
        exit(1);
    }
    echo json_encode($info['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo "\nChannel target: " . (telegramChannelChatId() !== '' ? telegramChannelChatId() : '(nije setovan)') . PHP_EOL;
    echo 'CHANNEL_ID env: ' . (telegramChannelId() !== '' ? telegramChannelId() : '(prazno)') . PHP_EOL;
    echo 'CHANNEL_USERNAME env: ' . (telegramChannelUsername() !== '' ? ('@' . telegramChannelUsername()) : '(prazno)') . PHP_EOL;
    echo 'Post ads: ' . (telegramPostAdsEnabled() ? 'ON' : 'OFF') . PHP_EOL;
    exit(0);
}

if ($arg === 'probe') {
    echo "Proveravam kanal...\n";
    echo 'Target: ' . (telegramChannelChatId() !== '' ? telegramChannelChatId() : '(nije setovan)') . PHP_EOL;
    $probe = telegramProbeChannel();
    if (empty($probe['ok'])) {
        fwrite(STDERR, 'PROBE FAIL: ' . (string)($probe['error'] ?? '') . PHP_EOL);
        if (!empty($probe['chat'])) {
            echo json_encode($probe['chat'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
        echo "\nKako naći CHANNEL_ID:\n";
        echo "1) Bot mora biti admin kanala.\n";
        echo "2) Objavite bilo šta u kanalu (kao admin).\n";
        echo "3) Privremeno: curl 'https://api.telegram.org/bot<TOKEN>/getUpdates'\n";
        echo "   Traži chat.id tipa -100...\n";
        echo "4) Ubaci u .env: TELEGRAM_CHANNEL_ID=-100...\n";
        exit(1);
    }
    echo "PROBE OK — bot vidi kanal i admin je.\n";
    echo json_encode($probe['chat'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if ($arg === 'post-info') {
    $res = telegramPostChannelInfo();
    if (empty($res['ok'])) {
        fwrite(STDERR, 'FAIL: ' . (string)($res['error'] ?? '') . PHP_EOL);
        exit(1);
    }
    echo "Info poruka poslata u kanal.\n";
    exit(0);
}

if ($arg === 'test-ad') {
    $adId = (int)$arg2;
    if ($adId <= 0) {
        fwrite(STDERR, "Upotreba: php tools/telegram_setup.php test-ad <ad_id>\n");
        exit(1);
    }
    $ad = getAdById($adId);
    if (!$ad) {
        fwrite(STDERR, "Oglas #{$adId} nije pronađen.\n");
        exit(1);
    }
    echo 'Channel target: ' . (telegramChannelChatId() !== '' ? telegramChannelChatId() : '(nije setovan)') . PHP_EOL;
    $res = telegramNotifyChannelNewAd($ad, true);
    if (empty($res['ok'])) {
        fwrite(STDERR, 'FAIL: ' . (string)($res['error'] ?? '') . PHP_EOL);
        exit(1);
    }
    echo "Oglas #{$adId} objavljen u Telegram kanal.\n";
    exit(0);
}

$url = $arg;
if ($url === '') {
    $url = rtrim(appBaseUrl(), '/') . '/api/telegram-webhook.php';
}
if (!str_starts_with($url, 'https://')) {
    fwrite(STDERR, "Webhook URL mora biti HTTPS.\nDato: {$url}\n");
    exit(1);
}

echo "Postavljam webhook: {$url}\n";
echo 'Allowed updates: ' . implode(', ', telegramWebhookAllowedUpdates()) . PHP_EOL;

$set = telegramSetWebhook($url);
if (empty($set['ok'])) {
    fwrite(STDERR, 'setWebhook greška: ' . (string)($set['description'] ?? '') . PHP_EOL);
    exit(1);
}

echo "Webhook OK.\n";

$commands = telegramApiRequest('setMyCommands', [
    'commands' => [
        ['command' => 'info', 'description' => 'Informacije o KupiTelefon.rs'],
        ['command' => 'help', 'description' => 'Lista komandi'],
        ['command' => 'kanal', 'description' => 'Link ka Telegram kanalu'],
        ['command' => 'start', 'description' => 'Početak / povezivanje naloga'],
    ],
]);
if (!empty($commands['ok'])) {
    echo "Bot komande (/info, /help…) registrovane.\n";
} else {
    echo 'setMyCommands upozorenje: ' . (string)($commands['description'] ?? '') . PHP_EOL;
}

echo "\n";
$info = telegramGetWebhookInfo();
if (!empty($info['ok'])) {
    echo json_encode($info['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

echo "\nVažno o KANALU:\n";
echo "- Članovi kanala NE MOGU da šalju /info botu (Telegram ograničenje).\n";
echo "- Bot u kanalu OBJAVLJUJE oglase i info poruke kao admin.\n";
echo "- Provera kanala: php tools/telegram_setup.php probe\n";
echo "- Info u kanal: php tools/telegram_setup.php post-info\n";
echo "\nProveri još:\n";
echo "1) Bot je administrator kanala (Post messages).\n";
echo "2) TELEGRAM_CHANNEL_ID=-100... u .env\n";
echo "3) TELEGRAM_POST_ADS_ENABLED=true\n";
