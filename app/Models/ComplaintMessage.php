<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'complaint_id',
        'sender_user_id',
        'sender_resident_id',
        'message',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function residentSender(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'sender_resident_id');
    }
}
