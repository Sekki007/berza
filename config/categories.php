<?php

declare(strict_types=1);

return [
    'groups' => [
        'iphone_parts' => [
            'label' => 'iPhone delovi',
            'ad_type' => 'delovi',
            'brand' => 'Apple',
            'equipment_type' => 'Rezervni delovi',
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
            'equipment_type' => 'Rezervni delovi',
            'models' => ['Galaxy S21 Ultra', 'Galaxy S22 Ultra', 'Galaxy S23 Ultra', 'Galaxy S24 Ultra', 'Galaxy A52', 'Galaxy A54'],
        ],
        'xiaomi_parts' => [
            'label' => 'Xiaomi / Redmi delovi',
            'ad_type' => 'delovi',
            'brand' => 'Xiaomi',
            'equipment_type' => 'Rezervni delovi',
            'models' => ['Xiaomi 13', 'Xiaomi 13 Pro', 'Xiaomi 14', 'Xiaomi 14 Pro', 'Redmi Note 12', 'Redmi Note 13 Pro'],
        ],
        'huawei_honor_parts' => [
            'label' => 'Huawei / Honor delovi',
            'ad_type' => 'delovi',
            'brand' => 'Huawei',
            'equipment_type' => 'Rezervni delovi',
            'models' => ['P40 Pro', 'P50 Pro', 'Mate 40 Pro', 'Honor 90', 'Honor 200'],
        ],
        'android_parts' => [
            'label' => 'Android delovi (ostali)',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => 'Rezervni delovi',
            'models' => [],
        ],
        'chargers_cables' => [
            'label' => 'Punjači i kablovi',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => 'Punjač/Kabl',
            'models' => [],
        ],
        'cases_protection' => [
            'label' => 'Maske i zaštita',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => 'Maska/Futrola',
            'models' => [],
        ],
        'audio_accessories' => [
            'label' => 'Slušalice i audio oprema',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => 'Slušalice',
            'models' => [],
        ],
        'watch_tablet_accessories' => [
            'label' => 'Dodatna oprema za satove i tablete',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => 'Ostalo',
            'models' => [],
        ],
        'other_parts' => [
            'label' => 'Ostalo — delovi i oprema',
            'ad_type' => 'delovi',
            'brand' => '',
            'equipment_type' => '',
            'models' => [],
        ],
        'phones' => [
            'label' => 'Telefoni',
            'ad_type' => 'telefon',
            'brand' => '',
            'equipment_type' => '',
            'models' => [],
        ],
        'service' => [
            'label' => 'Servis',
            'ad_type' => 'servis',
            'brand' => '',
            'equipment_type' => '',
            'models' => [],
        ],
    ],
];
