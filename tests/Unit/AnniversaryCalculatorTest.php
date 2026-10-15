<?php

namespace Tests\Unit;

use App\Support\AnniversaryCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnniversaryCalculatorTest extends TestCase
{
    public static function dates(): array
    {
        return [
            'same day' => ['2026-01-01 01:00', '2026-01-01 23:00', []],
            'future start' => ['2026-01-02', '2026-01-01', []],
            'ordinary day' => ['2026-01-01', '2026-01-04', []],
            'five days' => ['2026-01-01', '2026-01-06', ['half_decade_days' => 5]],
            'ten days' => ['2026-01-01', '2026-01-11', ['round_days' => 10]],
            'week' => ['2026-01-01', '2026-01-08', ['weeks' => 1]],
            'overlapping anniversaries' => ['2026-01-01', '2026-03-12', ['round_days' => 70, 'weeks' => 10]],
            'month' => ['2026-01-01', '2026-02-01', ['months' => 1]],
            'short month clamps' => ['2026-01-31', '2026-02-28', ['weeks' => 4, 'months' => 1]],
            'leap month clamps' => ['2024-01-31', '2024-02-29', ['months' => 1]],
            'year and month' => ['2025-01-01', '2026-01-01', ['half_decade_days' => 365, 'months' => 12, 'years' => 1]],
            'leap anniversary clamps' => ['2024-02-29', '2025-02-28', ['half_decade_days' => 365, 'months' => 12, 'years' => 1]],
            'time of day ignored' => ['2026-01-01 23:59', '2026-01-11 00:01', ['round_days' => 10]],
        ];
    }

    #[DataProvider('dates')]
    public function test_calendar_anniversaries(string $start, string $date, array $expected): void
    {
        $startedAt = CarbonImmutable::parse($start, 'UTC');
        $target = CarbonImmutable::parse($date, 'UTC');
        $actual = (new AnniversaryCalculator)->forDate($startedAt, $target);
        $this->assertEquals($expected, array_column($actual, 'value', 'type'));
        $this->assertSame($start, $startedAt->format(str_contains($start, ' ') ? 'Y-m-d H:i' : 'Y-m-d'));
    }
}
