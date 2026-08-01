<?php

return [
    'command' => [
        'description' => 'Buat deskripsi AI untuk item menu tanpa catatan',
        'no_key' => 'Kunci API DeepSeek belum dikonfigurasi. Atur DEEPSEEK_API_KEY di file .env Anda untuk membuat copy AI.',
        'nothing_to_do' => 'Tidak ada item menu yang membutuhkan deskripsi.',
        'ok' => ':name -> ok',
        'skipped_no_key' => ':name -> dilewati tanpa kunci',
        'failed' => ':name -> gagal: :reason',
    ],

    'form' => [
        'generate_label' => 'Buat dengan AI',
    ],

    'notification' => [
        'name_required' => 'Nama menu wajib diisi',
        'generated' => 'Deskripsi AI berhasil dibuat',
        'no_key_title' => 'Kunci API DeepSeek belum dikonfigurasi',
        'no_key_body' => 'Atur DEEPSEEK_API_KEY di file .env Anda untuk membuat copy AI.',
        'failed_title' => 'Gagal membuat deskripsi',
        'failed_body' => 'Penyebab: :reason',
    ],
];
