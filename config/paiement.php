<?php

// Tous les réglages de paiement. Les valeurs viennent du fichier .env.
return [

    'lightning' => [
        'url' => env('LIGHTNING_API_URL'),
        'cle' => env('LIGHTNING_API_KEY'),
        'expiration_minutes' => (int) env('LIGHTNING_EXPIRATION_MINUTES', 5),
        // Les webhooks LNbits ne sont pas signés : on protège l'adresse par un secret.
        'secret_webhook' => env('LIGHTNING_WEBHOOK_SECRET'),
    ],

    'taux' => [
        'url' => env('RATE_API_URL', 'https://api.coingecko.com/api/v3/simple/price'),
        'cache_secondes' => (int) env('RATE_CACHE_SECONDS', 60),
        'xof_par_eur' => (float) env('XOF_PER_EUR', 655.957),
        'marge_pourcent' => (float) env('SATS_SAFETY_MARGIN_PERCENT', 0.5),
    ],

    'paydunya' => [
        'master_key' => env('PAYDUNYA_MASTER_KEY'),
        'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
        'public_key' => env('PAYDUNYA_PUBLIC_KEY'),
        'token' => env('PAYDUNYA_TOKEN'),
        'mode' => env('PAYDUNYA_MODE', 'test'), // test | live
    ],

];
