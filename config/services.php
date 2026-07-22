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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'jitsi' => [
    'app_id' => env('JITSI_APP_ID'),
    'secret' => env('JITSI_SECRET'),
    'domain' => env('JITSI_DOMAIN'),
    ],
    
    //for senoclock integration testing
    'senoclock' => [
        'base_url' => env('SENOCLOCK_API_BASE_URL') ?: 'https://api-euc1.senoclock.ai',
        'email' => env('SENOCLOCK_EMAIL'),
        'password' => env('SENOCLOCK_PASSWORD'),
    ],
     'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    // Free OCR fallback for lab report images (https://ocr.space/ocrapi)
    // Default "helloworld" is OCR.space's public demo key (rate-limited).
    'ocr_space' => [
        'api_key' => env('OCR_SPACE_API_KEY', 'helloworld'),
        'endpoint' => env('OCR_SPACE_ENDPOINT', 'https://api.ocr.space/parse/image'),
    ],
];
