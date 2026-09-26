<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use BelongsToCompany, HasFactory, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'payment_id',
        'receipt_number',
        'file_disk',
        'file_path',
        'generated_at',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'snapshot' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
