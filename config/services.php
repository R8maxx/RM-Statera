<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gotenberg
    |--------------------------------------------------------------------------
    |
    | API en contenedor sobre Chromium y LibreOffice para la generación
    | documental (SoA, DdA, plan de adecuación, actas). Versión anclada, nunca
    | el tag '8', y nunca alcanzable desde fuera de la red interna: descarga
    | las URL que se le pasen, así que exponerlo es un SSRF de manual.
    |
    */

    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://127.0.0.1:3000'),

        /*
         * Segundo eslabón de una cadena que tiene que quedar en este orden:
         *
         *     --api-timeout=120s  <  este cliente  <  timeout del job (300 s)
         *
         * Con el cliente HTTP que se descubre por defecto (30 s), una SoA grande
         * falla de forma intermitente y el error apunta a Gotenberg, que no
         * tiene ninguna culpa.
         */
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
