<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'url' => env('FONNTE_URL', 'https://api.fonnte.com/send'),
    ],
];
