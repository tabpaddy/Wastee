<?php

namespace App\Filament\Platform\Resources\Companies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')->columns(2)->schema([
                TextEntry::make('name'), TextEntry::make('uuid')->label('Public reference'),
                TextEntry::make('status')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value))->badge(),
                TextEntry::make('registration_number'), TextEntry::make('license_number'),
                TextEntry::make('email'), TextEntry::make('phone'), TextEntry::make('website'),
                TextEntry::make('submitted_at')->dateTime(), TextEntry::make('approved_at')->dateTime(),
                TextEntry::make('approver.name'), TextEntry::make('review_summary')->columnSpanFull(),
            ]),
            Section::make('Owner')->columns(2)->schema([
                TextEntry::make('owner.name'), TextEntry::make('owner.email'),
                TextEntry::make('owner.phone'), TextEntry::make('owner.email_verified_at')->dateTime(),
            ]),
        ]);
    }
}
