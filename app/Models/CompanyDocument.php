<?php

namespace App\Models;

use App\Enums\CompanyDocumentType;
use App\Enums\DocumentReviewStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDocument extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'document_type',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => CompanyDocumentType::class,
            'status' => DocumentReviewStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
