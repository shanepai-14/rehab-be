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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
       'movider' => [
        'api_url' => env('MOVIDER_API_URL', 'https://api.movider.co/v1/sms'),
        'api_key' => env('MOVIDER_API_KEY','XmrzzQ9HPnjWlWz3UHuraSxCkfT1t4') ,
        'api_secret' => env('MOVIDER_API_SECRET','3P8NhUew5Y9X5jiuVZAZTl4a3f0-jf'),
        'sender_id' => env('MOVIDER_SENDER_ID', 'rehab_app'),
    ],

];
