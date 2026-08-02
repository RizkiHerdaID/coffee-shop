<?php

return [
    'label' => 'Purchase Order',
    'plural_label' => 'Purchase Orders',
    'navigation_group' => 'Procurement',

    'fields' => [
        'supplier' => 'Supplier',
        'ordered_at' => 'Order date',
        'expected_at' => 'Expected date',
        'status' => 'Status',
        'total' => 'Total',
        'note' => 'Note',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'description' => 'Description',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'relation' => [
        'items' => [
            'label' => 'Order Items',
            'empty_heading' => 'No order items yet',
        ],
    ],

    'actions' => [
        'export' => 'Export CSV/XLSX',
    ],

    'exports' => [
        'completed' => 'Purchase orders export completed: :count rows.',
        'failed' => ':failed_count rows failed.',
    ],
];
