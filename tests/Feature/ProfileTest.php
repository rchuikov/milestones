<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_renders_for_a_user_with_a_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->email)
            ->assertSee('name="current_password"', false)->assertDontSee('Set a password to edit your profile');
    }

    public function test_name_can_change_without_password_but_email_verification_is_preserved(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;
        $this->actingAs($user)->patch('/profile', ['name' => 'Updated Name', 'email' => $user->email])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
        Notification::assertNothingSent();
    }

    public function test_email_change_requires_current_password_and_sends_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['external_id' => 'linked-google']);
        $data = ['name' => $user->name, 'email' => 'changed@example.com'];
        $this->actingAs($user)->patch('/profile', $data)->assertSessionHasErrors('current_password');
        $this->patch('/profile', $data + ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->patch('/profile', $data + ['current_password' => 'password'])->assertRedirect(route('verification.notice'));
        $this->assertSame('changed@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('linked-google', $user->fresh()->external_id);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_password_change_requires_current_password_and_rotates_remember_token(): void
    {
        $user = User::factory()->create();
        $token = $user->remember_token;
        $data = ['name' => $user->name, 'email' => $user->email, 'password' => 'updated-password', 'password_confirmation' => 'updated-password'];
        $this->actingAs($user)->patch('/profile', $data)->assertSessionHasErrors('current_password');
        $this->patch('/profile', $data + ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('updated-password', $user->fresh()->password));
        $this->assertNotSame($token, $user->fresh()->remember_token);
    }

    public function test_profile_rejects_duplicate_email_short_password_and_mismatched_confirmation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->patch('/profile', [
            'name' => '', 'email' => $other->email, 'current_password' => 'password',
            'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_google_only_profile_disables_identity_fields_and_hides_current_password(): void
    {
        $user = User::factory()->create(['password' => null, 'external_id' => 'google-id']);
        $response = $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Set a password to edit your profile')
            ->assertDontSee('name="current_password"', false)->assertSee('Set password');
        foreach (['name', 'email'] as $name) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*\bname="'.$name.'"[^>]*\bdisabled\b/s', $response->getContent());
        }
    }

    public function test_first_password_preserves_identity_verification_and_google_link(): void
    {
        $user = User::factory()->create(['password' => null, 'external_id' => 'google-id']);
        $original = $user->only(['name', 'email', 'email_verified_at', 'external_id']);
        $token = $user->remember_token;
        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'password' => 'first-password', 'password_confirmation' => 'first-password',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertTrue(Hash::check('first-password', $user->fresh()->password));
        $this->assertEquals($original, $user->fresh()->only(array_keys($original)));
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->get('/profile')->assertSee('name="current_password"', false)->assertDontSee('Set a password to edit your profile');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'first-password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public static function forbiddenFirstPasswordFields(): array
    {
        return [['name', 'Changed'], ['email', 'changed@example.com'], ['current_password', 'anything']];
    }

    #[DataProvider('forbiddenFirstPasswordFields')]
    public function test_first_password_rejects_identity_changes(string $field, string $value): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user)->patch('/profile', [
            'password' => 'first-password', 'password_confirmation' => 'first-password', $field => $value,
        ])->assertSessionHasErrors($field);
        $this->assertNull($user->fresh()->password);
    }

    public function test_first_password_is_required_and_must_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user)->patch('/profile', [])->assertSessionHasErrors('password');
        $this->patch('/profile', ['password' => 'first-password', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->password);
    }
}
