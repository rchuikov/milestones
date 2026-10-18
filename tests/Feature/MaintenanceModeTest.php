<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryStorage = sys_get_temp_dir().'/milestones-maintenance-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->temporaryStorage.'/framework', recursive: true);
        $this->app->useStoragePath($this->temporaryStorage);

        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory($this->temporaryStorage);
        } finally {
            parent::tearDown();
        }
    }

    private function enableMaintenance(): void
    {
        File::put(storage_path('framework/app-maintenance'), '');
    }

    public function test_without_flag_guests_and_users_keep_normal_access(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/register')->assertOk();
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
    }

    public static function blockedGuestRequests(): array
    {
        return [
            'home' => ['GET', '/'],
            'account' => ['GET', '/account'],
            'settings' => ['GET', '/settings'],
            'settings update' => ['PATCH', '/settings'],
            'registration form' => ['GET', '/register'],
            'registration submission' => ['POST', '/register'],
            'password recovery form' => ['GET', '/forgot-password'],
            'password recovery submission' => ['POST', '/forgot-password'],
            'password reset form' => ['GET', '/reset-password/example'],
            'password reset submission' => ['POST', '/reset-password'],
        ];
    }

    #[DataProvider('blockedGuestRequests')]
    public function test_guests_receive_maintenance_page(string $method, string $path): void
    {
        $this->enableMaintenance();

        $this->call($method, $path)
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300')
            ->assertViewIs('maintenance.index')
            ->assertSee('scheduled maintenance')
            ->assertDontSee('Log out');

        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_regular_users_are_blocked_and_can_log_out_from_maintenance_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $this->enableMaintenance();

        $this->actingAs($user)->get('/')
            ->assertStatus(503)
            ->assertViewIs('maintenance.index')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Log out');

        $this->patch('/settings', ['name' => 'Changed name'])
            ->assertStatus(503);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->patch('/account', ['email' => 'changed@example.com', 'current_password' => 'password'])
            ->assertStatus(503);
        $this->assertSame($user->email, $user->fresh()->email);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/')->assertStatus(503);
    }

    public function test_unverified_regular_users_receive_maintenance_instead_of_verification_redirect(): void
    {
        $this->enableMaintenance();

        $this->actingAs(User::factory()->unverified()->create())->get('/')
            ->assertStatus(503)
            ->assertViewIs('maintenance.index');
        $this->get('/email/verify')->assertStatus(503);
        $this->post('/email/verification-resend')->assertStatus(503);
    }

    public static function loginRoles(): array
    {
        return [
            'admin' => [UserRole::Admin, 200],
            'regular user' => [UserRole::User, 503],
        ];
    }

    #[DataProvider('loginRoles')]
    public function test_password_login_remains_available_and_session_role_controls_access(UserRole $role, int $status): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->enableMaintenance();

        $this->get('/login')->assertOk();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf-turnstile-response' => 'test-token',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        Auth::forgetGuards();

        $this->get('/')->assertStatus($status);
        $this->get('/account')->assertStatus($status);
        $this->get('/settings')->assertStatus($status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_update_settings_during_maintenance(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->enableMaintenance();

        $this->actingAs($admin)->patch('/settings', [
            'name' => 'Updated Admin',
        ])->assertRedirect();

        $this->assertSame('Updated Admin', $admin->fresh()->name);
        $this->get('/milestones/create')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    #[DataProvider('loginRoles')]
    public function test_google_login_remains_available_and_respects_role(UserRole $role, int $status): void
    {
        $user = User::factory()->create(['role' => $role, 'external_id' => 'google-maintenance']);
        $identity = (new SocialiteUser)->map([
            'id' => $user->external_id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/example'));
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->twice()->with('google')->andReturn($provider);
        $this->enableMaintenance();

        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/example');
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertStatus($status);
    }

    public function test_removing_flag_restores_access_without_logging_in_again(): void
    {
        $this->enableMaintenance();
        $this->actingAs(User::factory()->create())->get('/')->assertStatus(503);

        File::delete(storage_path('framework/app-maintenance'));

        $this->get('/')->assertOk();
        $this->get('/account')->assertOk();
    }
}
