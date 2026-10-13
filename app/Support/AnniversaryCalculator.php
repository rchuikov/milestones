<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class AnniversaryCalculator
{
    public const string TYPE_ROUND_DAYS = 'round_days';

    public const string TYPE_HALF_DECADE_DAYS = 'half_decade_days';

    public const string TYPE_WEEKS = 'weeks';

    public const string TYPE_MONTHS = 'months';

    public const string TYPE_YEARS = 'years';

    public function forDate(
        CarbonInterface $startedAt,
        CarbonInterface $date,
    ): array {
        $startedAt = CarbonImmutable::instance($startedAt)->startOfDay();
        $date = CarbonImmutable::instance($date)->startOfDay();

        if ($date->lessThanOrEqualTo($startedAt)) {
            return [];
        }

        $anniversaries = [];

        $days = $startedAt->diffInDays($date);

        if ($days % 10 === 0) {
            $anniversaries[] = [
                'type' => self::TYPE_ROUND_DAYS,
                'value' => $days,
            ];
        }

        if ($days % 10 === 5) {
            $anniversaries[] = [
                'type' => self::TYPE_HALF_DECADE_DAYS,
                'value' => $days,
            ];
        }

        if ($days % 7 === 0) {
            $anniversaries[] = [
                'type' => self::TYPE_WEEKS,
                'value' => intdiv($days, 7),
            ];
        }

        $months = ($date->year - $startedAt->year) * 12
            + ($date->month - $startedAt->month);

        if (
            $months > 0
            && $startedAt->addMonthsNoOverflow($months)->isSameDay($date)
        ) {
            $anniversaries[] = [
                'type' => self::TYPE_MONTHS,
                'value' => $months,
            ];
        }

        $years = $date->year - $startedAt->year;

        if (
            $years > 0
            && $startedAt->addYearsNoOverflow($years)->isSameDay($date)
        ) {
            $anniversaries[] = [
                'type' => self::TYPE_YEARS,
                'value' => $years,
            ];
        }

        return $anniversaries;
    }
}
