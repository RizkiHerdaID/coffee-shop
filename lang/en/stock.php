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
        'cost' => 'Unit Cost',
        'note' => 'Note',
        'type' => 'Type',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'badges' => [
        'low' => 'Low',
    ],

    'restock' => [
        'suggested_quantity' => 'Suggested Quantity',
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
        'export' => 'Export CSV/XLSX',
    ],

    'exports' => [
        'completed' => 'Stock export completed: :count rows.',
        'failed' => ':failed_count rows failed.',
    ],

    'notifications' => [
        'stock_in_success' => 'Stock in recorded.',
        'stock_in_failed' => 'Failed to record stock in.',
        'stock_out_success' => 'Stock out recorded.',
        'stock_out_failed' => 'Insufficient stock.',
    ],

    'empty' => [
        'heading' => 'No stock items yet',
        'description' => 'Create your first stock item to start tracking inventory.',
    ],

    'alert' => [
        'subject' => 'Low Stock Alert',
        'body' => 'Stock :name is running low: :quantity :unit left (min threshold :threshold). Please reorder soon.',
        'sent' => 'Low stock alerts sent for :count item(s).',
        'none' => 'No low-stock items to alert.',
        'no_phone' => 'Low-stock WhatsApp number not configured (set WHATSAPP_LOW_STOCK_PHONE).',
    ],

    'command' => [
        'description' => 'Send WhatsApp alerts for low-stock items',
    ],
];
