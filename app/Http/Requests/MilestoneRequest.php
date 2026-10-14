<?php

namespace App\Http\Requests;

use App\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $milestone = $this->route('milestone');

        return ! $milestone
            || $milestone->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        $canEditStartedAt = $this->canEditStartedAt();

        return [
            'name' => ['required', 'string', 'max:255'],

            'started_at' => [
                Rule::requiredIf($canEditStartedAt),
                Rule::prohibitedIf(! $canEditStartedAt),
                'date',
                'before:tomorrow',
            ],

            'show_round_days' => ['boolean'],
            'show_half_decade_days' => ['boolean'],
            'show_round_weeks' => ['boolean'],
            'show_round_months' => ['boolean'],
            'show_years' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'show_round_days' => $this->boolean('show_round_days'),
            'show_half_decade_days' => $this->boolean('show_half_decade_days'),
            'show_round_weeks' => $this->boolean('show_round_weeks'),
            'show_round_months' => $this->boolean('show_round_months'),
            'show_years' => $this->boolean('show_years'),
        ]);
    }

    private function canEditStartedAt(): bool
    {
        /** @var Milestone|null $milestone */
        if (! $milestone = $this->route('milestone')) {
            return true;
        }
        $periods = $milestone->periods()->get(['id', 'finished_at']);

        return $periods->count() === 1 && $periods->first()->finished_at === null;
    }
}
