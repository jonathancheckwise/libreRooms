<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Panneau d'administration du site (admin.pepite-lausanne.ch) — voir
    // app/Http/Controllers/PepiteSsoController.php
    'pepite_sso' => [
        'secret' => env('PEPITE_SSO_SECRET', ''),
        'redirects' => explode(',', env('PEPITE_SSO_REDIRECTS', 'https://admin.pepite-lausanne.ch/')),
    ],

    // Intégration comptable bexio (voir app/Services/Bexio/BexioClient.php).
    // Le token (Personal Access Token ou access token OAuth) vit UNIQUEMENT dans
    // .env sur le serveur — jamais en dur dans le code ni dans le dépôt.
    'bexio' => [
        'token' => env('BEXIO_TOKEN'),
        'base_url' => env('BEXIO_BASE_URL', 'https://api.bexio.com'),
        // IDs propres au compte bexio de La Pépite (remplis après exploration) :
        'user_id' => env('BEXIO_USER_ID'),          // utilisateur bexio émetteur
        'bank_account_id' => env('BEXIO_BANK_ACCOUNT_ID'), // compte bancaire (QR-facture)
        'account_id' => env('BEXIO_ACCOUNT_ID'),    // compte de produit (chiffre d'affaires)
        'tax_id' => env('BEXIO_TAX_ID'),            // taux de TVA par défaut
        'currency_id' => env('BEXIO_CURRENCY_ID'),  // CHF
        'language_id' => env('BEXIO_LANGUAGE_ID'),  // français
        'country_id' => env('BEXIO_COUNTRY_ID'),    // Suisse (contacts)
        'payment_term_days' => (int) env('BEXIO_PAYMENT_TERM_DAYS', 30),
        // Date de bascule : seules les réservations à partir de cette date sont
        // facturées automatiquement (avant = géré manuellement par les admins).
        'billing_start' => env('BEXIO_BILLING_START', '2026-10-19'),
    ],

];
