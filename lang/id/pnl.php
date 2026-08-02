<?php

return [
    'title' => 'Laporan Laba Rugi',
    'navigation' => 'Laba Rugi',

    'description' => 'Ringkasan pendapatan, HPP, dan beban untuk periode yang dipilih.',

    'period' => [
        'from' => 'Dari',
        'to' => 'Sampai',
        'invalid' => 'Tanggal "Dari" tidak boleh setelah tanggal "Sampai".',
        'empty' => 'Tidak ada data pada periode ini.',
    ],

    'summary' => [
        'revenue' => 'Pendapatan',
        'orders_count' => 'Jumlah Pesanan',
        'items_sold' => 'Item Terjual',
        'inventory_value' => 'Nilai Persediaan',
    ],

    'statement' => [
        'revenue' => 'Pendapatan',
        'cogs' => 'HPP (Bahan Baku)',
        'gross_margin' => 'Laba Kotor',
        'expenses_title' => 'Beban Operasional',
        'net_margin' => 'Laba Bersih',
    ],

    'margins' => [
        'gross' => 'Margin Kotor',
        'net' => 'Margin Bersih',
    ],
];
