<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['user'], ['admin']];
    }

    #[DataProvider('roles')]
    public function test_command_creates_verified_user_with_chosen_role_and_hashed_password(string $role): void
    {
        $this->artisan('user:create')->expectsQuestion('Name', 'Command User')
            ->expectsQuestion('Email', 'command@example.com')
            ->expectsChoice('Role', $role, ['user', 'admin'])
            ->expectsQuestion('Password', 'secure-password')
            ->expectsQuestion('Confirm password', 'secure-password')
            ->expectsOutput('User command@example.com created.')->assertSuccessful();
        $user = User::sole();
        $this->assertSame(UserRole::from($role), $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secure-password', $user->password));
    }

    public function test_command_rejects_invalid_data_without_creating_user(): void
    {
        $this->artisan('user:create')->expectsQuestion('Name', '')
            ->expectsQuestion('Email', 'invalid')->expectsChoice('Role', 'user', ['user', 'admin'])
            ->expectsQuestion('Password', 'short')->expectsQuestion('Confirm password', 'different')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'command@example.com']);
        $this->artisan('user:create')->expectsQuestion('Name', 'Duplicate')
            ->expectsQuestion('Email', 'command@example.com')->expectsChoice('Role', 'user', ['user', 'admin'])
            ->expectsQuestion('Password', 'secure-password')->expectsQuestion('Confirm password', 'secure-password')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}
