<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('registration_number')->nullable()->unique();
            $table->string('license_number')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('website')->nullable();
            $table->string('status', 40)->default('draft');
            $table->datetime('submitted_at')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_summary')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('active');
            $table->datetime('joined_at');
            $table->datetime('left_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('document_type', 40);
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 40)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->datetime('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('company_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('label');
            $table->string('address_line');
            $table->string('community')->nullable();
            $table->foreignId('lga_id')->constrained('lgas')->restrictOnDelete();
            $table->foreignId('state_id')->constrained('states')->restrictOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->date('active_from');
            $table->date('active_to')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'is_head_office']);
            $table->foreign(['lga_id', 'state_id'])->references(['id', 'state_id'])->on('lgas')->restrictOnDelete();
            $table->unsignedTinyInteger('open_head_office')->nullable()->storedAs("CASE WHEN active_to IS NULL AND is_head_office = 1 AND status = 'active' THEN 1 ELSE NULL END");
            $table->unique(['company_id', 'open_head_office']);
        });

        Schema::create('company_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('currency')->default('NGN');
            $table->string('invoice_prefix')->default('INV');
            $table->string('receipt_prefix')->default('RCT');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('timezone')->default('Africa/Lagos');
            $table->timestamps();
            $table->unique(['company_id']);
        });

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('email');
            $table->string('token_hash')->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('role_name');
            $table->string('status', 40)->default('pending');
            $table->datetime('expires_at');
            $table->datetime('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->unsignedTinyInteger('pending_slot')->nullable()->storedAs("CASE WHEN status = 'pending' THEN 1 ELSE NULL END");
            $table->unique(['company_id', 'email', 'pending_slot']);
        });

        Schema::create('company_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->date('active_from');
            $table->date('active_to')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['community_id', 'status']);
            $table->unsignedTinyInteger('open_slot')->nullable()->storedAs('CASE WHEN active_to IS NULL THEN 1 ELSE NULL END');
            $table->unique(['company_id', 'community_id', 'open_slot']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('company_service_areas');
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('company_approval_logs');
        Schema::dropIfExists('company_locations');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');
    }
};
