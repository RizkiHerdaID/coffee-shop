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
        'success_no_wa' => 'Booking received. Confirmation to follow.',
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
        'invalid_phone' => 'Invalid WhatsApp number format.',
        'past_time' => 'Reservation time must be in the future. Pick a time that has not passed today.',
        'closed' => 'That time is outside our opening hours. Please pick a time when we are open.',
        'too_far' => 'Reservations can only be made up to 90 days ahead. Please pick a closer date.',
    ],

    'empty' => [
        'heading' => 'No reservations yet',
        'description' => 'Table reservations from customers will show up here.',
    ],
];
