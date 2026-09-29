<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'property_code',
        'community_id',
        'building_number',
        'street',
        'landmark',
        'property_type',
        'status',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(PropertyOccupancy::class);
    }

    public function providerAssignments(): HasMany
    {
        return $this->hasMany(PropertyCompanyAssignment::class);
    }

    public function currentProviderAssignment(): HasOne
    {
        return $this->hasOne(PropertyCompanyAssignment::class)->current();
    }

    public function collectionRecords(): HasMany
    {
        return $this->hasMany(CollectionRecord::class);
    }

    public function collectionZones(): BelongsToMany
    {
        return $this->belongsToMany(CollectionZone::class, 'collection_zone_properties')->withPivot('company_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), PropertyStatus::Active);
    }

    public function scopeCurrentlyServedBy(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('providerAssignments', fn (Builder $assignments) => $assignments->forCompany($companyId)->current());
    }

    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('providerAssignments', fn (Builder $assignments) => $assignments->forCompany($companyId)->where('assigned_from', '<=', today()));
    }
}
