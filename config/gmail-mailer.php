<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gmail OAuth2 Credentials Path
    |--------------------------------------------------------------------------
    |
    | Path to the Gmail OAuth2 credentials JSON file downloaded from
    | Google Cloud Console. This file contains your client_id and client_secret.
    |
    | Default: storage/app/gmail/credentials.json
    |
    */

    'credentials_path' => env('GMAIL_CREDENTIALS_PATH', storage_path('app/gmail/credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Gmail OAuth2 Token Path
    |--------------------------------------------------------------------------
    |
    | Path where the OAuth2 access and refresh tokens will be stored.
    | This file is generated after running the setup command.
    |
    | Default: storage/app/gmail/token.json
    |
    */

    'token_path' => env('GMAIL_TOKEN_PATH', storage_path('app/gmail/token.json')),

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | The application name shown in Google OAuth consent screen.
    |
    */

    'application_name' => env('GMAIL_APP_NAME', config('app.name', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Test Email Settings
    |--------------------------------------------------------------------------
    |
    | Default recipient for the test command (os:gmail-mailer:test).
    |
    */

    'test_recipient' => env('GMAIL_TEST_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging for debugging purposes.
    |
    */

    'logging' => [
        'enabled' => env('GMAIL_LOGGING_ENABLED', false),
        'channel' => env('GMAIL_LOG_CHANNEL', config('logging.default')),
    ],

];
