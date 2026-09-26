<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('country_code')->default('NG');
            $table->timestamps();
        });

        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->unique(['state_id', 'name']);
            $table->unique(['id', 'state_id']);
        });

        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lga_id')->constrained('lgas')->restrictOnDelete();
            $table->string('name');
            $table->string('ward')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->unique(['lga_id', 'name']);
            $table->index(['lga_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('states');
    }
};
