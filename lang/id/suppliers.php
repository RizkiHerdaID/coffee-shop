<?php

return [
    'label' => 'Pemasok',
    'plural_label' => 'Pemasok',
    'navigation_group' => 'Pengadaan',

    'fields' => [
        'name' => 'Nama',
        'contact_person' => 'Nama Kontak',
        'phone' => 'Telepon',
        'email' => 'Email',
        'address' => 'Alamat',
        'note' => 'Catatan',
        'supplier' => 'Pemasok',
        'ordered_at' => 'Tanggal Pesan',
        'expected_at' => 'Tanggal Tiba',
        'status' => 'Status',
        'total' => 'Total',
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

    'scorecard' => [
        'orders_count' => 'Jumlah PO',
        'total_spend' => 'Total Belanja',
        'outstanding' => 'PO Belum Tiba',
        'avg_lead_time' => 'Rata-rata Waktu Tiba',
        'on_time_rate' => 'Tepat Waktu',
        'days' => 'hari',
    ],

    'relation' => [
        'items' => [
            'label' => 'Item Pesanan',
            'empty_heading' => 'Belum ada item pesanan',
        ],
    ],
];
