<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.turnstile.secret_key' => 'test-secret', 'services.turnstile.site_key' => 'test-site']);
    }

    public function test_successful_verification_sends_the_token_and_configured_secret(): void
    {
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);

        $validator = Validator::make(['token' => 'test-token'], ['token' => [new Turnstile]]);
        $this->assertTrue($validator->passes());

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret'
            && $request['response'] === 'test-token');
        Http::assertSentCount(1);
    }

    public static function rejectedResponses(): array
    {
        return [
            'rejected token' => [['success' => false], 200],
            'missing success' => [[], 200],
            'string success' => [['success' => 'true'], 200],
            'numeric success' => [['success' => 1], 200],
            'server error' => [['success' => true], 500, 'Security verification is temporarily unavailable. Please try again.'],
            'client error' => [['success' => true], 400],
            'invalid JSON' => ['not json', 200],
        ];
    }

    #[DataProvider('rejectedResponses')]
    public function test_unsuccessful_verification_fails_validation(array|string $body, int $status, string $message = 'Security verification failed. Please try again.'): void
    {
        if ($status >= 500) {
            Log::shouldReceive('warning')->once()->with('Turnstile Siteverify server error.', ['status' => $status]);
        }
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response($body, $status)]);

        $validator = Validator::make(['token' => 'test-token'], ['token' => [new Turnstile]]);
        $this->assertTrue($validator->fails());
        $this->assertSame($message, $validator->errors()->first('token'));
    }

    public function test_connection_failure_returns_a_validation_error(): void
    {
        Log::shouldReceive('warning')->once()->with('Turnstile Siteverify connection failed.', [
            'exception_type' => ConnectionException::class,
        ]);
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::failedConnection()]);

        $validator = Validator::make(['token' => 'test-token'], ['token' => [new Turnstile]]);
        $this->assertTrue($validator->fails());
        $this->assertSame('Security verification is temporarily unavailable. Please try again.', $validator->errors()->first('token'));
    }

    public static function protectedRequests(): array
    {
        $cases = [];
        foreach (['/register', '/login', '/forgot-password'] as $path) {
            foreach (['missing' => null, 'empty' => '', 'array' => ['token'], 'oversized' => str_repeat('a', 2049), 'rejected' => 'invalid-token', 'unavailable' => 'test-token'] as $state => $token) {
                $cases[$path.' '.$state] = [$path, $state, $token];
            }
        }

        return $cases;
    }

    #[DataProvider('protectedRequests')]
    public function test_protected_actions_require_verification(string $path, string $state, mixed $token): void
    {
        Notification::fake();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => $state === 'unavailable'
            ? Http::failedConnection()
            : Http::response(['success' => false])]);
        $user = User::factory()->create();
        $data = [
            'name' => 'New User',
            'email' => $path === '/register' ? 'new@example.com' : $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
        if ($state !== 'missing') {
            $data['cf-turnstile-response'] = $token;
        }

        $this->from($path)->post($path, $data)
            ->assertRedirect($path)
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertSame(0, RateLimiter::attempts('login:'.$user->email.'|127.0.0.1'));
        Notification::assertNothingSent();
        if (in_array($state, ['missing', 'empty', 'array', 'oversized'])) {
            Http::assertNothingSent();
        } else {
            Http::assertSentCount(1);
        }
    }

    public static function technicalResponses(): array
    {
        return [
            'invalid secret' => ['invalid-input-secret', true],
            'service error' => ['internal-error', true],
            'ordinary rejection' => ['invalid-input-response', false],
        ];
    }

    #[DataProvider('technicalResponses')]
    public function test_only_technical_response_errors_are_logged_without_credentials(string $code, bool $technical): void
    {
        if ($technical) {
            Log::shouldReceive('warning')->once()->with('Turnstile Siteverify technical error.', [
                'error_codes' => [$code],
            ]);
        } else {
            Log::shouldReceive('warning')->never();
            Log::shouldReceive('error')->never();
        }
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => false,
            'error-codes' => [$code],
            'secret' => 'must-not-be-logged',
            'response' => 'must-not-be-logged',
        ])]);

        $validator = Validator::make(['token' => 'test-token'], ['token' => [new Turnstile]]);
        $this->assertTrue($validator->fails());
        $this->assertSame($technical
            ? 'Security verification is temporarily unavailable. Please try again.'
            : 'Security verification failed. Please try again.', $validator->errors()->first('token'));
    }

    public function test_missing_secret_is_logged_and_rejected_without_contacting_cloudflare(): void
    {
        config(['services.turnstile.secret_key' => null]);
        Log::shouldReceive('error')->once()->with('Turnstile secret key is not configured.');

        $validator = Validator::make(['token' => 'test-token'], ['token' => [new Turnstile]]);
        $this->assertTrue($validator->fails());
        $this->assertSame('Security verification is temporarily unavailable. Please try again.', $validator->errors()->first('token'));
        Http::assertNothingSent();
    }

    public static function throttledActions(): array
    {
        return [['/login', 20, 60], ['/forgot-password', 10, 600]];
    }

    #[DataProvider('throttledActions')]
    public function test_ip_throttle_blocks_siteverify_until_the_window_expires(string $path, int $limit, int $seconds): void
    {
        $this->freezeTime();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false])]);
        $data = ['email' => 'person@example.com', 'password' => 'password', 'cf-turnstile-response' => 'test-token'];

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->post($path, $data)->assertSessionHasErrors('cf-turnstile-response');
        }
        Http::assertSentCount($limit);

        $this->post($path, $data)->assertStatus(429)->assertHeader('Retry-After', (string) $seconds);
        Http::assertSentCount($limit);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post($path, $data)->assertRedirect()->assertSessionHasErrors('cf-turnstile-response');
        Http::assertSentCount($limit + 1);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->travel($seconds - 1)->seconds();
        $this->post($path, $data)->assertStatus(429)->assertHeader('Retry-After', '1');
        Http::assertSentCount($limit + 1);

        $this->travel(1)->second();
        $this->post($path, $data)->assertRedirect()->assertSessionHasErrors('cf-turnstile-response');
        Http::assertSentCount($limit + 2);
    }

    public function test_widget_displays_the_server_validation_error(): void
    {
        $this->withViewErrors(['cf-turnstile-response' => 'Security verification failed. Please try again.'])
            ->blade('<x-turnstile />')
            ->assertSee('Security verification failed. Please try again.');
    }

    public static function protectedPages(): array
    {
        return [['/register'], ['/login'], ['/forgot-password']];
    }

    #[DataProvider('protectedPages')]
    public function test_authentication_forms_render_the_widget_and_start_blocked(string $path): void
    {
        $response = $this->get($path)->assertOk()
            ->assertSee('data-externally-blocked="1"', false)
            ->assertSee('data-sitekey="test-site"', false)
            ->assertSee('data-theme="light"', false)
            ->assertSee('data-callback="turnstileSuccess"', false)
            ->assertSee('data-expired-callback="turnstileBlocked"', false)
            ->assertSee('data-error-callback="turnstileError"', false)
            ->assertSee('data-timeout-callback="turnstileBlocked"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"'));
        $this->assertMatchesRegularExpression('/<button\b[^>]*type="submit"[^>]*\sdisabled(?:\s|=)/s', $response->getContent());
        Http::assertNothingSent();
    }
}
