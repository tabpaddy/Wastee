<?php

namespace App\Models;

use App\Enums\CollectionRecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionRecord extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'property_id',
        'property_occupancy_id',
        'collection_schedule_id',
        'collector_user_id',
        'status',
        'remarks',
        'attempted_at',
        'collected_at',
        'property_snapshot',
        'resident_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionRecordStatus::class,
            'attempted_at' => 'immutable_datetime',
            'collected_at' => 'immutable_datetime',
            'property_snapshot' => 'array',
            'resident_snapshot' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(PropertyOccupancy::class, 'property_occupancy_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CollectionSchedule::class, 'collection_schedule_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_user_id');
    }
}
