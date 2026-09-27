<?php

namespace App\Filament\Platform\Resources\Companies\Pages;

use App\Filament\Platform\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;
}
