<?php

declare(strict_types=1);

/**
 * Registruje / proverava Telegram webhook za KupiTelefon bota.
 *
 * Primeri:
 *   php tools/telegram_setup.php
 *   php tools/telegram_setup.php https://kupitelefon.rs/api/telegram-webhook.php
 *   php tools/telegram_setup.php status
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!telegramEnabled()) {
    fwrite(STDERR, "Telegram nije uključen. U .env stavi TELEGRAM_ENABLED=true i TELEGRAM_BOT_TOKEN.\n");
    exit(1);
}

$arg = trim((string)($argv[1] ?? ''));

if ($arg === 'status' || $arg === 'info') {
    $info = telegramGetWebhookInfo();
    if (empty($info['ok'])) {
        fwrite(STDERR, 'getWebhookInfo greška: ' . (string)($info['description'] ?? '') . PHP_EOL);
        exit(1);
    }
    echo json_encode($info['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
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

echo "Webhook OK.\n\n";
$info = telegramGetWebhookInfo();
if (!empty($info['ok'])) {
    echo json_encode($info['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

echo "\nProveri još:\n";
echo "1) Bot je administrator kanala (može da šalje poruke).\n";
echo "2) TELEGRAM_CHANNEL_ID ili TELEGRAM_CHANNEL_USERNAME u .env.\n";
echo "3) TELEGRAM_WELCOME_ENABLED=true\n";
echo "4) Uđi u kanal test nalogom i proveri dobrodošlicu.\n";
