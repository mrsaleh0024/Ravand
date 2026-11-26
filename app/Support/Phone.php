<?php

namespace App\Support;

/**
 * Mobile-number normalization (ADR-019).
 *
 * Iranian mobile numbers are the only login identity. Users type numbers in
 * many shapes (Persian digits, +98, separators) — everything is normalized
 * to the canonical `09xxxxxxxxx` form before storage or lookup. Normalized
 * values are always ASCII digits so login lookups are exact.
 */
final class Phone
{
    /** Persian and Arabic digit ranges. */
    private const FA_MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Normalize any user-typed mobile number to `09xxxxxxxxx`, or null when
     * the input cannot represent an Iranian mobile number.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = strtr(trim($raw), self::FA_MAP);
        $digits = preg_replace('/[^0-9]/', '', $digits) ?? '';

        if ($digits === '') {
            return null;
        }

        // 98XXXXXXXXXX → 0XXXXXXXXXX
        if (str_starts_with($digits, '98') && mb_strlen($digits) === 12) {
            $digits = '0'.mb_substr($digits, 2);
        }

        // 9XXXXXXXXX (10 digits, no leading zero) → 0XXXXXXXXX
        if (str_starts_with($digits, '9') && mb_strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^09\d{9}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /** Mask a normalized number for display: 0912***1234. */
    public static function mask(string $phone): string
    {
        if (mb_strlen($phone) !== 11) {
            return $phone;
        }

        return mb_substr($phone, 0, 4).'***'.mb_substr($phone, -4);
    }
}
