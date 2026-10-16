<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function guestPages(): array
    {
        return [['/login'], ['/register'], ['/forgot-password'], ['/reset-password/example?email=user@example.com']];
    }

    #[DataProvider('guestPages')]
    public function test_guest_authentication_pages_render(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_registration_hashes_password_assigns_user_role_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password',
            'role' => 'admin', 'email_verified_at' => now(),
        ])->assertRedirect(route('verification.notice'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_duplicate_email_and_invalid_password(): void
    {
        $user = User::factory()->create();
        $this->post('/register', [
            'name' => '', 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_login_uses_intended_destination_and_remember_cookie(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => url('/profile')])
            ->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect('/profile')
            ->assertCookie(auth()->guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_without_flashing_password(): void
    {
        $user = User::factory()->create();
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_google_only_user_cannot_log_in_with_a_password(): void
    {
        $user = User::factory()->create(['password' => null, 'external_id' => 'google-only']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_authentication_and_session_data(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['private-data' => 'secret'])
            ->post('/logout')->assertRedirect('/')->assertSessionMissing('private-data');
        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_from_guest_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/');
    }

    public function test_unverified_user_is_sent_to_verification_before_accessing_milestones(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('verification.notice'));

        $this->get('/email/verify')
            ->assertOk()
            ->assertSee('data-cooldown-button', false)
            ->assertSee('data-remaining-seconds="0"', false);
    }

    public function test_signed_verification_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($url)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_rejects_unsigned_expired_and_wrong_identity_links(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $params = ['id' => $user->id, 'hash' => sha1($user->email)];
        $this->get(route('verification.verify', $params))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), $params))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinute(), [
            'id' => $user->id, 'hash' => sha1('other@example.com'),
        ]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinute(), [
            'id' => $user->id + 1, 'hash' => sha1($user->email),
        ]))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_email_can_be_resent_and_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-resend')
            ->assertRedirect()
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'A new verification link has been sent.']);

        $this->post('/email/verification-resend')
            ->assertRedirect()->assertSessionHasErrors('verification');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_verified_users_are_not_sent_another_verification_email(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create())->post('/email/verification-resend')->assertRedirect('/');
        Notification::assertNothingSent();
    }

    public function test_password_recovery_sends_a_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'A password reset link has been sent.'])
            ->assertSessionHas('passwordResetSent', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_recovery_flash_and_restart_are_only_shown_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('passwordResetSent', true)
            ->assertSessionHasInput('email', $user->email);

        $this->get('/forgot-password')->assertOk()
            ->assertSee('A password reset link has been sent.')
            ->assertSee("Enter your email address and we'll send you a password reset link.", false)
            ->assertSee('data-restart="1"', false);

        $this->get('/forgot-password')->assertOk()
            ->assertDontSee('A password reset link has been sent.')
            ->assertSee("Enter your email address and we'll send you a password reset link.", false)
            ->assertSee('data-restart="0"', false)
            ->assertSessionMissing('flash')
            ->assertSessionMissing('passwordResetSent');
    }

    public function test_password_recovery_validates_email_and_rejects_unknown_users(): void
    {
        $this->post('/forgot-password', ['email' => 'invalid'])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => 'missing@example.com'])->assertSessionHasErrors('email');
    }

    public function test_password_recovery_is_throttled(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])
            ->assertSessionHas('flash', ['type' => 'success', 'message' => 'A password reset link has been sent.'])
            ->assertSessionHas('passwordResetSent', true);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])
            ->assertSessionHasErrors('email');

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public static function passwordStates(): array
    {
        return ['local account' => [false], 'Google-only account' => [true]];
    }

    #[DataProvider('passwordStates')]
    public function test_password_can_be_reset_including_for_google_only_accounts(bool $googleOnly): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create($googleOnly ? ['password' => null, 'external_id' => 'google-id'] : []);
        $oldToken = $user->remember_token;
        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('flash', ['type' => 'success', 'message' => __(Password::PasswordReset)]);
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
        Event::assertDispatched(PasswordReset::class);
    }

    public function test_invalid_reset_token_and_password_confirmation_leave_password_unchanged(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $oldHash = $user->password;
        DB::table('sessions')->insert([
            'id' => str_repeat('a', 40), 'user_id' => $user->id,
            'payload' => base64_encode('{}'), 'last_activity' => now()->timestamp,
        ]);
        $data = ['email' => $user->email, 'token' => 'invalid', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::createToken($user);
        $data['password_confirmation'] = 'different';
        $this->post('/reset-password', $data)->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => str_repeat('a', 40), 'user_id' => $user->id]);
    }

    public function test_password_reset_revokes_all_user_sessions_and_preserves_other_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $guardKey = auth()->guard()->getName();
        $sessionId = str_repeat('a', 40);

        foreach ([$sessionId => $user, str_repeat('b', 40) => $user, str_repeat('c', 40) => $other] as $id => $owner) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $owner->id,
                'payload' => base64_encode(json_encode([$guardKey => $owner->id])),
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->withCookie(config('session.cookie'), $sessionId)->get('/')->assertOk();
        $this->assertAuthenticatedAs($user);

        // Simulate recovery in a different browser with no in-memory session data.
        $this->app['session.store']->flush();
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), str_repeat('d', 40));

        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('flash', ['type' => 'success', 'message' => __(Password::PasswordReset)]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => str_repeat('c', 40), 'user_id' => $other->id]);

        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $sessionId)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'replacement-password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }
}
