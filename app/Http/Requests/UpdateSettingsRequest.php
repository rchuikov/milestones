<?php

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::enum(Locale::class)],
        ];
    }
}
