<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MilestoneService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class MilestoneServiceTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidTransitions(): array
    {
        return [['start', true], ['stop', false], ['reset', false]];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_leave_periods_unchanged(string $operation, bool $active): void
    {
        $milestone = User::factory()->create()->milestones()->create(['name' => 'Tracked']);
        $period = $milestone->periods()->create(['started_at' => now()->subDay(), 'finished_at' => $active ? null : now()]);
        $original = $period->fresh()->getAttributes();
        try {
            app(MilestoneService::class)->$operation($milestone);
            $this->fail('Invalid state transition was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('current period', $exception->getMessage());
        }
        $this->assertSame(1, $milestone->periods()->count());
        $this->assertSame($original, $period->fresh()->getAttributes());
    }

    public function test_failed_start_date_update_rolls_back_milestone_changes(): void
    {
        $milestone = User::factory()->create()->milestones()->create(['name' => 'Original']);
        $period = $milestone->periods()->create(['started_at' => now()->subDay(), 'finished_at' => now()]);
        try {
            app(MilestoneService::class)->update($milestone, [
                'name' => 'Must roll back', 'color' => '#123456', 'started_at' => now()->subDays(2),
                'show_round_days' => false, 'show_half_decade_days' => false,
                'show_round_weeks' => false, 'show_round_months' => false, 'show_years' => false,
            ]);
            $this->fail('A historical start date was changed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Milestone start date cannot be changed.', $exception->getMessage());
        }
        $this->assertSame('Original', $milestone->fresh()->name);
        $this->assertNull($milestone->fresh()->color);
        $this->assertNotNull($period->fresh()->finished_at);
    }

    public function test_reset_rolls_back_finishing_current_period_when_next_period_cannot_be_saved(): void
    {
        $milestone = User::factory()->create()->milestones()->create(['name' => 'Tracked']);
        $period = $milestone->periods()->create(['started_at' => now()->subDay()]);
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into `milestone_periods`')) {
                throw new RuntimeException('Simulated period persistence failure.');
            }
        });

        try {
            app(MilestoneService::class)->reset($milestone);
            $this->fail('The simulated write failure did not occur.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated period persistence failure.', $exception->getMessage());
        }

        $this->assertNull($period->fresh()->finished_at);
        $this->assertSame(1, $milestone->periods()->count());
    }

    public function test_create_rolls_back_milestone_when_initial_period_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into `milestone_periods`')) {
                throw new RuntimeException('Simulated period persistence failure.');
            }
        });

        try {
            app(MilestoneService::class)->create($user, [
                'name' => 'Must roll back', 'started_at' => now(),
                'show_round_days' => true, 'show_half_decade_days' => true,
                'show_round_weeks' => true, 'show_round_months' => true, 'show_years' => true,
            ]);
            $this->fail('The simulated write failure did not occur.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated period persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('milestones', 0);
        $this->assertDatabaseCount('milestone_periods', 0);
    }
}
