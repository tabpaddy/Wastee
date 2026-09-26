<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('property_code')->unique();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->string('building_number')->nullable();
            $table->string('street');
            $table->string('landmark')->nullable();
            $table->string('property_type', 40)->default('residential');
            $table->string('status', 40)->default('active');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['community_id', 'status']);
        });

        Schema::create('property_company_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'assigned_to']);
            $table->index(['company_id', 'assigned_to']);
            $table->unsignedTinyInteger('open_slot')->nullable()->storedAs('CASE WHEN assigned_to IS NULL THEN 1 ELSE NULL END');
            $table->unique(['property_id', 'open_slot']);
        });

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique('user_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('occupation')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('property_occupancies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->string('occupancy_type', 40)->default('tenant');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['resident_id', 'move_out_date']);
            $table->index(['property_id', 'move_out_date']);
            $table->unique(['id', 'resident_id']);
            $table->unique(['id', 'property_id']);
            $table->unsignedTinyInteger('open_slot')->nullable()->storedAs('CASE WHEN move_out_date IS NULL THEN 1 ELSE NULL END');
            $table->unique(['resident_id', 'open_slot']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('property_occupancies');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('property_company_assignments');
        Schema::dropIfExists('properties');
    }
};
