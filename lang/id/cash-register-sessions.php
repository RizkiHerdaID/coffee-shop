<?php

return [
    'label' => 'Kas Register',
    'plural_label' => 'Kas Register',

    'fields' => [
        'opened_at' => 'Dibuka Pada',
        'closed_at' => 'Ditutup Pada',
        'opening_float' => 'Uang Awal',
        'expected_amount' => 'Jumlah Diharapkan',
        'counted_amount' => 'Jumlah Dihitung',
        'discrepancy' => 'Selisih',
        'status' => 'Status',
        'admin' => 'Petugas',
        'created_at' => 'Dibuat',
    ],

    'status' => [
        'open' => 'Buka',
        'closed' => 'Tutup',
    ],

    'hints' => [
        'expected_formula' => 'Jumlah diharapkan = uang awal + total penjualan (order) dalam rentang sesi.',
    ],

    'empty' => [
        'sessions_heading' => 'Belum ada sesi kas',
        'sessions_description' => 'Buka sesi kas pertama Anda.',
    ],
];
