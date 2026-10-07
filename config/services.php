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
         *     --api-timeout=120s  <  este cliente  <  timeout del job (600 s)
         *
         * El job hace hasta cuatro llamadas —la medida del índice, la portada,
         * el cuerpo y la unión—, así que su timeout cubre cuatro veces el de la
         * API de Gotenberg, no una.
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

    /*
    | Las dos fuentes que rellenan el alta de una vulnerabilidad a partir de su
    | CVE. **Sólo se les manda el identificador**, desde el servidor, y sólo
    | cuando alguien pulsa «Traer datos»: es la única salida del producto hacia
    | fuera, y por eso se apaga entera con `CVE_CONSULTA_ACTIVA=false`.
    */
    'nvd' => [
        'activa' => (bool) env('CVE_CONSULTA_ACTIVA', true),
        'url' => env('NVD_URL', 'https://services.nvd.nist.gov/rest/json/cves/2.0'),
        // Sin clave, NVD admite unas 5 consultas cada 30 s: sobra para dar de alta a mano.
        'clave' => env('NVD_API_KEY'),
        'timeout' => (int) env('NVD_TIMEOUT', 8),
    ],

    'kev' => [
        'url' => env('KEV_URL', 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json'),
        'timeout' => (int) env('KEV_TIMEOUT', 8),
    ],

];
