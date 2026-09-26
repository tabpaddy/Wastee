<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PropertyType;
use App\Enums\ServicePlanStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    use BelongsToCompany, HasFactory, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'property_type',
        'amount',
        'billing_cycle',
        'status',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'amount' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'status' => ServicePlanStatus::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), ServicePlanStatus::Active);
    }

    public function scopeCurrent(Builder $query, ?string $on = null): Builder
    {
        $on ??= today()->toDateString();

        return $query->active()
            ->where(fn (Builder $q) => $q->whereDate('effective_from', '<=', $on)->orWhereNull('effective_from'))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>', $on));
    }
}
