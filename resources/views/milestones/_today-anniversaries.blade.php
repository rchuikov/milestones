@php
    use App\Support\AnniversaryCalculator;

    /** @var AnniversaryCalculator $calculator */
    $calculator = app(AnniversaryCalculator::class);

    $items = [];

    foreach ($milestones as $milestone) {
        $currentPeriod = $milestone->currentPeriod;

        if (! $currentPeriod) {
            continue;
        }

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

        if ($anniversaries) {
            $items[] = [
                'milestone' => $milestone,
                'anniversaries' => $anniversaries,
            ];
        }
    }
@endphp

@if ($items)
    <div class="mb-1 px-2 text-lg font-medium text-gray-600">
        Today's anniversaries
    </div>

    <div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        @foreach ($items as $item)
            @unless ($loop->first)
                <div class="mx-5 border-t border-gray-100"></div>
            @endunless

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-2.5">
                <div class="flex min-w-40 items-center gap-2 font-semibold text-gray-700">
                    @if ($item['milestone']->color)
                        <span
                            class="h-3 w-3 shrink-0 rounded-full"
                            style="background-color: {{ $item['milestone']->color }}"
                        ></span>
                    @endif

                    <span>{{ $item['milestone']->name }}</span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($item['anniversaries'] as $anniversary)
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

                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $classes }}"
                        >
                    {{ $label }}
                </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
