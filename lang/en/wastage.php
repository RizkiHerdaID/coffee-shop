<?php

return [
    'label' => 'Wastage',
    'plural_label' => 'Wastage',
    'navigation' => 'Stock Wastage',

    'fields' => [
        'stock_item' => 'Stock Item',
        'quantity' => 'Quantity',
        'reason' => 'Reason',
        'note' => 'Note',
        'admin' => 'Admin',
        'recorded_at' => 'Recorded At',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'reasons' => [
        'spilled' => 'Spilled',
        'expired' => 'Expired',
        'damaged' => 'Damaged',
        'other' => 'Other',
    ],

    'actions' => [
        'create' => 'Record Wastage',
    ],

    'validation' => [
        'quantity_exceeds_stock' => 'Quantity exceeds available stock.',
    ],

    'notifications' => [
        'created' => 'Wastage recorded.',
        'movement_note' => 'Stock wastage #:id',
    ],

    'empty' => [
        'heading' => 'No wastage yet',
        'description' => 'Record your first stock wastage (spilled, expired, damaged, etc.).',
        'relation_heading' => 'No wastage for this item yet',
    ],
];
