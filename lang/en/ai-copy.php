<?php

return [
    'command' => [
        'description' => 'Generate AI descriptions for menu items without a note',
        'no_key' => 'No DeepSeek API key configured. Set DEEPSEEK_API_KEY in your .env file to generate AI copy.',
        'nothing_to_do' => 'No menu items need a description.',
        'ok' => ':name -> ok',
        'skipped_no_key' => ':name -> skipped no key',
        'failed' => ':name -> failed: :reason',
    ],

    'form' => [
        'generate_label' => 'Generate with AI',
    ],

    'notification' => [
        'name_required' => 'Menu name required',
        'generated' => 'AI description generated',
        'no_key_title' => 'No DeepSeek API key configured',
        'no_key_body' => 'Set DEEPSEEK_API_KEY in your .env file to generate AI copy.',
        'failed_title' => 'Failed to generate description',
        'failed_body' => 'Reason: :reason',
    ],
];
