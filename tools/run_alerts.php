<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$saved = processSavedSearchAlerts(true);
$exp = processAdExpirations();
$top = processTopExpirations();

echo json_encode([
    'saved_search_alerts' => $saved,
    'ad_expirations' => $exp,
    'top_expirations' => $top,
    'ran_at' => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
