<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingTriggers = $this->existingSqliteTriggers();
        Schema::table('company_service_areas', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('end_reason')->nullable();
        });
        Schema::table('property_company_assignments', function (Blueprint $table): void {
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('end_reason')->nullable();
        });
        Schema::table('property_occupancies', function (Blueprint $table): void {
            // Existing responsibility is unknown; operators explicitly choose contacts for legacy households.
            $table->boolean('is_billing_contact')->default(false);
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('end_reason')->nullable();
            $billingSlot = $table->unsignedTinyInteger('billing_slot')->nullable();
            $expression = 'CASE WHEN move_out_date IS NULL AND is_billing_contact = 1 THEN 1 ELSE NULL END';
            // SQLite cannot add a stored generated column to a populated table.
            if (DB::getDriverName() === 'sqlite') {
                $billingSlot->virtualAs($expression);
            } else {
                $billingSlot->storedAs($expression);
            }
            $table->unique(['property_id', 'billing_slot'], 'occupancy_one_open_billing_contact');
        });
        foreach (['insert', 'update'] as $event) {
            $condition = 'NEW.is_billing_contact NOT IN (0,1)';
            $name = 'p5_billing_contact_'.$event;
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER $name BEFORE $event ON property_occupancies WHEN ($condition) BEGIN SELECT RAISE(ABORT, 'Invalid billing contact flag'); END");
            } else {
                DB::unprepared("CREATE TRIGGER $name BEFORE $event ON property_occupancies FOR EACH ROW BEGIN IF ($condition) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid billing contact flag'; END IF; END");
            }
        }
        $this->restoreSqliteTriggers($existingTriggers);
    }

    public function down(): void
    {
        $existingTriggers = $this->existingSqliteTriggers();
        foreach (['insert', 'update'] as $event) {
            DB::unprepared('DROP TRIGGER IF EXISTS p5_billing_contact_'.$event);
        }
        Schema::table('property_occupancies', function (Blueprint $table): void {
            $table->dropUnique('occupancy_one_open_billing_contact');
            $table->dropColumn(['billing_slot', 'is_billing_contact', 'end_reason']);
            $table->dropConstrainedForeignId('ended_by');
        });
        Schema::table('property_company_assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ended_by');
            $table->dropColumn('end_reason');
        });
        Schema::table('company_service_areas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('ended_by');

            $table->dropColumn('end_reason');
        });
        $this->restoreSqliteTriggers($existingTriggers);
    }

    private function existingSqliteTriggers(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        // SQLite rebuilds tables when adding/removing foreign keys; preserve the earlier invariants.
        return DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND name NOT LIKE 'p5_%' AND tbl_name IN ('company_service_areas', 'property_company_assignments', 'property_occupancies')");
    }

    private function restoreSqliteTriggers(array $triggers): void
    {
        foreach ($triggers as $trigger) {
            if (! DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger->name)->exists()) {
                DB::unprepared($trigger->sql);
            }
        }
    }
};
