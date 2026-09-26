<?php

namespace App\Models;

use App\Enums\OccupancyType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyOccupancy extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
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
}
