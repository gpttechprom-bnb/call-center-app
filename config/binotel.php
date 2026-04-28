<?php

return [
    'log_channel' => env('BINOTEL_LOG_CHANNEL', 'binotel_webhook'),

    'feedback' => [
        'api_key' => env('BINOTEL_FEEDBACK_API_KEY', 'uk6Wd0F1ISo7'),
        'default_counter' => (int) env('BINOTEL_FEEDBACK_DEFAULT_COUNTER', 100),
        'max_counter' => (int) env('BINOTEL_FEEDBACK_MAX_COUNTER', 500),
    ],

    'api' => [
        'key' => env('BINOTEL_API_KEY', ''),
        'secret' => env('BINOTEL_API_SECRET', ''),
        'host' => env('BINOTEL_API_HOST', 'https://api.binotel.com/api/'),
        'version' => env('BINOTEL_API_VERSION', '4.0'),
        'format' => env('BINOTEL_API_FORMAT', 'json'),
        'timeout' => (int) env('BINOTEL_API_TIMEOUT', 20),
        'disable_ssl_checks' => filter_var(
            env('BINOTEL_API_DISABLE_SSL_CHECKS', false),
            FILTER_VALIDATE_BOOL
        ),
        'debug' => filter_var(env('BINOTEL_API_DEBUG', false), FILTER_VALIDATE_BOOL),
    ],

    'allowed_servers' => [
        '45.91.130.36' => 'api.binotel.com',
        '45.91.130.51' => 'sip1.binotel.com',
        '45.91.130.52' => 'sip2.binotel.com',
        '45.91.130.53' => 'sip3.binotel.com',
        '45.91.130.54' => 'sip4.binotel.com',
        '45.91.130.55' => 'sip5.binotel.com',
        '45.91.130.56' => 'sip6.binotel.com',
        '45.91.130.57' => 'sip7.binotel.com',
        '45.91.130.58' => 'sip8.binotel.com',
        '45.91.130.59' => 'sip9.binotel.com',
        '45.91.130.60' => 'sip10.binotel.com',
        '45.91.130.61' => 'sip11.binotel.com',
        '45.91.130.62' => 'sip12.binotel.com',
        '45.91.130.63' => 'sip13.binotel.com',
        '45.91.130.64' => 'sip14.binotel.com',
        '45.91.130.65' => 'sip15.binotel.com',
        '45.91.130.66' => 'sip16.binotel.com',
        '45.91.130.67' => 'sip17.binotel.com',
        '45.91.130.68' => 'sip18.binotel.com',
        '45.91.130.69' => 'sip19.binotel.com',
        '45.91.130.70' => 'sip20.binotel.com',
        '45.91.130.71' => 'sip21.binotel.com',
        '45.91.130.72' => 'sip22.binotel.com',
        '45.91.130.73' => 'sip23.binotel.com',
        '45.91.130.74' => 'sip24.binotel.com',
        '45.91.130.75' => 'sip25.binotel.com',
        '45.91.130.76' => 'sip26.binotel.com',
        '45.91.130.77' => 'sip27.binotel.com',
        '45.91.130.78' => 'sip28.binotel.com',
        '45.91.130.79' => 'sip29.binotel.com',
        '45.91.130.80' => 'sip30.binotel.com',
        '45.91.130.81' => 'sip31.binotel.com',
        '45.91.130.82' => 'sip32.binotel.com',
        '45.91.129.203' => 'GetCall ACS Server',
        '194.180.10.12' => 'Local server',
    ],
];
