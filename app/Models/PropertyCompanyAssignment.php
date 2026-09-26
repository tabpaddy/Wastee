<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyCompanyAssignment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'property_id',
        'company_id',
        'assigned_from',
        'assigned_to',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'immutable_date',
            'assigned_to' => 'immutable_date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $query, ?string $on = null): Builder
    {
        $on ??= today()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereDate('assigned_from', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('assigned_to')->orWhereDate('assigned_to', '>', $on));
    }
}
