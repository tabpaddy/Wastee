<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Enums\CompanyStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyApprovalLog extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'actor_user_id',
        'action',
        'from_status',
        'to_status',
        'remarks',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'from_status' => CompanyStatus::class,
            'to_status' => CompanyStatus::class,
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
