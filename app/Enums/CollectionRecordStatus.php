<?php

namespace App\Enums;

enum CollectionRecordStatus: string
{
    case Collected = 'collected';
    case Missed = 'missed';
    case Inaccessible = 'inaccessible';
    case Cancelled = 'cancelled';
}
