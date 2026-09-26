<?php

namespace App\Enums;

enum OccupancyType: string
{
    case Tenant = 'tenant';
    case OwnerOccupier = 'owner_occupier';
    case Other = 'other';
}
