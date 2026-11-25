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
        'email' => env('RAVAND_OWNER_EMAIL'),
        'password' => env('RAVAND_OWNER_PASSWORD'),
        'tenant_name' => env('RAVAND_TENANT_NAME'),
        'tenant_slug' => env('RAVAND_TENANT_SLUG'),
        'currency' => env('RAVAND_TENANT_CURRENCY'),
    ],

];
