<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'name']);
            $table->unique(['id', 'company_id']);
        });

        Schema::create('collection_zone_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('collection_zone_id')->constrained('collection_zones')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'property_id']);
            $table->foreign(['collection_zone_id', 'company_id'])->references(['id', 'company_id'])->on('collection_zones')->restrictOnDelete();
        });

        Schema::create('collection_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('collection_zone_id')->constrained('collection_zones')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('recurrence_weeks')->default(1);
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->unique(['id', 'company_id']);
            $table->foreign(['collection_zone_id', 'company_id'])->references(['id', 'company_id'])->on('collection_zones')->restrictOnDelete();
        });

        Schema::create('collection_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('property_occupancy_id')->nullable()->constrained('property_occupancies')->restrictOnDelete();
            $table->foreignId('collection_schedule_id')->nullable()->constrained('collection_schedules')->restrictOnDelete();
            $table->foreignId('collector_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 40);
            $table->text('remarks')->nullable();
            $table->datetime('attempted_at');
            $table->datetime('collected_at')->nullable();
            $table->json('property_snapshot');
            $table->json('resident_snapshot')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'collected_at']);
            $table->index(['property_id', 'collected_at']);
            $table->foreign(['collection_schedule_id', 'company_id'])->references(['id', 'company_id'])->on('collection_schedules')->restrictOnDelete();
            $table->foreign(['property_occupancy_id', 'property_id'])->references(['id', 'property_id'])->on('property_occupancies')->restrictOnDelete();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->foreignId('property_occupancy_id')->nullable()->constrained('property_occupancies')->restrictOnDelete();
            $table->string('category');
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 40)->default('normal');
            $table->string('status', 40)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->datetime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->foreign(['property_occupancy_id', 'resident_id'])->references(['id', 'resident_id'])->on('property_occupancies')->restrictOnDelete();
            $table->foreign(['company_id', 'assigned_to'])->references(['company_id', 'user_id'])->on('company_memberships')->restrictOnDelete();
        });

        Schema::create('complaint_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->restrictOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('sender_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['complaint_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_messages');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('collection_records');
        Schema::dropIfExists('collection_schedules');
        Schema::dropIfExists('collection_zone_properties');
        Schema::dropIfExists('collection_zones');
    }
};
