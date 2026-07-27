<?php

declare(strict_types=1);

return [
    'groups' => [
        'iphone_parts' => [
            'label' => 'iPhone delovi',
            'ad_type' => 'delovi',
            'brand' => 'Apple',
            'models' => [
                'iPhone X', 'iPhone XS', 'iPhone XR', 'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max',
                'iPhone 12', 'iPhone 12 mini', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
                'iPhone 13', 'iPhone 13 mini', 'iPhone 13 Pro', 'iPhone 13 Pro Max',
                'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
                'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max',
                'iPhone 16', 'iPhone 16 Pro', 'iPhone 16 Pro Max',
            ],
        ],
        'samsung_parts' => [
            'label' => 'Samsung delovi',
            'ad_type' => 'delovi',
            'brand' => 'Samsung',
            'models' => ['Galaxy S21 Ultra', 'Galaxy S22 Ultra', 'Galaxy S23 Ultra', 'Galaxy S24 Ultra', 'Galaxy A52', 'Galaxy A54'],
        ],
        'phones' => [
            'label' => 'Uređaji',
            'ad_type' => 'telefon',
            'brand' => '',
            'models' => [],
        ],
        'service' => [
            'label' => 'Servisne usluge',
            'ad_type' => 'servis',
            'brand' => '',
            'models' => [],
        ],
    ],
];
