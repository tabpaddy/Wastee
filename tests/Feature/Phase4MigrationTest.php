<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase4MigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_legacy_memberships_are_backfilled_and_migration_rolls_back_cleanly(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $membership = CompanyMembership::create(['company_id' => $company->id, 'user_id' => $owner->id, 'joined_at' => now()->subMonth()]);
        $former = CompanyMembership::create(['company_id' => $company->id, 'user_id' => User::factory()->create()->id,
            'status' => 'inactive', 'joined_at' => now()->subYear(), 'left_at' => now()->subMonths(2)]);
        $migration = require database_path('migrations/2026_09_28_000000_add_staff_membership_history.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('company_membership_periods'));
        DB::table('roles')->insert(['company_id' => $company->id, 'name' => 'Legacy Role', 'guard_name' => 'web']);
        $migration->up();
        $this->assertSame(2, DB::table('company_membership_periods')->count());
        $this->assertSame($membership->joined_at->toDateTimeString(), $membership->periods()->sole()->joined_at->toDateTimeString());
        $this->assertNull($membership->periods()->sole()->left_at);
        $this->assertSame($former->left_at->toDateTimeString(), $former->periods()->sole()->left_at->toDateTimeString());
        $this->assertSame('7', DB::table('roles')->where('name', 'Legacy Role')->value('uuid')[14]);
        $migration->down();
        $this->assertTrue(Schema::hasTable('company_memberships'));
        $this->assertFalse(Schema::hasColumn('roles', 'uuid'));
        $migration->up();
    }
}
