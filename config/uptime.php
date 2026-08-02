<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uptime Monitoring
    |--------------------------------------------------------------------------
    |
    | heartbeat_url: optional push/heartbeat endpoint (healthchecks.io ping URL
    | or an Uptime Kuma "Push" monitor URL). When set, the scheduled commands
    | (summary:send daily/weekly, stock:alert-low, pulse:check) ping it after
    | each successful run without overlapping, so an external monitor can alert
    | when the scheduler stops. Null (default) disables the pings entirely.
    |
    | After changing this in production, rebuild the config cache:
    |   php artisan config:cache
    |
    */

    'heartbeat_url' => env('UPTIME_HEARTBEAT_URL'),

];
