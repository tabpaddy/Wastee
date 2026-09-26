<?php

namespace App\Enums;

enum CollectionScheduleStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
}
