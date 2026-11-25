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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8000),
    ],

    'exa' => [
        // Web search that grounds each mockup in real UI references.
        'key' => env('EXA_API_KEY'),
        'base_url' => env('EXA_BASE_URL', 'https://api.exa.ai'),
    ],

    'inspiration' => [
        // Falls back to a keyless HTML search, then to a curated gallery.
        'enabled' => (bool) env('INSPIRATION_SEARCH', true),
        'endpoint' => env('INSPIRATION_ENDPOINT', 'https://html.duckduckgo.com/html/'),
        'timeout' => (int) env('INSPIRATION_TIMEOUT', 8),
        'connect_timeout' => (int) env('INSPIRATION_CONNECT_TIMEOUT', 4),
    ],

    'local_ai' => [
        'base_url' => env('LOCAL_AI_BASE_URL', 'http://127.0.0.1:1235/v1'),
        'model' => env('LOCAL_AI_MODEL', 'qwen2.5-coder-7b-instruct'),
        'timeout' => (int) env('LOCAL_AI_TIMEOUT', 180),
        'max_tokens' => (int) env('LOCAL_AI_MAX_TOKENS', 900),
    ],

];
