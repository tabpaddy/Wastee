<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'currency',
        'invoice_prefix',
        'receipt_prefix',
        'support_email',
        'support_phone',
        'timezone',
    ];
}
