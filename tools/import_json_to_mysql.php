<?php

declare(strict_types=1);

/**
 * Uvozi postojeće data/*.json u MySQL (prema database/schema.sql).
 *
 * Upotreba (CLI):
 *   php tools/import_json_to_mysql.php
 *
 * Zahteva: MySQL baza kreirana iz schema.sql + ispravan .env
 */

require_once dirname(__DIR__) . '/config/database.php';

function loadJson(string $file): array
{
    $path = dirname(__DIR__) . '/data/' . $file;
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL konekcija nije uspela: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Proveri .env (DB_HOST, DB_NAME, DB_USER, DB_PASS) i da li si uvezao schema.sql\n");
    exit(1);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

println('== Import users ==');
$users = loadJson('users.json');
$pdo->exec('DELETE FROM users');
$stmt = $pdo->prepare(
    'INSERT INTO users (
        id, username, password_hash, full_name, phone, email, email_verified_at, email_verify_token,
        email_verify_sent_at, notify_email, shop_name, shop_bio, location, is_admin, is_blocked,
        blocked_reason, verified_seller, verified_seller_at, credits, created_at
    ) VALUES (
        :id, :username, :password_hash, :full_name, :phone, :email, :email_verified_at, :email_verify_token,
        :email_verify_sent_at, :notify_email, :shop_name, :shop_bio, :location, :is_admin, :is_blocked,
        :blocked_reason, :verified_seller, :verified_seller_at, :credits, :created_at
    )'
);
foreach ($users as $u) {
    $stmt->execute([
        ':id' => (int)($u['id'] ?? 0),
        ':username' => (string)($u['username'] ?? ''),
        ':password_hash' => (string)($u['password_hash'] ?? ''),
        ':full_name' => (string)($u['full_name'] ?? ''),
        ':phone' => $u['phone'] ?? null,
        ':email' => ($u['email'] ?? '') !== '' ? $u['email'] : null,
        ':email_verified_at' => $u['email_verified_at'] ?? null,
        ':email_verify_token' => $u['email_verify_token'] ?? null,
        ':email_verify_sent_at' => $u['email_verify_sent_at'] ?? null,
        ':notify_email' => !isset($u['notify_email']) || !empty($u['notify_email']) ? 1 : 0,
        ':shop_name' => $u['shop_name'] ?? null,
        ':shop_bio' => $u['shop_bio'] ?? null,
        ':location' => $u['location'] ?? null,
        ':is_admin' => !empty($u['is_admin']) ? 1 : 0,
        ':is_blocked' => !empty($u['is_blocked']) ? 1 : 0,
        ':blocked_reason' => $u['blocked_reason'] ?? null,
        ':verified_seller' => !empty($u['verified_seller']) ? 1 : 0,
        ':verified_seller_at' => $u['verified_seller_at'] ?? null,
        ':credits' => (int)($u['credits'] ?? 0),
        ':created_at' => $u['created_at'] ?? date('Y-m-d H:i:s'),
    ]);
}
println('  ' . count($users) . ' users');

