<?php

// Ravand application-level allowlists and constants (phase 01).
// Codes are stable English identifiers; Persian titles live in the UI
// dictionary, not here (spec section 4.2).

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant timezones
    |--------------------------------------------------------------------------
    |
    | Default tenant timezone is Asia/Tehran (spec section 4.3). The list is a
    | server-side allowlist used to validate tenant settings updates.
    |
    */

    'default_timezone' => 'Asia/Tehran',

    'allowed_timezones' => [
        'Asia/Tehran',
        'UTC',
        'Asia/Dubai',
        'Asia/Istanbul',
        'Europe/London',
        'Europe/Berlin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant locales and calendars
    |--------------------------------------------------------------------------
    |
    | MVP ships Persian UI (RTL-first). The structure allows adding locales
    | later without schema changes.
    |
    */

    'default_locale' => 'fa-IR',

    'allowed_locales' => [
        'fa-IR',
    ],

    'allowed_calendars' => [
        'jalali',
        'gregorian',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    |
    | IRT is an application-local code (not ISO 4217); it means Iranian Toman.
    | Every future financial record will snapshot its own currency_code.
    |
    */

    'allowed_currencies' => [
        'IRR',
        'IRT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner bootstrap (.env)
    |--------------------------------------------------------------------------
    |
    | Optional credentials for `ravand:create-owner` (ADR-018). When set in
    | .env, the command runs without interactive prompts. Resolution order:
    | CLI option > .env > hidden prompt. Intended for local/CI convenience —
    | remove the values after the first boot and never run `config:cache`
    | while they are set in production.
    |
    */

    'owner' => [
        'name' => env('RAVAND_OWNER_NAME'),
        'phone' => env('RAVAND_OWNER_PHONE'),
        'password' => env('RAVAND_OWNER_PASSWORD'),
        'tenant_name' => env('RAVAND_TENANT_NAME'),
        'tenant_slug' => env('RAVAND_TENANT_SLUG'),
        'currency' => env('RAVAND_TENANT_CURRENCY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bale bots (ADR-020)
    |--------------------------------------------------------------------------
    |
    | Two bots, both writing ONLY to the owner's chat:
    |   code  → owner login OTP codes (6 digits, 5-minute expiry).
    |   admin → password-reset requests and member events mirror.
    |
    | Tokens come from BotFather in Bale. Discover the owner chat id with
    | `php artisan ravand:bale:poll` (prints it for every /start) and store
    | it on the Security page. On servers with a public URL, the same events
    | can arrive through the webhook route instead.
    |
    */

    'bale' => [
        'code_bot_token' => env('BALE_CODE_BOT_TOKEN'),
        'admin_bot_token' => env('BALE_ADMIN_BOT_TOKEN'),
        'api_base' => env('BALE_API_BASE', 'https://tapi.bale.ai/bot'),
        'webhook_secret' => env('BALE_WEBHOOK_SECRET'),
        // Owner two-factor via Bale OTP; managers/operators never get OTP.
        // filter_var so 'true'/'false'/'0'/'1' strings in .env all behave.
        'owner_2fa' => filter_var(env('BALE_OWNER_2FA', true), FILTER_VALIDATE_BOOLEAN),
        // Optional env fallback chat id (normally stored on the owner row).
        'chat_id' => env('RAVAND_BALE_CHAT_ID'),
        'otp' => [
            'ttl' => 300,
            'attempts' => 5,
            'resend_cooldown' => 60,
        ],
    ],

];
