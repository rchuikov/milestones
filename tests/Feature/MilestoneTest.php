<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\MilestonePeriod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PDOException;
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
        return [['GET', '/'], ['GET', '/account'], ['PATCH', '/account'], ['GET', '/settings'], ['PATCH', '/settings'], ['GET', '/milestones/create'], ['POST', '/milestones'], ['GET', '/milestones/1/edit'], ['PUT', '/milestones/1'], ['DELETE', '/milestones/1'], ['GET', '/milestones/1/history'], ['POST', '/milestones/1/start'], ['POST', '/milestones/1/stop'], ['POST', '/milestones/1/reset'], ['GET', '/milestones/anniversaries'], ['POST', '/milestones/1/start', true], ['POST', '/milestones/1/stop', true], ['POST', '/milestones/1/reset', true], ['GET', '/milestones/anniversaries', true]];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_cannot_access_private_routes(string $method, string $path, bool $ajax = false): void
    {
        if ($ajax) {
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
                ->json($method, $path)->assertUnauthorized();
        } else {
            $this->call($method, $path)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('protectedRoutes')]
    public function test_unverified_users_cannot_access_private_routes(string $method, string $path, bool $ajax = false): void
    {
        $user = User::factory()->unverified()->create();
        $milestone = $this->milestone($user);
        $path = str_replace('/milestones/1', '/milestones/'.$milestone->id, $path);
        $this->actingAs($user);
        if ($ajax) {
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
                ->json($method, $path)->assertForbidden();
        } else {
            $this->call($method, $path)->assertRedirect(route('verification.notice'));
        }
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
            ->assertRedirect('/')->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Milestone created.']);
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
            ->assertRedirect('/')->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Milestone updated.']);
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

    public static function actionRequestTypes(): array
    {
        return ['regular form' => [false], 'AJAX' => [true]];
    }

    #[DataProvider('actionRequestTypes')]
    public function test_stop_start_and_reset_preserve_history_and_share_reset_timestamp(bool $ajax): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $first = $milestone->currentPeriod;
        $this->actingAs($user);
        if ($ajax) {
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
        }

        $assertResponse = function ($response, bool $active) use ($ajax, $milestone): void {
            if (! $ajax) {
                $response->assertRedirect('/');

                return;
            }

            $response->assertOk()->assertViewIs('milestones._card')
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertSee('data-milestone-id="'.$milestone->id.'"', false)
                ->assertDontSee('<html', false)->assertDontSee('role="alert"', false);

            if ($active) {
                $response->assertSee('data-started-at="'.now()->toIso8601String().'"', false)
                    ->assertSee('action="'.route('milestones.reset', $milestone).'"', false)
                    ->assertSee('action="'.route('milestones.stop', $milestone).'"', false)
                    ->assertDontSee('action="'.route('milestones.start', $milestone).'"', false);
            } else {
                $response->assertSee('Stopped')->assertDontSee('data-started-at=', false)
                    ->assertSee('action="'.route('milestones.start', $milestone).'"', false)
                    ->assertDontSee('action="'.route('milestones.reset', $milestone).'"', false)
                    ->assertDontSee('action="'.route('milestones.stop', $milestone).'"', false);
            }
        };

        $assertResponse($this->post(route('milestones.stop', $milestone)), false);
        $this->assertTrue($first->fresh()->finished_at->equalTo(now()));
        $this->assertNull($milestone->fresh()->currentPeriod);
        $this->travel(1)->minute();
        $assertResponse($this->post(route('milestones.start', $milestone)), true);
        $second = $milestone->fresh()->currentPeriod;
        $this->assertTrue($second->started_at->equalTo(now()));
        $this->travel(1)->minute();
        $assertResponse($this->post(route('milestones.reset', $milestone)), true);
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
        $this->actingAs($user)->delete(route('milestones.destroy', $milestone))->assertRedirect('/')
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Milestone deleted.']);
        $this->get('/')->assertOk()->assertSee('Milestone deleted.');
        $this->get('/')->assertOk()->assertDontSee('Milestone deleted.');
        $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
        $this->assertDatabaseCount('milestone_periods', 0);
    }

    public static function ownedActions(): array
    {
        return [['GET', 'edit'], ['GET', 'history'], ['PUT', 'update'], ['DELETE', 'destroy'], ['POST', 'start'], ['POST', 'stop'], ['POST', 'reset'], ['POST', 'start', true], ['POST', 'stop', true], ['POST', 'reset', true]];
    }

    #[DataProvider('ownedActions')]
    public function test_other_users_cannot_read_or_change_milestone(string $method, string $action, bool $ajax = false): void
    {
        $milestone = $this->milestone(User::factory()->create());
        $original = $milestone->fresh()->getAttributes();
        $this->actingAs(User::factory()->create())->call($method, route('milestones.'.$action, $milestone), $this->data(), server: $ajax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'] : [])
            ->assertForbidden();
        $this->assertSame($original, $milestone->fresh()->getAttributes());
        $this->assertSame(1, $milestone->periods()->count());
        $this->assertNull($milestone->fresh()->currentPeriod->finished_at);
    }

    #[DataProvider('ownedActions')]
    public function test_missing_milestones_return_not_found(string $method, string $action, bool $ajax = false): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user);
        $id = $milestone->id;
        $milestone->delete();

        $this->actingAs($user)->call($method, route('milestones.'.$action, $id), $this->data(), server: $ajax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json'] : [])
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
        $this->actingAs($user)->get('/')->assertOk()->assertSee('10 days')->assertSee("Today's special dates");
        $milestone->update(['show_round_days' => false]);
        $this->get('/')->assertOk()->assertDontSee("Today's special dates");
        $milestone->periods()->update(['finished_at' => now()]);
        $this->get('/')->assertOk()->assertSee('Stopped')->assertDontSee('data-started-at=', false);
    }

    public static function invalidAjaxActions(): array
    {
        return [
            ['start', true, 'Milestone already has a current period.'],
            ['stop', false, 'Milestone does not have a current period.'],
            ['reset', false, 'Milestone does not have a current period.'],
        ];
    }

    #[DataProvider('invalidAjaxActions')]
    public function test_invalid_ajax_action_returns_current_card_with_error_without_changing_periods(string $action, bool $active, string $message): void
    {
        $user = User::factory()->create();
        $milestone = $this->milestone($user, $active);
        $original = $milestone->periods()->first()->getAttributes();

        $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('milestones.'.$action, $milestone))
            ->assertOk()->assertViewIs('milestones._card')
            ->assertSee('data-milestone-id="'.$milestone->id.'"', false)
            ->assertSee('role="alert"', false)->assertSee($message);

        $this->assertSame(1, $milestone->periods()->count());
        $this->assertSame($original, $milestone->periods()->first()->getAttributes());
    }

    public static function failedDatabaseActions(): array
    {
        return [['start', false], ['stop', true], ['reset', true]];
    }

    #[DataProvider('failedDatabaseActions')]
    public function test_ajax_database_errors_are_reported_without_exposing_details(string $action, bool $active): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create();
        $milestone = $this->milestone($user, $active);
        $original = $milestone->periods()->first()->getAttributes();
        $exception = new QueryException('mariadb', 'update milestone_periods set finished_at = ?', ['2026-10-29 12:00:00'], new PDOException('Internal driver diagnostic'));
        MilestonePeriod::saving(function () use ($exception): void {
            throw $exception;
        });
        Exceptions::fake();

        $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('milestones.'.$action, $milestone))
            ->assertOk()->assertViewIs('milestones._card')
            ->assertSee('role="alert"', false)->assertSee('DB Error')
            ->assertDontSee('Internal driver diagnostic')->assertDontSee('update milestone_periods');

        Exceptions::assertReported(fn (QueryException $reported) => $reported === $exception);
        $this->assertSame(1, $milestone->periods()->count());
        $this->assertSame($original, $milestone->periods()->first()->getAttributes());
    }

    public function test_anniversaries_fragment_is_private_filtered_and_reflects_period_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 11)->setTime(12, 0));
        $user = User::factory()->create();
        $mine = $this->milestone($user);
        $other = $this->milestone(User::factory()->create());
        $other->update(['name' => 'Private anniversary']);
        $stopped = $this->milestone($user, false);
        $stopped->update(['name' => 'Stopped anniversary']);
        $hidden = $this->milestone($user);
        $hidden->update(['name' => 'Disabled anniversary', 'show_round_days' => false]);

        $this->actingAs($user)->get(route('milestones.anniversaries'))->assertNotFound();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('milestones.anniversaries'))
            ->assertOk()->assertViewIs('milestones._today-anniversaries')
            ->assertSee($mine->name)->assertSee('10 days')
            ->assertDontSee($other->name)->assertDontSee($stopped->name)->assertDontSee($hidden->name)
            ->assertDontSee('<html', false)->assertDontSee('data-milestone-id=', false);

        $this->post(route('milestones.reset', $mine))->assertOk();
        $this->get(route('milestones.anniversaries'))->assertOk()->assertContent('');

        $this->travel(10)->days();
        $this->get(route('milestones.anniversaries'))->assertOk()->assertSee($mine->name)->assertSee('10 days');
        $this->post(route('milestones.stop', $mine))->assertOk();
        $this->get(route('milestones.anniversaries'))->assertOk()->assertContent('');
        $this->post(route('milestones.start', $mine))->assertOk();
        $this->get(route('milestones.anniversaries'))->assertOk()->assertContent('');
    }
}
