<?php

namespace App\Filament\Company\Resources\Staff;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Models\CompanyMembership;
use App\Services\Auth\AuthorizationContext;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = CompanyMembership::class;

    protected static ?string $slug = 'staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $recordRouteKeyName = 'user_uuid';

    protected static bool $isScopedToTenant = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forStaffList(app(AuthorizationContext::class)->companyId() ?? -1);
    }

    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        $query = static::getEloquentQuery()->whereHas('user', fn ($users) => $users->where('uuid', $key));
        if ($modifyQuery) {
            $query = $modifyQuery($query) ?? $query;
        }

        return $query->first();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return Tables\StaffTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\StaffInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\MembershipPeriodsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStaff::route('/'), 'view' => Pages\ViewStaff::route('/{record}')];
    }
}
