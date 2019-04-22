<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'remp' => [
        'beam' => [
            'web_addr' => env('REMP_BEAM_ADDR'),
            'tracker_addr' => env('REMP_TRACKER_ADDR'),
            'tracker_property_token' => env('REMP_TRACKER_ADDR'),
            'segments_addr' => env('REMP_SEGMENTS_ADDR'),
            'segments_auth_token' => env('REMP_SEGMENTS_AUTH_TOKEN'),
        ],
        'campaign' => [
            'web_addr' => env('REMP_CAMPAIGN_ADDR'),
        ],
        'mailer' => [
            'web_addr' => env('REMP_MAILER_ADDR'),
            'api_token' => env('REMP_MAILER_API_TOKEN')
        ],
        'sso' => [
            'web_addr' => env('REMP_SSO_ADDR'),
            'api_token' => env('REMP_SSO_API_TOKEN'),
        ],
        'linked' => [
            'beam' => [
                'url' => '/',
                'icon' => 'album',
            ],
            'campaign' => [
                'url' => env('REMP_CAMPAIGN_ADDR'),
                'icon' => 'trending-up',
            ],
            'mailer' => [
                'url' => env('REMP_MAILER_ADDR'),
                'icon' => 'email',
            ],
        ],
    ],

];
