<?php

return [
    'label' => 'Stock Item',
    'plural_label' => 'Stock Items',

    'fields' => [
        'name' => 'Name',
        'unit' => 'Unit',
        'unit_placeholder' => 'grams / liters / pcs',
        'quantity' => 'Quantity',
        'min_threshold' => 'Min Threshold',
        'note' => 'Note',
        'type' => 'Type',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'badges' => [
        'low' => 'Low',
    ],

    'movements' => [
        'label' => 'Stock Movements',
        'in' => 'In',
        'out' => 'Out',
        'empty_heading' => 'No stock movements yet',
    ],

    'actions' => [
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'submit' => 'Save',
    ],

    'notifications' => [
        'stock_in_success' => 'Stock in recorded.',
        'stock_out_success' => 'Stock out recorded.',
        'stock_out_failed' => 'Insufficient stock.',
    ],

    'empty' => [
        'heading' => 'No stock items yet',
        'description' => 'Create your first stock item to start tracking inventory.',
    ],
];
