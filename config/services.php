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

    'passport' => [
        'login_endpoint' => value(function () {
            $endpoint = env('PASSPORT_LOGIN_ENDPOINT');
            return blank($endpoint) ? 'https://voare.test/oauth/token' : $endpoint;
        }),
        'client_id' => value(function () {
            $id = env('PASSPORT_PASSWORD_CLIENT_ID');
            return blank($id) ? '019a6092-ab3c-72c5-9baa-f4fb4c0cfbc5' : $id;
        }),
        'client_secret' => value(function () {
            $secret = env('PASSPORT_PASSWORD_CLIENT_SECRET');
            return blank($secret) ? 'eTmtcIyQAlXlf7OmJ1HWo1oGiZbX2v81nhzec20s' : $secret;
        }),
    ],

];
