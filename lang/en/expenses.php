<?php

return [
    'label' => 'Expense',
    'plural_label' => 'Expenses',
    'cash_register_label' => 'Cash Register',
    'cash_register_plural_label' => 'Cash Register Sessions',

    'fields' => [
        'category' => 'Category',
        'description' => 'Description',
        'amount' => 'Amount',
        'spent_at' => 'Date',
        'note' => 'Note',
        'opened_at' => 'Opened At',
        'closed_at' => 'Closed At',
        'opening_float' => 'Opening Float',
        'expected_amount' => 'Expected Amount',
        'counted_amount' => 'Counted Amount',
        'discrepancy' => 'Discrepancy',
        'status' => 'Status',
        'admin' => 'Admin',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'categories' => [
        'ingredients' => 'Ingredients',
        'supplies' => 'Supplies',
        'utilities' => 'Utilities',
        'equipment' => 'Equipment',
        'marketing' => 'Marketing',
        'salaries' => 'Salaries',
        'rent' => 'Rent',
        'other' => 'Other',
    ],

    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],

    'actions' => [
        'create' => 'New Expense',
    ],

    'notifications' => [
        'created' => 'Expense recorded.',
        'session_created' => 'Cash register session opened.',
    ],

    'hints' => [
        'expected_formula' => 'Expected = opening float + order revenue within the session window.',
    ],

    'empty' => [
        'expenses_heading' => 'No expenses yet',
        'expenses_description' => 'Record your first expense.',
        'sessions_heading' => 'No cash register sessions yet',
        'sessions_description' => 'Open your first cash register session.',
    ],
];
