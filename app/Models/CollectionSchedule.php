<?php

namespace App\Models;

use App\Enums\CollectionScheduleStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionSchedule extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'collection_zone_id',
        'day_of_week',
        'start_time',
        'end_time',
        'recurrence_weeks',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionScheduleStatus::class,
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(CollectionZone::class, 'collection_zone_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(CollectionRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), CollectionScheduleStatus::Active);
    }
}
