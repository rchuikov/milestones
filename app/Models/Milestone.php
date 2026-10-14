<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property bool $show_round_days
 * @property bool $show_half_decade_days
 * @property bool $show_round_weeks
 * @property bool $show_round_months
 * @property bool $show_years
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, MilestonePeriod> $periods
 * @property-read MilestonePeriod|null $currentPeriod
 */
#[Fillable(['name', 'color', 'show_round_days', 'show_half_decade_days', 'show_round_weeks', 'show_round_months', 'show_years'])]
final class Milestone extends Model
{
    protected function casts(): array
    {
        return [
            'show_round_days' => 'boolean',
            'show_half_decade_days' => 'boolean',
            'show_round_weeks' => 'boolean',
            'show_round_months' => 'boolean',
            'show_years' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(MilestonePeriod::class);
    }

    public function currentPeriod(): HasOne
    {
        return $this->hasOne(MilestonePeriod::class)
            ->whereNull('finished_at');
    }
}
