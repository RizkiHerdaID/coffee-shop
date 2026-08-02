<?php

return [
    'label' => 'Reservation',
    'plural_label' => 'Reservations',
    'navigation' => 'Table Reservations',

    'fields' => [
        'name' => 'Name',
        'phone' => 'WhatsApp',
        'party_size' => 'Party Size',
        'date' => 'Date',
        'time' => 'Time',
        'status' => 'Status',
        'notes' => 'Notes',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'status' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
    ],

    'actions' => [
        'create' => 'Create Reservation',
        'edit' => 'Edit',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
    ],

    'notifications' => [
        'created' => 'Reservation created.',
        'confirmed' => 'Reservation confirmed.',
        'cancelled' => 'Reservation cancelled.',
    ],

    'flash' => [
        'success' => 'Thank you! Your reservation has been received and we will confirm it via WhatsApp.',
    ],

    'form' => [
        'heading' => 'Table Reservation',
        'subheading' => 'Book a table and enjoy our coffee comfortably.',
        'name' => 'Full Name',
        'name_placeholder' => 'Your name',
        'phone' => 'WhatsApp Number',
        'phone_placeholder' => '08xxxxxxxxxx',
        'party_size' => 'Party Size',
        'party_size_placeholder' => 'e.g. 2',
        'date' => 'Date',
        'time' => 'Time',
        'notes' => 'Notes (optional)',
        'notes_placeholder' => 'Special requests, e.g. outdoor seating',
        'submit' => 'Send Reservation',
        'submitting' => 'Sending…',
        'success' => 'Your reservation has been sent. We will confirm via WhatsApp.',
        'invalid_phone' => 'Invalid WhatsApp number format.',
        'party_size_min' => 'Party size must be at least 1.',
    ],

    'empty' => [
        'heading' => 'No reservations yet',
        'description' => 'Table reservations from customers will show up here.',
    ],
];
