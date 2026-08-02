<?php

return [
    'label' => 'Pesanan Pembelian',
    'plural_label' => 'Pesanan Pembelian',
    'navigation_group' => 'Pengadaan',

    'fields' => [
        'supplier' => 'Pemasok',
        'ordered_at' => 'Tanggal Pesan',
        'expected_at' => 'Tanggal Tiba',
        'status' => 'Status',
        'total' => 'Total',
        'note' => 'Catatan',
        'quantity' => 'Jumlah',
        'unit_price' => 'Harga Satuan',
        'description' => 'Deskripsi',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'statuses' => [
        'pending' => 'Menunggu',
        'received' => 'Diterima',
        'cancelled' => 'Dibatalkan',
    ],

    'relation' => [
        'items' => [
            'label' => 'Item Pesanan',
            'empty_heading' => 'Belum ada item pesanan',
        ],
    ],
];
