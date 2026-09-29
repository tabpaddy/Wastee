<?php

namespace App\Filament\Platform\Resources\Communities;

use App\Models\Community;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunityResource extends Resource
{
    protected static ?string $model = Community::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Reference data';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! auth()->user()?->can('platform.geography.view'), fn ($query) => $query->whereRaw('1 = 0'))->with('lga.state');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return Tables\CommunitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCommunities::route('/')];
    }
}
