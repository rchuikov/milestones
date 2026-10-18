<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public static function passwordStates(): array
    {
        return ['password account' => [false], 'Google account' => [true]];
    }

    #[DataProvider('passwordStates')]
    public function test_name_can_change_without_password_and_account_details_are_preserved(bool $withoutPassword): void
    {
        Notification::fake();
        $user = User::factory()->create($withoutPassword ? ['password' => null, 'external_id' => 'google-id'] : []);
        $original = $user->only(['email', 'password', 'email_verified_at', 'external_id', 'remember_token']);

        $this->actingAs($user)->get('/settings')->assertOk()
            ->assertSee('name="name"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="password"', false);
        $this->from('/settings')->patch('/settings', [
            'name' => 'Updated Name',
            'email' => 'injected@example.com',
            'password' => 'injected-password',
            'password_confirmation' => 'injected-password',
        ])->assertRedirect('/settings')->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Settings updated.']);

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertEquals($original, $user->fresh()->only(array_keys($original)));
        Notification::assertNothingSent();
    }

    public static function invalidNames(): array
    {
        return ['missing' => [[]], 'empty' => [['name' => '']], 'array' => [['name' => []]], 'too long' => [['name' => str_repeat('a', 256)]]];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_name_is_rejected(array $data): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/settings', $data)->assertSessionHasErrors('name');
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_menu_contains_settings_account_and_log_out_in_order(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertOk()
            ->assertSeeInOrder(['Settings', 'Account', 'Log out']);
    }
}
