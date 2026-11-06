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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Email-to-ticket. "testmail" polls a testmail.app namespace; each
    | workspace receives mail at {namespace}.{slug}@inbox.testmail.app.
    | "postmark" receives webhooks at {slug}@{domain}; that endpoint is
    | rejected unless the secret is configured and supplied by the provider.
    */
    'inbound_email' => [
        'driver' => env('INBOUND_EMAIL_DRIVER', 'postmark'),
        'domain' => env('INBOUND_EMAIL_DOMAIN', 'inbound.mahbubdesk.test'),
        'secret' => env('INBOUND_EMAIL_SECRET'),
        'max_payload_bytes' => (int) env('INBOUND_EMAIL_MAX_PAYLOAD_BYTES', 5 * 1024 * 1024),
        'max_recipients' => (int) env('INBOUND_EMAIL_MAX_RECIPIENTS', 50),
    ],

    // REST API: requests per minute per access token.
    'api' => [
        'rate_limit' => (int) env('API_RATE_LIMIT', 120),
    ],

    'testmail' => [
        'api_key' => env('TESTMAIL_API_KEY'),
        'namespace' => env('TESTMAIL_NAMESPACE'),
        'domain' => 'inbox.testmail.app',
        'endpoint' => env('TESTMAIL_ENDPOINT', 'https://api.testmail.app/api/graphql'),
        // On the very first poll, only import mail received this many minutes ago or later.
        'initial_lookback_minutes' => (int) env('TESTMAIL_INITIAL_LOOKBACK_MINUTES', 60),
    ],

];
