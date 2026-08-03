<?php

return [
    'label' => 'Cash Register',
    'plural_label' => 'Cash Register Sessions',

    'fields' => [
        'opened_at' => 'Opened At',
        'closed_at' => 'Closed At',
        'opening_float' => 'Opening Float',
        'expected_amount' => 'Expected Amount',
        'counted_amount' => 'Counted Amount',
        'discrepancy' => 'Discrepancy',
        'status' => 'Status',
        'admin' => 'Admin',
        'created_at' => 'Created At',
    ],

    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],

    'hints' => [
        'expected_formula' => 'Expected = opening float + order revenue within the session window.',
    ],

    'empty' => [
        'sessions_heading' => 'No cash register sessions yet',
        'sessions_description' => 'Open your first cash register session.',
    ],
];
