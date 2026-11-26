<?php

use App\Auth\Pipeline\AttemptPhoneAuthentication;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | This value defines which password broker Fortify can use. Ravand has no
    | email-based password reset (ADR-021), so the broker is unused but kept
    | configured for framework compatibility.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | ADR-019: the Iranian mobile number IS the login identity. There is no
    | email and no separate username — the login form posts a `phone` field
    | which is normalized to 09xxxxxxxxx before the lookup.
    |
    */

    'username' => 'phone',

    'email' => 'phone',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | Digits are case-insensitive; harmless normalization for the phone
    | identifier (kept for the CanonicalizeUsername pipeline action).
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication when the operation is successful.
    |
    */

    'home' => '/dashboard',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Login Pipeline
    |--------------------------------------------------------------------------
    |
    | Custom pipeline (ADR-019/020): phone credentials + owner OTP branch.
    | AttemptPhoneAuthentication resolves the user by mobile number and,
    | for the owner with Bale 2FA configured, defers the session start to
    | the OTP challenge instead of logging in immediately.
    |
    */

    'pipelines' => [
        'login' => [
            EnsureLoginIsNotThrottled::class,
            CanonicalizeUsername::class,
            AttemptPhoneAuthentication::class,
            PrepareAuthenticatedSession::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Logins and OTP verification attempts are throttled per identifier and
    | IP. `otp` is registered in App\Providers\FortifyServiceProvider.
    |
    */

    'limiters' => [
        'login' => 'login',
        'otp' => 'otp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | All optional Fortify features are intentionally disabled (ADR-019/021):
    |   - registration / email verification: accounts exist only through the
    |     owner-setup command and the panel;
    |   - email password reset: replaced by panel-side reset requests;
    |   - TOTP two-factor and passkeys: replaced by Bale OTP for the owner.
    |
    | The password-confirmation routes (`password.confirm`) are registered
    | unconditionally by Fortify and stay enabled for sensitive screens.
    |
    */

    'features' => [
        //
    ],

];
