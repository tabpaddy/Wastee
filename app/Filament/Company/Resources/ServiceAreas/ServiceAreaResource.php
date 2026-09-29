<?php

namespace App\Filament\Company\Resources\ServiceAreas;

use App\Models\CompanyServiceArea;
use App\Services\Auth\AuthorizationContext;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceAreaResource extends Resource
{
    protected static ?string $model = CompanyServiceArea::class;

    protected static ?string $slug = 'service-areas';

    protected static ?string $modelLabel = 'service area';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forCompany(app(AuthorizationContext::class)->companyId() ?? -1)->with('community.lga.state', 'creator', 'endedBy');
    }

    public static function table(Table $table): Table
    {
        return Tables\ServiceAreasTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServiceAreas::route('/')];
    }
}
