<?php
/** @var array $cfg */
/** @var array $schema */
/** @var string $type */
/** @var string $brand */
/** @var string $model */
/** @var string $location */
/** @var string $condition */
/** @var string $minPrice */
/** @var string $maxPrice */
/** @var string $sort */
/** @var string $storage */
/** @var string $listingType */
/** @var string $equipmentType */
/** @var bool $onlyPriced */
/** @var bool $onlyPhotos */
/** @var string $search */
/** @var bool $includeHiddenQ */

$schema = $schema ?? adFormSchema();
$includeHiddenQ = $includeHiddenQ ?? false;
?>
<?php if ($includeHiddenQ): ?>
    <input type="hidden" name="q" value="<?= h($search) ?>">
<?php endif; ?>

<select class="filter-select" name="type">
    <option value="">Svi tipovi oglasa</option>
    <option value="telefon" <?= $type === 'telefon' ? 'selected' : '' ?>>Telefoni</option>
    <option value="delovi" <?= $type === 'delovi' ? 'selected' : '' ?>>Delovi</option>
    <option value="servis" <?= $type === 'servis' ? 'selected' : '' ?>>Servisne usluge</option>
</select>

<select class="filter-select" name="listing_type">
    <option value="">Prodaja / kupovina / …</option>
    <?php foreach ($schema['listing_types'] as $ltKey => $ltLabel): ?>
        <option value="<?= h($ltKey) ?>" <?= $listingType === $ltKey ? 'selected' : '' ?>><?= h($ltLabel) ?></option>
    <?php endforeach; ?>
</select>

<select class="filter-select" name="brand">
    <option value="">Svi brendovi</option>
    <?php foreach ($cfg['brands'] as $b): ?>
        <option value="<?= h($b) ?>" <?= $brand === $b ? 'selected' : '' ?>><?= h($b) ?></option>
    <?php endforeach; ?>
</select>

<select class="filter-select" name="location">
    <option value="">Svi gradovi</option>
    <?php foreach ($cfg['cities'] as $city): ?>
        <option value="<?= h($city) ?>" <?= $location === $city ? 'selected' : '' ?>><?= h($city) ?></option>
    <?php endforeach; ?>
</select>

<?php
$categoryGroup = $categoryGroup ?? '';
if ($categoryGroup !== '' && !empty($cfg['groups'][$categoryGroup]['models'])):
?>
    <input type="hidden" name="category_group" value="<?= h($categoryGroup) ?>">
    <select class="filter-select" name="model">
        <option value="">Svi modeli</option>
        <?php foreach ($cfg['groups'][$categoryGroup]['models'] as $m): ?>
            <option value="<?= h($m) ?>" <?= $model === $m ? 'selected' : '' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>

<select class="filter-select" name="condition">
    <option value="">Sva stanja</option>
    <?php foreach ($cfg['conditions'] as $st): ?>
        <option value="<?= h($st) ?>" <?= $condition === $st ? 'selected' : '' ?>><?= h($st) ?></option>
    <?php endforeach; ?>
</select>

<select class="filter-select" name="storage">
    <option value="">Memorija (sve)</option>
    <?php foreach ($schema['storage_options'] as $st): ?>
        <option value="<?= h($st) ?>" <?= $storage === $st ? 'selected' : '' ?>><?= h($st) ?></option>
    <?php endforeach; ?>
</select>

<select class="filter-select" name="equipment_type">
    <option value="">Tip opreme (sve)</option>
    <?php foreach ($schema['equipment_types'] as $eq): ?>
        <option value="<?= h($eq) ?>" <?= $equipmentType === $eq ? 'selected' : '' ?>><?= h($eq) ?></option>
    <?php endforeach; ?>
</select>

<input class="filter-select" type="number" name="min_price" placeholder="Cena od (€)" value="<?= h($minPrice) ?>" min="0" step="1">
<input class="filter-select" type="number" name="max_price" placeholder="Cena do (€)" value="<?= h($maxPrice) ?>" min="0" step="1">

<select class="filter-select" name="sort">
    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnovije</option>
    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Cena rastuće</option>
    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Cena opadajuće</option>
</select>

<label class="filter-check">
    <input type="checkbox" name="only_priced" value="1" <?= $onlyPriced ? 'checked' : '' ?>>
    Samo sa cenom (€)
</label>
<label class="filter-check">
    <input type="checkbox" name="only_photos" value="1" <?= $onlyPhotos ? 'checked' : '' ?>>
    Samo sa fotografijom
</label>
