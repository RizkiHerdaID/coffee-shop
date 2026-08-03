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
        'stock_item' => 'Stock Item',
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
        'receive' => 'Receive Order',
        'receive_confirm' => 'Stock will increase for linked items and the status will change to Received.',
        'receive_submit' => 'Receive',
    ],

    'notifications' => [
        'received_success' => 'Purchase order received — :count item(s) added to stock.',
        'receive_note' => 'PO #:id received',
        'zero_total' => 'Order total is 0 — cannot receive this order.',
        'already_received' => 'This order was already received.',
    ],

    'restock' => [
        'navigation' => 'Restock Suggestions',
        'heading' => 'Restock Suggestions',
        'description' => 'Stock items at or below their minimum threshold. Use the suggested quantity to create a new purchase order.',
        'create_po' => 'Create Purchase Order',
        'manage_stock' => 'Manage Stock',
        'empty_heading' => 'No items need restocking',
        'empty_description' => 'All stock items are above their minimum threshold.',
    ],

    'exports' => [
        'completed' => 'Purchase orders export completed: :count rows.',
        'failed' => ':failed_count rows failed.',
    ],
];
