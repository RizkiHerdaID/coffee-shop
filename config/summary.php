<?php

return [
    'recipient' => env('SUMMARY_RECIPIENT') ?: env('MAIL_FROM_ADDRESS', 'admin@example.com'),

    'daily' => [
        'time' => '08:00',
    ],

    'weekly' => [
        'day' => 'monday',
        'time' => '08:00',
    ],
];
