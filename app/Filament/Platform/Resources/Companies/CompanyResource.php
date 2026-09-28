<?php

namespace App\Filament\Platform\Resources\Companies;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Models\Company;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CompanyResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = Company::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return Gate::allows('platform.companies.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forReviewerQueue()->when(! static::canViewAny(), fn ($q) => $q->whereRaw('1 = 0'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\CompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\CompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DocumentsRelationManager::class, RelationManagers\LocationsRelationManager::class,
            RelationManagers\ApprovalLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCompanies::route('/'), 'view' => Pages\ViewCompany::route('/{record}')];
    }
}
