<?php

namespace App\Filament\Company\Resources\Staff\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StaffInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('user.name')->label('Name'), TextEntry::make('user.email')->label('Email'),
            TextEntry::make('user.phone')->label('Phone'), TextEntry::make('user.roles.name')->label('Roles')->badge(),
            TextEntry::make('status')->formatStateUsing(fn ($state) => ucfirst($state->value))->badge(),
            TextEntry::make('joined_at')->dateTime(), TextEntry::make('left_at')->dateTime(),
            TextEntry::make('user.last_login_at')->dateTime(),
        ]);
    }
}
