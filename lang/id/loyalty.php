<?php

return [
    'label' => 'Loyalitas',
    'plural_label' => 'Loyalitas',

    'fields' => [
        'phone' => 'No. HP',
        'stamps' => 'Stempel',
        'free_drinks' => 'Minuman Gratis',
        'redeemed' => 'Ditebus',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'actions' => [
        'grant' => 'Berikan Stempel',
        'grant_heading' => 'Berikan Stempel ke Nomor HP',
        'grant_submit' => 'Berikan',
        'grant_qty' => 'Jumlah',
        'adjust' => 'Sesuaikan Stempel',
        'adjust_heading' => 'Sesuaikan Stempel',
        'adjust_submit' => 'Simpan',
        'adjust_qty' => 'Jumlah',
        'redeem' => 'Tebus Gratis',
        'redeem_heading' => 'Tebus Minuman Gratis',
        'redeem_submit' => 'Tebus',
        'redeem_confirm' => 'Tebus satu minuman gratis dengan 10 stempel?',
    ],

    'hints' => [
        'adjust' => 'Gunakan angka negatif untuk mengurangi stempel (tidak bisa di bawah 0).',
    ],

    'notifications' => [
        'granted' => 'Stempel diberikan.',
        'adjusted' => 'Stempel diperbarui.',
        'redeemed' => 'Minuman gratis ditebus.',
        'redeem_failed' => 'Stempel belum mencapai 10 — tidak bisa ditebus.',
    ],

    'empty' => [
        'heading' => 'Belum ada kartu loyalitas',
        'description' => 'Kartu dibuat otomatis saat pesanan lunas dicatat dengan nomor HP.',
    ],
];
