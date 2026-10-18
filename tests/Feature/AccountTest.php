<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_renders_for_a_user_with_a_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk()->assertSee($user->email)
            ->assertSee('name="current_password"', false)->assertDontSee('Set a password to edit your account');
    }

    public function test_unchanged_email_requires_current_password_without_changing_account_or_sending_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();
        $this->actingAs($user)->from('/account')->patch('/account', ['email' => $user->email])
            ->assertRedirect('/account')->assertSessionHasErrors('current_password');
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->patch('/account', ['email' => $user->email, 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->patch('/account', ['email' => $user->email, 'current_password' => 'password'])
            ->assertRedirect('/account')->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'Account updated.']);
        $this->assertSame($original, $user->fresh()->getAttributes());
        Notification::assertNothingSent();
    }

    public function test_email_change_requires_current_password_and_sends_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['external_id' => 'linked-google']);
        $data = ['email' => 'changed@example.com'];
        $this->actingAs($user)->patch('/account', $data)->assertSessionHasErrors('current_password');
        $this->patch('/account', $data + ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->patch('/account', $data + ['current_password' => 'password'])->assertRedirect(route('verification.notice'));
        $this->assertSame('changed@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('linked-google', $user->fresh()->external_id);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_password_change_requires_current_password_and_rotates_remember_token(): void
    {
        $user = User::factory()->create();
        $token = $user->remember_token;
        $data = ['email' => $user->email, 'password' => 'updated-password', 'password_confirmation' => 'updated-password'];
        $this->actingAs($user)->patch('/account', $data)->assertSessionHasErrors('current_password');
        $this->patch('/account', $data + ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('updated-password', $user->fresh()->password));
        $this->assertNotSame($token, $user->fresh()->remember_token);
    }

    public function test_account_rejects_duplicate_email_short_password_and_mismatched_confirmation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->patch('/account', [
            'email' => $other->email, 'current_password' => 'password',
            'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_google_only_account_disables_identity_fields_and_hides_current_password(): void
    {
        $user = User::factory()->create(['password' => null, 'external_id' => 'google-id']);
        $response = $this->actingAs($user)->get('/account')->assertOk()->assertSee('Set a password to edit your account')
            ->assertDontSee('name="current_password"', false)->assertSee('Set password');
        foreach (['email'] as $name) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*\bname="'.$name.'"[^>]*\bdisabled\b/s', $response->getContent());
        }
    }

    public function test_first_password_preserves_identity_verification_and_google_link(): void
    {
        $user = User::factory()->create(['password' => null, 'external_id' => 'google-id']);
        $original = $user->only(['email', 'email_verified_at', 'external_id']);
        $token = $user->remember_token;
        $this->actingAs($user)->from('/account')->patch('/account', [
            'password' => 'first-password', 'password_confirmation' => 'first-password',
        ])->assertRedirect('/account')->assertSessionHasNoErrors()->assertSessionHas('flash', ['type' => 'success', 'message' => 'Password set. You can now edit your account and sign in with your email.']);
        $this->assertTrue(Hash::check('first-password', $user->fresh()->password));
        $this->assertEquals($original, $user->fresh()->only(array_keys($original)));
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->get('/account')->assertSee('name="current_password"', false)->assertDontSee('Set a password to edit your account');
        $this->post('/logout');
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);
        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'first-password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public static function forbiddenFirstPasswordFields(): array
    {
        return [['email', 'changed@example.com'], ['current_password', 'anything']];
    }

    #[DataProvider('forbiddenFirstPasswordFields')]
    public function test_first_password_rejects_identity_changes(string $field, string $value): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user)->patch('/account', [
            'password' => 'first-password', 'password_confirmation' => 'first-password', $field => $value,
        ])->assertSessionHasErrors($field);
        $this->assertNull($user->fresh()->password);
    }

    public function test_first_password_is_required_and_must_be_confirmed(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->actingAs($user)->patch('/account', [])->assertSessionHasErrors('password');
        $this->patch('/account', ['password' => 'first-password', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->password);
    }
}
