<?php

namespace App\Models;

use App\Enums\ServiceAreaStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyServiceArea extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'community_id',
        'active_from',
        'active_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'immutable_date',
            'active_to' => 'immutable_date',
            'status' => ServiceAreaStatus::class,
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), ServiceAreaStatus::Active);
    }

    public function scopeCurrent(Builder $query, ?string $on = null): Builder
    {
        $on ??= today()->toDateString();

        return $query->active()
            ->where(fn (Builder $q) => $q->whereDate('active_from', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('active_to')->orWhereDate('active_to', '>', $on));
    }
}
