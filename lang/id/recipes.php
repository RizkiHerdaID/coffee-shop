<?php

return [
    'label' => 'Resep',
    'plural_label' => 'Resep',

    'fields' => [
        'stock_item' => 'Bahan Baku',
        'quantity' => 'Jumlah',
        'quantity_help' => 'Jumlah yang dipakai per porsi, dalam satuan bahan (mis. 18 gram, 250 ml).',
        'cost' => 'Biaya per Satuan',
        'cost_help' => 'Harga beli rata-rata per satuan, dipakai untuk menghitung HPP.',
        'cogs' => 'HPP',
        'cogs_help' => 'Biaya bahan baku per porsi (HPP = harga pokok penjualan).',
        'margin' => 'Margin',
    ],

    'cogs' => [
        'label' => 'HPP',
        'tooltip' => 'Harga Pokok Penjualan',
    ],

    'margin' => [
        'label' => 'Margin',
        'tooltip' => 'Harga jual dikurangi HPP',
    ],

    'empty' => [
        'heading' => 'Belum ada bahan',
        'description' => 'Tambahkan bahan baku dan jumlahnya untuk menghitung HPP item ini.',
    ],

    'notifications' => [
        'saved' => 'Resep diperbarui.',
    ],
];
