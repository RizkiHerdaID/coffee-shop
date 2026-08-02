<?php

return [
    'label' => 'Supplier',
    'plural_label' => 'Suppliers',
    'navigation_group' => 'Procurement',

    'fields' => [
        'name' => 'Name',
        'contact_person' => 'Contact person',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'note' => 'Note',
        'supplier' => 'Supplier',
        'ordered_at' => 'Order date',
        'expected_at' => 'Expected date',
        'status' => 'Status',
        'total' => 'Total',
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

    'scorecard' => [
        'orders_count' => 'PO count',
        'total_spend' => 'Total spend',
        'outstanding' => 'Outstanding POs',
        'avg_lead_time' => 'Avg lead time',
        'on_time_rate' => 'On-time rate',
        'days' => 'days',
    ],

    'relation' => [
        'items' => [
            'label' => 'Order Items',
            'empty_heading' => 'No order items yet',
        ],
    ],
];
