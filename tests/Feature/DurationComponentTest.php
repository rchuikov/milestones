<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DurationComponentTest extends TestCase
{
    public static function durations(): array
    {
        return [[0, 'less than a minute', '(0 days)'], [59, 'less than a minute', '(0 days)'], [60, '1 minute', '(0 days)'], [3661, '1 hour, 1 minute', '(0 days)'], [86400, '1 day', '(1 day)'], [172800, '2 days', '(2 days)']];
    }

    #[DataProvider('durations')]
    public function test_duration_formats_minutes_and_total_days(int $seconds, string $duration, string $total): void
    {
        $this->blade('<x-duration :seconds="$seconds" />', ['seconds' => $seconds])
            ->assertSee($duration)->assertSee($total);
    }
}
