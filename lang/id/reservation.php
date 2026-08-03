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
        'submitting' => 'Mengirim…',
        'success' => 'Reservasi Anda berhasil dikirim. Kami akan mengonfirmasi melalui WhatsApp.',
        'invalid_phone' => 'Format nomor WhatsApp tidak valid.',
        'party_size_min' => 'Jumlah tamu minimal 1.',
        'past_time' => 'Waktu reservasi harus di masa depan. Pilih jam yang belum lewat untuk hari ini.',
    ],

    'empty' => [
        'heading' => 'Belum ada reservasi',
        'description' => 'Reservasi meja dari pelanggan akan muncul di sini.',
    ],
];
