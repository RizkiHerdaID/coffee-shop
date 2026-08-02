<?php

return [
    'subject' => [
        'daily' => 'Ringkasan Penjualan :date',
        'weekly' => 'Ringkasan Penjualan :start - :end',
    ],

    'greeting' => 'Halo,',

    'intro' => 'Berikut ringkasan penjualan untuk periode :start hingga :end.',

    'stats' => [
        'revenue' => 'Total Pendapatan',
        'orders_count' => 'Jumlah Pesanan',
        'avg_order' => 'Rata-rata Nilai Pesanan',
        'top_items' => 'Produk Terlaris',
        'empty' => 'Tidak ada penjualan pada periode ini.',
    ],

    'table' => [
        'item' => 'Produk',
        'qty' => 'Jumlah',
        'revenue' => 'Pendapatan',
    ],

    'footer' => ':shop — :address',

    'command' => [
        'running' => 'Menyiapkan ringkasan penjualan :period...',
        'queued' => 'Ringkasan penjualan :period diantrekan untuk :recipient.',
        'no_recipient' => 'Penerima ringkasan belum dikonfigurasi (atur SUMMARY_RECIPIENT atau MAIL_FROM_ADDRESS).',
        'error' => 'Gagal mengirim ringkasan penjualan: :error',
    ],
];
