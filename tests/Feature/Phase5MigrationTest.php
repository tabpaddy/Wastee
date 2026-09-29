<?php

namespace Tests\Feature;

use App\Models\PropertyOccupancy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase5MigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_and_reapply_preserve_legacy_records_without_guessing_billing_responsibility(): void
    {
        $occupancy = PropertyOccupancy::factory()->create(['is_billing_contact' => true]);
        $originalStart = $occupancy->move_in_date->toDateString();
        $migration = require database_path('migrations/2026_09_29_000000_extend_operational_history.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('property_occupancies', 'is_billing_contact'));
        $this->assertDatabaseHas('property_occupancies', ['id' => $occupancy->id]);
        $migration->up();
        $this->assertFalse($occupancy->fresh()->is_billing_contact);
        $this->assertSame($originalStart, $occupancy->fresh()->move_in_date->toDateString());
        $this->assertFalse(Schema::hasColumn('company_service_areas', 'uuid'));
        $this->expectException(QueryException::class);
        DB::table('property_occupancies')->where('id', $occupancy->id)->update(['move_out_date' => $originalStart]);
    }

    public function test_lifecycle_actor_foreign_keys_are_enforced(): void
    {
        $occupancy = PropertyOccupancy::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('property_occupancies')->where('id', $occupancy->id)->update(['ended_by' => 999999]);
    }
}
