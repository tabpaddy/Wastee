<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case CorrectionRequired = 'correction_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
