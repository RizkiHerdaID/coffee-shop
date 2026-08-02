<?php

return [
    'label' => 'Item Stok',
    'plural_label' => 'Stok',

    'fields' => [
        'name' => 'Nama',
        'unit' => 'Satuan',
        'unit_placeholder' => 'gram / liter / pcs',
        'quantity' => 'Jumlah',
        'min_threshold' => 'Batas Minimum',
        'cost' => 'Biaya per Satuan',
        'note' => 'Catatan',
        'type' => 'Tipe',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'badges' => [
        'low' => 'Menipis',
    ],

    'movements' => [
        'label' => 'Riwayat Stok',
        'in' => 'Masuk',
        'out' => 'Keluar',
        'empty_heading' => 'Belum ada pergerakan stok',
    ],

    'actions' => [
        'stock_in' => 'Stok Masuk',
        'stock_out' => 'Stok Keluar',
        'submit' => 'Simpan',
        'export' => 'Ekspor CSV/XLSX',
    ],

    'exports' => [
        'completed' => 'Ekspor stok selesai: :count baris.',
        'failed' => ':failed_count baris gagal.',
    ],

    'notifications' => [
        'stock_in_success' => 'Stok masuk dicatat.',
        'stock_out_success' => 'Stok keluar dicatat.',
        'stock_out_failed' => 'Stok tidak mencukupi.',
    ],

    'empty' => [
        'heading' => 'Belum ada item stok',
        'description' => 'Tambahkan item stok pertama Anda untuk mulai mencatat persediaan.',
    ],

    'alert' => [
        'subject' => 'Peringatan Stok Menipis',
        'body' => 'Stok :name menipis: tersisa :quantity :unit (batas minimum :threshold). Segera lakukan pemesanan ulang.',
        'sent' => 'Peringatan stok menipis terkirim untuk :count item.',
        'none' => 'Tidak ada item stok menipis yang perlu diberitahukan.',
        'no_phone' => 'Nomor WhatsApp untuk peringatan stok belum dikonfigurasi (atur WHATSAPP_LOW_STOCK_PHONE).',
    ],
];
