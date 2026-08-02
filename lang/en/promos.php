<?php

return [
    'label' => 'Promo',
    'plural_label' => 'Promos',

    'fields' => [
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'badge' => 'Badge',
        'cta_text' => 'Button Text',
        'cta_url' => 'Button URL',
        'starts_at' => 'Starts At',
        'ends_at' => 'Ends At',
        'active' => 'Active',
        'sort_order' => 'Sort Order',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'empty' => [
        'heading' => 'No promos yet',
        'description' => 'Create your first promo to show it on the site banner.',
    ],

    'ai' => [
        'generate_label' => 'Generate with AI',
        'notification' => [
            'title_required' => 'The promo title is required',
            'generated' => 'AI subtitle generated',
            'no_key_title' => 'DeepSeek API key is not configured',
            'no_key_body' => 'Set DEEPSEEK_API_KEY in your .env file to generate AI copy.',
            'failed_title' => 'Failed to generate subtitle',
            'failed_body' => 'Reason: :reason',
        ],
    ],
];
