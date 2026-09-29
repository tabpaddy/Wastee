<?php

namespace App\Models;

use App\Enums\ServiceAreaStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTemporalOverlap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyServiceArea extends Model
{
    use BelongsToCompany, HasTemporalOverlap;

    protected const PERIOD_START = 'active_from';

    protected const PERIOD_END = 'active_to';

    protected $fillable = [
        'ended_by',
        'end_reason',
        'created_by',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
