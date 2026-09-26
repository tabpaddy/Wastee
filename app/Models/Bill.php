<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use BelongsToCompany, HasFactory, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'property_occupancy_id',
        'service_plan_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'subtotal',
        'discount_amount',
        'late_fee',
        'total_amount',
        'amount_paid',
        'outstanding_amount',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'created_by',
        'resident_snapshot',
        'property_snapshot',
        'company_snapshot',
        'plan_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'immutable_date',
            'billing_period_end' => 'immutable_date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'status' => BillStatus::class,
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'resident_snapshot' => 'array',
            'property_snapshot' => 'array',
            'company_snapshot' => 'array',
            'plan_snapshot' => 'array',
        ];
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(PropertyOccupancy::class, 'property_occupancy_id');
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [BillStatus::Issued, BillStatus::PartiallyPaid])->where('outstanding_amount', '>', 0);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->where('due_at', '<', now());
    }
}
