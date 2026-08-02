<?php

return [
    'label' => 'Pengeluaran',
    'plural_label' => 'Pengeluaran',
    'cash_register_label' => 'Kas Register',
    'cash_register_plural_label' => 'Kas Register',

    'fields' => [
        'category' => 'Kategori',
        'description' => 'Deskripsi',
        'amount' => 'Jumlah',
        'spent_at' => 'Tanggal',
        'note' => 'Catatan',
        'opened_at' => 'Dibuka Pada',
        'closed_at' => 'Ditutup Pada',
        'opening_float' => 'Uang Awal',
        'expected_amount' => 'Jumlah Diharapkan',
        'counted_amount' => 'Jumlah Dihitung',
        'discrepancy' => 'Selisih',
        'status' => 'Status',
        'admin' => 'Petugas',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'categories' => [
        'ingredients' => 'Bahan Baku',
        'supplies' => 'Perlengkapan',
        'utilities' => 'Utilitas',
        'equipment' => 'Peralatan',
        'marketing' => 'Pemasaran',
        'salaries' => 'Gaji',
        'rent' => 'Sewa',
        'other' => 'Lainnya',
    ],

    'status' => [
        'open' => 'Buka',
        'closed' => 'Tutup',
    ],

    'actions' => [
        'create' => 'Buat Pengeluaran',
    ],

    'notifications' => [
        'created' => 'Pengeluaran dicatat.',
        'session_created' => 'Sesi kas dibuka.',
    ],

    'hints' => [
        'expected_formula' => 'Jumlah diharapkan = uang awal + total penjualan (order) dalam rentang sesi.',
    ],

    'empty' => [
        'expenses_heading' => 'Belum ada pengeluaran',
        'expenses_description' => 'Catat pengeluaran pertama Anda.',
        'sessions_heading' => 'Belum ada sesi kas',
        'sessions_description' => 'Buka sesi kas pertama Anda.',
    ],
];
