<?php

namespace App\Domain\Sales;

use App\Support\PersianText;

/**
 * Integer money arithmetic on DECIMAL(20,0) columns (spec 5.5).
 *
 * Amounts are plain integer strings — rial/toman have no fractions — and
 * every sum/multiplication goes through bcmath so values beyond 2^63 are
 * still safe. Floats are never used for money anywhere in the codebase.
 */
final class Money
{
    /** Normalize user input (Persian/Arabic digits, separators) to an integer string. */
    public static function normalize(int|string|null $value): string
    {
        if ($value === null) {
            return '0';
        }

        return self::clean((string) $value);
    }

    /** @param int|string ...$values */
    public static function add(...$values): string
    {
        $sum = '0';

        foreach ($values as $value) {
            $sum = bcadd($sum, self::clean((string) $value), 0);
        }

        return $sum;
    }

    public static function sub(int|string $a, int|string $b): string
    {
        return bcsub(self::clean((string) $a), self::clean((string) $b), 0);
    }

    /** Integer multiplication (quantity × unit price). */
    public static function mul(int|string $value, int $factor): string
    {
        return bcmul(self::clean((string) $value), (string) $factor, 0);
    }

    public static function cmp(int|string $a, int|string $b): int
    {
        return bccomp(self::clean((string) $a), self::clean((string) $b), 0);
    }

    public static function min(int|string $a, int|string $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::clean((string) $a) : self::clean((string) $b);
    }

    /** Persian/Arabic digits → ASCII, separators stripped, sign preserved. */
    private static function clean(string $raw): string
    {
        $raw = trim($raw);
        $negative = str_starts_with($raw, '-');
        $digits = PersianText::digitsOnly($negative ? substr($raw, 1) : $raw);

        if ($digits === '') {
            return '0';
        }

        return ($negative && $digits !== '0') ? '-'.$digits : $digits;
    }
}
