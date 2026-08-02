<?php

return [
    'title' => 'Profit & Loss Report',
    'navigation' => 'Profit & Loss',

    'description' => 'Revenue, COGS, and expenses summary for the selected period.',

    'period' => [
        'from' => 'From',
        'to' => 'To',
        'invalid' => 'The "From" date cannot be after the "To" date.',
        'empty' => 'There is no data for this period.',
    ],

    'summary' => [
        'revenue' => 'Revenue',
        'orders_count' => 'Orders',
        'items_sold' => 'Items Sold',
        'inventory_value' => 'Inventory Value',
    ],

    'statement' => [
        'revenue' => 'Revenue',
        'cogs' => 'COGS (Ingredients)',
        'gross_margin' => 'Gross Profit',
        'expenses_title' => 'Operating Expenses',
        'net_margin' => 'Net Profit',
    ],

    'margins' => [
        'gross' => 'Gross Margin',
        'net' => 'Net Margin',
    ],
];
