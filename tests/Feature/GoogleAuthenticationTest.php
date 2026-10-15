<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function googleUser(string $email = 'person@gmail.com', string $id = 'google-123', array $raw = [], ?string $name = 'Google User'): void
    {
        $user = (new SocialiteUser)->setRaw($raw)->map(['id' => $id, 'email' => $email, 'name' => $name]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    public function test_redirect_uses_google_provider(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/example'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/example');
    }

    public function test_real_google_redirect_stores_state_and_requests_identity_scopes(): void
    {
        config(['services.google' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => url('/auth/google/callback'),
        ]]);
        $response = $this->get('/auth/google')->assertRedirect()->assertSessionHas('state');
        $url = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(session('state'), $query['state']);
        $this->assertSame('test-client', $query['client_id']);
        foreach (['openid', 'profile', 'email'] as $scope) {
            $this->assertContains($scope, explode(' ', $query['scope']));
        }
    }

    public function test_real_provider_rejects_forged_state_before_any_network_request(): void
    {
        config(['services.google' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => url('/auth/google/callback'),
        ]]);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturnUsing(
            fn () => (new GoogleProvider(request(), 'test-client', 'test-secret', url('/auth/google/callback')))
                ->setHttpClient(new Client(['handler' => new MockHandler]))
        );
        $this->withSession(['state' => 'original-state'])
            ->get('/auth/google/callback?state=forged-state&code=example')
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function googleIdentities(): array
    {
        return [
            'Gmail' => ['person@gmail.com', [], true],
            'Workspace' => ['person@example.com', ['email_verified' => true, 'hd' => 'example.com'], true],
            'third-party verified email' => ['person@example.com', ['email_verified' => true], false],
            'unverified Workspace' => ['person@example.com', ['email_verified' => false, 'hd' => 'example.com'], false],
            'unverified third-party email' => ['person@example.com', [], false],
        ];
    }

    #[DataProvider('googleIdentities')]
    public function test_new_google_account_verification_depends_on_email_authority(string $email, array $raw, bool $verified): void
    {
        Notification::fake();
        $this->googleUser($email, raw: $raw);
        $this->get('/auth/google/callback')->assertRedirect('/');
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->external_id);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertNull($user->password);
        $this->assertSame($verified, $user->hasVerifiedEmail());
        if ($verified) {
            Notification::assertNothingSent();
        } else {
            Notification::assertSentTo($user, VerifyEmail::class);
            $this->get('/')->assertRedirect(route('verification.notice'));
        }
    }

    public function test_missing_google_name_falls_back_to_email(): void
    {
        $this->googleUser(name: null);
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertSame('person@gmail.com', User::sole()->name);
    }

    public function test_existing_google_id_logs_in_after_local_email_change_without_overwriting_profile(): void
    {
        Notification::fake();
        $user = User::factory()->create(['external_id' => 'google-123', 'email' => 'changed@example.com']);
        $original = $user->fresh()->getAttributes();
        $this->googleUser();
        $this->withSession(['url.intended' => url('/profile')])->get('/auth/google/callback')->assertRedirect('/profile');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, $user->fresh()->getAttributes());
        Notification::assertNothingSent();
    }

    public function test_trusted_google_email_links_verified_account_without_changing_password(): void
    {
        $user = User::factory()->create(['email' => 'person@gmail.com']);
        $hash = $user->password;
        $verifiedAt = $user->email_verified_at;
        $this->googleUser();
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->external_id);
        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_email_cannot_claim_unverified_local_account(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'person@gmail.com']);
        $original = $user->fresh()->getAttributes();
        $this->googleUser();

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_third_party_email_cannot_link_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);
        $this->googleUser($user->email, raw: ['email_verified' => true]);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->external_id);
    }

    public static function conflictingEmails(): array
    {
        return [['person@gmail.com'], ['person@example.com']];
    }

    #[DataProvider('conflictingEmails')]
    public function test_email_match_never_overrides_conflicting_google_id(string $email): void
    {
        $user = User::factory()->create(['email' => $email, 'external_id' => 'original-google']);
        $this->googleUser($email, 'another-google');
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('original-google', $user->fresh()->external_id);
    }

    public static function oauthErrors(): array
    {
        return [['access_denied', 'Google sign-in was cancelled.'], ['server_error', 'Google sign-in could not be completed. Please try again.']];
    }

    #[DataProvider('oauthErrors')]
    public function test_provider_denial_clears_state_and_does_not_call_google(string $error, string $message): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->withSession(['state' => 'oauth-state'])->get('/auth/google/callback?error='.$error)
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => $message])->assertSessionMissing('state');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_state_returns_to_login_without_creating_an_account(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function transportErrors(): array
    {
        return [['connection'], ['http'], ['request']];
    }

    #[DataProvider('transportErrors')]
    public function test_google_transport_errors_are_handled_and_logged_without_credentials(string $kind): void
    {
        $request = new Request('POST', 'https://oauth2.googleapis.com/token');
        $exception = match ($kind) {
            'connection' => new ConnectException('sensitive-token-must-not-be-logged', $request),
            'http' => RequestException::create($request, new Response(503)),
            'request' => new RequestException('sensitive-token-must-not-be-logged', $request),
        };
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow($exception);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        Log::shouldReceive('warning')->once()->with('Google sign-in request failed.', [
            'exception_type' => $exception::class, 'status' => $kind === 'http' ? 503 : null,
        ]);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
