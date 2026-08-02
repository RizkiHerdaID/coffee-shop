<?php

return [
    'confirmation' => 'Hello! We received your order :order_number at :shop. Total: :total. For more info contact :phone. Thank you!',
    'confirmation_with_items' => 'Hello! We received your order :order_number at :shop. Items: :items. Total: :total. For more info contact :phone. Thank you!',

    'reservation' => 'Hello :name! We received your table reservation for :party_size people on :date at :time at :shop. For changes, contact :phone. Thank you!',

    'log' => [
        'no_token' => 'WhatsApp: no Fonnte token configured, skipping send.',
        'exception' => 'WhatsApp: Fonnte request threw an exception.',
        'failed' => 'WhatsApp: Fonnte request failed.',
    ],
];
