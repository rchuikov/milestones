<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class GoogleAccountMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_google_migration_rollback_preserves_users_and_milestone_history(): void
    {
        $local = User::factory()->create();
        $hash = $local->password;
        $google = User::factory()->create(['password' => null, 'external_id' => 'google-id']);
        $milestone = $google->milestones()->create(['name' => 'Keep this history']);
        $period = $milestone->periods()->create(['started_at' => now()->subDay()]);

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('users', 'external_id'));
        $this->assertDatabaseCount('users', 2);
        $this->assertSame($hash, $local->fresh()->password);
        $this->assertNotEmpty($google->fresh()->password);
        $this->assertNotSame($hash, $google->fresh()->password);
        $this->assertDatabaseHas('milestones', ['id' => $milestone->id, 'user_id' => $google->id]);
        $this->assertDatabaseHas('milestone_periods', ['id' => $period->id, 'milestone_id' => $milestone->id]);

        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('users', 'external_id'));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('milestone_periods', ['id' => $period->id]);
    }
}
