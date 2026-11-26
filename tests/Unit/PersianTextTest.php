<?php

namespace Tests\Unit;

use App\Support\PersianText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Persian text normalization smoke cases (spec 2.3): the shared service is
 * the single source of truth for search normalization.
 */
final class PersianTextTest extends TestCase
{
    public static function normalizationProvider(): array
    {
        return [
            'arabic yeh becomes persian yeh' => ['مشتري', 'مشتری'],
            'arabic kaf becomes persian kaf' => ['كباب', 'کباب'],
            'persian digits become ascii' => ['۰۹۱۲', '0912'],
            'arabic-indic digits become ascii' => ['٠٩١٢', '0912'],
            'multiple spaces collapse' => ['  نام   مشتری  ', 'نام مشتری'],
            'zwnj removed for recall' => ["می\u{200c}خواهد", 'میخواهد'],
            'heh with yeh above becomes heh' => ['نامۀ', 'نامه'],
            'null becomes empty string' => [null, ''],
        ];
    }

    #[DataProvider('normalizationProvider')]
    public function test_normalize(?string $input, string $expected): void
    {
        $this->assertSame($expected, PersianText::normalize($input));
    }

    public function test_digits_only_strips_everything_but_digits(): void
    {
        $this->assertSame('09121112233', PersianText::digitsOnly('۰۹۱۲-۱۱۱ ۲۲۳۳'));
        // Country-prefix reshaping is Phone::normalize's job, not this
        // low-level helper's — digitsOnly just unifies and collects digits.
        $this->assertSame('989121112233', PersianText::digitsOnly('+98 912 111 2233'));
        $this->assertSame('', PersianText::digitsOnly('بدون رقم'));
    }

    public function test_handle_normalization_lowercases_and_strips_at(): void
    {
        $this->assertSame('reyhane.shop', PersianText::normalizeHandle('@Reyhane.Shop'));
    }
}
