<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'registration_number',
        'license_number',
        'email',
        'phone',
        'website',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'review_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CompanyLocation::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(CompanyApprovalLog::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(CompanyServiceArea::class);
    }

    public function servicePlans(): HasMany
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function collectionZones(): HasMany
    {
        return $this->hasMany(CollectionZone::class);
    }

    public function collectionSchedules(): HasMany
    {
        return $this->hasMany(CollectionSchedule::class);
    }

    public function collectionRecords(): HasMany
    {
        return $this->hasMany(CollectionRecord::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function providerAssignments(): HasMany
    {
        return $this->hasMany(PropertyCompanyAssignment::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::Approved);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::PendingReview);
    }
}