println('== Import ads ==');
$ads = loadJson('ads.json');
$pdo->exec('DELETE FROM ads');
$stmt = $pdo->prepare(
    'INSERT INTO ads (
        id, title, description, ad_type, category, category_group, brand, model, storage, price,
        condition_state, location, country, contact_phone, shop_name, badge, images_json,
        is_active, is_sold, is_promoted, promoted_until, is_highlighted, highlighted_until,
        views, expires_at, expiry_warned_at, created_by, created_at, updated_at
    ) VALUES (
        :id, :title, :description, :ad_type, :category, :category_group, :brand, :model, :storage, :price,
        :condition_state, :location, :country, :contact_phone, :shop_name, :badge, :images_json,
        :is_active, :is_sold, :is_promoted, :promoted_until, :is_highlighted, :highlighted_until,
        :views, :expires_at, :expiry_warned_at, :created_by, :created_at, :updated_at
    )'
);
foreach ($ads as $a) {
    $type = (string)($a['ad_type'] ?? 'telefon');
    if (!in_array($type, ['telefon', 'delovi', 'servis'], true)) {
        $type = 'telefon';
    }
    $stmt->execute([
        ':id' => (int)($a['id'] ?? 0),
        ':title' => (string)($a['title'] ?? ''),
        ':description' => $a['description'] ?? null,
        ':ad_type' => $type,
        ':category' => (string)($a['category'] ?? 'Telefoni'),
        ':category_group' => $a['category_group'] ?? null,
        ':brand' => $a['brand'] ?? null,
        ':model' => $a['model'] ?? null,
        ':storage' => $a['storage'] ?? null,
        ':price' => (float)($a['price'] ?? 0),
        ':condition_state' => (string)($a['condition_state'] ?? 'Polovno'),
        ':location' => (string)($a['location'] ?? ''),
        ':country' => (string)($a['country'] ?? 'Srbija'),
        ':contact_phone' => $a['contact_phone'] ?? null,
        ':shop_name' => $a['shop_name'] ?? null,
        ':badge' => $a['badge'] ?? null,
        ':images_json' => json_encode($a['images'] ?? [], JSON_UNESCAPED_UNICODE),
        ':is_active' => (int)($a['is_active'] ?? 1),
        ':is_sold' => !empty($a['is_sold']) ? 1 : 0,
        ':is_promoted' => !empty($a['is_promoted']) ? 1 : 0,
        ':promoted_until' => $a['promoted_until'] ?? null,
        ':is_highlighted' => !empty($a['is_highlighted']) ? 1 : 0,
        ':highlighted_until' => $a['highlighted_until'] ?? null,
        ':views' => (int)($a['views'] ?? 0),
        ':expires_at' => $a['expires_at'] ?? null,
        ':expiry_warned_at' => $a['expiry_warned_at'] ?? null,
        ':created_by' => (int)($a['created_by'] ?? 1),
        ':created_at' => $a['created_at'] ?? date('Y-m-d H:i:s'),
        ':updated_at' => $a['updated_at'] ?? null,
    ]);
}
println('  ' . count($ads) . ' ads');

println('== Import messages ==');
$messages = loadJson('messages.json');
$pdo->exec('DELETE FROM messages');
$stmt = $pdo->prepare(
    'INSERT INTO messages (id, ad_id, from_user_id, from_name, from_phone, to_user_id, body, is_read, created_at)
     VALUES (:id, :ad_id, :from_user_id, :from_name, :from_phone, :to_user_id, :body, :is_read, :created_at)'
);
foreach ($messages as $m) {
    $stmt->execute([
        ':id' => (int)($m['id'] ?? 0),
        ':ad_id' => (int)($m['ad_id'] ?? 0),
        ':from_user_id' => isset($m['from_user_id']) ? (int)$m['from_user_id'] : null,
        ':from_name' => $m['from_name'] ?? null,
        ':from_phone' => $m['from_phone'] ?? null,
        ':to_user_id' => (int)($m['to_user_id'] ?? 0),
        ':body' => (string)($m['body'] ?? ''),
        ':is_read' => !empty($m['is_read']) ? 1 : 0,
        ':created_at' => $m['created_at'] ?? date('Y-m-d H:i:s'),
    ]);
}
println('  ' . count($messages) . ' messages');

