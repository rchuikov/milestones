<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);

        $this->travelTo(now()->startOfSecond());
        Event::fake([Attempting::class, Failed::class]);
    }

    public function test_five_failed_logins_are_checked_before_lockout_and_only_safe_input_is_flashed(): void
    {
        $user = User::factory()->create();
        $key = 'login:'.$user->email.'|127.0.0.1';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->from('/login')->post('/login', [
                'cf-turnstile-response' => 'test-token',
                'email' => $user->email, 'password' => 'wrong', 'remember' => '1',
            ])->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'Invalid email or password. Attempts left: '.(5 - $attempt)])
                ->assertSessionHasInput('email', $user->email)
                ->assertSessionHasInput('remember', '1')
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('loginCooldown')
                ->assertSessionMissing('flash');
        }

        $this->post('/login', [
            'cf-turnstile-response' => 'test-token',
            'email' => $user->email, 'password' => 'wrong', 'remember' => '1',
        ])->assertRedirect('/login')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'danger', 'message' => 'Too many login attempts. Try again later.'])
            ->assertSessionHas('loginCooldown', 60)
            ->assertSessionHasInput('email', $user->email)
            ->assertSessionHasInput('remember', '1')
            ->assertSessionMissing('_old_input.password');

        Event::assertDispatchedTimes(Attempting::class, 5);
        Event::assertDispatchedTimes(Failed::class, 5);
        $this->assertSame(5, RateLimiter::attempts($key));
        $this->assertGuest();

        $response = $this->get('/login')->assertOk()
            ->assertSee('Too many login attempts. Try again later.')
            ->assertSee('data-remaining-seconds="60"', false)
            ->assertSee('Try again in 01:00')
            ->assertSee('data-restart="0"', false);
        $this->assertMatchesRegularExpression('/<button\b[^>]*\sdisabled(?:\s|=)[^>]*data-cooldown-button/s', $response->getContent());
    }

    public function test_login_cooldown_survives_reload_and_uses_the_current_ip_without_extending_the_deadline(): void
    {
        $email = 'PERSON@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $email, 'password' => 'wrong']);
        }

        $this->get('/login')->assertOk()->assertSee('data-remaining-seconds="60"', false);

        $this->travel(20)->seconds();
        $response = $this->get('/login')->assertOk()
            ->assertSessionMissing('loginCooldown')
            ->assertSee('data-remaining-seconds="40"', false)
            ->assertSee('Try again in 00:40')
            ->assertSee('value="'.$email.'"', false);
        $this->assertMatchesRegularExpression('/<button\b[^>]*\sdisabled(?:\s|=)[^>]*data-cooldown-button/s', $response->getContent());

        $this->get('/login')->assertOk()->assertSee('data-remaining-seconds="40"', false);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('/login')->assertOk()->assertSee('data-remaining-seconds="0"', false);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/login')->assertOk()->assertSee('data-remaining-seconds="40"', false);

        $this->travel(40)->seconds();
        $response = $this->get('/login')->assertOk()->assertSee('data-remaining-seconds="0"', false);
        $this->assertMatchesRegularExpression('/<button\b[^>]*\sdisabled(?:\s|=)[^>]*data-cooldown-button/s', $response->getContent());
        Event::assertDispatchedTimes(Attempting::class, 5);
    }

    public static function blockedPasswords(): array
    {
        return ['incorrect password' => ['wrong'], 'correct password' => ['password']];
    }

    #[DataProvider('blockedPasswords')]
    public function test_blocked_posts_do_not_check_credentials_increment_attempts_or_extend_the_deadline(string $password): void
    {
        $user = User::factory()->create();
        $key = 'login:'.$user->email.'|127.0.0.1';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'wrong']);
        }

        $this->travel(20)->seconds();

        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => $password])
            ->assertRedirect('/login')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('flash', ['type' => 'danger', 'message' => 'Too many login attempts. Try again later.'])
            ->assertSessionHas('loginCooldown', 40)
            ->assertSessionMissing('_old_input.password');

        Event::assertDispatchedTimes(Attempting::class, 5);
        $this->assertSame(5, RateLimiter::attempts($key));
        $this->assertSame(40, RateLimiter::availableIn($key));
        $this->assertGuest();
    }

    public function test_correct_password_on_the_fifth_attempt_succeeds_and_clears_the_limiter(): void
    {
        $user = User::factory()->create();
        $key = 'login:'.$user->email.'|127.0.0.1';

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'wrong']);
        }

        $this->withSession(['url.intended' => url('/profile')])
            ->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect('/profile')
            ->assertCookie(auth()->guard()->getRecallerName())
            ->assertSessionMissing('loginEmail')
            ->assertSessionMissing('loginCooldown')
            ->assertSessionMissing('flash');

        Event::assertDispatchedTimes(Attempting::class, 5);
        Event::assertDispatchedTimes(Failed::class, 4);
        $this->assertSame(0, RateLimiter::attempts($key));
        $this->assertSame(0, RateLimiter::availableIn($key));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_allowed_again_at_the_deadline(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'wrong']);
        }

        $this->travel(59)->seconds();
        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'password'])
            ->assertSessionHas('loginCooldown', 1);
        $this->assertGuest();

        $this->travel(1)->second();
        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        Event::assertDispatchedTimes(Attempting::class, 6);
    }

    public function test_email_case_shares_the_limiter_but_other_emails_and_ips_do_not(): void
    {
        $email = 'person@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $attempt % 2 ? strtoupper($email) : $email, 'password' => 'wrong']);
        }

        $this->assertSame(5, RateLimiter::attempts('login:'.$email.'|127.0.0.1'));

        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => strtoupper($email), 'password' => 'wrong'])
            ->assertSessionHas('loginCooldown', 60);
        Event::assertDispatchedTimes(Attempting::class, 5);

        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => 'other@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password. Attempts left: 4']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => $email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password. Attempts left: 4']);
        $this->assertSame(1, RateLimiter::attempts('login:'.$email.'|192.0.2.10'));
        $this->assertSame(5, RateLimiter::attempts('login:'.$email.'|127.0.0.1'));
    }

    public function test_invalid_login_input_does_not_consume_an_attempt(): void
    {
        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => 'person@example.com'])->assertSessionHasErrors('password');
        $this->post('/login', ['cf-turnstile-response' => 'test-token', 'email' => 'invalid', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertSame(0, RateLimiter::attempts('login:person@example.com|127.0.0.1'));
        $this->assertSame(0, RateLimiter::attempts('login:invalid|127.0.0.1'));
        Event::assertNotDispatched(Attempting::class);
    }

    public function test_registration_allows_five_requests_per_ip_then_blocks_until_ten_minutes(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', ['cf-turnstile-response' => 'test-token'])->assertRedirect()->assertSessionHasErrors(['name', 'email', 'password']);
        }

        $this->post('/register', ['cf-turnstile-response' => 'test-token'])->assertStatus(429)->assertHeader('Retry-After', '600');
        $this->get('/register')->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/register', ['cf-turnstile-response' => 'test-token'])->assertRedirect()->assertSessionHasErrors('email');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->travel(599)->seconds();
        $this->post('/register', ['cf-turnstile-response' => 'test-token'])->assertStatus(429)->assertHeader('Retry-After', '1');
        $this->travel(1)->second();
        $this->post('/register', ['cf-turnstile-response' => 'test-token'])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
    }
}
