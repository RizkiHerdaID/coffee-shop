<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POS printer (58mm thermal)
    |--------------------------------------------------------------------------
    |
    | Connection settings for the thermal receipt printer consumed by the
    | PrintReceipt / PrintKitchenTicket queued jobs (mike42/escpos-php).
    | Leave disabled when no printer is attached: jobs then log and return,
    | and the cashier falls back to the browser-print receipt view.
    |
    */

    'printer' => [
        'enabled' => (bool) env('POS_PRINTER_ENABLED', false),

        // Supported connections: network | file | windows
        'connection' => env('POS_PRINTER_CONNECTION', 'network'),

        // Network: hostname/IP of the printer; file: path to the raw device
        // (e.g. /dev/usb/lp0); windows: "printer name" via SMB.
        'address' => env('POS_PRINTER_ADDRESS'),

        'port' => (int) env('POS_PRINTER_PORT', 9100),

        // Character columns per line on the 58mm roll (default 32).
        'chars_per_line' => (int) env('POS_PRINTER_CHARS_PER_LINE', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Static QRIS
    |--------------------------------------------------------------------------
    |
    | Path (relative to /public) to the merchant's static QRIS image shown on
    | the cashier page when the cashier picks the QRIS payment method. When
    | empty, a localized placeholder box is rendered instead.
    |
    */

    'qris' => [
        'image' => env('POS_QRIS_IMAGE'),
    ],

];
