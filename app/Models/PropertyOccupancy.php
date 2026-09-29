<?php

namespace App\Models;

use App\Enums\OccupancyType;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\HasTemporalOverlap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyOccupancy extends Model
{
    use HasFactory, HasPublicUuid, HasTemporalOverlap;

    protected const PERIOD_START = 'move_in_date';

    protected const PERIOD_END = 'move_out_date';

    protected $fillable = [
        'ended_by',
        'end_reason',
        'is_billing_contact',
        'resident_id',
        'property_id',
        'move_in_date',
        'move_out_date',
        'occupancy_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_billing_contact' => 'boolean',
            'move_in_date' => 'immutable_date',
            'move_out_date' => 'immutable_date',
            'occupancy_type' => OccupancyType::class,
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function scopeCurrent(Builder $query, ?string $on = null): Builder
    {
        $on ??= today()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereDate('move_in_date', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('move_out_date')->orWhereDate('move_out_date', '>', $on));
    }

    public function scopeBillingContact(Builder $query): Builder
    {
        return $query->where('is_billing_contact', true);
    }

    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        // Both periods must have begun; touching half-open endpoints are not a service relationship.
        return $query->where('move_in_date', '<=', today())
            ->whereExists(function ($assignments) use ($companyId): void {
                $assignments->selectRaw('1')->from('property_company_assignments as service_history')
                    ->whereColumn('service_history.property_id', 'property_occupancies.property_id')
                    ->where('service_history.company_id', $companyId)->where('service_history.assigned_from', '<=', today())
                    ->where(fn ($query) => $query->whereNull('service_history.assigned_to')
                        ->orWhereRaw('DATE(service_history.assigned_to) > DATE(property_occupancies.move_in_date)'))
                    ->where(fn ($query) => $query->whereNull('property_occupancies.move_out_date')
                        ->orWhereRaw('DATE(property_occupancies.move_out_date) > DATE(service_history.assigned_from)'));
            });
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
