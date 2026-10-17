<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VerificationEmailSender;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class VerificationEmailCooldownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);

        $this->freezeTime();
    }

    public function test_registration_and_resend_share_one_cooldown(): void
    {
        Notification::fake();
        $this->post('/register', [
            'cf-turnstile-response' => 'test-token',
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('verification.notice'));
        $user = User::sole();

        $this->from('/email/verify')->post('/email/verification-resend')
            ->assertRedirect('/email/verify')->assertSessionHasErrors('verification');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->assertSame(60, app(VerificationEmailSender::class)->remainingSeconds($user));
    }

    public function test_google_registration_and_resend_share_one_cooldown(): void
    {
        Notification::fake();
        $google = (new SocialiteUser)->setRaw(['email_verified' => true])
            ->map(['id' => 'google-id', 'email' => 'person@example.com', 'name' => 'Person']);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect('/');
        $user = User::sole();
        $this->post('/email/verification-resend')->assertSessionHasErrors('verification');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_page_displays_server_remaining_time_without_starting_or_extending_cooldown(): void
    {
        Notification::fake();
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/email/verify')->assertOk()
            ->assertSee('data-remaining-seconds="0"', false);
        Notification::assertNothingSent();

        $this->post('/email/verification-resend')->assertSessionHas('flash', ['type' => 'success', 'message' => 'A new verification link has been sent.']);
        $this->get('/email/verify')->assertOk()->assertSee('data-remaining-seconds="60"', false)->assertSee('Resend email in 01:00');
        $this->travel(30)->seconds();
        $this->get('/email/verify')->assertOk()->assertSee('data-remaining-seconds="30"', false);
        $this->get('/email/verify')->assertOk()->assertSee('data-remaining-seconds="30"', false);
        $this->travel(30)->seconds();
        $this->get('/email/verify')->assertOk()->assertSee('data-remaining-seconds="0"', false);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_resend_is_rejected_until_the_deadline_and_allowed_at_sixty_seconds(): void
    {
        Notification::fake();
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/email/verification-resend')->assertSessionHas('flash', ['type' => 'success', 'message' => 'A new verification link has been sent.']);
        $this->travel(59)->seconds();
        $this->post('/email/verification-resend')->assertSessionHasErrors('verification');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->travel(1)->second();
        $this->post('/email/verification-resend')->assertSessionHas('flash', ['type' => 'success', 'message' => 'A new verification link has been sent.']);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
        $this->assertSame(60, app(VerificationEmailSender::class)->remainingSeconds($user));
    }

    public function test_email_change_has_its_own_cooldown_and_blocks_immediate_resend(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $sender = app(VerificationEmailSender::class);
        $sender->send($user);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => 'new-address@example.com', 'current_password' => 'password',
        ])->assertRedirect(route('verification.notice'));
        $user->refresh();
        $this->assertSame('new-address@example.com', $user->email);
        $this->assertSame(60, $sender->remainingSeconds($user));
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
        $this->post('/email/verification-resend')->assertSessionHasErrors('verification');
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_users_do_not_share_cooldowns_and_verified_users_receive_no_mail(): void
    {
        Notification::fake();
        $sender = app(VerificationEmailSender::class);
        $first = User::factory()->unverified()->create();
        $second = User::factory()->unverified()->create();
        $verified = User::factory()->create();
        $this->assertTrue($sender->send($first));
        $this->assertSame(0, $sender->remainingSeconds($second));
        $this->assertTrue($sender->send($second));
        $this->assertFalse($sender->send($verified));
        Notification::assertSentToTimes($first, VerifyEmail::class, 1);
        Notification::assertSentToTimes($second, VerifyEmail::class, 1);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_failed_delivery_does_not_start_cooldown_and_releases_lock(): void
    {
        $user = User::factory()->unverified()->create();
        $sender = app(VerificationEmailSender::class);
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Mail unavailable.'));
        try {
            $sender->send($user);
            $this->fail('Expected delivery failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Mail unavailable.', $exception->getMessage());
        }
        $this->assertSame(0, $sender->remainingSeconds($user));
        Notification::fake();
        $this->assertTrue($sender->send($user));
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_in_progress_delivery_prevents_another_sender_from_sending(): void
    {
        $user = User::factory()->unverified()->create();
        $sender = app(VerificationEmailSender::class);
        Notification::shouldReceive('send')->once()->andReturnUsing(function () use ($sender, $user): void {
            $this->assertFalse($sender->send($user));
        });
        $this->assertTrue($sender->send($user));
        $this->assertSame(60, $sender->remainingSeconds($user));
    }
}
