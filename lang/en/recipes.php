<?php

return [
    'label' => 'Recipe',
    'plural_label' => 'Recipes',

    'fields' => [
        'stock_item' => 'Ingredient',
        'quantity' => 'Quantity',
        'quantity_help' => 'Amount used per serving, in the ingredient unit (e.g. 18 grams, 250 ml).',
        'cost' => 'Unit Cost',
        'cost_help' => 'Average purchase price per unit, used to compute COGS.',
        'cogs' => 'COGS',
        'cogs_help' => 'Ingredient cost per serving (cost of goods sold).',
        'margin' => 'Margin',
    ],

    'cogs' => [
        'label' => 'COGS',
        'tooltip' => 'Cost of goods sold',
    ],

    'margin' => [
        'label' => 'Margin',
        'tooltip' => 'Selling price minus COGS',
    ],

    'empty' => [
        'heading' => 'No ingredients yet',
        'description' => 'Add ingredients and their quantities to compute this item\'s COGS.',
    ],

    'notifications' => [
        'saved' => 'Recipe updated.',
    ],
];
