<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMembershipPeriod extends Model
{
    protected $fillable = ['joined_at', 'left_at', 'joined_by', 'left_by', 'join_reason', 'leave_reason', 'joined_roles', 'left_roles'];

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime',
            'joined_roles' => 'array', 'left_roles' => 'array'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CompanyMembership::class, 'company_membership_id');
    }

    public function joinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joined_by');
    }

    public function leftBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'left_by');
    }
}
