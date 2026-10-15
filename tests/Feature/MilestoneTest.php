<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    private function milestone(User $user, bool $active = true): Milestone
    {
        $milestone = $user->milestones()->create(['name' => 'Personal milestone', 'color' => '#123456']);
        $milestone->periods()->create(['started_at' => '2026-01-01 12:00:00', 'finished_at' => $active ? null : '2026-01-02 12:00:00']);

        return $milestone;
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'New milestone', 'color' => '#aBc123', 'started_at' => '2026-01-01T12:00', 'show_years' => '1'], $overrides);
    }

    public static function protectedRoutes(): array
    {
        return [['GET', '/'], ['GET', '/profile'], ['PATCH', '/profile'], ['GET', '/milestones/create'], ['POST', '/milestones'], ['GET', '/milestones/1/edit'], ['PUT', '/milestones/1'], ['DELETE', '/milestones/1'], ['GET', '/milestones/1/history'], ['POST', '/milestones/1/start'], ['POST', '/milestones/1/stop'], ['POST', '/milestones/1/reset']];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_cannot_access_private_routes(string $method, string $path): void
    {
        $this->call($method, $path)->assertRedirect(route('login'));
    }

    #[DataProvider('protectedRoutes')]
    public function test_unverified_users_cannot_access_private_routes(string $method, string $path): void
    {
        $user = User::factory()->unverified()->create();
        $milestone = $this->milestone($user);
        $path = str_replace('/milestones/1', '/milestones/'.$milestone->id, $path);
        $this->actingAs($user)->call($method, $path)->assertRedirect(route('verification.notice'));
    }

    public function test_index_only_displays_owned_milestones_and_create_form_renders(): void
    {
        $user = User::factory()->create();
        $mine = $this->milestone($user);
        $other = $this->milestone(User::factory()->create());
        $other->update(['name' => 'Someone else private milestone']);
        $this->actingAs($user)->get('/')->assertOk()->assertSee($mine->name)->assertDontSee($other->name)
            ->assertSee('data-started-at=', false);
        $this->get('/milestones/create')->assertOk()->assertSee('Create milestone');
    }

    public function test_create_persists_owner_initial_period_color_and_normalized_checkboxes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/milestones', $this->data() + ['user_id' => 999])
            ->assertRedirect('/')->assertSessionHasNoErrors();
        $milestone = Milestone::sole();
        $this->assertSame($user->id, $milestone->user_id);
        $this->assertSame('#aBc123', $milestone->color);
        $this->assertTrue($milestone->show_years);
        foreach (['show_round_days', 'show_half_decade_days', 'show_round_weeks', 'show_round_months'] as $field) {
            $this->assertFalse($milestone->$field);
        }
        $this->assertSame('2026-01-01 12:00:00', $milestone->currentPeriod->started_at->toDateTimeString());
        $this->assertSame(1, $milestone->periods()->count());
    }

    public static function invalidMilestones(): array
    {
        return [['name', ''], ['name', str_repeat('x', 256)], ['color', 'red'], ['color', '#123'], ['started_at', 'invalid'], ['started_at', '']];
    }

    #[DataProvider('invalidMilestones')]
    public function test_creation_validates_input_without_partial_records(string $field, string $value): void
    {
        $this->actingAs(User::factory()->create())->post('/milestones', $this->data([$field => $value]))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('milestones', 0);
        $this->assertDatabaseCount('milestone_periods', 0);
    }

    public function test_initial_start_can_be_edited_and_color_cleared(): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $this->actingAs($user)->get(route('milestones.edit', $milestone))->assertOk()->assertSee('name="started_at"', false);
        $this->put(route('milestones.update', $milestone), $this->data(['color' => null, 'started_at' => '2025-12-01T10:00']))
            ->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertNull($milestone->fresh()->color);
        $this->assertSame('2025-12-01 10:00:00', $milestone->fresh()->currentPeriod->started_at->toDateTimeString());
    }

    public function test_color_is_optional_when_creating_and_updating_a_milestone(): void
    {
        $data = $this->data();
        unset($data['color']);
        $this->actingAs(User::factory()->create())->post('/milestones', $data)
            ->assertRedirect('/')->assertSessionHasNoErrors();
        $milestone = Milestone::sole();
        $this->assertNull($milestone->color);
        $this->put(route('milestones.update', $milestone), $data)
            ->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertNull($milestone->fresh()->color);
    }

    public static function lockedStartStates(): array
    {
        return [['stopped'], ['multiple']];
    }

    #[DataProvider('lockedStartStates')]
    public function test_start_date_is_locked_after_stopping_or_creating_more_periods(string $state): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user, false);
        if ($state === 'multiple') {
            $milestone->periods()->create(['started_at' => '2026-02-01 10:00:00']);
        }
        $this->actingAs($user)->get(route('milestones.edit', $milestone))->assertOk()->assertDontSee('name="started_at"', false);
        $this->put(route('milestones.update', $milestone), $this->data())->assertSessionHasErrors('started_at');
        $data = $this->data(['name' => 'Renamed']);
        unset($data['started_at']);
        $this->put(route('milestones.update', $milestone), $data)->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $milestone->fresh()->name);
    }

    public function test_started_at_must_be_before_tomorrow(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/milestones', $this->data([
                'started_at' => '2026-10-15T23:59',
            ]))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('milestones', 1);
        $this->assertDatabaseCount('milestone_periods', 1);

        $this->post('/milestones', $this->data([
            'started_at' => '2026-10-16T00:00',
        ]))
            ->assertSessionHasErrors('started_at');

        $this->assertDatabaseCount('milestones', 1);
        $this->assertDatabaseCount('milestone_periods', 1);
    }

    public function test_initial_start_can_be_updated_to_the_end_of_today(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $user = User::factory()->create();
        $milestone = $this->milestone($user);

        $this->actingAs($user)->put(route('milestones.update', $milestone), $this->data([
            'started_at' => '2026-10-15T23:59',
        ]))->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertSame('2026-10-15 23:59:00', $milestone->fresh()->currentPeriod->started_at->toDateTimeString());
        $this->assertSame(1, $milestone->periods()->count());
    }

    public static function futureStartDates(): array
    {
        return [
            'tomorrow at midnight' => ['2026-10-16T00:00'],
            'later tomorrow' => ['2026-10-16T12:00'],
            'a later date' => ['2026-10-20T12:00'],
        ];
    }

    #[DataProvider('futureStartDates')]
    public function test_future_start_update_is_rejected_without_changing_milestone_or_period(string $startedAt): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $period = $milestone->currentPeriod;
        $originalMilestone = $milestone->fresh()->getAttributes();
        $originalPeriod = $period->getAttributes();

        $this->actingAs($user)->put(route('milestones.update', $milestone), $this->data([
            'name' => 'Must not be saved',
            'color' => '#abcdef',
            'started_at' => $startedAt,
        ]))->assertSessionHasErrors('started_at');

        $this->assertSame($originalMilestone, $milestone->fresh()->getAttributes());
        $this->assertSame($originalPeriod, $period->fresh()->getAttributes());
        $this->assertSame(1, $milestone->periods()->count());
    }

    public function test_stop_start_and_reset_preserve_history_and_share_reset_timestamp(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $first = $milestone->currentPeriod;
        $this->actingAs($user)->post(route('milestones.stop', $milestone))->assertRedirect('/');
        $this->assertTrue($first->fresh()->finished_at->equalTo(now()));
        $this->assertNull($milestone->fresh()->currentPeriod);
        $this->travel(1)->minute();
        $this->post(route('milestones.start', $milestone))->assertRedirect('/');
        $second = $milestone->fresh()->currentPeriod;
        $this->assertTrue($second->started_at->equalTo(now()));
        $this->travel(1)->minute();
        $this->post(route('milestones.reset', $milestone))->assertRedirect('/');
        $third = $milestone->fresh()->currentPeriod;
        $this->assertTrue($second->fresh()->finished_at->equalTo($third->started_at));
        $this->assertTrue($third->started_at->equalTo(now()));
        $this->assertSame(3, $milestone->periods()->count());
        $this->assertSame(1, $milestone->periods()->whereNull('finished_at')->count());
    }

    public function test_history_is_ordered_and_displays_periods_and_totals(): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user, false);
        $milestone->periods()->create(['started_at' => '2026-02-01 12:00:00', 'finished_at' => '2026-02-03 12:00:00']);
        $this->actingAs($user)->get(route('milestones.history', $milestone))->assertOk()
            ->assertSeeInOrder(['01.02.2026 12:00', '01.01.2026 12:00'])
            ->assertSee('Total')->assertSee('(3 days)')->assertSee('Stopped');
    }

    public function test_delete_cascades_to_periods(): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $this->actingAs($user)->delete(route('milestones.destroy', $milestone))->assertRedirect('/');
        $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
        $this->assertDatabaseCount('milestone_periods', 0);
    }

    public static function ownedActions(): array
    {
        return [['GET', 'edit'], ['GET', 'history'], ['PUT', 'update'], ['DELETE', 'destroy'], ['POST', 'start'], ['POST', 'stop'], ['POST', 'reset']];
    }

    #[DataProvider('ownedActions')]
    public function test_other_users_cannot_read_or_change_milestone(string $method, string $action): void
    {
        $milestone = $this->milestone(User::factory()->create());
        $original = $milestone->fresh()->getAttributes();
        $this->actingAs(User::factory()->create())->call($method, route('milestones.'.$action, $milestone), $this->data())
            ->assertForbidden();
        $this->assertSame($original, $milestone->fresh()->getAttributes());
        $this->assertSame(1, $milestone->periods()->count());
        $this->assertNull($milestone->fresh()->currentPeriod->finished_at);
    }

    #[DataProvider('ownedActions')]
    public function test_missing_milestones_return_not_found(string $method, string $action): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $id = $milestone->id;
        $milestone->delete();

        $this->actingAs($user)->call($method, route('milestones.'.$action, $id), $this->data())
            ->assertNotFound();
    }

    public function test_other_users_get_forbidden_before_update_validation(): void
    {
        $milestone = $this->milestone(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->put(route('milestones.update', $milestone), [
                'name' => '',
                'color' => 'invalid',
                'started_at' => 'invalid',
            ])
            ->assertForbidden();
    }

    public function test_anniversary_preferences_control_badges_and_stopped_milestones_show_no_timer(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 11)->setTime(12, 0));
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $this->actingAs($user)->get('/')->assertOk()->assertSee('10 days')->assertSee("Today's anniversaries", false);
        $milestone->update(['show_round_days' => false]);
        $this->get('/')->assertOk()->assertDontSee("Today's anniversaries", false);
        $milestone->periods()->update(['finished_at' => now()]);
        $this->get('/')->assertOk()->assertSee('Stopped')->assertDontSee('data-started-at=', false);
    }
}
