<?php

declare(strict_types=1);

/**
 * @param array<string,string> $options key => label
 * @param list<string> $selected
 */
function renderChipGroup(string $name, array $options, array $selected = [], bool $multi = true): void
{
    $inputType = $multi ? 'checkbox' : 'radio';
    echo '<div class="chip-grid">';
    foreach ($options as $value => $label) {
        $val = (string)$value;
        $lab = (string)$label;
        $checked = in_array($val, $selected, true);
        $fieldName = $multi ? $name . '[]' : $name;
        echo '<label class="chip-option' . ($checked ? ' is-on' : '') . '">';
        echo '<input type="' . h($inputType) . '" name="' . h($fieldName) . '" value="' . h($val) . '"' . ($checked ? ' checked' : '') . '>';
        echo '<span>' . h($lab) . '</span>';
        echo '</label>';
    }
    echo '</div>';
}

/**
 * @param list<string> $options
 * @param list<string> $selected
 */
function renderChipGroupList(string $name, array $options, array $selected = []): void
{
    $map = [];
    foreach ($options as $opt) {
        $map[(string)$opt] = (string)$opt;
    }
    renderChipGroup($name, $map, $selected, true);
}
