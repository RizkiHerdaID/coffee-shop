<?php

return [
    'label' => 'Pembuangan',
    'plural_label' => 'Pembuangan',
    'navigation' => 'Pembuangan Stok',

    'fields' => [
        'stock_item' => 'Item Stok',
        'quantity' => 'Jumlah',
        'reason' => 'Alasan',
        'note' => 'Catatan',
        'admin' => 'Petugas',
        'recorded_at' => 'Waktu Dicatat',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'reasons' => [
        'spilled' => 'Tumpah',
        'expired' => 'Kadaluarsa',
        'damaged' => 'Rusak',
        'other' => 'Lainnya',
    ],

    'actions' => [
        'create' => 'Catat Pembuangan',
    ],

    'notifications' => [
        'created' => 'Pembuangan dicatat.',
    ],

    'empty' => [
        'heading' => 'Belum ada pembuangan',
        'description' => 'Catat pembuangan stok pertama Anda (tumpah, kadaluarsa, rusak, dll).',
        'relation_heading' => 'Belum ada pembuangan untuk item ini',
    ],
];
