<?php

return [
    'recipient' => env('SUMMARY_RECIPIENT') ?: env('MAIL_FROM_ADDRESS', 'admin@example.com'),

    'daily' => [
        'time' => '08:00',
    ],

    'weekly' => [
        // Cron day-of-week integer (0=Sunday .. 6=Saturday; 1 = Monday).
        // The cron-expression parser used by weeklyOn() rejects day NAMES
        // like 'monday', so this must stay an int.
        'day' => 1,
        'time' => '08:00',
    ],
];
