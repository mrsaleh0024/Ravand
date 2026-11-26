/**
 * Central display formatter (phase 01 seed of the Date Adapter, ADR-005).
 *
 * - Storage is always Gregorian/UTC in the database.
 * - The Persian UI displays Jalali (Shamsi) dates; conversion happens ONLY
 *   here. No scattered date logic in pages (spec section 4.3).
 * - Full shamsi input support arrives with the dedicated adapter in phase 02.
 */

const jalaliDateTime = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    dateStyle: 'medium',
    timeStyle: 'short',
    numberingSystem: 'arabext',
});

const jalaliDate = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    dateStyle: 'medium',
    numberingSystem: 'arabext',
});

/** Compact Jalali label for chart axes: month + day, no year. */
const jalaliMonthDay = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    day: 'numeric',
    month: 'short',
    numberingSystem: 'arabext',
});

/** ISO 8601 / UTC instant → «۱۴۰۴/۰۳/۰۲، ۱۸:۳۰» style Jalali string. */
export function formatJalaliDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return jalaliDateTime.format(date);
}

/** ISO 8601 / UTC instant → Jalali date-only string. */
export function formatJalaliDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return jalaliDate.format(date);
}

/** ISO 8601 / date-only string → compact Jalali «۲۵ آذر» (chart axes). */
export function formatJalaliMonthDay(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return jalaliMonthDay.format(date);
}

/**
 * Integer amount (DECIMAL(20,0) string or number) → Persian-digit label
 * with the currency unit (phase 03, spec 4.4). Amounts stay strings on
 * the wire; formatting only happens here at display time.
 */
export function formatAmount(amount: string | number | null | undefined, currencyCode?: string | null): string {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    const digits = String(amount).replace(/[^0-9-]/g, '');

    if (digits === '' || digits === '-') {
        return '—';
    }

    const sign = digits.startsWith('-') ? '−' : '';
    const abs = digits.replace('-', '');
    const grouped = abs.replace(/\B(?=(\d{3})+(?!\d))/g, '،');
    const unit = currencyCode === 'IRT' ? 'تومان' : currencyCode === 'IRR' ? 'ریال' : '';

    return `${sign}${toPersianDigits(grouped)}${unit ? ` ${unit}` : ''}`;
}

/** ASCII digits → Persian digits (display only; never in inputs). */
export function toPersianDigits(value: string): string {
    return value.replace(/[0-9]/g, (digit) => String.fromCharCode(digit.charCodeAt(0) + 0x06f0));
}

/** Persian/Arabic digits → ASCII digits (user input → server format). */
export function toAsciiDigits(value: string): string {
    return value
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0630));
}
