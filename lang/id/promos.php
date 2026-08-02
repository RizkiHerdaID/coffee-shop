<?php

return [
    'label' => 'Promo',
    'plural_label' => 'Promo',

    'fields' => [
        'title' => 'Judul',
        'subtitle' => 'Subjudul',
        'badge' => 'Label',
        'cta_text' => 'Teks Tombol',
        'cta_url' => 'URL Tombol',
        'starts_at' => 'Mulai',
        'ends_at' => 'Berakhir',
        'active' => 'Aktif',
        'sort_order' => 'Urutan',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'empty' => [
        'heading' => 'Belum ada promo',
        'description' => 'Buat promo pertama Anda untuk tampil di banner situs.',
    ],

    'ai' => [
        'generate_label' => 'Buat dengan AI',
        'notification' => [
            'title_required' => 'Judul promo wajib diisi',
            'generated' => 'Subjudul AI berhasil dibuat',
            'no_key_title' => 'Kunci API DeepSeek belum dikonfigurasi',
            'no_key_body' => 'Atur DEEPSEEK_API_KEY di file .env Anda untuk membuat copy AI.',
            'failed_title' => 'Gagal membuat subjudul',
            'failed_body' => 'Penyebab: :reason',
        ],
    ],
];
