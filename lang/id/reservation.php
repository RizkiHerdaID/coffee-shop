<?php

return [
    'label' => 'Reservasi',
    'plural_label' => 'Reservasi',
    'navigation' => 'Reservasi Meja',

    'fields' => [
        'name' => 'Nama',
        'phone' => 'No. WhatsApp',
        'party_size' => 'Jumlah Tamu',
        'date' => 'Tanggal',
        'time' => 'Jam',
        'status' => 'Status',
        'notes' => 'Catatan',
        'created_at' => 'Dibuat',
        'updated_at' => 'Diperbarui',
    ],

    'status' => [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'cancelled' => 'Dibatalkan',
    ],

    'actions' => [
        'create' => 'Buat Reservasi',
        'edit' => 'Ubah',
        'confirm' => 'Konfirmasi',
        'cancel' => 'Batalkan',
    ],

    'notifications' => [
        'created' => 'Reservasi dibuat.',
        'confirmed' => 'Reservasi dikonfirmasi.',
        'cancelled' => 'Reservasi dibatalkan.',
    ],

    'flash' => [
        'success' => 'Terima kasih! Reservasi Anda telah kami terima dan akan kami konfirmasi melalui WhatsApp.',
        'success_no_wa' => 'Pesanan diterima. Konfirmasi menyusul.',
    ],

    'form' => [
        'heading' => 'Reservasi Meja',
        'subheading' => 'Pesan meja dan nikmati kopi kami dengan nyaman.',
        'name' => 'Nama Lengkap',
        'name_placeholder' => 'Nama Anda',
        'phone' => 'No. WhatsApp',
        'phone_placeholder' => '08xxxxxxxxxx',
        'party_size' => 'Jumlah Tamu',
        'party_size_placeholder' => 'cth. 2',
        'date' => 'Tanggal',
        'time' => 'Jam',
        'notes' => 'Catatan (opsional)',
        'notes_placeholder' => 'Permintaan khusus, mis. tempat outdoor',
        'submit' => 'Kirim Reservasi',
        'invalid_phone' => 'Format nomor WhatsApp tidak valid.',
        'past_time' => 'Waktu reservasi harus di masa depan. Pilih jam yang belum lewat untuk hari ini.',
        'closed' => 'Jam tersebut di luar jam operasional kami. Silakan pilih jam lain saat kami buka.',
        'too_far' => 'Reservasi hanya dapat dibuat maksimal 90 hari ke depan. Silakan pilih tanggal yang lebih dekat.',
    ],

    'empty' => [
        'heading' => 'Belum ada reservasi',
        'description' => 'Reservasi meja dari pelanggan akan muncul di sini.',
    ],
];
