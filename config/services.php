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

    'notion' => [
        'token' => env('NOTION_API_TOKEN'),
        'database_id' => env('NOTION_DATABASE_ID'),
        'properties' => [
            'name' => env('NOTION_PROPERTY_NAME', 'Name'),
            'date' => env('NOTION_PROPERTY_DATE', 'Date'),
            'status' => env('NOTION_PROPERTY_STATUS', 'Status'),
            'options' => env('NOTION_PROPERTY_OPTIONS', 'Options'),
            'gallery_name' => env('NOTION_PROPERTY_GALLERY_NAME', 'Nom de la galerie'),
        ],
        'timeout' => (int) env('NOTION_TIMEOUT', 5),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'zoho' => [
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'redirect' => env('ZOHO_REDIRECT_URI'),
    ],

];
