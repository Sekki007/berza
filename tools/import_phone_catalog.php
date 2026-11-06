<?php

declare(strict_types=1);

/**
 * Provera i uvoz kataloga telefona.
 *
 *   php tools/import_phone_catalog.php
 *   php tools/import_phone_catalog.php --apply
 *   php tools/import_phone_catalog.php --apply --sql
 *
 * Podrazumevano je dry-run: ništa se ne upisuje.
 * --apply upisuje proveren dokument phone_models.json u postojeće skladište
 * (JSON fajl ili MySQL json_documents, isto kao ostali podaci).
 * --sql dodatno puni relacione tabele iz database/phone_catalog_upgrade.sql
 * ako je MySQL dostupan. Ako nije, uvoz JSON-a i dalje uspeva.
 */

define('KT_SKIP_BACKGROUND', true);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$sql = in_array('--sql', $argv, true);

$raw = json_decode((string)file_get_contents(phoneCatalogSeedPath()), true);
if (!is_array($raw)) {
    fwrite(STDERR, "Seed nije ispravan JSON.\n");
    exit(1);
}
phoneCatalogResetCache();
$bundle = phoneCatalogNormalizeBundle($raw);
if (empty($bundle['ok'])) {
    fwrite(STDERR, "Seed nije prošao proveru:\n");
    foreach ($bundle['errors'] as $error) {
        fwrite(STDERR, ' - ' . $error . "\n");
    }
    exit(1);
}

$withSpecs = 0;
$specRows = 0;
$variants = 0;
foreach ($bundle['models'] as $model) {
    if ($model['specs'] !== []) {
        $withSpecs++;
    }
    $specRows += count($model['specs']);
    $variants += count($model['variants']);
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo 'Modela: ' . count($bundle['models']) . "\n";
echo 'Sa specifikacijama: ' . $withSpecs . "\n";
echo 'Redova specifikacija: ' . $specRows . "\n";
echo 'Varijanti memorije: ' . $variants . "\n";
echo 'Izvora: ' . count($bundle['sources']) . "\n";
echo $bundle['coverage_note'] . "\n";
echo "Modeli:\n";
foreach ($bundle['order'] as $id) {
    $model = $bundle['models'][$id];
    echo ' - ' . $model['id'] . ' | ' . $model['brand'] . ' ' . $model['name']
        . ' | specs=' . count($model['specs'])
        . ' | varijante=' . count($model['variants'])
        . ' | ' . phoneCatalogUrl($model) . "\n";
}

if (!$apply) {
    echo "Ništa nije upisano. Za upis: php tools/import_phone_catalog.php --apply\n";
    exit(0);
}

writeJsonFile(phoneCatalogStoreFilename(), [
    'version' => 1,
    'coverage_note' => $bundle['coverage_note'],
    'sources' => array_values($bundle['sources']),
    'models' => array_map(static function (array $model): array {
        return [
            'id' => $model['id'],
            'brand' => $model['brand'],
            'brand_slug' => $model['brand_slug'],
            'name' => $model['name'],
            'official_name' => $model['official_name'],
            'slug' => $model['slug'],
            'source_ids' => $model['source_ids'],
            'aliases' => array_map(static fn(array $tokens): string => implode(' ', $tokens), $model['aliases']),
            'colors' => $model['colors'],
            'variants' => array_values($model['variants']),
            'highlights' => $model['highlights'],
            'specs' => $model['specs'],
            'image' => null,
        ];
    }, array_values($bundle['models'])),
]);
phoneCatalogResetCache();
echo "Upisano: " . phoneCatalogStoreFilename() . ' (' . storageDriver() . ")\n";

if (!$sql) {
    exit(0);
}

try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    echo "SQL preskočen, MySQL nije dostupan: " . $e->getMessage() . "\n";
    exit(0);
}

$schema = dirname(__DIR__) . '/database/phone_catalog_upgrade.sql';
$sqlText = (string)file_get_contents($schema);
$sqlText = preg_replace('/^--.*$/m', '', $sqlText) ?? $sqlText;
foreach (array_filter(array_map('trim', explode(';', $sqlText))) as $statement) {
    $pdo->exec($statement);
}

$sourceStmt = $pdo->prepare(
    'INSERT INTO phone_spec_sources (id, kind, publisher, title, url, retrieved_at, note)
     VALUES (:id, :kind, :publisher, :title, :url, :retrieved_at, :note)
     ON DUPLICATE KEY UPDATE kind = VALUES(kind), publisher = VALUES(publisher), title = VALUES(title), url = VALUES(url), retrieved_at = VALUES(retrieved_at), note = VALUES(note)'
);
foreach ($bundle['sources'] as $source) {
    $sourceStmt->execute([
        ':id' => $source['id'],
        ':kind' => $source['kind'],
        ':publisher' => $source['publisher'],
        ':title' => $source['title'],
        ':url' => $source['url'],
        ':retrieved_at' => $source['retrieved_at'] !== '' ? $source['retrieved_at'] : null,
        ':note' => $source['note'] !== '' ? $source['note'] : null,
    ]);
}

$modelStmt = $pdo->prepare(
    'INSERT INTO phone_models (id, brand, brand_slug, name, official_name, slug, source_ids_json, colors_json)
     VALUES (:id, :brand, :brand_slug, :name, :official_name, :slug, :source_ids_json, :colors_json)
     ON DUPLICATE KEY UPDATE brand = VALUES(brand), brand_slug = VALUES(brand_slug), name = VALUES(name), official_name = VALUES(official_name), slug = VALUES(slug), source_ids_json = VALUES(source_ids_json), colors_json = VALUES(colors_json)'
);
$pdo->exec('DELETE FROM phone_model_variants');
$pdo->exec('DELETE FROM phone_model_specs');
$variantStmt = $pdo->prepare(
    'INSERT INTO phone_model_variants (model_id, variant_id, storage, source_id)
     VALUES (:model_id, :variant_id, :storage, :source_id)'
);
$specStmt = $pdo->prepare(
    'INSERT INTO phone_model_specs (model_id, group_label, spec_label, spec_value, source_id, sort_order)
     VALUES (:model_id, :group_label, :spec_label, :spec_value, :source_id, :sort_order)'
);
foreach ($bundle['models'] as $model) {
    $modelStmt->execute([
        ':id' => $model['id'],
        ':brand' => $model['brand'],
        ':brand_slug' => $model['brand_slug'],
        ':name' => $model['name'],
        ':official_name' => $model['official_name'] !== '' ? $model['official_name'] : null,
        ':slug' => $model['slug'],
        ':source_ids_json' => json_encode($model['source_ids'], JSON_UNESCAPED_UNICODE),
        ':colors_json' => json_encode($model['colors'], JSON_UNESCAPED_UNICODE),
    ]);
    foreach ($model['variants'] as $variant) {
        $variantStmt->execute([
            ':model_id' => $model['id'],
            ':variant_id' => $variant['id'],
            ':storage' => $variant['storage'],
            ':source_id' => $variant['source_id'],
        ]);
    }
    $sort = 0;
    foreach ($model['specs'] as $spec) {
        $specStmt->execute([
            ':model_id' => $model['id'],
            ':group_label' => $spec['group'],
            ':spec_label' => $spec['label'],
            ':spec_value' => $spec['value'],
            ':source_id' => $spec['source_id'],
            ':sort_order' => $sort++,
        ]);
    }
}
echo "SQL tabele su ažurirane.\n";
exit(0);
