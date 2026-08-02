<?php

return [
    'subject' => [
        'daily' => 'Sales Summary :date',
        'weekly' => 'Sales Summary :start - :end',
    ],

    'greeting' => 'Hello,',

    'intro' => 'Here is the sales summary for the period :start to :end.',

    'stats' => [
        'revenue' => 'Total Revenue',
        'orders_count' => 'Orders Count',
        'avg_order' => 'Average Order Value',
        'top_items' => 'Top Items',
        'empty' => 'There were no sales during this period.',
    ],

    'table' => [
        'item' => 'Item',
        'qty' => 'Qty',
        'revenue' => 'Revenue',
    ],

    'footer' => ':shop — :address',

    'command' => [
        'running' => 'Preparing :period sales summary...',
        'queued' => ':period sales summary queued for :recipient.',
        'no_recipient' => 'No summary recipient configured (set SUMMARY_RECIPIENT or MAIL_FROM_ADDRESS).',
        'error' => 'Failed to send sales summary: :error',
    ],
];
