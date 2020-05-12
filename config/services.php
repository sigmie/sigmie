<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, SparkPost and others. This file provides a sane default
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT'),
        'newsletter_list' => env('MAILGUN_NEWSLETTER_LIST')
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'configcat' => [
        'key' => env('CONFIGCAT_KEY')
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => '/github/callback',
    ],

    'mailchimp' => [
        'key' => env('MAILCHIMP_KEY'),
        'data_center' => env('MAILCHIMP_DATA_CENTER'),
    ],
    'paddle' => [
        'vendor_id' => 113820,
        'plans' => [
            'hobby' => [
                'id' => 593241,
                'name' => 'Hobby'
            ],
            'pro' => [
                'id' =>  593244,
                'name' => 'Pro'
            ],
            'serious' => [
                'id' => 593245,
                'name' => 'Serious'
            ],
        ],
        'test_plans'=>[
            'test' => [
                'id'=> 593243,
                'name' => 'Test'
            ]
        ]
    ],
];
