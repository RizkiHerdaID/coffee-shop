<?php

return [
    'label' => 'Loyalty',
    'plural_label' => 'Loyalty',

    'fields' => [
        'phone' => 'Phone',
        'stamps' => 'Stamps',
        'free_drinks' => 'Free Drinks',
        'redeemed' => 'Redeemed',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'actions' => [
        'grant' => 'Grant Stamps',
        'grant_heading' => 'Grant Stamps to a Phone Number',
        'grant_submit' => 'Grant',
        'grant_qty' => 'Quantity',
        'adjust' => 'Adjust Stamps',
        'adjust_heading' => 'Adjust Stamps',
        'adjust_submit' => 'Save',
        'adjust_qty' => 'Quantity',
        'redeem' => 'Redeem Free Drink',
        'redeem_heading' => 'Redeem Free Drink',
        'redeem_submit' => 'Redeem',
        'redeem_confirm' => 'Redeem one free drink for 10 stamps?',
    ],

    'hints' => [
        'adjust' => 'Use a negative number to remove stamps (cannot go below 0).',
    ],

    'notifications' => [
        'granted' => 'Stamps granted.',
        'adjusted' => 'Stamps updated.',
        'redeemed' => 'Free drink redeemed.',
        'redeem_failed' => 'Not enough stamps (need at least 10) — cannot redeem.',
    ],

    'empty' => [
        'heading' => 'No loyalty cards yet',
        'description' => 'Cards are created automatically when a paid order is recorded with a phone number.',
    ],
];
