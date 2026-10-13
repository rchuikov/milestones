@php
    use App\Support\AnniversaryCalculator;

    if (! $currentPeriod) {
        return;
    }

    /** @var AnniversaryCalculator $calculator */
    $calculator = app(AnniversaryCalculator::class);

    $anniversaries = $calculator->forDate(
        $currentPeriod->started_at,
        today(),
    );

    $anniversaries = array_filter(
        $anniversaries,
        fn (array $anniversary) => match ($anniversary['type']) {
            AnniversaryCalculator::TYPE_ROUND_DAYS =>
                $milestone->show_round_days,

            AnniversaryCalculator::TYPE_HALF_DECADE_DAYS =>
                $milestone->show_half_decade_days,

            AnniversaryCalculator::TYPE_WEEKS =>
                $milestone->show_round_weeks,

            AnniversaryCalculator::TYPE_MONTHS =>
                $milestone->show_round_months,

            AnniversaryCalculator::TYPE_YEARS =>
                $milestone->show_years,
        },
    );
@endphp

@if ($anniversaries)
        @foreach ($anniversaries as $anniversary)
            @php
                [$label, $classes] = match ($anniversary['type']) {
                    AnniversaryCalculator::TYPE_ROUND_DAYS => [
                        $anniversary['value'].' days',
                        'bg-amber-50 text-amber-800 ring-amber-200',
                    ],

                    AnniversaryCalculator::TYPE_HALF_DECADE_DAYS => [
                        $anniversary['value'].' days',
                        'bg-rose-50 text-rose-800 ring-rose-200',
                    ],

                    AnniversaryCalculator::TYPE_WEEKS => [
                        $anniversary['value'].' weeks',
                        'bg-blue-50 text-blue-800 ring-blue-200',
                    ],

                    AnniversaryCalculator::TYPE_MONTHS => [
                        $anniversary['value'].' months',
                        'bg-emerald-50 text-emerald-800 ring-emerald-200',
                    ],

                    AnniversaryCalculator::TYPE_YEARS => [
                        $anniversary['value'].' years',
                        'bg-violet-50 text-violet-800 ring-violet-200',
                    ],
                };
            @endphp

            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $classes }}">
                {{ $label }}
            </span>
        @endforeach
@endif
