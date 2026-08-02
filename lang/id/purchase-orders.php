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
        'stock_item' => 'Item Stok',
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

    'actions' => [
        'export' => 'Ekspor CSV/XLSX',
        'receive' => 'Terima Pesanan',
        'receive_confirm' => 'Stok akan bertambah sesuai item yang terhubung, dan status berubah menjadi Diterima.',
        'receive_submit' => 'Terima',
    ],

    'notifications' => [
        'received_success' => 'Pesanan diterima — :count item ditambahkan ke stok.',
        'receive_note' => 'Penerimaan PO #:id',
    ],

    'restock' => [
        'navigation' => 'Saran Restok',
        'heading' => 'Saran Restok',
        'description' => 'Item stok yang berada di atau di bawah batas minimum. Gunakan jumlah saran untuk membuat pesanan pembelian baru.',
        'create_po' => 'Buat Pesanan Pembelian',
        'manage_stock' => 'Kelola Stok',
        'empty_heading' => 'Tidak ada item yang perlu direstok',
        'empty_description' => 'Semua item stok berada di atas batas minimumnya.',
    ],

    'exports' => [
        'completed' => 'Ekspor pesanan pembelian selesai: :count baris.',
        'failed' => ':failed_count baris gagal.',
    ],
];
