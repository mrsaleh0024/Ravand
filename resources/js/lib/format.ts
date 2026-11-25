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