$simpleImports = [
    'ratings.json' => ['ratings', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM ratings');
        $stmt = $pdo->prepare(
            'INSERT INTO ratings (id, seller_id, from_user_id, vote, score, comment, ad_id, conversation_key, created_at)
             VALUES (:id, :seller_id, :from_user_id, :vote, :score, :comment, :ad_id, :conversation_key, :created_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $vote = $r['vote'] ?? '';
            if ($vote !== 'positive' && $vote !== 'negative') {
                $score = (int)($r['score'] ?? 0);
                $vote = $score >= 3 || $score === 1 ? 'positive' : 'negative';
            }
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':seller_id' => (int)($r['seller_id'] ?? 0),
                ':from_user_id' => (int)($r['from_user_id'] ?? 0),
                ':vote' => $vote,
                ':score' => $r['score'] ?? null,
                ':comment' => $r['comment'] ?? null,
                ':ad_id' => isset($r['ad_id']) ? (int)$r['ad_id'] : null,
                ':conversation_key' => $r['conversation_key'] ?? null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $n++;
        }
        return $n;
    }],
    'reports.json' => ['reports', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM reports');
        $stmt = $pdo->prepare(
            'INSERT INTO reports (id, type, target_id, from_user_id, from_name, reason, details, status, admin_note, created_at, updated_at)
             VALUES (:id, :type, :target_id, :from_user_id, :from_name, :reason, :details, :status, :admin_note, :created_at, :updated_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $type = ($r['type'] ?? '') === 'user' ? 'user' : 'ad';
            $status = (string)($r['status'] ?? 'open');
            if (!in_array($status, ['open', 'resolved', 'dismissed'], true)) {
                $status = 'open';
            }
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':type' => $type,
                ':target_id' => (int)($r['target_id'] ?? $r['ad_id'] ?? $r['user_id'] ?? 0),
                ':from_user_id' => isset($r['from_user_id']) ? (int)$r['from_user_id'] : null,
                ':from_name' => $r['from_name'] ?? null,
                ':reason' => (string)($r['reason'] ?? ''),
                ':details' => $r['details'] ?? null,
                ':status' => $status,
                ':admin_note' => $r['admin_note'] ?? null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $r['updated_at'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }],
    'notifications.json' => ['notifications', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM notifications');
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (id, user_id, type, title, body, link, is_read, created_at)
             VALUES (:id, :user_id, :type, :title, :body, :link, :is_read, :created_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':user_id' => (int)($r['user_id'] ?? 0),
                ':type' => (string)($r['type'] ?? 'info'),
                ':title' => (string)($r['title'] ?? ''),
                ':body' => (string)($r['body'] ?? ''),
                ':link' => $r['link'] ?? null,
                ':is_read' => !empty($r['is_read']) ? 1 : 0,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $n++;
        }
        return $n;
    }],
    'top_orders.json' => ['top_orders', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM top_orders');
        $stmt = $pdo->prepare(
            'INSERT INTO top_orders (id, user_id, ad_id, package_id, days, price, status, paid_with, created_at, paid_at)
             VALUES (:id, :user_id, :ad_id, :package_id, :days, :price, :status, :paid_with, :created_at, :paid_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':user_id' => (int)($r['user_id'] ?? 0),
                ':ad_id' => (int)($r['ad_id'] ?? 0),
                ':package_id' => (string)($r['package_id'] ?? ''),
                ':days' => (int)($r['days'] ?? 0),
                ':price' => (float)($r['price'] ?? 0),
                ':status' => (string)($r['status'] ?? 'pending'),
                ':paid_with' => $r['paid_with'] ?? null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
                ':paid_at' => $r['paid_at'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }],
    'credit_deposits.json' => ['credit_deposits', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM credit_deposits');
        $stmt = $pdo->prepare(
            'INSERT INTO credit_deposits (id, user_id, amount, status, note, created_at, confirmed_at)
             VALUES (:id, :user_id, :amount, :status, :note, :created_at, :confirmed_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':user_id' => (int)($r['user_id'] ?? 0),
                ':amount' => (int)($r['amount'] ?? 0),
                ':status' => (string)($r['status'] ?? 'pending'),
                ':note' => $r['note'] ?? null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
                ':confirmed_at' => $r['confirmed_at'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }],
    'credit_transactions.json' => ['credit_transactions', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM credit_transactions');
        $stmt = $pdo->prepare(
            'INSERT INTO credit_transactions (id, user_id, amount, type, note, meta_json, created_at)
             VALUES (:id, :user_id, :amount, :type, :note, :meta_json, :created_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':user_id' => (int)($r['user_id'] ?? 0),
                ':amount' => (int)($r['amount'] ?? 0),
                ':type' => (string)($r['type'] ?? 'other'),
                ':note' => $r['note'] ?? null,
                ':meta_json' => isset($r['meta']) ? json_encode($r['meta'], JSON_UNESCAPED_UNICODE) : null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $n++;
        }
        return $n;
    }],
    'saved_searches.json' => ['saved_searches', static function (PDO $pdo, array $rows): int {
        $pdo->exec('DELETE FROM saved_searches');
        $stmt = $pdo->prepare(
            'INSERT INTO saved_searches (id, user_id, name, filters_json, alert_enabled, last_match_ids_json, last_checked_at, created_at)
             VALUES (:id, :user_id, :name, :filters_json, :alert_enabled, :last_match_ids_json, :last_checked_at, :created_at)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $stmt->execute([
                ':id' => (int)($r['id'] ?? 0) ?: null,
                ':user_id' => (int)($r['user_id'] ?? 0),
                ':name' => $r['name'] ?? null,
                ':filters_json' => json_encode($r['filters'] ?? [], JSON_UNESCAPED_UNICODE),
                ':alert_enabled' => !empty($r['alert_enabled']) ? 1 : 0,
                ':last_match_ids_json' => json_encode($r['last_match_ids'] ?? [], JSON_UNESCAPED_UNICODE),
                ':last_checked_at' => $r['last_checked_at'] ?? null,
                ':created_at' => $r['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $n++;
        }
        return $n;
    }],
];

foreach ($simpleImports as $file => [$label, $fn]) {
    println("== Import {$label} ==");
    $rows = loadJson($file);
    $n = $fn($pdo, $rows);
    println("  {$n} rows");
}

println('== Import ad_stats ==');
$stats = loadJson('ad_stats.json');
$pdo->exec('DELETE FROM ad_stats_daily');
$pdo->exec('DELETE FROM ad_stats');
$st = $pdo->prepare(
    'INSERT INTO ad_stats (ad_id, views, phone_reveals, messages_started)
     VALUES (:ad_id, :views, :phone_reveals, :messages_started)'
);
$std = $pdo->prepare(
    'INSERT INTO ad_stats_daily (ad_id, day, views, phone_reveals, messages_started)
     VALUES (:ad_id, :day, :views, :phone_reveals, :messages_started)'
);
$statsCount = 0;
foreach ($stats as $key => $row) {
    if (!is_array($row)) {
        continue;
    }
    $adId = (int)($row['ad_id'] ?? $key);
    if ($adId <= 0) {
        continue;
    }
    $st->execute([
        ':ad_id' => $adId,
        ':views' => (int)($row['views'] ?? 0),
        ':phone_reveals' => (int)($row['phone_reveals'] ?? 0),
        ':messages_started' => (int)($row['messages_started'] ?? 0),
    ]);
    foreach (($row['daily'] ?? []) as $day => $bucket) {
        if (!is_array($bucket)) {
            continue;
        }
        $std->execute([
            ':ad_id' => $adId,
            ':day' => (string)$day,
            ':views' => (int)($bucket['views'] ?? 0),
            ':phone_reveals' => (int)($bucket['phone_reveals'] ?? 0),
            ':messages_started' => (int)($bucket['messages_started'] ?? 0),
        ]);
    }
    $statsCount++;
}
println("  {$statsCount} ad stats");

println('== Import settings ==');
$settings = loadJson('settings.json');
$pdo->exec('DELETE FROM settings');
if ($settings !== []) {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)');
    $stmt->execute([
        ':k' => 'site',
        ':v' => json_encode($settings, JSON_UNESCAPED_UNICODE),
    ]);
    println('  site settings saved');
} else {
    println('  (empty)');
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
println('');
println('Gotovo. MySQL je napunjen iz JSON fajlova.');
println('NAPOMENA: sama aplikacija i dalje čita JSON dok ne uključimo MySQL data sloj (sledeći korak).');
