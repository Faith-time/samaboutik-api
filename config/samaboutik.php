<?php

return [
    'lightning_expiration_minutes' => (int) env('LIGHTNING_EXPIRATION_MINUTES', 5),

    'xof_per_eur'        => (float) env('XOF_PER_EUR', 655.957),
    'rate_api_url'       => env('RATE_API_URL'),
    'rate_cache_seconds' => (int) env('RATE_CACHE_SECONDS', 60),

    'lightning' => [
        'url' => env('LIGHTNING_API_URL'),
        'key' => env('LIGHTNING_API_KEY'),
    ],

    'paydunya' => [
        'mode'        => env('PAYDUNYA_MODE', 'test'),
        'master_key'  => env('PAYDUNYA_MASTER_KEY'),
        'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
        'public_key'  => env('PAYDUNYA_PUBLIC_KEY'),
        'token'       => env('PAYDUNYA_TOKEN'),
    ],

    'ai_key' => env('AI_API_KEY'),
];
