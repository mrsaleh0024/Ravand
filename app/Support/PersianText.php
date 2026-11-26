<?php

namespace App\Support;

/**
 * Persian text normalization for searchable columns (spec section 2.3).
 *
 * The original user-entered text is always kept for display; normalized
 * copies live in dedicated columns (`normalized_name`, `normalized_value`,
 * `normalized_handle`) and are the ONLY thing search queries look at.
 * Normalization is intentionally lossy: it trades exactness for recall.
 */
final class PersianText
{
    /** Arabic → Persian letter fixes. */
    private const LETTER_MAP = [
        'ي' => 'ی',   // Arabic Yeh → Persian Yeh
        'ك' => 'ک',   // Arabic Kaf → Persian Kaf
        'ۀ' => 'ه',   // Heh with Yeh above → Heh
        'ة' => 'ه',   // Teh Marbuta → Heh
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و', 'ئ' => 'ی',
    ];

    /** Persian and Arabic digit ranges → ASCII digits. */
    private const FA_MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Normalize free text for name/handle search columns:
     * letters ی/ک unified, digits unified, ZWNJ → plain, whitespace trimmed
     * and collapsed. Never used for storage of the display value itself.
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $text = strtr($raw, self::LETTER_MAP);
        $text = strtr($text, self::FA_MAP);
        $text = str_replace("\u{200c}", '', $text); // ZWNJ removed for recall
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Normalize a phone-like search term: unify digits and strip everything
     * that is not a digit, so «۰۹۱۲-۳۴۵ ۶۷۸۹» matches «09123456789».
     */
    public static function digitsOnly(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $digits = strtr($raw, self::FA_MAP);

        return preg_replace('/[^0-9]/', '', $digits) ?? '';
    }

    /** Lowercased, digit-unified form for social handles (Instagram etc.). */
    public static function normalizeHandle(?string $raw): string
    {
        $text = self::normalize($raw);

        return mb_strtolower(trim($text, "@ \t\n\r"));
    }
}
