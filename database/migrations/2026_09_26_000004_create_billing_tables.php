<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('property_type', 40)->nullable();
            $table->decimal('amount', 14, 2)->default(null);
            $table->string('billing_cycle', 40)->default('monthly');
            $table->string('status', 40)->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->unique(['id', 'company_id']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('property_occupancy_id')->constrained('property_occupancies')->restrictOnDelete();
            $table->foreignId('service_plan_id')->nullable()->constrained('service_plans')->restrictOnDelete();
            $table->string('invoice_number');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->decimal('subtotal', 14, 2)->default(null);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('late_fee', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(null);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2)->default(null);
            $table->string('currency')->default('NGN');
            $table->string('status', 40)->default('draft');
            $table->datetime('issued_at')->nullable();
            $table->datetime('due_at');
            $table->datetime('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('resident_snapshot');
            $table->json('property_snapshot');
            $table->json('company_snapshot');
            $table->json('plan_snapshot');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['property_occupancy_id', 'status']);
            $table->index(['due_at', 'status']);
            $table->unique(['company_id', 'invoice_number']);
            $table->unique(['id', 'company_id']);
            $table->foreign(['service_plan_id', 'company_id'])->references(['id', 'company_id'])->on('service_plans')->restrictOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('bill_id')->constrained('bills')->restrictOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->string('payment_method', 40);
            $table->decimal('amount', 14, 2)->default(null);
            $table->string('currency')->default('NGN');
            $table->string('status', 40)->default('pending');
            $table->datetime('paid_at')->nullable();
            $table->datetime('verified_at')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['bill_id', 'status']);
            $table->unique(['id', 'company_id']);
            $table->foreign(['bill_id', 'company_id'])->references(['id', 'company_id'])->on('bills')->restrictOnDelete();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->unique('payment_id');
            $table->string('receipt_number');
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->datetime('generated_at');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['company_id', 'receipt_number']);
            $table->foreign(['payment_id', 'company_id'])->references(['id', 'company_id'])->on('payments')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('service_plans');
    }
};
