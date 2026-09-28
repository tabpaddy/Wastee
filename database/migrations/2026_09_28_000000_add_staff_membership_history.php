<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique();
        });
        DB::table('roles')->orderBy('id')->eachById(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['uuid' => (string) Str::uuid7()]);
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
        Schema::table('staff_invitations', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        DB::table('staff_invitations')->orderBy('id')->eachById(function (object $invitation): void {
            $roleId = DB::table('roles')->where('company_id', $invitation->company_id)
                ->where('name', $invitation->role_name)->where('guard_name', 'web')->value('id');
            DB::table('staff_invitations')->where('id', $invitation->id)->update(['role_id' => $roleId]);
        });
        Schema::create('company_membership_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_membership_id')->constrained()->restrictOnDelete();
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->foreignId('joined_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('left_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('join_reason')->nullable();
            $table->text('leave_reason')->nullable();
            $table->json('joined_roles')->nullable();
            $table->json('left_roles')->nullable();
            $table->timestamps();
            $table->unsignedTinyInteger('open_slot')->nullable()->storedAs('CASE WHEN left_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['company_membership_id', 'open_slot'], 'membership_period_one_open');
            $table->index(['company_membership_id', 'joined_at'], 'membership_period_start');
        });
        DB::table('company_memberships')->orderBy('id')->eachById(function (object $membership): void {
            // Unknown legacy departures are not invented. Their open episode is closed on an explicit rejoin.
            $reason = $membership->status === 'inactive' && $membership->left_at === null
                ? 'Legacy inactive membership; departure time was not recorded.'
                : 'Backfilled from existing membership dates.';
            DB::table('company_membership_periods')->insert([
                'company_membership_id' => $membership->id,
                'joined_at' => $membership->joined_at,
                'left_at' => $membership->left_at,
                'join_reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->installPeriodConstraints();
    }

    private function installPeriodConstraints(): void
    {
        $invalid = 'NEW.left_at IS NOT NULL AND NEW.left_at <= NEW.joined_at';
        $overlap = 'EXISTS (SELECT 1 FROM company_membership_periods p WHERE p.company_membership_id = NEW.company_membership_id AND p.id <> NEW.id AND (NEW.left_at IS NULL OR p.joined_at < NEW.left_at) AND (p.left_at IS NULL OR p.left_at > NEW.joined_at))';
        foreach (['INSERT', 'UPDATE'] as $operation) {
            $trigger = 'membership_period_'.strtolower($operation);
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON company_membership_periods BEGIN SELECT CASE WHEN ($invalid) OR ($overlap) THEN RAISE(ABORT, 'Invalid or overlapping membership period') END; END");
            } else {
                DB::unprepared("CREATE TRIGGER $trigger BEFORE $operation ON company_membership_periods FOR EACH ROW BEGIN IF ($invalid) OR ($overlap) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or overlapping membership period'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS membership_period_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS membership_period_update');
        Schema::dropIfExists('company_membership_periods');
        Schema::table('staff_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
