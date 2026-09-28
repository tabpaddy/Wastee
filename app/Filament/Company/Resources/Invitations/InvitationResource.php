<?php

namespace App\Filament\Company\Resources\Invitations;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Models\StaffInvitation;
use App\Services\Auth\AuthorizationContext;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InvitationResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = StaffInvitation::class;

    protected static ?string $slug = 'invitations';

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('company_id', app(AuthorizationContext::class)->companyId() ?? -1)->with(['inviter', 'acceptedBy']);
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
        return Tables\InvitationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvitations::route('/')];
    }
}
