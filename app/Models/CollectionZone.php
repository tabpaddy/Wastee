<?php

namespace App\Models;

use App\Enums\CollectionZoneStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionZone extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionZoneStatus::class,
        ];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'collection_zone_properties')->withPivot('company_id')->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CollectionSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), CollectionZoneStatus::Active);
    }
}
