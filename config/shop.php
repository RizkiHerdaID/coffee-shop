<?php

return [
    'name' => 'Coffee Shop',

    'tables' => 4,

    'phone' => '+6281234567890',
    'phone_display' => '+62 812-3456-7890',

    'gofood_url' => 'https://gofood.co.id/your-merchant',
    'grab_url' => 'https://grab.com/your-merchant',

    'email' => 'hello@coffee-shop.example',

    'address' => "Jl. Contoh Raya No. 123\nJakarta Selatan, Indonesia",

    'hours' => [
        'mon_fri' => '07:00 — 18:00',
        'sat' => '08:00 — 20:00',
        'sun' => '08:00 — 16:00',
    ],

    'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode("Jl. Contoh Raya No. 123\nJakarta Selatan, Indonesia"),
];
