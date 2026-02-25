<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'vacunas_api' => [
    'url' => env('VACUNAS_API_URL'),
    'endpoints' => [
        'biologicos' => env('VACUNAS_API_BIOLOGICOS'),
    ],
    'token' => env('VACUNAS_API_TOKEN'),
],


];
