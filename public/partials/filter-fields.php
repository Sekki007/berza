<?php
declare(strict_types=1);
/**
 * Shared listing filters (sidebar + mobile drawer).
 * Expects: $cfg, $schema, $type, $deviceType, $brand, $location, $condition,
 * $minPrice, $maxPrice, $model, $categoryGroup, $sort, $search, $filterLayout
 * $filterLayout: 'sidebar' | 'drawer'
 */
$filterLayout = $filterLayout ?? 'sidebar';
$typeOptions = [
    '' => 'Sve',
    'telefon' => 'Uređaji',
    'delovi' => 'Oprema',
    'servis' => 'Servis',
];
$pricePresets = [
    ['label' => 'do 100€', 'min' => '', 'max' => '100'],
    ['label' => '100–300€', 'min' => '100', 'max' => '300'],
    ['label' => '300–600€', 'min' => '300', 'max' => '600'],
    ['label' => '600€+', 'min' => '600', 'max' => ''],
];
$uid = $filterLayout === 'drawer' ? 'm' : 'd';
?>
<?php if ($search !== ''): ?>
    <input type="hidden" name="q" value="<?= h($search) ?>">
<?php endif; ?>
<input type="hidden" name="sort" value="<?= h($sort) ?>">
<?php if ($categoryGroup !== ''): ?>
    <input type="hidden" name="category_group" value="<?= h($categoryGroup) ?>">
<?php endif; ?>
<?php if (!empty($equipmentGroup)): ?>
    <input type="hidden" name="equipment_group" value="<?= h((string)$equipmentGroup) ?>">
<?php endif; ?>

<div class="filter-field">
    <span class="filter-label" id="filter-type-<?= h($uid) ?>">Tip oglasa</span>
    <div class="filter-chips" role="radiogroup" aria-labelledby="filter-type-<?= h($uid) ?>">
        <?php foreach ($typeOptions as $val => $label): ?>
            <label class="filter-chip<?= $type === $val ? ' is-active' : '' ?>">
                <input type="radio" name="type" value="<?= h($val) ?>" <?= $type === $val ? 'checked' : '' ?>>
                <span><?= h($label) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<div class="filter-field">
    <label class="filter-label" for="filter-device-<?= h($uid) ?>">Tip uređaja</label>
    <select class="filter-select" id="filter-device-<?= h($uid) ?>" name="device_type">
        <option value="">Svi tipovi</option>
        <?php foreach ($schema['device_types'] as $dtKey => $dtLabel): ?>
            <option value="<?= h($dtKey) ?>" <?= $deviceType === $dtKey ? 'selected' : '' ?>><?= h($dtLabel) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="filter-field">
    <label class="filter-label" for="filter-brand-<?= h($uid) ?>">Brend</label>
    <select class="filter-select" id="filter-brand-<?= h($uid) ?>" name="brand">
        <option value="">Svi brendovi</option>
        <?php foreach ($cfg['brands'] as $b): ?>
            <option value="<?= h($b) ?>" <?= $brand === $b ? 'selected' : '' ?>><?= h($b) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($categoryGroup !== '' && !empty($cfg['groups'][$categoryGroup]['models'])): ?>
<div class="filter-field">
    <label class="filter-label" for="filter-model-<?= h($uid) ?>">Model</label>
    <select class="filter-select" id="filter-model-<?= h($uid) ?>" name="model">
        <option value="">Svi modeli</option>
        <?php foreach ($cfg['groups'][$categoryGroup]['models'] as $m): ?>
            <option value="<?= h($m) ?>" <?= $model === $m ? 'selected' : '' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="filter-field">
    <label class="filter-label" for="filter-city-<?= h($uid) ?>">Grad</label>
    <select class="filter-select" id="filter-city-<?= h($uid) ?>" name="location">
        <option value="">Svi gradovi</option>
        <?php foreach ($cfg['cities'] as $city): ?>
            <option value="<?= h($city) ?>" <?= $location === $city ? 'selected' : '' ?>><?= h($city) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="filter-field">
    <span class="filter-label" id="filter-cond-<?= h($uid) ?>">Stanje</span>
    <div class="filter-chips filter-chips-wrap" role="radiogroup" aria-labelledby="filter-cond-<?= h($uid) ?>">
        <label class="filter-chip<?= $condition === '' ? ' is-active' : '' ?>">
            <input type="radio" name="condition" value="" <?= $condition === '' ? 'checked' : '' ?>>
            <span>Sve</span>
        </label>
        <?php foreach ($cfg['conditions'] as $st): ?>
            <label class="filter-chip<?= $condition === $st ? ' is-active' : '' ?>">
                <input type="radio" name="condition" value="<?= h($st) ?>" <?= $condition === $st ? 'checked' : '' ?>>
                <span><?= h($st) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<div class="filter-field">
    <span class="filter-label">Cena (€)</span>
    <div class="filter-price-row">
        <input class="filter-select filter-price-input" type="number" name="min_price" inputmode="decimal" min="0" step="1" placeholder="Od" value="<?= h($minPrice) ?>" aria-label="Cena od">
        <span class="filter-price-sep" aria-hidden="true">–</span>
        <input class="filter-select filter-price-input" type="number" name="max_price" inputmode="decimal" min="0" step="1" placeholder="Do" value="<?= h($maxPrice) ?>" aria-label="Cena do">
    </div>
    <div class="filter-price-presets" data-price-presets>
        <?php foreach ($pricePresets as $preset):
            $isActive = (string)$minPrice === (string)$preset['min'] && (string)$maxPrice === (string)$preset['max'];
            ?>
            <button type="button" class="filter-preset<?= $isActive ? ' is-active' : '' ?>"
                    data-min="<?= h($preset['min']) ?>"
                    data-max="<?= h($preset['max']) ?>"><?= h($preset['label']) ?></button>
        <?php endforeach; ?>
    </div>
</div>
